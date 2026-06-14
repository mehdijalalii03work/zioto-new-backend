<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>زیوتو | خرید شمش طلا و نقره اقساطی ویژه کارمندان بانک ملی</title>
  <meta name="description" content="خرید شمش طلا و نقره با بهترین قیمت و امکان خرید اقساطی ویژه مشتریان و کارمندان بانک ملی ایران">
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
</head>
<body class="bg-zioto-green font-vazir text-white min-h-screen">

  <header id="main-header" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-zioto-gold text-2xl font-bold tracking-tight">ZIOTO</span>
      </div>
      <nav class="hidden md:flex items-center gap-6 text-sm">
        <a href="#" onclick="navigateTo('home'); return false;" class="text-white/80 hover:text-zioto-gold transition-colors">خانه</a>
        <a href="#" onclick="navigateTo('about'); return false;" class="text-white/80 hover:text-zioto-gold transition-colors">درباره ما</a>
      </nav>
      <div class="flex items-center gap-4">
        <button onclick="navigateTo('cart')" class="relative p-2 text-white/80 hover:text-zioto-gold transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
          </svg>
          <span id="cart-count" class="absolute -top-1 -right-1 bg-zioto-gold text-zioto-green text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center hidden">0</span>
        </button>
        <div id="auth-buttons"></div>
        <button class="md:hidden p-2 text-white/80" onclick="toggleMobileMenu()">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>
    <div id="mobile-menu" class="hidden md:hidden bg-zioto-green-dark/95 backdrop-blur-sm border-t border-white/10">
      <div class="container mx-auto px-4 py-4 flex flex-col gap-3">
        <a href="#" onclick="navigateTo('home'); toggleMobileMenu(); return false;" class="text-white/80 hover:text-zioto-gold transition-colors py-2">خانه</a>
        <a href="#" onclick="navigateTo('about'); toggleMobileMenu(); return false;" class="text-white/80 hover:text-zioto-gold transition-colors py-2">درباره ما</a>
      </div>
    </div>
  </header>

  <main id="app" class="pt-20"></main>

  <footer class="bg-zioto-green-dark border-t border-white/10 mt-20">
    <div class="container mx-auto px-4 py-12">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
        <div>
          <div class="flex items-center gap-2 mb-4">
            <span class="text-zioto-gold text-xl font-bold">ZIOTO</span>
          </div>
          <p class="text-white/60 text-sm leading-7">
            زیوتو، معتبرترین مرکز فروش شمش طلا و نقره با امکان خرید اقساطی ویژه مشتریان و کارمندان بانک ملی ایران.
          </p>
        </div>
        <div>
          <h3 class="text-zioto-gold font-bold mb-4">دسترسی سریع</h3>
          <ul class="space-y-2 text-sm text-white/60">
            <li><a href="#" class="hover:text-zioto-gold transition-colors">شرایط اقساط</a></li>
            <li><a href="#" class="hover:text-zioto-gold transition-colors">سوالات متداول</a></li>
          </ul>
        </div>
        <div>
          <h3 class="text-zioto-gold font-bold mb-4">خدمات</h3>
          <ul class="space-y-2 text-sm text-white/60">
            <li>خرید نقدی</li>
            <li>خرید اقساطی</li>
            <li>مشاوره سرمایه‌گذاری</li>
            <li>ارسال رایگان</li>
          </ul>
        </div>
        <div>
          <h3 class="text-zioto-gold font-bold mb-4">تماس با ما</h3>
          <ul class="space-y-2 text-sm text-white/60">
            <li class="flex items-center gap-2">
              <svg class="w-4 h-4 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              <span>۰۲۱-XXXXXXXX</span>
            </li>
            <li class="flex items-center gap-2">
              <svg class="w-4 h-4 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              <span>info@zioto.ir</span>
            </li>
          </ul>
        </div>
      </div>
      <div class="border-t border-white/10 mt-8 pt-8 text-center text-white/40 text-sm">
        <p>© ۱۴۰۵ زیوتو. تمامی حقوق محفوظ است.</p>
        <p class="mt-2">ویژه کارمندان محترم بانک ملی ایران</p>
      </div>
    </div>
  </footer>

  <script src="{{ asset('js/landing/data.js') }}"></script>
  <script src="{{ asset('js/landing/app.js') }}"></script>
</body>
</html>
