<x-filament-panels::page>
    @php $records = $this->getRecords(); @endphp
    <div class="fi-card fi-w-full rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-card-body p-6">
            {{-- Filters --}}
            <div class="mb-4 flex items-center gap-4 flex-wrap">
                {{-- Type Filter --}}
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">نوع:</span>
                    @php
                        $activeClass = 'inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-primary-600 hover:bg-primary-500 transition';
                        $inactiveClass = 'inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700 transition';
                    @endphp
                    <button wire:click="setFilterType(null)" class="{{ !$this->filterType ? $activeClass : $inactiveClass }}">
                        همه
                    </button>
                    <button wire:click="setFilterType('board')" class="{{ $this->filterType === 'board' ? $activeClass : $inactiveClass }}">
                        تابلو قیمت
                    </button>
                    <button wire:click="setFilterType('product')" class="{{ $this->filterType === 'product' ? $activeClass : $inactiveClass }}">
                        قیمت محصول
                    </button>
                </div>

                {{-- Item Filter --}}
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">آیتم:</span>
                    <select wire:change="setFilterItem($event.target.value)" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">همه</option>
                        @foreach($this->getTypeItems() as $item)
                            <option value="{{ $item }}" {{ $this->filterItem === $item ? 'selected' : '' }}>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Date Range --}}
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">از:</span>
                    <input type="text" wire:model.live="dateFrom" placeholder="1403/01/01" class="w-28 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                    <span class="text-sm text-gray-500 dark:text-gray-400">تا:</span>
                    <input type="text" wire:model.live="dateTo" placeholder="1403/12/29" class="w-28 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                </div>

                {{-- Clear --}}
                <button wire:click="clearFilters" class="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700 transition">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                    پاک کردن فیلترها
                </button>

                {{-- Prune --}}
                <div class="mr-auto">
                    <button wire:click="pruneOldRecords" wire:confirm="آیا از حذف رکوردهای قدیمی‌تر از ۹۰ روز اطمینان دارید؟" style="background-color: #dc2626; color: #fff;" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-semibold shadow-sm transition hover:opacity-90">
                        <x-heroicon-o-trash class="h-4 w-4" />
                        حذف رکوردهای قدیمی (۹۰+ روز)
                    </button>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">نوع</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">نام آیتم</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">قیمت فروش (ریال)</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">تغییرات %</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">منبع</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">تاریخ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($records as $record)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $record->_id }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $this->getTypeColor($record->type) }}">
                                        {{ $this->getTypeLabel($record->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ $record->item_name }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs text-gray-900 dark:text-white">{{ $this->formatPrice($record->sell_price) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($record->change_percent !== null)
                                        @php $changeColor = $record->change_percent > 0 ? 'text-green-600 dark:text-green-400' : ($record->change_percent < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'); @endphp
                                        <span class="font-mono text-xs {{ $changeColor }}">
                                            {{ $record->change_percent > 0 ? '+' : '' }}{{ number_format($record->change_percent, 2) }}%
                                        </span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $record->source }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $record->created_at ? \Morilog\Jalali\Jalalian::fromDateTime($record->created_at)->format('Y/m/d H:i:s') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <x-heroicon-o-chart-bar class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                        <span class="text-sm text-gray-500 dark:text-gray-400">داده‌ای یافت نشد</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($records instanceof \Illuminate\Pagination\LengthAwarePaginator && $records->hasPages())
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        صفحه {{ $records->currentPage() }} از {{ $records->lastPage() }}
                    </span>
                    <div class="flex items-center gap-1">
                        <a href="{{ $records->previousPageUrl() }}" wire:navigate
                           class="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700 transition {{ $records->onFirstPage() ? 'pointer-events-none opacity-50' : '' }}">
                            <x-heroicon-o-chevron-right class="h-4 w-4" />
                            قبلی
                        </a>
                        <a href="{{ $records->nextPageUrl() }}" wire:navigate
                           class="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700 transition {{ !$records->hasMorePages() ? 'pointer-events-none opacity-50' : '' }}">
                            بعدی
                            <x-heroicon-o-chevron-left class="h-4 w-4" />
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
