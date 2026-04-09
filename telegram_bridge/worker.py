import asyncio
import json
import os
import random
from contextlib import closing
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, Optional

import pymysql
from dotenv import load_dotenv
from openai import OpenAI
from telethon import TelegramClient, events
from telethon.errors import FloodWaitError

load_dotenv()

UNSET = object()

API_ID = os.getenv("TELEGRAM_API_ID")
API_HASH = os.getenv("TELEGRAM_API_HASH")
SESSION_DIR = os.getenv("SESSION_DIR", "./sessions")

DB_HOST = os.getenv("DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("DB_PORT", "3306"))
DB_DATABASE = os.getenv("DB_DATABASE")
DB_USERNAME = os.getenv("DB_USERNAME")
DB_PASSWORD = os.getenv("DB_PASSWORD", "")

WORKER_RESCAN_SECONDS = int(os.getenv("WORKER_RESCAN_SECONDS", "30"))

OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")
OPENAI_MODEL = os.getenv("OPENAI_MODEL", "gpt-5-mini")

openai_client = OpenAI(api_key=OPENAI_API_KEY) if OPENAI_API_KEY else None

if not API_ID or not API_HASH:
    raise RuntimeError("Missing TELEGRAM_API_ID or TELEGRAM_API_HASH in telegram_bridge/.env")

if not DB_DATABASE or not DB_USERNAME:
    raise RuntimeError("Missing DB_DATABASE or DB_USERNAME in telegram_bridge/.env")

API_ID = int(API_ID)

session_path = Path(SESSION_DIR)
session_path.mkdir(parents=True, exist_ok=True)


def utc_now() -> datetime:
    return datetime.now(timezone.utc)


def build_session_file(session_name: str) -> str:
    safe_name = "".join(c for c in session_name if c.isalnum() or c in ("-", "_"))
    return str(session_path / safe_name)


def db_connection():
    return pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USERNAME,
        password=DB_PASSWORD,
        database=DB_DATABASE,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
        charset="utf8mb4",
    )


def db_fetch_all(query: str, params=None):
    with closing(db_connection()) as conn:
        with conn.cursor() as cursor:
            cursor.execute(query, params or [])
            return cursor.fetchall()


def db_fetch_one(query: str, params=None):
    with closing(db_connection()) as conn:
        with conn.cursor() as cursor:
            cursor.execute(query, params or [])
            return cursor.fetchone()


def db_execute(query: str, params=None):
    with closing(db_connection()) as conn:
        with conn.cursor() as cursor:
            cursor.execute(query, params or [])
            return cursor.lastrowid


def parse_keywords(raw_keywords) -> list[str]:
    if raw_keywords is None:
        return []

    if isinstance(raw_keywords, list):
        return [str(k).strip().lower() for k in raw_keywords if str(k).strip()]

    if isinstance(raw_keywords, str):
        raw_keywords = raw_keywords.strip()
        if not raw_keywords:
            return []

        try:
            decoded = json.loads(raw_keywords)
            if isinstance(decoded, list):
                return [str(k).strip().lower() for k in decoded if str(k).strip()]
        except json.JSONDecodeError:
            pass

        return [
            str(k).strip().lower()
            for k in raw_keywords.replace("\r", "\n").replace(",", "\n").split("\n")
            if str(k).strip()
        ]

    return []


def normalize_text(value: str) -> str:
    return (value or "").strip().lower()


def generate_ai_reply(row: dict, incoming_message: str) -> Optional[str]:
    if not openai_client:
        print("[ai] OPENAI_API_KEY missing, skipping AI reply.")
        return None

    trimmed_message = (incoming_message or "")[:1000]

    system_prompt = (
        row.get("ai_instructions")
        or "Reply naturally, briefly, and conversationally. Keep it short."
    ).strip()

    try:
        response = openai_client.responses.create(
            model=OPENAI_MODEL,
            input=[
                {
                    "role": "system",
                    "content": system_prompt,
                },
                {
                    "role": "user",
                    "content": f"Incoming Telegram message: {trimmed_message}\n\nReply in 1 short message only.",
                },
            ],
            reasoning={"effort": "low"},
            text={"verbosity": "low"},
            max_output_tokens=300,
        )

        print(f"[ai] status: {getattr(response, 'status', None)}")
        print(f"[ai] incomplete_details: {getattr(response, 'incomplete_details', None)}")
        print(f"[ai] output_text: {repr(getattr(response, 'output_text', ''))}")

        text = (getattr(response, "output_text", "") or "").strip()
        if text:
            return text

        output = getattr(response, "output", None) or []
        for item in output:
            if getattr(item, "type", None) == "message":
                content = getattr(item, "content", None) or []
                for part in content:
                    if getattr(part, "type", None) == "output_text":
                        candidate = (getattr(part, "text", "") or "").strip()
                        if candidate:
                            return candidate

        return None

    except Exception as e:
        print(f"[ai] reply generation failed: {e}")
        return None


