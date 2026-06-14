// Zioto Landing Page - Main Application

// ==================== STATE ====================
let currentPage = 'home';
let selectedProduct = null;
let cart = [];
let selectedPaymentMethod = 'installment';
let activeProfileTab = 'orders';

// Auth state
let isLoggedIn = false;
let authStep = 'phone'; // phone, otp
let authPhone = '';
let otpCountdown = 0;
let otpTimer = null;

// Mock user data
let userData = {
  name: 'علی محمدی',
  phone: '۰۹۱۲۱۲۳۴۵۶۷',
  email: 'ali.mohammadi@email.com',
  nationalId: '۱۲۳۴۵۶۷۸۹۰',
  employeeId: 'BM-۱۲۳۴۵',
  joinDate: '۱۴۰۳/۰۳/۱۵',
  avatar: null
};

// Mock orders data
let ordersData = [
  {
    id: 'ZT-1A2B3C',
    date: '۱۴۰۴/۰۳/۱۰',
    status: 'success',
    statusText: 'تحویل شده',
    items: [
      { name: 'شمش طلای ۵ گرمی', quantity: 1, price: 17500000 }
    ],
    total: 17500000,
    paymentMethod: 'installment',
    trackingCode: '۱۲۳۴۵۶۷۸۹۰۱۲'
  },
  {
    id: 'ZT-4D5E6F',
    date: '۱۴۰۴/۰۲/۲۰',
    status: 'pending',
    statusText: 'در حال پردازش',
    items: [
      { name: 'شمش نقره ۱۰۰ گرمی', quantity: 2, price: 4200000 },
      { name: 'شمش طلای ۱ گرمی', quantity: 3, price: 3500000 }
    ],
    total: 18900000,
    paymentMethod: 'online',
    trackingCode: null
  },
  {
    id: 'ZT-7G8H9I',
    date: '۱۴۰۴/۰۱/۰۵',
    status: 'failed',
    statusText: 'ناموفق',
    items: [
      { name: 'شمش طلای ۱۰ گرمی', quantity: 1, price: 35000000 }
    ],
    total: 35000000,
    paymentMethod: 'installment',
    trackingCode: null,
    failReason: 'عدم تایید اطلاعات کارمندی'
  },
  {
    id: 'ZT-J1K2L3',
    date: '۱۴۰۳/۱۲/۱۸',
    status: 'success',
    statusText: 'تحویل شده',
    items: [
      { name: 'شمش طلای ۲.۵ گرمی', quantity: 2, price: 8750000 },
      { name: 'شمش نقره ۱ کیلوگرمی', quantity: 1, price: 40000000 }
    ],
    total: 57500000,
    paymentMethod: 'installment',
    trackingCode: '۹۸۷۶۵۴۳۲۱۰۱۲'
  },
  {
    id: 'ZT-M4N5O6',
    date: '۱۴۰۳/۱۱/۱۰',
    status: 'cancelled',
    statusText: 'لغو شده',
    items: [
      { name: 'شمش طلای ۲۰ گرمی', quantity: 1, price: 70000000 }
    ],
    total: 70000000,
    paymentMethod: 'online',
    trackingCode: null
  }
];

// Mock payments data
let paymentsData = [
  {
    id: 'PAY-001',
    orderId: 'ZT-1A2B3C',
    date: '۱۴۰۴/۰۳/۱۰',
    amount: 17500000,
    status: 'success',
    statusText: 'موفق',
    method: 'اقساطی - قسط اول',
    installments: { current: 1, total: 12 }
  },
  {
    id: 'PAY-002',
    orderId: 'ZT-1A2B3C',
    date: '۱۴۰۴/۰۴/۱۰',
    amount: 1458333,
    status: 'success',
    statusText: 'موفق',
    method: 'اقساطی - قسط دوم',
    installments: { current: 2, total: 12 }
  },
  {
    id: 'PAY-003',
    orderId: 'ZT-4D5E6F',
    date: '۱۴۰۴/۰۲/۲۰',
    amount: 18900000,
    status: 'pending',
    statusText: 'در انتظار',
    method: 'پرداخت آنلاین',
    installments: null
  },
  {
    id: 'PAY-004',
    orderId: 'ZT-7G8H9I',
    date: '۱۴۰۴/۰۱/۰۵',
    amount: 35000000,
    status: 'failed',
    statusText: 'ناموفق',
    method: 'اقساطی',
    installments: null
  },
  {
    id: 'PAY-005',
    orderId: 'ZT-J1K2L3',
    date: '۱۴۰۳/۱۲/۱۸',
    amount: 57500000,
    status: 'success',
    statusText: 'موفق',
    method: 'اقساطی - قسط اول',
    installments: { current: 1, total: 6 }
  },
  {
    id: 'PAY-006',
    orderId: 'ZT-1A2B3C',
    date: '۱۴۰۴/۰۵/۱۰',
    amount: 1458333,
    status: 'success',
    statusText: 'موفق',
    method: 'اقساطی - قسط سوم',
    installments: { current: 3, total: 12 }
  }
];

