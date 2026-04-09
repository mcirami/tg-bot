<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Connect Telegram') }}
        </h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6">
            @if (session('status'))
                <div class="bg-green-100 border border-green-300 text-white px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Connection Status</h3>

                <div class="space-y-2 text-sm">
                    <p>
                    <strong>Status:</strong>
                        @php
                            $status = $connection?->status ?? 'not_connected';
                        @endphp
                        @if ($status === 'connected')
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800 border border-green-300">
                                Connected
                            </span>
                        @elseif ($status === 'code_sent')
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-yellow-100 text-yellow-800 border border-yellow-300">
                                Code Sent
                            </span>
                        @elseif ($status === 'password_required')
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-purple-100 text-purple-800 border border-purple-300">
                                Password Required
                            </span>
                        @elseif ($status === 'failed')
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-red-100 text-red-800 border border-red-300">
                                Disconnected / Error
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-800 border border-gray-300">
                                Not Connected
                            </span>
                        @endif
                    </p>
                    <p><strong>Phone:</strong> {{ $connection?->phone_number ?? '—' }}</p>
                    <p><strong>Username:</strong> {{ $connection?->telegram_username ?? '—' }}</p>
                    <p><strong>Name:</strong>
                        {{ trim(($connection?->telegram_first_name ?? '') . ' ' . ($connection?->telegram_last_name ?? '')) ?: '—' }}
                    </p>

                    @if ($connection?->last_error)
                        <div class="mt-3 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded">
                            <strong>Last error:</strong> {{ $connection->last_error }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">1. Send Code</h3>

                <form method="POST" action="{{ route('telegram.connect.send-code') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">
                            Telegram Phone Number
                        </label>
                        <input
                            id="phone_number"
                            name="phone_number"
                            type="text"
                            value="{{ old('phone_number', $connection?->phone_number) }}"
                            placeholder="+15551234567"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                        @error('phone_number')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="px-4 py-2 !bg-blue-600 text-white rounded">
                        Send Code
                    </button>
                </form>
            </div>

            @if ($connection && in_array($connection->status, ['code_sent', 'password_required']))
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">2. Verify Code</h3>

                    <form method="POST" action="{{ route('telegram.connect.verify-code') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700">
                                Telegram Code
                            </label>
                            <input
                                id="code"
                                name="code"
                                type="text"
                                value="{{ old('code') }}"
                                placeholder="12345"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            >
                            @error('code')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">
                            Verify Code
                        </button>
                    </form>
                </div>
            @endif

            @if ($connection?->status === 'password_required')
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">3. Verify Telegram Password</h3>

                    <form method="POST" action="{{ route('telegram.connect.verify-password') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Telegram 2FA Password
                            </label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            >
                            @error('password')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded">
                            Verify Password
                        </button>
                    </form>
                </div>
            @endif
            <div class="bg-white shadow rounded-lg p-6 flex gap-5 flex-col">
                <div class="flex gap-5">
                    <form method="POST" action="{{ route('telegram.connect.test-status') }}" class="mt-4 inline-block">
                        @csrf

                        <button
                            type="submit"
                            class="mr-2 px-4 py-2 bg-slate-500 text-white rounded"
                        >
                            Test Telegram Status
                        </button>
                    </form>
                    @if ($connection?->isConnected())
                        <form method="POST" action="{{ route('telegram.connect.disconnect') }}" class="mt-4">
                            @csrf

                            <button
                                type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded"
                                onclick="return confirm('Disconnect this Telegram account?')"
                            >
                                Disconnect Telegram
                            </button>
                        </form>
                    @endif
                </div>
                <div class="w-full">
                    <a href="{{ route('telegram.automation.edit') }}" class="text-blue-600 underline">
                        Automation Settings
                    </a>
                </div>
                @error('status_check')
                    <p class="text-sm text-red-600 mt-3">{{ $message }}</p>
                @enderror
                @error('disconnect')
                    <p class="text-sm text-red-600 mt-3">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</x-app-layout>
