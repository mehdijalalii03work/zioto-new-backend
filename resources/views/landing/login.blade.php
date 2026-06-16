@extends('landing.layouts.app')

@section('title', 'ورود | زیوتو')

@section('content')
<section class="min-h-[80vh] flex items-center justify-center px-4 py-12" x-data="loginForm()" x-init="init()">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <div class="w-16 h-16 bg-zioto-gold/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <span class="text-zioto-gold text-2xl font-bold">Z</span>
      </div>
      <h1 class="text-2xl font-bold text-white mb-2" x-text="title"></h1>
      <p class="text-white/50 text-sm" x-text="subtitle"></p>
    </div>

    <div class="bg-[#1A1D23] rounded-2xl p-8 border border-zioto-gold/20 login-card">
      {{-- Step 1: Phone --}}
      <template x-if="step === 'phone'">
        <form @submit.prevent="sendOTP">
          <div class="mb-6">
            <label class="form-label">شماره موبایل</label>
            <input type="tel" class="form-input pl-12 text-left" placeholder="۰۹۱۲XXXXXXX" x-model="phone" maxlength="11" dir="ltr" required>
          </div>
          <div class="mb-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" x-model="agreeTerms" class="w-4 h-4 accent-zioto-gold" required>
              <span class="text-sm text-white/60">شرایط و قوانین استفاده را مطالعه کرده و می‌پذیرم</span>
            </label>
          </div>
          <button type="submit" class="btn-gold w-full py-4 text-lg" x-text="loading ? 'در حال ارسال...' : 'ارسال کد تایید'"></button>
          <div class="relative my-8">
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-white/10"></div></div>
            <div class="relative flex justify-center text-sm"><span class="px-4 bg-[#111318] text-white/40">یا</span></div>
          </div>
          <button type="button" @click="showNotification('ورود با شماره کارمندی به زودی فعال می‌شود', 'info')" class="w-full px-4 py-2 bg-zioto-gold/20 text-zioto-gold rounded-lg text-sm hover:bg-zioto-gold/30 transition-colors">
            ورود با شماره کارمندی
          </button>
        </form>
      </template>

      {{-- Step 2: OTP --}}
      <template x-if="step === 'otp'">
        <form @submit.prevent="verifyOTP">
          <div class="mb-6">
            <label class="form-label text-center block mb-4">کد ۶ رقمی تایید</label>
            <div class="flex justify-center gap-2" dir="ltr">
              <template x-for="(_, i) in 6" :key="i">
                <template x-if="i === 3">
                  <span class="text-zioto-gold self-center text-xl font-bold">-</span>
                </template>
                <input type="text" maxlength="1"
                       x-ref="otpInputs"
                       class="otp-input w-12 h-14 text-center text-xl font-bold bg-[#1A1D23]/80 border-2 border-white/20 rounded-xl text-white focus:border-zioto-gold focus:outline-none transition-colors"
                       :class="{ 'border-zioto-gold bg-zioto-gold/10': otpInputs[i] }"
                       @input="handleOTPInput(i)"
                       @keydown="handleOTPKeydown($event, i)"
                       x-model="otpInputs[i]"
                       :disabled="otpExpired">
              </template>
            </div>
          </div>
          <div class="text-center mb-4">
            <p x-show="otpExpired" class="text-red-400 text-sm">کد تایید منقضی شد</p>
            <p x-show="!otpExpired && otpExpiresAt > 0" class="text-white/50 text-sm">
              کد تا <span class="text-zioto-gold font-bold" x-text="formatOTPTimer(otpRemaining)"></span> دیگر منقضی می‌شود
            </p>
          </div>
          <div class="text-center mb-6">
            <template x-if="otpCountdown > 0">
              <p class="text-white/50 text-sm">ارسال مجدد کد تا <span class="text-zioto-gold font-bold" x-text="toPersianNum(otpCountdown)"></span> ثانیه دیگر</p>
            </template>
            <template x-if="otpCountdown <= 0">
              <button type="button" @click="resendOTP()" class="text-zioto-gold text-sm hover:text-zioto-gold-light transition-colors">ارسال مجدد کد تایید</button>
            </template>
          </div>
          <button type="submit" class="btn-gold w-full py-4 text-lg"
                  x-text="otpExpired ? 'کد منقضی شد' : (loading ? 'در حال تایید...' : 'تایید کد')"
                  :disabled="otpExpired"></button>
          <button type="button" @click="goToPhoneStep()" class="w-full mt-4 text-white/50 hover:text-white text-sm transition-colors">تغییر شماره موبایل</button>
        </form>
      </template>

      {{-- Step 3: Register --}}
      <template x-if="step === 'register'">
        <form @submit.prevent="submitShahkar">
          <div class="mb-4">
            <label class="form-label">نام</label>
            <input type="text" class="form-input" placeholder="نام خود را وارد کنید" x-model="register.firstName" required>
          </div>
          <div class="mb-4">
            <label class="form-label">نام خانوادگی</label>
            <input type="text" class="form-input" placeholder="نام خانوادگی خود را وارد کنید" x-model="register.lastName" required>
          </div>
          <div class="mb-4">
            <label class="form-label">کد ملی</label>
            <input type="text" class="form-input text-left" placeholder="۱۰ رقم" x-model="register.nationalCode" maxlength="10" dir="ltr" required>
            <p class="text-white/40 text-xs mt-1">کد ملی باید با شماره موبایل ثبت‌شده مطابقت داشته باشد</p>
          </div>
          <div class="mb-6">
            <label class="form-label">تاریخ تولد</label>
            <div class="grid grid-cols-3 gap-2" dir="ltr">
              <select class="form-input" x-model="register.birthYear">
                <option value="">سال</option>
                @for ($y = 1344; $y <= 1404; $y++)
                  <option value="{{ $y }}">{{ number_format($y, 0, '', '') }}</option>
                @endfor
              </select>
              <select class="form-input" x-model="register.birthMonth">
                <option value="">ماه</option>
                @foreach (['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'] as $i => $m)
                  <option value="{{ $i + 1 }}">{{ $m }}</option>
                @endforeach
              </select>
              <select class="form-input" x-model="register.birthDay">
                <option value="">روز</option>
                @for ($d = 1; $d <= 31; $d++)
                  <option value="{{ $d }}">{{ number_format($d, 0, '', '') }}</option>
                @endfor
              </select>
            </div>
          </div>
          <button type="submit" class="btn-gold w-full py-4 text-lg" x-text="loading ? 'در حال احراز هویت...' : 'احراز هویت و ثبت‌نام'"></button>
          <button type="button" @click="goToOTPStep()" class="w-full mt-4 text-white/50 hover:text-white text-sm transition-colors">بازگشت</button>
        </form>
      </template>
    </div>

    <div class="text-center mt-6">
      <p class="text-white/40 text-xs">با ورود به سایت، شرایط و قوانین زیوتو را می‌پذیرید.</p>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  function loginForm() {
    return {
      step: 'phone',
      phone: '',
      agreeTerms: false,
      loading: false,

      otpInputs: ['', '', '', '', '', ''],
      otpExpiresAt: 0,
      otpCountdown: 0,
      otpTimer: null,
      otpExpired: false,

      authToken: '',

      register: {
        firstName: '',
        lastName: '',
        nationalCode: '',
        birthYear: '',
        birthMonth: '',
        birthDay: '',
      },

      get title() {
        switch (this.step) {
          case 'phone': return 'ورود به حساب کاربری';
          case 'otp': return 'تایید شماره موبایل';
          case 'register': return 'تکمیل اطلاعات';
          default: return 'ورود به حساب کاربری';
        }
      },

      get subtitle() {
        switch (this.step) {
          case 'phone': return 'شماره موبایل خود را وارد کنید';
          case 'otp': return 'کد ۶ رقمی ارسال شده به ' + this.formatPhoneDisplay(this.phone) + ' را وارد کنید';
          case 'register': return 'لطفاً اطلاعات هویتی خود را وارد کنید';
          default: return 'شماره موبایل خود را وارد کنید';
        }
      },

      get otpRemaining() {
        const now = Date.now();
        const remaining = this.otpExpiresAt > now ? Math.floor((this.otpExpiresAt - now) / 1000) : 0;
        return remaining;
      },

      init() {
        if (Alpine.store('auth').isLoggedIn) {
          window.location.href = '{{ route('landing.profile') }}';
        }
      },

      showNotification(message, type) {
        if (typeof window.showNotification === 'function') {
          window.showNotification(message, type || 'success');
        }
      },

      formatPhoneDisplay(phone) {
        if (!phone) return '';
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.length === 11) return cleaned.slice(0, 4) + '***' + cleaned.slice(7);
        return phone;
      },

      formatOTPTimer(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return (typeof toPersianNum === 'function' ? toPersianNum(m) : m) + ':' +
               (typeof toPersianNum === 'function' ? toPersianNum(s < 10 ? '0' + s : s) : (s < 10 ? '0' + s : s));
      },

      async sendOTP() {
        const cleaned = this.phone.replace(/\D/g, '');
        if (cleaned.length !== 11 || !cleaned.startsWith('09')) {
          this.showNotification('شماره موبایل معتبر نیست', 'error');
          return;
        }
        if (!this.agreeTerms) {
          this.showNotification('لطفاً شرایط و قوانین را بپذیرید', 'error');
          return;
        }
        this.loading = true;
        try {
          const res = await fetch('/api/auth/send-otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ phone: cleaned }),
          });
          const data = await res.json();
          if (res.status === 429) {
            this.showNotification(data.message || 'لطفاً کمی صبر کنید', 'error');
            return;
          }
          if (res.status !== 200) {
            this.showNotification(data.message || 'خطا در ارسال کد', 'error');
            return;
          }
          this.step = 'otp';
          this.otpExpiresAt = Date.now() + 180000;
          this.otpExpired = false;
          this.otpCountdown = 60;
          this.startOTPTimer();
          this.showNotification(data.message || 'کد تایید ارسال شد', 'success');
          this.$nextTick(() => {
            const inputs = this.$refs.otpInputs;
            if (inputs && inputs[0]) inputs[0].focus();
          });
        } catch (e) {
          this.showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
          this.loading = false;
        }
      },

      handleOTPInput(index) {
        this.otpInputs[index] = this.otpInputs[index].replace(/[^0-9]/g, '');
        if (this.otpInputs[index] && index < 5) {
          const inputs = this.$refs.otpInputs;
          if (inputs && inputs[index + 1]) inputs[index + 1].focus();
        }
        const otp = this.otpInputs.join('');
        if (otp.length === 6) {
          setTimeout(() => this.verifyOTP(), 200);
        }
      },

      handleOTPKeydown(e, index) {
        if (e.key === 'Backspace' && !e.target.value && index > 0) {
          const inputs = this.$refs.otpInputs;
          if (inputs && inputs[index - 1]) {
            inputs[index - 1].focus();
            this.otpInputs[index - 1] = '';
          }
        }
      },

      async verifyOTP() {
        const otp = this.otpInputs.join('');
        if (otp.length !== 6) {
          this.showNotification('لطفاً کد ۶ رقمی را کامل وارد کنید', 'error');
          return;
        }
        this.loading = true;
        try {
          const res = await fetch('/api/auth/verify-otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ phone: this.phone.replace(/\D/g, ''), code: otp }),
          });
          const data = await res.json();
          if (res.status !== 200) {
            this.showNotification(data.message || 'کد تایید نامعتبر است', 'error');
            return;
          }
          if (data.requires_registration) {
            this.authToken = data.token;
            this.step = 'register';
            this.$nextTick(() => {
              const el = document.querySelector('[x-ref="register-first-name"]');
              if (el) el.focus();
            });
            return;
          }
          Alpine.store('auth').isLoggedIn = true;
          Alpine.store('auth').user = data.user || {};
          this.showNotification('خوش آمدید!', 'success');
          this.stopOTPTimer();
          window.location.href = '{{ route('landing.home') }}';
        } catch (e) {
          this.showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
          this.loading = false;
        }
      },

      async submitShahkar() {
        const firstName = this.register.firstName.trim();
        const lastName = this.register.lastName.trim();
        const nationalCode = this.register.nationalCode.replace(/\D/g, '');
        const { birthYear, birthMonth, birthDay } = this.register;

        if (firstName.length < 2) { this.showNotification('نام باید حداقل ۲ کاراکتر باشد', 'error'); return; }
        if (lastName.length < 2) { this.showNotification('نام خانوادگی باید حداقل ۲ کاراکتر باشد', 'error'); return; }
        if (nationalCode.length !== 10) { this.showNotification('کد ملی باید ۱۰ رقم باشد', 'error'); return; }
        if (!this.validateNationalCode(nationalCode)) { this.showNotification('کد ملی نامعتبر است', 'error'); return; }
        if (!birthYear || !birthMonth || !birthDay) { this.showNotification('تاریخ تولد را کامل وارد کنید', 'error'); return; }

        const birthDate = this.persianToGregorian(parseInt(birthYear), parseInt(birthMonth), parseInt(birthDay));
        this.loading = true;
        try {
          const res = await fetch('/api/auth/shahkar-verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
              token: this.authToken,
              first_name: firstName,
              last_name: lastName,
              national_code: nationalCode,
              birth_date: birthDate,
            }),
          });
          const data = await res.json();
          if (res.status !== 200) {
            this.showNotification(data.message || 'خطا در احراز هویت', 'error');
            return;
          }
          Alpine.store('auth').isLoggedIn = true;
          Alpine.store('auth').user = data.user || {};
          this.showNotification('احراز هویت با موفقیت انجام شد!', 'success');
          this.stopOTPTimer();
          window.location.href = '{{ route('landing.home') }}';
        } catch (e) {
          this.showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
          this.loading = false;
        }
      },

      validateNationalCode(code) {
        if (!/^\d{10}$/.test(code)) return false;
        const invalid = ['0000000000','1111111111','2222222222','3333333333','4444444444','5555555555','6666666666','7777777777','8888888888','9999999999'];
        if (invalid.includes(code)) return false;
        let sum = 0;
        for (let i = 0; i < 9; i++) sum += parseInt(code.charAt(i)) * (10 - i);
        const remainder = sum % 11;
        const check = parseInt(code.charAt(9));
        return remainder < 2 ? check === remainder : check === (11 - remainder);
      },

      persianToGregorian(py, pm, pd) {
        function isLeap(py) {
          const y = (py - 474) % 2820 + 474;
          const rem = y % 33;
          return rem === 1 || rem === 5 || rem === 9 || rem === 13 || rem === 17 || rem === 22 || rem === 26 || rem === 30;
        }
        function monthDays(py, pm) {
          if (pm <= 6) return 31;
          if (pm <= 11) return 30;
          return isLeap(py) ? 30 : 29;
        }
        let days = (py + 1595) * 365 + Math.floor((py + 1595) / 33) * 8 + Math.floor(((py + 1595) % 33 + 3) / 4) - 355668;
        for (let i = 1; i < pm; i++) days += monthDays(py, i);
        days += pd;
        let gy = 400 * Math.floor(days / 146097);
        days %= 146097;
        if (days > 36524) { days--; gy += 100 * Math.floor(days / 36524); days %= 36524; if (days >= 365) days++; }
        gy += 4 * Math.floor(days / 1461);
        days %= 1461;
        if (days > 365) { gy += Math.floor((days - 1) / 365); days = (days - 1) % 365; }
        let gDay = days + 1;
        const gDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) gDays[1] = 29;
        let gm = 1;
        for (let i = 0; i < 12; i++) {
          if (gDay <= gDays[i]) { gm = i + 1; break; }
          gDay -= gDays[i];
        }
        return gy + '-' + String(gm).padStart(2, '0') + '-' + String(gDay).padStart(2, '0');
      },

      resendOTP() {
        if (this.otpCountdown > 0) return;
        const cleaned = this.phone.replace(/\D/g, '');
        fetch('/api/auth/send-otp', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ phone: cleaned }),
        })
        .then(res => res.json())
        .then(data => {
          if (data.message) this.showNotification(data.message, 'success');
          this.otpExpiresAt = Date.now() + 180000;
          this.otpExpired = false;
          this.otpCountdown = 60;
        })
        .catch(() => this.showNotification('خطا در ارسال مجدد کد', 'error'));
      },

      startOTPTimer() {
        this.stopOTPTimer();
        this.otpTimer = setInterval(() => {
          this.otpCountdown--;
          if (this.otpRemaining <= 0 && this.otpCountdown <= 0) {
            this.otpExpired = true;
            this.stopOTPTimer();
          }
        }, 1000);
      },

      stopOTPTimer() {
        if (this.otpTimer) {
          clearInterval(this.otpTimer);
          this.otpTimer = null;
        }
      },

      goToPhoneStep() {
        this.step = 'phone';
        this.phone = '';
        this.authToken = '';
        this.otpExpiresAt = 0;
        this.otpExpired = false;
        this.otpInputs = ['', '', '', '', '', ''];
        this.stopOTPTimer();
      },

      goToOTPStep() {
        this.step = 'otp';
        this.authToken = '';
        this.register = { firstName: '', lastName: '', nationalCode: '', birthYear: '', birthMonth: '', birthDay: '' };
      },
    }
  }
</script>
@endpush
