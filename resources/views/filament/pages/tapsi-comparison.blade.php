<x-filament-panels::page>
    @php
        $toToman = fn ($value) => is_numeric($value) ? number_format((float) $value / 10, 0) : '—';
        $pct = fn ($value) => $value === null ? '—' : number_format($value, 2).'٪';
        $pctClass = fn ($value) => $value === null ? '' : ($value >= 0 ? 'text-emerald-700' : 'text-red-700');
        $belowThresholdRows = array_values(array_filter($rows, fn ($row) => $row['isBelowThreshold'] === true));
        $aboveThresholdRows = array_values(array_filter($rows, fn ($row) => $row['isBelowThreshold'] === false));
        $unknownPriceRows = array_values(array_filter($rows, fn ($row) => $row['isBelowThreshold'] === null));
        $sections = [
            ['title' => 'محصولات زیر آستانه', 'condition' => 'کمتر از '.number_format($markupThreshold), 'rows' => $belowThresholdRows],
            ['title' => 'محصولات بالای آستانه', 'condition' => number_format($markupThreshold).' و بیشتر', 'rows' => $aboveThresholdRows],
            ['title' => 'محصولات بدون قیمت', 'condition' => 'قیمت قابل محاسبه نیست', 'rows' => $unknownPriceRows],
        ];
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-filament::button wire:click="refresh" color="primary" size="sm" icon="heroicon-o-arrow-path">
            بروزرسانی
        </x-filament::button>
        <p class="text-sm text-gray-500">
            {{ number_format($productCount) }} محصول دارای شناسهٔ تپسی‌شاپ
        </p>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">آستانهٔ قیمت <span dir="ltr">TAPSI_MARKUP_THRESHOLD</span></p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($markupThreshold) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">ضریب زیر آستانه <span dir="ltr">TAPSI_MARKUP_BELOW_50M</span></p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($markupBelowThreshold, 2) }}٪</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">ضریب از آستانه به بالا <span dir="ltr">TAPSI_MARKUP_ABOVE_50M</span></p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($markupAboveThreshold, 2) }}٪</p>
        </div>
    </div>

    @if(count($rows) === 0)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            محصولی با شناسهٔ تپسی‌شاپ یافت نشد.
        </div>
    @else
        @foreach($sections as $section)
            @if(count($section['rows']) > 0)
                <section class="mb-6">
                    <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $section['title'] }}</h2>
                        <div class="text-sm text-gray-500">
                            {{ $section['condition'] }} <span dir="ltr">· {{ count($section['rows']) }} محصول</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-950/5">
                        <table class="w-full min-w-[900px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700">
                                    <th class="border-e border-gray-200 px-3 py-2 text-start">محصول</th>
                                    <th class="border-e border-gray-200 px-3 py-2 text-start">
                                        <button type="button" wire:click="sortByPrice" class="inline-flex items-center gap-1 font-semibold hover:text-gray-900" title="مرتب‌سازی بر اساس قیمت زیوتو">
                                            قیمت زیوتو (تومان)
                                            <span aria-hidden="true">{{ $sortColumn === 'price' ? ($priceSortDirection === 'asc' ? '▲' : '▼') : '↕' }}</span>
                                        </button>
                                    </th>
                                    <th class="border-e border-gray-200 px-3 py-2 text-start">ضریب اعمال‌شده</th>
                                    <th class="border-e border-gray-200 px-3 py-2 text-start">قیمت ارسالی به تپسی‌شاپ (تومان)</th>
                                    <th class="px-3 py-2 text-start">
                                        <button type="button" wire:click="sortByDiff" class="inline-flex items-center gap-1 font-semibold hover:text-gray-900" title="مرتب‌سازی بر اساس قدرمطلق اختلاف">
                                            اختلاف ٪
                                            <span aria-hidden="true">{{ $sortColumn === 'diff' ? ($sortDirection === 'desc' ? '▼' : '▲') : '↕' }}</span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($section['rows'] as $row)
                                    <tr class="border-t border-gray-100 odd:bg-white even:bg-gray-50">
                                        <td class="px-3 py-2">
                                            <div class="font-semibold text-gray-900">{{ $row['name'] }}</div>
                                            <div class="font-mono text-xs text-gray-400" dir="ltr">{{ $row['productId'] }}</div>
                                        </td>
                                        <td class="px-3 py-2 tabular-nums">{{ $toToman($row['ourPrice']) }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $row['markupPercent'] === null ? '—' : number_format($row['markupPercent'], 2).'٪' }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $toToman($row['tapsiPrice']) }}</td>
                                        <td class="px-3 py-2 font-bold tabular-nums {{ $pctClass($row['diffPct']) }}">{{ $pct($row['diffPct']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endforeach

        <p class="mt-3 text-sm text-gray-500">
            قیمت مرجع تپسی‌شاپ از قیمت فعلی زیوتو و ضریب تنظیم‌شده در تنظیمات تپسی‌شاپ محاسبه می‌شود؛ اختلاف نسبت به همین قیمت ارسالی سنجیده می‌شود.
        </p>
    @endif
</x-filament-panels::page>
