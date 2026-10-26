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

    @if($this->canNavigateByDay() || $this->canNavigateByPeriod())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-chevron-right" wire:click="{{ $this->canNavigateByPeriod() ? 'previousPeriod' : 'previousDay' }}" wire:loading.attr="disabled">
                {{ $this->canNavigateByPeriod() ? match ($dateRange) { 'week' => 'هفته قبل', 'month' => 'ماه قبل', 'year' => 'سال قبل' } : 'روز قبل' }}
            </x-filament::button>

            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ $this->canNavigateByPeriod() ? $this->selectedPeriodLabel() : 'گزارش روز '.$this->selectedDayLabel() }}
            </p>

            @if($this->canNavigateByPeriod() ? $this->canNavigateToNextPeriod() : $this->canNavigateToNextDay())
                <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-chevron-left" icon-position="after" wire:click="{{ $this->canNavigateByPeriod() ? 'nextPeriod' : 'nextDay' }}" wire:loading.attr="disabled">
                    {{ $this->canNavigateByPeriod() ? match ($dateRange) { 'week' => 'هفته بعد', 'month' => 'ماه بعد', 'year' => 'سال بعد' } : 'روز بعد' }}
                </x-filament::button>
            @endif
        </div>
    @endif

    @if($submitted)
        <section class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5" aria-label="خلاصه فروش محصولات">
            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">تعداد کل فروش</p>
                        <p class="mt-3 text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($summary['total_quantity']) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">عدد محصول فروخته‌شده</p>
                    </div>
                    <span class="rounded-xl bg-primary-50 p-2.5 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-shopping-bag" class="h-5 w-5" />
                    </span>
                </div>
            </article>

            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">پرفروش‌ترین محصول</p>
                        @if($summary['best_seller_name'] !== null)
                            <p class="mt-3 line-clamp-2 text-lg font-bold text-gray-950 dark:text-white" title="{{ $summary['best_seller_name'] }}">{{ $summary['best_seller_name'] }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($summary['best_seller_quantity']) }} عدد · {{ number_format($summary['best_seller_share'], 1) }}٪ از فروش
                            </p>
                        @else
                            <p class="mt-3 text-lg font-bold text-gray-400 dark:text-gray-500">—</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">در این بازه فروشی ثبت نشده</p>
                        @endif
                    </div>
                    <span class="rounded-xl bg-amber-50 p-2.5 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">
                        <x-filament::icon icon="heroicon-o-trophy" class="h-5 w-5" />
                    </span>
                </div>
            </article>

            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">تنوع محصولات فروخته‌شده</p>
                        <p class="mt-3 text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($summary['product_count']) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">محصول متفاوت</p>
                    </div>
                    <span class="rounded-xl bg-blue-50 p-2.5 text-blue-700 dark:bg-blue-400/10 dark:text-blue-300">
                        <x-filament::icon icon="heroicon-o-squares-2x2" class="h-5 w-5" />
                    </span>
                </div>
            </article>

            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">تغییر نسبت به {{ $summary['comparison_label'] ?? 'بازه قبل' }}</p>
                        @if($summary['previous_quantity'] === null)
                            <p class="mt-3 text-lg font-bold text-gray-500 dark:text-gray-400">قابل مقایسه نیست</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $dateRange === 'all' ? 'برای فیلتر «همه» دورهٔ مرجعی وجود ندارد' : 'بازهٔ زمانی انتخاب‌شده برای مقایسه معتبر نیست' }}
                            </p>
                        @else
                            @if($summary['change_percent'] === null)
                                <p class="mt-3 text-2xl font-bold text-primary-700 dark:text-primary-300">شروع فروش</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($summary['previous_quantity']) }} عدد در {{ $summary['comparison_label'] }}</p>
                            @else
                                <p @class([
                                    'mt-3 text-2xl font-bold tabular-nums',
                                    'text-emerald-700 dark:text-emerald-300' => $summary['change_percent'] > 0,
                                    'text-rose-700 dark:text-rose-300' => $summary['change_percent'] < 0,
                                    'text-gray-700 dark:text-gray-300' => $summary['change_percent'] === 0.0,
                                ])>
                                    {{ $summary['change_percent'] > 0 ? '+' : '' }}{{ number_format($summary['change_percent'], 1) }}٪
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $summary['change_quantity'] > 0 ? '+' : '' }}{{ number_format($summary['change_quantity']) }} عدد · {{ number_format($summary['previous_quantity']) }} عدد در {{ $summary['comparison_label'] }}
                                </p>
                            @endif
                        @endif
                    </div>
                    <span class="rounded-xl bg-emerald-50 p-2.5 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">
                        <x-filament::icon icon="heroicon-o-arrow-trending-up" class="h-5 w-5" />
                    </span>
                </div>
            </article>

            <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">ترکیب فروش</p>
                    <span class="rounded-xl bg-violet-50 p-2.5 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300">
                        <x-filament::icon icon="heroicon-o-chart-pie" class="h-5 w-5" />
                    </span>
                </div>
                <div class="space-y-3 text-xs">
                    <div>
                        <p class="mb-1.5 font-semibold text-gray-700 dark:text-gray-200">بر اساس فلز</p>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-gray-500 dark:text-gray-400">
                            <span>طلا {{ number_format($summary['gold_quantity']) }} عدد · {{ number_format($summary['gold_share'], 1) }}٪</span>
                            <span>نقره {{ number_format($summary['silver_quantity']) }} عدد · {{ number_format($summary['silver_share'], 1) }}٪</span>
                            @if($summary['other_metal_quantity'] > 0)
                                <span>نامشخص {{ number_format($summary['other_metal_quantity']) }} عدد · {{ number_format(max(0, 100 - $summary['gold_share'] - $summary['silver_share']), 1) }}٪</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="mb-1.5 font-semibold text-gray-700 dark:text-gray-200">بر اساس فرم</p>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-gray-500 dark:text-gray-400">
                            <span>پلاک {{ number_format($summary['plaque_quantity']) }} عدد · {{ number_format($summary['plaque_share'], 1) }}٪</span>
                            <span>شمش {{ number_format($summary['bar_quantity']) }} عدد · {{ number_format($summary['bar_share'], 1) }}٪</span>
                            @if($summary['sachmeh_quantity'] > 0)
                                <span>ساچمه {{ number_format($summary['sachmeh_quantity']) }} عدد · {{ number_format($summary['sachmeh_share'], 1) }}٪</span>
                            @endif
                            @if($summary['other_form_quantity'] > 0)
                                <span>سایر {{ number_format($summary['other_form_quantity']) }} عدد · {{ number_format($summary['other_form_share'], 1) }}٪</span>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        </section>
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
