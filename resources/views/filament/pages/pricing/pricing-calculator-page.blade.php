<x-filament-panels::page>
    <div
        x-data="{
            formatCalculatorInput(event, property) {
                const input = event.target;
                const previousValue = input.value;
                const previousCaret = input.selectionStart ?? previousValue.length;
                const digitsBeforeCaret = previousValue.slice(0, previousCaret).replace(/[^0-9۰-۹٠-٩٫.]/g, '').length;
                const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
                const arabicDigits = '٠١٢٣٤٥٦٧٨٩';
                let raw = previousValue
                    .replace(/[۰-۹]/g, (digit) => String(persianDigits.indexOf(digit)))
                    .replace(/[٠-٩]/g, (digit) => String(arabicDigits.indexOf(digit)))
                    .replace(/٫/g, '.')
                    .replace(/[٬,،\s]/g, '');

                const decimalPosition = raw.indexOf('.');

                if (decimalPosition !== -1) {
                    raw = raw.slice(0, decimalPosition + 1) + raw.slice(decimalPosition + 1).replace(/\./g, '');
                }

                const [integerPart, decimalPart] = raw.split('.', 2);
                const groupedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                const formatted = decimalPosition === -1
                    ? groupedInteger
                    : `${groupedInteger}.${decimalPart ?? ''}`;

                input.value = formatted;

                let caret = 0;
                let countedCharacters = 0;

                while (caret < formatted.length && countedCharacters < digitsBeforeCaret) {
                    if (/[0-9.]/.test(formatted[caret])) {
                        countedCharacters++;
                    }

                    caret++;
                }

                input.setSelectionRange(caret, caret);
                $wire.set(property, raw);
            },
        }"
        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]"
    >
        <form wire:submit="calculate" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-6 space-y-1">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">ورودی‌های محاسبه</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">قیمت هر گرم، وزن و درصد اجرت را دستی وارد کنید. این ماشین‌حساب به قیمت تابلو و اطلاعات محصولات متصل نیست.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 2xl:grid-cols-3">
                <div>
                    <label for="spotPrice" class="mb-2 block text-sm font-semibold text-gray-800 dark:text-gray-200">قیمت لحظه‌ای هر گرم</label>
                    <div class="relative rounded-xl border border-gray-300 bg-gray-50 transition focus-within:border-primary-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-950 dark:focus-within:bg-gray-900">
                        <input id="spotPrice" type="text" wire:ignore inputmode="decimal" placeholder="مثلاً ۳۴٬۸۳۳٬۳۴۶" x-on:input="formatCalculatorInput($event, 'spotPrice')" class="w-full rounded-xl border-0 bg-transparent px-4 py-3.5 text-lg font-semibold tabular-nums text-gray-950 shadow-none outline-none placeholder:text-sm placeholder:font-normal placeholder:text-gray-400 focus:ring-0 dark:text-white" />
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-xs font-medium text-gray-500 dark:text-gray-400">تومان</span>
                    </div>
                    @error('spotPrice') <p class="mt-1 text-sm text-danger-600">قیمت لحظه‌ای باید عددی بیشتر از صفر باشد.</p> @enderror
                </div>

                <div>
                    <label for="weight" class="mb-2 block text-sm font-semibold text-gray-800 dark:text-gray-200">وزن</label>
                    <div class="relative rounded-xl border border-gray-300 bg-gray-50 transition focus-within:border-primary-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-950 dark:focus-within:bg-gray-900">
                        <input id="weight" type="text" wire:ignore inputmode="decimal" placeholder="مثلاً ۱٫۲۵" x-on:input="formatCalculatorInput($event, 'weight')" class="w-full rounded-xl border-0 bg-transparent px-4 py-3.5 pe-12 text-lg font-semibold tabular-nums text-gray-950 shadow-none outline-none placeholder:text-sm placeholder:font-normal placeholder:text-gray-400 focus:ring-0 dark:text-white" />
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-sm font-medium text-gray-500 dark:text-gray-400">گرم</span>
                    </div>
                    @error('weight') <p class="mt-1 text-sm text-danger-600">وزن باید عددی بیشتر از صفر باشد.</p> @enderror
                </div>

                <div>
                    <label for="laborPercent" class="mb-2 block text-sm font-semibold text-gray-800 dark:text-gray-200">درصد اجرت</label>
                    <div class="relative rounded-xl border border-gray-300 bg-gray-50 transition focus-within:border-primary-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-950 dark:focus-within:bg-gray-900">
                        <input id="laborPercent" type="text" wire:ignore inputmode="decimal" placeholder="مثلاً ۷٫۵" x-on:input="formatCalculatorInput($event, 'laborPercent')" class="w-full rounded-xl border-0 bg-transparent px-4 py-3.5 pe-12 text-lg font-semibold tabular-nums text-gray-950 shadow-none outline-none placeholder:text-sm placeholder:font-normal placeholder:text-gray-400 focus:ring-0 dark:text-white" />
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-sm font-medium text-gray-500 dark:text-gray-400">٪</span>
                    </div>
                    @error('laborPercent') <p class="mt-1 text-sm text-danger-600">درصد اجرت باید بین صفر تا ۱۰۰۰ باشد.</p> @enderror
                </div>
            </div>

            <fieldset class="mt-6">
                <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">اعمال مالیات روی</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:border-gray-700 dark:has-[:checked]:bg-primary-500/10">
                        <input type="radio" value="labor" wire:model="taxScope" class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <span>
                            <span class="block text-sm font-medium text-gray-950 dark:text-white">فقط اجرت</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">مالیات فقط روی مبلغ اجرت حساب می‌شود.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:border-gray-700 dark:has-[:checked]:bg-primary-500/10">
                        <input type="radio" value="product_and_labor" wire:model="taxScope" class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <span>
                            <span class="block text-sm font-medium text-gray-950 dark:text-white">محصول و اجرت</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">مالیات روی قیمت پایه به‌علاوهٔ اجرت حساب می‌شود.</span>
                        </span>
                    </label>
                </div>
                @error('taxScope') <p class="mt-1 text-sm text-danger-600">مبنای مالیات را انتخاب کنید.</p> @enderror
            </fieldset>

            <div class="mt-5 max-w-sm">
                <label for="taxPercent" class="mb-2 block text-sm font-semibold text-gray-800 dark:text-gray-200">درصد مالیات</label>
                <div class="relative rounded-xl border border-gray-300 bg-gray-50 transition focus-within:border-primary-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-950 dark:focus-within:bg-gray-900">
                    <input id="taxPercent" type="text" wire:ignore value="{{ $taxPercent }}" inputmode="decimal" x-on:input="formatCalculatorInput($event, 'taxPercent')" class="w-full rounded-xl border-0 bg-transparent px-4 py-3.5 pe-12 text-lg font-semibold tabular-nums text-gray-950 shadow-none outline-none focus:ring-0 dark:text-white" />
                    <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-sm font-medium text-gray-500 dark:text-gray-400">٪</span>
                </div>
                @error('taxPercent') <p class="mt-1 text-sm text-danger-600">درصد مالیات باید بین صفر تا ۱۰۰۰ باشد.</p> @enderror
            </div>

            <div class="mt-7 flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-calculator" wire:loading.attr="disabled">
                    محاسبه
                </x-filament::button>
            </div>
        </form>

        <section aria-live="polite" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @if($result && $showDetails)
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">ریز محاسبات</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">فرمول و مبلغ هر مرحله</p>
                    </div>
                    <x-filament::button type="button" color="gray" size="sm" wire:click="toggleDetails" icon="heroicon-o-arrow-uturn-right">
                        بازگشت به نتیجه
                    </x-filament::button>
                </div>

                <dl class="divide-y divide-gray-100 rounded-xl border border-gray-100 px-4 dark:divide-gray-800 dark:border-gray-800">
                    <div class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <dt class="shrink-0 text-gray-600 dark:text-gray-300">قیمت لحظه‌ای هر گرم</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $result['formattedSpotPrice'] }} تومان</dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <dt class="shrink-0 text-gray-600 dark:text-gray-300">وزن محصول</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $result['formattedWeight'] }} گرم</dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <dt class="shrink-0 text-gray-600 dark:text-gray-300">قیمت پایه (قیمت هر گرم × وزن)</dt>
                        <dd class="flex flex-wrap items-center gap-2 font-medium tabular-nums text-gray-700 dark:text-gray-200" dir="ltr">
                            <span>{{ $result['formattedSpotPrice'] }} × {{ $result['formattedWeight'] }}</span>
                            <span class="text-gray-400">=</span>
                            <span class="rounded-lg bg-primary-50 px-2.5 py-1 font-bold text-primary-800 ring-1 ring-primary-200 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">{{ $result['formattedProductAmount'] }} تومان</span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <dt class="shrink-0 text-gray-600 dark:text-gray-300">اجرت ({{ number_format($result['laborPercent'], 2) }}٪ قیمت پایه)</dt>
                        <dd class="flex flex-wrap items-center gap-2 font-medium tabular-nums text-gray-700 dark:text-gray-200" dir="ltr">
                            <span>{{ $result['formattedProductAmount'] }} × {{ number_format($result['laborPercent'], 2) }}٪</span>
                            <span class="text-gray-400">=</span>
                            <span class="rounded-lg bg-primary-50 px-2.5 py-1 font-bold text-primary-800 ring-1 ring-primary-200 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">{{ $result['formattedLaborAmount'] }} تومان</span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <dt class="shrink-0 text-gray-600 dark:text-gray-300">جمع قیمت پایه و اجرت</dt>
                        <dd class="flex flex-wrap items-center gap-2 font-medium tabular-nums text-gray-700 dark:text-gray-200" dir="ltr">
                            <span>{{ $result['formattedProductAmount'] }} + {{ $result['formattedLaborAmount'] }}</span>
                            <span class="text-gray-400">=</span>
                            <span class="rounded-lg bg-primary-50 px-2.5 py-1 font-bold text-primary-800 ring-1 ring-primary-200 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">{{ $result['formattedSubtotal'] }} تومان</span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <dt class="shrink-0 text-gray-600 dark:text-gray-300">مبنای مالیات: {{ $result['taxScopeLabel'] }}</dt>
                        <dd class="flex flex-wrap items-center gap-2 font-medium tabular-nums text-gray-700 dark:text-gray-200" dir="ltr">
                            @if($result['taxScopeLabel'] === 'محصول و اجرت')
                                <span>{{ $result['formattedProductAmount'] }} + {{ $result['formattedLaborAmount'] }}</span>
                            @else
                                <span>{{ $result['formattedLaborAmount'] }} <span class="font-normal text-gray-500">(فقط اجرت)</span></span>
                            @endif
                            <span class="text-gray-400">=</span>
                            <span class="rounded-lg bg-primary-50 px-2.5 py-1 font-bold text-primary-800 ring-1 ring-primary-200 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">{{ $result['formattedTaxableAmount'] }} تومان</span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <dt class="shrink-0 text-gray-600 dark:text-gray-300">مبلغ مالیات ({{ number_format($result['taxPercent'], 2) }}٪)</dt>
                        <dd class="flex flex-wrap items-center gap-2 font-medium tabular-nums text-gray-700 dark:text-gray-200" dir="ltr">
                            <span>{{ $result['formattedTaxableAmount'] }} × {{ number_format($result['taxPercent'], 2) }}٪</span>
                            <span class="text-gray-400">=</span>
                            <span class="rounded-lg bg-primary-50 px-2.5 py-1 font-bold text-primary-800 ring-1 ring-primary-200 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">{{ $result['formattedTaxAmount'] }} تومان</span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 text-sm font-bold sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <dt class="shrink-0 text-gray-900 dark:text-white">قیمت نهایی</dt>
                        <dd class="flex flex-wrap items-center gap-2 tabular-nums text-gray-700 dark:text-gray-200" dir="ltr">
                            <span>{{ $result['formattedSubtotal'] }} + {{ $result['formattedTaxAmount'] }}</span>
                            <span class="text-gray-400">=</span>
                            <span class="rounded-lg bg-amber-100 px-2.5 py-1 font-bold text-amber-900 ring-1 ring-amber-300 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20">{{ $result['formattedTotalPrice'] }} تومان</span>
                        </dd>
                    </div>
                </dl>
            @elseif($result)
                <div class="flex h-full min-h-64 flex-col items-center justify-center text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">قیمت نهایی با مالیات</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-primary-700 dark:text-primary-400">{{ $result['formattedTotalPrice'] }} <span class="text-base font-medium">تومان</span></p>
                    <x-filament::button type="button" color="gray" size="sm" wire:click="toggleDetails" icon="heroicon-o-document-magnifying-glass" class="mt-5">
                        مشاهده جزئیات محاسبات
                    </x-filament::button>
                </div>
            @else
                <div class="flex h-full min-h-64 flex-col items-center justify-center text-center">
                    <x-filament::icon icon="heroicon-o-calculator" class="mb-3 h-10 w-10 text-gray-400" />
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">آمادهٔ محاسبه</h2>
                    <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">قیمت هر گرم، وزن و درصد اجرت را وارد کنید و دکمهٔ محاسبه را بزنید تا قیمت نهایی اینجا نمایش داده شود.</p>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
