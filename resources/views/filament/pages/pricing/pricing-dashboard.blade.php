<x-filament-panels::page>
    @php
        $fmt = fn ($value) => is_numeric($value) ? number_format((float) $value, 0) : '—';
        $toman = fn ($value) => is_numeric($value) ? number_format((float) $value, 0).' تومان' : '—';
        $jalali = function ($timestamp) {
            if (! $timestamp) {
                return '—';
            }

            return \Morilog\Jalali\Jalalian::forge((int) $timestamp)->format('Y-m-d H:i');
        };

        $sourceLabels = ['persian' => 'PersianAPI', 'tala' => 'Tala.ir'];
        $statusMap = [
            'ok' => ['موفق', 'text-emerald-600'],
            'fallback' => ['ذخیره‌شده', 'text-amber-600'],
            'stale' => ['قدیمی', 'text-amber-600'],
            'down' => ['عدم دسترسی', 'text-red-600'],
            'disabled' => ['غیرفعال', 'text-gray-500'],
        ];

        $f = $formula;
        $c = $f['coefs'] ?? [];
        $g750 = $f['gold750'] ?? [];
        $g995 = $f['gold995'] ?? [];
        $g9999 = $f['gold9999'] ?? [];
        $s9999 = $f['silver9999'] ?? [];
        $s925 = $f['silver925'] ?? [];
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">قیمت‌های فعلی</h2>
            <p class="text-sm text-gray-500">آخرین بروزرسانی: {{ $updatedAt ?: '—' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-filament::button wire:click="refreshBoard" color="gray" size="sm">
                بروزرسانی نمایش
            </x-filament::button>
            @if(auth()->user()?->can(\App\Enums\Permission::PricingEdit->value))
                <x-filament::button wire:click="forceRefresh" color="primary" size="sm" wire:loading.attr="disabled">
                    بروزرسانی داده‌های API
                </x-filament::button>
                <x-filament::button wire:click="clearCache" color="gray" size="sm" wire:loading.attr="disabled">
                    پاک کردن کش
                </x-filament::button>
            @endif
        </div>
    </div>

    {{-- وضعیت بروزرسانی --}}
    <div class="mb-6 rounded-xl bg-white p-4 ring-1 ring-gray-950/5">
        <div class="font-semibold mb-2">وضعیت بروزرسانی</div>
        @if($lastSuccessAt)
            <div class="text-sm text-gray-700">آخرین بروزرسانی موفق: {{ $lastSuccessAt }}</div>
            @if($usedFallback)
                <div class="text-sm text-amber-600 mt-1">از داده ذخیره‌شده (کش) استفاده شده است.</div>
            @endif
        @else
            <div class="text-sm text-red-600">هنوز بروزرسانی موفقی ثبت نشده است.</div>
        @endif
    </div>

    {{-- Source status --}}
    @if(!empty($sourceStatus))
        <div class="mb-6 overflow-x-auto rounded-xl bg-white ring-1 ring-gray-950/5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">منبع</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">وضعیت</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">آخرین داده موفق</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sourceStatus as $source => $status)
                        @php
                            [$label, $color] = $statusMap[$status['status'] ?? ''] ?? [($status['status'] ?? '—'), 'text-gray-500'];
                        @endphp
                        <tr>
                            <td class="px-4 py-3"><strong>{{ $sourceLabels[$source] ?? $source }}</strong></td>
                            <td class="px-4 py-3 {{ $color }}">{{ $label }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $status['last_time'] ? $jalali($status['last_time']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Current prices --}}
    @if(!empty($board))
        <div class="mb-6 overflow-x-auto rounded-xl bg-white ring-1 ring-gray-950/5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">نوع فلز</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">قیمت</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">روند</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">آخرین بروزرسانی</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($board as $key => $row)
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-4 py-3"><strong>{{ $row['name'] ?? (\App\Services\Pricing\DynamicPriceService::METAL_OPTIONS[$key] ?? $key) }}</strong></td>
                            <td class="px-4 py-3">{{ $toman($row['value'] ?? null) }}</td>
                            <td class="px-4 py-3">
                                @if(($row['trend'] ?? null) === 'up')
                                    <span class="text-emerald-600">▲ صعودی</span>
                                @elseif(($row['trend'] ?? null) === 'down')
                                    <span class="text-red-600">▼ نزولی</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $jalali($row['updated_at'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mb-6 text-sm text-gray-500">اطلاعات قیمتی موجود نیست. لطفاً اتصال API را بررسی کنید.</p>
    @endif

    <p class="mb-6 text-xs text-gray-500">مدت زمان کش: {{ $cacheDuration }} ثانیه</p>

    {{-- Base prices --}}
    <div class="mb-6 rounded-xl bg-white p-5 ring-1 ring-gray-950/5">
        <h3 class="text-base font-semibold text-gray-900">قیمت‌های پایه</h3>
        <p class="mt-1 mb-4 text-sm text-gray-500">این قیمت‌ها مستقیماً از API ها دریافت می‌شوند و پایه محاسبات هستند:</p>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">نام</th>
                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">قیمت</th>
                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">منبع</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="px-4 py-3"><strong>طلای ۷۵۰ (PersianAPI)</strong></td>
                    <td class="px-4 py-3">{{ $toman($basePrices['persian']['Gold750'] ?? null) }}</td>
                    <td class="px-4 py-3"><span class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">PersianAPI</span></td>
                </tr>
                <tr>
                    <td class="px-4 py-3"><strong>گرم ۱۸ عیار (Tala.ir)</strong></td>
                    <td class="px-4 py-3">{{ $toman($basePrices['tala']['Gold750'] ?? null) }}</td>
                    <td class="px-4 py-3"><span class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Tala.ir</span></td>
                </tr>
                <tr>
                    <td class="px-4 py-3"><strong>نقره ۹۹۹ (PersianAPI)</strong></td>
                    <td class="px-4 py-3">{{ $toman($basePrices['persian']['Silver999'] ?? null) }}</td>
                    <td class="px-4 py-3"><span class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">PersianAPI</span></td>
                </tr>
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">این سه قیمت پایه محاسبات تابلو قیمت هستند.</p>
    </div>

    {{-- Formulas --}}
    <div class="rounded-xl bg-white p-5 ring-1 ring-gray-950/5">
        <h3 class="text-base font-semibold text-gray-900">نحوه محاسبه قیمت‌ها</h3>
        <p class="mt-1 text-sm text-gray-500">سیستم قیمت‌گذاری با استفاده از منابع مختلف و ضرایب قابل تنظیم، قیمت‌های خرید و فروش را محاسبه می‌کند.</p>

        <h4 class="mt-5 mb-3 text-sm font-semibold text-gray-700">فرمول‌های محاسبه (گام به گام)</h4>

        {{-- 1. طلای ۷۵۰ --}}
        <div class="mb-5 rounded-lg border-r-4 border-[#0073aa] bg-gray-50 p-5">
            <h5 class="m-0 mb-4 font-bold text-[#0073aa]">۱. طلای ۷۵۰ (۱۸ عیار) - قیمت فروش و خرید</h5>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">📥 پیش‌نیازها (قیمت‌های پایه از API ها)</h6>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="py-2 text-start text-xs font-medium text-gray-500">منبع داده</th>
                            <th class="py-2 text-start text-xs font-medium text-gray-500">قیمت (تومان)</th>
                            <th class="py-2 text-start text-xs font-medium text-gray-500">نوع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2"><strong>PersianAPI - طلای ۷۵۰</strong></td>
                            <td class="py-2"><code class="rounded bg-blue-50 px-2 py-0.5">{{ $fmt($g750['persian'] ?? null) }}</code></td>
                            <td class="py-2 text-xs text-gray-500">مستقیم از API</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>Tala.ir - گرم ۱۸ عیار (مستقیم)</strong></td>
                            <td class="py-2"><code class="rounded bg-amber-50 px-2 py-0.5">{{ $fmt($g750['talaDirect'] ?? null) }}</code></td>
                            <td class="py-2 text-xs text-gray-500">مستقیم از API</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>قیمت دستی ورودی</strong></td>
                            <td class="py-2"><code class="rounded bg-red-50 px-2 py-0.5">{{ $fmt($g750['manual'] ?? null) }}</code></td>
                            <td class="py-2 text-xs text-gray-500">از تنظیمات</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">🧮 دریافت مستقیم طلای ۷۵۰ از Tala.ir</h6>
                <p class="my-1 text-xs text-gray-500">Tala.ir قیمت «گرم ۱۸ عیار» را مستقیماً ارائه می‌دهد؛ نیازی به تبدیل از مظنه (۷۰۵) نیست:</p>
                <div class="my-2 border-r-3 border-sky-500 bg-gray-100 p-3">
                    <code class="block text-sm text-pink-700">طلای۷۵۰ (Tala.ir) = geram18k (Tala.ir)</code>
                    <p class="mt-2 text-xs text-gray-500">💡 کلید <code>geram18k</code> با واحد تومان، مستقیم از API تلا دریافت می‌شود.</p>
                </div>
                <h6 class="mt-4 mb-2 text-xs font-semibold text-gray-600">📝 مقدار دریافتی:</h6>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div class="text-gray-500">// دریافت مستقیم از Tala.ir</div>
                    <div>گرم ۱۸ عیار (geram18k) = <strong class="text-red-600">{{ $fmt($g750['talaDirect'] ?? null) }}</strong> تومان</div>
                    <div class="mt-2 border-t border-gray-200 pt-2">
                        <strong class="text-emerald-600">= {{ $fmt($g750['talaDirect'] ?? null) }} تومان</strong>
                    </div>
                </div>
            </div>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">💰 محاسبه قیمت فروش طلای ۷۵۰</h6>
                <p class="my-1 text-xs text-gray-500"><strong>منطق:</strong> انتخاب بیشترین قیمت بین API ها و قیمت دستی</p>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div class="text-gray-500">// مرحله ۲: مقایسه قیمت‌های API</div>
                    <div>PersianAPI = <strong class="text-blue-700">{{ $fmt($g750['persian'] ?? null) }}</strong> تومان</div>
                    <div>Tala.ir = <strong class="text-amber-600">{{ $fmt($g750['talaEffective'] ?? null) }}</strong> تومان</div>
                    <div class="my-2 border-t border-gray-200 pt-2">
                        Max(PersianAPI, Tala.ir) = <strong class="text-emerald-600">{{ $fmt($g750['maxApi'] ?? null) }}</strong> تومان
                        <span class="text-gray-400">(منبع: {{ $g750['maxSource'] ?? '—' }})</span>
                    </div>

                    <div class="mt-3 text-gray-500">// مرحله ۳: بررسی قیمت دستی</div>
                    <div>قیمت دستی = <strong class="text-red-600">{{ $fmt($g750['manual'] ?? null) }}</strong> تومان</div>
                    <div>Max API = <strong>{{ $fmt($g750['maxApi'] ?? null) }}</strong> تومان</div>
                    @if(($g750['manual'] ?? null) !== null && $g750['manual'] > 0 && ($g750['maxApi'] ?? null) !== null && $g750['manual'] > $g750['maxApi'])
                        <div class="text-red-600">✓ قیمت دستی > Max API → انتخاب قیمت دستی</div>
                    @else
                        <div class="text-emerald-600">✓ قیمت دستی ≤ Max API → انتخاب Max API</div>
                    @endif

                    <div class="my-3 rounded-sm border-t-2 border-blue-700 bg-blue-50 p-2">
                        <strong class="text-blue-700">🎯 قیمت نهایی فروش = {{ $fmt($g750['sell'] ?? null) }} تومان</strong>
                        <div class="mt-1 text-gray-500">({{ $g750['sellLogic'] ?? '—' }})</div>
                    </div>
                </div>
            </div>

            <div class="rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">🏷️ محاسبه قیمت خرید طلای ۷۵۰</h6>
                <p class="my-1 text-xs text-gray-500"><strong>منطق:</strong> اگر قیمت دستی بیشتر از Max API است → ۹۹٪ قیمت فروش، در غیر اینصورت → کمترین قیمت API</p>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div class="text-gray-500">// مرحله ۴: محاسبه Min API</div>
                    <div>Min(PersianAPI, Tala.ir) = <strong class="text-amber-600">{{ $fmt($g750['minApi'] ?? null) }}</strong> تومان
                        <span class="text-gray-400">(منبع: {{ $g750['minSource'] ?? '—' }})</span>
                    </div>

                    <div class="mt-3 text-gray-500">// مرحله ۵: تعیین قیمت خرید</div>
                    @if(($g750['manual'] ?? null) !== null && $g750['manual'] > 0 && ($g750['maxApi'] ?? null) !== null && $g750['manual'] > $g750['maxApi'])
                        <div class="text-emerald-600">✓ قیمت دستی > Max API → استفاده از ضریب</div>
                    @else
                        <div class="text-emerald-600">✓ قیمت دستی ≤ Max API → استفاده از Min API</div>
                    @endif

                    <div class="my-3 rounded-sm border-t-2 border-emerald-600 bg-emerald-50 p-2">
                        <strong class="text-emerald-700">🎯 قیمت نهایی خرید = {{ $fmt($g750['buy'] ?? null) }} تومان</strong>
                        <div class="mt-1 text-gray-500">({{ $g750['buyLogic'] ?? '—' }})</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-md border-r-4 border-sky-500 bg-sky-50 p-3">
                <strong class="text-sky-700">📊 خلاصه نتیجه:</strong>
                <div class="mt-2 text-sm leading-6">
                    • <strong>قیمت فروش:</strong> {{ $toman($g750['sell'] ?? null) }} - انتخاب شده از {{ $g750['sellLogic'] ?? '—' }}<br>
                    • <strong>قیمت خرید:</strong> {{ $toman($g750['buy'] ?? null) }} - انتخاب شده از {{ $g750['buyLogic'] ?? '—' }}
                </div>
            </div>
        </div>

        {{-- 2. طلای ۹۹۵ --}}
        <div class="mb-5 rounded-lg border-r-4 border-emerald-600 bg-gray-50 p-5">
            <h5 class="m-0 mb-4 font-bold text-emerald-600">۲. طلای ۹۹۵ (۲۴ عیار) - قیمت فروش و خرید</h5>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">📥 پیش‌نیازها</h6>
                <p class="my-1 text-xs text-gray-500">طلای ۹۹۵ به طور مستقیم از طلای ۷۵۰ محاسبه می‌شود:</p>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2 w-2/5"><strong>قیمت فروش طلای ۷۵۰</strong></td>
                            <td class="py-2"><code class="rounded bg-blue-50 px-2 py-0.5">{{ $fmt($g750['sell'] ?? null) }}</code> تومان</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>قیمت خرید طلای ۷۵۰</strong></td>
                            <td class="py-2"><code class="rounded bg-emerald-50 px-2 py-0.5">{{ $fmt($g750['buy'] ?? null) }}</code> تومان</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>ضریب تبدیل ۷۵۰ به ۹۹۵</strong></td>
                            <td class="py-2"><code class="rounded bg-amber-50 px-2 py-0.5">{{ $c['gold750_to_gold995'] ?? '—' }}</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">🧮 فرمول محاسبه</h6>
                <div class="my-2 border-r-3 border-emerald-600 bg-gray-100 p-3">
                    <code class="block text-sm text-pink-700">طلای۹۹۵_فروش = طلای۷۵۰_فروش × {{ $c['gold750_to_gold995'] ?? '—' }}</code>
                    <code class="block text-sm text-pink-700">طلای۹۹۵_خرید = طلای۷۵۰_خرید × {{ $c['gold750_to_gold995'] ?? '—' }}</code>
                </div>
                <h6 class="mt-4 mb-2 text-xs font-semibold text-gray-600">📝 جایگذاری اعداد:</h6>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div class="text-gray-500">// محاسبه قیمت فروش</div>
                    <div>طلای۹۹۵_فروش = {{ $fmt($g750['sell'] ?? null) }} × {{ $c['gold750_to_gold995'] ?? '—' }}</div>
                    <div class="mt-2 border-t border-gray-200 pt-2">
                        <strong class="text-blue-700">= {{ $fmt($g995['sell'] ?? null) }} تومان</strong>
                    </div>

                    <div class="mt-3 text-gray-500">// محاسبه قیمت خرید</div>
                    <div>طلای۹۹۵_خرید = {{ $fmt($g750['buy'] ?? null) }} × {{ $c['gold750_to_gold995'] ?? '—' }}</div>
                    <div class="mt-2 border-t border-gray-200 pt-2">
                        <strong class="text-emerald-600">= {{ $fmt($g995['buy'] ?? null) }} تومان</strong>
                    </div>
                </div>
            </div>

            <div class="rounded-md border-r-4 border-emerald-600 bg-emerald-50 p-3">
                <strong class="text-emerald-700">📊 خلاصه نتیجه:</strong>
                <div class="mt-2 text-sm leading-6">
                    • <strong>قیمت فروش:</strong> {{ $toman($g995['sell'] ?? null) }}<br>
                    • <strong>قیمت خرید:</strong> {{ $toman($g995['buy'] ?? null) }}
                </div>
            </div>
        </div>

        {{-- 3. طلای ۹۹۹.۹ --}}
        <div class="mb-5 rounded-lg border-r-4 border-orange-500 bg-gray-50 p-5">
            <h5 class="m-0 mb-4 font-bold text-orange-500">۳. طلای ۹۹۹.۹ (۲۴ عیار خالص) - قیمت فروش و خرید</h5>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">📥 پیش‌نیازها</h6>
                <p class="my-1 text-xs text-gray-500">طلای ۹۹۹.۹ به طور مستقیم از طلای ۹۹۵ محاسبه می‌شود:</p>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2 w-2/5"><strong>قیمت فروش طلای ۹۹۵</strong></td>
                            <td class="py-2"><code class="rounded bg-blue-50 px-2 py-0.5">{{ $fmt($g995['sell'] ?? null) }}</code> تومان</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>قیمت خرید طلای ۹۹۵</strong></td>
                            <td class="py-2"><code class="rounded bg-emerald-50 px-2 py-0.5">{{ $fmt($g995['buy'] ?? null) }}</code> تومان</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>ضریب تبدیل ۹۹۵ به ۹۹۹.۹</strong></td>
                            <td class="py-2"><code class="rounded bg-amber-50 px-2 py-0.5">{{ $c['gold995_to_gold9999'] ?? '—' }}</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">🧮 فرمول محاسبه</h6>
                <div class="my-2 border-r-3 border-orange-500 bg-gray-100 p-3">
                    <code class="block text-sm text-pink-700">طلای۹۹۹.۹_فروش = طلای۹۹۵_فروش × {{ $c['gold995_to_gold9999'] ?? '—' }}</code>
                    <code class="block text-sm text-pink-700">طلای۹۹۹.۹_خرید = طلای۹۹۵_خرید × {{ $c['gold995_to_gold9999'] ?? '—' }}</code>
                </div>
                <h6 class="mt-4 mb-2 text-xs font-semibold text-gray-600">📝 جایگذاری اعداد:</h6>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div class="text-gray-500">// محاسبه قیمت فروش</div>
                    <div>طلای۹۹۹.۹_فروش = {{ $fmt($g995['sell'] ?? null) }} × {{ $c['gold995_to_gold9999'] ?? '—' }}</div>
                    <div class="mt-2 border-t border-gray-200 pt-2">
                        <strong class="text-blue-700">= {{ $fmt($g9999['sell'] ?? null) }} تومان</strong>
                    </div>

                    <div class="mt-3 text-gray-500">// محاسبه قیمت خرید</div>
                    <div>طلای۹۹۹.۹_خرید = {{ $fmt($g995['buy'] ?? null) }} × {{ $c['gold995_to_gold9999'] ?? '—' }}</div>
                    <div class="mt-2 border-t border-gray-200 pt-2">
                        <strong class="text-emerald-600">= {{ $fmt($g9999['buy'] ?? null) }} تومان</strong>
                    </div>
                </div>
            </div>

            <div class="rounded-md border-r-4 border-orange-500 bg-orange-50 p-3">
                <strong class="text-orange-700">📊 خلاصه نتیجه:</strong>
                <div class="mt-2 text-sm leading-6">
                    • <strong>قیمت فروش:</strong> {{ $toman($g9999['sell'] ?? null) }}<br>
                    • <strong>قیمت خرید:</strong> {{ $toman($g9999['buy'] ?? null) }}
                </div>
            </div>
        </div>

        {{-- 4. نقره ۹۹۹.۹ --}}
        <div class="mb-5 rounded-lg border-r-4 border-purple-600 bg-gray-50 p-5">
            <h5 class="m-0 mb-4 font-bold text-purple-600">۴. نقره ۹۹۹.۹ - قیمت فروش و خرید</h5>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">📥 پیش‌نیازها (قیمت‌های پایه از API ها)</h6>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="py-2 text-start text-xs font-medium text-gray-500">منبع داده</th>
                            <th class="py-2 text-start text-xs font-medium text-gray-500">قیمت (تومان)</th>
                            <th class="py-2 text-start text-xs font-medium text-gray-500">نوع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2"><strong>PersianAPI - نقره ۹۹۹</strong></td>
                            <td class="py-2"><code class="rounded bg-blue-50 px-2 py-0.5">{{ $fmt($s9999['persian999'] ?? null) }}</code></td>
                            <td class="py-2 text-xs text-gray-500">مستقیم از API</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>قیمت دستی ورودی</strong></td>
                            <td class="py-2"><code class="rounded bg-red-50 px-2 py-0.5">{{ $fmt($s9999['manual'] ?? null) }}</code></td>
                            <td class="py-2 text-xs text-gray-500">از تنظیمات</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>ضریب تبدیل ۹۹۹ به ۹۹۹.۹</strong></td>
                            <td class="py-2"><code class="rounded bg-amber-50 px-2 py-0.5">{{ $c['silver999_to_silver9999'] ?? '—' }}</code></td>
                            <td class="py-2 text-xs text-gray-500">ضریب تبدیل</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">🧮 فرمول محاسبه نقره ۹۹۹.۹ از PersianAPI</h6>
                <p class="my-1 text-xs text-gray-500">PersianAPI نقره ۹۹۹ را ارائه می‌دهد، برای تبدیل به ۹۹۹.۹:</p>
                <div class="my-2 border-r-3 border-purple-600 bg-gray-100 p-3">
                    <code class="block text-sm text-pink-700">نقره۹۹۹.۹ (PersianAPI) = نقره۹۹۹ (PersianAPI) × {{ $c['silver999_to_silver9999'] ?? '—' }}</code>
                </div>
                <h6 class="mt-4 mb-2 text-xs font-semibold text-gray-600">📝 جایگذاری اعداد:</h6>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div class="text-gray-500">// مرحله ۱: تبدیل نقره ۹۹۹ به ۹۹۹.۹</div>
                    <div>نقره۹۹۹ (PersianAPI) = <strong class="text-red-600">{{ $fmt($s9999['persian999'] ?? null) }}</strong> تومان</div>
                    <div>ضریب تبدیل = <strong class="text-sky-600">{{ $c['silver999_to_silver9999'] ?? '—' }}</strong></div>
                    <div>نقره۹۹۹.۹ (محاسبه‌شده) = {{ $fmt($s9999['persian999'] ?? null) }} × {{ $c['silver999_to_silver9999'] ?? '—' }}</div>
                    <div class="mt-2 border-t border-gray-200 pt-2">
                        <strong class="text-emerald-600">= {{ $fmt($s9999['converted'] ?? null) }} تومان</strong>
                    </div>
                </div>
            </div>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">💰 محاسبه قیمت فروش نقره ۹۹۹.۹</h6>
                <p class="my-1 text-xs text-gray-500"><strong>منطق:</strong> انتخاب بیشترین قیمت بین PersianAPI و قیمت دستی</p>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div class="text-gray-500">// مرحله ۲: بررسی قیمت دستی</div>
                    <div>نقره۹۹۹.۹ (PersianAPI) = <strong class="text-blue-700">{{ $fmt($s9999['converted'] ?? null) }}</strong> تومان</div>
                    <div>قیمت دستی = <strong class="text-red-600">{{ $fmt($s9999['manual'] ?? null) }}</strong> تومان</div>
                    @if(($s9999['manual'] ?? null) !== null && $s9999['manual'] > 0 && ($s9999['converted'] ?? null) !== null && $s9999['manual'] > $s9999['converted'])
                        <div class="mt-2 text-red-600">✓ قیمت دستی > PersianAPI → انتخاب قیمت دستی</div>
                    @else
                        <div class="mt-2 text-emerald-600">✓ قیمت دستی ≤ PersianAPI → انتخاب PersianAPI</div>
                    @endif

                    <div class="my-3 rounded-sm border-t-2 border-purple-600 bg-purple-50 p-2">
                        <strong class="text-purple-700">🎯 قیمت نهایی فروش = {{ $fmt($s9999['sell'] ?? null) }} تومان</strong>
                        <div class="mt-1 text-gray-500">({{ $s9999['sellLogic'] ?? '—' }})</div>
                    </div>
                </div>
            </div>

            <div class="rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">🏷️ محاسبه قیمت خرید نقره ۹۹۹.۹</h6>
                <p class="my-1 text-xs text-gray-500"><strong>منطق:</strong> ۹۹٪ قیمت فروش</p>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div class="text-gray-500">// مرحله ۳: محاسبه قیمت خرید</div>
                    <div>نقره۹۹۹.۹_خرید = نقره۹۹۹.۹_فروش × {{ $c['buy'] ?? '—' }}</div>
                    <div>نقره۹۹۹.۹_خرید = {{ $fmt($s9999['sell'] ?? null) }} × {{ $c['buy'] ?? '—' }}</div>

                    <div class="my-3 rounded-sm border-t-2 border-emerald-600 bg-emerald-50 p-2">
                        <strong class="text-emerald-700">🎯 قیمت نهایی خرید = {{ $fmt($s9999['buy'] ?? null) }} تومان</strong>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-md border-r-4 border-purple-600 bg-purple-50 p-3">
                <strong class="text-purple-800">📊 خلاصه نتیجه:</strong>
                <div class="mt-2 text-sm leading-6">
                    • <strong>قیمت فروش:</strong> {{ $toman($s9999['sell'] ?? null) }} - انتخاب شده از {{ $s9999['sellLogic'] ?? '—' }}<br>
                    • <strong>قیمت خرید:</strong> {{ $toman($s9999['buy'] ?? null) }} - محاسبه شده از ۹۹٪ قیمت فروش
                </div>
            </div>
        </div>

        {{-- 5. نقره ۹۲۵ --}}
        <div class="mb-5 rounded-lg border-r-4 border-slate-500 bg-gray-50 p-5">
            <h5 class="m-0 mb-4 font-bold text-slate-600">۵. نقره ۹۲۵ - قیمت فروش و خرید</h5>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">📥 پیش‌نیازها</h6>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="py-2 text-start text-xs font-medium text-gray-500">منبع داده</th>
                            <th class="py-2 text-start text-xs font-medium text-gray-500">مقدار</th>
                            <th class="py-2 text-start text-xs font-medium text-gray-500">نوع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2"><strong>قیمت فروش نقره ۹۹۹.۹</strong></td>
                            <td class="py-2"><code class="rounded bg-blue-50 px-2 py-0.5">{{ $fmt($s9999['sell'] ?? null) }}</code></td>
                            <td class="py-2 text-xs text-gray-500">مبنای محاسبه</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>ضریب تبدیل نقره ۹۲۵ (فروش)</strong></td>
                            <td class="py-2"><code class="rounded bg-amber-50 px-2 py-0.5">{{ $c['silver925_sell'] ?? '—' }}</code></td>
                            <td class="py-2 text-xs text-gray-500">ضریب تبدیل</td>
                        </tr>
                        <tr>
                            <td class="py-2"><strong>ضریب تبدیل نقره ۹۲۵ (خرید)</strong></td>
                            <td class="py-2"><code class="rounded bg-amber-50 px-2 py-0.5">{{ $c['silver925_buy'] ?? '—' }}</code></td>
                            <td class="py-2 text-xs text-gray-500">ضریب تبدیل</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mb-4 rounded-md border border-gray-200 bg-white p-4">
                <h6 class="m-0 mb-3 text-sm font-semibold text-gray-800">🧮 فرمول‌ها</h6>
                <div class="my-2 border-r-3 border-slate-500 bg-gray-100 p-3">
                    <code class="block text-sm text-pink-700">نقره۹۲۵_فروش = نقره۹۹۹.۹_فروش × {{ $c['silver925_sell'] ?? '—' }}</code>
                    <code class="mt-1 block text-sm text-pink-700">نقره۹۲۵_خرید = نقره۹۲۵_فروش × {{ $c['silver925_buy'] ?? '—' }}</code>
                </div>
                <h6 class="mt-4 mb-2 text-xs font-semibold text-gray-600">📝 جایگذاری اعداد:</h6>
                <div class="rounded-md border border-dashed border-gray-400 bg-gray-50 p-3 font-mono text-xs leading-7">
                    <div>نقره۹۲۵_فروش = {{ $fmt($s9999['sell'] ?? null) }} × {{ $c['silver925_sell'] ?? '—' }}</div>
                    <div class="mt-2 border-t border-gray-200 pt-2">
                        <strong class="text-slate-600">= {{ $fmt($s925['sell'] ?? null) }} تومان</strong>
                    </div>
                    <div class="mt-2">نقره۹۲۵_خرید = {{ $fmt($s925['sell'] ?? null) }} × {{ $c['silver925_buy'] ?? '—' }}</div>
                    <div class="mt-2 border-t border-gray-200 pt-2">
                        <strong class="text-slate-600">= {{ $fmt($s925['buy'] ?? null) }} تومان</strong>
                    </div>
                </div>
            </div>

            <div class="rounded-md border-r-4 border-slate-500 bg-slate-100 p-3">
                <strong class="text-slate-700">📊 خلاصه نتیجه:</strong>
                <div class="mt-2 text-sm leading-6">
                    • <strong>قیمت فروش:</strong> {{ $toman($s925['sell'] ?? null) }}<br>
                    • <strong>قیمت خرید:</strong> {{ $toman($s925['buy'] ?? null) }}
                </div>
            </div>
        </div>

        <p class="rounded-md border-r-4 border-amber-500 bg-amber-50 p-3 text-sm">
            <strong>نکته:</strong>
            تمام ضرایب از تب «ضرایب محاسبه قیمت» قابل تغییر هستند. هر تغییری در ضرایب بلافاصله در محاسبات قیمت اعمال می‌شود.
        </p>
    </div>
</x-filament-panels::page>
