<x-filament-panels::page>
    @php
        $provincePaths = [
            1 => 'M380,180 L400,160 L420,185 L410,210 L390,200 Z',
            2 => 'M350,200 L380,180 L390,220 L360,240 L340,210 Z',
            3 => 'M250,180 L290,160 L320,190 L310,220 L270,210 Z',
            4 => 'M150,160 L200,140 L240,170 L220,200 L180,190 Z',
            5 => 'M80,140 L130,120 L160,150 L140,190 L100,170 Z',
            6 => 'M40,100 L90,80 L120,110 L100,150 L60,130 Z',
            7 => 'M20,150 L50,130 L80,160 L60,200 L30,180 Z',
            8 => 'M10,200 L40,180 L70,210 L50,250 L20,230 Z',
            9 => 'M30,240 L60,220 L90,250 L70,290 L40,270 Z',
            10 => 'M60,270 L90,250 L120,280 L100,320 L70,300 Z',
            11 => 'M100,300 L140,280 L170,310 L150,350 L120,330 Z',
            12 => 'M150,320 L190,300 L220,330 L200,370 L160,350 Z',
            13 => 'M200,340 L240,320 L270,350 L250,390 L210,370 Z',
            14 => 'M270,340 L310,320 L340,350 L320,390 L280,370 Z',
            15 => 'M340,330 L380,310 L410,340 L390,380 L350,360 Z',
            16 => 'M410,320 L450,300 L480,330 L460,370 L420,350 Z',
            17 => 'M480,300 L520,280 L550,310 L530,350 L490,330 Z',
            18 => 'M550,280 L590,260 L620,290 L600,330 L560,310 Z',
            19 => 'M620,260 L660,240 L690,270 L670,310 L630,290 Z',
            20 => 'M500,340 L540,320 L570,350 L550,390 L510,370 Z',
            21 => 'M550,350 L590,330 L620,360 L600,400 L560,380 Z',
            22 => 'M480,370 L520,350 L550,380 L530,420 L490,400 Z',
            23 => 'M420,380 L460,360 L490,390 L470,430 L430,410 Z',
            24 => 'M380,390 L420,370 L450,400 L430,440 L390,420 Z',
            25 => 'M340,400 L380,380 L410,410 L390,450 L350,430 Z',
            26 => 'M300,410 L340,390 L370,420 L350,460 L310,440 Z',
            27 => 'M260,400 L300,380 L330,410 L310,450 L270,430 Z',
            28 => 'M220,380 L260,360 L290,390 L270,430 L230,410 Z',
            29 => 'M180,360 L220,340 L250,370 L230,410 L190,390 Z',
            30 => 'M140,330 L180,310 L210,340 L190,380 L150,360 Z',
            31 => 'M100,300 L140,280 L170,310 L150,350 L110,330 Z',
        ];

        $provinceLabels = [
            1 => [400, 195],
            2 => [365, 215],
            3 => [285, 195],
            4 => [205, 185],
            5 => [125, 165],
            6 => [95, 125],
            7 => [45, 165],
            8 => [35, 215],
            9 => [65, 255],
            10 => [90, 295],
            11 => [140, 325],
            12 => [195, 335],
            13 => [245, 345],
            14 => [295, 345],
            15 => [375, 335],
            16 => [445, 325],
            17 => [515, 305],
            18 => [585, 285],
            19 => [655, 265],
            20 => [525, 355],
            21 => [585, 355],
            22 => [515, 385],
            23 => [455, 405],
            24 => [415, 415],
            25 => [375, 425],
            26 => [335, 435],
            27 => [295, 415],
            28 => [255, 395],
            29 => [205, 375],
            30 => [175, 345],
            31 => [135, 315],
        ];
    @endphp

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

        {{-- Active Provinces --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-emerald-400 to-emerald-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">استان‌های فعال</p>
                    <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ number_format($stats['active_provinces']) }}</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-3 dark:bg-emerald-500/10">
                    <x-heroicon-o-map-pin class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>
        </div>

        {{-- Top Province --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-amber-400 to-amber-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">بیشترین کاربر (استان)</p>
                    <p class="text-2xl font-bold tracking-tight text-amber-700 dark:text-amber-300">{{ $stats['top_province'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($stats['top_province_count']) }} کاربر</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-3 dark:bg-amber-500/10">
                    <x-heroicon-o-trophy class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
        </div>

        {{-- Total Provinces --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-purple-400 to-purple-600"></div>
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">تعداد کل استان‌ها</p>
                    <p class="text-3xl font-bold tracking-tight text-purple-600 dark:text-purple-400">{{ number_format($stats['total_provinces']) }}</p>
                </div>
                <div class="rounded-2xl bg-purple-50 p-3 dark:bg-purple-500/10">
                    <x-heroicon-o-squares-2x2 class="h-6 w-6 text-purple-600 dark:text-purple-400" />
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

        <div class="relative" x-data="iranMap()">
            <div class="overflow-auto">
                <svg
                    viewBox="0 0 800 600"
                    class="w-full max-w-4xl h-auto"
                    @mousemove.window="showTooltip($event)"
                    @mouseleave.window="hideTooltip()"
                >
                    <defs>
                        <style>
                            .province-path {
                                fill: #fff;
                                stroke: #e5e7eb;
                                stroke-width: 1;
                                transition: fill 0.2s, stroke 0.2s;
                            }
                            .province-path:hover {
                                stroke: #991b1b;
                                stroke-width: 2;
                                cursor: pointer;
                            }
                            .province-path.has-data {
                                cursor: pointer;
                            }
                            .province-label {
                                font-size: 10px;
                                fill: #6b7280;
                                pointer-events: none;
                                text-anchor: middle;
                                dominant-baseline: middle;
                            }
                            .province-label:hover {
                                fill: #1f2937;
                                font-weight: 600;
                            }
                        </style>
                    </defs>

                    {{-- Iran Provinces Map --}}
                    @foreach($provinceData as $id => $data)
                        <path
                            class="province-path {{ $data['count'] > 0 ? 'has-data' : '' }}"
                            :style="'fill: ' + @this.provinceColors[{{ $id }}]"
                            d="{{ $provincePaths[$id] ?? '' }}"
                            data-province-id="{{ $id }}"
                            data-province-name="{{ $data['name'] }}"
                            data-user-count="{{ $data['count'] }}"
                            @click="showProvinceInfo({{ $id }}, '{{ $data['name'] }}', {{ $data['count'] }})"
                        />
                    @endforeach

                    {{-- Province Labels --}}
                    @foreach($provinceData as $id => $data)
                        @if(isset($provinceLabels[$id]))
                            <text
                                class="province-label"
                                x="{{ $provinceLabels[$id][0] }}"
                                y="{{ $provinceLabels[$id][1] }}"
                            >
                                {{ $data['name'] }}
                            </text>
                        @endif
                    @endforeach
                </svg>
            </div>

            {{-- Tooltip --}}
            <div
                x-show="showTooltip"
                x-transition
                class="fixed z-50 pointer-events-none"
                :style="'top: ' + (tooltipY + 10) + 'px; left: ' + (tooltipX + 10) + 'px'"
            >
                <div class="rounded-lg bg-gray-900 px-3 py-2 text-white text-sm shadow-lg">
                    <p class="font-semibold" x-text="tooltipProvince"></p>
                    <p x-text="'کاربران: ' + tooltipCount"></p>
                </div>
            </div>

            {{-- Selected Province Info Panel --}}
            <div x-show="selectedProvince" x-transition class="mt-6 p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white" x-text="selectedProvinceName"></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="'تعداد کاربران: ' + selectedProvinceCount"></p>
                    </div>
                    <button @click="selectedProvince = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
            </div>

            {{-- Legend --}}
            <div class="mt-6 flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium">شدت رنگ:</span>
                <div class="flex items-center gap-2">
                    @for($i = 0; $i <= 4; $i++)
                        <div
                            class="w-8 h-4 rounded border border-gray-300 dark:border-gray-600"
                            :style="'background-color: ' + @this.legendColors[{{ $i }}]"
                        ></div>
                    @endfor
                </div>
                <span>کم</span>
                <span class="ml-auto">زیاد</span>
            </div>
        </div>
    </div>

    <script>
        function iranMap() {
            return {
                showTooltip: false,
                tooltipX: 0,
                tooltipY: 0,
                tooltipProvince: '',
                tooltipCount: 0,
                selectedProvince: false,
                selectedProvinceName: '',
                selectedProvinceCount: 0,

                provinceColors: {
                    @foreach($provinceData as $id => $data)
                        {{ $id }}: '{{ $data['color'] }}',
                    @endforeach
                },

                legendColors: [
                    'rgb(255, 200, 200)',
                    'rgb(255, 160, 160)',
                    'rgb(255, 120, 120)',
                    'rgb(255, 80, 80)',
                    'rgb(255, 0, 0)',
                ],

                showTooltip(event) {
                    const path = event.target.closest('.province-path');
                    if (!path) return;

                    this.tooltipProvince = path.dataset.provinceName;
                    this.tooltipCount = path.dataset.userCount;
                    this.tooltipX = event.clientX;
                    this.tooltipY = event.clientY;
                    this.showTooltip = true;
                },

                hideTooltip() {
                    this.showTooltip = false;
                },

                showProvinceInfo(id, name, count) {
                    this.selectedProvince = true;
                    this.selectedProvinceName = name;
                    this.selectedProvinceCount = count;
                },
            };
        }
    </script>
</x-filament-panels::page>