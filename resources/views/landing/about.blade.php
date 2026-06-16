@extends('landing.layouts.app')

@section('title', 'درباره ما | زیوتو')
@section('description', 'درباره شرکت صنعتی بازرگانی فناوران بین الملل ساویس (زیوتو)')

@section('content')
<section class="container mx-auto px-4 py-16">
  <div class="max-w-4xl mx-auto">
    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-white mb-4">درباره زیوتو</h1>
      <div class="w-20 h-1 bg-zioto-gold mx-auto rounded-full"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
      <div class="bg-[#1A1D23] rounded-2xl p-6 text-center border border-zioto-gold/20">
        <div class="text-3xl font-black text-zioto-gold mb-2">+۵۰۰</div>
        <p class="text-white/60 text-sm">مشتری راضی</p>
      </div>
      <div class="bg-[#1A1D23] rounded-2xl p-6 text-center border border-zioto-gold/20">
        <div class="text-3xl font-black text-zioto-gold mb-2">۱۰۰٪</div>
        <p class="text-white/60 text-sm">ضمانت اصالت کالا</p>
      </div>
      <div class="bg-[#1A1D23] rounded-2xl p-6 text-center border border-zioto-gold/20">
        <div class="text-3xl font-black text-zioto-gold mb-2">۲۴</div>
        <p class="text-white/60 text-sm">ساعت ارسال</p>
      </div>
    </div>

    <div class="prose prose-invert max-w-none text-white/70 leading-8 space-y-6">
      <p>
        شرکت صنعتی بازرگانی فناوران بین الملل ساویس با برند زیوتو، یکی از شرکت‌های فناور عضو 
        پارک علم و فناوری پردیس می‌باشد که در زمینه خرید و فروش شمش طلا و نقره خالص فعالیت می‌کند.
      </p>
      <p>
        ما در زیوتو امکان خرید اقساطی شمش طلا و نقره را ویژه مشتریان و کارمندان بانک ملی ایران 
        فراهم کرده‌ایم تا همه بتوانند به راحتی و با خیال راحت در بازار طلا و نقره سرمایه‌گذاری کنند.
      </p>
      <p>
        تمامی محصولات زیوتو دارای گواهی اصالت و شماره سریال منحصربفرد بوده و با ضمانت بازخرید 
        به فروش می‌رسند. همچنین محصولات به صورت کاملاً بیمه‌شده به تمام نقاط ایران ارسال می‌شوند.
      </p>
    </div>
  </div>
</section>
@endsection
