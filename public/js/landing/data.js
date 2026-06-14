const PRODUCTS = [
  {
    id: 1,
    name: "شمش طلای ۱ گرمی",
    weight: "1 گرم",
    purity: "۹۹۹.۹",
    category: "gold",
    price: 3500000,
    originalPrice: 3650000,
    image: "https://placehold.co/600x400/1B4332/C8A84E?text=شمش+طلای+۱+گرمی",
    description: "شمش طلای خالص با عیار ۹۹۹.۹ مناسب برای سرمایه‌گذاری و هدیه. گواهی اصالت و از بورس فلزات تهران.",
    badge: "پرفروش",
    installment: "امکان خرید اقساطی"
  },
  {
    id: 2,
    name: "شمش طلای ۲.۵ گرمی",
    weight: "۲.۵ گرم",
    purity: "۹۹۹.۹",
    category: "gold",
    price: 8750000,
    originalPrice: 9100000,
    image: "https://placehold.co/600x400/1B4332/C8A84E?text=شمش+طلای+۲.۵+گرمی",
    description: "شمش طلای خالص با عیار ۹۹۹.۹ مناسب برای سرمایه‌گذاری بلندمدت. بسته‌بندی شکیل با کد رهگیری.",
    badge: "جدید",
    installment: "امکان خرید اقساطی"
  },
  {
    id: 3,
    name: "شمش طلای ۵ گرمی",
    weight: "۵ گرم",
    purity: "۹۹۹.۹",
    category: "gold",
    price: 17500000,
    originalPrice: 18200000,
    image: "https://placehold.co/600x400/1B4332/C8A84E?text=شمش+طلای+۵+گرمی",
    description: "شمش طلای ۵ گرمی با خلوص ۹۹۹.۹ مناسب سرمایه‌گذاری و ذخیره ارزش. ارسال رایگان با بیمه.",
    badge: "ویژه",
    installment: "امکان خرید اقساطی"
  },
  {
    id: 4,
    name: "شمش طلای ۱۰ گرمی",
    weight: "۱۰ گرم",
    purity: "۹۹۹.۹",
    category: "gold",
    price: 35000000,
    originalPrice: 36400000,
    image: "https://placehold.co/600x400/1B4332/C8A84E?text=شمش+طلای+۱۰+گرمی",
    description: "شمش طلای ۱۰ گرمی خالص با گواهی اصالت. بهترین گزینه برای سرمایه‌گذاری مطمئن.",
    badge: "",
    installment: "امکان خرید اقساطی"
  },
  {
    id: 5,
    name: "شمش طلای ۲۰ گرمی",
    weight: "۲۰ گرم",
    purity: "۹۹۹.۹",
    category: "gold",
    price: 70000000,
    originalPrice: 72800000,
    image: "https://placehold.co/600x400/1B4332/C8A84E?text=شمش+طلای+۲۰+گرمی",
    description: "شمش طلای ۲۰ گرمی با خلوص بالا، مناسب سرمایه‌گذاران حرفه‌ای. بسته‌بندی ایمن با شماره سریال.",
    badge: "",
    installment: "امکان خرید اقساطی"
  },
  {
    id: 6,
    name: "شمش طلای ۵۰ گرمی",
    weight: "۵۰ گرم",
    purity: "۹۹۹.۹",
    category: "gold",
    price: 175000000,
    originalPrice: 182000000,
    image: "https://placehold.co/600x400/1B4332/C8A84E?text=شمش+طلای+۵۰+گرمی",
    description: "شمش طلای ۵۰ گرمی خالص با استاندارد بین‌المللی. گزینه‌ای ایده‌آل برای حفظ ارزش دارایی.",
    badge: "پیشنهاد ویژه",
    installment: "امکان خرید اقساطی"
  },
  {
    id: 7,
    name: "شمش نقره ۱۰ گرمی",
    weight: "۱۰ گرم",
    purity: "۹۹۹",
    category: "silver",
    price: 450000,
    originalPrice: 480000,
    image: "https://placehold.co/600x400/2D6A4F/C8A84E?text=شمش+نقره+۱۰+گرمی",
    description: "شمش نقره خالص با عیار ۹۹۹ مناسب برای شروع سرمایه‌گذاری. قیمت مناسب و ارزشمند.",
    badge: "اقتصادی",
    installment: "امکان خرید اقساطی"
  },
  {
    id: 8,
    name: "شمش نقره ۱۰۰ گرمی",
    weight: "۱۰۰ گرم",
    purity: "۹۹۹",
    category: "silver",
    price: 4200000,
    originalPrice: 4500000,
    image: "https://placehold.co/600x400/2D6A4F/C8A84E?text=شمش+نقره+۱۰۰+گرمی",
    description: "شمش نقره ۱۰۰ گرمی با خلوص بالا. مناسب سرمایه‌گذاری بلندمدت و تنوع سبد دارایی.",
    badge: "",
    installment: "امکان خرید اقساطی"
  },
  {
    id: 9,
    name: "شمش نقره ۱ کیلوگرمی",
    weight: "۱ کیلوگرم",
    purity: "۹۹۹",
    category: "silver",
    price: 40000000,
    originalPrice: 43000000,
    image: "https://placehold.co/600x400/2D6A4F/C8A84E?text=شمش+نقره+۱+کیلویی",
    description: "شمش نقره ۱ کیلوگرمی با استاندارد بین‌المللی. بهترین انتخاب برای سرمایه‌گذاری سنگین نقره.",
    badge: "ویژه",
    installment: "امکان خرید اقساطی"
  }
];

function formatPrice(price) {
  return new Intl.NumberFormat('fa-IR').format(price) + ' ریال';
}

function formatPriceToman(price) {
  return new Intl.NumberFormat('fa-IR').format(price / 10) + ' تومان';
}

function getDiscountPercent(original, current) {
  return Math.round(((original - current) / original) * 100);
}
