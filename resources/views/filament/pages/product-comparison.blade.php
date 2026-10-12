<x-filament-panels::page>
    @php
        $toToman = fn ($value) => is_numeric($value) ? number_format((float) $value / 10, 0) : '—';
        $pct = fn ($value) => $value === null ? '—' : number_format($value, 2).'٪';
        $pctClass = fn ($value) => $value === null ? '' : ($value >= 0 ? 'text-emerald-700' : 'text-red-700');
        $isHl = fn ($diff) => $diffThreshold !== null && $diff !== null && abs($diff) >= $diffThreshold;
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-filament::button wire:click="refresh" color="primary" size="sm" icon="heroicon-o-arrow-path">
            بروزرسانی
        </x-filament::button>
        <div class="flex items-center gap-2">
            <input
                type="number"
                min="0"
                max="1000"
                step="0.01"
                wire:model="allowedDiffInput"
                placeholder="٪ اختلاف مجاز"
                class="w-36 rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-center text-sm shadow-sm outline-none transition focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
            />
            <x-filament::button wire:click="applyThreshold" color="gray" size="sm">
                اعمال
            </x-filament::button>
        </div>
        <p class="text-sm text-gray-500">
            {{ number_format($productCount) }} محصول دارای tokeniko_sku
        </p>
    </div>

    @if($tokenikoError)
        <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            {{ $tokenikoError }} — قیمت‌های توکنیکو نمایش داده نمی‌شوند.
        </div>
    @endif

    @if(count($rows) === 0)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            محصولی با tokeniko_sku یافت نشد.
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-950/5">
            <table class="w-full min-w-[900px] border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th class="border-e border-gray-200 px-3 py-2 text-start">محصول</th>
                        <th class="border-e border-gray-200 px-3 py-2 text-start">قیمت زیوتو (تومان)</th>
                        <th class="border-e border-gray-200 px-3 py-2 text-start">قیمت توکنیکو (تومان)</th>
                        <th class="px-3 py-2 text-start">
                        <button type="button" wire:click="sortByDiff" class="inline-flex items-center gap-1 font-semibold hover:text-gray-900" title="مرتب‌سازی بر اساس قدرمطلق اختلاف">
                            اختلاف ٪
                            <span aria-hidden="true">{{ $sortDirection === 'desc' ? '▼' : '▲' }}</span>
                        </button>
                    </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-t border-gray-100 {{ $isHl($row['diffPct']) ? 'bg-amber-200' : 'odd:bg-white even:bg-gray-50' }}">
                            <td class="px-3 py-2">
                                <div class="font-semibold text-gray-900">{{ $row['name'] }}</div>
                                {{-- <div class="font-mono text-xs text-gray-400" dir="ltr">{{ $row['sku'] }}</div> --}}
                            </td>
                            <td class="px-3 py-2 tabular-nums">{{ $toToman($row['ourPrice']) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $toToman($row['tokPrice']) }}</td>
                            <td class="px-3 py-2 font-bold tabular-nums {{ $pctClass($row['diffPct']) }}">{{ $pct($row['diffPct']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-sm text-gray-500">
            درصد اختلاف نسبت به قیمت توکنیکو حساب می‌شود. قیمت توکنیکو ذخیره نمی‌شود و در هر بازدید دوباره خوانده می‌شود.
        </p>
    @endif
</x-filament-panels::page>
