function renderProfile() {
  if (!STATE.isLoggedIn) {
    navigateTo('login');
    return '';
  }

  if (!STATE.profileLoaded) {
    fetchProfileData();
    return `
      <section class="container mx-auto px-4 py-12">
        <div class="flex items-center justify-center min-h-[60vh]">
          <div class="text-center">
            <div class="w-12 h-12 border-4 border-zioto-gold/20 border-t-zioto-gold rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-white/50">در حال بارگذاری...</p>
          </div>
        </div>
      </section>
    `;
  }

  return `
    <section class="container mx-auto px-4 py-12">
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <div class="lg:col-span-1">
          <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20 sticky top-24">
            <div class="text-center mb-6 pb-6 border-b border-white/10">
              <div class="w-20 h-20 bg-zioto-gold/20 rounded-full mx-auto mb-4 flex items-center justify-center">
                <svg class="w-10 h-10 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </div>
              <h3 class="font-bold text-white text-lg">${STATE.userData.name || 'کاربر'}</h3>
              <p class="text-sm text-zioto-gold">${STATE.userData.phone || ''}</p>
              <p class="text-xs text-white/50 mt-1">عضویت: ${STATE.userData.joinDate || ''}</p>
            </div>
            <nav class="space-y-2">
              <button onclick="switchProfileTab('settings')" class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${STATE.activeProfileTab === 'settings' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>تنظیمات حساب</span>
              </button>
            </nav>
            <hr class="border-white/10 my-4">
            <button onclick="logout()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all text-red-400 hover:bg-red-500/10">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              <span>خروج از حساب</span>
            </button>
          </div>
        </div>
        <div class="lg:col-span-3">
          <div id="profile-content">
            ${renderSettingsTab()}
          </div>
        </div>
      </div>
    </section>
  `;
}

function fetchProfileData() {
  fetch('/api/profile', {
    headers: { 'Accept': 'application/json' },
  })
  .then(res => {
    if (res.status === 401) { STATE.isLoggedIn = false; updateAuthButtons(); navigateTo('login'); return null; }
    return res.json();
  })
  .then(data => {
    if (!data) return;
    const u = data.user;
    STATE.userData = {
      first_name: u.first_name || '',
      last_name: u.last_name || '',
      name: u.name || '',
      phone: u.phone || '',
      email: u.email || '',
      nationalId: u.national_id || '',
      birthDate: u.birth_date || '',
      joinDate: u.created_at ? toPersianDate(u.created_at) : '',
    };
    STATE.profileLoaded = true;
    renderPage();
  })
  .catch(() => {
    showNotification('خطا در دریافت اطلاعات کاربر', 'error');
  });
}

function toPersianDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function switchProfileTab(tab) {
  STATE.activeProfileTab = tab;
  renderPage();
}

function renderSettingsTab() {
  const birthParts = parseBirthDate(STATE.userData.birthDate);
  return `
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-white mb-2">تنظیمات حساب</h2>
      <p class="text-white/50">مدیریت اطلاعات شخصی و امنیت حساب</p>
    </div>
    <form onsubmit="saveProfile(event)" class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20 mb-6">
      <h3 class="text-lg font-bold text-white mb-6">اطلاعات شخصی</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">نام</label>
          <input type="text" class="form-input" id="profile-first-name" value="${STATE.userData.first_name || ''}" required>
        </div>
        <div>
          <label class="form-label">نام خانوادگی</label>
          <input type="text" class="form-input" id="profile-last-name" value="${STATE.userData.last_name || ''}" required>
        </div>
        <div>
          <label class="form-label">شماره موبایل</label>
          <input type="tel" class="form-input" value="${STATE.userData.phone || ''}" dir="ltr" disabled>
        </div>
        <div>
          <label class="form-label">ایمیل</label>
          <input type="email" class="form-input" id="profile-email" value="${STATE.userData.email || ''}" dir="ltr">
        </div>
        <div>
          <label class="form-label">کد ملی</label>
          <input type="text" class="form-input" value="${STATE.userData.nationalId || ''}" dir="ltr" disabled>
        </div>
        <div>
          <label class="form-label">تاریخ تولد</label>
          <div class="grid grid-cols-3 gap-2" dir="ltr">
            <select class="form-input" id="profile-birth-year">
              <option value="">سال</option>
              ${renderPersianYearOptions(birthParts.year)}
            </select>
            <select class="form-input" id="profile-birth-month">
              <option value="">ماه</option>
              ${renderPersianMonthOptions(birthParts.month)}
            </select>
            <select class="form-input" id="profile-birth-day">
              <option value="">روز</option>
              ${renderPersianDayOptions(birthParts.day)}
            </select>
          </div>
        </div>
      </div>
      <button type="submit" class="btn-gold mt-6 px-6 py-2" id="save-profile-btn">ذخیره تغییرات</button>
    </form>
  `;
}

