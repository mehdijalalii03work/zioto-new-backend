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
                        <div class="min-w-0 text-sm font-medium text-gray-800 dark:text-gray-200" title="{{ $product['name'] }}">
                            <span class="me-1 text-xs font-normal text-gray-400">{{ number_format($loop->iteration) }}.</span>
                            <span class="break-words">{{ $product['name'] }}</span>
                        </div>

                        <div class="h-5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" dir="rtl" role="img" aria-label="{{ $product['name'] }}: {{ number_format($product['quantity']) }} عدد">
                            <div class="h-full rounded-full bg-primary-500 transition-all duration-500 dark:bg-primary-400" style="width: {{ $barWidth }}%"></div>
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
