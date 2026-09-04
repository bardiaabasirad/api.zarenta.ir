<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variety;
use Illuminate\Validation\ValidationException;

class CartService
{
    public static function syncCart($user, ?array $items = null)
    {
        $items ??= [];

        if (empty($items)) {
            return CartItem::query()
                ->where('user_id', $user->id)
                ->with([
                    'product.size_unit',
                    'variety.images',
                    'variety.color',
                ])
                ->get();
        }

        $productIds = collect($items)
            ->pluck('product_id')
            ->filter(fn ($id) => !is_null($id))
            ->unique()
            ->values();

        $varietyIds = collect($items)
            ->pluck('variety_id')
            ->filter(fn ($id) => !is_null($id))
            ->unique()
            ->values();

        /*
         * کلید آرایه، id تنوع و مقدار آن product_id متناظر است:
         *
         * [
         *   493 => 164,
         *   494 => 164,
         * ]
         */
        $varietyProductMap = Variety::query()
            ->whereIn('id', $varietyIds)
            ->pluck('product_id', 'id');

        $existingProductIds = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('id')
            ->flip();

        foreach ($items as $index => $item) {
            $productId = (int) $item['product_id'];
            $varietyId = isset($item['variety_id'])
                ? (int) $item['variety_id']
                : null;

            if (!isset($existingProductIds[$productId])) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" => [
                        'محصول انتخاب‌شده دیگر وجود ندارد.',
                    ],
                ]);
            }

            if ($varietyId !== null) {
                if (!isset($varietyProductMap[$varietyId])) {
                    throw ValidationException::withMessages([
                        "items.$index.variety_id" => [
                            'تنوع انتخاب‌شده دیگر وجود ندارد.',
                        ],
                    ]);
                }

                if ((int) $varietyProductMap[$varietyId] !== $productId) {
                    throw ValidationException::withMessages([
                        "items.$index.variety_id" => [
                            'تنوع انتخاب‌شده متعلق به محصول موردنظر نیست.',
                        ],
                    ]);
                }
            }
        }

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $varietyId = $item['variety_id'] ?? null;

            $query = CartItem::query()
                ->where('user_id', $user->id)
                ->where('product_id', $productId);

            if ($varietyId === null) {
                $query->whereNull('variety_id');
            } else {
                $query->where('variety_id', $varietyId);
            }

            $cartItem = $query->first();

            if ($cartItem) {
                $cartItem->update([
                    'count' => (int) $item['count'],
                ]);

                continue;
            }

            CartItem::create([
                'user_id' => $user->id,
                'product_id' => $productId,
                'variety_id' => $varietyId,
                'count' => (int) $item['count'],
            ]);
        }

        return CartItem::query()
            ->where('user_id', $user->id)
            ->with([
                'product.size_unit',
                'variety.images',
                'variety.color',
            ])
            ->get();
    }

    public static function payable(
        array $items,
              $marketPrice,
        float $shippingCost,
        float $vat = 0.09
    ): int {
        $payable = array_reduce($items, function ($carry, $item) use ($vat, $marketPrice) {
            $variety = Variety::with('product:id,vat')
                ->findOrFail($item['variety_id']);

            return $carry + (
                    ProductPriceService::priceWithDiscount(
                        $variety,
                        $marketPrice,
                        $variety->product->vat,
                        $vat
                    ) * $item['count']
                );
        }, 0);

        return ceil($payable / 1000) * 1000 + $shippingCost;
    }

    public static function purchase(array $items, $marketPrice): int
    {
        return array_reduce($items, function ($carry, $item) use ($marketPrice) {
            $variety = Variety::findOrFail($item['variety_id']);

            return $carry + ProductPriceService::purchasePrice(
                    $variety,
                    $marketPrice,
                    $item['count']
                );
        }, 0);
    }

    public static function generateUniqueCode(): int
    {
        $code = rand(10000000, 99999999);

        while (Order::where('code', $code)->exists()) {
            $code = rand(10000000, 99999999);
        }

        return $code;
    }
}
