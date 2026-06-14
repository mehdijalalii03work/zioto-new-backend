function formatPrice(price) {
  return new Intl.NumberFormat('fa-IR').format(price) + ' ریال';
}

function formatPriceToman(price) {
  return new Intl.NumberFormat('fa-IR').format(price / 10) + ' تومان';
}

function getDiscountPercent(original, current) {
  return Math.round(((original - current) / original) * 100);
}

function toPersianNum(num) {
  const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
  return num.toString().replace(/\d/g, d => persianDigits[d]);
}

function toggleMobileMenu() {
  const menu = document.getElementById('mobile-menu');
  menu.classList.toggle('hidden');
}

function showNotification(message, type = 'success') {
  const notification = document.createElement('div');
  notification.className = `fixed top-24 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-xl shadow-2xl text-sm font-medium transition-all transform translate-y-0 opacity-100 ${
    type === 'error' ? 'bg-red-500/90 text-white' :
    type === 'info' ? 'bg-blue-500/90 text-white' :
    'bg-zioto-gold/90 text-[#111318]'
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
