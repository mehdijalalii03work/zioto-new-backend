function renderLogin() {
  if (STATE.isLoggedIn) { navigateTo('profile'); return ''; }
  return `
    <section class="min-h-[80vh] flex items-center justify-center px-4 py-12">
      <div class="w-full max-w-md">
        <div class="text-center mb-8">
          <div class="w-16 h-16 bg-zioto-gold/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <span class="text-zioto-gold text-2xl font-bold">Z</span>
          </div>
          <h1 class="text-2xl font-bold text-white mb-2">
            ${getAuthTitle()}
          </h1>
          <p class="text-white/50 text-sm">
            ${getAuthSubtitle()}
          </p>
        </div>
        <div class="bg-[#1A1D23] rounded-2xl p-8 border border-zioto-gold/20 login-card">
          ${getAuthStep()}
        </div>
        <div class="text-center mt-6">
          <p class="text-white/40 text-xs">با ورود به سایت، شرایط و قوانین زیوتو را می‌پذیرید.</p>
        </div>
      </div>
    </section>
  `;
}

function getAuthTitle() {
  switch (STATE.authStep) {
    case 'phone': return 'ورود به حساب کاربری';
    case 'otp': return 'تایید شماره موبایل';
    case 'register': return 'تکمیل اطلاعات';
    default: return 'ورود به حساب کاربری';
  }
}

function getAuthSubtitle() {
  switch (STATE.authStep) {
    case 'phone': return 'شماره موبایل خود را وارد کنید';
    case 'otp': return `کد ۶ رقمی ارسال شده به ${formatPhoneDisplay(STATE.authPhone)} را وارد کنید`;
    case 'register': return 'لطفاً اطلاعات هویتی خود را وارد کنید';
    default: return 'شماره موبایل خود را وارد کنید';
  }
}

function getAuthStep() {
  switch (STATE.authStep) {
    case 'phone': return renderPhoneStep();
    case 'otp': return renderOTPStep();
    case 'register': return renderRegisterStep();
    default: return renderPhoneStep();
  }
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
      <button type="submit" class="btn-gold w-full py-4 text-lg" id="send-otp-btn">ارسال کد تایید</button>
      <div class="relative my-8">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-white/10"></div></div>
        <div class="relative flex justify-center text-sm"><span class="px-4 bg-[#111318] text-white/40">یا</span></div>
      </div>
      <button type="button" onclick="showNotification('ورود با شماره کارمندی به زودی فعال می‌شود', 'info')" class="w-full px-4 py-2 bg-zioto-gold/20 text-zioto-gold rounded-lg text-sm hover:bg-zioto-gold/30 transition-colors">
        ورود با شماره کارمندی
      </button>
    </form>
  `;
}

function renderOTPStep() {
  const now = Date.now();
  const remaining = STATE.otpExpiresAt > now ? Math.floor((STATE.otpExpiresAt - now) / 1000) : 0;
  const expired = remaining <= 0 && STATE.otpExpiresAt > 0;
  return `
    <form onsubmit="verifyOTP(event)">
      <div class="mb-6">
        <label class="form-label text-center block mb-4">کد ۶ رقمی تایید</label>
        <div class="flex justify-center gap-2" dir="ltr">
          ${[0,1,2,3,4,5].map(i => `
            ${i === 3 ? '<span class="text-zioto-gold self-center text-xl font-bold">-</span>' : ''}
            <input type="text" maxlength="1" class="otp-input w-12 h-14 text-center text-xl font-bold bg-[#1A1D23]/80 border-2 border-white/20 rounded-xl text-white focus:border-zioto-gold focus:outline-none transition-colors" data-index="${i}" oninput="handleOTPInput(this, ${i})" onkeydown="handleOTPKeydown(event, ${i})" ${expired ? 'disabled' : ''}>
          `).join('')}
        </div>
      </div>
      <div class="text-center mb-4">
        ${expired
          ? `<p class="text-red-400 text-sm">کد تایید منقضی شد</p>`
          : STATE.otpExpiresAt > 0
            ? `<p class="text-white/50 text-sm">کد تا <span id="otp-expiry" class="text-zioto-gold font-bold">${formatOTPTimer(remaining)}</span> دیگر منقضی می‌شود</p>`
            : ''
        }
      </div>
      <div class="text-center mb-6">
        ${STATE.otpCountdown > 0
          ? `<p class="text-white/50 text-sm">ارسال مجدد کد تا <span id="otp-countdown" class="text-zioto-gold font-bold">${toPersianNum(STATE.otpCountdown)}</span> ثانیه دیگر</p>`
          : `<button type="button" onclick="resendOTP()" class="text-zioto-gold text-sm hover:text-zioto-gold-light transition-colors">ارسال مجدد کد تایید</button>`
        }
      </div>
      <button type="submit" class="btn-gold w-full py-4 text-lg" id="verify-otp-btn" ${expired ? 'disabled' : ''}>${expired ? 'کد منقضی شد' : 'تایید کد'}</button>
      <button type="button" onclick="goToPhoneStep()" class="w-full mt-4 text-white/50 hover:text-white text-sm transition-colors">تغییر شماره موبایل</button>
    </form>
  `;
}

function formatOTPTimer(seconds) {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${toPersianNum(m)}:${toPersianNum(s < 10 ? '0' + s : s)}`;
}

