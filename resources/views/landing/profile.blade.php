@extends('landing.layouts.app')

@section('title', 'پروفایل | زیوتو')

@section('content')
<section class="container mx-auto px-4 py-12" x-data="profileForm()" x-init="init()">
  <div x-show="!loading" class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <div class="lg:col-span-1">
      <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20 sticky top-24">
        <div class="text-center mb-6 pb-6 border-b border-white/10">
          <div class="w-20 h-20 bg-zioto-gold/20 rounded-full mx-auto mb-4 flex items-center justify-center">
            <svg class="w-10 h-10 text-zioto-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </div>
          <h3 class="font-bold text-white text-lg" x-text="$store.auth.user?.name || 'کاربر'"></h3>
          <p class="text-sm text-zioto-gold" x-text="$store.auth.user?.phone || ''"></p>
        </div>
        <nav class="space-y-2">
          <button @click="activeTab = 'settings'"
                  :class="activeTab === 'settings' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'"
                  class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>اطلاعات شخصی</span>
          </button>
        </nav>
        <hr class="border-white/10 my-4">
        <button @click="$store.auth.logout()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all text-red-400 hover:bg-red-500/10">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          <span>خروج از حساب</span>
        </button>
      </div>
    </div>

    <div class="lg:col-span-3">
      <template x-if="activeTab === 'settings'">
        <div>
          <div class="mb-6">
            <h2 class="text-2xl font-bold text-white mb-2">اطلاعات شخصی</h2>
            <p class="text-white/50">مدیریت اطلاعات شخصی و امنیت حساب</p>
          </div>
          <form @submit.prevent="saveProfile" class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20 mb-6">
            <h3 class="text-lg font-bold text-white mb-6">اطلاعات شخصی</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label">نام</label>
                <input type="text" class="form-input" x-model="form.firstName" required>
              </div>
              <div>
                <label class="form-label">نام خانوادگی</label>
                <input type="text" class="form-input" x-model="form.lastName" required>
              </div>
              <div>
                <label class="form-label">شماره موبایل</label>
                <input type="tel" class="form-input" :value="$store.auth.user?.phone || ''" dir="ltr" disabled>
              </div>
              <div>
                <label class="form-label">ایمیل</label>
                <input type="email" class="form-input" x-model="form.email" dir="ltr">
              </div>
              <div>
                <label class="form-label">کد ملی</label>
                <input type="text" class="form-input" :value="form.nationalCode" dir="ltr" disabled>
              </div>
              <div>
                <label class="form-label">تاریخ تولد</label>
                <div class="grid grid-cols-3 gap-2" dir="ltr">
                  <select class="form-input" x-model="form.birthYear">
                    <option value="">سال</option>
                    @for ($y = 1344; $y <= 1404; $y++)
                      <option value="{{ $y }}">{{ number_format($y, 0, '', '') }}</option>
                    @endfor
                  </select>
                  <select class="form-input" x-model="form.birthMonth">
                    <option value="">ماه</option>
                    @foreach (['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'] as $i => $m)
                      <option value="{{ $i + 1 }}">{{ $m }}</option>
                    @endforeach
                  </select>
                  <select class="form-input" x-model="form.birthDay">
                    <option value="">روز</option>
                    @for ($d = 1; $d <= 31; $d++)
                      <option value="{{ $d }}">{{ number_format($d, 0, '', '') }}</option>
                    @endfor
                  </select>
                </div>
              </div>
            </div>
            <button type="submit" class="btn-gold mt-6 px-6 py-2" x-text="saving ? 'در حال ذخیره...' : 'ذخیره تغییرات'" :disabled="saving"></button>
          </form>
        </div>
      </template>
    </div>
  </div>

  <div x-show="loading" class="flex items-center justify-center min-h-[60vh]">
    <div class="text-center">
      <div class="w-12 h-12 border-4 border-zioto-gold/20 border-t-zioto-gold rounded-full animate-spin mx-auto mb-4"></div>
      <p class="text-white/50">در حال بارگذاری...</p>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  function profileForm() {
    return {
      loading: true,
      saving: false,
      activeTab: 'settings',
      form: {
        firstName: '',
        lastName: '',
        email: '',
        nationalCode: '',
        birthYear: '',
        birthMonth: '',
        birthDay: '',
      },

      async init() {
        if (!Alpine.store('auth').isLoggedIn) {
          window.location.href = '{{ route('landing.login') }}';
          return;
        }
        await this.fetchProfile();
        this.loading = false;
      },

      async fetchProfile() {
        try {
          const res = await fetch('/api/profile', { headers: { 'Accept': 'application/json' } });
          if (res.status === 401) {
            Alpine.store('auth').isLoggedIn = false;
            window.location.href = '{{ route('landing.login') }}';
            return;
          }
          const data = await res.json();
          if (data?.user) {
            const u = data.user;
            Alpine.store('auth').user = u;
            this.form.firstName = u.first_name || '';
            this.form.lastName = u.last_name || '';
            this.form.email = u.email || '';
            this.form.nationalCode = u.national_id || '';
            if (u.birth_date) {
              const parts = this.parseBirthDate(u.birth_date);
              this.form.birthYear = parts.year;
              this.form.birthMonth = parts.month;
              this.form.birthDay = parts.day;
            }
          }
        } catch (e) {
          if (typeof showNotification === 'function') showNotification('خطا در دریافت اطلاعات کاربر', 'error');
        }
      },

      parseBirthDate(gregorianDate) {
        if (!gregorianDate) return { year: '', month: '', day: '' };
        const d = new Date(gregorianDate);
        if (isNaN(d.getTime())) return { year: '', month: '', day: '' };
        const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn', { year: 'numeric', month: 'numeric', day: 'numeric' });
        const parts = formatter.formatToParts(d);
        const result = { year: '', month: '', day: '' };
        for (const p of parts) {
          if (p.type === 'year') result.year = p.value;
          if (p.type === 'month') result.month = p.value;
          if (p.type === 'day') result.day = p.value;
        }
        return result;
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

      async saveProfile(e) {
        e.preventDefault();
        if (this.form.firstName.length < 2) {
          if (typeof showNotification === 'function') showNotification('نام باید حداقل ۲ کاراکتر باشد', 'error');
          return;
        }
        if (this.form.lastName.length < 2) {
          if (typeof showNotification === 'function') showNotification('نام خانوادگی باید حداقل ۲ کاراکتر باشد', 'error');
          return;
        }
        this.saving = true;
        try {
          let birthDate = null;
          if (this.form.birthYear && this.form.birthMonth && this.form.birthDay) {
            birthDate = this.persianToGregorian(parseInt(this.form.birthYear), parseInt(this.form.birthMonth), parseInt(this.form.birthDay));
          }
          const res = await fetch('/api/profile', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
              first_name: this.form.firstName,
              last_name: this.form.lastName,
              email: this.form.email || null,
              birth_date: birthDate,
            }),
          });
          const data = await res.json();
          if (res.status !== 200) {
            const msg = data?.errors ? Object.values(data.errors).flat().join('، ') : (data.message || 'خطا در ذخیره اطلاعات');
            if (typeof showNotification === 'function') showNotification(msg, 'error');
            return;
          }
          if (data?.user) Alpine.store('auth').user = data.user;
          if (typeof showNotification === 'function') showNotification(data.message || 'اطلاعات با موفقیت بروزرسانی شد', 'success');
        } catch (e) {
          if (typeof showNotification === 'function') showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
          this.saving = false;
        }
      },
    }
  }
</script>
@endpush
