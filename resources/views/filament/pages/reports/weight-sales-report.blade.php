<x-filament-panels::page>
    <form wire:submit="loadReport">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="loadReport">نمایش گزارش</span>
                <span wire:loading wire:target="loadReport">در حال بارگذاری...</span>
            </x-filament::button>
        </div>
    </form>

    @if($report->count())
        <style>
            .wsr-responsive { overflow-x: auto; margin-top: 1.5rem !important; }
            table.wsr-table {
                width: 100%;
                margin-bottom: 1rem;
                color: #212529;
                vertical-align: middle;
                border-color: #dee2e6;
                border-collapse: collapse;
                font-size: 1rem;
                font-weight: 400;
                line-height: 1.5;
                background-color: #fff;
                box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important;
                border-radius: .375rem;
                overflow: hidden;
            }
            table.wsr-table > thead.wsr-thead-dark > tr > th {
                background-color: #212529;
                border-color: #373b3e;
                color: #fff;
                padding: .65rem .6rem;
                border: 1px solid #373b3e;
                font-size: 1.05rem;
                font-weight: 800;
                text-align: center;
                vertical-align: middle;
                white-space: nowrap;
            }
            table.wsr-table > tbody > tr > td,
            table.wsr-table > tfoot.wsr-tfoot-secondary > tr > td {
                padding: .6rem .6rem;
                border: 1px solid #dee2e6;
                font-size: 1rem;
                font-weight: 600;
                vertical-align: middle;
                text-align: center;
            }
            table.wsr-table > tbody > tr:hover > td { background-color: rgba(0, 0, 0, .075); }
            table.wsr-table > tbody > tr > td.fw-medium,
            table.wsr-table .fw-medium { font-weight: 500; }
            table.wsr-table > tbody > tr > td.text-end,
            table.wsr-table > tfoot > tr > td.text-end { text-align: right; }
            table.wsr-table .gold-col {
                background-color: orange !important;
                color: black !important;
            }
            table.wsr-table .silver-col {
                background-color: silver !important;
                color: black !important;
            }
            table.wsr-table .gold750-col {
                background-color: #ffe8cc !important;
                color: black !important;
            }
            table.wsr-table .makingFee-col {
                background-color: #d0ebff !important;
                color: black !important;
            }
            table.wsr-table .bg-success {
                --bs-bg-opacity: 1;
                background-color: rgba(25, 135, 84, var(--bs-bg-opacity)) !important;
            }
            table.wsr-table .bg-opacity-10 { --bs-bg-opacity: .1; }
            table.wsr-table .bg-opacity-25 { --bs-bg-opacity: .25; }
            table.wsr-table .text-white { color: #fff !important; }
            table.wsr-table .text-danger { color: #dc3545 !important; }
            table.wsr-table .price-col { font-weight: 800; }
            table.wsr-table > tfoot.wsr-tfoot-secondary > tr > td {
                background-color: #e2e3e5;
                border-color: #d3d6d8;
                font-weight: 700;
            }
            table.wsr-table > tfoot.wsr-tfoot-secondary > tr > td.text-start { text-align: left; }
        </style>
        <div class="table-responsive mt-4 wsr-responsive">
            <table class="table table-hover table-bordered align-middle text-center shadow-sm rounded-3 overflow-hidden wsr-table">
                <thead class="table-dark wsr-thead-dark">
                    <tr>
                        <th scope="col">تاریخ شمسی</th>
                        <th scope="col" class="fw-bold bg-success text-white">جمع کل ریالی <span class="text-danger">طلاونقره</span></th>
                        <th scope="col">تعداد اقلام</th>
                        <th scope="col" class="gold-col">تعداد شمش طلا</th>
                        <th scope="col" class="gold-col">وزن شمش طلا</th>
                        <th scope="col" class="gold-col">میانگین وزن/قلم</th>
                        <th scope="col" class="gold-col">ریالی شمش ها</th>
                        <th scope="col" class="gold-col">قیمت روز شمش (ریال/گرم)</th>
                        <th scope="col" class="gold750-col">تعداد طلا ۷۵۰</th>
                        <th scope="col" class="gold750-col">وزن طلا ۷۵۰</th>
                        <th scope="col" class="gold750-col">میانگین وزن/قلم</th>
                        <th scope="col" class="gold750-col">ریالی طلا ۷۵۰</th>
                        <th scope="col" class="gold750-col">قیمت روز طلا ۷۵۰ (ریال/گرم)</th>
                        <th scope="col" class="silver-col">تعداد نقره</th>
                        <th scope="col" class="silver-col">وزن نقره</th>
                        <th scope="col" class="silver-col">میانگین وزن/قلم</th>
                        <th scope="col" class="silver-col">مبلغ فاکتورهای نقره</th>
                        <th scope="col" class="silver-col">قیمت روز نقره (ریال/گرم)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report as $row)
                        <tr>
                            <td class="fw-medium">{{ \Morilog\Jalali\Jalalian::fromCarbon(\Illuminate\Support\Carbon::parse($row['date']))->format('Y/m/d') }}</td>
                            <td class="text-end fw-bold bg-success bg-opacity-10">{{ number_format($row['total_rial']) }}</td>
                            <td>{{ number_format($row['total_items']) }}</td>
                            <td class="gold-col">{{ number_format($row['bar_count']) }}</td>
                            <td class="text-end gold-col">{{ rtrim(rtrim(number_format($row['bar_weight'], 2), '0'), '.') }}</td>
                            <td class="text-end gold-col">{{ rtrim(rtrim(number_format($row['bar_avg_weight'], 2), '0'), '.') }}</td>
                            <td class="text-end gold-col">{{ number_format($row['bar_rial']) }}</td>
                            <td class="text-end gold-col price-col">{{ number_format($row['bar_price']) }}</td>
                            <td class="gold750-col">{{ number_format($row['gold750_count']) }}</td>
                            <td class="text-end gold750-col">{{ rtrim(rtrim(number_format($row['gold750_weight'], 2), '0'), '.') }}</td>
                            <td class="text-end gold750-col">{{ rtrim(rtrim(number_format($row['gold750_avg_weight'], 2), '0'), '.') }}</td>
                            <td class="text-end gold750-col">{{ number_format($row['gold750_rial']) }}</td>
                            <td class="text-end gold750-col price-col">{{ number_format($row['gold750_price']) }}</td>
                            <td class="silver-col">{{ number_format($row['silver_count']) }}</td>
                            <td class="text-end silver-col">{{ rtrim(rtrim(number_format($row['silver_weight'], 2), '0'), '.') }}</td>
                            <td class="text-end silver-col">{{ rtrim(rtrim(number_format($row['silver_avg_weight'], 2), '0'), '.') }}</td>
                            <td class="text-end silver-col">{{ number_format($row['silver_rial']) }}</td>
                            <td class="text-end silver-col price-col">{{ number_format($row['silver_price']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-secondary fw-bold wsr-tfoot-secondary">
                    <tr>
                        @php
                            $totalBarWeight = $report->sum('bar_weight');
                            $totalBarCount = $report->sum('bar_count');
                            $totalGold750Weight = $report->sum('gold750_weight');
                            $totalGold750Count = $report->sum('gold750_count');
                            $totalSilverWeight = $report->sum('silver_weight');
                            $totalSilverCount = $report->sum('silver_count');
                        @endphp
                        <td class="text-start">جمع کل</td>
                        <td class="text-end fw-bold bg-success bg-opacity-25">{{ number_format($report->sum('total_rial')) }}</td>
                        <td>{{ number_format($report->sum('total_items')) }}</td>
                        <td class="gold-col">{{ number_format($totalBarCount) }}</td>
                        <td class="text-end gold-col">{{ rtrim(rtrim(number_format($totalBarWeight, 2), '0'), '.') }}</td>
                        <td class="text-end gold-col">{{ $totalBarCount > 0 ? rtrim(rtrim(number_format($totalBarWeight / $totalBarCount, 2), '0'), '.') : '—' }}</td>
                        <td class="text-end gold-col">{{ number_format($report->sum('bar_rial')) }}</td>
                        <td class="text-end gold-col price-col">{{ $totalBarWeight > 0 ? number_format($report->sum('bar_rial') / $totalBarWeight) : '—' }}</td>
                        <td class="gold750-col">{{ number_format($totalGold750Count) }}</td>
                        <td class="text-end gold750-col">{{ rtrim(rtrim(number_format($totalGold750Weight, 2), '0'), '.') }}</td>
                        <td class="text-end gold750-col">{{ $totalGold750Count > 0 ? rtrim(rtrim(number_format($totalGold750Weight / $totalGold750Count, 2), '0'), '.') : '—' }}</td>
                        <td class="text-end gold750-col">{{ number_format($report->sum('gold750_rial')) }}</td>
                        <td class="text-end gold750-col price-col">{{ $totalGold750Weight > 0 ? number_format($report->sum('gold750_rial') / $totalGold750Weight) : '—' }}</td>
                        <td class="silver-col">{{ number_format($totalSilverCount) }}</td>
                        <td class="text-end silver-col">{{ rtrim(rtrim(number_format($totalSilverWeight, 2), '0'), '.') }}</td>
                        <td class="text-end silver-col">{{ $totalSilverCount > 0 ? rtrim(rtrim(number_format($totalSilverWeight / $totalSilverCount, 2), '0'), '.') : '—' }}</td>
                        <td class="text-end silver-col">{{ number_format($report->sum('silver_rial')) }}</td>
                        <td class="text-end silver-col price-col">{{ $totalSilverWeight > 0 ? number_format($report->sum('silver_rial') / $totalSilverWeight) : '—' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @elseif($submitted)
        <div class="mt-8 flex flex-col items-center justify-center py-12 text-center">
            <div class="rounded-2xl bg-gray-100 p-4 dark:bg-white/5">
                <x-heroicon-o-document-chart-bar class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            </div>
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">داده‌ای برای نمایش یافت نشد</p>
        </div>
    @endif
</x-filament-panels::page>
