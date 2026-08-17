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
        <div class="mt-8 fi-card fi-w-full rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-card-body p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">نتیجه گزارش</h3>
                    <span class="inline-flex items-center rounded-full bg-green-500 px-3 py-1 text-xs font-bold text-white">{{ $report->count() }} روز</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">تاریخ فاکتور</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">تعداد فاکتور</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">مبلغ خالص (ریال)</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-amber-600 dark:text-amber-400">تعداد طلا</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-amber-600 dark:text-amber-400">ریال طلا</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">تعداد نقره</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">ریال نقره</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">تعداد کل</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($report as $row)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ \Morilog\Jalali\Jalalian::fromCarbon(\Illuminate\Support\Carbon::parse($row['date']))->format('Y/m/d') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            {{ number_format($row['invoice_count']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-mono text-xs text-gray-900 dark:text-white">{{ number_format($row['net_amount']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30">
                                            {{ number_format($row['gold_count']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-mono text-xs text-amber-700 dark:text-amber-400">{{ number_format($row['gold_amount']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            {{ number_format($row['silver_count']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-mono text-xs text-gray-900 dark:text-white">{{ number_format($row['silver_amount']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-full bg-green-500 px-3 py-1 text-xs font-bold text-white">
                                            {{ number_format($row['total_count']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5 font-semibold">
                                <td class="px-4 py-3 text-center text-xs text-gray-900 dark:text-white">جمع کل</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-900 dark:text-white">{{ number_format($report->sum('invoice_count')) }}</td>
                                <td class="px-4 py-3 text-center text-xs font-mono text-gray-900 dark:text-white">{{ number_format($report->sum('net_amount')) }}</td>
                                <td class="px-4 py-3 text-center text-xs text-amber-700 dark:text-amber-400">{{ number_format($report->sum('gold_count')) }}</td>
                                <td class="px-4 py-3 text-center text-xs font-mono text-amber-700 dark:text-amber-400">{{ number_format($report->sum('gold_amount')) }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-900 dark:text-white">{{ number_format($report->sum('silver_count')) }}</td>
                                <td class="px-4 py-3 text-center text-xs font-mono text-gray-900 dark:text-white">{{ number_format($report->sum('silver_amount')) }}</td>
                                <td class="px-4 py-3 text-center text-xs font-bold text-green-600 dark:text-green-400">{{ number_format($report->sum('total_count')) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
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
