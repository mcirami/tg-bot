<?php

namespace App\Http\Controllers;

use App\Models\TelegramAutomation;
use App\Models\TelegramTriggerReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class TelegramAutomationController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        $automation = TelegramAutomation::firstOrCreate(
            ['user_id' => $user->id],
            [
                'is_enabled' => false,
                'ai_instructions' => 'Reply naturally, briefly, and conversationally.',
                'daily_message_limit' => 20,
                'per_chat_cooldown_minutes' => 60,
                'mark_seen_delay_min_seconds' => 5,
                'mark_seen_delay_max_seconds' => 10,
                'typing_delay_min_seconds' => 3,
                'typing_delay_max_seconds' => 7,
                'idle_follow_up_minutes' => null,
                'idle_follow_up_message' => null,
            ]
        );

        $triggerReplies = TelegramTriggerReply::query()
                                              ->where('user_id', $user->id)
                                              ->orderBy('sort_order')
                                              ->get();

        return view('telegram.automation-settings', [
            'automation' => $automation,
            'connection' => $user->telegramConnection,
            'triggerReplies' => $triggerReplies,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $automation = TelegramAutomation::firstOrCreate(
            ['user_id' => $user->id],
            [
                'is_enabled' => false,
                'ai_instructions' => 'Reply naturally, briefly, and conversationally.',
                'daily_message_limit' => 20,
                'per_chat_cooldown_minutes' => 60,
                'mark_seen_delay_min_seconds' => 5,
                'mark_seen_delay_max_seconds' => 10,
                'typing_delay_min_seconds' => 3,
                'typing_delay_max_seconds' => 7,
            ]
        );

        $validated = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'ai_instructions' => ['nullable', 'string', 'max:10000'],
            'daily_message_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'per_chat_cooldown_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'mark_seen_delay_min_seconds' => ['required', 'integer', 'min:0', 'max:300'],
            'mark_seen_delay_max_seconds' => ['required', 'integer', 'min:0', 'max:300'],
            'typing_delay_min_seconds' => ['required', 'integer', 'min:0', 'max:300'],
            'typing_delay_max_seconds' => ['required', 'integer', 'min:0', 'max:300'],

            'trigger_replies' => ['nullable', 'array'],
            'trigger_replies.*.is_enabled' => ['nullable', 'boolean'],
            'trigger_replies.*.trigger_type' => ['required_with:trigger_replies', 'in:keyword,message_count'],
            'trigger_replies.*.match_type' => ['nullable', 'in:any,all'],
            'trigger_replies.*.keywords' => ['nullable', 'string', 'max:5000'],
            'trigger_replies.*.message_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'trigger_replies.*.reply_text' => ['required_with:trigger_replies', 'nullable', 'string', 'max:5000'],
            'trigger_replies.*.fire_once_per_chat' => ['nullable', 'boolean'],

            'idle_follow_up_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'idle_follow_up_message' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validated['mark_seen_delay_min_seconds'] > $validated['mark_seen_delay_max_seconds']) {
            return back()
                ->withErrors([
                    'mark_seen_delay_min_seconds' => 'Seen delay min cannot be greater than max.',
                ])
                ->withInput();
        }

        if ($validated['typing_delay_min_seconds'] > $validated['typing_delay_max_seconds']) {
            return back()
                ->withErrors([
                    'typing_delay_min_seconds' => 'Typing delay min cannot be greater than max.',
                ])
                ->withInput();
        }

        if (! empty($validated['idle_follow_up_message']) && empty($validated['idle_follow_up_minutes'])) {
            return back()
                ->withErrors([
                    'idle_follow_up_minutes' => 'Idle follow-up minutes is required when an idle follow-up message is set.',
                ])
                ->withInput();
        }

        if (! empty($validated['idle_follow_up_minutes']) && empty(trim($validated['idle_follow_up_message'] ?? ''))) {
            return back()
                ->withErrors([
                    'idle_follow_up_message' => 'Idle follow-up message is required when idle follow-up minutes is set.',
                ])
                ->withInput();
        }

        $connection = $user->telegramConnection;

        if (($validated['is_enabled'] ?? false) && (! $connection || $connection->status !== 'connected')) {
            return back()
                ->withErrors([
                    'is_enabled' => 'You must connect Telegram before enabling automation.',
                ])
                ->withInput();
        }

        $automation->update([
            'is_enabled' => (bool) ($validated['is_enabled'] ?? false),
            'ai_instructions' => $validated['ai_instructions'] ?? null,
            'daily_message_limit' => $validated['daily_message_limit'],
            'per_chat_cooldown_minutes' => $validated['per_chat_cooldown_minutes'],
            'mark_seen_delay_min_seconds' => $validated['mark_seen_delay_min_seconds'],
            'mark_seen_delay_max_seconds' => $validated['mark_seen_delay_max_seconds'],
            'typing_delay_min_seconds' => $validated['typing_delay_min_seconds'],
            'typing_delay_max_seconds' => $validated['typing_delay_max_seconds'],
            'idle_follow_up_minutes' => $validated['idle_follow_up_minutes'] ?? null,
            'idle_follow_up_message' => $validated['idle_follow_up_message'] ?? null,
        ]);

        TelegramTriggerReply::where('user_id', $user->id)->delete();

        foreach (($validated['trigger_replies'] ?? []) as $index => $triggerReply) {
            $triggerType = $triggerReply['trigger_type'];

            TelegramTriggerReply::create([
                'user_id' => $user->id,
                'is_enabled' => (bool) ($triggerReply['is_enabled'] ?? false),
                'trigger_type' => $triggerType,
                'match_type' => $triggerType === 'keyword'
                    ? ($triggerReply['match_type'] ?? 'any')
                    : null,
                'keywords' => $triggerType === 'keyword'
                    ? $this->parseKeywords($triggerReply['keywords'] ?? '')
                    : [],
                'message_count' => $triggerType === 'message_count'
                    ? (int) ($triggerReply['message_count'] ?? 0)
                    : null,
                'reply_text' => $triggerReply['reply_text'] ?? null,
                'fire_once_per_chat' => (bool) ($triggerReply['fire_once_per_chat'] ?? false),
                'sort_order' => $index + 1,
            ]);
        }

        return redirect()
            ->route('telegram.automation.edit')
            ->with('status', 'Automation settings saved successfully.');
    }

    protected function parseKeywords(string $keywords): array
    {
        return collect(preg_split('/[\r\n,]+/', $keywords))
            ->map(fn ($keyword) => trim($keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
