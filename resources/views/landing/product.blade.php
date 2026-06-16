@extends('landing.layouts.app')

@section('title', $product['name'] . ' | زیوتو')

@section('content')
<section class="container mx-auto px-4 py-12">
  <div class="max-w-5xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start"
         x-data="productPage()"
         x-init="init({{ Js::from($product) }})">
      <div class="flex flex-col items-center gap-4">
        <div class="relative w-full max-w-[300px]">
          <img :src="mainImage" alt="{{ $product['name'] }}" class="w-full aspect-[517/800] object-cover rounded-2xl border border-zioto-gold/20">
          @if ($product['badge'])
            <span class="absolute top-4 right-4 badge badge-gold text-base px-4 py-2">{{ $product['badge'] }}</span>
          @endif
        </div>
        @if (count($product['images']) > 1)
          <div class="flex gap-3 flex-wrap justify-center">
            @foreach ($product['images'] as $i => $img)
              <button @click="changeImage({{ $i }})"
                      :class="{ 'border-zioto-gold': currentImage === {{ $i }}, 'border-white/20': currentImage !== {{ $i }} }"
                      class="w-16 h-16 rounded-lg overflow-hidden border-2 hover:border-zioto-gold transition-colors">
                <img src="{{ $img }}" alt="{{ $product['name'] }} {{ $i + 1 }}" class="w-full h-full object-cover">
              </button>
            @endforeach
          </div>
        @endif
      </div>

      <div>
        <div class="flex items-center gap-3 mb-4">
          <span class="px-3 py-1 rounded-full text-sm {{ $product['category'] === 'gold' ? 'bg-zioto-gold/20 text-zioto-gold' : 'bg-white/10 text-white/60' }}">
            {{ $product['category'] === 'gold' ? 'شمش طلا' : 'شمش نقره' }}
          </span>
          <span class="px-3 py-1 rounded-full text-sm bg-white/10 text-white/60">عیار {{ $product['purity'] }}</span>
        </div>
        <h1 class="text-3xl font-bold text-white mb-2">{{ $product['name'] }}</h1>
        <p class="text-white/50 mb-6">وزن: {{ $product['weight'] }}</p>

        <div class="bg-[#1A1D23]/80 rounded-xl p-6 mb-6 border border-zioto-gold/10">
          <div class="flex items-center gap-3 mb-4">
            <span class="text-zioto-gold text-3xl font-black">{{ number_format($product['price'] / 10) }} تومان</span>
          </div>
          <div class="flex items-center gap-2 text-sm text-zioto-gold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            <span>امکان خرید اقساطی از طریق بانک ملی ایران</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-6">
          <div class="flex items-center gap-3 p-3 bg-[#1A1D23]/40 rounded-lg">
            <svg class="w-6 h-6 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span class="text-sm text-white/70">گواهی اصالت</span>
          </div>
          <div class="flex items-center gap-3 p-3 bg-[#1A1D23]/40 rounded-lg">
            <svg class="w-6 h-6 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            <span class="text-sm text-white/70">ارسال به همه نقاط ایران</span>
          </div>
          <div class="flex items-center gap-3 p-3 bg-[#1A1D23]/40 rounded-lg">
            <svg class="w-6 h-6 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-sm text-white/70">پرداخت امن</span>
          </div>
          <div class="flex items-center gap-3 p-3 bg-[#1A1D23]/40 rounded-lg">
            <svg class="w-6 h-6 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <span class="text-sm text-white/70">ضمانت بازخرید</span>
          </div>
        </div>

        <div class="flex items-center gap-4 mb-6">
          <span class="text-white/70">تعداد:</span>
          <div class="flex items-center border border-zioto-gold/30 rounded-lg overflow-hidden">
            <button @click="quantity = Math.max(1, quantity - 1)" class="px-4 py-2 text-zioto-gold hover:bg-zioto-gold/10 transition-colors">-</button>
            <span class="px-6 py-2 text-white font-bold" x-text="toPersianNum(quantity)">۱</span>
            <button @click="quantity++" class="px-4 py-2 text-zioto-gold hover:bg-zioto-gold/10 transition-colors">+</button>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4">
          <button @click="addToCart()" class="btn-gold flex-1 text-lg py-4">افزودن به سبد خرید</button>
          <button @click="buyNow()" class="btn-outline-gold flex-1 text-lg py-4">خرید فوری</button>
        </div>
      </div>
    </div>

    <div class="mt-12">
      <h2 class="text-xl font-bold text-white mb-4">توضیحات محصول</h2>
      <div class="text-white/70 leading-8 prose prose-invert max-w-none">
        {!! nl2br(e($product['description'])) !!}
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  function productPage() {
    return {
      product: null,
      quantity: 1,
      mainImage: '',
      currentImage: 0,
      images: [],

      init(product) {
        this.product = product;
        this.images = product.images && product.images.length > 0 ? product.images : [product.image];
        this.mainImage = this.images[0] || product.image;
      },

      changeImage(index) {
        this.currentImage = index;
        this.mainImage = this.images[index];
      },

      addToCart() {
        if (this.product) {
          Alpine.store('cart').add(this.product, this.quantity);
        }
      },

      buyNow() {
        if (this.product) {
          Alpine.store('cart').add(this.product, this.quantity);
          window.location.href = '{{ route('landing.cart') }}';
        }
      },
    }
  }
</script>
@endpush
