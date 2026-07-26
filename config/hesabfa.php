<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hesabfa API Credentials
    |--------------------------------------------------------------------------
    */

    'api_key' => env('HESABFA_API_KEY', ''),
    'login_token' => env('HESABFA_LOGIN_TOKEN', ''),
    'base_url' => env('HESABFA_BASE_URL', 'https://api.hesabfa.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */

    'default_project' => env('HESABFA_DEFAULT_PROJECT', 'سایت ZIOTO'),
    'default_unit' => 'عدد',
    'draft_invoice' => env('HESABFA_DRAFT_INVOICE', true),
    'use_current_date' => env('HESABFA_USE_CURRENT_DATE', false),

    /*
    |--------------------------------------------------------------------------
    | Item Codes
    |--------------------------------------------------------------------------
    */

    'shipping_item_code' => env('HESABFA_SHIPPING_ITEM_CODE', ''),
    'installment_fee_item_code' => env('HESABFA_INSTALLMENT_FEE_ITEM_CODE', ''),

    /*
    |--------------------------------------------------------------------------
    | Warehouse
    |--------------------------------------------------------------------------
    */

    'warehouse_code' => env('HESABFA_WAREHOUSE_CODE', '11'),
    'enable_warehouse_receipt' => env('HESABFA_ENABLE_WAREHOUSE_RECEIPT', false),

    /*
    |--------------------------------------------------------------------------
    | Customer Settings
    |--------------------------------------------------------------------------
    */

    'customer_node' => env('HESABFA_CUSTOMER_NODE', ''),
    'customer_family' => env('HESABFA_CUSTOMER_FAMILY', ''),

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */

    'auto_sync' => env('HESABFA_AUTO_SYNC', true),
    'sync_statuses' => ['confirmed', 'processing'],

    /*
    |--------------------------------------------------------------------------
    | Stock Sync
    |--------------------------------------------------------------------------
    */

    'sync_stock' => env('HESABFA_SYNC_STOCK', true),
    'sync_interval' => (int) env('HESABFA_SYNC_INTERVAL', 60),
    'enable_reserved_stock' => env('HESABFA_ENABLE_RESERVED_STOCK', false),
    'excluded_skus' => array_filter(explode(',', env('HESABFA_EXCLUDED_SKUS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */

    'webhook_secret' => env('HESABFA_WEBHOOK_SECRET', ''),

];
