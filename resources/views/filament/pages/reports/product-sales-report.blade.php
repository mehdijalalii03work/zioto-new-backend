<x-filament-panels::page>
    <form wire:submit="loadReport" class="space-y-5">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-chart-bar" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="loadReport">نمایش گزارش</span>
                <span wire:loading wire:target="loadReport">در حال بارگذاری...</span>
            </x-filament::button>
        </div>
    </form>

    @if($this->canNavigateByDay())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-chevron-right" wire:click="previousDay" wire:loading.attr="disabled">
                روز قبل
            </x-filament::button>

            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                گزارش روز {{ $this->selectedDayLabel() }}
            </p>

            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-chevron-left" wire:click="nextDay" wire:loading.attr="disabled">
                روز بعد
            </x-filament::button>
        </div>
    @endif

    @if($submitted && $report->isNotEmpty())
        @php
            $maxQuantity = (int) $report->max('quantity');
            $totalQuantity = (int) $report->sum('quantity');
        @endphp

        <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6" aria-label="نمودار تعداد فروش محصولات">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">تعداد فروش به تفکیک محصول</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">سفارش‌های تأییدشده و تکمیل‌شده، مرتب‌شده از بیشترین فروش</p>
                </div>
                <div class="flex gap-2 text-sm">
                    <span class="rounded-lg bg-gray-50 px-3 py-2 text-gray-600 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10">
                        {{ number_format($report->count()) }} محصول
                    </span>
                    <span class="rounded-lg bg-primary-50 px-3 py-2 font-medium text-primary-800 ring-1 ring-primary-200 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">
                        {{ number_format($totalQuantity) }} عدد فروش
                    </span>
                </div>
            </div>

            <div class="max-h-[70vh] space-y-4 overflow-y-auto pe-1">
                @foreach($report as $product)
                    @php
                        $barWidth = $maxQuantity > 0 ? max(2, ($product['quantity'] / $maxQuantity) * 100) : 0;
                    @endphp

                    <div class="grid gap-2 sm:grid-cols-[minmax(10rem,0.34fr)_minmax(0,1fr)_5rem] sm:items-center sm:gap-4">
                        <div class="min-w-0" title="{{ $product['name'] }}">
                            <div class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                <span class="me-1 text-xs font-normal text-gray-400">{{ number_format($loop->iteration) }}.</span>
                                <span class="break-words">{{ $product['name'] }}</span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300' => $product['metal_type'] === 'gold',
                                    'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' => $product['metal_type'] === 'silver',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => $product['metal_type'] === null,
                                ])>
                                    {{ $product['metal_label'] }}
                                </span>
                                <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-400/10 dark:text-blue-300">
                                    {{ $product['form_label'] }}
                                </span>
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300' => $product['brand_label'] === 'زیوتو پلاس',
                                    'bg-primary-50 text-primary-800 dark:bg-primary-400/10 dark:text-primary-300' => $product['brand_label'] === 'زیوتو',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => ! in_array($product['brand_label'], ['زیوتو پلاس', 'زیوتو'], true),
                                ])>
                                    {{ $product['brand_label'] }}
                                </span>
                            </div>
                        </div>

                        <div class="h-5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" dir="rtl" role="img" aria-label="{{ $product['name'] }}: {{ number_format($product['quantity']) }} عدد">
                            <div @class([
                                'h-full rounded-full transition-all duration-500',
                                'bg-emerald-500 dark:bg-emerald-400' => $product['brand_label'] === 'زیوتو پلاس',
                                'bg-amber-500 dark:bg-amber-400' => $product['brand_label'] !== 'زیوتو پلاس' && $product['metal_type'] === 'gold',
                                'bg-slate-400 dark:bg-slate-500' => $product['brand_label'] !== 'زیوتو پلاس' && $product['metal_type'] === 'silver',
                                'bg-primary-500 dark:bg-primary-400' => $product['brand_label'] !== 'زیوتو پلاس' && $product['metal_type'] === null,
                            ]) style="width: {{ $barWidth }}%; {{ $product['brand_label'] !== 'زیوتو پلاس' && $product['form_label'] === 'پلاک' ? 'background-color: #752D47;' : '' }}"></div>
                        </div>

                        <div class="text-sm font-bold tabular-nums text-gray-950 dark:text-white sm:text-end">
                            {{ number_format($product['quantity']) }} <span class="font-normal text-gray-500 dark:text-gray-400">عدد</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @elseif($submitted)
        <div class="mt-6 flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700">
            <x-filament::icon icon="heroicon-o-chart-bar" class="mb-3 h-9 w-9 text-gray-400" />
            <p class="text-sm font-medium text-gray-900 dark:text-white">در این بازه فروش ثبت‌شده‌ای پیدا نشد.</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">فقط سفارش‌های تأییدشده یا تکمیل‌شده در گزارش لحاظ می‌شوند.</p>
        </div>
    @endif
</x-filament-panels::page>
