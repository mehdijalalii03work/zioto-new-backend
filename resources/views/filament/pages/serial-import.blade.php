<x-filament-panels::page>
    <div wire:poll.3s="refreshProgress"></div>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        فایل XLSX کارخانه را بارگذاری کنید. فقط ستون M با عنوان «عامل اول» خوانده می‌شود و بقیه ستون‌ها
        نادیده گرفته می‌شوند. سریال تکراری هرگز دوباره ثبت نمی‌شود و پس از ایمپورت گزارش کامل نمایش داده می‌شود.
    </p>

    <form wire:submit="analyzeUpload" class="mb-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3 mt-4">
            <x-filament::button type="submit">
                بررسی بدون ذخیره
            </x-filament::button>

            <x-filament::button
                color="gray"
                tag="a"
                :href="route('filament.admin.resources.serial-import-batches.index')"
            >
                تاریخچه ایمپورت‌ها
            </x-filament::button>
        </div>
    </form>

    @if ($this->getBatchRecord() && $this->summary)
        @if ($this->hashWarning)
            <div class="mb-4 rounded-lg bg-warning-50 p-4 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">
                {{ $this->hashWarning }}
            </div>
        @endif

        @if (count($this->worksheetOptions) > 1)
            <div class="mb-4 flex items-center gap-3">
                <label for="serial-worksheet">کاربرگ</label>
                <select id="serial-worksheet" wire:model="selectedWorksheet" class="rounded border-gray-300 dark:bg-gray-900">
                    <option value="">انتخاب کاربرگ</option>
                    @foreach ($this->worksheetOptions as $worksheet)
                        <option value="{{ $worksheet }}">{{ $worksheet }}</option>
                    @endforeach
                </select>
                <x-filament::button wire:click="selectWorksheet">تحلیل کاربرگ</x-filament::button>
            </div>
        @endif

        <div class="mb-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="mb-2 flex flex-wrap items-center gap-3">
                <span class="font-semibold">{{ $this->getBatchRecord()->original_filename }}</span>
                <span class="text-sm text-gray-500">وضعیت: {{ $this->getBatchRecord()->status->label() }}</span>
            </div>

            <dl class="grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
                <div><dt class="text-gray-500">سطرهای غیرخالی</dt><dd class="font-semibold">{{ $this->summary['nonempty_rows'] }}</dd></div>
                <div><dt class="text-gray-500">وارد شده</dt><dd class="font-semibold">{{ $this->summary['imported'] }}</dd></div>
                <div><dt class="text-gray-500">آماده ورود</dt><dd class="font-semibold">{{ $this->summary['eligible_for_import'] }}</dd></div>
                <div><dt class="text-gray-500">تکراری در فایل</dt><dd class="font-semibold">{{ $this->summary['duplicate_in_file'] }}</dd></div>
                <div><dt class="text-gray-500">تکراری در سامانه</dt><dd class="font-semibold">{{ $this->summary['duplicate_in_db'] }}</dd></div>
                <div><dt class="text-gray-500">قالب نامعتبر</dt><dd class="font-semibold">{{ $this->summary['invalid_format'] }}</dd></div>
                <div><dt class="text-gray-500">پیشوند ناشناخته</dt><dd class="font-semibold">{{ $this->summary['unknown_prefix'] }}</dd></div>
                <div><dt class="text-gray-500">در انتظار</dt><dd class="font-semibold">{{ $this->summary['pending'] }}</dd></div>
            </dl>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                @if (in_array($this->summary['status'], ['ready', 'failed'], true) && ($this->summary['pending'] ?? 0) > 0)
                    <x-filament::button wire:click="runImport" wire:loading.attr="disabled" color="success">
                        ایمپورت نهایی
                    </x-filament::button>
                @endif

                @if (in_array($this->getBatchRecord()->status->value, ['completed', 'completed_with_errors', 'failed'], true))
                    <x-filament::button color="gray" tag="a" :href="$this->getReportUrl()">
                        دانلود گزارش کامل
                    </x-filament::button>
                @endif
            </div>
        </div>

        @if (count($this->preview) > 0)
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 dark:bg-gray-800">
                            <th class="px-3 py-2 text-right">ردیف</th>
                            <th class="px-3 py-2 text-right">سریال</th>
                            <th class="px-3 py-2 text-right">محصول</th>
                            <th class="px-3 py-2 text-right">وضعیت</th>
                            <th class="px-3 py-2 text-right">دلیل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->preview as $previewRow)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2">{{ $previewRow['row_number'] }}</td>
                                <td class="px-3 py-2 font-mono" dir="ltr">{{ $previewRow['normalized_serial'] ?? $previewRow['raw_value'] }}</td>
                                <td class="px-3 py-2">{{ $previewRow['product'] }}</td>
                                <td class="px-3 py-2">{{ $previewRow['validation'] }} / {{ $previewRow['result'] }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $previewRow['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-400">۲۰ ردیف اول نمایش داده شده است؛ گزارش کامل همه ردیف‌ها را دارد.</p>
        @endif
    @endif
</x-filament-panels::page>
