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
                        <input id="spotPrice" type="text" wire:ignore inputmode="decimal" placeholder="مثلاً ۳۴۸٬۳۳۳٬۴۶۰" x-on:input="formatCalculatorInput($event, 'spotPrice')" class="w-full rounded-xl border-0 bg-transparent px-4 py-3.5 text-lg font-semibold tabular-nums text-gray-950 shadow-none outline-none placeholder:text-sm placeholder:font-normal placeholder:text-gray-400 focus:ring-0 dark:text-white" />
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
            @if($result)
                <div class="mb-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">قیمت نهایی با مالیات</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-primary-700 dark:text-primary-400">{{ $result['formattedTotalPrice'] }} <span class="text-base font-medium">تومان</span></p>
                </div>

                <h2 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">ریز محاسبات</h2>
                <dl class="divide-y divide-gray-100 rounded-xl border border-gray-100 px-4 dark:divide-gray-800 dark:border-gray-800">
                    <div class="flex items-start justify-between gap-4 py-3 text-sm">
                        <dt class="text-gray-600 dark:text-gray-300">قیمت هر گرم</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $result['formattedSpotPrice'] }} تومان</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3 text-sm">
                        <dt class="text-gray-600 dark:text-gray-300">وزن محصول</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $result['formattedWeight'] }} گرم</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3 text-sm">
                        <dt class="text-gray-600 dark:text-gray-300">قیمت پایه (قیمت هر گرم × وزن)</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $result['formattedProductAmount'] }} تومان</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3 text-sm">
                        <dt class="text-gray-600 dark:text-gray-300">اجرت ({{ number_format($result['laborPercent'], 2) }}٪ قیمت پایه)</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $result['formattedLaborAmount'] }} تومان</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3 text-sm">
                        <dt class="text-gray-600 dark:text-gray-300">جمع قیمت پایه و اجرت</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $result['formattedSubtotal'] }} تومان</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3 text-sm">
                        <dt class="text-gray-600 dark:text-gray-300">مبنای محاسبه مالیات: {{ $result['taxScopeLabel'] }}</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $result['formattedTaxableAmount'] }} تومان</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3 text-sm">
                        <dt class="text-gray-600 dark:text-gray-300">مالیات ({{ number_format($result['taxPercent'], 2) }}٪)</dt>
                        <dd class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $result['formattedTaxAmount'] }} تومان</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3 text-sm font-bold">
                        <dt class="text-gray-900 dark:text-white">قیمت نهایی</dt>
                        <dd class="tabular-nums text-primary-700 dark:text-primary-400">{{ $result['formattedTotalPrice'] }} تومان</dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-xl bg-gray-50 p-4 text-xs leading-6 text-gray-600 dark:bg-gray-800 dark:text-gray-300" dir="rtl">
                    قیمت پایه = قیمت لحظه‌ای هر گرم × وزن محصول<br />
                    اجرت = قیمت پایه × درصد اجرت ÷ ۱۰۰<br />
                    مالیات = مبلغ مشمول مالیات × درصد مالیات ÷ ۱۰۰<br />
                    قیمت نهایی = قیمت پایه + اجرت + مالیات
                </div>
            @else
                <div class="flex h-full min-h-64 flex-col items-center justify-center text-center">
                    <x-filament::icon icon="heroicon-o-calculator" class="mb-3 h-10 w-10 text-gray-400" />
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">آمادهٔ محاسبه</h2>
                    <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">قیمت هر گرم، وزن و درصد اجرت را وارد کنید و دکمهٔ محاسبه را بزنید تا جزئیات قیمت اینجا نمایش داده شود.</p>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
