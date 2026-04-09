<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text:black dark:text-white leading-tight">
            Telegram Automation Settings
        </h2>
    </x-slot>


        @if (session('status'))
            <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Connection Check</h3>

            <div class="space-y-2 text-sm">
                <p>
                    <strong>Telegram Status:</strong>
                    @if ($connection?->status === 'connected')
                        <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800 border border-green-300">
                            Connected
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-800 border border-gray-300">
                            Not Connected
                        </span>
                    @endif
                </p>

                <p><strong>Phone:</strong> {{ $connection?->phone_number ?? '—' }}</p>
                <p><strong>Username:</strong> {{ $connection?->telegram_username ?? '—' }}</p>
            </div>

            @if (! $connection || $connection->status !== 'connected')
                <div class="mt-4 bg-yellow-100 border border-yellow-300 text-yellow-900 px-4 py-3 rounded">
                    Connect Telegram first before turning automation on.
                </div>
            @endif
        </div>

        @php
            $triggerRepliesOld = old('trigger_replies');

            $triggerRepliesForView = is_array($triggerRepliesOld)
                ? $triggerRepliesOld
                : ($triggerReplies ?? collect())->map(function ($triggerReply) {
                    return [
                        'is_enabled' => $triggerReply->is_enabled,
                        'trigger_type' => $triggerReply->trigger_type,
                        'match_type' => $triggerReply->match_type,
                        'keywords' => is_array($triggerReply->keywords ?? null)
                            ? implode(', ', $triggerReply->keywords)
                            : '',
                        'message_count' => $triggerReply->message_count,
                        'reply_text' => $triggerReply->reply_text,
                        'fire_once_per_chat' => $triggerReply->fire_once_per_chat,
                    ];
                })->values()->all();
        @endphp

        <form method="POST" action="{{ route('telegram.automation.update') }}" class="space-y-6">
            @csrf

            <div class="bg-white shadow rounded-lg p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold mb-4">Automation</h3>

                    <label class="inline-flex items-center gap-3">
                        <input
                            type="hidden"
                            name="is_enabled"
                            value="0"
                        >
                        <input
                            type="checkbox"
                            name="is_enabled"
                            value="1"
                            {{ old('is_enabled', $automation->is_enabled) ? 'checked' : '' }}
                            class="rounded border-gray-300"
                        >
                        <span class="text-sm text-gray-700">Enable auto-replies</span>
                    </label>

                    @error('is_enabled')
                    <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ai_instructions" class="block text-sm font-medium text-gray-700">
                        AI Reply Instructions
                    </label>
                    <textarea
                        id="ai_instructions"
                        name="ai_instructions"
                        rows="6"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        placeholder="Reply casually and naturally. Keep replies short. Try to move the conversation toward my site when it makes sense."
                    >{{ old('ai_instructions', $automation->ai_instructions) }}</textarea>
                    <p class="text-sm text-gray-500 mt-1">
                        This is the default AI behavior. If no trigger rule matches, the AI reply will be used.
                    </p>
                    @error('ai_instructions')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="border-t pt-6 space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h4 class="text-base font-semibold text-gray-900">Trigger Replies</h4>
                            <p class="text-sm text-gray-500 mt-1">
                                These send a specific reply instead of the AI reply when matched.
                            </p>
                        </div>

                        <button
                            type="button"
                            id="add-trigger-reply"
                            class="px-3 py-2 bg-gray-100 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-200"
                        >
                            + Add Trigger Reply
                        </button>
                    </div>

                    <div id="trigger-replies-list" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @forelse ($triggerRepliesForView as $index => $triggerReply)
                            <div class="trigger-reply-item border border-gray-200 rounded-lg p-4 space-y-4 bg-gray-50">
                                <div class="flex items-start justify-between gap-4">
                                    <h5 class="font-medium text-gray-900">Trigger Reply</h5>
                                    <button
                                        type="button"
                                        class="remove-trigger-reply px-2 py-1 text-sm text-red-600 border border-red-200 rounded hover:bg-red-50"
                                    >
                                        Remove
                                    </button>
                                </div>

                                <div>
                                    <label class="inline-flex items-center gap-3">
                                        <input
                                            type="hidden"
                                            name="trigger_replies[{{ $index }}][is_enabled]"
                                            value="0"
                                        >
                                        <input
                                            type="checkbox"
                                            name="trigger_replies[{{ $index }}][is_enabled]"
                                            value="1"
                                            {{ !empty($triggerReply['is_enabled']) ? 'checked' : '' }}
                                            class="rounded border-gray-300"
                                        >
                                        <span class="text-sm text-gray-700">Enabled</span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            Trigger Type
                                        </label>
                                        <select
                                            name="trigger_replies[{{ $index }}][trigger_type]"
                                            class="trigger-type-select mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                        >
                                            <option value="keyword" {{ ($triggerReply['trigger_type'] ?? 'keyword') === 'keyword' ? 'selected' : '' }}>
                                                Keyword Match
                                            </option>
                                            <option value="message_count" {{ ($triggerReply['trigger_type'] ?? '') === 'message_count' ? 'selected' : '' }}>
                                                After X Incoming Messages
                                            </option>
                                        </select>
                                        @error("trigger_replies.$index.trigger_type")
                                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="keyword-fields {{ ($triggerReply['trigger_type'] ?? 'keyword') === 'keyword' ? '' : 'hidden' }}">
                                        <label class="block text-sm font-medium text-gray-700">
                                            Match Type
                                        </label>
                                        <select
                                            name="trigger_replies[{{ $index }}][match_type]"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                        >
                                            <option value="any" {{ ($triggerReply['match_type'] ?? 'any') === 'any' ? 'selected' : '' }}>
                                                Any keyword matches
                                            </option>
                                            <option value="all" {{ ($triggerReply['match_type'] ?? '') === 'all' ? 'selected' : '' }}>
                                                All keywords must match
                                            </option>
                                        </select>
                                        @error("trigger_replies.$index.match_type")
                                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="message-count-fields {{ ($triggerReply['trigger_type'] ?? 'keyword') === 'message_count' ? '' : 'hidden' }} md:col-span-1">
                                        <label class="block text-sm font-medium text-gray-700">
                                            Send After X Incoming Messages
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="1000"
                                            name="trigger_replies[{{ $index }}][message_count]"
                                            value="{{ $triggerReply['message_count'] ?? '' }}"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                            placeholder="3"
                                        >
                                        @error("trigger_replies.$index.message_count")
                                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="keyword-fields {{ ($triggerReply['trigger_type'] ?? 'keyword') === 'keyword' ? '' : 'hidden' }}">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Keywords
                                    </label>
                                    <textarea
                                        name="trigger_replies[{{ $index }}][keywords]"
                                        rows="4"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                        placeholder="price, hello, info"
                                    >{{ $triggerReply['keywords'] ?? '' }}</textarea>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Separate keywords with commas or new lines.
                                    </p>
                                    @error("trigger_replies.$index.keywords")
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Specific Reply to Send
                                    </label>
                                    <textarea
                                        name="trigger_replies[{{ $index }}][reply_text]"
                                        rows="4"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                        placeholder="Type the exact message to send when this trigger matches."
                                    >{{ $triggerReply['reply_text'] ?? '' }}</textarea>
                                    @error("trigger_replies.$index.reply_text")
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="inline-flex items-center gap-3">
                                        <input
                                            type="hidden"
                                            name="trigger_replies[{{ $index }}][fire_once_per_chat]"
                                            value="0"
                                        >
                                        <input
                                            type="checkbox"
                                            name="trigger_replies[{{ $index }}][fire_once_per_chat]"
                                            value="1"
                                            {{ !empty($triggerReply['fire_once_per_chat']) ? 'checked' : '' }}
                                            class="rounded border-gray-300"
                                        >
                                        <span class="text-sm text-gray-700">Only send once per chat</span>
                                    </label>
                                </div>
                            </div>
                        @empty
                            <div class="trigger-reply-item border border-gray-200 rounded-lg p-4 space-y-4 bg-gray-50">
                                <div class="flex items-start justify-between gap-4">
                                    <h5 class="font-medium text-gray-900">Trigger Reply</h5>
                                    <button
                                        type="button"
                                        class="remove-trigger-reply px-2 py-1 text-sm text-red-600 border border-red-200 rounded hover:bg-red-50"
                                    >
                                        Remove
                                    </button>
                                </div>

                                <div>
                                    <label class="inline-flex items-center gap-3">
                                        <input type="hidden" name="trigger_replies[0][is_enabled]" value="0">
                                        <input type="checkbox" name="trigger_replies[0][is_enabled]" value="1" checked class="rounded border-gray-300">
                                        <span class="text-sm text-gray-700">Enabled</span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Trigger Type</label>
                                        <select name="trigger_replies[0][trigger_type]" class="trigger-type-select mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                            <option value="keyword" selected>Keyword Match</option>
                                            <option value="message_count">After X Incoming Messages</option>
                                        </select>
                                    </div>

                                    <div class="keyword-fields">
                                        <label class="block text-sm font-medium text-gray-700">Match Type</label>
                                        <select name="trigger_replies[0][match_type]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                            <option value="any" selected>Any keyword matches</option>
                                            <option value="all">All keywords must match</option>
                                        </select>
                                    </div>

                                    <div class="message-count-fields hidden md:col-span-1">
                                        <label class="block text-sm font-medium text-gray-700">Send After X Incoming Messages</label>
                                        <input type="number" min="1" max="1000" name="trigger_replies[0][message_count]" value="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="3">
                                    </div>
                                </div>

                                <div class="keyword-fields">
                                    <label class="block text-sm font-medium text-gray-700">Keywords</label>
                                    <textarea name="trigger_replies[0][keywords]" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="price, hello, info"></textarea>
                                    <p class="text-sm text-gray-500 mt-1">Separate keywords with commas or new lines.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Specific Reply to Send</label>
                                    <textarea name="trigger_replies[0][reply_text]" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Type the exact message to send when this trigger matches."></textarea>
                                </div>

                                <div>
                                    <label class="inline-flex items-center gap-3">
                                        <input type="hidden" name="trigger_replies[0][fire_once_per_chat]" value="0">
                                        <input type="checkbox" name="trigger_replies[0][fire_once_per_chat]" value="1" class="rounded border-gray-300">
                                        <span class="text-sm text-gray-700">Only send once per chat</span>
                                    </label>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6 space-y-6">
                <h3 class="text-lg font-semibold">Limits and Timing</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="daily_message_limit" class="block text-sm font-medium text-gray-700">
                            Daily Message Limit
                        </label>
                        <input
                            id="daily_message_limit"
                            name="daily_message_limit"
                            type="number"
                            min="1"
                            max="1000"
                            value="{{ old('daily_message_limit', $automation->daily_message_limit) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                        @error('daily_message_limit')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="per_chat_cooldown_minutes" class="block text-sm font-medium text-gray-700">
                            Per-Chat Cooldown Minutes
                        </label>
                        <input
                            id="per_chat_cooldown_minutes"
                            name="per_chat_cooldown_minutes"
                            type="number"
                            min="0"
                            max="10080"
                            value="{{ old('per_chat_cooldown_minutes', $automation->per_chat_cooldown_minutes) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                        @error('per_chat_cooldown_minutes')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="mark_seen_delay_min_seconds" class="block text-sm font-medium text-gray-700">
                            Seen Delay Min Seconds
                        </label>
                        <input
                            id="mark_seen_delay_min_seconds"
                            name="mark_seen_delay_min_seconds"
                            type="number"
                            min="0"
                            max="300"
                            value="{{ old('mark_seen_delay_min_seconds', $automation->mark_seen_delay_min_seconds) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                        @error('mark_seen_delay_min_seconds')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="mark_seen_delay_max_seconds" class="block text-sm font-medium text-gray-700">
                            Seen Delay Max Seconds
                        </label>
                        <input
                            id="mark_seen_delay_max_seconds"
                            name="mark_seen_delay_max_seconds"
                            type="number"
                            min="0"
                            max="300"
                            value="{{ old('mark_seen_delay_max_seconds', $automation->mark_seen_delay_max_seconds) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                        @error('mark_seen_delay_max_seconds')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="typing_delay_min_seconds" class="block text-sm font-medium text-gray-700">
                            Typing Delay Min Seconds
                        </label>
                        <input
                            id="typing_delay_min_seconds"
                            name="typing_delay_min_seconds"
                            type="number"
                            min="0"
                            max="300"
                            value="{{ old('typing_delay_min_seconds', $automation->typing_delay_min_seconds) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                        @error('typing_delay_min_seconds')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="typing_delay_max_seconds" class="block text-sm font-medium text-gray-700">
                            Typing Delay Max Seconds
                        </label>
                        <input
                            id="typing_delay_max_seconds"
                            name="typing_delay_max_seconds"
                            type="number"
                            min="0"
                            max="300"
                            value="{{ old('typing_delay_max_seconds', $automation->typing_delay_max_seconds) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                        @error('typing_delay_max_seconds')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="idle_follow_up_minutes" class="block text-sm font-medium text-gray-700">
                            Idle Follow-Up Minutes
                        </label>
                        <input
                            id="idle_follow_up_minutes"
                            name="idle_follow_up_minutes"
                            type="number"
                            min="1"
                            max="10080"
                            value="{{ old('idle_follow_up_minutes', $automation->idle_follow_up_minutes) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            placeholder="Example: 30"
                        >
                        <p class="text-sm text-gray-500 mt-1">
                            If the other person stays inactive for this many minutes after your last reply, send the idle follow-up message.
                        </p>
                        @error('idle_follow_up_minutes')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="idle_follow_up_message" class="block text-sm font-medium text-gray-700">
                            Idle Follow-Up Message
                        </label>
                        <textarea
                            id="idle_follow_up_message"
                            name="idle_follow_up_message"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            placeholder="Hey, just checking back in :)"
                        >{{ old('idle_follow_up_message', $automation->idle_follow_up_message) }}</textarea>
                        @error('idle_follow_up_message')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    class="px-4 py-2 bg-blue-500 text-white rounded"
                >
                    Save Automation Settings
                </button>

                <a
                    href="{{ route('telegram.connect.edit') }}"
                    class="text-sm text-blue-600 underline"
                >
                    Back to Telegram Connection
                </a>
            </div>
        </form>

    <template id="trigger-reply-template">
        <div class="trigger-reply-item border border-gray-200 rounded-lg p-4 space-y-4 bg-gray-50">
            <div class="flex items-start justify-between gap-4">
                <h5 class="font-medium text-gray-900">Trigger Reply</h5>
                <button
                    type="button"
                    class="remove-trigger-reply px-2 py-1 text-sm text-red-600 border border-red-200 rounded hover:bg-red-50"
                >
                    Remove
                </button>
            </div>

            <div>
                <label class="inline-flex items-center gap-3">
                    <input type="hidden" data-name="is_enabled_hidden" value="0">
                    <input type="checkbox" data-name="is_enabled" value="1" checked class="rounded border-gray-300">
                    <span class="text-sm text-gray-700">Enabled</span>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Trigger Type</label>
                    <select data-name="trigger_type" class="trigger-type-select mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="keyword" selected>Keyword Match</option>
                        <option value="message_count">After X Incoming Messages</option>
                    </select>
                </div>

                <div class="keyword-fields">
                    <label class="block text-sm font-medium text-gray-700">Match Type</label>
                    <select data-name="match_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="any" selected>Any keyword matches</option>
                        <option value="all">All keywords must match</option>
                    </select>
                </div>

                <div class="message-count-fields hidden md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">Send After X Incoming Messages</label>
                    <input type="number" min="1" max="1000" data-name="message_count" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="3">
                </div>
            </div>

            <div class="keyword-fields">
                <label class="block text-sm font-medium text-gray-700">Keywords</label>
                <textarea data-name="keywords" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="price, hello, info"></textarea>
                <p class="text-sm text-gray-500 mt-1">Separate keywords with commas or new lines.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Specific Reply to Send</label>
                <textarea data-name="reply_text" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Type the exact message to send when this trigger matches."></textarea>
            </div>

            <div>
                <label class="inline-flex items-center gap-3">
                    <input type="hidden" data-name="fire_once_per_chat_hidden" value="0">
                    <input type="checkbox" data-name="fire_once_per_chat" value="1" class="rounded border-gray-300">
                    <span class="text-sm text-gray-700">Only send once per chat</span>
                </label>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const list = document.getElementById('trigger-replies-list');
            const addButton = document.getElementById('add-trigger-reply');
            const template = document.getElementById('trigger-reply-template');

            function updateTriggerTypeVisibility(item) {
                const triggerType = item.querySelector('.trigger-type-select')?.value;
                const keywordFields = item.querySelectorAll('.keyword-fields');
                const messageCountFields = item.querySelectorAll('.message-count-fields');

                if (triggerType === 'message_count') {
                    keywordFields.forEach(el => el.classList.add('hidden'));
                    messageCountFields.forEach(el => el.classList.remove('hidden'));
                } else {
                    keywordFields.forEach(el => el.classList.remove('hidden'));
                    messageCountFields.forEach(el => el.classList.add('hidden'));
                }
            }

            function bindItem(item) {
                item.querySelector('.remove-trigger-reply')?.addEventListener('click', function () {
                    item.remove();
                    reindexItems();
                });

                item.querySelector('.trigger-type-select')?.addEventListener('change', function () {
                    updateTriggerTypeVisibility(item);
                });

                updateTriggerTypeVisibility(item);
            }

            function reindexItems() {
                const items = list.querySelectorAll('.trigger-reply-item');

                items.forEach((item, index) => {
                    item.querySelectorAll('[data-name]').forEach((field) => {
                        const fieldName = field.getAttribute('data-name');
                        let actualName = '';

                        switch (fieldName) {
                            case 'is_enabled_hidden':
                                actualName = `trigger_replies[${index}][is_enabled]`;
                                break;
                            case 'is_enabled':
                                actualName = `trigger_replies[${index}][is_enabled]`;
                                break;
                            case 'trigger_type':
                                actualName = `trigger_replies[${index}][trigger_type]`;
                                break;
                            case 'match_type':
                                actualName = `trigger_replies[${index}][match_type]`;
                                break;
                            case 'keywords':
                                actualName = `trigger_replies[${index}][keywords]`;
                                break;
                            case 'message_count':
                                actualName = `trigger_replies[${index}][message_count]`;
                                break;
                            case 'reply_text':
                                actualName = `trigger_replies[${index}][reply_text]`;
                                break;
                            case 'fire_once_per_chat_hidden':
                                actualName = `trigger_replies[${index}][fire_once_per_chat]`;
                                break;
                            case 'fire_once_per_chat':
                                actualName = `trigger_replies[${index}][fire_once_per_chat]`;
                                break;
                        }

                        if (actualName) {
                            field.setAttribute('name', actualName);
                        }
                    });
                });
            }

            addButton?.addEventListener('click', function () {
                const fragment = template.content.cloneNode(true);
                const item = fragment.querySelector('.trigger-reply-item');
                list.appendChild(fragment);
                const appendedItem = list.lastElementChild;
                bindItem(appendedItem);
                reindexItems();
            });

            list.querySelectorAll('.trigger-reply-item').forEach(bindItem);
            reindexItems();
        });
    </script>
</x-app-layout>
