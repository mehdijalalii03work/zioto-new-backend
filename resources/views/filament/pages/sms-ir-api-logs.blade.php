<x-filament-panels::page>
    @php $logs = $this->getLogs(); @endphp
    <div class="fi-card fi-w-full rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-card-body p-6">
            <div class="mb-4 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">وضعیت:</span>
                    @php
                        $activeClass = 'inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white';
                        $inactiveClass = 'inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600';
                    @endphp
                    <button wire:click="setFilterStatus(null)" class="{{ $this->filterStatus === null ? $activeClass : $inactiveClass }}">همه</button>
                    <button wire:click="setFilterStatus('success')" class="{{ $this->filterStatus === 'success' ? $activeClass : $inactiveClass }}">موفق</button>
                    <button wire:click="setFilterStatus('failed')" class="{{ $this->filterStatus === 'failed' ? $activeClass : $inactiveClass }}">ناموفق</button>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    شماره موبایل:
                    <input wire:model.live.debounce.400ms="filterMobile" type="search" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="0912...">
                </label>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                        <th class="px-4 py-3 text-start text-xs text-gray-500">#</th>
                        <th class="px-4 py-3 text-start text-xs text-gray-500">شماره موبایل</th>
                        <th class="px-4 py-3 text-start text-xs text-gray-500">اندپوینت</th>
                        <th class="px-4 py-3 text-start text-xs text-gray-500">وضعیت</th>
                        <th class="px-4 py-3 text-start text-xs text-gray-500">HTTP / کد خطا</th>
                        <th class="px-4 py-3 text-start text-xs text-gray-500">مدت (ms)</th>
                        <th class="px-4 py-3 text-start text-xs text-gray-500">تاریخ</th>
                        <th class="px-4 py-3 text-start text-xs text-gray-500">جزئیات</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $log->_id }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-900 dark:text-white">{{ $log->mobile }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-900 dark:text-white">{{ $log->endpoint }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-md px-2 py-1 text-xs ring-1 ring-inset {{ $this->getStatusColor($log->success) }}">{{ $this->getStatusLabel($log->success) }}</span></td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $log->response_status ?? '—' }} / {{ $log->error_code ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $log->duration_ms ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">{{ $log->created_at ? \Morilog\Jalali\Jalalian::fromDateTime($log->created_at)->format('Y/m/d H:i:s') : '—' }}</td>
                                <td class="px-4 py-3"><button wire:click="viewLog('{{ $log->_id }}')" class="rounded-lg px-2 py-1 text-xs font-semibold text-primary-700 ring-1 ring-gray-300 dark:text-primary-400 dark:ring-gray-600">جزئیات</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500">لاگی یافت نشد</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
                    <span>صفحه {{ $logs->currentPage() }} از {{ $logs->lastPage() }}</span>
                    <div class="flex gap-3"><button wire:click="previousPage" @disabled($logs->onFirstPage())>قبلی</button><button wire:click="nextPage" @disabled(!$logs->hasMorePages())>بعدی</button></div>
                </div>
            @endif
        </div>
    </div>

    @if($showDetailModal && $selectedLog)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeDetailModal()">
            <div class="fixed inset-0 bg-gray-900/60" x-on:click="$wire.closeDetailModal()"></div>
            <div class="relative flex max-h-[85vh] w-full max-w-4xl flex-col rounded-xl bg-white shadow-xl dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">جزئیات درخواست SMS.ir</h3>
                    <button wire:click="closeDetailModal" class="text-gray-500">بستن</button>
                </div>
                <div class="space-y-5 overflow-y-auto p-6">
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div><span class="text-xs text-gray-500">اندپوینت</span><p class="mt-1 font-mono text-sm">{{ $selectedLog->endpoint }}</p></div>
                        <div><span class="text-xs text-gray-500">شماره موبایل</span><p class="mt-1 font-mono text-sm">{{ $selectedLog->mobile }}</p></div>
                        <div><span class="text-xs text-gray-500">وضعیت HTTP</span><p class="mt-1 font-mono text-sm">{{ $selectedLog->response_status ?? '—' }}</p></div>
                        <div><span class="text-xs text-gray-500">کد خطا</span><p class="mt-1 font-mono text-sm">{{ $selectedLog->error_code ?? '—' }}</p></div>
                    </div>
                    @if($selectedLog->error_message)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $selectedLog->error_message }}</div>
                    @endif
                    <div><span class="text-xs text-gray-500">Request Body (کد OTP ماسک شده)</span><pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-gray-50 p-4 text-xs dark:bg-white/5">{{ $this->formatJson($selectedLog->request_body) }}</pre></div>
                    <div><span class="text-xs text-gray-500">Response Body</span><pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-gray-50 p-4 text-xs dark:bg-white/5">{{ $this->formatJson($selectedLog->response_body) }}</pre></div>
                    <div><span class="text-xs text-gray-500">مدت و تاریخ</span><p class="mt-1 text-sm">{{ $selectedLog->duration_ms ?? '—' }} ms · {{ $selectedLog->created_at ? \Morilog\Jalali\Jalalian::fromDateTime($selectedLog->created_at)->format('Y/m/d H:i:s') : '—' }}</p></div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
