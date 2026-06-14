<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Models\Product;

$landingPages = fn () => view('landing.index', [
    'products' => Product::with('images')
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get()
        ->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'weight' => $product->weight ? $product->weight.' گرم' : '',
            'purity' => '۹۹۹.۹',
            'category' => str_contains(strtolower($product->name), 'نقره') ? 'silver' : 'gold',
            'price' => (int) $product->price,
            'originalPrice' => (int) ($product->price * 1.04),
            'image' => $product->images->where('is_primary', true)->first()?->image_path
                ? asset('storage/'.$product->images->where('is_primary', true)->first()->image_path)
                : ($product->images->first()?->image_path
                    ? asset('storage/'.$product->images->first()->image_path)
                    : 'https://placehold.co/600x400/1B4332/C8A84E?text='.urlencode($product->name)),
            'images' => $product->images->map(fn ($img) => asset('storage/'.$img->image_path))->values(),
            'description' => $product->description ?? '',
            'badge' => '',
            'installment' => 'امکان خرید اقساطی',
            'stock_quantity' => $product->stock_quantity,
        ]),
]);

Route::get('/', $landingPages);
Route::get('/about', $landingPages);
Route::get('/login', $landingPages);
Route::get('/profile', $landingPages);
Route::get('/cart', $landingPages);
Route::get('/checkout', $landingPages);
Route::get('/success', $landingPages);
Route::get('/products/{slug}', $landingPages);