function parseBirthDate(gregorianDate) {
  if (!gregorianDate) return { year: '', month: '', day: '' };
  const d = new Date(gregorianDate);
  if (isNaN(d.getTime())) return { year: '', month: '', day: '' };
  const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn', { year: 'numeric', month: 'numeric', day: 'numeric' });
  const parts = formatter.formatToParts(d);
  let year = '', month = '', day = '';
  for (const p of parts) {
    if (p.type === 'year') year = p.value;
    if (p.type === 'month') month = p.value;
    if (p.type === 'day') day = p.value;
  }
  return { year, month, day };
}

function renderPersianYearOptions(selected) {
  const currentYear = 1404;
  let html = '';
  for (let y = currentYear - 60; y <= currentYear; y++) {
    html += `<option value="${y}" ${selected == y ? 'selected' : ''}>${toPersianNum(y)}</option>`;
  }
  return html;
}

function renderPersianMonthOptions(selected) {
  const months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  return months.map((m, i) => `<option value="${i + 1}" ${selected == i + 1 ? 'selected' : ''}>${m}</option>`).join('');
}

function renderPersianDayOptions(selected) {
  let html = '';
  for (let d = 1; d <= 31; d++) {
    html += `<option value="${d}" ${selected == d ? 'selected' : ''}>${toPersianNum(d)}</option>`;
  }
  return html;
}

function saveProfile(e) {
  e.preventDefault();
  const firstName = document.getElementById('profile-first-name').value.trim();
  const lastName = document.getElementById('profile-last-name').value.trim();
  const email = document.getElementById('profile-email').value.trim();
  const birthYear = document.getElementById('profile-birth-year').value;
  const birthMonth = document.getElementById('profile-birth-month').value;
  const birthDay = document.getElementById('profile-birth-day').value;
  const birthDate = birthYear && birthMonth && birthDay ? persianToGregorian(parseInt(birthYear), parseInt(birthMonth), parseInt(birthDay)) : null;

  if (firstName.length < 2) { showNotification('نام باید حداقل ۲ کاراکتر باشد', 'error'); return; }
  if (lastName.length < 2) { showNotification('نام خانوادگی باید حداقل ۲ کاراکتر باشد', 'error'); return; }

  const btn = document.getElementById('save-profile-btn');
  btn.disabled = true;
  btn.textContent = 'در حال ذخیره...';

  fetch('/api/profile', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({
      first_name: firstName,
      last_name: lastName,
      email: email || null,
      birth_date: birthDate,
    }),
  })
  .then(res => res.json().then(data => ({ status: res.status, data })))
  .then(({ status, data }) => {
    if (status !== 200) {
      const msg = data?.errors ? Object.values(data.errors).flat().join('، ') : (data.message || 'خطا در ذخیره اطلاعات');
      showNotification(msg, 'error');
      return;
    }
    const u = data.user;
    STATE.userData = {
      ...STATE.userData,
      first_name: u.first_name || '',
      last_name: u.last_name || '',
      name: u.name || '',
      email: u.email || '',
      birthDate: u.birth_date || '',
    };
    updateAuthButtons();
    showNotification(data.message || 'اطلاعات با موفقیت بروزرسانی شد', 'success');
  })
  .catch(() => {
    showNotification('خطا در ارتباط با سرور', 'error');
  })
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'ذخیره تغییرات';
  });
}
