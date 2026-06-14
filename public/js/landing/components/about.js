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
        <div class="bg-gradient-to-br from-[#1A1D23] to-[#111318] rounded-3xl p-8 border border-zioto-gold/20">
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-[#1A1D23]/80 rounded-2xl p-6 text-center">
              <p class="text-3xl font-black text-zioto-gold mb-2">۹۹۹.۹</p>
              <p class="text-sm text-white/60">عیار طلا</p>
            </div>
            <div class="bg-[#1A1D23]/80 rounded-2xl p-6 text-center">
              <p class="text-3xl font-black text-zioto-gold mb-2">۹۹۹</p>
              <p class="text-sm text-white/60">عیار نقره</p>
            </div>
            <div class="bg-[#1A1D23]/80 rounded-2xl p-6 text-center">
              <p class="text-3xl font-black text-zioto-gold mb-2">۱۲</p>
              <p class="text-sm text-white/60">ماه اقساط</p>
            </div>
            <div class="bg-[#1A1D23]/80 rounded-2xl p-6 text-center">
              <p class="text-3xl font-black text-zioto-gold mb-2">۰%</p>
              <p class="text-sm text-white/60">سود اقساط</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  `;
}