function renderRegisterStep() {
  return `
    <form onsubmit="submitShahkar(event)">
      <div class="mb-4">
        <label class="form-label">نام</label>
        <input type="text" class="form-input" placeholder="نام خود را وارد کنید" id="reg-first-name" required>
      </div>
      <div class="mb-4">
        <label class="form-label">نام خانوادگی</label>
        <input type="text" class="form-input" placeholder="نام خانوادگی خود را وارد کنید" id="reg-last-name" required>
      </div>
      <div class="mb-4">
        <label class="form-label">کد ملی</label>
        <input type="text" class="form-input text-left" placeholder="۱۰ رقم" id="reg-national-code" maxlength="10" dir="ltr" required>
        <p class="text-white/40 text-xs mt-1">کد ملی باید با شماره موبایل ثبت‌شده مطابقت داشته باشد</p>
      </div>
      <div class="mb-6">
        <label class="form-label">تاریخ تولد</label>
        <div class="grid grid-cols-3 gap-2" dir="ltr">
          <select class="form-input" id="reg-birth-year">
            <option value="">سال</option>
            ${renderPersianYearOptions()}
          </select>
          <select class="form-input" id="reg-birth-month">
            <option value="">ماه</option>
            ${renderPersianMonthOptions()}
          </select>
          <select class="form-input" id="reg-birth-day">
            <option value="">روز</option>
            ${renderPersianDayOptions()}
          </select>
        </div>
      </div>
      <button type="submit" class="btn-gold w-full py-4 text-lg" id="shahkar-btn">احراز هویت و ثبت‌نام</button>
      <button type="button" onclick="goToOTPStep()" class="w-full mt-4 text-white/50 hover:text-white text-sm transition-colors">بازگشت</button>
    </form>
  `;
}

function renderPersianYearOptions() {
  const currentYear = 1404;
  let html = '';
  for (let y = currentYear - 60; y <= currentYear; y++) {
    html += `<option value="${y}">${toPersianNum(y)}</option>`;
  }
  return html;
}

function renderPersianMonthOptions() {
  const months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  return months.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
}

function renderPersianDayOptions() {
  let html = '';
  for (let d = 1; d <= 31; d++) {
    html += `<option value="${d}">${toPersianNum(d)}</option>`;
  }
  return html;
}

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
  const btn = document.getElementById('send-otp-btn');
  btn.disabled = true;
  btn.textContent = 'در حال ارسال...';

  fetch('/api/auth/send-otp', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ phone }),
  })
  .then(res => res.json().then(data => ({ status: res.status, data })))
  .then(({ status, data }) => {
    if (status === 429) {
      showNotification(data.message || 'لطفاً کمی صبر کنید', 'error');
      return;
    }
    if (status !== 200) {
      showNotification(data.message || 'خطا در ارسال کد', 'error');
      return;
    }
    STATE.authPhone = phone;
    STATE.authStep = 'otp';
    STATE.otpExpiresAt = Date.now() + 180000;
    renderPage();
    startOTPCountdown();
    setTimeout(() => { const firstInput = document.querySelector('.otp-input'); if (firstInput) firstInput.focus(); }, 100);
    showNotification(data.message || 'کد تایید ارسال شد', 'success');
  })
  .catch(() => {
    showNotification('خطا در ارتباط با سرور', 'error');
  })
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'ارسال کد تایید';
  });
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

  const btn = document.getElementById('verify-otp-btn');
  btn.disabled = true;
  btn.textContent = 'در حال تایید...';

  fetch('/api/auth/verify-otp', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ phone: STATE.authPhone, code: otp }),
  })
  .then(res => res.json().then(data => ({ status: res.status, data })))
  .then(({ status, data }) => {
    if (status !== 200) {
      showNotification(data.message || 'کد تایید نامعتبر است', 'error');
      return;
    }
    if (data.requires_registration) {
      STATE.authToken = data.token;
      STATE.authStep = 'register';
      renderPage();
      setTimeout(() => { const nameInput = document.getElementById('reg-first-name'); if (nameInput) nameInput.focus(); }, 100);
      return;
    }
    STATE.isLoggedIn = true;
    STATE.authStep = 'phone';
    STATE.authPhone = '';
    STATE.authToken = '';
    STATE.otpExpiresAt = 0;
    STATE.userData = { ...STATE.userData, ...(data.user || {}), phone: (data.user?.phone) || STATE.authPhone };
    updateAuthButtons();
    showNotification(`خوش آمدید!`, 'success');
    navigateTo('home');
  })
  .catch(() => {
    showNotification('خطا در ارتباط با سرور', 'error');
  })
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'تایید کد';
  });
}

