<x-filament-panels::page>
    <div class="flex items-center gap-3 mb-4">
        <x-filament::button wire:click="toggleEdit"
            :icon="$isEditing ? 'heroicon-o-x-mark' : 'heroicon-o-pencil'"
            :color="$isEditing ? 'danger' : 'primary'"
            size="sm">
            {{ $isEditing ? 'لغو ویرایش' : 'ویرایش' }}
        </x-filament::button>

        @if($isEditing)
            <x-filament::button wire:click="save" color="success" size="sm">
                ذخیره تغییرات
            </x-filament::button>
        @endif
    </div>

    <form wire:submit="save">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