// ==================== ROUTING ====================
function navigateTo(page, data = null) {
  currentPage = page;
  if (data) {
    if (page === 'product') selectedProduct = data;
  }
  renderPage();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderPage() {
  const app = document.getElementById('app');
  switch (currentPage) {
    case 'home':
      app.innerHTML = renderHome();
      break;
    case 'about':
      app.innerHTML = renderAbout();
      break;
    case 'login':
      app.innerHTML = renderLogin();
      break;
    case 'profile':
      app.innerHTML = renderProfile();
      break;
    case 'product':
      app.innerHTML = renderProductDetail(selectedProduct);
      break;
    case 'cart':
      app.innerHTML = renderCart();
      break;
    case 'checkout':
      app.innerHTML = renderCheckout();
      break;
    case 'success':
      app.innerHTML = renderSuccess();
      break;
    default:
      app.innerHTML = renderHome();
  }
  updateCartCount();
  animateOnScroll();
}

// ==================== HOME PAGE ====================
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
      <div id="products-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
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
    <div class="product-card bg-zioto-green-dark/80 rounded-2xl overflow-hidden cursor-pointer" onclick="navigateTo('product', ${JSON.stringify(p).replace(/"/g, '&quot;')})">
      <div class="relative overflow-hidden">
        <img src="${p.image}" alt="${p.name}" class="product-image w-full h-56 object-cover">
        ${p.badge ? `<span class="badge badge-gold">${p.badge}</span>` : ''}
        <div class="absolute bottom-3 left-3 installment-badge px-3 py-1 rounded-full text-xs text-zioto-gold">
          ${p.installment}
        </div>
      </div>
      <div class="p-5">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-xs px-2 py-1 rounded-full ${p.category === 'gold' ? 'bg-zioto-gold/20 text-zioto-gold' : 'bg-white/10 text-white/60'}">${p.category === 'gold' ? 'طلای خالص' : 'نقره خالص'}</span>
          <span class="text-xs text-white/40">عیار ${p.purity}</span>
        </div>
        <h3 class="text-lg font-bold text-white mb-2">${p.name}</h3>
        <p class="text-sm text-white/50 mb-3">وزن: ${p.weight}</p>
        <div class="flex items-center justify-between">
          <div>
            <span class="text-zioto-gold font-bold text-xl">${formatPriceToman(p.price)}</span>
            <span class="price-original text-sm mr-2">${formatPriceToman(p.originalPrice)}</span>
          </div>
          <span class="text-xs text-green-400">%${getDiscountPercent(p.originalPrice, p.price)} تخفیف</span>
        </div>
        <button class="w-full mt-4 btn-gold text-sm py-3" onclick="event.stopPropagation(); navigateTo('product', ${JSON.stringify(p).replace(/"/g, '&quot;')})">
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

// ==================== PROFILE PAGE ====================
function renderProfile() {
  return `
    <section class="container mx-auto px-4 py-12">
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <div class="lg:col-span-1">
          <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/20 sticky top-24">
            <div class="text-center mb-6 pb-6 border-b border-white/10">
              <div class="w-20 h-20 bg-zioto-gold/20 rounded-full mx-auto mb-4 flex items-center justify-center">
                <svg class="w-10 h-10 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </div>
              <h3 class="font-bold text-white text-lg">${userData.name}</h3>
              <p class="text-sm text-zioto-gold">${userData.employeeId}</p>
              <p class="text-xs text-white/50 mt-1">عضویت: ${userData.joinDate}</p>
            </div>
            <nav class="space-y-2">
              <button onclick="switchProfileTab('orders')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${activeProfileTab === 'orders' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>سفارشات من</span>
              </button>
              <button onclick="switchProfileTab('payments')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${activeProfileTab === 'payments' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                <span>تاریخچه پرداخت</span>
              </button>
              <button onclick="switchProfileTab('installments')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${activeProfileTab === 'installments' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>اقساط فعال</span>
              </button>
              <button onclick="switchProfileTab('settings')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${activeProfileTab === 'settings' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>تنظیمات حساب</span>
              </button>
            </nav>
          </div>
        </div>
        <div class="lg:col-span-3">
          <div id="profile-content">
            ${renderProfileContent()}
          </div>
        </div>
      </div>
    </section>
  `;
}

