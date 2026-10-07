<x-filament-panels::page>
    @php
        $fmt = fn ($value) => is_numeric($value) ? number_format((float) $value, 0) : '—';
        // Board prices are stored in Rials; convert to Tomans for display
        $toToman = fn ($value) => is_numeric($value) ? number_format((float) $value / 10, 0) : '—';
        $toman = fn ($value) => is_numeric($value) ? number_format((float) $value / 10, 0).' تومان' : '—';
        $jalali = function ($timestamp) {
            if (! $timestamp) {
                return '—';
            }

            return \Morilog\Jalali\Jalalian::forge((int) $timestamp, new \DateTimeZone((string) config('app.timezone')))->format('H:i Y-m-d');
        };

        $trendBadge = function (?string $trend) {
            return match ($trend) {
                'up' => '<span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><span aria-hidden="true">▲</span> صعودی</span>',
                'down' => '<span class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700"><span aria-hidden="true">▼</span> نزولی</span>',
                default => '<span class="text-xs text-gray-400">بدون تغییر</span>',
            };
        };

        $sourceLabels = ['persian' => 'PersianAPI', 'tala' => 'Tala.ir'];
        $statusMap = [
            'ok' => ['موفق', 'text-emerald-600', 'bg-emerald-500'],
            'fallback' => ['ذخیره‌شده', 'text-amber-600', 'bg-amber-500'],
            'stale' => ['قدیمی', 'text-amber-600', 'bg-amber-500'],
            'down' => ['عدم دسترسی', 'text-red-600', 'bg-red-500'],
            'disabled' => ['غیرفعال', 'text-gray-500', 'bg-gray-400'],
        ];

        $okCount = 0;
        foreach ($sourceStatus as $status) {
            if (($status['status'] ?? '') === 'ok') {
                $okCount++;
            }
        }
        $totalCount = count($sourceStatus);

        $latestTs = 0;
        foreach ($sourceStatus as $status) {
            $time = (int) ($status['last_time'] ?? 0);
            if ($time > $latestTs) {
                $latestTs = $time;
            }
        }
        $agoFrom = function (int $ts): string {
            if ($ts <= 0) {
                return '—';
            }

            $diff = max(0, time() - $ts);

            return match (true) {
                $diff < 60 => 'همین حالا',
                $diff < 3600 => floor($diff / 60).' دقیقه پیش',
                $diff < 86400 => floor($diff / 3600).' ساعت پیش',
                default => floor($diff / 86400).' روز پیش',
            };
        };

        $agoLabel = $agoFrom($latestTs);
        $requestAgoLabel = $agoFrom($lastRequestTimes === [] ? 0 : max($lastRequestTimes));
        $changeAgoLabel = $agoFrom($lastPriceChangeTimes === [] ? 0 : max($lastPriceChangeTimes));

        $changeLabels = [
            'persian.Gold750' => 'طلای ۷۵۰ — PersianAPI',
            'tala.Gold750' => 'طلای ۷۵۰ — Tala.ir',
            'persian.Silver999' => 'نقره ۹۹۹ — PersianAPI',
        ];
        $changeDots = [
            'persian.Gold750' => 'bg-emerald-500',
            'tala.Gold750' => 'bg-amber-500',
            'persian.Silver999' => 'bg-slate-400',
        ];

        $priceGroups = [
            'Gold750' => ['label' => 'طلای ۷۵۰', 'dot' => 'bg-emerald-500'],
            'Gold995' => ['label' => 'طلای ۹۹۵', 'dot' => 'bg-amber-500'],
            'Gold9999' => ['label' => 'طلای ۹۹۹.۹', 'dot' => 'bg-yellow-300'],
            'Silver9999' => ['label' => 'نقره ۹۹۹.۹', 'dot' => 'bg-slate-400'],
        ];

        $f = $formula;
        $c = $f['coefs'] ?? [];
        $g750 = $f['gold750'] ?? [];
        $g995 = $f['gold995'] ?? [];
        $g9999 = $f['gold9999'] ?? [];
        $g999 = $f['gold999'] ?? [];
        $s9999 = $f['silver9999'] ?? [];
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-gray-900">تابلوی قیمت زیوتو</h2>
            <p class="mt-1 text-sm text-gray-500">
                آخرین بروزرسانی نمایش: {{ $updatedAt ? $jalali(strtotime($updatedAt)) : ($lastSuccessAt ?: '—') }}
                <span class="mx-1 text-gray-300">•</span>
                مدت کش: {{ $cacheDuration }} ثانیه
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button wire:click="refreshBoard" color="gray" size="sm">
                بروزرسانی نمایش
            </x-filament::button>
            @if(auth()->user()?->can(\App\Enums\Permission::PricingEdit->value))
                <x-filament::button wire:click="forceRefresh" color="primary" size="sm" wire:loading.attr="disabled">
                    بروزرسانی از API
                </x-filament::button>
                <x-filament::button wire:click="clearCache" color="gray" size="sm" wire:loading.attr="disabled">
                    پاک کردن کش
                </x-filament::button>
            @endif
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- منابع قیمتی --}}
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">وضعیت منابع قیمتی</span>
                <x-filament::icon icon="heroicon-o-server" class="size-4 text-gray-400" />
            </div>
            <div class="space-y-1.5 border-t border-gray-100 mt-2 pt-2">
                @foreach($sourceStatus as $source => $status)
                    @php
                        [$label, $color, $dot] = $statusMap[$status['status'] ?? ''] ?? [($status['status'] ?? '—'), 'text-gray-500', 'bg-gray-400'];
                    @endphp
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="flex items-center gap-1.5 font-medium text-gray-700">
                            <span class="size-1.5 shrink-0 rounded-full {{ $dot }}"></span>
                            {{ $sourceLabels[$source] ?? $source }}
                        </span>
                        <span class="{{ $color }}">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- آخرین بروزرسانی موفق --}}
        {{-- <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">آخرین بروزرسانی موفق</span>
                <x-filament::icon icon="heroicon-o-clock" class="size-4 text-gray-400" />
            </div>
            @if($lastSuccessAt)
                <div class="mt-2 text-2xl font-bold tabular-nums text-gray-900">{{ $lastSuccessAt }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ $agoLabel }}</div>
                @if($usedFallback)
                    <div class="mt-2 rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-700">
                        از داده ذخیره‌شده (کش) استفاده شده است.
                    </div>
                @endif
            @else
                <div class="mt-2 text-2xl font-bold text-red-500">ثبت نشده</div>
                <div class="mt-1 text-xs text-gray-500">اتصال API را بررسی کنید.</div>
            @endif
        </div> --}}

        {{-- آخرین درخواست موفق API (ذخیره‌شده در دیتابیس؛ با انقضای کش از بین نمی‌رود) --}}
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">آخرین درخواست موفق API</span>
                <x-filament::icon icon="heroicon-o-signal" class="size-4 text-gray-400" />
            </div>

            @if($lastRequestAt)
                <div class="mt-2 text-2xl font-bold tabular-nums text-gray-900">{{ $lastRequestAt }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ $requestAgoLabel }}</div>

                <div class="mt-2 space-y-1 border-t border-gray-100 pt-2">
                    @foreach($lastRequestTimes as $source => $ts)
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="flex items-center gap-1.5 font-medium text-gray-700">
                                <span class="size-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                                {{ $sourceLabels[$source] ?? $source }}
                            </span>
                            <span class="tabular-nums text-gray-500">{{ $jalali($ts) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 rounded-md bg-slate-50 px-2 py-1 text-[11px] leading-4 text-slate-600">
                    فقط زمان دریافت پاسخ موفق از منبع؛ فرقی نمی‌کند قیمت تغییر کرده باشد یا نه.
                </div>
            @else
                <div class="mt-2 text-2xl font-bold text-red-500">ثبت نشده</div>
                <div class="mt-1 text-xs text-gray-500">هنوز درخواست موفقی ثبت نشده است.</div>
            @endif
        </div>

        {{-- آخرین تغییر قیمت پایه --}}
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">آخرین تغییر قیمت پایه</span>
                <x-filament::icon icon="heroicon-o-arrow-trending-up" class="size-4 text-gray-400" />
            </div>

            @if($lastPriceChangeAt)
                <div class="mt-2 text-2xl font-bold tabular-nums text-gray-900">{{ $lastPriceChangeAt }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ $changeAgoLabel }}</div>
            @else
                <div class="mt-2 text-2xl font-bold text-red-500">ثبت نشده</div>
                <div class="mt-1 text-xs text-gray-500">هنوز تغییری ثبت نشده است.</div>
            @endif

            <div class="mt-2 space-y-1 border-t border-gray-100 pt-2">
                @foreach($lastPriceChangeTimes as $path => $ts)
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="flex items-center gap-1.5 font-medium text-gray-700">
                            <span class="size-1.5 shrink-0 rounded-full {{ $changeDots[$path] ?? 'bg-gray-400' }}"></span>
                            {{ $changeLabels[$path] ?? $path }}
                        </span>
                        <span class="tabular-nums {{ $ts > 0 ? 'text-gray-500' : 'text-gray-300' }}">{{ $ts > 0 ? $jalali($ts) : '—' }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 rounded-md bg-slate-50 px-2 py-1 text-[11px] leading-4 text-slate-600">
                لحظه‌ای که مقدار خام این قیمت نسبت به نمونه قبلی عوض شده است.
            </div>
        </div>

        {{-- طلای ۷۵۰ --}}
{{--        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">--}}
{{--            <div class="flex items-center justify-between">--}}
{{--                <span class="text-xs font-medium text-gray-500">طلای ۷۵۰ (۱۸ عیار)</span>--}}
{{--                <x-filament::icon icon="heroicon-o-banknotes" class="size-4 text-gray-400" />--}}
{{--            </div>--}}
{{--            <div class="mt-2 flex items-center justify-between gap-2">--}}
{{--                <span class="text-2xl font-bold tabular-nums text-gray-900">{{ $toToman($g750['sell'] ?? null) }}</span>--}}
{{--                {!! $trendBadge($board['Gold750_Sell']['trend'] ?? null) !!}--}}
{{--            </div>--}}
{{--            <div class="mt-1 text-xs text-gray-500">--}}
{{--                فروش (تومان) <span class="mx-1 text-gray-300">•</span>--}}
{{--                خرید: <span class="font-medium text-gray-700">{{ $toToman($g750['buy'] ?? null) }}</span>--}}
{{--            </div>--}}
{{--        </div>--}}

        {{-- نقره ۹۹۹.۹ --}}
{{--        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">--}}
{{--            <div class="flex items-center justify-between">--}}
{{--                <span class="text-xs font-medium text-gray-500">نقره ۹۹۹.۹</span>--}}
{{--                <x-filament::icon icon="heroicon-o-banknotes" class="size-4 text-gray-400" />--}}
{{--            </div>--}}
{{--            <div class="mt-2 flex items-center justify-between gap-2">--}}
{{--                <span class="text-2xl font-bold tabular-nums text-gray-900">{{ $toToman($s9999['sell'] ?? null) }}</span>--}}
{{--                {!! $trendBadge($board['Silver9999_Sell']['trend'] ?? null) !!}--}}
{{--            </div>--}}
{{--            <div class="mt-1 text-xs text-gray-500">--}}
{{--                فروش (تومان) <span class="mx-1 text-gray-300">•</span>--}}
{{--                خرید: <span class="font-medium text-gray-700">{{ $toToman($s9999['buy'] ?? null) }}</span>--}}
{{--            </div>--}}
{{--        </div>--}}

    </div>

    <div class="mt-6 space-y-6">
        {{-- Current prices --}}
        <section class="overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                <h3 class="font-semibold text-gray-900">قیمت‌های فعلی تابلو</h3>
                <span class="text-xs text-gray-500">واحد: تومان</span>
            </header>

            @if(!empty($board))
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50">
                                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">نوع فلز</th>
                                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">فروش</th>
                                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">خرید</th>
                                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">آخرین بروزرسانی</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($priceGroups as $base => $meta)
                                @php
                                    $sellRow = $board[$base.'_Sell'] ?? null;
                                    $buyRow = $board[$base.'_Buy'] ?? null;
                                @endphp
                                <tr class="hover:bg-gray-50/80">
                                    <td class="px-5 py-3">
                                        <span class="flex items-center gap-2 font-medium text-gray-900">
                                            <span class="size-2.5 shrink-0 rounded-full"></span>
                                            {{ $meta['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 font-semibold tabular-nums text-gray-900">{{ $toToman($sellRow['value'] ?? null) }}</td>
                                    <td class="px-5 py-3 tabular-nums text-gray-600">{{ $toToman($buyRow['value'] ?? null) }}</td>
                                    <td class="px-5 py-3 text-xs text-gray-500">{{ $jalali($sellRow['updated_at'] ?? ($buyRow['updated_at'] ?? null)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-8 text-center text-sm text-gray-500">
                    اطلاعات قیمتی موجود نیست. لطفاً اتصال API را بررسی کنید.
                </div>
            @endif
        </section>

        {{-- Base prices --}}
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-950/5">
            <h3 class="font-semibold text-gray-900">قیمت‌های پایه</h3>
            <p class="mt-1 text-sm text-gray-500">این قیمت‌ها مستقیماً از API ها دریافت می‌شوند و پایه محاسبات هستند:</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-gray-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500">طلای ۷۵۰</span>
                        <span class="rounded bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">PersianAPI</span>
                    </div>
                    <div class="mt-2 text-lg font-bold tabular-nums text-gray-900">{{ $toToman($basePrices['persian']['Gold750'] ?? null) }}</div>
                    <div class="mt-0.5 text-xs text-gray-500">تومان</div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500">طلای ۷۵۰</span>
                        <span class="rounded bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">Tala.ir</span>
                    </div>
                    <div class="mt-2 text-lg font-bold tabular-nums text-gray-900">{{ $toToman($basePrices['tala']['Gold750'] ?? null) }}</div>
                    <div class="mt-0.5 text-xs text-gray-500">تومان</div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500">نقره ۹۹۹</span>
                        <span class="rounded bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">PersianAPI</span>
                    </div>
                    <div class="mt-2 text-lg font-bold tabular-nums text-gray-900">{{ $toToman($basePrices['persian']['Silver999'] ?? null) }}</div>
                    <div class="mt-0.5 text-xs text-gray-500">تومان</div>
                </div>
            </div>

            <p class="mt-3 text-xs text-gray-500">این سه قیمت پایه محاسبات تابلو قیمت هستند.</p>
        </section>

        {{-- Formulas --}}
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-950/5">
            <h3 class="font-semibold text-gray-900">نحوه محاسبه قیمت‌ها</h3>
            <p class="mt-1 text-sm text-gray-500">سیستم قیمت‌گذاری با استفاده از منابع مختلف و ضرایب قابل تنظیم، قیمت‌های خرید و فروش را محاسبه می‌کند. روی هر ردیف کلیک کنید تا جزئیات باز شود.</p>

            <div class="mt-4 space-y-3">
                {{-- 1. طلای ۷۵۰ --}}
                <details class="group overflow-hidden rounded-lg ring-1 ring-gray-950/5" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 [&::-webkit-details-marker]:hidden hover:bg-gray-50">
                        <span class="flex items-center gap-2.5">
                            <span class="size-2.5 shrink-0 rounded-full bg-[#0073aa]"></span>
                            <span class="font-semibold text-gray-900">۱. طلای ۷۵۰ (۱۸ عیار)</span>
                        </span>
                        <span class="flex items-center gap-4 text-sm tabular-nums">
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $toToman($g750['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $toToman($g750['buy'] ?? null) }}</strong></span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 text-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">ورودی‌ها</div>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">PersianAPI — طلای ۷۵۰</dt>
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $toToman($g750['persian'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">Tala.ir — طلای ۷۵۰</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $toToman($g750['talaDirect'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت دستی</dt>
                                        <dd class="font-mono tabular-nums text-red-600">{{ $toToman($g750['manual'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-2">
                                        <dt class="text-gray-600">Max(Persian, Tala)</dt>
                                        <dd class="font-mono tabular-nums">{{ $toToman($g750['maxApi'] ?? null) }} <span class="text-xs text-gray-400">({{ $g750['maxSource'] ?? '—' }})</span></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">Min(Persian, Tala)</dt>
                                        <dd class="font-mono tabular-nums">{{ $toToman($g750['minApi'] ?? null) }} <span class="text-xs text-gray-400">({{ $g750['minSource'] ?? '—' }})</span></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-2">
                                        <dt class="text-gray-600">HighValueRatio (ضریب فروش)</dt>
                                        <dd class="font-mono tabular-nums text-purple-600">{{ number_format((float) ($c['gold750_sell_ratio'] ?? 1.001), 4) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">LowValueRatio (ضریب خرید)</dt>
                                        <dd class="font-mono tabular-nums text-purple-600">{{ number_format((float) ($c['gold750_buy_ratio'] ?? 0.997), 4) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">فروش = Max(Persian, Tala) × HighValueRatio ({{ number_format((float) ($c['gold750_sell_ratio'] ?? 1.001), 4) }})</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">خرید = Min(Persian, Tala) × LowValueRatio ({{ number_format((float) ($c['gold750_buy_ratio'] ?? 0.997), 4) }})</code>
                                <p class="mt-2.5 text-xs leading-6 text-gray-500">
                                    قیمت فروش با ضرب ماکزیمم API در HighValueRatio و قیمت خرید با ضرب مینیمم API در LowValueRatio محاسبه می‌شود.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border-s-4 border-blue-600 bg-blue-50 p-3">
                                <div class="text-xs font-medium text-blue-700">قیمت نهایی فروش</div>
                                <div class="mt-1 text-lg font-bold tabular-nums text-blue-900">{{ $toman($g750['sell'] ?? null) }}</div>
                                <div class="mt-1 text-xs text-blue-700/80">{{ $g750['sellLogic'] ?? '—' }}</div>
                            </div>
                            <div class="rounded-lg border-s-4 border-emerald-600 bg-emerald-50 p-3">
                                <div class="text-xs font-medium text-emerald-700">قیمت نهایی خرید</div>
                                <div class="mt-1 text-lg font-bold tabular-nums text-emerald-900">{{ $toman($g750['buy'] ?? null) }}</div>
                                <div class="mt-1 text-xs text-emerald-700/80">{{ $g750['buyLogic'] ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </details>

                {{-- 2. طلای ۹۹۵ --}}
                <details class="group overflow-hidden rounded-lg ring-1 ring-gray-950/5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 [&::-webkit-details-marker]:hidden hover:bg-gray-50">
                        <span class="flex items-center gap-2.5">
                            <span class="size-2.5 shrink-0 rounded-full bg-emerald-600"></span>
                            <span class="font-semibold text-gray-900">۲. طلای ۹۹۵ (۲۴ عیار)</span>
                        </span>
                        <span class="flex items-center gap-4 text-sm tabular-nums">
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $toToman($g995['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $toToman($g995['buy'] ?? null) }}</strong></span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 text-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">ورودی‌ها</div>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت فروش طلای ۹۹۹</dt>
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $toToman($g999['sell'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت خرید طلای ۹۹۹</dt>
                                        <dd class="font-mono tabular-nums text-emerald-700">{{ $toToman($g999['buy'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">ضریب تبدیل ۹۹۹ به ۹۹۵</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $c['gold999_to_gold995'] ?? '—' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">HighValueRatio (ضریب فروش)</dt>
                                        <dd class="font-mono tabular-nums text-purple-600">{{ number_format((float) ($c['gold995_sell_ratio'] ?? 1), 4) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">LowValueRatio (ضریب خرید)</dt>
                                        <dd class="font-mono tabular-nums text-purple-600">{{ number_format((float) ($c['gold995_buy_ratio'] ?? 0.995), 4) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">طلای۹۹۵ فروش = طلای۹۹۹ فروش × {{ $c['gold999_to_gold995'] ?? '—' }} × HighValueRatio</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">طلای۹۹۵ خرید = طلای۹۹۹ خرید × {{ $c['gold999_to_gold995'] ?? '—' }} × LowValueRatio</code>
                                <div class="mt-3 space-y-1.5">
                                    <div class="rounded-lg border-s-4 border-blue-600 bg-blue-50 px-3 py-2">
                                        <span class="text-xs text-blue-700">فروش: </span>
                                        <span class="text-sm font-bold tabular-nums text-blue-900">{{ $toman($g995['sell'] ?? null) }}</span>
                                    </div>
                                    <div class="rounded-lg border-s-4 border-emerald-600 bg-emerald-50 px-3 py-2">
                                        <span class="text-xs text-emerald-700">خرید: </span>
                                        <span class="text-sm font-bold tabular-nums text-emerald-900">{{ $toman($g995['buy'] ?? null) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>

                {{-- 3. طلای ۹۹۹.۹ --}}
                <details class="group overflow-hidden rounded-lg ring-1 ring-gray-950/5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 [&::-webkit-details-marker]:hidden hover:bg-gray-50">
                        <span class="flex items-center gap-2.5">
                            <span class="size-2.5 shrink-0 rounded-full bg-orange-500"></span>
                            <span class="font-semibold text-gray-900">۳. طلای ۹۹۹.۹ (۲۴ عیار)</span>
                        </span>
                        <span class="flex items-center gap-4 text-sm tabular-nums">
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $toToman($g9999['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $toToman($g9999['buy'] ?? null) }}</strong></span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 text-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">ورودی‌ها</div>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت فروش طلای ۹۹۹</dt>
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $toToman($g999['sell'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت خرید طلای ۹۹۹</dt>
                                        <dd class="font-mono tabular-nums text-emerald-700">{{ $toToman($g999['buy'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">ضریب تبدیل ۹۹۹ به ۹۹۹.۹</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $c['gold999_to_gold9999'] ?? '—' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">HighValueRatio (ضریب فروش)</dt>
                                        <dd class="font-mono tabular-nums text-purple-600">{{ number_format((float) ($c['gold9999_sell_ratio'] ?? 1), 4) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">LowValueRatio (ضریب خرید)</dt>
                                        <dd class="font-mono tabular-nums text-purple-600">{{ number_format((float) ($c['gold9999_buy_ratio'] ?? 0.995), 4) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">طلای۹۹۹.۹ فروش = طلای۹۹۹ فروش × {{ $c['gold999_to_gold9999'] ?? '—' }} × HighValueRatio</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">طلای۹۹۹.۹ خرید = طلای۹۹۹ خرید × {{ $c['gold999_to_gold9999'] ?? '—' }} × LowValueRatio</code>
                                <div class="mt-3 space-y-1.5">
                                    <div class="rounded-lg border-s-4 border-blue-600 bg-blue-50 px-3 py-2">
                                        <span class="text-xs text-blue-700">فروش: </span>
                                        <span class="text-sm font-bold tabular-nums text-blue-900">{{ $toman($g9999['sell'] ?? null) }}</span>
                                    </div>
                                    <div class="rounded-lg border-s-4 border-emerald-600 bg-emerald-50 px-3 py-2">
                                        <span class="text-xs text-emerald-700">خرید: </span>
                                        <span class="text-sm font-bold tabular-nums text-emerald-900">{{ $toman($g9999['buy'] ?? null) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>

                {{-- 4. طلای ۹۹۹ (۲۴ عیار، مستقیم از API) --}}
                <details class="group overflow-hidden rounded-lg ring-1 ring-gray-950/5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 [&::-webkit-details-marker]:hidden hover:bg-gray-50">
                        <span class="flex items-center gap-2.5">
                            <span class="size-2.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span class="font-semibold text-gray-900">۴. طلای ۹۹۹ (۲۴ عیار)</span>
                        </span>
                        <span class="flex items-center gap-4 text-sm tabular-nums">
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $toToman($g999['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $toToman($g999['buy'] ?? null) }}</strong></span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 text-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">ورودی‌ها</div>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">PersianAPI — طلای ۲۴ عیار</dt>
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $toToman($g999['persian'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">Tala.ir — گرم ۲۴ عیار</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $toToman($g999['tala'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت دستی</dt>
                                        <dd class="font-mono tabular-nums text-red-600">{{ $toToman($g999['manual'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-2">
                                        <dt class="text-gray-600">Max(Persian, Tala)</dt>
                                        <dd class="font-mono tabular-nums">{{ $toToman($g999['maxApi'] ?? null) }} <span class="text-xs text-gray-400">({{ $g999['maxSource'] ?? '—' }})</span></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">Min(Persian, Tala)</dt>
                                        <dd class="font-mono tabular-nums">{{ $toToman($g999['minApi'] ?? null) }} <span class="text-xs text-gray-400">({{ $g999['minSource'] ?? '—' }})</span></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-2">
                                        <dt class="text-gray-600">HighValueRatio (ضریب فروش)</dt>
                                        <dd class="font-mono tabular-nums text-purple-600">{{ number_format((float) ($c['gold999_sell_ratio'] ?? 1.001), 4) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">LowValueRatio (ضریب خرید)</dt>
                                        <dd class="font-mono tabular-nums text-purple-600">{{ number_format((float) ($c['gold999_buy_ratio'] ?? 0.997), 4) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">فروش = Max(Persian, Tala) × HighValueRatio ({{ number_format((float) ($c['gold999_sell_ratio'] ?? 1.001), 4) }})</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">خرید = Min(Persian, Tala) × LowValueRatio ({{ number_format((float) ($c['gold999_buy_ratio'] ?? 0.997), 4) }})</code>
                                <p class="mt-2.5 text-xs leading-6 text-gray-500">
                                    قیمت فروش با ضرب ماکزیمم API در HighValueRatio و قیمت خرید با ضرب مینیمم API در LowValueRatio محاسبه می‌شود.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border-s-4 border-blue-600 bg-blue-50 p-3">
                                <div class="text-xs font-medium text-blue-700">قیمت نهایی فروش</div>
                                <div class="mt-1 text-lg font-bold tabular-nums text-blue-900">{{ $toman($g999['sell'] ?? null) }}</div>
                                <div class="mt-1 text-xs text-blue-700/80">{{ $g999['sellLogic'] ?? '—' }}</div>
                            </div>
                            <div class="rounded-lg border-s-4 border-emerald-600 bg-emerald-50 p-3">
                                <div class="text-xs font-medium text-emerald-700">قیمت نهایی خرید</div>
                                <div class="mt-1 text-lg font-bold tabular-nums text-emerald-900">{{ $toman($g999['buy'] ?? null) }}</div>
                                <div class="mt-1 text-xs text-emerald-700/80">{{ $g999['buyLogic'] ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </details>

                {{-- 5. نقره ۹۹۹.۹ --}}
                <details class="group overflow-hidden rounded-lg ring-1 ring-gray-950/5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 [&::-webkit-details-marker]:hidden hover:bg-gray-50">
                        <span class="flex items-center gap-2.5">
                            <span class="size-2.5 shrink-0 rounded-full bg-purple-600"></span>
                            <span class="font-semibold text-gray-900">۴. نقره ۹۹۹.۹</span>
                        </span>
                        <span class="flex items-center gap-4 text-sm tabular-nums">
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $toToman($s9999['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $toToman($s9999['buy'] ?? null) }}</strong></span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 text-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">ورودی‌ها</div>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">PersianAPI — نقره ۹۹۹ (مرجع، در قیمت استفاده نمی‌شود)</dt>
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $toToman($s9999['persian999'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">ضریب تبدیل ۹۹۹.۹ به ۹۹۹</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $c['silver9999_to_silver999'] ?? '—' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">نقره ۹۹۹ محاسبه‌شده</dt>
                                        <dd class="font-mono tabular-nums">{{ $toToman($s9999['silver999_sell'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">ضریب خرید نقره</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $c['silver_buy'] ?? '—' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت دستی (تنها منبع)</dt>
                                        <dd class="font-mono tabular-nums text-red-600">{{ $toToman($s9999['manual'] ?? null) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">نقره۹۹۹.۹ فروش = قیمت دستی</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">نقره۹۹۹ فروش = نقره۹۹۹.۹ فروش × {{ $c['silver9999_to_silver999'] ?? '—' }}</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">نقره خرید = فروش × {{ $c['silver_buy'] ?? '—' }}</code>
                                <div class="mt-3 space-y-1.5">
                                    <div class="rounded-lg border-s-4 border-purple-600 bg-purple-50 px-3 py-2">
                                        <div class="text-xs text-purple-700">قیمت نهایی فروش</div>
                                        <div class="mt-0.5 text-sm font-bold tabular-nums text-purple-900">{{ $toman($s9999['sell'] ?? null) }}</div>
                                        <div class="mt-0.5 text-xs text-purple-700/80">{{ $s9999['sellLogic'] ?? '—' }}</div>
                                    </div>
                                    <div class="rounded-lg border-s-4 border-emerald-600 bg-emerald-50 px-3 py-2">
                                        <div class="text-xs text-emerald-700">قیمت نهایی خرید (ضریب خرید نقره)</div>
                                        <div class="mt-0.5 text-sm font-bold tabular-nums text-emerald-900">{{ $toman($s9999['buy'] ?? null) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>

            </div>

            <p class="mt-4 rounded-lg border-s-4 border-amber-500 bg-amber-50 p-3 text-sm text-amber-800">
                <strong>نکته:</strong>
                تمام ضرایب از صفحه «ضرایب تابلو» قابل تغییر هستند. هر تغییری در ضرایب بلافاصله در محاسبات قیمت اعمال می‌شود.
            </p>
        </section>
    </div>
</x-filament-panels::page>
