<x-filament-panels::page>
    @php($logs = $this->getLogs())
    <div class="fi-card fi-w-full rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-card-body p-6">
            <div class="mb-4 flex items-center gap-2 flex-wrap">
                <span class="text-sm text-gray-500 dark:text-gray-400">فیلتر:</span>
                @php
                    $activeClass = 'inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-primary-600 hover:bg-primary-500 transition';
                    $inactiveClass = 'inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700 transition';
                @endphp
                <button wire:click="setFilterType(null)" class="{{ !$this->filterType ? $activeClass : $inactiveClass }}">
                    همه
                </button>
                <button wire:click="setFilterType('full_sync')" class="{{ $this->filterType === 'full_sync' ? $activeClass : $inactiveClass }}">
                    همگام‌سازی کامل
                </button>
                <button wire:click="setFilterType('contact')" class="{{ $this->filterType === 'contact' ? $activeClass : $inactiveClass }}">
                    مشتری
                </button>
                <button wire:click="setFilterType('invoice')" class="{{ $this->filterType === 'invoice' ? $activeClass : $inactiveClass }}">
                    فاکتور
                </button>
                <button wire:click="setFilterType('rahkaran_invoice')" class="{{ $this->filterType === 'rahkaran_invoice' ? $activeClass : $inactiveClass }}">
                    فاکتور راهکاران
                </button>
                <button wire:click="setFilterType('rahkaran_stock_sync')" class="{{ $this->filterType === 'rahkaran_stock_sync' ? $activeClass : $inactiveClass }}">
                    موجودی/سریال راهکاران
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">شماره سفارش</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">نوع</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">وضعیت</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">پیام خطا</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">تاریخ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($logs as $log)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $log->id }}</td>
                                <td class="px-4 py-3">
                                    @if($log->order)
                                        <span class="font-mono text-xs text-gray-900 dark:text-white">{{ $log->order->order_number }}</span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $typeColors = [
                                            'full_sync' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-500 dark:ring-success-500/30',
                                            'contact' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-500 dark:ring-info-500/30',
                                            'invoice' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-500 dark:ring-warning-500/30',
                                            'rahkaran_invoice' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-500 dark:ring-info-500/30',
                                            'rahkaran_stock_sync' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-500 dark:ring-warning-500/30',
                                        ];
                                        $colorClass = $typeColors[$log->sync_type] ?? 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30';
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $colorClass }}">
                                        {{ $this->getSyncTypeLabel($log->sync_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusColors = [
                                            'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-500 dark:ring-success-500/30',
                                            'failed' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-500 dark:ring-danger-500/30',
                                        ];
                                        $statusClass = $statusColors[$log->status] ?? 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/30';
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusClass }}">
                                        {{ $this->getStatusLabel($log->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 max-w-xs truncate text-gray-500 dark:text-gray-400" title="{{ $log->error_message }}">
                                    {{ $log->error_message ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $log->created_at->format('Y/m/d H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <x-heroicon-o-document-text class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                        <span class="text-sm text-gray-500 dark:text-gray-400">لاگی یافت نشد</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between">
                <p class="m-0 text-sm text-gray-600 dark:text-gray-300">
                    @if($logs->total() > 0)
                        نمایش <span class="font-semibold text-gray-950 dark:text-white">{{ number_format($logs->firstItem()) }} تا {{ number_format($logs->lastItem()) }}</span>
                        از <span class="font-semibold text-gray-950 dark:text-white">{{ number_format($logs->total()) }}</span> لاگ
                    @else
                        هیچ لاگی برای نمایش وجود ندارد
                    @endif
                </p>

                @if($logs->lastPage() > 1)
                    @php
                        $startPage = max(1, $logs->currentPage() - 2);
                        $endPage = min($logs->lastPage(), $logs->currentPage() + 2);
                    @endphp
                    <nav aria-label="صفحه‌بندی لاگ‌ها" class="flex flex-wrap items-center justify-center gap-1" dir="rtl">
                        <button type="button" wire:click="goToPage(1)" @disabled($logs->currentPage() === 1) aria-label="صفحه اول" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <x-heroicon-o-chevron-double-right class="h-4 w-4" />
                        </button>
                        <button type="button" wire:click="goToPage({{ $logs->currentPage() - 1 }})" @disabled($logs->currentPage() === 1) aria-label="صفحه قبل" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </button>

                        @foreach(range($startPage, $endPage) as $pageNumber)
                            <button type="button" wire:click="goToPage({{ $pageNumber }})" @class([
                                'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $logs->currentPage() === $pageNumber,
                                'border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $logs->currentPage() !== $pageNumber,
                            ]) aria-current="{{ $logs->currentPage() === $pageNumber ? 'page' : 'false' }}">
                                {{ number_format($pageNumber) }}
                            </button>
                        @endforeach

                        <button type="button" wire:click="goToPage({{ $logs->currentPage() + 1 }})" @disabled($logs->currentPage() === $logs->lastPage()) aria-label="صفحه بعد" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <x-heroicon-o-chevron-left class="h-4 w-4" />
                        </button>
                        <button type="button" wire:click="goToPage({{ $logs->lastPage() }})" @disabled($logs->currentPage() === $logs->lastPage()) aria-label="صفحه آخر" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <x-heroicon-o-chevron-double-left class="h-4 w-4" />
                        </button>
                    </nav>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
