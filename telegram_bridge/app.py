import asyncio
import os
from pathlib import Path

from dotenv import load_dotenv
from flask import Flask, jsonify, request
from telethon import TelegramClient
from telethon.errors import (
    PasswordHashInvalidError,
    PhoneCodeExpiredError,
    PhoneCodeInvalidError,
    SessionPasswordNeededError,
)
from telethon.sessions import StringSession

load_dotenv()

API_ID = os.getenv("TELEGRAM_API_ID")
API_HASH = os.getenv("TELEGRAM_API_HASH")
BRIDGE_HOST = os.getenv("BRIDGE_HOST", "127.0.0.1")
BRIDGE_PORT = int(os.getenv("BRIDGE_PORT", "5000"))
SESSION_DIR = os.getenv("SESSION_DIR", "./sessions")

if not API_ID or not API_HASH:
    raise RuntimeError("Missing TELEGRAM_API_ID or TELEGRAM_API_HASH in telegram_bridge/.env")

API_ID = int(API_ID)

app = Flask(__name__)

session_path = Path(SESSION_DIR)
session_path.mkdir(parents=True, exist_ok=True)


def build_session_file(session_name: str) -> str:
    safe_name = "".join(c for c in session_name if c.isalnum() or c in ("-", "_"))
    return str(session_path / safe_name)


def json_error(message: str, status_code: int = 400):
    return jsonify({
        "ok": False,
        "message": message,
    }), status_code


async def send_code_async(phone_number: str, session_name: str):
    session_file = build_session_file(session_name)
    client = TelegramClient(session_file, API_ID, API_HASH)

    await client.connect()

    try:
        result = await client.send_code_request(phone_number)

        return {
            "ok": True,
            "phone_code_hash": result.phone_code_hash,
        }
    finally:
        await client.disconnect()


async def verify_code_async(phone_number: str, session_name: str, code: str, phone_code_hash: str):
    session_file = build_session_file(session_name)
    client = TelegramClient(session_file, API_ID, API_HASH)

    await client.connect()

    try:
        try:
            await client.sign_in(
                phone=phone_number,
                code=code,
                phone_code_hash=phone_code_hash,
            )
        except SessionPasswordNeededError:
            return {
                "ok": True,
                "status": "password_required",
            }

        me = await client.get_me()

        return {
            "ok": True,
            "status": "connected",
            "telegram_user_id": str(me.id),
            "telegram_username": me.username,
            "telegram_first_name": me.first_name,
            "telegram_last_name": me.last_name,
        }
    finally:
        await client.disconnect()


async def verify_password_async(session_name: str, password: str):
    session_file = build_session_file(session_name)
    client = TelegramClient(session_file, API_ID, API_HASH)

    await client.connect()

    try:
        await client.sign_in(password=password)
        me = await client.get_me()

        return {
            "ok": True,
            "status": "connected",
            "telegram_user_id": str(me.id),
            "telegram_username": me.username,
            "telegram_first_name": me.first_name,
            "telegram_last_name": me.last_name,
        }
    finally:
        await client.disconnect()

def build_session_paths(session_name: str):
    safe_name = "".join(c for c in session_name if c.isalnum() or c in ("-", "_"))
    base = session_path / safe_name
    return base, Path(str(base) + ".session")

async def disconnect_async(session_name: str):
    base_path, sqlite_session_file = build_session_paths(session_name)
    client = TelegramClient(str(base_path), API_ID, API_HASH)

    await client.connect()

    try:
        if await client.is_user_authorized():
            await client.log_out()
    finally:
        await client.disconnect()

    if sqlite_session_file.exists():
        sqlite_session_file.unlink()

    journal_file = Path(str(sqlite_session_file) + "-journal")
    if journal_file.exists():
        journal_file.unlink()

    return {
        "ok": True,
        "message": "Telegram session disconnected.",
    }
