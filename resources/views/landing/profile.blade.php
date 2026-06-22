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
          <button @click="activeTab = 'addresses'"
                  :class="activeTab === 'addresses' ? 'bg-zioto-gold/20 text-zioto-gold' : 'text-white/70 hover:bg-white/5 hover:text-white'"
                  class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>آدرس‌ها</span>
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

      <template x-if="activeTab === 'addresses'">
        <div>
          <div class="flex items-center justify-between mb-6">
            <div>
              <h2 class="text-2xl font-bold text-white mb-2">آدرس‌ها</h2>
              <p class="text-white/50">مدیریت آدرس‌های ارسال</p>
            </div>
            <button @click="openAddressModal()" class="btn-gold px-4 py-2 text-sm flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              افزودن آدرس جدید
            </button>
          </div>

          <template x-if="addresses.length === 0">
            <div class="bg-[#1A1D23] rounded-2xl p-12 border border-zioto-gold/20 text-center">
              <svg class="w-16 h-16 text-white/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <p class="text-white/50 mb-4">هنوز آدرسی ثبت نشده است</p>
              <button @click="openAddressModal()" class="btn-gold px-6 py-2">افزودن آدرس جدید</button>
            </div>
          </template>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="addr in addresses" :key="addr.id">
              <div class="bg-[#1A1D23] rounded-2xl p-5 border transition-all"
                   :class="addr.is_default ? 'border-zioto-gold/40' : 'border-white/10'">
                <div class="flex items-start justify-between mb-3">
                  <div class="flex items-center gap-2">
                    <span x-show="addr.label" class="bg-zioto-gold/20 text-zioto-gold text-xs px-2 py-1 rounded-lg" x-text="addr.label"></span>
                    <span x-show="addr.is_default" class="bg-green-500/20 text-green-400 text-xs px-2 py-1 rounded-lg">پیش‌فرض</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <button @click="setDefault(addr)" x-show="!addr.is_default" class="p-1.5 rounded-lg text-white/50 hover:text-zioto-gold hover:bg-white/5 transition-all" title="تنظیم به عنوان پیش‌فرض">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                    <button @click="editAddress(addr)" class="p-1.5 rounded-lg text-white/50 hover:text-blue-400 hover:bg-white/5 transition-all" title="ویرایش">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button @click="deleteAddress(addr)" class="p-1.5 rounded-lg text-white/50 hover:text-red-400 hover:bg-red-500/10 transition-all" title="حذف">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </div>
                <div class="space-y-1 text-sm">
                  <p class="text-white/70" x-text="(addr.province?.name || '') + '، ' + (addr.city?.name || '')"></p>
                  <p x-show="addr.district" class="text-white/50" x-text="'منطقه: ' + addr.district"></p>
                  <p class="text-white/70" x-text="addr.address_line"></p>
                  <p x-show="addr.postal_code" class="text-white/50" x-text="'کد پستی: ' + addr.postal_code"></p>
                  <p x-show="addr.receiver_name" class="text-white/50" x-text="'تحویل‌گیرنده: ' + addr.receiver_name"></p>
                  <p x-show="addr.receiver_phone" class="text-white/50" x-text="'تلفن: ' + addr.receiver_phone"></p>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>
    </div>
  </div>

  <!-- Address Modal -->
  <div x-show="showAddressModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="absolute inset-0 bg-black/60" @click="closeAddressModal()"></div>
    <div class="relative bg-[#1A1D23] rounded-2xl border border-zioto-gold/20 w-full max-w-lg max-h-[90vh] overflow-y-auto" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-white" x-text="editingAddress ? 'ویرایش آدرس' : 'افزودن آدرس جدید'"></h3>
          <button @click="closeAddressModal()" class="p-2 rounded-lg text-white/50 hover:text-white hover:bg-white/5 transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <form @submit.prevent="saveAddress">
          <div class="space-y-4">
            <div>
              <label class="form-label">عنوان (اختیاری)</label>
              <input type="text" class="form-input" x-model="addressForm.label" placeholder="مثلاً خانه، محل کار">
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">استان *</label>
                <select class="form-input" x-model="addressForm.province_id" @change="onProvinceChange()" required>
                  <option value="">انتخاب استان</option>
                  <template x-for="p in provinces" :key="p.id">
                    <option :value="p.id" x-text="p.name"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="form-label">شهر *</label>
                <select class="form-input" x-model="addressForm.city_id" :disabled="!addressForm.province_id" required>
                  <option value="">انتخاب شهر</option>
                  <template x-for="c in cities" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                  </template>
                </select>
              </div>
            </div>

            <div>
              <label class="form-label">منطقه / محله (اختیاری)</label>
              <input type="text" class="form-input" x-model="addressForm.district" placeholder="مثلاً مجیدیه">
            </div>

            <div>
              <label class="form-label">آدرس کامل *</label>
              <textarea class="form-input" x-model="addressForm.address_line" rows="3" placeholder="خیابان، کوچه، پلاک، طبقه" required></textarea>
            </div>

            <div>
              <label class="form-label">کد پستی (اختیاری)</label>
              <input type="text" class="form-input" x-model="addressForm.postal_code" placeholder="۱۰ رقم" dir="ltr" maxlength="10">
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">نام تحویل‌گیرنده (اختیاری)</label>
                <input type="text" class="form-input" x-model="addressForm.receiver_name" placeholder="برای هدیه">
              </div>
              <div>
                <label class="form-label">تلفن تحویل‌گیرنده (اختیاری)</label>
                <input type="tel" class="form-input" x-model="addressForm.receiver_phone" placeholder="۰۹۱۲XXXXXXX" dir="ltr">
              </div>
            </div>

            <div class="flex items-center gap-6">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" x-model="addressForm.is_default" class="w-4 h-4 rounded border-white/30 bg-white/10 text-zioto-gold focus:ring-zioto-gold">
                <span class="text-sm text-white/70">آدرس پیش‌فرض</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" x-model="addressForm.is_billing" class="w-4 h-4 rounded border-white/30 bg-white/10 text-zioto-gold focus:ring-zioto-gold">
                <span class="text-sm text-white/70">آدرس صورتحساب</span>
              </label>
            </div>
          </div>

          <div class="flex items-center gap-3 mt-6">
            <button type="submit" class="btn-gold px-6 py-2" :disabled="addressSaving" x-text="addressSaving ? 'در حال ذخیره...' : 'ذخیره آدرس'"></button>
            <button type="button" @click="closeAddressModal()" class="px-6 py-2 rounded-xl border border-white/20 text-white/70 hover:text-white hover:bg-white/5 transition-all">انصراف</button>
          </div>
        </form>
      </div>
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

      addresses: [],
      provinces: [],
      cities: [],
      showAddressModal: false,
      editingAddress: null,
      addressSaving: false,
      addressForm: {
        label: '',
        province_id: '',
        city_id: '',
        district: '',
        address_line: '',
        postal_code: '',
        receiver_name: '',
        receiver_phone: '',
        is_default: false,
        is_billing: false,
      },

      async init() {
        if (!Alpine.store('auth').isLoggedIn) {
          window.location.href = '{{ route('landing.login') }}';
          return;
        }
        await this.fetchProfile();
        await this.fetchProvinces();
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

      async fetchProvinces() {
        try {
          const res = await fetch('/api/locations/provinces', { headers: { 'Accept': 'application/json' } });
          const data = await res.json();
          if (data?.provinces) this.provinces = data.provinces;
        } catch (e) {}
      },

      async fetchCities(provinceId) {
        if (!provinceId) { this.cities = []; return; }
        try {
          const res = await fetch(`/api/locations/provinces/${provinceId}/cities`, { headers: { 'Accept': 'application/json' } });
          const data = await res.json();
          if (data?.cities) this.cities = data.cities;
        } catch (e) { this.cities = []; }
      },

      async fetchAddresses() {
        try {
          const res = await fetch('/api/addresses', { headers: { 'Accept': 'application/json' } });
          if (res.status === 401) return;
          const data = await res.json();
          if (data?.addresses) this.addresses = data.addresses;
        } catch (e) {}
      },

      async onProvinceChange() {
        this.addressForm.city_id = '';
        await this.fetchCities(this.addressForm.province_id);
      },

      openAddressModal(address = null) {
        this.editingAddress = address;
        if (address) {
          this.addressForm = {
            label: address.label || '',
            province_id: address.province_id,
            city_id: address.city_id,
            district: address.district || '',
            address_line: address.address_line,
            postal_code: address.postal_code || '',
            receiver_name: address.receiver_name || '',
            receiver_phone: address.receiver_phone || '',
            is_default: address.is_default,
            is_billing: address.is_billing,
          };
          this.fetchCities(address.province_id);
        } else {
          this.addressForm = { label: '', province_id: '', city_id: '', district: '', address_line: '', postal_code: '', receiver_name: '', receiver_phone: '', is_default: false, is_billing: false };
          this.cities = [];
        }
        this.showAddressModal = true;
      },

      editAddress(addr) {
        this.openAddressModal(addr);
      },

      closeAddressModal() {
        this.showAddressModal = false;
        this.editingAddress = null;
      },

      async saveAddress() {
        if (!this.addressForm.province_id || !this.addressForm.city_id || !this.addressForm.address_line) {
          if (typeof showNotification === 'function') showNotification('لطفاً فیلدهای الزامی را پر کنید', 'error');
          return;
        }
        if (this.addressForm.address_line.length < 10) {
          if (typeof showNotification === 'function') showNotification('آدرس باید حداقل ۱۰ کاراکتر باشد', 'error');
          return;
        }
        if (this.addressForm.postal_code && !/^\d{10}$/.test(this.addressForm.postal_code)) {
          if (typeof showNotification === 'function') showNotification('کد پستی باید ۱۰ رقم باشد', 'error');
          return;
        }
        if (this.addressForm.receiver_phone && !/^09\d{9}$/.test(this.addressForm.receiver_phone)) {
          if (typeof showNotification === 'function') showNotification('شماره تلفن معتبر نیست', 'error');
          return;
        }
        this.addressSaving = true;
        try {
          const url = this.editingAddress ? `/api/addresses/${this.editingAddress.id}` : '/api/addresses';
          const method = this.editingAddress ? 'PUT' : 'POST';
          const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(this.addressForm),
          });
          const data = await res.json();
          if (res.status >= 400) {
            const msg = data?.errors ? Object.values(data.errors).flat().join('، ') : (data.message || 'خطا در ذخیره آدرس');
            if (typeof showNotification === 'function') showNotification(msg, 'error');
            return;
          }
          if (typeof showNotification === 'function') showNotification(data.message || 'آدرس با موفقیت ذخیره شد', 'success');
          this.closeAddressModal();
          await this.fetchAddresses();
        } catch (e) {
          if (typeof showNotification === 'function') showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
          this.addressSaving = false;
        }
      },

      async deleteAddress(addr) {
        if (!confirm('آیا از حذف این آدرس مطمئن هستید؟')) return;
        try {
          const res = await fetch(`/api/addresses/${addr.id}`, { method: 'DELETE', headers: { 'Accept': 'application/json' } });
          const data = await res.json();
          if (typeof showNotification === 'function') showNotification(data.message || 'آدرس حذف شد', 'success');
          await this.fetchAddresses();
        } catch (e) {
          if (typeof showNotification === 'function') showNotification('خطا در حذف آدرس', 'error');
        }
      },

      async setDefault(addr) {
        try {
          const res = await fetch(`/api/addresses/${addr.id}/default`, { method: 'PUT', headers: { 'Accept': 'application/json' } });
          const data = await res.json();
          if (typeof showNotification === 'function') showNotification(data.message || 'آدرس پیش‌فرض تنظیم شد', 'success');
          await this.fetchAddresses();
        } catch (e) {
          if (typeof showNotification === 'function') showNotification('خطا در تنظیم آدرس پیش‌فرض', 'error');
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
