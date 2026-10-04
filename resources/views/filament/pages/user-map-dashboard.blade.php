<x-filament-panels::page>
    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Total Users --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-primary-400 to-primary-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">تعداد کل کاربران</p>
                    <p class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ number_format($stats['total_users']) }}</p>
                </div>
                <div class="rounded-2xl bg-primary-50 p-3 dark:bg-primary-500/10">
                    <x-heroicon-o-users class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
        </div>

        {{-- Users Placed On The Map --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-emerald-400 to-emerald-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">کاربران روی نقشه</p>
                    <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ number_format($stats['mapped_users']) }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">از {{ number_format($stats['total_users']) }} کاربر</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-3 dark:bg-emerald-500/10">
                    <x-heroicon-o-map-pin class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>
        </div>

        {{-- Covered Provinces --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-purple-400 to-purple-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">استان‌های دارای کاربر</p>
                    <p class="text-3xl font-bold tracking-tight text-purple-600 dark:text-purple-400">
                        {{ number_format($stats['active_provinces']) }}
                        <span class="text-lg font-medium text-gray-400 dark:text-gray-500">از {{ number_format($stats['total_provinces']) }}</span>
                    </p>
                </div>
                <div class="rounded-2xl bg-purple-50 p-3 dark:bg-purple-500/10">
                    <x-heroicon-o-squares-2x2 class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </div>

        {{-- Top Province --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-amber-400 to-amber-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">بیشترین کاربر (استان)</p>
                    <p class="text-2xl font-bold tracking-tight text-amber-700 dark:text-amber-300">{{ $stats['top_province'] ?? '—' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($stats['top_province_count']) }} کاربر</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-3 dark:bg-amber-500/10">
                    <x-heroicon-o-trophy class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
        </div>
    </div>

    {{-- Iran Map --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">توزیع کاربران بر اساس استان</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                رنگ‌گذاری بر اساس تعداد کاربران: از قرمز کم‌رنگ (کمترین) تا قرمز پررنگ (بیشترین)
            </p>
        </div>

        {{-- The Alpine state lives inline on this element: @script payloads are only
             evaluated after Alpine walks the tree, so a global factory would not
             exist yet when x-data is evaluated. --}}
        <div
            class="relative"
            x-data="{
                tooltip: { visible: false, x: 0, y: 0, name: '', count: 0 },
                selected: { visible: false, name: '', count: 0 },
                showTooltip(province, event) {
                    this.tooltip.visible = true;
                    this.tooltip.x = event.clientX + 12;
                    this.tooltip.y = event.clientY + 12;
                    this.readProvince(province, this.tooltip);
                },
                hideTooltip() {
                    this.tooltip.visible = false;
                },
                selectProvince(province) {
                    this.readProvince(province, this.selected);
                    this.selected.visible = true;
                },
                clearSelection() {
                    this.selected.visible = false;
                },
                readProvince(province, target) {
                    target.name = province.dataset.provinceName;
                    target.count = Number.parseInt(province.dataset.userCount, 10);
                },
            }"
        >
            @include('filament.pages.partials.iran-provinces')

            {{-- Tooltip --}}
            <div
                x-show="tooltip.visible"
                x-transition
                x-cloak
                class="fixed z-50 pointer-events-none"
                :style="'top: ' + tooltip.y + 'px; left: ' + tooltip.x + 'px'"
                role="status"
            >
                <div class="rounded-lg bg-gray-900 px-3 py-2 text-white text-sm shadow-lg">
                    <p class="font-semibold" x-text="tooltip.name"></p>
                    <p x-text="'کاربران: ' + tooltip.count"></p>
                </div>
            </div>

            {{-- Selected Province Info Panel --}}
            <div
                x-show="selected.visible"
                x-transition
                x-cloak
                class="mt-6 p-4 rounded-lg bg-gray-50 dark:bg-gray-800"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white" x-text="selected.name"></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="'تعداد کاربران: ' + selected.count"></p>
                    </div>
                    <button
                        type="button"
                        @click="clearSelection()"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        aria-label="بستن"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
            </div>

            {{-- Legend --}}
            <div class="mt-6 flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium">شدت رنگ:</span>
                <div class="flex items-center gap-2">
                    @foreach ($legendColors as $color)
                        <div class="w-8 h-4 rounded border border-gray-300 dark:border-gray-600" style="background-color: {{ $color }}"></div>
                    @endforeach
                </div>
                <span>کم</span>
                <span class="ml-auto">زیاد</span>
            </div>

            {{-- Coverage Note --}}
            @if ($stats['unmapped_users'] > 0)
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($stats['unmapped_users']) }} کاربر آدرس ثبت‌شده دارند ولی استانشان مشخص نیست، بنابراین در نقشه نمایش داده نمی‌شوند.
                </p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
