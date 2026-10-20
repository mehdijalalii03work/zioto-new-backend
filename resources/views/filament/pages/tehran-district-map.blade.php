<x-filament-panels::page>
    {{-- <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['label' => $addressSource === 'orders' ? 'مشتریان دارای سفارش و آدرس تهران' : 'مشتریان دارای آدرس ثبت‌شده تهران', 'value' => $stats['customers_with_tehran_address']],
            ['label' => 'منطقه صریح در آدرس', 'value' => $stats['confirmed_customers']],
            ['label' => 'تطبیق محتمل از روی محله', 'value' => $stats['probable_customers']],
            ['label' => 'نشانه‌های متعارض', 'value' => $stats['ambiguous_customers']],
            ['label' => 'بدون تطبیق منطقه', 'value' => $stats['unmatched_customers']],
        ] as $stat)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($stat['value']) }}</p>
            </div>
        @endforeach
    </div> --}}

    <div class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">نقشه مناطق ۲۲گانه تهران</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                عدد داخل هر منطقه، تعداد مشتریان دارای تطبیق یکتاست؛ عبارت صریح منطقه و تطبیق نام محله جداگانه شمرده می‌شوند.
            </p>
        </div>

        <div class="mb-5 flex flex-wrap items-center gap-3" dir="rtl">
            <label for="tehran-address-source" class="text-sm font-medium text-gray-700 dark:text-gray-200">منبع آدرس</label>
            <select id="tehran-address-source" wire:model.live="addressSource" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                <option value="orders">مشتریان دارای سفارش سایت</option>
                <option value="saved_addresses">همه آدرس‌های ثبت‌شده مشتریان</option>
            </select>
        </div>

        <style>
            .tehran-districts-map {
                display: block;
                width: 100%;
                max-height: 75vh;
                margin-inline: auto;
            }

            .tehran-district {
                fill: #f3e8dc;
                stroke: #8b6545;
                stroke-width: 1.5;
                stroke-linejoin: round;
                vector-effect: non-scaling-stroke;
                transition: fill 150ms ease;
            }

            .tehran-district:hover {
                filter: brightness(.92);
            }

            .tehran-district-label {
                fill: #3f2b1b;
                font-family: inherit;
                font-size: 18px;
                font-weight: 700;
                text-anchor: middle;
                dominant-baseline: central;
                pointer-events: none;
            }

            .tehran-district-count {
                fill: #3f2b1b;
                font-family: inherit;
                font-size: 13px;
                font-weight: 700;
                text-anchor: middle;
                dominant-baseline: central;
                pointer-events: none;
            }
        </style>

        <div class="overflow-x-auto" dir="ltr">
            @include('filament.pages.partials.tehran-districts-map')
        </div>

        <div class="mt-3 flex items-center justify-center gap-2 text-xs text-gray-500 dark:text-gray-400" dir="rtl">
            <span>مشتری کمتر</span>
            <span class="h-3 w-24 rounded-full" style="background: linear-gradient(to left, #f3e8dc, #be4123)"></span>
            <span>مشتری بیشتر</span>
        </div>

        {{-- <div class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-4" dir="rtl">
            @foreach ($districtData as $districtNumber => $data)
                <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                    <span class="font-medium text-gray-800 dark:text-gray-100">منطقه {{ $districtNumber }}</span>
                    <span class="text-gray-600 dark:text-gray-300">
                        {{ number_format($data['customers']) }} مشتری
                        <span class="text-xs">(صریح {{ number_format($data['confirmed']) }} · محتمل {{ number_format($data['probable']) }})</span>
                    </span>
                </div>
            @endforeach
        </div> --}}

        <p class="mt-4 text-xs leading-6 text-amber-800 dark:text-amber-300">
            @if ($addressSource === 'orders')
                فقط مشتریان دارای سفارش سایت زیوتو شمرده می‌شوند؛ آدرس فعلی حساب مبناست.
            @else
                همه مشتریان دارای آدرس تهران شمرده می‌شوند، حتی اگر سفارشی ثبت نکرده باشند.
            @endif
        </p>

    </div>
</x-filament-panels::page>
