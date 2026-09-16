<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name, email, phone and photo')">
        <form wire:submit="updateProfileInformation" class="my-4 w-full space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#142f44] text-lg font-semibold text-white">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="{{ __('Profile photo') }}" class="size-full object-cover" />
                    @elseif (auth()->user()->profile_photo)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url(auth()->user()->profile_photo) }}" alt="{{ __('Profile photo') }}" class="size-full object-cover" />
                    @else
                        {{ auth()->user()->initials() }}
                    @endif
                </div>

                <div>
                    <flux:label>{{ __('Profile photo') }}</flux:label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full cursor-pointer rounded-lg border border-zinc-200 bg-white py-1.5 ps-1 text-sm text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200" />
                        @if (auth()->user()->profile_photo && ! $photo)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removePhoto" wire:confirm="{{ __('¿Quitar la foto de perfil?') }}">
                                {{ __('Quitar foto') }}
                            </flux:button>
                        @endif
                    </div>
                    @error('photo') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
                </div>
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            </div>

            <flux:input wire:model="phone" :label="__('Phone')" type="tel" autocomplete="tel" />

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        <flux:separator class="my-4" />

        <div>
            <flux:heading size="lg">{{ __('Datos de acceso') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Estos datos son administrados por el Área de Sistemas.') }}</flux:text>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <flux:label>{{ __('Usuario') }}</flux:label>
                    <flux:input :value="auth()->user()->username" readonly class="mt-1 bg-zinc-50 dark:bg-zinc-900" />
                </div>
                <div>
                    <flux:label>{{ __('Rol / Área') }}</flux:label>
                    <flux:input :value="auth()->user()->getRoleNames()->first() ?? '—'" readonly class="mt-1 bg-zinc-50 dark:bg-zinc-900" />
                </div>
            </div>
        </div>

        <flux:separator class="my-4" />

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