def get_trigger_replies_for_user(user_id: int) -> list[dict]:
    return db_fetch_all(
        """
        SELECT
            id,
            user_id,
            is_enabled,
            trigger_type,
            match_type,
            keywords,
            message_count,
            reply_text,
            fire_once_per_chat,
            sort_order
        FROM telegram_trigger_replies
        WHERE user_id = %s
          AND is_enabled = 1
        ORDER BY sort_order ASC, id ASC
        """,
        [user_id],
    )


class WorkerManager:
    def __init__(self):
        self.active_clients: Dict[int, TelegramClient] = {}
        self.client_tasks: Dict[int, asyncio.Task] = {}
        self.user_contexts: Dict[int, dict] = {}

    async def run(self):
        print("Telegram worker started.")

        while True:
            try:
                await self.sync_clients()
            except Exception as e:
                print(f"[manager] sync error: {e}")

            await asyncio.sleep(WORKER_RESCAN_SECONDS)

    async def sync_clients(self):
        rows = self.get_active_user_rows()
        desired_user_ids = {row["user_id"] for row in rows}
        current_user_ids = set(self.active_clients.keys())

        for row in rows:
            user_id = row["user_id"]

            if user_id not in self.active_clients:
                await self.start_client_for_user(row)
            else:
                self.user_contexts[user_id] = row

            await self.process_idle_follow_ups_for_user(user_id, row)

        users_to_remove = current_user_ids - desired_user_ids
        for user_id in users_to_remove:
            await self.stop_client_for_user(user_id)

    def get_active_user_rows(self):
        query = """
            SELECT
                tc.id AS telegram_connection_id,
                tc.user_id,
                tc.phone_number,
                tc.session_name,
                tc.status AS connection_status,
                ta.id AS telegram_automation_id,
                ta.is_enabled,
                ta.ai_instructions,
                ta.daily_message_limit,
                ta.per_chat_cooldown_minutes,
                ta.mark_seen_delay_min_seconds,
                ta.mark_seen_delay_max_seconds,
                ta.typing_delay_min_seconds,
                ta.typing_delay_max_seconds,
                ta.idle_follow_up_minutes,
                ta.idle_follow_up_message
            FROM telegram_connections tc
            INNER JOIN telegram_automations ta
                ON ta.user_id = tc.user_id
            WHERE tc.status = 'connected'
              AND ta.is_enabled = 1
              AND tc.session_name IS NOT NULL
        """
        return db_fetch_all(query)

    async def start_client_for_user(self, row: dict):
        user_id = row["user_id"]
        session_name = row["session_name"]
        session_file = build_session_file(session_name)

        sqlite_session_file = Path(session_file + ".session")
        if not sqlite_session_file.exists():
            print(f"[user {user_id}] session file missing, skipping.")
            self.mark_connection_failed(user_id, "Telegram session file is missing.")
            return

        client = TelegramClient(session_file, API_ID, API_HASH)
        await client.connect()

        try:
            if not await client.is_user_authorized():
                print(f"[user {user_id}] session is not authorized, skipping.")
                self.mark_connection_failed(user_id, "Telegram session is no longer authorized.")
                await client.disconnect()
                return
        except Exception:
            await client.disconnect()
            raise

        @client.on(events.NewMessage(incoming=True))
        async def on_new_message(event):
            try:
                await self.handle_new_message(user_id, event)
            except Exception as e:
                print(f"[user {user_id}] message handler error: {e}")

        self.active_clients[user_id] = client
        self.user_contexts[user_id] = row

        task = asyncio.create_task(self.keep_client_alive(user_id, client))
        self.client_tasks[user_id] = task

        print(f"[user {user_id}] client started and listening.")

    async def stop_client_for_user(self, user_id: int):
        print(f"[user {user_id}] stopping client.")

        task = self.client_tasks.pop(user_id, None)
        if task:
            task.cancel()

        client = self.active_clients.pop(user_id, None)
        self.user_contexts.pop(user_id, None)

        if client:
            try:
                await client.disconnect()
            except Exception as e:
                print(f"[user {user_id}] disconnect error: {e}")

    async def keep_client_alive(self, user_id: int, client: TelegramClient):
        try:
            await client.run_until_disconnected()
        except asyncio.CancelledError:
            pass
        except Exception as e:
            print(f"[user {user_id}] client crashed: {e}")
            self.mark_connection_failed(user_id, f"Worker client crashed: {e}")
        finally:
            self.active_clients.pop(user_id, None)
            self.client_tasks.pop(user_id, None)

    async def handle_new_message(self, user_id: int, event):
        row = self.user_contexts.get(user_id)
        if not row:
            print(f"[user {user_id}] missing context, skipping event.")
            return

        client = self.active_clients.get(user_id)
        if not client:
            print(f"[user {user_id}] missing client, skipping event.")
            return

        if event.is_private is not True:
            return

        if event.out:
            return

        sender = await event.get_sender()
        me = await client.get_me()

        if sender and me and getattr(sender, "id", None) == getattr(me, "id", None):
            return

        chat = await event.get_chat()
        chat_id = str(event.chat_id)
        telegram_message_id = str(event.message.id) if event.message and event.message.id else None
        message_text = event.raw_text or ""

        print(f"[user {user_id}] incoming private message from chat {chat_id}: {message_text[:80]!r}")

        self.log_message(
            user_id=user_id,
            telegram_connection_id=row["telegram_connection_id"],
            chat_id=chat_id,
            telegram_message_id=telegram_message_id,
            direction="incoming",
            message_text=message_text,
            matched_keyword=None,
            status="received",
            sent_at=None,
        )

        chat_state = self.get_or_create_chat_state(user_id, chat_id)
        self.reset_reply_count_if_needed(chat_state)

        incoming_message_count = int(chat_state.get("incoming_message_count") or 0) + 1
        now = utc_now()

        last_incoming_after_reply_at = now if chat_state.get("last_outgoing_message_at") else None

        self.upsert_chat_state(
            user_id=user_id,
            chat_id=chat_id,
            last_incoming_message_at=now,
            last_message_text=message_text,
            incoming_message_count=incoming_message_count,
            reply_count_today=int(chat_state.get("reply_count_today") or 0),
            reply_count_date=chat_state.get("reply_count_date"),
            last_incoming_after_reply_at=last_incoming_after_reply_at,
            idle_follow_up_sent_at=None,
        )

        chat_state["incoming_message_count"] = incoming_message_count
        chat_state["last_incoming_after_reply_at"] = last_incoming_after_reply_at
        chat_state["idle_follow_up_sent_at"] = None
        chat_state["last_incoming_message_at"] = now

        if self.is_in_cooldown(chat_state, int(row["per_chat_cooldown_minutes"])):
            self.log_message(
                user_id=user_id,
                telegram_connection_id=row["telegram_connection_id"],
                chat_id=chat_id,
                telegram_message_id=telegram_message_id,
                direction="incoming",
                message_text=message_text,
                matched_keyword=None,
                status="skipped_cooldown",
                sent_at=None,
            )
            print(f"[user {user_id}] skipped: cooldown active.")
            return

        if int(chat_state["reply_count_today"]) >= int(row["daily_message_limit"]):
            self.log_message(
                user_id=user_id,
                telegram_connection_id=row["telegram_connection_id"],
                chat_id=chat_id,
                telegram_message_id=telegram_message_id,
                direction="incoming",
                message_text=message_text,
                matched_keyword=None,
                status="skipped_daily_limit",
                sent_at=None,
            )
            print(f"[user {user_id}] skipped: daily limit reached.")
            return

        trigger_rules = get_trigger_replies_for_user(user_id)
        matched_rule = self.find_matching_trigger_rule(
            chat_state=chat_state,
            message_text=message_text,
            trigger_rules=trigger_rules,
        )

        reply_text = None
        matched_keyword = None
        reply_source = None

        if matched_rule:
            reply_text = (matched_rule.get("reply_text") or "").strip()
            matched_keyword = matched_rule.get("_matched_keyword")
            reply_source = "trigger_rule"
            print(f"[user {user_id}] matched trigger rule #{matched_rule['id']}")

        if not reply_text:
            ai_reply = generate_ai_reply(row, message_text)
            if ai_reply:
                reply_text = ai_reply
                reply_source = "ai"
                print(f"[user {user_id}] using AI reply.")

        if not reply_text:
            self.log_message(
                user_id=user_id,
                telegram_connection_id=row["telegram_connection_id"],
                chat_id=chat_id,
                telegram_message_id=telegram_message_id,
                direction="incoming",
                message_text=message_text,
                matched_keyword=matched_keyword,
                status="skipped_no_reply_generated",
                sent_at=None,
            )
            print(f"[user {user_id}] skipped: no trigger reply and AI returned empty.")
            return

        seen_delay = self.random_delay(
            int(row["mark_seen_delay_min_seconds"]),
            int(row["mark_seen_delay_max_seconds"]),
        )
        typing_delay = self.random_delay(
            int(row["typing_delay_min_seconds"]),
            int(row["typing_delay_max_seconds"]),
        )

        await asyncio.sleep(seen_delay)

        try:
            await client.send_read_acknowledge(chat)
        except Exception as e:
            print(f"[user {user_id}] read acknowledge failed: {e}")

        async with client.action(chat, "typing"):
            await asyncio.sleep(typing_delay)

        try:
            sent_message = await client.send_message(chat, reply_text)
        except FloodWaitError as e:
            self.log_message(
                user_id=user_id,
                telegram_connection_id=row["telegram_connection_id"],
                chat_id=chat_id,
                telegram_message_id=telegram_message_id,
                direction="outgoing",
                message_text=reply_text,
                matched_keyword=matched_keyword,
                status=f"failed_flood_wait_{e.seconds}s",
                sent_at=None,
            )
            print(f"[user {user_id}] flood wait: {e.seconds}s")
            return
        except Exception as e:
            self.log_message(
                user_id=user_id,
                telegram_connection_id=row["telegram_connection_id"],
                chat_id=chat_id,
                telegram_message_id=telegram_message_id,
                direction="outgoing",
                message_text=reply_text,
                matched_keyword=matched_keyword,
                status=f"failed_send:{str(e)[:180]}",
                sent_at=None,
            )
            print(f"[user {user_id}] send failed: {e}")
            return

        sent_at = utc_now()
        today_str = sent_at.date().isoformat()
        new_reply_count = int(chat_state["reply_count_today"]) + 1

        self.upsert_chat_state(
            user_id=user_id,
            chat_id=chat_id,
            last_incoming_message_at=now,
            last_replied_at=now,
            last_outgoing_message_at=sent_at,
            last_incoming_after_reply_at=None,
            idle_follow_up_sent_at=None,
            last_message_text=message_text,
            incoming_message_count=incoming_message_count,
            reply_count_today=new_reply_count,
            reply_count_date=today_str,
        )

        if matched_rule:
            should_mark_fired = (
                bool(matched_rule.get("fire_once_per_chat"))
                or matched_rule.get("trigger_type") == "message_count"
            )

            if should_mark_fired:
                self.mark_trigger_rule_fired(user_id, chat_id, int(matched_rule["id"]))

        self.log_message(
            user_id=user_id,
            telegram_connection_id=row["telegram_connection_id"],
            chat_id=chat_id,
            telegram_message_id=str(sent_message.id) if sent_message and sent_message.id else None,
            direction="outgoing",
            message_text=reply_text,
            matched_keyword=matched_keyword,
            status=f"sent_{reply_source}",
            sent_at=sent_at,
        )

        print(f"[user {user_id}] replied successfully to chat {chat_id}.")

    async def process_idle_follow_ups_for_user(self, user_id: int, row: dict):
        client = self.active_clients.get(user_id)
        if not client:
            return

        idle_minutes = int(row.get("idle_follow_up_minutes") or 0)
        idle_message = (row.get("idle_follow_up_message") or "").strip()

        if idle_minutes <= 0 or not idle_message:
            return

        chats = self.get_chats_needing_idle_follow_up(user_id)
        now = utc_now()

        for chat_state in chats:
            last_outgoing_at = chat_state.get("last_outgoing_message_at")
            if not last_outgoing_at:
                continue

            if isinstance(last_outgoing_at, str):
                try:
                    last_outgoing_at = datetime.fromisoformat(last_outgoing_at)
                except ValueError:
                    continue

            if last_outgoing_at.tzinfo is None:
                last_outgoing_at = last_outgoing_at.replace(tzinfo=timezone.utc)

            elapsed_seconds = (now - last_outgoing_at).total_seconds()
            if elapsed_seconds < idle_minutes * 60:
                continue

            chat_id = str(chat_state["chat_id"])

            try:
                entity = await client.get_entity(int(chat_id))
            except Exception as e:
                print(f"[user {user_id}] idle follow-up get_entity failed for chat {chat_id}: {e}")
                continue

            try:
                sent_message = await client.send_message(entity, idle_message)
            except FloodWaitError as e:
                print(f"[user {user_id}] idle follow-up flood wait: {e.seconds}s")
                continue
            except Exception as e:
                print(f"[user {user_id}] idle follow-up send failed for chat {chat_id}: {e}")
                continue

            self.upsert_chat_state(
                user_id=user_id,
                chat_id=chat_id,
                last_outgoing_message_at=now,
                idle_follow_up_sent_at=now,
            )

            self.log_message(
                user_id=user_id,
                telegram_connection_id=row["telegram_connection_id"],
                chat_id=chat_id,
                telegram_message_id=str(sent_message.id) if sent_message and sent_message.id else None,
                direction="outgoing",
                message_text=idle_message,
                matched_keyword=None,
                status="sent_idle_follow_up",
                sent_at=now,
            )

            print(f"[user {user_id}] idle follow-up sent to chat {chat_id}.")

    def get_chats_needing_idle_follow_up(self, user_id: int) -> list[dict]:
        return db_fetch_all(
            """
            SELECT *
            FROM telegram_chat_states
            WHERE user_id = %s
              AND last_outgoing_message_at IS NOT NULL
              AND last_incoming_after_reply_at IS NULL
              AND idle_follow_up_sent_at IS NULL
            """,
            [user_id],
        )

    def find_matching_trigger_rule(self, chat_state: dict, message_text: str, trigger_rules: list[dict]) -> Optional[dict]:
        haystack = normalize_text(message_text)
        incoming_message_count = int(chat_state.get("incoming_message_count") or 0)
        chat_id = str(chat_state["chat_id"])
        user_id = int(chat_state["user_id"])

        for rule in trigger_rules:
            rule_id = int(rule["id"])

            if bool(rule.get("fire_once_per_chat")) and self.has_trigger_rule_fired(user_id, chat_id, rule_id):
                continue

            trigger_type = (rule.get("trigger_type") or "keyword").strip()

            if trigger_type == "message_count":
                if self.has_trigger_rule_fired(user_id, chat_id, rule_id):
                    continue

                target = int(rule.get("message_count") or 0)
                if target > 0 and incoming_message_count == target:
                    rule["_matched_keyword"] = None
                    return rule
                continue

            raw_keywords = rule.get("keywords")
            keywords = parse_keywords(raw_keywords)

            if not keywords:
                continue

            match_type = (rule.get("match_type") or "any").strip()

            if match_type == "all":
                if all(keyword in haystack for keyword in keywords):
                    rule["_matched_keyword"] = ", ".join(keywords)
                    return rule
            else:
                for keyword in keywords:
                    if keyword in haystack:
                        rule["_matched_keyword"] = keyword
                        return rule

        return None

    def has_trigger_rule_fired(self, user_id: int, chat_id: str, trigger_reply_id: int) -> bool:
        row = db_fetch_one(
            """
            SELECT id
            FROM telegram_trigger_reply_fires
            WHERE user_id = %s
              AND chat_id = %s
              AND trigger_reply_id = %s
            LIMIT 1
            """,
            [user_id, chat_id, trigger_reply_id],
        )
        return bool(row)

    def mark_trigger_rule_fired(self, user_id: int, chat_id: str, trigger_reply_id: int):
        existing = db_fetch_one(
            """
            SELECT id
            FROM telegram_trigger_reply_fires
            WHERE user_id = %s
              AND chat_id = %s
              AND trigger_reply_id = %s
            LIMIT 1
            """,
            [user_id, chat_id, trigger_reply_id],
        )

        if existing:
            db_execute(
                """
                UPDATE telegram_trigger_reply_fires
                SET fired_at = %s,
                    updated_at = %s
                WHERE id = %s
                """,
                [utc_now(), utc_now(), existing["id"]],
            )
            return

        db_execute(
            """
            INSERT INTO telegram_trigger_reply_fires
            (user_id, chat_id, trigger_reply_id, fired_at, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s)
            """,
            [user_id, chat_id, trigger_reply_id, utc_now(), utc_now(), utc_now()],
        )

    def random_delay(self, min_seconds: int, max_seconds: int) -> int:
        if min_seconds > max_seconds:
            min_seconds, max_seconds = max_seconds, min_seconds
        return random.randint(min_seconds, max_seconds)

    def get_or_create_chat_state(self, user_id: int, chat_id: str) -> dict:
        row = db_fetch_one(
            """
            SELECT *
            FROM telegram_chat_states
            WHERE user_id = %s AND chat_id = %s
            LIMIT 1
            """,
            [user_id, chat_id],
        )

        if row:
            return row

        now = utc_now()
        db_execute(
            """
            INSERT INTO telegram_chat_states
            (
                user_id,
                chat_id,
                last_incoming_message_at,
                last_replied_at,
                last_outgoing_message_at,
                last_incoming_after_reply_at,
                idle_follow_up_sent_at,
                incoming_message_count,
                reply_count_today,
                reply_count_date,
                last_message_text,
                created_at,
                updated_at
            )
            VALUES (%s, %s, NULL, NULL, NULL, NULL, NULL, 0, 0, %s, NULL, %s, %s)
            """,
            [user_id, chat_id, now.date().isoformat(), now, now],
        )

        return db_fetch_one(
            """
            SELECT *
            FROM telegram_chat_states
            WHERE user_id = %s AND chat_id = %s
            LIMIT 1
            """,
            [user_id, chat_id],
        )

    def reset_reply_count_if_needed(self, chat_state: dict):
        today_str = utc_now().date().isoformat()
        current_date = chat_state.get("reply_count_date")

        if isinstance(current_date, datetime):
            current_date = current_date.date().isoformat()

        if str(current_date) != today_str:
            db_execute(
                """
                UPDATE telegram_chat_states
                SET reply_count_today = 0,
                    reply_count_date = %s,
                    updated_at = %s
                WHERE id = %s
                """,
                [today_str, utc_now(), chat_state["id"]],
            )
            chat_state["reply_count_today"] = 0
            chat_state["reply_count_date"] = today_str

    def is_in_cooldown(self, chat_state: dict, cooldown_minutes: int) -> bool:
        if cooldown_minutes <= 0:
            return False

        last_replied_at = chat_state.get("last_replied_at")
        if not last_replied_at:
            return False

        if isinstance(last_replied_at, str):
            try:
                last_replied_at = datetime.fromisoformat(last_replied_at)
            except ValueError:
                return False

        if last_replied_at.tzinfo is None:
            last_replied_at = last_replied_at.replace(tzinfo=timezone.utc)

        elapsed_seconds = (utc_now() - last_replied_at).total_seconds()
        return elapsed_seconds < (cooldown_minutes * 60)

    def upsert_chat_state(
        self,
        user_id: int,
        chat_id: str,
        last_incoming_message_at=UNSET,
        last_replied_at=UNSET,
        last_outgoing_message_at=UNSET,
        last_incoming_after_reply_at=UNSET,
        idle_follow_up_sent_at=UNSET,
        incoming_message_count=UNSET,
        reply_count_today=UNSET,
        reply_count_date=UNSET,
        last_message_text=UNSET,
    ):
        existing = db_fetch_one(
            """
            SELECT id
            FROM telegram_chat_states
            WHERE user_id = %s AND chat_id = %s
            LIMIT 1
            """,
            [user_id, chat_id],
        )

        now = utc_now()

        if existing:
            updates = []
            params = []

            if last_incoming_message_at is not UNSET:
                updates.append("last_incoming_message_at = %s")
                params.append(last_incoming_message_at)

            if last_replied_at is not UNSET:
                updates.append("last_replied_at = %s")
                params.append(last_replied_at)

            if last_outgoing_message_at is not UNSET:
                updates.append("last_outgoing_message_at = %s")
                params.append(last_outgoing_message_at)

            if last_incoming_after_reply_at is not UNSET:
                updates.append("last_incoming_after_reply_at = %s")
                params.append(last_incoming_after_reply_at)

            if idle_follow_up_sent_at is not UNSET:
                updates.append("idle_follow_up_sent_at = %s")
                params.append(idle_follow_up_sent_at)

            if incoming_message_count is not UNSET:
                updates.append("incoming_message_count = %s")
                params.append(incoming_message_count)

            if reply_count_today is not UNSET:
                updates.append("reply_count_today = %s")
                params.append(reply_count_today)

            if reply_count_date is not UNSET:
                updates.append("reply_count_date = %s")
                params.append(reply_count_date)

            if last_message_text is not UNSET:
                updates.append("last_message_text = %s")
                params.append(last_message_text)

            updates.append("updated_at = %s")
            params.append(now)
            params.append(existing["id"])

            db_execute(
                f"""
                UPDATE telegram_chat_states
                SET {", ".join(updates)}
                WHERE id = %s
                """,
                params,
            )
        else:
            db_execute(
                """
                INSERT INTO telegram_chat_states
                (
                    user_id,
                    chat_id,
                    last_incoming_message_at,
                    last_replied_at,
                    last_outgoing_message_at,
                    last_incoming_after_reply_at,
                    idle_follow_up_sent_at,
                    incoming_message_count,
                    reply_count_today,
                    reply_count_date,
                    last_message_text,
                    created_at,
                    updated_at
                )
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                [
                    user_id,
                    chat_id,
                    None if last_incoming_message_at is UNSET else last_incoming_message_at,
                    None if last_replied_at is UNSET else last_replied_at,
                    None if last_outgoing_message_at is UNSET else last_outgoing_message_at,
                    None if last_incoming_after_reply_at is UNSET else last_incoming_after_reply_at,
                    None if idle_follow_up_sent_at is UNSET else idle_follow_up_sent_at,
                    0 if incoming_message_count is UNSET else incoming_message_count,
                    0 if reply_count_today is UNSET else reply_count_today,
                    utc_now().date().isoformat() if reply_count_date is UNSET else reply_count_date,
                    None if last_message_text is UNSET else last_message_text,
                    now,
                    now,
                ],
            )

    def log_message(
        self,
        user_id: int,
        telegram_connection_id: int,
        chat_id: str,
        telegram_message_id: Optional[str],
        direction: str,
        message_text: Optional[str],
        matched_keyword: Optional[str],
        status: Optional[str],
        sent_at: Optional[datetime],
    ):
        db_execute(
            """
            INSERT INTO telegram_message_logs
            (
                user_id,
                telegram_connection_id,
                chat_id,
                telegram_message_id,
                direction,
                message_text,
                matched_keyword,
                status,
                sent_at,
                created_at,
                updated_at
            )
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """,
            [
                user_id,
                telegram_connection_id,
                chat_id,
                telegram_message_id,
                direction,
                message_text,
                matched_keyword,
                status,
                sent_at,
                utc_now(),
                utc_now(),
            ],
        )

    def mark_connection_failed(self, user_id: int, message: str):
        db_execute(
            """
            UPDATE telegram_connections
            SET status = 'failed',
                connected_at = NULL,
                last_error = %s,
                last_error_at = %s,
                updated_at = %s
            WHERE user_id = %s
            """,
            [message[:65535], utc_now(), utc_now(), user_id],
        )


async def main():
    manager = WorkerManager()
    await manager.run()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("Telegram worker stopped.")
