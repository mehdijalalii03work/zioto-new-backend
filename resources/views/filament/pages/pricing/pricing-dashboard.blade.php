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
        $agoLabel = '—';
        if ($latestTs > 0) {
            $diff = max(0, time() - $latestTs);
            $agoLabel = match (true) {
                $diff < 60 => 'همین حالا',
                $diff < 3600 => floor($diff / 60).' دقیقه پیش',
                $diff < 86400 => floor($diff / 3600).' ساعت پیش',
                default => floor($diff / 86400).' روز پیش',
            };
        }

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
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">
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
        </div>

        {{-- طلای ۷۵۰ --}}
{{--        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">--}}
{{--            <div class="flex items-center justify-between">--}}
{{--                <span class="text-xs font-medium text-gray-500">طلای ۷۵۰ (۱۸ عیار)</span>--}}
{{--                <x-filament::icon icon="heroicon-o-banknotes" class="size-4 text-gray-400" />--}}
{{--            </div>--}}
{{--            <div class="mt-2 flex items-center justify-between gap-2">--}}
{{--                <span class="text-2xl font-bold tabular-nums text-gray-900">{{ $fmt($g750['sell'] ?? null) }}</span>--}}
{{--                {!! $trendBadge($board['Gold750_Sell']['trend'] ?? null) !!}--}}
{{--            </div>--}}
{{--            <div class="mt-1 text-xs text-gray-500">--}}
{{--                فروش (تومان) <span class="mx-1 text-gray-300">•</span>--}}
{{--                خرید: <span class="font-medium text-gray-700">{{ $fmt($g750['buy'] ?? null) }}</span>--}}
{{--            </div>--}}
{{--        </div>--}}

        {{-- نقره ۹۹۹.۹ --}}
{{--        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5">--}}
{{--            <div class="flex items-center justify-between">--}}
{{--                <span class="text-xs font-medium text-gray-500">نقره ۹۹۹.۹</span>--}}
{{--                <x-filament::icon icon="heroicon-o-banknotes" class="size-4 text-gray-400" />--}}
{{--            </div>--}}
{{--            <div class="mt-2 flex items-center justify-between gap-2">--}}
{{--                <span class="text-2xl font-bold tabular-nums text-gray-900">{{ $fmt($s9999['sell'] ?? null) }}</span>--}}
{{--                {!! $trendBadge($board['Silver9999_Sell']['trend'] ?? null) !!}--}}
{{--            </div>--}}
{{--            <div class="mt-1 text-xs text-gray-500">--}}
{{--                فروش (تومان) <span class="mx-1 text-gray-300">•</span>--}}
{{--                خرید: <span class="font-medium text-gray-700">{{ $fmt($s9999['buy'] ?? null) }}</span>--}}
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
                                    <td class="px-5 py-3 font-semibold tabular-nums text-gray-900">{{ $fmt($sellRow['value'] ?? null) }}</td>
                                    <td class="px-5 py-3 tabular-nums text-gray-600">{{ $fmt($buyRow['value'] ?? null) }}</td>
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
                    <div class="mt-2 text-lg font-bold tabular-nums text-gray-900">{{ $fmt($basePrices['persian']['Gold750'] ?? null) }}</div>
                    <div class="mt-0.5 text-xs text-gray-500">تومان</div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500">طلای ۷۵۰</span>
                        <span class="rounded bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">Tala.ir</span>
                    </div>
                    <div class="mt-2 text-lg font-bold tabular-nums text-gray-900">{{ $fmt($basePrices['tala']['Gold750'] ?? null) }}</div>
                    <div class="mt-0.5 text-xs text-gray-500">تومان</div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-500">نقره ۹۹۹</span>
                        <span class="rounded bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">PersianAPI</span>
                    </div>
                    <div class="mt-2 text-lg font-bold tabular-nums text-gray-900">{{ $fmt($basePrices['persian']['Silver999'] ?? null) }}</div>
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
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $fmt($g750['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $fmt($g750['buy'] ?? null) }}</strong></span>
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
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $fmt($g750['persian'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">Tala.ir — طلای ۷۵۰</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $fmt($g750['talaDirect'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت دستی</dt>
                                        <dd class="font-mono tabular-nums text-red-600">{{ $fmt($g750['manual'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-2">
                                        <dt class="text-gray-600">Max(Persian, Tala)</dt>
                                        <dd class="font-mono tabular-nums">{{ $fmt($g750['maxApi'] ?? null) }} <span class="text-xs text-gray-400">({{ $g750['maxSource'] ?? '—' }})</span></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">Min(Persian, Tala)</dt>
                                        <dd class="font-mono tabular-nums">{{ $fmt($g750['minApi'] ?? null) }} <span class="text-xs text-gray-400">({{ $g750['minSource'] ?? '—' }})</span></dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">فروش = بیشترین قیمت بین PersianAPI و Tala.ir و قیمت دستی</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">خرید = کمترین قیمت بین PersianAPI و Tala.ir یا فروش × {{ $c['buy'] ?? '—' }}</code>
                                <p class="mt-2.5 text-xs leading-6 text-gray-500">
                                    قیمت خرید فقط وقتی از قیمت فروش × {{ $c['buy'] ?? '—' }} محاسبه می‌شود که قیمت دستی از Max API بیشتر باشد یا دو API برابر باشند.
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
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $fmt($g995['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $fmt($g995['buy'] ?? null) }}</strong></span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 text-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">ورودی‌ها</div>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت فروش طلای ۷۵۰</dt>
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $fmt($g750['sell'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت خرید طلای ۷۵۰</dt>
                                        <dd class="font-mono tabular-nums text-emerald-700">{{ $fmt($g750['buy'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">ضریب تبدیل ۷۵۰ به ۹۹۵</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $c['gold750_to_gold995'] ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">طلای۹۹۵ فروش = طلای۷۵۰ فروش × {{ $c['gold750_to_gold995'] ?? '—' }}</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">طلای۹۹۵ خرید = طلای۷۵۰ خرید × {{ $c['gold750_to_gold995'] ?? '—' }}</code>
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
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $fmt($g9999['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $fmt($g9999['buy'] ?? null) }}</strong></span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 text-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">ورودی‌ها</div>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت فروش طلای ۷۵۰</dt>
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $fmt($g750['sell'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت خرید طلای ۷۵۰</dt>
                                        <dd class="font-mono tabular-nums text-emerald-700">{{ $fmt($g750['buy'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">ضریب تبدیل ۷۵۰ به ۹۹۹.۹</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $c['gold750_to_gold9999'] ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">طلای۹۹۹.۹ فروش = طلای۷۵۰ فروش × {{ $c['gold750_to_gold9999'] ?? '—' }}</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">طلای۹۹۹.۹ خرید = طلای۷۵۰ خرید × {{ $c['gold750_to_gold9999'] ?? '—' }}</code>
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

                {{-- 4. نقره ۹۹۹.۹ --}}
                <details class="group overflow-hidden rounded-lg ring-1 ring-gray-950/5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 [&::-webkit-details-marker]:hidden hover:bg-gray-50">
                        <span class="flex items-center gap-2.5">
                            <span class="size-2.5 shrink-0 rounded-full bg-purple-600"></span>
                            <span class="font-semibold text-gray-900">۴. نقره ۹۹۹.۹</span>
                        </span>
                        <span class="flex items-center gap-4 text-sm tabular-nums">
                            <span class="text-gray-500">فروش: <strong class="text-gray-900">{{ $fmt($s9999['sell'] ?? null) }}</strong></span>
                            <span class="text-gray-500">خرید: <strong class="text-gray-900">{{ $fmt($s9999['buy'] ?? null) }}</strong></span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-4 text-sm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">ورودی‌ها</div>
                                <dl class="space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">PersianAPI — نقره ۹۹۹</dt>
                                        <dd class="font-mono tabular-nums text-blue-700">{{ $fmt($s9999['persian999'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">ضریب تبدیل ۹۹۹ به ۹۹۹.۹</dt>
                                        <dd class="font-mono tabular-nums text-amber-600">{{ $c['silver999_to_silver9999'] ?? '—' }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">نقره ۹۹۹.۹ محاسبه‌شده</dt>
                                        <dd class="font-mono tabular-nums">{{ $fmt($s9999['converted'] ?? null) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-600">قیمت دستی</dt>
                                        <dd class="font-mono tabular-nums text-red-600">{{ $fmt($s9999['manual'] ?? null) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-lg bg-white p-4">
                                <div class="mb-2.5 text-xs font-semibold text-gray-500">فرمول</div>
                                <code dir="rtl" class="block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">نقره۹۹۹.۹ فروش = بیشترین قیمت بین «نقره۹۹۹ × {{ $c['silver999_to_silver9999'] ?? '—' }}» و قیمت دستی</code>
                                <code dir="rtl" class="mt-1.5 block rounded bg-gray-50 p-2.5 text-xs leading-6 text-pink-700">نقره۹۹۹.۹ خرید = نقره۹۹۹.۹ فروش × {{ $c['buy'] ?? '—' }}</code>
                                <div class="mt-3 space-y-1.5">
                                    <div class="rounded-lg border-s-4 border-purple-600 bg-purple-50 px-3 py-2">
                                        <div class="text-xs text-purple-700">قیمت نهایی فروش</div>
                                        <div class="mt-0.5 text-sm font-bold tabular-nums text-purple-900">{{ $toman($s9999['sell'] ?? null) }}</div>
                                        <div class="mt-0.5 text-xs text-purple-700/80">{{ $s9999['sellLogic'] ?? '—' }}</div>
                                    </div>
                                    <div class="rounded-lg border-s-4 border-emerald-600 bg-emerald-50 px-3 py-2">
                                        <div class="text-xs text-emerald-700">قیمت نهایی خرید (۹۹٪ فروش)</div>
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
                تمام ضرایب از تب «ضرایب محاسبه قیمت» قابل تغییر هستند. هر تغییری در ضرایب بلافاصله در محاسبات قیمت اعمال می‌شود.
            </p>
        </section>
    </div>
</x-filament-panels::page>
