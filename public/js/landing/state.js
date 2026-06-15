const STATE = {
  currentPage: 'home',
  selectedProduct: null,
  selectedPaymentMethod: 'installment',
  activeProfileTab: 'orders',
  quantity: 1,

  isLoggedIn: false,
  authStep: 'phone',
  authPhone: '',
  authToken: '',
  otpCountdown: 0,
  otpTimer: null,

  cart: [],

  userData: {
    name: 'علی محمدی',
    phone: '۰۹۱۲۱۲۳۴۵۶۷',
    email: 'ali.mohammadi@email.com',
    nationalId: '۱۲۳۴۵۶۷۸۹۰',
    employeeId: 'BM-۱۲۳۴۵',
    joinDate: '۱۴۰۳/۰۳/۱۵',
    avatar: null,
  },

  ordersData: [
    { id: 'ZT-1A2B3C', date: '۱۴۰۴/۰۳/۱۰', status: 'success', statusText: 'تحویل شده', items: [{ name: 'شمش طلای ۵ گرمی', quantity: 1, price: 17500000 }], total: 17500000, paymentMethod: 'installment', trackingCode: '۱۲۳۴۵۶۷۸۹۰۱۲' },
    { id: 'ZT-4D5E6F', date: '۱۴۰۴/۰۲/۲۰', status: 'pending', statusText: 'در حال پردازش', items: [{ name: 'شمش نقره ۱۰۰ گرمی', quantity: 2, price: 4200000 }, { name: 'شمش طلای ۱ گرمی', quantity: 3, price: 3500000 }], total: 18900000, paymentMethod: 'online', trackingCode: null },
    { id: 'ZT-7G8H9I', date: '۱۴۰۴/۰۱/۰۵', status: 'failed', statusText: 'ناموفق', items: [{ name: 'شمش طلای ۱۰ گرمی', quantity: 1, price: 35000000 }], total: 35000000, paymentMethod: 'installment', trackingCode: null, failReason: 'عدم تایید اطلاعات کارمندی' },
    { id: 'ZT-J1K2L3', date: '۱۴۰۳/۱۲/۱۸', status: 'success', statusText: 'تحویل شده', items: [{ name: 'شمش طلای ۲.۵ گرمی', quantity: 2, price: 8750000 }, { name: 'شمش نقره ۱ کیلوگرمی', quantity: 1, price: 40000000 }], total: 57500000, paymentMethod: 'installment', trackingCode: '۹۸۷۶۵۴۳۲۱۰۱۲' },
    { id: 'ZT-M4N5O6', date: '۱۴۰۳/۱۱/۱۰', status: 'cancelled', statusText: 'لغو شده', items: [{ name: 'شمش طلای ۲۰ گرمی', quantity: 1, price: 70000000 }], total: 70000000, paymentMethod: 'online', trackingCode: null },
  ],

  paymentsData: [
    { id: 'PAY-001', orderId: 'ZT-1A2B3C', date: '۱۴۰۴/۰۳/۱۰', amount: 17500000, status: 'success', statusText: 'موفق', method: 'اقساطی - قسط اول', installments: { current: 1, total: 12 } },
    { id: 'PAY-002', orderId: 'ZT-1A2B3C', date: '۱۴۰۴/۰۴/۱۰', amount: 1458333, status: 'success', statusText: 'موفق', method: 'اقساطی - قسط دوم', installments: { current: 2, total: 12 } },
    { id: 'PAY-003', orderId: 'ZT-4D5E6F', date: '۱۴۰۴/۰۲/۲۰', amount: 18900000, status: 'pending', statusText: 'در انتظار', method: 'پرداخت آنلاین', installments: null },
    { id: 'PAY-004', orderId: 'ZT-7G8H9I', date: '۱۴۰۴/۰۱/۰۵', amount: 35000000, status: 'failed', statusText: 'ناموفق', method: 'اقساطی', installments: null },
    { id: 'PAY-005', orderId: 'ZT-J1K2L3', date: '۱۴۰۳/۱۲/۱۸', amount: 57500000, status: 'success', statusText: 'موفق', method: 'اقساطی - قسط اول', installments: { current: 1, total: 6 } },
    { id: 'PAY-006', orderId: 'ZT-1A2B3C', date: '۱۴۰۴/۰۵/۱۰', amount: 1458333, status: 'success', statusText: 'موفق', method: 'اقساطی - قسط سوم', installments: { current: 3, total: 12 } },
  ],
};
