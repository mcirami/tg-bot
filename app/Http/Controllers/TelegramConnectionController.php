<?php

namespace App\Http\Controllers;

use App\Models\TelegramConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class TelegramConnectionController extends Controller
{
    public function edit(Request $request): View
    {
        $connection = $request->user()->telegramConnection;

        return view('telegram.connect', [
            'connection' => $connection,
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
        ]);

        $user = $request->user();

        $connection = TelegramConnection::updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone_number' => $validated['phone_number'],
                'session_name' => 'user-' . $user->id,
                'status' => 'pending',
                'phone_code_hash' => null,
                'telegram_user_id' => null,
                'telegram_username' => null,
                'telegram_first_name' => null,
                'telegram_last_name' => null,
                'connected_at' => null,
                'last_error' => null,
                'last_error_at' => null,
            ]
        );

        try {
            $response = Http::timeout(20)->post(config('services.telegram_bridge.base_url') . '/send-code', [
                'user_id' => $user->id,
                'phone_number' => $connection->phone_number,
                'session_name' => $connection->session_name,
            ]);

            if (! $response->successful()) {
                $message = $response->json('message') ?? 'Failed to send Telegram code.';
                $connection->markFailed($message);

                return back()->withErrors([
                    'phone_number' => $message,
                ])->withInput();
            }

            $data = $response->json();

            $connection->update([
                'phone_code_hash' => $data['phone_code_hash'] ?? null,
                'status' => 'code_sent',
                'last_error' => null,
                'last_error_at' => null,
            ]);

            return redirect()
                ->route('telegram.connect.edit')
                ->with('status', 'Telegram code sent successfully.');
        } catch (\Throwable $e) {
            $connection->markFailed($e->getMessage());

            return back()->withErrors([
                'phone_number' => 'Could not reach Telegram service: ' . $e->getMessage(),
            ])->withInput();
        }
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $connection = $user->telegramConnection;

        if (! $connection) {
            return redirect()
                ->route('telegram.connect.edit')
                ->withErrors([
                    'code' => 'No Telegram connection was found.',
                ]);
        }

        try {
            $response = Http::timeout(20)->post(config('services.telegram_bridge.base_url') . '/verify-code', [
                'user_id' => $user->id,
                'phone_number' => $connection->phone_number,
                'session_name' => $connection->session_name,
                'phone_code_hash' => $connection->phone_code_hash,
                'code' => $validated['code'],
            ]);

            if (! $response->successful()) {
                $message = $response->json('message') ?? 'Failed to verify Telegram code.';
                $connection->markFailed($message);

                return back()->withErrors([
                    'code' => $message,
                ]);
            }

            $data = $response->json();

            if (($data['status'] ?? null) === 'password_required') {
                $connection->update([
                    'status' => 'password_required',
                    'last_error' => null,
                    'last_error_at' => null,
                ]);

                return redirect()
                    ->route('telegram.connect.edit')
                    ->with('status', 'Telegram account requires a password.');
            }

            $connection->markConnected([
                'telegram_user_id' => $data['telegram_user_id'] ?? null,
                'telegram_username' => $data['telegram_username'] ?? null,
                'telegram_first_name' => $data['telegram_first_name'] ?? null,
                'telegram_last_name' => $data['telegram_last_name'] ?? null,
            ]);

            return redirect()
                ->route('telegram.connect.edit')
                ->with('status', 'Telegram connected successfully.');
        } catch (\Throwable $e) {
            $connection->markFailed($e->getMessage());

            return back()->withErrors([
                'code' => 'Could not reach Telegram service: ' . $e->getMessage(),
            ]);
        }
    }

    public function verifyPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $connection = $user->telegramConnection;

        if (! $connection) {
            return redirect()
                ->route('telegram.connect.edit')
                ->withErrors([
                    'password' => 'No Telegram connection was found.',
                ]);
        }

        try {
            $response = Http::timeout(20)->post(config('services.telegram_bridge.base_url') . '/verify-password', [
                'user_id' => $user->id,
                'session_name' => $connection->session_name,
                'password' => $validated['password'],
            ]);

            if (! $response->successful()) {
                $message = $response->json('message') ?? 'Failed to verify Telegram password.';
                $connection->markFailed($message);

                return back()->withErrors([
                    'password' => $message,
                ]);
            }

            $data = $response->json();

            $connection->markConnected([
                'telegram_user_id' => $data['telegram_user_id'] ?? null,
                'telegram_username' => $data['telegram_username'] ?? null,
                'telegram_first_name' => $data['telegram_first_name'] ?? null,
                'telegram_last_name' => $data['telegram_last_name'] ?? null,
            ]);

            return redirect()
                ->route('telegram.connect.edit')
                ->with('status', 'Telegram connected successfully.');
        } catch (\Throwable $e) {
            $connection->markFailed($e->getMessage());

            return back()->withErrors([
                'password' => 'Could not reach Telegram service: ' . $e->getMessage(),
            ]);
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $connection = $user->telegramConnection;

        if (! $connection) {
            return redirect()
                ->route('telegram.connect.edit')
                ->with('status', 'No Telegram connection was found.');
        }

        try {
            $response = Http::timeout(20)->post(
                config('services.telegram_bridge.base_url') . '/disconnect',
                [
                    'session_name' => $connection->session_name,
                ]
            );

            if (! $response->successful()) {
                $message = $response->json('message') ?? 'Failed to disconnect Telegram session.';

                return back()->withErrors([
                    'disconnect' => $message,
                ]);
            }

            $connection->update([
                'phone_code_hash' => null,
                'telegram_user_id' => null,
                'telegram_username' => null,
                'telegram_first_name' => null,
                'telegram_last_name' => null,
                'status' => 'pending',
                'connected_at' => null,
                'last_error' => null,
                'last_error_at' => null,
            ]);

            return redirect()
                ->route('telegram.connect.edit')
                ->with('status', 'Telegram disconnected successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors([
                'disconnect' => 'Could not reach Telegram service: ' . $e->getMessage(),
            ]);
        }
    }

    public function testStatus(Request $request): RedirectResponse
    {
        $user = $request->user();
        $connection = $user->telegramConnection;

        if (! $connection || ! $connection->session_name) {
            return redirect()
                ->route('telegram.connect.edit')
                ->withErrors([
                    'status_check' => 'No Telegram session was found to test.',
                ]);
        }

        try {
            $response = Http::timeout(20)->post(
                config('services.telegram_bridge.base_url') . '/status',
                [
                    'session_name' => $connection->session_name,
                ]
            );

            if (! $response->successful()) {
                $message = $response->json('message') ?? 'Failed to check Telegram status.';

                return back()->withErrors([
                    'status_check' => $message,
                ]);
            }

            $data = $response->json();

            if (! ($data['authorized'] ?? false)) {
                $connection->update([
                    'status' => 'failed',
                    'connected_at' => null,
                    'telegram_user_id' => null,
                    'telegram_username' => null,
                    'telegram_first_name' => null,
                    'telegram_last_name' => null,
                    'last_error' => 'Telegram session is no longer authorized.',
                    'last_error_at' => now(),
                ]);

                return redirect()
                    ->route('telegram.connect.edit')
                    ->withErrors([
                        'status_check' => 'Telegram session is no longer authorized.',
                    ]);
            }

            $connection->update([
                'status' => 'connected',
                'connected_at' => $connection->connected_at ?? now(),
                'telegram_user_id' => $data['telegram_user_id'] ?? null,
                'telegram_username' => $data['telegram_username'] ?? null,
                'telegram_first_name' => $data['telegram_first_name'] ?? null,
                'telegram_last_name' => $data['telegram_last_name'] ?? null,
                'last_error' => null,
                'last_error_at' => null,
            ]);

            return redirect()
                ->route('telegram.connect.edit')
                ->with('status', 'Telegram session is active.');
        } catch (\Throwable $e) {
            return back()->withErrors([
                'status_check' => 'Could not reach Telegram service: ' . $e->getMessage(),
            ]);
        }
    }
}
