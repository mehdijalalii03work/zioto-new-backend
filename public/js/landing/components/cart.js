function addToCart(product) {
  const existing = STATE.cart.find(item => item.id === product.id);
  if (existing) { existing.quantity += STATE.quantity; } else { STATE.cart.push({ ...product, quantity: STATE.quantity }); }
  STATE.quantity = 1;
  updateCartCount();
  showNotification('محصول به سبد خرید اضافه شد');
}

function buyNow(product) {
  addToCart(product);
  navigateTo('cart');
}

function removeFromCart(productId) {
  STATE.cart = STATE.cart.filter(item => item.id !== productId);
  updateCartCount();
  renderPage();
}

function updateCartItemQuantity(productId, change) {
  const item = STATE.cart.find(item => item.id === productId);
  if (item) { item.quantity = Math.max(1, item.quantity + change); renderPage(); }
}

function getCartTotal() { return STATE.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0); }
function getCartCount() { return STATE.cart.reduce((sum, item) => sum + item.quantity, 0); }

function updateCartCount() {
  const count = getCartCount();
  const countEl = document.getElementById('cart-count');
  if (count > 0) { countEl.textContent = count; countEl.classList.remove('hidden'); }
  else { countEl.classList.add('hidden'); }
}

function renderCart() {
  if (STATE.cart.length === 0) {
    return `
      <section class="container mx-auto px-4 py-20 text-center">
        <div class="max-w-md mx-auto">
          <h2 class="text-2xl font-bold text-white mb-4">سبد خرید شما خالی است</h2>
          <p class="text-white/50 mb-8">محصولات ما را مشاهده کنید و محصول مورد نظرتان را به سبد خرید اضافه کنید.</p>
          <button onclick="navigateTo('home')" class="btn-gold px-8 py-3">مشاهده محصولات</button>
        </div>
      </section>
    `;
  }
  return `
    <section class="container mx-auto px-4 py-12">
      <h1 class="text-3xl font-bold text-white mb-8">سبد خرید</h1>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
          ${STATE.cart.map(item => `
            <div class="cart-item bg-[#1A1D23] rounded-xl p-4 flex gap-4">
              <img src="${item.image}" alt="${item.name}" class="w-20 aspect-[517/800] object-cover rounded-lg">
              <div class="flex-1">
                <h3 class="font-bold text-white">${item.name}</h3>
                <p class="text-sm text-white/50">عیار ${item.purity} | وزن ${item.weight}</p>
                <p class="text-zioto-gold font-bold mt-2">${formatPriceToman(item.price)}</p>
              </div>
              <div class="flex flex-col items-end justify-between">
                <button onclick="removeFromCart(${item.id})" class="text-red-400 hover:text-red-300 transition-colors">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <div class="flex items-center border border-zioto-gold/30 rounded-lg overflow-hidden">
                  <button onclick="updateCartItemQuantity(${item.id}, -1)" class="px-3 py-1 text-zioto-gold hover:bg-zioto-gold/10 transition-colors text-sm">-</button>
                  <span class="px-4 py-1 text-white text-sm font-bold">${toPersianNum(item.quantity)}</span>
                  <button onclick="updateCartItemQuantity(${item.id}, 1)" class="px-3 py-1 text-zioto-gold hover:bg-zioto-gold/10 transition-colors text-sm">+</button>
                </div>
              </div>
            </div>
          `).join('')}
        </div>
        <div class="lg:col-span-1">
          <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20 sticky top-24">
            <h3 class="text-xl font-bold text-white mb-6">خلاصه سفارش</h3>
            <div class="space-y-3 mb-6">
              <div class="flex justify-between text-white/70">
                <span>جمع کل</span>
                <span>${formatPriceToman(getCartTotal())}</span>
              </div>
              <div class="border-t border-white/10 pt-3">
                <div class="flex justify-between text-white font-bold text-lg">
                  <span>مبلغ قابل پرداخت</span>
                  <span class="text-zioto-gold">${formatPriceToman(getCartTotal())}</span>
                </div>
              </div>
            </div>
            <button onclick="navigateTo('checkout')" class="btn-gold w-full text-lg py-4">تکمیل خرید</button>
            <button onclick="navigateTo('home')" class="w-full text-white/60 hover:text-white text-sm mt-3 transition-colors">بازگشت به فروشگاه</button>
          </div>
        </div>
      </div>
    </section>
  `;
}
