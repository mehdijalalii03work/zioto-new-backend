<x-filament-panels::page>
    <p class="mb-4 text-sm text-gray-500">
        قیمت نهایی بر اساس وزن، قیمت فلز و ضرایب اجرت محاسبه می‌شود.
    </p>

    <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-gradient-to-br from-gray-50 to-gray-100 p-4">
        <span class="text-sm font-bold text-gray-700">قیمت لحظه‌ای هر گرم (تومان)</span>

        @foreach($livePrices as $livePrice)
            <span @class([
                'rounded-lg border px-3 py-2 text-sm',
                'border-amber-500 bg-amber-50 text-amber-800' => $livePrice['kind'] === 'gold',
                'border-gray-400 bg-gray-100 text-gray-700' => $livePrice['kind'] === 'silver',
            ])>
                <strong>{{ $livePrice['label'] }}:</strong>
                {{ $livePrice['value'] !== null ? number_format($livePrice['value']) : '—' }}
            </span>
        @endforeach
    </div>

    @if(count($rows) === 0)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            محصولی با قیمت‌گذاری پویا یافت نشد. برای دیدن این صفحه، قیمت‌گذاری پویا را روی محصولات فعال کنید.
        </div>
    @else
        @php($activePeriodData = collect($periods)->firstWhere('slug', $activePeriod))

        <div class="mb-3 flex justify-center">
            <div class="inline-flex rounded-xl bg-gray-100 p-1">
                @foreach($periods as $period)
                    <button
                        type="button"
                        wire:click="setPeriod('{{ $period['slug'] }}')"
                        @class([
                            'rounded-lg px-6 py-2 text-sm transition',
                            'bg-white font-semibold text-gray-900 shadow-sm ring-1 ring-gray-950/5' => $activePeriod === $period['slug'],
                            'text-gray-500 hover:text-gray-900' => $activePeriod !== $period['slug'],
                        ])
                    >
                        {{ $period['name'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-950/5">
            <table class="w-full min-w-[1100px] border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th rowspan="2" class="border-e border-gray-200 px-3 py-2 text-start">محصول</th>
                        <th rowspan="2" class="border-e border-gray-200 px-3 py-2 text-start">نوع</th>
                        <th rowspan="2" class="border-e border-gray-200 px-3 py-2 text-start">وزن (گرم)</th>

                        @if($activePeriodData)
                            <th colspan="{{ count($roles) }}" class="border-e-2 border-gray-300 bg-gray-200 px-3 py-2 text-center">
                                {{ $activePeriodData['name'] }}
                                <span class="block text-xs font-normal text-gray-500">
                                    {{ $activePeriodData['start'] }} - {{ $activePeriodData['end'] }}
                                </span>
                            </th>
                        @endif
                    </tr>

                    <tr class="bg-gray-50">
                        @foreach($roles as $role)
                            <th class="px-2 py-1.5 text-center text-[11px] font-normal text-gray-600">
                                {{ $role['name'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-t border-gray-100 odd:bg-white even:bg-gray-50">
                            <td class="px-3 py-2">
                                <a href="{{ $row['edit_url'] }}" class="font-semibold text-primary-600 hover:underline">
                                    {{ $row['name'] }}
                                </a>
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ $row['metal_label'] }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row['weight'] }}</td>

                            @foreach($row['cells'][$activePeriod] ?? [] as $cell)
                                <td class="border-e border-gray-100 px-1 py-2 text-center align-top">
                                    @if($cell)
                                        <span class="block text-xs text-gray-500">درصد اجرت: {{ round($cell['coefficient'] * 100, 4) }}٪</span>
                                        <span class="block text-[13px] text-amber-700">اجرت: {{ number_format($cell['labor_cost']) }}</span>
                                        <span class="block text-sm font-bold text-blue-800">نهایی: {{ number_format($cell['final_price']) }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-sm text-gray-500">
            {{ number_format($productCount) }} محصول — قیمت‌ها بر حسب تومان است.
        </p>
    @endif
</x-filament-panels::page>