async def status_async(session_name: str):
    session_file = build_session_file(session_name)
    client = TelegramClient(session_file, API_ID, API_HASH)

    await client.connect()

    try:
        authorized = await client.is_user_authorized()

        if not authorized:
            return {
                "ok": True,
                "connected": False,
                "authorized": False,
                "message": "Telegram session is not authorized.",
            }

        me = await client.get_me()

        return {
            "ok": True,
            "connected": True,
            "authorized": True,
            "telegram_user_id": str(me.id),
            "telegram_username": me.username,
            "telegram_first_name": me.first_name,
            "telegram_last_name": me.last_name,
            "message": "Telegram session is active.",
        }
    finally:
        await client.disconnect()

@app.post("/send-code")
def send_code():
    data = request.get_json(silent=True) or {}

    phone_number = (data.get("phone_number") or "").strip()
    session_name = (data.get("session_name") or "").strip()

    if not phone_number:
        return json_error("phone_number is required.")

    if not session_name:
        return json_error("session_name is required.")

    try:
        result = asyncio.run(send_code_async(phone_number, session_name))
        return jsonify(result)
    except Exception as e:
        return json_error(f"Failed to send code: {str(e)}", 500)


@app.post("/verify-code")
def verify_code():
    data = request.get_json(silent=True) or {}

    phone_number = (data.get("phone_number") or "").strip()
    session_name = (data.get("session_name") or "").strip()
    code = (data.get("code") or "").strip()
    phone_code_hash = (data.get("phone_code_hash") or "").strip()

    if not phone_number:
        return json_error("phone_number is required.")

    if not session_name:
        return json_error("session_name is required.")

    if not code:
        return json_error("code is required.")

    if not phone_code_hash:
        return json_error("phone_code_hash is required.")

    try:
        result = asyncio.run(
            verify_code_async(
                phone_number=phone_number,
                session_name=session_name,
                code=code,
                phone_code_hash=phone_code_hash,
            )
        )
        return jsonify(result)
    except PhoneCodeInvalidError:
        return json_error("Invalid Telegram code.", 422)
    except PhoneCodeExpiredError:
        return json_error("Telegram code expired. Please send a new code.", 422)
    except SessionPasswordNeededError:
        return jsonify({
            "ok": True,
            "status": "password_required",
        })
    except Exception as e:
        return json_error(f"Failed to verify code: {str(e)}", 500)


@app.post("/verify-password")
def verify_password():
    data = request.get_json(silent=True) or {}

    session_name = (data.get("session_name") or "").strip()
    password = data.get("password") or ""

    if not session_name:
        return json_error("session_name is required.")

    if not password:
        return json_error("password is required.")

    try:
        result = asyncio.run(
            verify_password_async(
                session_name=session_name,
                password=password,
            )
        )
        return jsonify(result)
    except PasswordHashInvalidError:
        return json_error("Invalid Telegram password.", 422)
    except Exception as e:
        return json_error(f"Failed to verify password: {str(e)}", 500)


@app.get("/health")
def health():
    return jsonify({
        "ok": True,
        "message": "Telegram bridge is running.",
    })

@app.post("/disconnect")
def disconnect():
    data = request.get_json(silent=True) or {}

    session_name = (data.get("session_name") or "").strip()

    if not session_name:
        return json_error("session_name is required.")

    try:
        result = asyncio.run(disconnect_async(session_name))
        return jsonify(result)
    except Exception as e:
        return json_error(f"Failed to disconnect: {str(e)}", 500)

@app.post("/status")
def status():
    data = request.get_json(silent=True) or {}

    session_name = (data.get("session_name") or "").strip()

    if not session_name:
        return json_error("session_name is required.")

    try:
        result = asyncio.run(status_async(session_name))
        return jsonify(result)
    except Exception as e:
        return json_error(f"Failed to check status: {str(e)}", 500)

if __name__ == "__main__":
    app.run(host=BRIDGE_HOST, port=BRIDGE_PORT, debug=True)