function submitShahkar(e) {
  e.preventDefault();
  const firstName = document.getElementById('reg-first-name').value.trim();
  const lastName = document.getElementById('reg-last-name').value.trim();
  const nationalCode = document.getElementById('reg-national-code').value.replace(/\D/g, '');
  const birthYear = document.getElementById('reg-birth-year').value;
  const birthMonth = document.getElementById('reg-birth-month').value;
  const birthDay = document.getElementById('reg-birth-day').value;
  const birthDate = birthYear && birthMonth && birthDay ? persianToGregorian(parseInt(birthYear), parseInt(birthMonth), parseInt(birthDay)) : null;

  if (firstName.length < 2) { showNotification('نام باید حداقل ۲ کاراکتر باشد', 'error'); return; }
  if (lastName.length < 2) { showNotification('نام خانوادگی باید حداقل ۲ کاراکتر باشد', 'error'); return; }
  if (nationalCode.length !== 10) { showNotification('کد ملی باید ۱۰ رقم باشد', 'error'); return; }
  if (!validateNationalCode(nationalCode)) { showNotification('کد ملی نامعتبر است', 'error'); return; }

  const btn = document.getElementById('shahkar-btn');
  btn.disabled = true;
  btn.textContent = 'در حال احراز هویت...';

  fetch('/api/auth/shahkar-verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({
      token: STATE.authToken,
      first_name: firstName,
      last_name: lastName,
      national_code: nationalCode,
      birth_date: birthDate,
    }),
  })
  .then(res => res.json().then(data => ({ status: res.status, data })))
  .then(({ status, data }) => {
    if (status !== 200) {
      showNotification(data.message || 'خطا در احراز هویت', 'error');
      return;
    }
    STATE.isLoggedIn = true;
    STATE.authStep = 'phone';
    STATE.authPhone = '';
    STATE.authToken = '';
    STATE.otpExpiresAt = 0;
    STATE.userData = {
      ...STATE.userData,
      ...(data.user || {}),
      name: `${firstName} ${lastName}`,
      phone: (data.user?.phone) || STATE.authPhone,
    };
    updateAuthButtons();
    showNotification('احراز هویت با موفقیت انجام شد!', 'success');
    navigateTo('home');
  })
  .catch(() => {
    showNotification('خطا در ارتباط با سرور', 'error');
  })
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'احراز هویت و ثبت‌نام';
  });
}

function validateNationalCode(code) {
  if (!/^\d{10}$/.test(code)) return false;
  const invalidPatterns = ['0000000000','1111111111','2222222222','3333333333','4444444444','5555555555','6666666666','7777777777','8888888888','9999999999'];
  if (invalidPatterns.includes(code)) return false;
  let sum = 0;
  for (let i = 0; i < 9; i++) sum += parseInt(code.charAt(i)) * (10 - i);
  const remainder = sum % 11;
  const checkDigit = parseInt(code.charAt(9));
  return remainder < 2 ? checkDigit === remainder : checkDigit === (11 - remainder);
}

function resendOTP() {
  if (STATE.otpCountdown > 0) return;
  const phone = STATE.authPhone;
  fetch('/api/auth/send-otp', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ phone }),
  })
  .then(res => res.json().then(data => ({ status: res.status, data })))
  .then(({ status, data }) => {
    if (status !== 200) {
      showNotification(data.message || 'خطا در ارسال مجدد کد', 'error');
      return;
    }
    STATE.otpExpiresAt = Date.now() + 180000;
    showNotification('کد تایید جدید ارسال شد', 'success');
    startOTPCountdown();
  })
  .catch(() => {
    showNotification('خطا در ارتباط با سرور', 'error');
  });
}

