<x-filament-panels::page>
    @php
        $stats = $this->getStats();
        $connection = $this->getConnectionStatus();
        $errors = $this->getRecentErrors();
        $activities = $this->getRecentActivity();
        $syncPercentage = $stats['total_orders'] > 0 ? round(($stats['synced_orders'] / $stats['total_orders']) * 100) : 0;
    @endphp

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-primary-400 to-primary-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">کل سفارشات</p>
                    <p class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ number_format($stats['total_orders']) }}</p>
                </div>
                <div class="rounded-2xl bg-primary-50 p-3 dark:bg-primary-500/10">
                    <x-heroicon-o-shopping-cart class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                    <span>پیشرفت همگام‌سازی</span>
                    <span class="font-semibold text-primary-600 dark:text-primary-400">{{ $syncPercentage }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-l from-primary-500 to-primary-400 transition-all duration-500" style="width: {{ $syncPercentage }}%"></div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-emerald-400 to-emerald-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">همگام‌سازی شده</p>
                    <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ number_format($stats['synced_orders']) }}</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-3 dark:bg-emerald-500/10">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                    <span>از کل سفارشات</span>
                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($stats['synced_orders']) }} سفارش</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-l from-emerald-500 to-emerald-400 transition-all duration-500" style="width: {{ $syncPercentage }}%"></div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-red-400 to-red-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">همگام‌سازی نشده</p>
                    <p class="text-3xl font-bold tracking-tight text-red-600 dark:text-red-400">{{ number_format($stats['unsynced_orders']) }}</p>
                </div>
                <div class="rounded-2xl bg-red-50 p-3 dark:bg-red-500/10">
                    <x-heroicon-o-x-circle class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                    <span>نیاز به بررسی</span>
                    <span class="font-semibold text-red-600 dark:text-red-400">{{ number_format($stats['unsynced_orders']) }} سفارش</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-l from-red-500 to-red-400 transition-all duration-500" style="width: {{ $stats['total_orders'] > 0 ? round(($stats['unsynced_orders'] / $stats['total_orders']) * 100) : 0 }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Connection Status --}}
    <div class="mb-8 rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 rounded-2xl {{ $connection['status'] === 'success' ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-red-50 dark:bg-red-500/10' }} p-3">
                        @if($connection['status'] === 'success')
                            <div class="relative">
                                <x-heroicon-o-check-badge class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                                <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                </span>
                            </div>
                        @else
                            <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-red-600 dark:text-red-400" />
                        @endif
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">وضعیت اتصال به حسابفا</p>
                        <p class="text-base font-semibold {{ $connection['status'] === 'success' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $connection['message'] }}
                        </p>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <button wire:click="syncStock" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                        <x-heroicon-o-arrow-path class="h-4 w-4" wire:loading.class="animate-spin" wire:target="syncStock" />
                        <span wire:loading.remove wire:target="syncStock">همگام‌سازی موجودی</span>
                        <span wire:loading wire:target="syncStock">در حال انجام...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Activity Log --}}
    <div class="mb-8 rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="px-6 py-5">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">آخرین فعالیت‌ها</h3>
                @if(count($activities) > 0)
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ count($activities) }} مورد</span>
                @endif
            </div>

            @if(count($activities) > 0)
                <div class="space-y-0">
                    @foreach($activities as $index => $activity)
                        <div class="relative flex gap-4 {{ $index < count($activities) - 1 ? 'pb-6' : '' }}">
                            @if($index < count($activities) - 1)
                                <div class="absolute right-[15px] top-8 h-full w-0.5 bg-gray-100 dark:bg-white/10"></div>
                            @endif

                            <div class="flex-shrink-0 relative z-10">
                                @if($activity['status'] === 'success')
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:ring-emerald-500/30">
                                        <x-heroicon-o-check class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-50 ring-1 ring-red-200 dark:bg-red-500/10 dark:ring-red-500/30">
                                        <x-heroicon-o-x-mark class="h-4 w-4 text-red-600 dark:text-red-400" />
                                    </div>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0 pt-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ match($activity['sync_type'] ?? '') { 'full_sync' => 'همگام‌سازی کامل', 'contact' => 'مشتری', 'invoice' => 'فاکتور', default => $activity['sync_type'] ?? 'نامشخص' } }}
                                    </span>
                                    @if($activity['order']['order_number'] ?? null)
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">#{{ $activity['order']['order_number'] }}</span>
                                    @endif
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $activity['status'] === 'success' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30' : 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30' }}">
                                        {{ $activity['status'] === 'success' ? 'موفق' : 'ناموفق' }}
                                    </span>
                                </div>
                                @if($activity['error_message'] ?? null)
                                    <p class="mt-1 text-xs text-red-500 dark:text-red-400 truncate">{{ $activity['error_message'] }}</p>
                                @endif
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ \Carbon\Carbon::parse($activity['created_at'])->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="rounded-2xl bg-gray-100 p-4 dark:bg-white/5">
                        <x-heroicon-o-clock class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    </div>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">هنوز فعالیتی ثبت نشده است</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Recent Errors --}}
    @if(count($errors) > 0)
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="px-6 py-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">آخرین خطاهای همگام‌سازی</h3>
                    <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30">{{ count($errors) }} خطا</span>
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">سفارش</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">نوع</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">خطا</th>
                                <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">تاریخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($errors as $error)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5 transition-colors duration-150">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-900 dark:text-white">{{ $error['order']['order_number'] ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium ring-1 ring-inset bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30">
                                            {{ match($error['sync_type'] ?? '') { 'full_sync' => 'همگام‌سازی کامل', 'contact' => 'مشتری', 'invoice' => 'فاکتور', default => $error['sync_type'] ?? '—' } }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate text-red-600 dark:text-red-400" title="{{ $error['error_message'] ?? '' }}">{{ $error['error_message'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($error['created_at'])->format('Y/m/d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
