<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Product\Models\Product;

class LandingController extends Controller
{
    public function home()
    {
        $products = $this->getProducts();

        return view('landing.home', compact('products'));
    }

    public function about()
    {
        return view('landing.about');
    }

    public function product(string $slug)
    {
        $product = collect($this->getProducts())->firstWhere('slug', $slug);

        abort_if(! $product, 404);

        return view('landing.product', compact('product'));
    }

    public function cart()
    {
        return view('landing.cart');
    }

    public function checkout()
    {
        return view('landing.checkout');
    }

    public function login()
    {
        return view('landing.login');
    }

    public function profile(Request $request)
    {
        if (! $request->user()) {
            return redirect()->route('landing.login');
        }

        return view('landing.profile');
    }

    public function success()
    {
        return view('landing.success');
    }

    private function getProducts(): array
    {
        return Product::with('images')
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
            ])->values()->toArray();
    }
}
