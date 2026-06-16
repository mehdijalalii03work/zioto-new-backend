<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'زیوتو | خرید شمش طلا و نقره اقساطی ویژه کارمندان بانک ملی')</title>
  <meta name="description" content="@yield('description', 'خرید شمش طلا و نقره با بهترین قیمت و امکان خرید اقساطی ویژه مشتریان و کارمندان بانک ملی ایران')">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/landing/custom.css') }}">
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            zioto: {
              green: '#1B4332',
              'green-light': '#2D6A4F',
              'green-dark': '#0F2B1F',
              gold: '#C8A84E',
              'gold-light': '#D4B96A',
              'gold-dark': '#A8893E',
            }
          },
          fontFamily: {
            vazir: ['Vazirmatn', 'sans-serif'],
          }
        }
      }
    }
  </script>
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.store('cart', {
        items: JSON.parse(localStorage.getItem('cart') || '[]'),

        _persist() {
          localStorage.setItem('cart', JSON.stringify(this.items));
          this.updateBadge();
        },

        add(product, qty = 1) {
          const idx = this.items.findIndex(i => i.id === product.id);
          if (idx >= 0) {
            this.items[idx] = { ...this.items[idx], quantity: this.items[idx].quantity + qty };
          } else {
            this.items = [...this.items, { ...product, quantity: qty }];
          }
          this._persist();
          if (typeof showNotification === 'function') {
            showNotification('محصول به سبد خرید اضافه شد');
          }
        },

        remove(id) {
          this.items = this.items.filter(i => i.id !== id);
          this._persist();
        },

        updateQuantity(id, change) {
          const idx = this.items.findIndex(i => i.id === id);
          if (idx >= 0) {
            const newQty = Math.max(1, this.items[idx].quantity + change);
            this.items[idx] = { ...this.items[idx], quantity: newQty };
            this._persist();
          }
        },

        get total() {
          return this.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
        },

        get count() {
          return this.items.reduce((sum, i) => sum + i.quantity, 0);
        },

        clear() {
          this.items = [];
          this._persist();
        },

        updateBadge() {
          const el = document.getElementById('cart-count');
          if (!el) return;
          const c = this.count;
          if (c > 0) {
            el.textContent = typeof toPersianNum === 'function' ? toPersianNum(c) : c;
            el.classList.remove('hidden');
          } else {
            el.classList.add('hidden');
          }
        },
      });

      Alpine.store('auth', {
        isLoggedIn: false,
        user: null,

        async check() {
          try {
            const res = await fetch('/api/profile', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('not authenticated');
            const data = await res.json();
            if (data?.user) {
              this.isLoggedIn = true;
              this.user = data.user;
            }
          } catch (e) {
            this.isLoggedIn = false;
            this.user = null;
          }
        },

        async logout() {
          try {
            await fetch('/api/auth/logout', { method: 'POST', headers: { 'Accept': 'application/json' } });
          } catch (e) {}
          this.isLoggedIn = false;
          this.user = null;
          if (typeof showNotification === 'function') {
            showNotification('با موفقیت خارج شدید');
          }
          window.location.href = '/';
        },
      });

      Alpine.store('auth').check();
    });
  </script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#111318] font-vazir text-white min-h-screen">

  <header x-data="{ scrolled: false }"
          x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 50)"
          :class="scrolled ? 'bg-[#111318]/95 backdrop-blur-sm shadow-lg' : ''"
          class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 border-b border-white/5">
    <div class="container mx-auto px-4 py-4 flex items-center justify-between">
      <div class="flex items-center gap-8">
        <a href="{{ route('landing.home') }}">
          <img src="{{ asset('images/zioto-logo.png') }}" alt="ZIOTO" class="h-3">
        </a>
        <nav class="hidden md:flex items-center gap-1">
          <a href="{{ route('landing.home') }}"
             class="px-4 py-2 rounded-lg text-sm transition-all {{ request()->routeIs('landing.home') ? 'bg-zioto-gold/15 text-zioto-gold' : 'text-white/70 hover:text-white hover:bg-white/5' }}">
            خانه
          </a>
          <a href="{{ route('landing.about') }}"
             class="px-4 py-2 rounded-lg text-sm transition-all {{ request()->routeIs('landing.about') ? 'bg-zioto-gold/15 text-zioto-gold' : 'text-white/70 hover:text-white hover:bg-white/5' }}">
            درباره ما
          </a>
        </nav>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('landing.cart') }}" class="relative p-2.5 rounded-lg text-white/70 hover:text-white hover:bg-white/5 transition-all">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
          </svg>
          <span id="cart-count" class="absolute -top-0.5 -right-0.5 bg-zioto-gold text-[#111318] text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center hidden">0</span>
        </a>
        <template x-if="$store.auth.isLoggedIn">
          <a :href="$store.auth.user?.is_admin ? '/admin' : '{{ route('landing.profile') }}'" class="flex items-center gap-2 p-2 rounded-lg text-white/70 hover:text-white hover:bg-white/5 transition-all">
            <div class="w-7 h-7 bg-white/10 rounded-full flex items-center justify-center">
              <svg class="w-4 h-4 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <span class="hidden md:inline text-sm" x-text="$store.auth.user?.name || 'کاربر'"></span>
          </a>
        </template>
        <template x-if="!$store.auth.isLoggedIn">
          <a href="{{ route('landing.login') }}" class="btn-gold text-sm px-4 py-2 rounded-lg">ورود / ثبت‌نام</a>
        </template>
        <button class="md:hidden p-2.5 rounded-lg text-white/70 hover:text-white hover:bg-white/5 transition-all" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>
    <div id="mobile-menu" class="hidden md:hidden bg-[#111318] border-t border-white/5">
      <div class="container mx-auto px-4 py-3 flex flex-col">
        <a href="{{ route('landing.home') }}" onclick="document.getElementById('mobile-menu').classList.add('hidden')"
           class="flex items-center gap-3 rounded-lg px-4 py-3 transition-all {{ request()->routeIs('landing.home') ? 'bg-zioto-gold/15 text-zioto-gold' : 'text-white/70 hover:text-white hover:bg-white/5' }}">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          <span class="text-sm">خانه</span>
        </a>
        <a href="{{ route('landing.about') }}" onclick="document.getElementById('mobile-menu').classList.add('hidden')"
           class="flex items-center gap-3 rounded-lg px-4 py-3 transition-all {{ request()->routeIs('landing.about') ? 'bg-zioto-gold/15 text-zioto-gold' : 'text-white/70 hover:text-white hover:bg-white/5' }}">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span class="text-sm">درباره ما</span>
        </a>
        <a href="{{ route('landing.cart') }}" onclick="document.getElementById('mobile-menu').classList.add('hidden')"
           class="flex items-center gap-3 rounded-lg px-4 py-3 transition-all {{ request()->routeIs('landing.cart') ? 'bg-zioto-gold/15 text-zioto-gold' : 'text-white/70 hover:text-white hover:bg-white/5' }}">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
          <span class="text-sm">سبد خرید</span>
        </a>
      </div>
    </div>
  </header>

  <main class="pt-20">
    @yield('content')
  </main>

  <footer class="bg-[#0D0F13] border-t border-zioto-gold/20 mt-20">
    <div class="container mx-auto px-4 py-12">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8">
        <div>
          <div class="mb-4">
            <span class="text-zioto-gold text-xl font-bold">زیوتو</span>
          </div>
          <p class="text-zioto-gold/80 text-sm mb-3 italic">زیربنای ثروت و سرمایه تو</p>
          <p class="text-white/60 text-xs leading-6">
            عضو شرکت‌های فناور پارک علم و فناوری پردیس
          </p>
        </div>
        <div class="hidden sm:block">
          <h3 class="text-zioto-gold font-bold mb-4">دسترسی سریع</h3>
          <ul class="space-y-2 text-sm text-white/80">
            <li><a href="#" class="hover:text-zioto-gold transition-colors">شرایط اقساط</a></li>
            <li><a href="#" class="hover:text-zioto-gold transition-colors">سوالات متداول</a></li>
          </ul>
        </div>
        <div>
          <h3 class="text-zioto-gold font-bold mb-4">تماس با ما</h3>
          <ul class="space-y-3 text-sm text-white/80">
            <li class="flex items-start gap-2">
              <svg class="w-4 h-4 text-zioto-gold shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <span class="leading-6">تهران، میدان ولیعصر، خیابان کریمخان زند، مجتمع الماس کریمخان، طبقه اول اداری، واحد ۱۰۹</span>
            </li>
            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg><span dir="ltr">۰۲۱-۸۶۰۳۸۴۳۲</span></span>
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg><span dir="ltr">۰۲۱۹۱۳۰۸۷۰۷</span></span>
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg><span dir="ltr">۰۹۱۰۵۹۰۵۱۷۷</span></span>
            </li>
          </ul>
        </div>
        <div>
          <h3 class="text-zioto-gold font-bold mb-4">نمادهای اعتماد</h3>
          <div class="flex gap-4">
{{--            <a target="_blank" href="https://trustseal.enamad.ir/?id=602261&Code=zOehq8kZr8PVLm4qwxyDPYrSnMQla0go" class="bg-white rounded-xl p-3 hover:shadow-lg hover:shadow-zioto-gold/10 transition-all">--}}
{{--              <img referrerpolicy="origin" src="https://trustseal.enamad.ir/logo.aspx?id=602261&Code=zOehq8kZr8PVLm4qwxyDPYrSnMQla0go" alt="اینماد" style="cursor:pointer" code="zOehq8kZr8PVLm4qwxyDPYrSnMQla0go" class="h-20">--}}
{{--            </a>--}}
            <a href="https://logo.samandehi.ir/Verify.aspx?id=372622&p=xlaojyoeuiwkgvkauiwkuiwk" target="_blank" rel="noopener noreferrer" class="bg-white rounded-xl p-3 hover:shadow-lg hover:shadow-zioto-gold/10 transition-all">
              <img src="{{ asset('images/samandehi-logo.jpg') }}" alt="ساماندهی" class="h-20">
            </a>
          </div>
        </div>
      </div>
      <div class="border-t border-zioto-gold/20 mt-8 pt-8 text-center text-white/50 text-xs">
        <p>© ۱۴۰۵ تمامی حقوق متعلق به شرکت صنعتی بازرگانی فناوران بین الملل ساویس می‌باشد.</p>
      </div>
    </div>
  </footer>

  <script src="{{ asset('js/landing/data.js') }}"></script>
  @stack('scripts')
</body>
</html>
