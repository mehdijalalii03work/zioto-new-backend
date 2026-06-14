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
            ${STATE.authStep === 'phone' ? 'ورود به حساب کاربری' : 'تایید شماره موبایل'}
          </h1>
          <p class="text-white/50 text-sm">
            ${STATE.authStep === 'phone' ? 'شماره موبایل خود را وارد کنید' : `کد ۶ رقمی ارسال شده به ${formatPhoneDisplay(STATE.authPhone)} را وارد کنید`}
          </p>
        </div>
        <div class="bg-zioto-green-dark/80 rounded-2xl p-8 border border-zioto-gold/20 login-card">
          ${STATE.authStep === 'phone' ? renderPhoneStep() : renderOTPStep()}
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
        ${STATE.otpCountdown > 0 ? `<p class="text-white/50 text-sm">ارسال مجدد کد تا <span id="otp-countdown" class="text-zioto-gold font-bold">${toPersianNum(STATE.otpCountdown)}</span> ثانیه دیگر</p>` : `<button type="button" onclick="resendOTP()" class="text-zioto-gold text-sm hover:text-zioto-gold-light transition-colors">ارسال مجدد کد تایید</button>`}
      </div>
      <button type="submit" class="btn-gold w-full py-4 text-lg">تایید و ورود</button>
      <button type="button" onclick="goToPhoneStep()" class="w-full mt-4 text-white/50 hover:text-white text-sm transition-colors">تغییر شماره موبایل</button>
    </form>
  `;
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
  STATE.authPhone = phone;
  STATE.authStep = 'otp';
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
    STATE.isLoggedIn = true;
    STATE.authStep = 'phone';
    STATE.authPhone = '';
    updateAuthButtons();
    showNotification(`خوش آمدید ${STATE.userData.name}!`, 'success');
    navigateTo('home');
  }, 1000);
}

function resendOTP() {
  if (STATE.otpCountdown > 0) return;
  showNotification('کد تایید جدید ارسال شد', 'success');
  startOTPCountdown();
}

function startOTPCountdown() {
  STATE.otpCountdown = 60;
  if (STATE.otpTimer) clearInterval(STATE.otpTimer);
  STATE.otpTimer = setInterval(() => {
    STATE.otpCountdown--;
    const countdownEl = document.getElementById('otp-countdown');
    if (countdownEl) countdownEl.textContent = toPersianNum(STATE.otpCountdown);
    if (STATE.otpCountdown <= 0) { clearInterval(STATE.otpTimer); STATE.otpTimer = null; renderPage(); }
  }, 1000);
}

function goToPhoneStep() {
  STATE.authStep = 'phone';
  renderPage();
}

function updateAuthButtons() {
  const container = document.getElementById('auth-buttons');
  if (!container) return;
  if (STATE.isLoggedIn) {
    container.innerHTML = `
      <button onclick="navigateTo('profile')" class="flex items-center gap-2 p-2 text-white/80 hover:text-zioto-gold transition-colors">
        <div class="w-8 h-8 bg-zioto-gold/20 rounded-full flex items-center justify-center">
          <svg class="w-5 h-5 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        <span class="hidden md:inline text-sm">${STATE.userData.name.split(' ')[0]}</span>
      </button>
    `;
  } else {
    container.innerHTML = `<button onclick="navigateTo('login')" class="btn-gold text-sm px-4 py-2">ورود / ثبت‌نام</button>`;
  }
}

function logout() {
  STATE.isLoggedIn = false;
  STATE.authStep = 'phone';
  STATE.authPhone = '';
  updateAuthButtons();
  showNotification('با موفقیت خارج شدید');
  navigateTo('home');
}
