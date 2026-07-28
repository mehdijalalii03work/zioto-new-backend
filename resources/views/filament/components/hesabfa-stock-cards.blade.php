@php
    $physical = (int) ($get('hesabfa_physical_stock') ?? 0);
    $reserved = (int) ($get('hesabfa_reserved_stock') ?? 0);
    $manualReserved = (int) ($get('hesabfa_manual_reserved') ?? 0);
    $sellable = max(0, $physical - $reserved - $manualReserved);
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 border-2 border-emerald-200 p-5 text-center shadow-sm">
        <div class="text-3xl font-bold text-emerald-700">{{ number_format($physical) }}</div>
        <div class="text-sm font-medium text-emerald-600 mt-1">موجودی فیزیکی (حسابفا)</div>
        <div class="text-xs text-emerald-500 mt-1">از حسابفا دریافت می‌شود</div>
    </div>

    <div class="rounded-xl bg-gradient-to-br from-amber-50 to-amber-100 border-2 border-amber-200 p-5 text-center shadow-sm">
        <div class="text-3xl font-bold text-amber-700">{{ number_format($reserved) }}</div>
        <div class="text-sm font-medium text-amber-600 mt-1">موجودی رزرو شده</div>
        <div class="text-xs text-amber-500 mt-1">سفارشات در حال پردازش</div>
    </div>

    <div class="rounded-xl bg-gradient-to-br from-sky-50 to-sky-100 border-2 border-sky-200 p-5 text-center shadow-sm">
        <div class="text-3xl font-bold text-sky-700">{{ number_format($sellable) }}</div>
        <div class="text-sm font-medium text-sky-600 mt-1">موجودی قابل فروش</div>
        <div class="text-xs text-sky-500 mt-1">فیزیکی - رزرو شده - رزرو دستی</div>
    </div>
</div>