function switchProfileTab(tab) {
  activeProfileTab = tab;
  renderPage();
}

function renderProfileContent() {
  switch (activeProfileTab) {
    case 'orders': return renderOrdersTab();
    case 'payments': return renderPaymentsTab();
    case 'installments': return renderInstallmentsTab();
    case 'settings': return renderSettingsTab();
    default: return renderOrdersTab();
  }
}

function renderOrdersTab() {
  const statusColors = {
    success: 'bg-green-500/20 text-green-400',
    pending: 'bg-yellow-500/20 text-yellow-400',
    failed: 'bg-red-500/20 text-red-400',
    cancelled: 'bg-white/10 text-white/50'
  };
  return `
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-white mb-2">سفارشات من</h2>
      <p class="text-white/50">تاریخچه تمامی سفارشات شما</p>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div class="bg-zioto-green-dark/80 rounded-xl p-4 border border-zioto-gold/10 text-center">
        <p class="text-2xl font-bold text-zioto-gold">${ordersData.length}</p>
        <p class="text-xs text-white/50">کل سفارشات</p>
      </div>
      <div class="bg-zioto-green-dark/80 rounded-xl p-4 border border-zioto-gold/10 text-center">
        <p class="text-2xl font-bold text-green-400">${ordersData.filter(o => o.status === 'success').length}</p>
        <p class="text-xs text-white/50">تحویل شده</p>
      </div>
      <div class="bg-zioto-green-dark/80 rounded-xl p-4 border border-zioto-gold/10 text-center">
        <p class="text-2xl font-bold text-yellow-400">${ordersData.filter(o => o.status === 'pending').length}</p>
        <p class="text-xs text-white/50">در حال پردازش</p>
      </div>
      <div class="bg-zioto-green-dark/80 rounded-xl p-4 border border-zioto-gold/10 text-center">
        <p class="text-2xl font-bold text-red-400">${ordersData.filter(o => o.status === 'failed' || o.status === 'cancelled').length}</p>
        <p class="text-xs text-white/50">ناموفق/لغو</p>
      </div>
    </div>
    <div class="space-y-4">
      ${ordersData.map(order => `
        <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/10 hover:border-zioto-gold/20 transition-all">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
              <div class="flex items-center gap-3 mb-1">
                <span class="font-bold text-white">${order.id}</span>
                <span class="px-2 py-0.5 rounded-full text-xs ${statusColors[order.status]}">${order.statusText}</span>
              </div>
              <p class="text-sm text-white/50">${order.date}</p>
            </div>
            <div class="text-left">
              <p class="text-zioto-gold font-bold">${formatPriceToman(order.total)}</p>
              <p class="text-xs text-white/50">${order.paymentMethod === 'installment' ? 'اقساطی' : 'نقدی'}</p>
            </div>
          </div>
          <div class="bg-zioto-green/30 rounded-xl p-4 mb-4">
            ${order.items.map(item => `
              <div class="flex justify-between items-center py-2 ${order.items.indexOf(item) < order.items.length - 1 ? 'border-b border-white/5' : ''}">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-zioto-gold/10 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                  </div>
                  <div>
                    <p class="text-sm text-white">${item.name}</p>
                    <p class="text-xs text-white/50">${toPersianNum(item.quantity)} × ${formatPriceToman(item.price)}</p>
                  </div>
                </div>
                <p class="text-sm text-zioto-gold">${formatPriceToman(item.price * item.quantity)}</p>
              </div>
            `).join('')}
          </div>
          <div class="flex flex-wrap gap-3">
            ${order.status === 'success' ? `
              <button onclick="showNotification('کد رهگیری: ${order.trackingCode}')" class="text-sm px-4 py-2 bg-zioto-gold/10 text-zioto-gold rounded-lg hover:bg-zioto-gold/20 transition-colors">کد رهگیری</button>
              <button onclick="showNotification('در حال دانلود فاکتور...')" class="text-sm px-4 py-2 bg-white/5 text-white/70 rounded-lg hover:bg-white/10 transition-colors">دانلود فاکتور</button>
            ` : ''}
            ${order.status === 'pending' ? `
              <button onclick="showNotification('در حال بروزرسانی وضعیت...')" class="text-sm px-4 py-2 bg-zioto-gold/10 text-zioto-gold rounded-lg hover:bg-zioto-gold/20 transition-colors">پیگیری سفارش</button>
            ` : ''}
            ${order.status === 'failed' ? `
              <button onclick="showNotification('در حال انتقال به درگاه پرداخت...')" class="text-sm px-4 py-2 bg-zioto-gold/10 text-zioto-gold rounded-lg hover:bg-zioto-gold/20 transition-colors">پرداخت مجدد</button>
            ` : ''}
            ${order.status === 'cancelled' ? `
              <button onclick="showNotification('در حال انتقال...')" class="text-sm px-4 py-2 bg-zioto-gold/10 text-zioto-gold rounded-lg hover:bg-zioto-gold/20 transition-colors">سفارش مجدد</button>
            ` : ''}
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

function renderPaymentsTab() {
  const statusColors = { success: 'bg-green-500/20 text-green-400', pending: 'bg-yellow-500/20 text-yellow-400', failed: 'bg-red-500/20 text-red-400' };
  const totalPaid = paymentsData.filter(p => p.status === 'success').reduce((sum, p) => sum + p.amount, 0);
  const totalPending = paymentsData.filter(p => p.status === 'pending').reduce((sum, p) => sum + p.amount, 0);
  const totalFailed = paymentsData.filter(p => p.status === 'failed').reduce((sum, p) => sum + p.amount, 0);
  return `
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-white mb-2">تاریخچه پرداخت</h2>
      <p class="text-white/50">لیست تمامی تراکنش‌های مالی شما</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
      <div class="bg-gradient-to-br from-green-500/20 to-green-600/10 rounded-xl p-5 border border-green-500/20">
        <p class="text-sm text-green-400 mb-1">پرداخت موفق</p>
        <p class="text-2xl font-bold text-green-400">${formatPriceToman(totalPaid)}</p>
      </div>
      <div class="bg-gradient-to-br from-yellow-500/20 to-yellow-600/10 rounded-xl p-5 border border-yellow-500/20">
        <p class="text-sm text-yellow-400 mb-1">در انتظار</p>
        <p class="text-2xl font-bold text-yellow-400">${formatPriceToman(totalPending)}</p>
      </div>
      <div class="bg-gradient-to-br from-red-500/20 to-red-600/10 rounded-xl p-5 border border-red-500/20">
        <p class="text-sm text-red-400 mb-1">ناموفق</p>
        <p class="text-2xl font-bold text-red-400">${formatPriceToman(totalFailed)}</p>
      </div>
    </div>
    <div class="bg-zioto-green-dark/80 rounded-2xl border border-zioto-gold/10 overflow-hidden">
      ${paymentsData.map(payment => `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 border-b border-white/5 hover:bg-white/5 transition-colors">
          <div>
            <span class="text-sm text-white">${payment.id}</span>
            <span class="text-xs text-white/50 block">${payment.date}</span>
          </div>
          <div class="text-sm text-zioto-gold font-bold">${formatPriceToman(payment.amount)}</div>
          <div class="text-sm text-white/70">${payment.method}</div>
          <div>
            <span class="px-2 py-1 rounded-full text-xs ${statusColors[payment.status]}">${payment.statusText}</span>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

function renderInstallmentsTab() {
  return `
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-white mb-2">اقساط فعال</h2>
      <p class="text-white/50">مدیریت اقساط در حال پرداخت شما</p>
    </div>
    <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/10 text-center py-16">
      <svg class="w-16 h-16 text-white/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
      <p class="text-white/50">اقساط فعالی وجود ندارد</p>
    </div>
  `;
}

function renderSettingsTab() {
  return `
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-white mb-2">تنظیمات حساب</h2>
      <p class="text-white/50">مدیریت اطلاعات شخصی و امنیت حساب</p>
    </div>
    <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/20 mb-6">
      <h3 class="text-lg font-bold text-white mb-6">اطلاعات شخصی</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">نام و نام خانوادگی</label>
          <input type="text" class="form-input" value="${userData.name}">
        </div>
        <div>
          <label class="form-label">شماره موبایل</label>
          <input type="tel" class="form-input" value="۰۹۱۲۱۲۳۴۵۶۷" dir="ltr">
        </div>
        <div>
          <label class="form-label">ایمیل</label>
          <input type="email" class="form-input" value="${userData.email}" dir="ltr">
        </div>
        <div>
          <label class="form-label">کد ملی</label>
          <input type="text" class="form-input" value="${userData.nationalId}" dir="ltr" disabled>
        </div>
      </div>
      <button onclick="showNotification('اطلاعات با موفقیت بروزرسانی شد')" class="btn-gold mt-6 px-6 py-2">ذخیره تغییرات</button>
    </div>
    <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/20">
      <h3 class="text-lg font-bold text-white mb-6">تغییر رمز عبور</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">رمز عبور فعلی</label>
          <input type="password" class="form-input" placeholder="••••••••" dir="ltr">
        </div>
        <div>
          <label class="form-label">رمز عبور جدید</label>
          <input type="password" class="form-input" placeholder="••••••••" dir="ltr">
        </div>
      </div>
      <button onclick="showNotification('رمز عبور با موفقیت تغییر کرد')" class="btn-gold mt-6 px-6 py-2">تغییر رمز عبور</button>
    </div>
  `;
}

// ==================== AUTH BUTTONS ====================
function updateAuthButtons() {
  const container = document.getElementById('auth-buttons');
  if (!container) return;
  if (isLoggedIn) {
    container.innerHTML = `
      <button onclick="navigateTo('profile')" class="flex items-center gap-2 p-2 text-white/80 hover:text-zioto-gold transition-colors">
        <div class="w-8 h-8 bg-zioto-gold/20 rounded-full flex items-center justify-center">
          <svg class="w-5 h-5 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        <span class="hidden md:inline text-sm">${userData.name.split(' ')[0]}</span>
      </button>
    `;
  } else {
    container.innerHTML = `<button onclick="navigateTo('login')" class="btn-gold text-sm px-4 py-2">ورود / ثبت‌نام</button>`;
  }
}

function logout() {
  isLoggedIn = false;
  authStep = 'phone';
  authPhone = '';
  updateAuthButtons();
  showNotification('با موفقیت خارج شدید');
  navigateTo('home');
}

// ==================== LOGIN PAGE ====================
function renderLogin() {
  if (isLoggedIn) { navigateTo('profile'); return ''; }
  return `
    <section class="min-h-[80vh] flex items-center justify-center px-4 py-12">
      <div class="w-full max-w-md">
        <div class="text-center mb-8">
          <div class="w-16 h-16 bg-zioto-gold/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <span class="text-zioto-gold text-2xl font-bold">Z</span>
          </div>
          <h1 class="text-2xl font-bold text-white mb-2">
            ${authStep === 'phone' ? 'ورود به حساب کاربری' : 'تایید شماره موبایل'}
          </h1>
          <p class="text-white/50 text-sm">
            ${authStep === 'phone' ? 'شماره موبایل خود را وارد کنید' : `کد ۶ رقمی ارسال شده به ${formatPhoneDisplay(authPhone)} را وارد کنید`}
          </p>
        </div>
        <div class="bg-zioto-green-dark/80 rounded-2xl p-8 border border-zioto-gold/20 login-card">
          ${authStep === 'phone' ? renderPhoneStep() : renderOTPStep()}
        </div>
        <div class="text-center mt-6">
          <p class="text-white/40 text-xs">با ورود به سایت، شرایط و قوانین زیوتو را می‌پذیرید.</p>
        </div>
      </div>
    </section>
  `;
}

function renderPhoneStep() {
  return `
    <form onsubmit="sendOTP(event)">
      <div class="mb-6">
        <label class="form-label">شماره موبایل</label>
        <input type="tel" class="form-input pl-12 text-left" placeholder="۰۹۱۲XXXXXXX" id="login-phone" maxlength="11" dir="ltr" required>
      </div>
      <div class="mb-6">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" id="agree-terms" class="w-4 h-4 accent-zioto-gold" required>
          <span class="text-sm text-white/60">شرایط و قوانین استفاده را مطالعه کرده و می‌پذیرم</span>
        </label>
      </div>
      <button type="submit" class="btn-gold w-full py-4 text-lg">ارسال کد تایید</button>
      <div class="relative my-8">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-white/10"></div></div>
        <div class="relative flex justify-center text-sm"><span class="px-4 bg-zioto-green-dark text-white/40">یا</span></div>
      </div>
      <button type="button" onclick="showNotification('ورود با شماره کارمندی به زودی فعال می‌شود', 'info')" class="w-full px-4 py-2 bg-zioto-gold/20 text-zioto-gold rounded-lg text-sm hover:bg-zioto-gold/30 transition-colors">
        ورود با شماره کارمندی
      </button>
    </form>
  `;
}

function renderOTPStep() {
  return `
    <form onsubmit="verifyOTP(event)">
      <div class="mb-6">
        <label class="form-label text-center block mb-4">کد ۶ رقمی تایید</label>
        <div class="flex justify-center gap-2" dir="ltr">
          ${[0,1,2,3,4,5].map(i => `
            ${i === 3 ? '<span class="text-zioto-gold self-center text-xl font-bold">-</span>' : ''}
            <input type="text" maxlength="1" class="otp-input w-12 h-14 text-center text-xl font-bold bg-zioto-green/50 border-2 border-white/20 rounded-xl text-white focus:border-zioto-gold focus:outline-none transition-colors" data-index="${i}" oninput="handleOTPInput(this, ${i})" onkeydown="handleOTPKeydown(event, ${i})">
          `).join('')}
        </div>
      </div>
      <div class="text-center mb-6">
        ${otpCountdown > 0 ? `<p class="text-white/50 text-sm">ارسال مجدد کد تا <span id="otp-countdown" class="text-zioto-gold font-bold">${toPersianNum(otpCountdown)}</span> ثانیه دیگر</p>` : `<button type="button" onclick="resendOTP()" class="text-zioto-gold text-sm hover:text-zioto-gold-light transition-colors">ارسال مجدد کد تایید</button>`}
      </div>
      <button type="submit" class="btn-gold w-full py-4 text-lg">تایید و ورود</button>
      <button type="button" onclick="goToPhoneStep()" class="w-full mt-4 text-white/50 hover:text-white text-sm transition-colors">تغییر شماره موبایل</button>
    </form>
  `;
}

// ==================== AUTH FUNCTIONS ====================
function formatPhoneDisplay(phone) {
  if (!phone) return '';
  const cleaned = phone.replace(/\D/g, '');
  if (cleaned.length === 11) return `${cleaned.slice(0, 4)}***${cleaned.slice(7)}`;
  return phone;
}

function sendOTP(e) {
  e.preventDefault();
  const phoneInput = document.getElementById('login-phone');
  const phone = phoneInput.value.replace(/\D/g, '');
  if (phone.length !== 11 || !phone.startsWith('09')) {
    showNotification('شماره موبایل معتبر نیست', 'error');
    return;
  }
  authPhone = phone;
  authStep = 'otp';
  renderPage();
  startOTPCountdown();
  setTimeout(() => { const firstInput = document.querySelector('.otp-input'); if (firstInput) firstInput.focus(); }, 100);
  showNotification('کد تایید ارسال شد', 'success');
}

function handleOTPInput(input, index) {
  input.value = input.value.replace(/[^0-9]/g, '');
  if (input.value && index < 5) {
    const nextInput = document.querySelector(`[data-index="${index + 1}"]`);
    if (nextInput) nextInput.focus();
  }
  const allInputs = document.querySelectorAll('.otp-input');
  const otp = Array.from(allInputs).map(i => i.value).join('');
  if (otp.length === 6) setTimeout(() => verifyOTP(new Event('submit')), 200);
}

function handleOTPKeydown(e, index) {
  if (e.key === 'Backspace' && !e.target.value && index > 0) {
    const prevInput = document.querySelector(`[data-index="${index - 1}"]`);
    if (prevInput) { prevInput.focus(); prevInput.value = ''; }
  }
}

function verifyOTP(e) {
  e.preventDefault();
  const allInputs = document.querySelectorAll('.otp-input');
  const otp = Array.from(allInputs).map(i => i.value).join('');
  if (otp.length !== 6) { showNotification('لطفاً کد ۶ رقمی را کامل وارد کنید', 'error'); return; }
  showNotification('در حال تایید...', 'info');
  setTimeout(() => {
    isLoggedIn = true;
    authStep = 'phone';
    authPhone = '';
    updateAuthButtons();
    showNotification(`خوش آمدید ${userData.name}!`, 'success');
    navigateTo('home');
  }, 1000);
}

function resendOTP() {
  if (otpCountdown > 0) return;
  showNotification('کد تایید جدید ارسال شد', 'success');
  startOTPCountdown();
}

function startOTPCountdown() {
  otpCountdown = 60;
  if (otpTimer) clearInterval(otpTimer);
  otpTimer = setInterval(() => {
    otpCountdown--;
    const countdownEl = document.getElementById('otp-countdown');
    if (countdownEl) countdownEl.textContent = toPersianNum(otpCountdown);
    if (otpCountdown <= 0) { clearInterval(otpTimer); otpTimer = null; renderPage(); }
  }, 1000);
}

function goToPhoneStep() {
  authStep = 'phone';
  renderPage();
}

// ==================== ABOUT PAGE ====================
function renderAbout() {
  return `
    <section class="hero-gradient relative overflow-hidden py-20">
      <div class="container mx-auto px-4 relative z-10">
        <div class="text-center max-w-3xl mx-auto">
          <span class="inline-block mb-4 bg-zioto-gold/20 text-zioto-gold px-4 py-2 rounded-full text-sm font-medium border border-zioto-gold/30">درباره زیوتو</span>
          <h1 class="text-4xl md:text-5xl font-black mb-6"><span class="gold-shimmer">داستان ما</span></h1>
          <p class="text-lg text-white/70 leading-8">زیوتو با هدف ارائه بهترین خدمات در حوزه خرید و فروش شمش طلا و نقره تاسیس شد</p>
        </div>
      </div>
    </section>
    <section class="container mx-auto px-4 py-16">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-20">
        <div>
          <h2 class="text-3xl font-bold text-white mb-6">چرا زیوتو؟</h2>
          <p class="text-white/70 leading-8 mb-6">زیوتو با بیش از یک دهه تجربه در حوزه فلزات گران‌بها، امروز به عنوان یکی از معتبرترین مراکز فروش شمش طلا و نقره در ایران شناخته می‌شود.</p>
          <p class="text-white/70 leading-8 mb-6">همکاری ما با بانک ملی ایران این امکان را فراهم کرده تا کارمندان محترم این بانک بتوانند شمش طلا و نقره مورد نیاز خود را به صورت اقساطی و با شرایط ویژه خریداری کنند.</p>
        </div>
        <div class="bg-gradient-to-br from-zioto-green-light to-zioto-green rounded-3xl p-8 border border-zioto-gold/20">
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-zioto-green-dark/50 rounded-2xl p-6 text-center">
              <p class="text-3xl font-black text-zioto-gold mb-2">۹۹۹.۹</p>
              <p class="text-sm text-white/60">عیار طلا</p>
            </div>
            <div class="bg-zioto-green-dark/50 rounded-2xl p-6 text-center">
              <p class="text-3xl font-black text-zioto-gold mb-2">۹۹۹</p>
              <p class="text-sm text-white/60">عیار نقره</p>
            </div>
            <div class="bg-zioto-green-dark/50 rounded-2xl p-6 text-center">
              <p class="text-3xl font-black text-zioto-gold mb-2">۱۲</p>
              <p class="text-sm text-white/60">ماه اقساط</p>
            </div>
            <div class="bg-zioto-green-dark/50 rounded-2xl p-6 text-center">
              <p class="text-3xl font-black text-zioto-gold mb-2">۰%</p>
              <p class="text-sm text-white/60">سود اقساط</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  `;
}

// ==================== PRODUCT DETAIL ====================
function renderProductDetail(product) {
  if (!product) return renderHome();
  return `
    <section class="container mx-auto px-4 py-12">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <div class="relative">
          <img src="${product.image}" alt="${product.name}" class="w-full rounded-2xl border border-zioto-gold/20">
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
            <button onclick="addToCart(${JSON.stringify(product).replace(/"/g, '&quot;')})" class="btn-gold flex-1 text-lg py-4">افزودن به سبد خرید</button>
            <button onclick="buyNow(${JSON.stringify(product).replace(/"/g, '&quot;')})" class="btn-outline-gold flex-1 text-lg py-4">خرید فوری</button>
          </div>
        </div>
      </div>
    </section>
  `;
}

let quantity = 1;
function updateQuantity(change) {
  quantity = Math.max(1, quantity + change);
  document.getElementById('product-quantity').textContent = toPersianNum(quantity);
}

function toPersianNum(num) {
  const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
  return num.toString().replace(/\d/g, d => persianDigits[d]);
}

// ==================== CART ====================
function addToCart(product) {
  const existing = cart.find(item => item.id === product.id);
  if (existing) { existing.quantity += quantity; } else { cart.push({ ...product, quantity: quantity }); }
  quantity = 1;
  updateCartCount();
  showNotification('محصول به سبد خرید اضافه شد');
}

function buyNow(product) {
  addToCart(product);
  navigateTo('cart');
}

function removeFromCart(productId) {
  cart = cart.filter(item => item.id !== productId);
  updateCartCount();
  renderPage();
}

function updateCartItemQuantity(productId, change) {
  const item = cart.find(item => item.id === productId);
  if (item) { item.quantity = Math.max(1, item.quantity + change); renderPage(); }
}

function getCartTotal() { return cart.reduce((sum, item) => sum + (item.price * item.quantity), 0); }
function getCartCount() { return cart.reduce((sum, item) => sum + item.quantity, 0); }

function updateCartCount() {
  const count = getCartCount();
  const countEl = document.getElementById('cart-count');
  if (count > 0) { countEl.textContent = count; countEl.classList.remove('hidden'); }
  else { countEl.classList.add('hidden'); }
}

function renderCart() {
  if (cart.length === 0) {
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
          ${cart.map(item => `
            <div class="cart-item bg-zioto-green-dark/80 rounded-xl p-4 flex gap-4">
              <img src="${item.image}" alt="${item.name}" class="w-24 h-24 object-cover rounded-lg">
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
          <div class="bg-zioto-green-dark/80 rounded-2xl p-6 border border-zioto-gold/20 sticky top-24">
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

// ==================== CHECKOUT ====================
function renderCheckout() {
  if (cart.length === 0) { navigateTo('cart'); return ''; }
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
              <div class="payment-method active" onclick="selectedPaymentMethod='installment'">
                <div class="flex items-center gap-3">
                  <div class="w-5 h-5 rounded-full border-2 border-zioto-gold flex items-center justify-center"><div class="w-3 h-3 rounded-full bg-zioto-gold"></div></div>
                  <div><p class="font-bold text-white">خرید اقساطی</p><p class="text-sm text-white/50">بانک ملی ایران</p></div>
                </div>
              </div>
              <div class="payment-method" onclick="selectedPaymentMethod='online'">
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
              ${cart.map(item => `
                <div class="flex gap-3">
                  <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded-lg">
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

// ==================== SUCCESS PAGE ====================
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
          <button onclick="cart = []; navigateTo('home')" class="btn-gold px-8 py-3">بازگشت به فروشگاه</button>
        </div>
      </div>
    </section>
  `;
}

// ==================== UTILITIES ====================
function toggleMobileMenu() {
  const menu = document.getElementById('mobile-menu');
  menu.classList.toggle('hidden');
}

function showNotification(message, type = 'success') {
  const notification = document.createElement('div');
  notification.className = `fixed top-24 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-xl shadow-2xl text-sm font-medium transition-all transform translate-y-0 opacity-100 ${
    type === 'error' ? 'bg-red-500/90 text-white' :
    type === 'info' ? 'bg-blue-500/90 text-white' :
    'bg-zioto-gold/90 text-zioto-green'
  }`;
  notification.textContent = message;
  document.body.appendChild(notification);
  setTimeout(() => {
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-20px)';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

function animateOnScroll() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('fade-in-up'); });
  }, { threshold: 0.1 });
  document.querySelectorAll('.product-card, section > div').forEach(el => observer.observe(el));
}

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', () => {
  renderPage();
  updateAuthButtons();
  window.addEventListener('scroll', () => {
    const header = document.getElementById('main-header');
    if (window.scrollY > 50) {
      header.classList.add('bg-zioto-green-dark/95', 'backdrop-blur-sm', 'shadow-lg');
    } else {
      header.classList.remove('bg-zioto-green-dark/95', 'backdrop-blur-sm', 'shadow-lg');
    }
  });
});
