<x-filament-panels::page>
    <form wire:submit.prevent="runPreview">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" color="primary" wire:loading.attr="disabled">
                محاسبه پیش‌نمایش
            </x-filament::button>
        </div>
    </form>

    @if($preview)
        <div class="mt-8 rounded-2xl bg-white p-6 ring-1 ring-gray-950/5">
            <h3 class="text-base font-semibold text-gray-900 mb-4">نتیجه محاسبه</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs text-gray-500">کلید تابلو</p>
                    <p class="mt-1 font-semibold">{{ $preview['metal_type'] }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs text-gray-500">قیمت پایه تابلو</p>
                    <p class="mt-1 font-semibold">{{ number_format($preview['base_price']) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs text-gray-500">وزن (گرم)</p>
                    <p class="mt-1 font-semibold">{{ $preview['weight'] }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs text-gray-500">ضریب اجرت</p>
                    <p class="mt-1 font-semibold">{{ $preview['coefficient'] }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs text-gray-500">نقش / بازه</p>
                    <p class="mt-1 font-semibold">{{ $preview['user_role'] }} / {{ $preview['time_period'] }}</p>
                </div>
                <div class="rounded-xl bg-primary-50 p-4 ring-1 ring-primary-600/20">
                    <p class="text-xs text-primary-700">قیمت نهایی</p>
                    <p class="mt-1 text-xl font-bold text-primary-700">
                        {{ number_format($preview['calculated_price']) }}
                        <span class="text-sm font-normal">تومان</span>
                    </p>
                </div>
            </div>
        </div>
    @elseif(($data['product_id'] ?? null))
        <p class="mt-6 text-sm text-gray-500">برای این محصول داده کافی (کلید تابلو / وزن) موجود نیست.</p>
    @endif
</x-filament-panels::page>
