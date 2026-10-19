<x-filament-panels::page>
    @php($records = $this->getRecords())
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 text-right font-medium">زمان</th>
                    <th class="px-4 py-3 text-right font-medium">کاربر</th>
                    <th class="px-4 py-3 text-right font-medium">مورد تغییر</th>
                    <th class="px-4 py-3 text-right font-medium">فیلد</th>
                    <th class="px-4 py-3 text-right font-medium">مقدار قبلی</th>
                    <th class="px-4 py-3 text-right font-medium">مقدار جدید</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($records as $record)
                    @foreach ($record->changes as $field => $change)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3">{{ $this->formatCreatedAt($record) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $this->actorName($record) }}</td>
                            <td class="px-4 py-3">{{ $this->subjectLabel($record) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $this->fieldLabel($field) }}</td>
                            <td class="max-w-xs whitespace-pre-wrap break-all px-4 py-3 font-mono text-xs">{{ $this->formatValue($change['old'] ?? null) }}</td>
                            <td class="max-w-xs whitespace-pre-wrap break-all px-4 py-3 font-mono text-xs">{{ $this->formatValue($change['new'] ?? null) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">هنوز تغییری در قیمت‌ها ثبت نشده است.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $records->links() }}</div>
</x-filament-panels::page>
