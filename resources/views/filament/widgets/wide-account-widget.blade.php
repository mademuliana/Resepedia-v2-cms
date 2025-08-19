<x-filament-widgets::widget>
    <x-filament::section>
        @php($user = auth()->user())

        <div class="flex flex-col gap-4">
            {{-- Top row: identity + actions --}}
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    {{-- Simple avatar circle with initials --}}
                    <div class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-700 font-semibold">
                        {{ str($user->name)->split('/\s+/')->map(fn($p)=>str($p)->substr(0,1))->take(2)->implode('') }}
                    </div>

                    <div>
                        <div class="text-base font-semibold">{{ $user->name }}</div>
                        <div class="text-sm text-gray-500">{{ $user->email }}</div>
                        @if(property_exists($user, 'role') && $user->role)
                            <div class="text-xs mt-1">
                                <x-filament::badge>{{ $user->role }}</x-filament::badge>
                                @if(method_exists($user, 'company') && $user->company)
                                    <span class="ml-2 text-gray-500">Company:</span>
                                    <span class="text-gray-700">{{ optional($user->company)->name }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 shrink-0">
                    <x-filament::button icon="heroicon-o-key" wire:click="issueToken">
                        Generate API token
                    </x-filament::button>

                    <x-filament::button color="gray" icon="heroicon-o-no-symbol" wire:click="revokeAllTokens">
                        Revoke all tokens
                    </x-filament::button>
                </div>
            </div>

            {{-- Token display (only after generation) --}}
            @if ($token)
                <div>
                    <label class="text-sm text-gray-600">Your new token (copy now; it won’t be shown again):</label>
                    <div class="mt-2 relative">
                        <input
                            type="text"
                            readonly
                            value="{{ $token }}"
                            class="w-full rounded-lg border-gray-300 pr-24"
                            onclick="this.select()"
                        />
                        <button
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-sm px-3 py-1 rounded-md border"
                            onclick="navigator.clipboard.writeText('{{ $token }}')"
                        >
                            Copy
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
