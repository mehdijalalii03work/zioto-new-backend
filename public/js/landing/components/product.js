function renderProductDetail(product) {
  if (!product) return renderHome();
  return `
    <section class="container mx-auto px-4 py-12">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <div class="relative w-[250px] mx-auto lg:w-full">
          <img src="${product.image}" alt="${product.name}" class="w-[250px] lg:w-full aspect-[517/800] object-cover rounded-2xl border border-zioto-gold/20">
          ${product.badge ? `<span class="badge badge-gold text-base px-4 py-2">${product.badge}</span>` : ''}
        </div>
        <div>
          <div class="flex items-center gap-3 mb-4">
            <span class="px-3 py-1 rounded-full text-sm ${product.category === 'gold' ? 'bg-zioto-gold/20 text-zioto-gold' : 'bg-white/10 text-white/60'}">${product.category === 'gold' ? 'شمش طلا' : 'شمش نقره'}</span>
            <span class="px-3 py-1 rounded-full text-sm bg-white/10 text-white/60">عیار ${product.purity}</span>
          </div>
          <h1 class="text-3xl font-bold text-white mb-2">${product.name}</h1>
          <p class="text-white/50 mb-6">وزن: ${product.weight}</p>
          <div class="bg-zioto-green-dark/50 rounded-xl p-6 mb-6 border border-zioto-gold/10">
            <div class="flex items-center gap-3 mb-4">
              <span class="text-zioto-gold text-3xl font-black">${formatPriceToman(product.price)}</span>
              <span class="price-original text-lg">${formatPriceToman(product.originalPrice)}</span>
              <span class="bg-green-500/20 text-green-400 text-sm px-2 py-1 rounded">%${getDiscountPercent(product.originalPrice, product.price)} تخفیف</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-zioto-gold">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
              <span>امکان خرید اقساطی از طریق بانک ملی ایران</span>
            </div>
          </div>
          <p class="text-white/70 leading-8 mb-8">${product.description}</p>
          <div class="flex items-center gap-4 mb-6">
            <span class="text-white/70">تعداد:</span>
            <div class="flex items-center border border-zioto-gold/30 rounded-lg overflow-hidden">
              <button onclick="updateQuantity(-1)" class="px-4 py-2 text-zioto-gold hover:bg-zioto-gold/10 transition-colors">-</button>
              <span id="product-quantity" class="px-6 py-2 text-white font-bold">۱</span>
              <button onclick="updateQuantity(1)" class="px-4 py-2 text-zioto-gold hover:bg-zioto-gold/10 transition-colors">+</button>
            </div>
          </div>
          <div class="flex flex-col sm:flex-row gap-4">
            <button onclick="addToCart(PRODUCTS.find(p => p.slug === '${product.slug}'))" class="btn-gold flex-1 text-lg py-4">افزودن به سبد خرید</button>
            <button onclick="buyNow(PRODUCTS.find(p => p.slug === '${product.slug}'))" class="btn-outline-gold flex-1 text-lg py-4">خرید فوری</button>
          </div>
        </div>
      </div>
    </section>
  `;
}

function updateQuantity(change) {
  STATE.quantity = Math.max(1, STATE.quantity + change);
  document.getElementById('product-quantity').textContent = toPersianNum(STATE.quantity);
}
