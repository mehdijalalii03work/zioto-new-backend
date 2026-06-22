@extends('landing.layouts.app')

@section('title', 'سفارش ثبت شد | زیوتو')

@section('content')
<section class="container mx-auto px-4 py-20 text-center">
  <div class="max-w-lg mx-auto">
    <div class="w-24 h-24 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6 success-check">
      <svg class="w-12 h-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 class="text-3xl font-bold text-white mb-4">سفارش شما ثبت شد!</h1>
    <p class="text-white/60 mb-4">
      شماره سفارش: <span id="orderNumber" class="text-zioto-gold font-bold text-xl"></span>
    </p>
    <p class="text-white/50 mb-8 leading-7">
      سفارش شما با موفقیت ثبت شد. در صورت انتخاب پرداخت اقساطی، پس از تایید اطلاعات کارمندی لینک پرداخت اقساطی برای شما ارسال خواهد شد.
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <a href="{{ route('landing.home') }}" onclick="localStorage.removeItem('cart')" class="btn-gold px-8 py-3 inline-block">بازگشت به فروشگاه</a>
      <a href="{{ route('landing.profile') }}" class="px-8 py-3 rounded-xl border border-zioto-gold/20 text-zioto-gold hover:bg-zioto-gold/10 transition-all inline-block">پیگیری سفارش</a>
    </div>
  </div>
</section>

@push('scripts')
<script>
  const params = new URLSearchParams(window.location.search);
  const order = params.get('order');
  if (order) {
    document.getElementById('orderNumber').textContent = order;
  } else {
    document.getElementById('orderNumber').textContent = 'ZT-{{ strtoupper(substr(uniqid(), -6)) }}';
  }
  localStorage.removeItem('cart');
</script>
@endpush
@endsection
