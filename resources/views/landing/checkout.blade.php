@extends('landing.layouts.app')

@section('title', 'تکمیل خرید | زیوتو')

@section('content')
<section class="container mx-auto px-4 py-12" x-data="checkoutForm()">
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
            <div class="border-t border-white/10 pt-4">
              <div class="flex justify-between text-white font-bold text-lg">
                <span>مبلغ نهایی</span>
                <span class="text-zioto-gold" x-text="formatPriceToman($store.cart.total)"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
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

      validate() {
        this.errors = {};
        if (!this.form.name || this.form.name.length < 3) this.errors.name = 'نام کامل را وارد کنید';
        if (!this.form.phone || !/^09\d{9}$/.test(this.form.phone.replace(/\D/g, ''))) this.errors.phone = 'شماره موبایل معتبر نیست';
        if (!this.form.nationalId || this.form.nationalId.replace(/\D/g, '').length !== 10) this.errors.nationalId = 'کد ملی ۱۰ رقمی را وارد کنید';
        if (!this.form.employeeId) this.errors.employeeId = 'شماره کارمندی را وارد کنید';
        return Object.keys(this.errors).length === 0;
      },

      submitOrder() {
        if (!this.validate()) {
          if (typeof showNotification === 'function') showNotification('لطفاً خطاهای فرم را برطرف کنید', 'error');
          return;
        }
        this.submitting = true;
        if (typeof showNotification === 'function') showNotification('در حال ثبت سفارش...', 'info');
        setTimeout(() => {
          window.location.href = '{{ route('landing.success') }}';
        }, 1500);
      },
    }
  }
</script>
@endpush
