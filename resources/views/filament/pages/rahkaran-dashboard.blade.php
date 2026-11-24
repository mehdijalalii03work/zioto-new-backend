<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 xl:col-span-2">
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                    <div class="flex items-start gap-4">
                        <div @class([
                            'rounded-xl p-3',
                            'bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400' => ($connectionCheck['success'] ?? false),
                            'bg-warning-50 text-warning-600 dark:bg-warning-400/10 dark:text-warning-400' => ! isset($connectionCheck),
                            'bg-danger-50 text-danger-600 dark:bg-danger-400/10 dark:text-danger-400' => isset($connectionCheck) && ! $connectionCheck['success'],
                        ])>
                            @if(($connectionCheck['success'] ?? false))
                                <x-heroicon-o-check-badge class="h-6 w-6" />
                            @else
                                <x-heroicon-o-signal class="h-6 w-6" />
                            @endif
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-base font-semibold text-gray-950 dark:text-white">اتصال راهکاران</h2>
                            @if(! $configured)
                                <p class="text-sm text-danger-600 dark:text-danger-400">تنظیمات اتصال کامل نیست.</p>
                            @elseif(! isset($connectionCheck))
                                <p class="text-sm text-gray-600 dark:text-gray-300">هنوز اتصال آزمایش نشده است.</p>
                            @else
                                <p @class([
                                    'text-sm',
                                    'text-success-600 dark:text-success-400' => $connectionCheck['success'],
                                    'text-danger-600 dark:text-danger-400' => ! $connectionCheck['success'],
                                ])>{{ $connectionCheck['message'] }}</p>
                            @endif
                            <div class="flex flex-wrap gap-x-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ $baseUrlHost ?? 'نشانی راهکاران تنظیم نشده' }}</span>
                                @if($connectionCheck['checked_at'] ?? null)
                                    <span>آخرین بررسی: {{ \Illuminate\Support\Carbon::parse($connectionCheck['checked_at'])->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                        <p class="text-xs text-gray-500 dark:text-gray-400">منبع موجودی سایت</p>
                        <p class="mt-1 font-semibold {{ $inventorySource === 'rahkaran' ? 'text-primary-700 dark:text-primary-300' : 'text-gray-700 dark:text-gray-200' }}">
                            {{ match ($inventorySource) { 'rahkaran' => 'راهکاران', 'offline' => 'آفلاین', default => 'حسابفا' } }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">آخرین همگام‌سازی موجودی</p>
                        @if($latestStockSync)
                            <p class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">
                                {{ $latestStockSync->status === 'success' ? 'موفق' : 'نیازمند بررسی' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $latestStockSync->created_at->diffForHumans() }}</p>
                        @else
                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">هنوز انجام نشده</p>
                        @endif
                    </div>
                    <x-heroicon-o-arrow-path class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['title' => 'کالاهای منتشرشده', 'value' => $productStats['total'], 'icon' => 'heroicon-o-cube', 'icon_class' => 'text-gray-500 dark:text-gray-400'],
                ['title' => 'دارای نگاشت', 'value' => $productStats['mapped'], 'icon' => 'heroicon-o-link', 'icon_class' => 'text-success-600 dark:text-success-400'],
                ['title' => 'بدون نگاشت', 'value' => $productStats['unmapped'], 'icon' => 'heroicon-o-exclamation-triangle', 'icon_class' => 'text-warning-600 dark:text-warning-400'],
                ['title' => 'سریال آزاد', 'value' => $serialStats['available'], 'icon' => 'heroicon-o-qr-code', 'icon_class' => 'text-primary-600 dark:text-primary-400'],
                ['title' => 'رزرو سریال', 'value' => $serialStats['reserved'], 'icon' => 'heroicon-o-bookmark', 'icon_class' => 'text-info-600 dark:text-info-400'],
            ] as $stat)
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['title'] }}</p>
                        <x-dynamic-component :component="$stat['icon']" class="h-5 w-5 {{ $stat['icon_class'] }}" />
                    </div>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($stat['value']) }}</p>
                </div>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-gray-950 dark:text-white">سیاست‌های فعال</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">مرجع و رفتار همگام‌سازی فعلی</p>
                    </div>
                    <x-heroicon-o-adjustments-horizontal class="h-6 w-6 text-gray-400" />
                </div>
                <dl class="mt-5 divide-y divide-gray-100 dark:divide-white/10">
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-gray-600 dark:text-gray-300">ارسال فاکتور به راهکاران</dt>
                        <dd @class([
                            'rounded-full px-2.5 py-1 text-xs font-medium',
                            'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $invoiceEnabled,
                            'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $invoiceEnabled,
                        ])>{{ $invoiceEnabled ? 'روشن' : 'خاموش' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-gray-600 dark:text-gray-300">تخصیص سریال پس از پرداخت</dt>
                        <dd @class([
                            'rounded-full px-2.5 py-1 text-xs font-medium',
                            'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $serialTrackingEnabled,
                            'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $serialTrackingEnabled,
                        ])>{{ $serialTrackingEnabled ? 'روشن' : 'خاموش' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-gray-600 dark:text-gray-300">کالاهای سریالی / غیرسریالی</dt>
                        <dd class="text-sm font-medium tabular-nums text-gray-950 dark:text-white">
                            {{ number_format($productStats['serialTracked']) }} / {{ number_format($productStats['withoutSerial']) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-gray-600 dark:text-gray-300">سفارش‌های پرداخت‌شده در انتظار ارسال</dt>
                        <dd class="text-sm font-semibold tabular-nums text-warning-700 dark:text-warning-400">{{ number_format($orderStats['pending']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-gray-600 dark:text-gray-300">فاکتورهای ناموفق</dt>
                        <dd class="text-sm font-semibold tabular-nums {{ $orderStats['failed'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-700 dark:text-gray-200' }}">{{ number_format($orderStats['failed']) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-gray-950 dark:text-white">نگاشت کالاها</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">نگاشت SKU سایت به شناسه کالای راهکاران</p>
                    </div>
                    <span class="text-sm tabular-nums text-gray-600 dark:text-gray-300">{{ number_format($productStats['mapped']) }} از {{ number_format($productStats['total']) }}</span>
                </div>
                @if($productsWithoutMapping->isNotEmpty())
                    <div class="mt-4 overflow-hidden rounded-lg border border-warning-200 dark:border-warning-400/20">
                        <div class="bg-warning-50 px-4 py-2 text-xs font-medium text-warning-800 dark:bg-warning-400/10 dark:text-warning-300">
                            چند کالای منتشرشده نگاشت نشده‌اند
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach($productsWithoutMapping as $product)
                                <li class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                                    <span class="truncate text-gray-700 dark:text-gray-200">{{ $product->name }}</span>
                                    <code class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $product->sku }}</code>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="mt-5 flex items-center gap-2 rounded-lg bg-success-50 px-4 py-3 text-sm text-success-700 dark:bg-success-400/10 dark:text-success-400">
                        <x-heroicon-o-check-circle class="h-5 w-5" />
                        همهٔ کالاهای منتشرشده نگاشت دارند.
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-gray-950 dark:text-white">رخدادهای اخیر راهکاران</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">فاکتورها و همگام‌سازی موجودی</p>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $recentLogs->count() }} رخداد اخیر</span>
            </div>

            @if($recentLogs->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[42rem] text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-start text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="px-3 py-3 text-start font-medium">رخداد</th>
                                <th class="px-3 py-3 text-start font-medium">سفارش</th>
                                <th class="px-3 py-3 text-start font-medium">وضعیت</th>
                                <th class="px-3 py-3 text-start font-medium">جزئیات</th>
                                <th class="px-3 py-3 text-start font-medium">زمان</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach($recentLogs as $log)
                                @php
                                    $isStockSync = $log->sync_type === 'rahkaran_stock_sync';
                                    $updatedProducts = $log->response_data['updated_product_count'] ?? null;
                                    $serialsRead = $log->response_data['synced_serial_count'] ?? null;
                                    $errors = $log->response_data['errors'] ?? null;
                                    $partial = $isStockSync && $log->status === 'failed' && $updatedProducts > 0;
                                @endphp
                                <tr>
                                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">
                                        {{ $log->sync_type === 'rahkaran_invoice' ? 'فاکتور فروش' : 'همگام‌سازی موجودی' }}
                                    </td>
                                    <td class="px-3 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">
                                        @if($log->order && auth()->user()?->can(\App\Enums\Permission::OrderView->value))
                                            <a class="text-primary-600 hover:underline dark:text-primary-400" href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $log->order]) }}">
                                                {{ $log->order->order_number }}
                                            </a>
                                        @else
                                            {{ $log->order?->order_number ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <span @class([
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $log->status === 'success',
                                            'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' => $log->status === 'failed' && ! $partial,
                                            'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' => $partial,
                                            'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! in_array($log->status, ['success', 'failed'], true),
                                        ])>{{ $partial ? 'موفقیت جزئی' : ($log->status === 'success' ? 'موفق' : ($log->status === 'failed' ? 'ناموفق' : $log->status)) }}</span>
                                    </td>
                                    <td class="max-w-sm px-3 py-3 text-gray-500 dark:text-gray-400">
                                        @if($isStockSync && $updatedProducts !== null)
                                            <div class="font-medium text-gray-700 dark:text-gray-200">
                                                {{ $updatedProducts }} کالا به‌روز شد · {{ is_array($errors) ? count($errors) : '—' }} کالای خطادار · {{ $serialsRead ?? '—' }} سریال خوانده شد
                                            </div>
                                            @if($log->error_message || $errors)
                                                <details class="mt-2 text-xs">
                                                    <summary class="cursor-pointer">جزئیات خطا</summary>
                                                    @if($errors)
                                                        <ul class="mt-2 space-y-1">
                                                            @foreach($errors as $sku => $error)
                                                                <li><bdi>{{ $sku }}</bdi> · {{ $error }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <p class="mt-2">{{ $log->error_message }}</p>
                                                    @endif
                                                </details>
                                            @endif
                                        @else
                                            {{ $log->error_message ?: ($log->response_data['message'] ?? '—') }}
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $log->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="mt-5 rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-white/15 dark:text-gray-400">
                    هنوز رخدادی برای راهکاران ثبت نشده است.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
