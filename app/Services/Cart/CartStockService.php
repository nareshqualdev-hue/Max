<?php

namespace App\Services\Cart;

use App\Services\Cart\ProductNormalizationService;
use App\Models\Products;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Existing OutOfStockItemsRemove() behavior from the old checkout.
     *
     * Returns the SKU list of cart items that are currently out of stock.
     * Also updates ShowStockLeftMessage for the existing low-stock UI.
     */
    public function outOfStockItemsRemove(): array
    {
        $strSku = [];

        if (!Session::has('ShoppingCart.Cart')) {
            return $strSku;
        }

        $tempCart = Session::get('ShoppingCart.Cart', []);
        $cntRow = count($tempCart);

        if ($cntRow === 0) {
            return $strSku;
        }

        $allSkus = $this->extractUniqueSkus($tempCart);

        $productRows = $this->getSellableProductStock($allSkus);

        $hasStoreItems = $this->cartHasStoreItems($tempCart);

        $storeStockMap = $hasStoreItems
            ? $this->getStoreStockForCart($allSkus)
            : collect();

        $stockLeftArray = [];

        for ($i = 0; $i < $cntRow; $i++) {
            $sku = $tempCart[$i]['SKU'] ?? '';

            if ($sku === '') {
                continue;
            }

            $productRow = $productRows->get($sku);

            if (!$productRow) {
                continue;
            }

            [$isOutOfStock, $stockLeft] =
                $this->evaluateStockForItem(
                    $tempCart[$i],
                    $productRow,
                    $storeStockMap
                );

            if ($isOutOfStock) {
                $strSku[] = $sku;
            }

            $stockLeftArray[] = $stockLeft;
        }

        Session::put('ShowStockLeftMessage', 'No');

        foreach ($stockLeftArray as $stockLeft) {
            if ($stockLeft > 0 && $stockLeft < 10) {
                Session::put('ShowStockLeftMessage', 'Yes');
                break;
            }
        }

        return $strSku;
    }

    private function extractUniqueSkus(array $tempCart): array
    {
        $skus = [];

        foreach ($tempCart as $item) {
            if (!empty($item['SKU'])) {
                $skus[$item['SKU']] = true;
            }
        }

        return array_keys($skus);
    }

    private function cartHasStoreItems(array $tempCart): bool
    {
        foreach ($tempCart as $item) {
            if (($item['OrderType'] ?? '') === 'Store') {
                return true;
            }
        }

        return false;
    }

    private function getSellableProductStock(array $skus)
    {
        $columns = [
            'pu_products.sku',
            'pu_products.cosmo_current_stock',
            'pu_products.nandansons_current_stock',
            'pu_products.nd_current_stock',
            'pu_products.perfumeworldwide_currentstock',
            'pu_products.pca_current_stock',
            'pu_products.current_stock',
            'pu_products.minimum_stock',
        ];

        $active = Products::whereIn('sku', $skus)
            ->where('status', '1')
            ->select($columns)
            ->get();

        $private = Products::query()
            ->join(
                'pu_products_one as po',
                'pu_products.products_id',
                '=',
                'po.products_id'
            )
            ->whereIn('pu_products.sku', $skus)
            ->where('pu_products.status', '2')
            ->where('po.is_private', 'Yes')
            ->where('po.private_code', '!=', '')
            ->select($columns)
            ->get();

        return $active
            ->concat($private)
            ->keyBy('sku');
    }

    private function getStoreStockForCart(array $skus)
    {
        if (!Auth::guard('store')->check()) {
            return collect();
        }

        $store = Auth::guard('store')->user();

        return DB::table('pu_store_inventory as ps')
            ->join(
                'pu_products',
                'pu_products.products_id',
                '=',
                'ps.products_id'
            )
            ->where('ps.store_id', $store->store_id)
            ->whereIn('pu_products.sku', $skus)
            ->select(
                'pu_products.sku',
                'ps.current_stock as store_currentStock'
            )
            ->get()
            ->keyBy('sku');
    }

    private function evaluateStockForItem(
        array $item,
        $productRow,
        $storeStockMap
    ): array {
        $isStoreOrder =
            ($item['OrderType'] ?? '') === 'Store';

        $storeRow = $isStoreOrder
            ? $storeStockMap->get($item['SKU'] ?? '')
            : null;

        $storeCurrentStock =
            $storeRow->store_currentStock ?? null;

        if ($isStoreOrder && $storeCurrentStock <= 0) {
            return [true, $storeCurrentStock];
        }

        /*
         * Preserve the old checkout vendor-specific stock rules.
         */
        $vendorChecks = [
            'IsCosmo'      => 'cosmo_current_stock',
            'IsPCA'        => 'pca_current_stock',
            'IsNandansons' => 'nandansons_current_stock',
            'IsPerfumePW'  => 'perfumeworldwide_currentstock',
            'IsND'         => 'nd_current_stock',
        ];

        foreach ($vendorChecks as $flag => $field) {
            if (
                ($item[$flag] ?? 'No') === 'Yes'
                && ($item['VendorSKU'] ?? '') !== ''
                && ($item['OrderType'] ?? '') === 'Website'
            ) {
                $vendorStock =
                    (float) ($productRow->$field ?? 0);

                $minimumStock =
                    (float) ($productRow->minimum_stock ?? 0);

                $stockLeft =
                    $vendorStock - $minimumStock;

                $qty =
                    (float) ($item['Qty'] ?? 0);

                $outOfStock =
                    $vendorStock <= 0
                    || $qty > $vendorStock;

                return [$outOfStock, $stockLeft];
            }
        }

        $currentStock =
            (float) ($productRow->current_stock ?? 0);

        $minimumStock =
            (float) ($productRow->minimum_stock ?? 0);

        $qty =
            (float) ($item['Qty'] ?? 0);

        if (
            $currentStock <= $minimumStock
            && ($item['VendorSKU'] ?? '') === ''
            && ($item['OrderType'] ?? '') === 'Website'
        ) {
            return [
                true,
                $currentStock - $minimumStock,
            ];
        }

        if (
            $qty > $currentStock
            && ($item['VendorSKU'] ?? '') === ''
            && ($item['OrderType'] ?? '') === 'Website'
        ) {
            return [
                true,
                $currentStock - $minimumStock,
            ];
        }

        /*
         * Not out of stock — compute stock-left for the
         * existing low-stock UI message.
         */
        if ($isStoreOrder) {
            return [false, $storeCurrentStock];
        }

        foreach ($vendorChecks as $flag => $field) {
            if (
                ($item[$flag] ?? 'No') === 'Yes'
                && ($item['VendorSKU'] ?? '') !== ''
                && ($item['OrderType'] ?? '') === 'Website'
            ) {
                return [
                    false,
                    (float) ($productRow->$field ?? 0)
                    - $minimumStock,
                ];
            }
        }

        return [
            false,
            $currentStock - $minimumStock,
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
