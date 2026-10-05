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
            .tir-responsive { overflow-x: auto; margin-top: 1.5rem !important; }
            table.tir-table {
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
            table.tir-table > thead.tir-thead-dark > tr > th {
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
            table.tir-table > tbody > tr > td,
            table.tir-table > tfoot.tir-tfoot-secondary > tr > td {
                padding: .6rem .6rem;
                border: 1px solid #dee2e6;
                font-size: 1rem;
                font-weight: 600;
                vertical-align: middle;
                text-align: center;
            }
            table.tir-table > tbody > tr:hover > td { background-color: rgba(0, 0, 0, .075); }
            table.tir-table > tbody > tr > td.fw-medium,
            table.tir-table .fw-medium { font-weight: 500; }
            table.tir-table > tbody > tr > td.text-end,
            table.tir-table > tfoot > tr > td.text-end { text-align: right; }
            table.tir-table .gold-col {
                background-color: orange !important;
                color: black !important;
            }
            table.tir-table .silver-col {
                background-color: silver !important;
                color: black !important;
            }
            table.tir-table > tfoot.tir-tfoot-secondary > tr > td {
                background-color: #e2e3e5;
                border-color: #d3d6d8;
                font-weight: 700;
            }
            table.tir-table > tfoot.tir-tfoot-secondary > tr > td.text-start { text-align: left; }
        </style>
        <div class="table-responsive mt-4 tir-responsive">
            <table class="table table-hover table-bordered align-middle text-center shadow-sm rounded-3 overflow-hidden tir-table">
                <thead class="table-dark tir-thead-dark">
                    <tr>
                        <th scope="col">تاریخ فاکتور</th>
                        <th scope="col">تعداد فاکتور</th>
                        <th scope="col">مبلغ خالص (ریال)</th>
                        <th scope="col" class="gold-col">تعداد طلا</th>
                        <th scope="col" class="gold-col">ریال شمش</th>
                        <th scope="col" class="silver-col">تعداد نقره</th>
                        <th scope="col" class="silver-col">ریال نقره</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report as $row)
                        <tr>
                            <td class="fw-medium">{{ \Morilog\Jalali\Jalalian::fromCarbon(\Illuminate\Support\Carbon::parse($row['date']))->format('Y/m/d') }}</td>
                            <td>{{ number_format($row['invoice_count']) }}</td>
                            <td class="text-end">{{ number_format($row['net_amount']) }}</td>
                            <td class="text-end gold-col">{{ number_format($row['gold_count']) }}</td>
                            <td class="text-end gold-col">{{ number_format($row['gold_amount']) }}</td>
                            <td class="text-end silver-col">{{ number_format($row['silver_count']) }}</td>
                            <td class="text-end silver-col">{{ number_format($row['silver_amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-secondary fw-bold tir-tfoot-secondary">
                    <tr>
                        <td class="text-start">جمع کل</td>
                        <td>{{ number_format($report->sum('invoice_count')) }}</td>
                        <td class="text-end">{{ number_format($report->sum('net_amount')) }}</td>
                        <td class="gold-col">{{ number_format($report->sum('gold_count')) }}</td>
                        <td class="text-end gold-col">{{ number_format($report->sum('gold_amount')) }}</td>
                        <td class="silver-col">{{ number_format($report->sum('silver_count')) }}</td>
                        <td class="text-end silver-col">{{ number_format($report->sum('silver_amount')) }}</td>
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
