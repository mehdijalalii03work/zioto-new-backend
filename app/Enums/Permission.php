<?php

namespace App\Enums;

enum Permission: string
{
    case DashboardView = 'dashboard.view';

    case ProductView = 'product.view';

    case ProductCreate = 'product.create';

    case ProductEdit = 'product.edit';

    case ProductDelete = 'product.delete';

    case ProductPricing = 'product.pricing';

    case CategoryView = 'category.view';

    case CategoryCreate = 'category.create';

    case CategoryEdit = 'category.edit';

    case CategoryDelete = 'category.delete';

    case BrandView = 'brand.view';

    case BrandCreate = 'brand.create';

    case BrandEdit = 'brand.edit';

    case BrandDelete = 'brand.delete';

    case OrderView = 'order.view';

    case OrderCreate = 'order.create';

    case OrderEdit = 'order.edit';

    case OrderDelete = 'order.delete';

    case PaymentView = 'payment.view';

    case PaymentCreate = 'payment.create';

    case PaymentEdit = 'payment.edit';

    case PaymentDelete = 'payment.delete';

    case CustomerView = 'customer.view';

    case CustomerCreate = 'customer.create';

    case CustomerEdit = 'customer.edit';

    case CustomerDelete = 'customer.delete';

    case ShippingView = 'shipping.view';

    case ShippingCreate = 'shipping.create';

    case ShippingEdit = 'shipping.edit';

    case ShippingDelete = 'shipping.delete';

    case SettingView = 'setting.view';

    case BlogPostView = 'blog-post.view';

    case BlogPostCreate = 'blog-post.create';

    case BlogPostEdit = 'blog-post.edit';

    case BlogPostDelete = 'blog-post.delete';

    case BlogCategoryView = 'blog-category.view';

    case BlogCategoryCreate = 'blog-category.create';

    case BlogCategoryEdit = 'blog-category.edit';

    case BlogCategoryDelete = 'blog-category.delete';

    case BlogTagView = 'blog-tag.view';

    case BlogTagCreate = 'blog-tag.create';

    case BlogTagEdit = 'blog-tag.edit';

    case BlogTagDelete = 'blog-tag.delete';

    case ContactMessageView = 'contact-message.view';

    case ContactMessageEdit = 'contact-message.edit';

    case ContactMessageDelete = 'contact-message.delete';

    case HesabfaView = 'hesabfa.view';

    case HesabfaSync = 'hesabfa.sync';

    case ManagementReportView = 'management-report.view';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'مشاهده داشبورد',
            self::ProductView => 'مشاهده محصولات',
            self::ProductCreate => 'ایجاد محصول',
            self::ProductEdit => 'ویرایش محصول',
            self::ProductDelete => 'حذف محصول',
            self::ProductPricing => 'مدیریت قیمت و موجودی',
            self::CategoryView => 'مشاهده دسته‌بندی‌ها',
            self::CategoryCreate => 'ایجاد دسته‌بندی',
            self::CategoryEdit => 'ویرایش دسته‌بندی',
            self::CategoryDelete => 'حذف دسته‌بندی',
            self::BrandView => 'مشاهده برندها',
            self::BrandCreate => 'ایجاد برند',
            self::BrandEdit => 'ویرایش برند',
            self::BrandDelete => 'حذف برند',
            self::OrderView => 'مشاهده سفارش‌ها',
            self::OrderCreate => 'ایجاد سفارش',
            self::OrderEdit => 'ویرایش سفارش',
            self::OrderDelete => 'حذف سفارش',
            self::PaymentView => 'مشاهده پرداخت‌ها',
            self::PaymentCreate => 'ایجاد پرداخت',
            self::PaymentEdit => 'ویرایش پرداخت',
            self::PaymentDelete => 'حذف پرداخت',
            self::CustomerView => 'مشاهده مشتریان',
            self::CustomerCreate => 'ایجاد مشتری',
            self::CustomerEdit => 'ویرایش مشتری',
            self::CustomerDelete => 'حذف مشتری',
            self::ShippingView => 'مشاهده روش‌های ارسال',
            self::ShippingCreate => 'ایجاد روش ارسال',
            self::ShippingEdit => 'ویرایش روش ارسال',
            self::ShippingDelete => 'حذف روش ارسال',
            self::SettingView => 'مشاهده و ویرایش تنظیمات',
            self::BlogPostView => 'مشاهده نوشته‌های وبلاگ',
            self::BlogPostCreate => 'ایجاد نوشته وبلاگ',
            self::BlogPostEdit => 'ویرایش نوشته وبلاگ',
            self::BlogPostDelete => 'حذف نوشته وبلاگ',
            self::BlogCategoryView => 'مشاهده دسته‌بندی وبلاگ',
            self::BlogCategoryCreate => 'ایجاد دسته‌بندی وبلاگ',
            self::BlogCategoryEdit => 'ویرایش دسته‌بندی وبلاگ',
            self::BlogCategoryDelete => 'حذف دسته‌بندی وبلاگ',
            self::BlogTagView => 'مشاهده برچسب‌های وبلاگ',
            self::BlogTagCreate => 'ایجاد برچسب وبلاگ',
            self::BlogTagEdit => 'ویرایش برچسب وبلاگ',
            self::BlogTagDelete => 'حذف برچسب وبلاگ',
            self::ContactMessageView => 'مشاهده پیام‌های تماس',
            self::ContactMessageEdit => 'ویرایش پیام‌های تماس',
            self::ContactMessageDelete => 'حذف پیام‌های تماس',
            self::HesabfaView => 'مشاهده حسابفا',
            self::HesabfaSync => 'همگام‌سازی با حسابفا',
            self::ManagementReportView => 'مشاهده گزارشات مدیریتی',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::DashboardView => 'داشبورد',
            self::ProductView, self::ProductCreate, self::ProductEdit, self::ProductDelete, self::ProductPricing => 'محصولات',
            self::CategoryView, self::CategoryCreate, self::CategoryEdit, self::CategoryDelete => 'دسته‌بندی محصولات',
            self::BrandView, self::BrandCreate, self::BrandEdit, self::BrandDelete => 'برندها',
            self::OrderView, self::OrderCreate, self::OrderEdit, self::OrderDelete => 'سفارش‌ها',
            self::PaymentView, self::PaymentCreate, self::PaymentEdit, self::PaymentDelete => 'پرداخت‌ها',
            self::CustomerView, self::CustomerCreate, self::CustomerEdit, self::CustomerDelete => 'مشتریان',
            self::ShippingView, self::ShippingCreate, self::ShippingEdit, self::ShippingDelete => 'روش‌های ارسال',
            self::SettingView => 'تنظیمات',
            self::BlogPostView, self::BlogPostCreate, self::BlogPostEdit, self::BlogPostDelete => 'نوشته‌های وبلاگ',
            self::BlogCategoryView, self::BlogCategoryCreate, self::BlogCategoryEdit, self::BlogCategoryDelete => 'دسته‌بندی وبلاگ',
            self::BlogTagView, self::BlogTagCreate, self::BlogTagEdit, self::BlogTagDelete => 'برچسب‌های وبلاگ',
            self::ContactMessageView, self::ContactMessageEdit, self::ContactMessageDelete => 'پیام‌های تماس',
            self::HesabfaView, self::HesabfaSync => 'حسابفا',
            self::ManagementReportView => 'گزارشات مدیریتی',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
