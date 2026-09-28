<x-filament-panels::page>
    <p class="mb-4 text-sm text-gray-500">
        درصد اجرت هر محصول برای بازهٔ زمانی و نقش مشتری. مقدار <strong>۷.۷</strong> یعنی ۷.۷٪ اجرت
        (ضریب داخلی ۰.۰۷۷). پیش‌فرض همهٔ سلول‌ها ۱۰۰٪ است.
    </p>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-filament::button
            wire:click="toggleEdit"
            :icon="$isEditing ? 'heroicon-o-x-mark' : 'heroicon-o-pencil'"
            :color="$isEditing ? 'danger' : 'primary'"
            size="sm">
            {{ $isEditing ? 'لغو ویرایش' : 'ویرایش' }}
        </x-filament::button>

        @if($isEditing)
            <x-filament::button wire:click="save" color="success" size="sm" icon="heroicon-o-check">
                ذخیره تغییرات
            </x-filament::button>
        @endif
    </div>

    @if(count($rows) === 0)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            محصولی با قیمت‌گذاری پویا یافت نشد. ابتدا روی محصول «قیمت‌گذاری پویا» و آیتم تابلو قیمت را تنظیم کنید.
        </div>
    @else
        @php($activePeriodData = collect($periods)->firstWhere('slug', $activePeriod))

        <div class="mb-3 flex justify-center">
            <div class="inline-flex rounded-xl bg-gray-100 p-1">
                @foreach($periods as $period)
                    <button
                        type="button"
                        wire:click="setPeriod('{{ $period['slug'] }}')"
                        @class([
                            'rounded-lg px-6 py-2 text-sm transition',
                            'bg-white font-semibold text-gray-900 shadow-sm ring-1 ring-gray-950/5' => $activePeriod === $period['slug'],
                            'text-gray-500 hover:text-gray-900' => $activePeriod !== $period['slug'],
                        ])
                    >
                        {{ $period['name'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-950/5">
            <table class="w-full min-w-[900px] border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th rowspan="2" class="border-e border-gray-200 px-3 py-2 text-start">محصول</th>
                        <th rowspan="2" class="border-e border-gray-200 px-3 py-2 text-start">نوع</th>
                        <th rowspan="2" class="border-e border-gray-200 px-3 py-2 text-start">وزن (گرم)</th>

                        @if($activePeriodData)
                            <th colspan="{{ count($roles) }}" class="border-e-2 border-gray-300 bg-gray-200 px-3 py-2 text-center">
                                {{ $activePeriodData['name'] }}
                                <span class="block text-xs font-normal text-gray-500">
                                    {{ $activePeriodData['start'] }} - {{ $activePeriodData['end'] }}
                                </span>
                            </th>
                        @endif
                    </tr>

                    <tr class="bg-gray-50">
                        @foreach($roles as $role)
                            <th class="px-2 py-1.5 text-center text-[11px] font-normal text-gray-600">
                                {{ $role['name'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-t border-gray-100 odd:bg-white even:bg-gray-50">
                            <td class="px-3 py-2">
                                <a href="{{ $row['edit_url'] }}" class="font-semibold text-primary-600 hover:underline">
                                    {{ $row['name'] }}
                                </a>
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ $row['metal_label'] }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row['weight'] }}</td>

                            @foreach($roles as $role)
                                <td class="border-e border-gray-100 px-1 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <input
                                            type="number"
                                            min="0"
                                            max="1000"
                                            step="0.01"
                                            wire:model="values.{{ $row['id'] }}.{{ $activePeriod }}.{{ $role['slug'] }}"
                                            @disabled(! $isEditing)
                                            class="w-24 rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-center text-sm shadow-sm outline-none transition focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                                        />
                                        <span class="text-xs text-gray-400">٪</span>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500">
                {{ number_format($productCount) }} محصول — مقادیر بر حسب درصد است.
            </p>

            @if($isEditing)
                <x-filament::button wire:click="save" color="success" size="sm" icon="heroicon-o-check">
                    ذخیره تغییرات
                </x-filament::button>
            @endif
        </div>
    @endif
</x-filament-panels::page>
