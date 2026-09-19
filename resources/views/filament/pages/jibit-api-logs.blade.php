<x-filament-panels::page>
    <div class="fi-card fi-w-full rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-card-body p-6">
            {{-- Filters --}}
            <div class="mb-4 flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">اندپوینت:</span>
                    @php
                        $activeClass = 'inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-primary-600 hover:bg-primary-500 transition';
                        $inactiveClass = 'inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700 transition';
                    @endphp
                    <button wire:click="setFilterEndpoint(null)" class="{{ !$this->filterEndpoint ? $activeClass : $inactiveClass }}">
                        همه
                    </button>
                    <button wire:click="setFilterEndpoint('/v1/tokens/generate')" class="{{ $this->filterEndpoint === '/v1/tokens/generate' ? $activeClass : $inactiveClass }}">
                        تولید توکن
                    </button>
                    <button wire:click="setFilterEndpoint('/v1/services/matching')" class="{{ $this->filterEndpoint === '/v1/services/matching' ? $activeClass : $inactiveClass }}">
                        احراز هویت
                    </button>
                    <button wire:click="setFilterEndpoint('/v1/services/identity')" class="{{ $this->filterEndpoint === '/v1/services/identity' ? $activeClass : $inactiveClass }}">
                        اطلاعات هویتی
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">وضعیت:</span>
                    <button wire:click="setFilterStatus(null)" class="{{ $this->filterStatus === null ? $activeClass : $inactiveClass }}">
                        همه
                    </button>
                    <button wire:click="setFilterStatus('success')" class="{{ $this->filterStatus === 'success' ? $activeClass : $inactiveClass }}">
                        موفق
                    </button>
                    <button wire:click="setFilterStatus('failed')" class="{{ $this->filterStatus === 'failed' ? $activeClass : $inactiveClass }}">
                        ناموفق
                    </button>
                </div>

                <div class="mr-auto">
                    <button wire:click="pruneOldLogs" wire:confirm="آیا از حذف لاگ‌های قدیمی‌تر از ۳۰ روز اطمینان دارید؟" style="background-color: #dc2626; color: #fff;" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-semibold shadow-sm transition hover:opacity-90">
                        <x-heroicon-o-trash class="h-4 w-4" />
                        حذف لاگ‌های قدیمی (۳۰+ روز)
                    </button>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">اندپوینت</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">متد</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">وضعیت</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">وضعیت HTTP</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">مدت (ms)</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">تاریخ</th>
                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($this->getLogs() as $log)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $log->id }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs text-gray-900 dark:text-white">{{ $this->getEndpointLabel($log->endpoint) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $methodColor = $this->getMethodColor($log->method);
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $methodColor }}">
                                        {{ $log->method }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusColor = $this->getStatusColor($log->success);
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColor }}">
                                        {{ $this->getStatusLabel($log->success) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $log->response_status ?? '—' }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $log->duration_ms ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $log->created_at ? \Morilog\Jalali\Jalalian::fromDateTime($log->created_at)->format('Y/m/d H:i:s') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <button wire:click="viewLog({{ $log->id }})" class="inline-flex items-center gap-1 rounded-lg bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700 transition">
                                        <x-heroicon-o-eye class="h-3 w-3" />
                                        جزئیات
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <x-heroicon-o-server-stack class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                        <span class="text-sm text-gray-500 dark:text-gray-400">لاگی یافت نشد</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $this->getLogs()->links() }}
            </div>
        </div>
    </div>

    {{-- Detail Modal --}}
    @if($showDetailModal && $selectedLog)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeDetailModal()">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" x-on:click="$wire.closeDetailModal()"></div>
            <div class="relative mx-auto w-full max-w-4xl rounded-xl bg-white shadow-xl dark:bg-gray-900 max-h-[85vh] overflow-hidden flex flex-col">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">جزئیات درخواست #{{ $selectedLog->id }}</h3>
                    <button wire:click="closeDetailModal()" class="rounded-lg p-1 text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                {{-- Content --}}
                <div class="overflow-y-auto p-6 space-y-6">
                    {{-- Meta Info --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Endpoint</span>
                            <p class="mt-1 font-mono text-sm text-gray-900 dark:text-white">{{ $selectedLog->endpoint }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Method</span>
                            <p class="mt-1">
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $this->getMethodColor($selectedLog->method) }}">
                                    {{ $selectedLog->method }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">HTTP Status</span>
                            <p class="mt-1 font-mono text-sm text-gray-900 dark:text-white">{{ $selectedLog->response_status ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">مدت زمان</span>
                            <p class="mt-1 font-mono text-sm text-gray-900 dark:text-white">{{ $selectedLog->duration_ms }} ms</p>
                        </div>
                    </div>

                    @if($selectedLog->error_message)
                        <div class="rounded-lg bg-danger-50 p-4 dark:bg-danger-500/10">
                            <span class="text-xs font-medium text-danger-700 dark:text-danger-500">پیام خطا</span>
                            <p class="mt-1 text-sm text-danger-800 dark:text-danger-400">{{ $selectedLog->error_message }}</p>
                        </div>
                    @endif

                    {{-- Request Body --}}
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Request Body</span>
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-50 p-4 text-xs font-mono text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $this->formatJson($selectedLog->request_body) }}</pre>
                    </div>

                    {{-- Response Body --}}
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Response Body</span>
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-50 p-4 text-xs font-mono text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $this->formatJson($selectedLog->response_body) }}</pre>
                    </div>

                    {{-- Timestamp --}}
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">تاریخ و ساعت</span>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ $selectedLog->created_at ? \Morilog\Jalali\Jalalian::fromDateTime($selectedLog->created_at)->format('Y/m/d H:i:s') : '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
