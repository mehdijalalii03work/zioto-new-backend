function renderHome() {
  return `
    <section class="hero-gradient relative overflow-hidden min-h-[70vh] flex items-center">
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        ${Array(20).fill(0).map((_, i) => `<div class="particle" style="left:${Math.random()*100}%;top:${Math.random()*100}%;animation-delay:${Math.random()*3}s"></div>`).join('')}
      </div>
      <div class="container mx-auto px-4 py-20 relative z-10">
        <div class="text-center max-w-4xl mx-auto">
          <div class="inline-block mb-6">
            <span class="bg-zioto-gold/20 text-zioto-gold px-4 py-2 rounded-full text-sm font-medium border border-zioto-gold/30">
              ویژه مشتریان و کارمندان بانک ملی ایران
            </span>
          </div>
          <h1 class="hero-title text-4xl md:text-6xl font-black mb-6 leading-tight">
            <span class="gold-shimmer">شمش طلا و نقره</span>
            <br>
            <span class="text-white">با خیال راحت اقساطی بخرید</span>
          </h1>
          <p class="hero-subtitle text-lg md:text-xl text-white/70 mb-10 max-w-2xl mx-auto leading-8">
            خرید مطمئن شمش طلا و نقره خالص با گواهی اصالت و امکان پرداخت اقساطی از طریق درگاه بانک ملی ایران
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="document.getElementById('products-section').scrollIntoView({behavior:'smooth'})" class="btn-gold text-lg px-8 py-4 pulse-gold">
              مشاهده محصولات
            </button>
            <button class="btn-outline-gold text-lg px-8 py-4">
              شرایط اقساط
            </button>
          </div>
          <div class="mt-12 flex flex-wrap justify-center gap-8 text-sm text-white/60">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-zioto-gold" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>گواهی اصالت</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-zioto-gold" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>ارسال رایگان</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-zioto-gold" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>پرداخت اقساطی</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-zioto-gold" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span>بانک ملی ایران</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="products-section" class="container mx-auto px-4 py-16">
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">محصولات ما</h2>
        <p class="text-white/60 max-w-xl mx-auto">شمش طلا و نقره خالص با بهترین قیمت و امکان خرید اقساطی</p>
        <div class="w-20 h-1 bg-zioto-gold mx-auto mt-4 rounded-full"></div>
      </div>
      <div class="flex justify-center gap-4 mb-10">
        <button onclick="filterProducts('all')" class="filter-btn active px-6 py-2 rounded-full text-sm font-medium transition-all" data-filter="all">همه</button>
        <button onclick="filterProducts('gold')" class="filter-btn px-6 py-2 rounded-full text-sm font-medium transition-all" data-filter="gold">شمش طلا</button>
        <button onclick="filterProducts('silver')" class="filter-btn px-6 py-2 rounded-full text-sm font-medium transition-all" data-filter="silver">شمش نقره</button>
      </div>
      <div id="products-grid" class="flex flex-wrap justify-center gap-6">
        ${renderProductCards(PRODUCTS)}
      </div>
    </section>

    <section class="bg-zioto-green-dark/50 py-16">
      <div class="container mx-auto px-4">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-bold text-white mb-4">چرا زیوتو؟</h2>
          <div class="w-20 h-1 bg-zioto-gold mx-auto rounded-full"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="text-center p-8 rounded-2xl bg-zioto-green/50 border border-zioto-gold/20 hover:border-zioto-gold/40 transition-all">
            <div class="w-16 h-16 bg-zioto-gold/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-zioto-gold mb-3">ضمانت اصالت</h3>
            <p class="text-white/60 leading-7">تمامی محصولات دارای گواهی اصالت و شماره سریال منحصربفرد هستند.</p>
          </div>
          <div class="text-center p-8 rounded-2xl bg-zioto-green/50 border border-zioto-gold/20 hover:border-zioto-gold/40 transition-all">
            <div class="w-16 h-16 bg-zioto-gold/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-zioto-gold mb-3">پرداخت اقساطی</h3>
            <p class="text-white/60 leading-7">امکان خرید اقساطی از طریق درگاه بانک ملی ایران ویژه کارمندان محترم.</p>
          </div>
          <div class="text-center p-8 rounded-2xl bg-zioto-green/50 border border-zioto-gold/20 hover:border-zioto-gold/40 transition-all">
            <div class="w-16 h-16 bg-zioto-gold/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            </div>
            <h3 class="text-xl font-bold text-zioto-gold mb-3">ارسال رایگان</h3>
            <p class="text-white/60 leading-7">ارسال رایگان و بیمه‌شده تمامی محصولات به سراسر کشور.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="container mx-auto px-4 py-16">
      <div class="bg-gradient-to-l from-zioto-green-light to-zioto-green rounded-3xl p-8 md:p-12 text-center border border-zioto-gold/20">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">آماده سرمایه‌گذاری هستید؟</h2>
        <p class="text-white/70 mb-8 max-w-xl mx-auto">همین الان شمش طلا یا نقره مورد نظرتان را با بهترین قیمت و امکان اقساط خریداری کنید.</p>
        <button onclick="document.getElementById('products-section').scrollIntoView({behavior:'smooth'})" class="btn-gold text-lg px-10 py-4">
          شروع خرید
        </button>
      </div>
    </section>
  `;
}

function renderProductCards(products) {
  return products.map(p => `
    <div class="product-card bg-zioto-green-dark/80 rounded-2xl overflow-hidden cursor-pointer w-[325px] mx-auto" onclick="navigateTo('product', PRODUCTS.find(p => p.slug === '${p.slug}'))">
      <div class="p-4 flex justify-center">
        <img src="${p.image}" alt="${p.name}" class="product-image w-[175px] aspect-[517/800] object-cover rounded-xl">
      </div>
      ${p.badge ? `<span class="badge badge-gold">${p.badge}</span>` : ''}
      <div class="px-5 pb-5">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-xs px-2 py-1 rounded-full ${p.category === 'gold' ? 'bg-zioto-gold/20 text-zioto-gold' : 'bg-white/10 text-white/60'}">عیار ${p.purity}</span>
        </div>
        <h3 class="text-lg font-bold text-white mb-2">${p.name}</h3>
        <div class="flex items-center justify-between">
          <div>
            <span class="text-zioto-gold font-bold text-xl">${formatPriceToman(p.price)}</span>
            <span class="price-original text-sm mr-2">${formatPriceToman(p.originalPrice)}</span>
          </div>
          <span class="text-xs text-green-400">%${getDiscountPercent(p.originalPrice, p.price)} تخفیف</span>
        </div>
        <button class="w-full mt-4 btn-gold text-sm py-3" onclick="event.stopPropagation(); navigateTo('product', PRODUCTS.find(p => p.slug === '${p.slug}'))">
          مشاهده و خرید
        </button>
      </div>
    </div>
  `).join('');
}

function filterProducts(category) {
  const filtered = category === 'all' ? PRODUCTS : PRODUCTS.filter(p => p.category === category);
  document.getElementById('products-grid').innerHTML = renderProductCards(filtered);

  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.classList.remove('active', 'bg-zioto-gold', 'text-zioto-green');
    btn.classList.add('bg-white/10', 'text-white/70');
  });

  const activeBtn = document.querySelector(`[data-filter="${category}"]`);
  activeBtn.classList.add('active', 'bg-zioto-gold', 'text-zioto-green');
  activeBtn.classList.remove('bg-white/10', 'text-white/70');
}
