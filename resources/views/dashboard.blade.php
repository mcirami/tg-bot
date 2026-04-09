<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Telegram Connection') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if(!$telegram || !$telegram->is_connected)
                        <p class="mb-4">No Telegram account connected.</p>

                        <a href="{{ route('telegram.connect.edit') }}" class="bg-blue-500 text-white px-4 py-2 rounded">
                            Connect Telegram
                        </a>
                    @else
                        <p class="mb-2">Connected as: {{ $telegram->telegram_username ?? 'Unknown' }}</p>

                        <p>Status:
                            <span class="{{ $telegram->is_enabled ? 'text-green-600' : 'text-red-600' }}">
                                {{ $telegram->is_enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
