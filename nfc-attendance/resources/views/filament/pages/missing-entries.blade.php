<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        <x-filament-panels::form>
            {{ $this->form }}
        </x-filament-panels::form>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
