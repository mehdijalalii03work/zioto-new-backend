<x-filament-panels::page>
    @php
        $toToman = fn ($value) => is_numeric($value) ? number_format((float) $value / 10, 0) : '—';
        $pct = fn ($value) => $value === null ? '—' : number_format($value, 2).'٪';
        $pctClass = fn ($value) => $value === null ? '' : ($value >= 0 ? 'text-emerald-700' : 'text-red-700');
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-filament::button wire:click="refresh" color="primary" size="sm" icon="heroicon-o-arrow-path">
            بروزرسانی
        </x-filament::button>
        <p class="text-sm text-gray-500">
            تابلو زیوتو: {{ $boardUpdatedAt ?? '—' }}
            <span class="mx-1 text-gray-300">•</span>
            تابلو توکنیکو: {{ $tokenikoUpdatedAt ?? '—' }}
        </p>
    </div>

    @if($tokenikoError)
        <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            {{ $tokenikoError }} — قیمت‌های توکنیکو نمایش داده نمی‌شوند.
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-950/5">
        <table class="w-full min-w-[900px] border-collapse text-sm">
            <thead>
                <tr class="bg-gray-100 text-gray-700">
                    <th class="border-e border-gray-200 px-3 py-2 text-start">آیتم</th>
                    <th class="border-e border-gray-200 px-3 py-2 text-start">فروش زیوتو (تومان)</th>
                    <th class="border-e border-gray-200 px-3 py-2 text-start">فروش توکنیکو (تومان)</th>
                    <th class="border-e-2 border-gray-300 bg-gray-200 px-3 py-2 text-start">اختلاف فروش ٪</th>
                    <th class="border-e border-gray-200 px-3 py-2 text-start">خرید زیوتو (تومان)</th>
                    <th class="border-e border-gray-200 px-3 py-2 text-start">خرید توکنیکو (تومان)</th>
                    <th class="px-3 py-2 text-start">اختلاف خرید ٪</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr class="border-t border-gray-100 odd:bg-white even:bg-gray-50">
                        <td class="px-3 py-2 font-semibold text-gray-900">{{ $row['label'] }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $toToman($row['ourSell']) }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $toToman($row['tokSell']) }}</td>
                        <td class="border-e-2 border-gray-300 bg-gray-200 px-3 py-2 font-bold tabular-nums {{ $pctClass($row['sellDiffPct']) }}">{{ $pct($row['sellDiffPct']) }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $toToman($row['ourBuy']) }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $toToman($row['tokBuy']) }}</td>
                        <td class="px-3 py-2 font-bold tabular-nums {{ $pctClass($row['buyDiffPct']) }}">{{ $pct($row['buyDiffPct']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-sm text-gray-500">
        درصد اختلاف نسبت به قیمت توکنیکو حساب می‌شود.
    </p>
</x-filament-panels::page>
