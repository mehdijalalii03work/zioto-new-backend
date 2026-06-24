<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <x-filament::button type="submit">
                ذخیره تنظیمات
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
