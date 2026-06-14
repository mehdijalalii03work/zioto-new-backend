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
              <h3 class="font-bold text-white text-lg">${STATE.userData.name}</h3>
              <p class="text-sm text-zioto-gold">${STATE.userData.employeeId}</p>
              <p class="text-xs text-white/50 mt-1">عضویت: ${STATE.userData.joinDate}</p>
            </div>
            <nav class="space-y-2">
              <button onclick="switchProfileTab('orders')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${STATE.activeProfileTab === 'orders' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>سفارشات من</span>
              </button>
              <button onclick="switchProfileTab('payments')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${STATE.activeProfileTab === 'payments' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                <span>تاریخچه پرداخت</span>
              </button>
              <button onclick="switchProfileTab('installments')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${STATE.activeProfileTab === 'installments' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>اقساط فعال</span>
              </button>
              <button onclick="switchProfileTab('settings')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${STATE.activeProfileTab === 'settings' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
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
  STATE.activeProfileTab = tab;
  renderPage();
}

function renderProfileContent() {
  switch (STATE.activeProfileTab) {
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
        <p class="text-2xl font-bold text-zioto-gold">${STATE.ordersData.length}</p>
        <p class="text-xs text-white/50">کل سفارشات</p>
      </div>
      <div class="bg-zioto-green-dark/80 rounded-xl p-4 border border-zioto-gold/10 text-center">
        <p class="text-2xl font-bold text-green-400">${STATE.ordersData.filter(o => o.status === 'success').length}</p>
        <p class="text-xs text-white/50">تحویل شده</p>
      </div>
      <div class="bg-zioto-green-dark/80 rounded-xl p-4 border border-zioto-gold/10 text-center">
        <p class="text-2xl font-bold text-yellow-400">${STATE.ordersData.filter(o => o.status === 'pending').length}</p>
        <p class="text-xs text-white/50">در حال پردازش</p>
      </div>
      <div class="bg-zioto-green-dark/80 rounded-xl p-4 border border-zioto-gold/10 text-center">
        <p class="text-2xl font-bold text-red-400">${STATE.ordersData.filter(o => o.status === 'failed' || o.status === 'cancelled').length}</p>
        <p class="text-xs text-white/50">ناموفق/لغو</p>
      </div>
    </div>
    <div class="space-y-4">
      ${STATE.ordersData.map(order => `
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
            ${order.status === 'pending' ? `<button onclick="showNotification('در حال بروزرسانی وضعیت...')" class="text-sm px-4 py-2 bg-zioto-gold/10 text-zioto-gold rounded-lg hover:bg-zioto-gold/20 transition-colors">پیگیری سفارش</button>` : ''}
            ${order.status === 'failed' ? `<button onclick="showNotification('در حال انتقال به درگاه پرداخت...')" class="text-sm px-4 py-2 bg-zioto-gold/10 text-zioto-gold rounded-lg hover:bg-zioto-gold/20 transition-colors">پرداخت مجدد</button>` : ''}
            ${order.status === 'cancelled' ? `<button onclick="showNotification('در حال انتقال...')" class="text-sm px-4 py-2 bg-zioto-gold/10 text-zioto-gold rounded-lg hover:bg-zioto-gold/20 transition-colors">سفارش مجدد</button>` : ''}
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

function renderPaymentsTab() {
  const statusColors = { success: 'bg-green-500/20 text-green-400', pending: 'bg-yellow-500/20 text-yellow-400', failed: 'bg-red-500/20 text-red-400' };
  const totalPaid = STATE.paymentsData.filter(p => p.status === 'success').reduce((sum, p) => sum + p.amount, 0);
  const totalPending = STATE.paymentsData.filter(p => p.status === 'pending').reduce((sum, p) => sum + p.amount, 0);
  const totalFailed = STATE.paymentsData.filter(p => p.status === 'failed').reduce((sum, p) => sum + p.amount, 0);
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
      ${STATE.paymentsData.map(payment => `
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
          <input type="text" class="form-input" value="${STATE.userData.name}">
        </div>
        <div>
          <label class="form-label">شماره موبایل</label>
          <input type="tel" class="form-input" value="۰۹۱۲۱۲۳۴۵۶۷" dir="ltr">
        </div>
        <div>
          <label class="form-label">ایمیل</label>
          <input type="email" class="form-input" value="${STATE.userData.email}" dir="ltr">
        </div>
        <div>
          <label class="form-label">کد ملی</label>
          <input type="text" class="form-input" value="${STATE.userData.nationalId}" dir="ltr" disabled>
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