function startOTPCountdown() {
  STATE.otpCountdown = 60;
  if (STATE.otpTimer) clearInterval(STATE.otpTimer);
  STATE.otpTimer = setInterval(() => {
    STATE.otpCountdown--;
    const countdownEl = document.getElementById('otp-countdown');
    if (countdownEl) countdownEl.textContent = toPersianNum(STATE.otpCountdown);

    const expiryEl = document.getElementById('otp-expiry');
    const remaining = Math.floor((STATE.otpExpiresAt - Date.now()) / 1000);
    if (expiryEl) {
      if (remaining > 0) {
        expiryEl.textContent = formatOTPTimer(remaining);
      } else {
        clearInterval(STATE.otpTimer);
        STATE.otpTimer = null;
        renderPage();
        return;
      }
    }

    if (STATE.otpCountdown <= 0 && remaining <= 0) {
      clearInterval(STATE.otpTimer);
      STATE.otpTimer = null;
      renderPage();
    }
  }, 1000);
}

function goToPhoneStep() {
  STATE.authStep = 'phone';
  STATE.authPhone = '';
  STATE.authToken = '';
  STATE.otpExpiresAt = 0;
  renderPage();
}

function goToOTPStep() {
  STATE.authStep = 'otp';
  STATE.authToken = '';
  renderPage();
}

function updateAuthButtons() {
  const container = document.getElementById('auth-buttons');
  if (!container) return;
  if (STATE.isLoggedIn) {
    container.innerHTML = `
      <button onclick="navigateTo('profile')" class="flex items-center gap-2 p-2 rounded-lg text-white/70 hover:text-white hover:bg-white/5 transition-all">
        <div class="w-7 h-7 bg-white/10 rounded-full flex items-center justify-center">
          <svg class="w-4 h-4 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        <span class="hidden md:inline text-sm">${(STATE.userData.name || 'کاربر').split(' ')[0]}</span>
      </button>
    `;
  } else {
    container.innerHTML = `<button onclick="navigateTo('login')" class="btn-gold text-sm px-4 py-2 rounded-lg">ورود / ثبت‌نام</button>`;
  }
}

function logout() {
  fetch('/api/auth/logout', { method: 'POST', headers: { 'Accept': 'application/json' } }).catch(() => {});
  STATE.isLoggedIn = false;
  STATE.profileLoaded = false;
  STATE.authStep = 'phone';
  STATE.authPhone = '';
  STATE.authToken = '';
  updateAuthButtons();
  showNotification('با موفقیت خارج شدید');
  navigateTo('home');
}

function persianToGregorian(py, pm, pd) {
  const persianDays = [0, 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
  const gregorianDays = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  const gregorianLeapDays = [0, 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

  let y = py - 979;
  let monthDays = 0;
  for (let i = 1; i < pm; i++) monthDays += persianDays[i];
  let persianDayOfYear = monthDays + pd;

  let jy = 0;
  if (persianDayOfYear <= 286) {
    jy = y + 621;
    monthDays = 0;
    for (let i = 1; i <= 12; i++) {
      const daysInMonths = (jy % 4 === 0 && (jy % 100 !== 0 || jy % 400 === 0)) ? gregorianLeapDays[i] : gregorianDays[i];
      if (persianDayOfYear > monthDays + daysInMonths) {
        monthDays += daysInMonths;
      } else {
        return `${jy}-${String(i).padStart(2, '0')}-${String(persianDayOfYear - monthDays).padStart(2, '0')}`;
      }
    }
  } else {
    jy = y + 622;
    persianDayOfYear -= 286;
    monthDays = 0;
    for (let i = 1; i <= 12; i++) {
      const daysInMonths = (jy % 4 === 0 && (jy % 100 !== 0 || jy % 400 === 0)) ? gregorianLeapDays[i] : gregorianDays[i];
      if (persianDayOfYear > monthDays + daysInMonths) {
        monthDays += daysInMonths;
      } else {
        return `${jy}-${String(i).padStart(2, '0')}-${String(persianDayOfYear - monthDays).padStart(2, '0')}`;
      }
    }
  }
  return null;
}
