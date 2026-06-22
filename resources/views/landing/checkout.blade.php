@extends('landing.layouts.app')

@section('title', 'تکمیل خرید | زیوتو')

@section('content')
<section class="container mx-auto px-4 py-12" x-data="checkoutForm()" x-init="init()">
  <template x-if="$store.cart.items.length === 0">
    <div class="text-center py-20">
      <p class="text-white/50">سبد خرید شما خالی است.</p>
      <a href="{{ route('landing.home') }}" class="btn-gold mt-4 inline-block px-8 py-3">مشاهده محصولات</a>
    </div>
  </template>

  <template x-if="$store.cart.items.length > 0">
    <div>
      <h1 class="text-3xl font-bold text-white mb-8">تکمیل خرید</h1>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20">
            <h3 class="text-xl font-bold text-white mb-6">اطلاعات شخصی</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label">نام و نام خانوادگی *</label>
                <input type="text" class="form-input" placeholder="نام کامل" x-model="form.name" :class="{ 'border-red-500': errors.name }">
                <p class="text-red-400 text-xs mt-1" x-show="errors.name" x-text="errors.name"></p>
              </div>
              <div>
                <label class="form-label">شماره موبایل *</label>
                <input type="tel" class="form-input" placeholder="۰۹۱۲XXXXXXX" dir="ltr" x-model="form.phone" :class="{ 'border-red-500': errors.phone }">
                <p class="text-red-400 text-xs mt-1" x-show="errors.phone" x-text="errors.phone"></p>
              </div>
              <div>
                <label class="form-label">کد ملی *</label>
                <input type="text" class="form-input" placeholder="XXXXXXXXXX" dir="ltr" x-model="form.nationalId" :class="{ 'border-red-500': errors.nationalId }">
                <p class="text-red-400 text-xs mt-1" x-show="errors.nationalId" x-text="errors.nationalId"></p>
              </div>
              <div>
                <label class="form-label">شماره کارمندی بانک ملی *</label>
                <input type="text" class="form-input" placeholder="شماره کارمندی" dir="ltr" x-model="form.employeeId" :class="{ 'border-red-500': errors.employeeId }">
                <p class="text-red-400 text-xs mt-1" x-show="errors.employeeId" x-text="errors.employeeId"></p>
              </div>
            </div>
          </div>

          <!-- Address Section for Logged-in Users -->
          <template x-if="$store.auth.isLoggedIn && addresses.length > 0">
            <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">آدرس ارسال</h3>
                <button @click="showAddressModal = true" class="text-zioto-gold text-sm hover:underline flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  آدرس جدید
                </button>
              </div>
              <div class="space-y-3">
                  <template x-for="addr in addresses" :key="addr.id">
                    <label @click="onAddressSelect(addr)"
                           class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all"
                         :class="selectedAddressId === addr.id ? 'border-zioto-gold bg-zioto-gold/10' : 'border-white/10 hover:border-white/20'">
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5"
                         :class="selectedAddressId === addr.id ? 'border-zioto-gold' : 'border-white/30'">
                      <div class="w-3 h-3 rounded-full" :class="selectedAddressId === addr.id ? 'bg-zioto-gold' : 'bg-transparent'"></div>
                    </div>
                    <div class="flex-1">
                      <div class="flex items-center gap-2 mb-1">
                        <span x-show="addr.label" class="bg-zioto-gold/20 text-zioto-gold text-xs px-2 py-0.5 rounded" x-text="addr.label"></span>
                        <span x-show="addr.is_default" class="bg-green-500/20 text-green-400 text-xs px-2 py-0.5 rounded">پیش‌فرض</span>
                      </div>
                      <p class="text-white/70 text-sm" x-text="(addr.province?.name || '') + '، ' + (addr.city?.name || '')"></p>
                      <p class="text-white/50 text-sm" x-text="addr.address_line"></p>
                    </div>
                  </label>
                </template>
              </div>
            </div>
          </template>

          <!-- Address Section for Guest or User without Addresses -->
          <template x-if="!$store.auth.isLoggedIn || addresses.length === 0">
            <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20">
              <h3 class="text-xl font-bold text-white mb-6">آدرس ارسال</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                <div>
                  <label class="form-label">منطقه / محله</label>
                  <input type="text" class="form-input" x-model="addressForm.district" placeholder="مثلاً مجیدیه">
                </div>
                <div>
                  <label class="form-label">کد پستی</label>
                  <input type="text" class="form-input" x-model="addressForm.postal_code" placeholder="۱۰ رقم" dir="ltr" maxlength="10">
                </div>
                <div class="md:col-span-2">
                  <label class="form-label">آدرس کامل *</label>
                  <textarea class="form-input" x-model="addressForm.address_line" rows="2" placeholder="خیابان، کوچه، پلاک، طبقه" required></textarea>
                </div>
                <div>
                  <label class="form-label">نام تحویل‌گیرنده</label>
                  <input type="text" class="form-input" x-model="addressForm.receiver_name">
                </div>
                <div>
                  <label class="form-label">تلفن تحویل‌گیرنده</label>
                  <input type="tel" class="form-input" x-model="addressForm.receiver_phone" dir="ltr">
                </div>
              </div>
            </div>
          </template>

          <!-- Shipping Method Selection -->
          <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20">
            <h3 class="text-xl font-bold text-white mb-6">روش ارسال</h3>
            <div x-show="!shippingMethods.length" class="text-white/50 text-sm py-4 text-center">
              لطفاً ابتدا آدرس ارسال را انتخاب کنید
            </div>
            <div class="space-y-3">
              <template x-for="method in shippingMethods" :key="method.id">
                <label @click="selectShippingMethod(method)"
                       class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all"
                       :class="selectedShippingMethodId === method.id ? 'border-zioto-gold bg-zioto-gold/10' : 'border-white/10 hover:border-white/20'">
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5"
                       :class="selectedShippingMethodId === method.id ? 'border-zioto-gold' : 'border-white/30'">
                    <div class="w-3 h-3 rounded-full" :class="selectedShippingMethodId === method.id ? 'bg-zioto-gold' : 'bg-transparent'"></div>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <span class="font-bold text-white" x-text="method.name"></span>
                      <span x-show="method.is_pickup" class="bg-amber-500/20 text-amber-400 text-xs px-2 py-0.5 rounded">حضوری</span>
                    </div>
                    <p class="text-white/50 text-sm" x-text="method.description"></p>
                    <div x-show="selectedShippingMethodId === method.id && shippingCalculation" class="mt-2 text-sm">
                      <div class="flex items-center gap-2 text-zioto-gold">
                        <span>هزینه ارسال:</span>
                        <span class="font-bold" x-text="formatPriceToman(shippingCalculation.shipping_cost)"></span>
                      </div>
                      <div x-show="shippingCalculation.estimated_min_days !== null" class="text-white/50">
                        <span>تخمین تحویل: </span>
                        <span x-text="toPersianNum(shippingCalculation.estimated_min_days) + ' تا ' + toPersianNum(shippingCalculation.estimated_max_days) + ' روز کاری'"></span>
                      </div>
                      <div x-show="shippingCalculation.has_insurance" class="text-amber-400 text-xs mt-1">
                        <span>هزینه بیمه: </span>
                        <span x-text="formatPriceToman(shippingCalculation.insurance_cost)"></span>
                      </div>
                      <div x-show="shippingCalculation.has_tax" class="text-red-400 text-xs mt-1">
                        <span>مالیات: </span>
                        <span x-text="formatPriceToman(shippingCalculation.tax_amount) + ' (' + toPersianNum(shippingCalculation.tax_rate) + '%)'"></span>
                      </div>
                    </div>
                    <div x-show="method.is_pickup" class="mt-2 text-xs text-white/50">
                      <p>آدرس دفتر شرکت: تهران، میدان ونک، خیابان ولیعصر، ساختمان زیوتو</p>
                      <p>ساعات کاری: شنبه تا چهارشنبه ۹ صبح تا ۱۷</p>
                    </div>
                  </div>
                  <div x-show="selectedShippingMethodId === method.id && shippingCalculation" class="text-left shrink-0">
                    <p class="text-zioto-gold font-bold" x-text="formatPriceToman(shippingCalculation.total_shipping_cost)"></p>
                  </div>
                </label>
              </template>
            </div>
          </div>

          <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20">
            <h3 class="text-xl font-bold text-white mb-6">روش پرداخت</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div @click="paymentMethod = 'installment'"
                   :class="paymentMethod === 'installment' ? 'border-zioto-gold bg-zioto-gold/10' : 'border-zioto-gold/20'"
                   class="payment-method border-2 rounded-xl p-4 cursor-pointer transition-all">
                <div class="flex items-center gap-3">
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                       :class="paymentMethod === 'installment' ? 'border-zioto-gold' : 'border-white/30'">
                    <div class="w-3 h-3 rounded-full" :class="paymentMethod === 'installment' ? 'bg-zioto-gold' : 'bg-transparent'"></div>
                  </div>
                  <div>
                    <p class="font-bold text-white">خرید اقساطی</p>
                    <p class="text-sm text-white/50">بانک ملی ایران</p>
                  </div>
                </div>
              </div>
              <div @click="paymentMethod = 'online'"
                   :class="paymentMethod === 'online' ? 'border-zioto-gold bg-zioto-gold/10' : 'border-zioto-gold/20'"
                   class="payment-method border-2 rounded-xl p-4 cursor-pointer transition-all">
                <div class="flex items-center gap-3">
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                       :class="paymentMethod === 'online' ? 'border-zioto-gold' : 'border-white/30'">
                    <div class="w-3 h-3 rounded-full" :class="paymentMethod === 'online' ? 'bg-zioto-gold' : 'bg-transparent'"></div>
                  </div>
                  <div>
                    <p class="font-bold text-white">پرداخت آنلاین</p>
                    <p class="text-sm text-white/50">پرداخت کامل نقدی</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <button @click="submitOrder()" class="btn-gold w-full text-lg py-4" x-text="submitting ? 'در حال ثبت سفارش...' : 'ثبت سفارش و پرداخت'"></button>
        </div>

        <div class="lg:col-span-1">
          <div class="bg-[#1A1D23] rounded-2xl p-6 border border-zioto-gold/20 sticky top-24">
            <h3 class="text-xl font-bold text-white mb-6">سفارش شما</h3>
            <div class="space-y-4 mb-6">
              <template x-for="item in $store.cart.items" :key="item.id">
                <div class="flex gap-3">
                  <img :src="item.image" :alt="item.name" class="w-14 aspect-[517/800] object-cover rounded-lg">
                  <div class="flex-1">
                    <p class="text-sm font-bold text-white" x-text="item.name"></p>
                    <p class="text-xs text-white/50" x-text="toPersianNum(item.quantity) + ' × ' + formatPriceToman(item.price)"></p>
                  </div>
                </div>
              </template>
            </div>
            <div class="border-t border-white/10 pt-4 space-y-2">
              <div class="flex justify-between text-white/70 text-sm">
                <span>مبلغ کالاها</span>
                <span x-text="formatPriceToman($store.cart.total)"></span>
              </div>
              <div x-show="shippingCalculation" class="flex justify-between text-white/70 text-sm">
                <span>هزینه ارسال</span>
                <span x-text="formatPriceToman(shippingCalculation.shipping_cost)"></span>
              </div>
              <div x-show="shippingCalculation?.has_tax" class="flex justify-between text-white/70 text-sm">
                <span>مالیات</span>
                <span x-text="formatPriceToman(shippingCalculation.tax_amount)"></span>
              </div>
              <div class="flex justify-between text-white font-bold text-lg pt-2 border-t border-white/10">
                <span>مبلغ قابل پرداخت</span>
                <span class="text-zioto-gold" x-text="formatPriceToman(($store.cart.total || 0) + (shippingCalculation?.total_shipping_cost || 0))"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>

  <!-- Add Address Modal (for logged-in users selecting new address at checkout) -->
  <div x-show="showAddressModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60" @click="showAddressModal = false"></div>
    <div class="relative bg-[#1A1D23] rounded-2xl border border-zioto-gold/20 w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-white">افزودن آدرس جدید</h3>
          <button @click="showAddressModal = false" class="p-2 rounded-lg text-white/50 hover:text-white hover:bg-white/5 transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <form @submit.prevent="saveNewAddress">
          <div class="space-y-4">
            <div>
              <label class="form-label">عنوان (اختیاری)</label>
              <input type="text" class="form-input" x-model="newAddressForm.label" placeholder="مثلاً خانه">
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">استان *</label>
                <select class="form-input" x-model="newAddressForm.province_id" @change="onModalProvinceChange()" required>
                  <option value="">انتخاب استان</option>
                  <template x-for="p in provinces" :key="p.id">
                    <option :value="p.id" x-text="p.name"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="form-label">شهر *</label>
                <select class="form-input" x-model="newAddressForm.city_id" :disabled="!newAddressForm.province_id" required>
                  <option value="">انتخاب شهر</option>
                  <template x-for="c in modalCities" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                  </template>
                </select>
              </div>
            </div>
            <div class="md:col-span-2">
              <label class="form-label">آدرس کامل *</label>
              <textarea class="form-input" x-model="newAddressForm.address_line" rows="2" required></textarea>
            </div>
            <div>
              <label class="form-label">کد پستی</label>
              <input type="text" class="form-input" x-model="newAddressForm.postal_code" dir="ltr" maxlength="10">
            </div>
          </div>
          <div class="flex items-center gap-3 mt-6">
            <button type="submit" class="btn-gold px-6 py-2" :disabled="newAddressSaving" x-text="newAddressSaving ? 'در حال ذخیره...' : 'ذخیره و انتخاب'"></button>
            <button type="button" @click="showAddressModal = false" class="px-6 py-2 rounded-xl border border-white/20 text-white/70 hover:text-white transition-all">انصراف</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  function checkoutForm() {
    return {
      form: { name: '', phone: '', nationalId: '', employeeId: '' },
      paymentMethod: 'installment',
      submitting: false,
      errors: {},

      addresses: [],
      selectedAddressId: null,
      provinces: [],
      cities: [],
      showAddressModal: false,
      modalCities: [],
      newAddressSaving: false,
      newAddressForm: { label: '', province_id: '', city_id: '', address_line: '', postal_code: '' },

      addressForm: { province_id: '', city_id: '', district: '', postal_code: '', address_line: '', receiver_name: '', receiver_phone: '' },

      shippingMethods: [],
      selectedShippingMethodId: null,
      shippingCalculation: null,
      shippingAddressSnapshot: null,

      async init() {
        await this.fetchProvinces();
        if ($store.auth.isLoggedIn) {
          await this.fetchAddresses();
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
        if (!provinceId) return [];
        try {
          const res = await fetch(`/api/locations/provinces/${provinceId}/cities`, { headers: { 'Accept': 'application/json' } });
          const data = await res.json();
          return data?.cities || [];
        } catch (e) { return []; }
      },

      async onProvinceChange() {
        this.cities = await this.fetchCities(this.addressForm.province_id);
        this.addressForm.city_id = '';
        this.fetchShippingMethods();
      },

      async onModalProvinceChange() {
        this.modalCities = await this.fetchCities(this.newAddressForm.province_id);
        this.newAddressForm.city_id = '';
      },

      async fetchAddresses() {
        try {
          const res = await fetch('/api/addresses', { headers: { 'Accept': 'application/json' } });
          const data = await res.json();
          if (data?.addresses) {
            this.addresses = data.addresses;
            const defaultAddr = this.addresses.find(a => a.is_default);
            if (defaultAddr) this.selectedAddressId = defaultAddr.id;
          }
        } catch (e) {}
      },

      async saveNewAddress() {
        if (!this.newAddressForm.province_id || !this.newAddressForm.city_id || !this.newAddressForm.address_line) {
          if (typeof showNotification === 'function') showNotification('لطفاً فیلدهای الزامی را پر کنید', 'error');
          return;
        }
        this.newAddressSaving = true;
        try {
          const res = await fetch('/api/addresses', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(this.newAddressForm),
          });
          const data = await res.json();
          if (res.status >= 400) {
            const msg = data?.errors ? Object.values(data.errors).flat().join('، ') : (data.message || 'خطا');
            if (typeof showNotification === 'function') showNotification(msg, 'error');
            return;
          }
          this.showAddressModal = false;
          this.newAddressForm = { label: '', province_id: '', city_id: '', address_line: '', postal_code: '' };
          await this.fetchAddresses();
          if (data?.address) this.selectedAddressId = data.address.id;
          if (typeof showNotification === 'function') showNotification('آدرس ذخیره شد', 'success');
        } catch (e) {
          if (typeof showNotification === 'function') showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
          this.newAddressSaving = false;
        }
      },

      onAddressSelect(addr) {
        this.selectedAddressId = addr.id;
        this.shippingAddressSnapshot = addr.full_address || (addr.province?.name || '') + '، ' + (addr.city?.name || '') + '، ' + addr.address_line;
        this.fetchShippingMethods();
      },

      async fetchShippingMethods() {
        try {
          const res = await fetch('/api/shipping/methods', { headers: { 'Accept': 'application/json' } });
          const data = await res.json();
          if (data?.methods) this.shippingMethods = data.methods;
        } catch (e) {}
      },

      async selectShippingMethod(method) {
        this.selectedShippingMethodId = method.id;
        this.shippingCalculation = null;
        await this.calculateShipping(method);
      },

      async calculateShipping(method) {
        const cartItems = ($store.cart.items || []).map(item => ({
          product_id: item.id,
          quantity: item.quantity,
          weight: item.weight || 10,
        }));
        const body = {
          shipping_method_id: method.id,
          cart_items: cartItems,
          cart_total: $store.cart.total || 0,
        };
        try {
          const res = await fetch('/api/shipping/calculate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body),
          });
          const data = await res.json();
          if (res.ok) {
            this.shippingCalculation = data;
          }
        } catch (e) {}
      },

      validate() {
        this.errors = {};
        if (!this.form.name || this.form.name.length < 3) this.errors.name = 'نام کامل را وارد کنید';
        if (!this.form.phone || !/^09\d{9}$/.test(this.form.phone.replace(/\D/g, ''))) this.errors.phone = 'شماره موبایل معتبر نیست';
        if (!this.form.nationalId || this.form.nationalId.replace(/\D/g, '').length !== 10) this.errors.nationalId = 'کد ملی ۱۰ رقمی را وارد کنید';
        if (!this.form.employeeId) this.errors.employeeId = 'شماره کارمندی را وارد کنید';
        if (!this.selectedShippingMethodId) {
          if (typeof showNotification === 'function') showNotification('لطفاً روش ارسال را انتخاب کنید', 'error');
          return false;
        }
        return Object.keys(this.errors).length === 0;
      },

      async submitOrder() {
        if (!this.validate()) {
          if (typeof showNotification === 'function') showNotification('لطفاً خطاهای فرم را برطرف کنید', 'error');
          return;
        }
        this.submitting = true;
        const cartItems = ($store.cart.items || []).map(item => ({
          product_id: item.id,
          quantity: item.quantity,
        }));
        const body = {
          name: this.form.name,
          phone: this.form.phone,
          national_id: this.form.nationalId,
          employee_id: this.form.employeeId,
          payment_method: this.paymentMethod,
          items: cartItems,
          shipping_method_id: this.selectedShippingMethodId,
          shipping_cost: this.shippingCalculation?.total_shipping_cost || 0,
        };
        if (this.selectedAddressId) {
          body.user_address_id = this.selectedAddressId;
        }
        if (this.shippingAddressSnapshot) {
          body.shipping_address_snapshot = this.shippingAddressSnapshot;
        }
        try {
          const res = await fetch('/api/orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body),
          });
          const data = await res.json();
          if (res.ok) {
            $store.cart.clear();
            if (typeof showNotification === 'function') showNotification('سفارش شما با موفقیت ثبت شد', 'success');
            setTimeout(() => {
              window.location.href = '{{ route('landing.success') }}?order=' + data.order.order_number;
            }, 1000);
          } else {
            const msg = data?.errors ? Object.values(data.errors).flat().join('، ') : (data.message || 'خطا در ثبت سفارش');
            if (typeof showNotification === 'function') showNotification(msg, 'error');
            this.submitting = false;
          }
        } catch (e) {
          if (typeof showNotification === 'function') showNotification('خطا در ارتباط با سرور', 'error');
          this.submitting = false;
        }
      },
    }
  }
</script>
@endpush
