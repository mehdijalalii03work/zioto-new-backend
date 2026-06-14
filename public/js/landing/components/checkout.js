function renderCheckout() {
  if (STATE.cart.length === 0) { navigateTo('cart'); return ''; }
  return `
    <section class="container mx-auto px-4 py-12">
      <h1 class="text-3xl font-bold text-white mb-8">تکمیل خرید</h1>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/20">
            <h3 class="text-xl font-bold text-white mb-6">اطلاعات شخصی</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div><label class="form-label">نام و نام خانوادگی *</label><input type="text" class="form-input" placeholder="نام کامل" id="checkout-name"></div>
              <div><label class="form-label">شماره موبایل *</label><input type="tel" class="form-input" placeholder="۰۹۱۲XXXXXXX" id="checkout-phone" dir="ltr"></div>
              <div><label class="form-label">کد ملی *</label><input type="text" class="form-input" placeholder="XXXXXXXXXX" id="checkout-national-id" dir="ltr"></div>
              <div><label class="form-label">شماره کارمندی بانک ملی *</label><input type="text" class="form-input" placeholder="شماره کارمندی" id="checkout-employee-id" dir="ltr"></div>
            </div>
          </div>
          <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/20">
            <h3 class="text-xl font-bold text-white mb-6">روش پرداخت</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="payment-method active" onclick="STATE.selectedPaymentMethod='installment'">
                <div class="flex items-center gap-3">
                  <div class="w-5 h-5 rounded-full border-2 border-zioto-gold flex items-center justify-center"><div class="w-3 h-3 rounded-full bg-zioto-gold"></div></div>
                  <div><p class="font-bold text-white">خرید اقساطی</p><p class="text-sm text-white/50">بانک ملی ایران</p></div>
                </div>
              </div>
              <div class="payment-method" onclick="STATE.selectedPaymentMethod='online'">
                <div class="flex items-center gap-3">
                  <div class="w-5 h-5 rounded-full border-2 border-white/30 flex items-center justify-center"><div class="w-3 h-3 rounded-full bg-transparent"></div></div>
                  <div><p class="font-bold text-white">پرداخت آنلاین</p><p class="text-sm text-white/50">پرداخت کامل نقدی</p></div>
                </div>
              </div>
            </div>
          </div>
          <button onclick="submitOrder()" class="btn-gold w-full text-lg py-4">ثبت سفارش و پرداخت</button>
        </div>
        <div class="lg:col-span-1">
          <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/20 sticky top-24">
            <h3 class="text-xl font-bold text-white mb-6">سفارش شما</h3>
            <div class="space-y-4 mb-6">
              ${STATE.cart.map(item => `
                <div class="flex gap-3">
                  <img src="${item.image}" alt="${item.name}" class="w-14 aspect-[517/800] object-cover rounded-lg">
                  <div class="flex-1">
                    <p class="text-sm font-bold text-white">${item.name}</p>
                    <p class="text-xs text-white/50">${toPersianNum(item.quantity)} × ${formatPriceToman(item.price)}</p>
                  </div>
                </div>
              `).join('')}
            </div>
            <div class="border-t border-white/10 pt-4">
              <div class="flex justify-between text-white font-bold text-lg">
                <span>مبلغ نهایی</span>
                <span class="text-zioto-gold">${formatPriceToman(getCartTotal())}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  `;
}

function submitOrder() {
  const name = document.getElementById('checkout-name')?.value;
  const phone = document.getElementById('checkout-phone')?.value;
  if (!name || !phone) { showNotification('لطفاً فیلدهای الزامی را پر کنید', 'error'); return; }
  showNotification('در حال ثبت سفارش...', 'info');
  setTimeout(() => navigateTo('success'), 1500);
}

function renderSuccess() {
  const orderNumber = 'ZT-' + Date.now().toString(36).toUpperCase();
  return `
    <section class="container mx-auto px-4 py-20 text-center">
      <div class="max-w-lg mx-auto">
        <div class="w-24 h-24 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6 success-check">
          <svg class="w-12 h-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-3xl font-bold text-white mb-4">سفارش شما ثبت شد!</h1>
        <p class="text-white/60 mb-6">شماره سفارش: <span class="text-zioto-gold font-bold">${orderNumber}</span></p>
        <p class="text-white/50 mb-8 leading-7">سفارش شما با موفقیت ثبت شد. در صورت انتخاب پرداخت اقساطی، پس از تایید اطلاعات کارمندی لینک پرداخت اقساطی برای شما ارسال خواهد شد.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <button onclick="STATE.cart = []; navigateTo('home')" class="btn-gold px-8 py-3">بازگشت به فروشگاه</button>
        </div>
      </div>
    </section>
  `;
}
