<x-filament-panels::page>
    <form wire:submit="preview" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit" wire:loading.attr="disabled">
            پیش‌نمایش سند
        </x-filament::button>
    </form>

    @if ($previewErrors !== [])
        <x-filament::section heading="خطاهای سند">
            <ul class="space-y-2">
                @foreach ($previewErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($previewItems !== [])
        <x-filament::section heading="پیش‌نمایش افزایش موجودی">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-start">کالا</th>
                            <th class="px-3 py-2 text-start">کد کالا</th>
                            <th class="px-3 py-2 text-start">افزایش موجودی</th>
                            <th class="px-3 py-2 text-start">سریال تکراری</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewItems as $item)
                            <tr class="border-t border-gray-200 dark:border-white/10">
                                <td class="px-3 py-2">{{ $item['name'] }}</td>
                                <td class="px-3 py-2"><bdi>{{ $item['code'] }}</bdi></td>
                                <td class="px-3 py-2">{{ $item['quantity'] }}</td>
                                <td class="px-3 py-2">{{ $item['duplicates'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($previewHash !== null)
        <x-filament::button wire:click="receive" wire:loading.attr="disabled">
            تأیید و ثبت ورود موجودی
        </x-filament::button>
    @endif
</x-filament-panels::page>
