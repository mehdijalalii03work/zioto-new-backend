<x-filament-panels::page>
    @php
        $toToman = fn ($value) => is_numeric($value) ? number_format((float) $value / 10, 0) : '—';
        $pct = fn ($value) => $value === null ? '—' : number_format($value, 4).'٪';
        $pctClass = fn ($value) => $value === null ? '' : ($value >= 0 ? 'text-emerald-700' : 'text-red-700');
    @endphp

    <p class="mb-4 text-sm text-gray-500">
        {{ number_format($productCount) }} محصول دارای شناسهٔ تپسی‌شاپ
    </p>

    @if(count($rows) === 0)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            محصولی با شناسهٔ تپسی‌شاپ یافت نشد.
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-950/5">
            <table class="w-full min-w-[900px] border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th class="border-e border-gray-200 px-3 py-2 text-start">محصول</th>
                        <th class="border-e border-gray-200 px-3 py-2 text-start">قیمت زیوتو (تومان)</th>
                        <th class="border-e border-gray-200 px-3 py-2 text-start">قیمت ارسالی به تپسی‌شاپ (تومان)</th>
                        <th class="px-3 py-2 text-start">اختلاف ٪</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-t border-gray-100 odd:bg-white even:bg-gray-50">
                            <td class="px-3 py-2">
                                <div class="font-semibold text-gray-900">{{ $row['name'] }}</div>
                                <div class="font-mono text-xs text-gray-400" dir="ltr">{{ $row['productId'] }}</div>
                            </td>
                            <td class="px-3 py-2 tabular-nums">{{ $toToman($row['ourPrice']) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $toToman($row['tapsiPrice']) }}</td>
                            <td class="px-3 py-2 font-bold tabular-nums {{ $pctClass($row['diffPct']) }}">{{ $pct($row['diffPct']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-sm text-gray-500">
            قیمت مرجع تپسی‌شاپ از قیمت فعلی زیوتو و ضریب تنظیم‌شده در تنظیمات تپسی‌شاپ محاسبه می‌شود؛ اختلاف نسبت به همین قیمت ارسالی سنجیده می‌شود.
        </p>
    @endif
</x-filament-panels::page>
