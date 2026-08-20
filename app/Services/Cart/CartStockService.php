<?php

namespace App\Services\Cart;

use App\Services\Cart\ProductNormalizationService;
use App\Models\Products;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartStockService
{
    public function __construct(
        protected ProductNormalizationService $productNormalizer
    ) {
    }

    /**
     * Existing ProductCheckInStock() behavior.
     *
     * 1111 = product unavailable
     * 2222 = quantity unavailable
     * 3333 = stock available
     */
    public function checkStock(
        int $productId,
        int $qty = 1,
        string $operation = 'insert',
        string $cookie = 'No',
        string $orderType = 'Website'
    ): array {
        if ($productId === 0) {
            return ['StockInfo' => 3333];
        }

        $qty = $qty > 0 ? $qty : 1;
        $cookie = $cookie ?: 'No';

        $isStore =
            Auth::guard('store')->check()
            && $orderType === 'Store';

        $query = Products::query()
            ->join(
                'pu_products_one as po',
                'pu_products.products_id',
                '=',
                'po.products_id'
            );

        if ($isStore) {
            $store = Auth::guard('store')->user();

            $query
                ->join(
                    'pu_store_inventory as ps',
                    'pu_products.products_id',
                    '=',
                    'ps.products_id'
                )
                ->where('ps.store_id', $store->store_id)
                ->select(
                    'pu_products.*',
                    'ps.current_stock as store_currentStock'
                );
        } else {
            $query->select('pu_products.*');
        }

        $productInfo = $query
            ->where(function ($q) {
                $q->where('pu_products.status', '1')
                    ->orWhere(function ($qry) {
                        $qry->where('pu_products.status', '2')
                            ->where('po.is_private', 'Yes')
                            ->where('po.private_code', '!=', '');
                    });
            })
            ->where('pu_products.products_id', $productId)
            ->distinct()
            ->get();

        if (!$productInfo || $productInfo->count() === 0) {
            return ['StockInfo' => 1111];
        }

        $productQuantity = $qty;

        if ($cookie === 'Yes' && $operation === 'insert') {
            $originalQuantity = $this->getStockInCart($productId);

            $productQuantity =
                $originalQuantity > $qty
                    ? $qty + $originalQuantity
                    : $originalQuantity;
        }

        if ($cookie === 'No') {
            $productQuantity =
                $operation === 'insert'
                    ? $this->getStockInCart($productId) + $qty
                    : $qty;
        }

        /*
         * Store inventory is already a direct store quantity.
         * Website inventory must go through the SetProduct-equivalent
         * normalization before stock is evaluated.
         */
        if ($isStore) {
            $availableStock =
                (int) ($productInfo[0]->store_currentStock ?? 0);

            return [
                'StockInfo' =>
                    $productQuantity > $availableStock ? 2222 : 3333,
                'ProdInfo' => $productInfo[0],
                'availableStock' => $availableStock,
                'requestedQuantity' => $productQuantity,
            ];
        }

        $normalizedProduct =
            $this->productNormalizer->normalize(
                $productInfo[0],
                $orderType
            );

        $availableStock =
            max(
                0,
                (float) $normalizedProduct->current_stock
                - (float) $normalizedProduct->minimum_stock
            );

        return [
            'StockInfo' =>
                $productQuantity > $availableStock ? 2222 : 3333,
            'ProdInfo' => $normalizedProduct,
            'availableStock' => $availableStock,
            'requestedQuantity' => $productQuantity,
        ];
    }

    /**
     * Final stock calculation after the existing SetProduct()
     * normalization has been performed.
     */
    public function checkNormalizedStock(
        object $productStock,
        int $requestedQuantity,
        string $orderType = 'Website'
    ): array {
        $availableStock =
            $orderType === 'Store'
                ? (int) ($productStock->store_currentStock ?? 0)
                : (int) (
                    ($productStock->current_stock ?? 0)
                    - ($productStock->minimum_stock ?? 0)
                );

        return [
            'StockInfo' =>
                $requestedQuantity > $availableStock
                    ? 2222
                    : 3333,
            'ProdInfo' => $productStock,
            'availableStock' => $availableStock,
        ];
    }

    /**
     * Existing ProductStockInCart() behavior.
     */
    public function getStockInCart(int $productId): int
    {
        $cart = Session::get('ShoppingCart.Cart', []);
        $cartQuantity = 0;

        foreach ($cart as $item) {
            if (
                isset($item['ProductID']) &&
                (int) $item['ProductID'] === $productId &&
                $productId !== 0
            ) {
                $cartQuantity += (int) ($item['Qty'] ?? 0);
            }
        }

        return $cartQuantity;
    }
}
