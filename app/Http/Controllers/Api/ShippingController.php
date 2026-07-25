<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function methods(Request $request): JsonResponse
    {
        $cityId = $request->input('city_id');
        $provinceId = $request->input('province_id');

        if (! $cityId && ! $provinceId) {
            $user = $request->user();
            $address = $user?->defaultAddress ?? $user?->addresses()->first();
            if ($address) {
                $cityId = $address->city_id;
                $provinceId = $address->province_id;
            }
        }

        $methods = ShippingMethod::active()->with('rates')->get();

        if (! $cityId && ! $provinceId) {
            $methods = $methods->filter(fn (ShippingMethod $m) => $m->is_pickup);

            return response()->json([
                'methods' => $methods->values(),
            ]);
        }

        if ($cityId || $provinceId) {
            $methods = $methods->filter(function (ShippingMethod $method) use ($cityId, $provinceId) {
                $exclude = $method->exclude_cities ?? [];
                if ($cityId && in_array((int) $cityId, array_map('intval', $exclude))) {
                    return false;
                }

                $rates = $method->rates;
                if ($rates->isEmpty()) {
                    return true;
                }

                $rateType = $rates->first()->rate_type;

                if ($rateType === 'province' && $provinceId) {
                    return $rates->contains(function (ShippingRate $r) use ($provinceId) {
                        return is_null($r->province_id) || (int) $r->province_id === (int) $provinceId;
                    });
                }

                if ($rateType === 'city' && $cityId) {
                    return $rates->contains(function (ShippingRate $r) use ($cityId) {
                        return is_null($r->city_id) || (int) $r->city_id === (int) $cityId;
                    });
                }

                if ($rates->contains(function (ShippingRate $r) use ($cityId, $provinceId) {
                    return ($r->province_id && $provinceId && (int) $r->province_id === (int) $provinceId)
                        || ($r->city_id && $cityId && (int) $r->city_id === (int) $cityId);
                })) {
                    return true;
                }

                if ($rates->every(fn (ShippingRate $r) => is_null($r->province_id) && is_null($r->city_id))) {
                    return true;
                }

                return false;
            });
        }

        $methods = $methods->map(function (ShippingMethod $method) use ($cityId, $provinceId) {
            if (($cityId || $provinceId) && $method->rates->isNotEmpty()) {
                $rateType = $method->rates->first()->rate_type;
                $method->setRelation('rates', $this->filterMatchingRates($method->rates, $rateType, $cityId, $provinceId));
            }

            return $method;
        })->filter(function (ShippingMethod $method) {
            return $method->rates->isNotEmpty();
        });

        return response()->json([
            'methods' => $methods->values(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $method = ShippingMethod::active()->with('rates')->findOrFail($id);

        return response()->json([
            'method' => $method,
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
            'province_id' => 'nullable|integer|exists:provinces,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'cart_items' => 'nullable|array',
            'cart_items.*.product_id' => 'integer',
            'cart_items.*.quantity' => 'integer|min:1',
            'cart_items.*.weight' => 'numeric|min:0',
            'cart_total' => 'nullable|numeric|min:0',
        ]);

        $method = ShippingMethod::active()->findOrFail($validated['shipping_method_id']);

        $totalWeight = 0;
        if (! empty($validated['cart_items'])) {
            foreach ($validated['cart_items'] as $item) {
                $totalWeight += ($item['weight'] ?? 0) * ($item['quantity'] ?? 1);
            }
        }

        $cartTotal = $validated['cart_total'] ?? 0;
        $provinceId = $validated['province_id'] ?? null;
        $cityId = $validated['city_id'] ?? null;

        $rate = $this->findMatchingRate($method, $totalWeight, $cartTotal, $provinceId, $cityId);

        if (! $rate) {
            return response()->json([
                'message' => 'تعرفه مناسبی برای این روش ارسال یافت نشد',
                'error_code' => 'SHIPPING_RATE_NOT_FOUND',
            ], 404);
        }

        $shippingCost = (int) $rate->base_rate;

        if ($rate->per_kg_rate && $totalWeight > 0) {
            $extraKg = max(0, ceil($totalWeight / 1000) - 1);
            $shippingCost += $extraKg * (int) $rate->per_kg_rate;
        }

        $freeShipping = false;
        if ($rate->free_shipping_min && $cartTotal >= $rate->free_shipping_min) {
            $shippingCost = 0;
            $freeShipping = true;
        }

        $taxAmount = 0;
        $taxRate = $rate->tax_rate;
        if ($taxRate && $taxRate > 0 && ! $freeShipping) {
            $taxAmount = (int) round($shippingCost * $taxRate / 100);
        }

        $breakdown = [
            'base_rate' => (int) $rate->base_rate,
        ];

        if ($rate->per_kg_rate && ! $freeShipping) {
            $breakdown['weight_surcharge'] = $shippingCost - (int) $rate->base_rate;
        }

        if ($taxAmount > 0) {
            $breakdown['tax'] = $taxAmount;
        }

        return response()->json([
            'shipping_method_id' => $method->id,
            'shipping_method_name' => $method->name,
            'shipping_cost' => $shippingCost,
            'estimated_min_days' => $rate->estimated_days_min,
            'estimated_max_days' => $rate->estimated_days_max,
            'has_tax' => $taxAmount > 0,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_shipping_cost' => $shippingCost + $taxAmount,
            'free_shipping' => $freeShipping,
            'breakdown' => $breakdown,
        ]);
    }

    private function filterMatchingRates($rates, string $rateType, ?int $cityId, ?int $provinceId)
    {
        return $rates->filter(function (ShippingRate $rate) use ($rateType, $cityId, $provinceId) {
            if ($rateType === 'province' && $provinceId) {
                return is_null($rate->province_id) || (int) $rate->province_id === (int) $provinceId;
            }
            if ($rateType === 'city' && $cityId) {
                return is_null($rate->city_id) || (int) $rate->city_id === (int) $cityId;
            }
            if ($rate->province_id && $provinceId) {
                return (int) $rate->province_id === (int) $provinceId;
            }
            if ($rate->city_id && $cityId) {
                return (int) $rate->city_id === (int) $cityId;
            }

            return true;
        })->values();
    }

    private function findMatchingRate(ShippingMethod $method, float $totalWeight, int $cartTotal, ?int $provinceId, ?int $cityId): ?ShippingRate
    {
        $rates = $method->rates;

        if ($rates->isEmpty()) {
            return null;
        }

        $rateType = $rates->first()->rate_type;

        return match ($rateType) {
            'flat' => $rates->first(),
            'weight' => $rates
                ->filter(fn (ShippingRate $r) => $r->min_weight <= $totalWeight && (! $r->max_weight || $totalWeight <= $r->max_weight))
                ->sortBy('min_weight')
                ->first(),
            'province' => $rates
                ->filter(fn (ShippingRate $r) => is_null($r->province_id) || (int) $r->province_id === (int) $provinceId)
                ->first(),
            'city' => $rates
                ->filter(fn (ShippingRate $r) => is_null($r->city_id) || (int) $r->city_id === (int) $cityId)
                ->first(),
            'cart_total' => $rates
                ->filter(fn (ShippingRate $r) => $r->min_cart_total <= $cartTotal && (! $r->max_cart_total || $cartTotal <= $r->max_cart_total))
                ->sortBy('min_cart_total')
                ->first(),
            default => $rates->first(),
        };
    }
}
