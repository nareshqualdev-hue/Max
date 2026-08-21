<?php

namespace App\Services\Cart;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Services\Discount\AutoDiscountService;
use App\Services\Discount\QuantityDiscountService;
use App\Services\Discount\BogoDiscountService;
use App\Services\Discount\CouponService;
use App\Services\Checkout\GiftCertificateService;

class CartService
{
    public function __construct(
        protected CartCalculatorService $cartCalculatorService,
        protected CartSessionService $cartSessionService,
        protected CartStockService $stockService,
        protected CartProductService $productService,
        protected AutoDiscountService $autoDiscountService,
        protected QuantityDiscountService $quantityDiscountService,
        protected BogoDiscountService $bogoDiscountService,
        protected CouponService $couponService,
        protected GiftCertificateService $giftCertificateService
    ) {
    }

    /**
     * Add product to cart.
     *
     * Product lookup, stock validation and SetProduct-equivalent
     * normalization are handled by CartStockService.
     */
    public function add(
        object $product,
        int $qty = 1,
        string $orderType = 'Website',
        string $cookie = 'No',
        string $giftWrap = 'No'
    ): array {
        $productId = (int) ($product->products_id ?? 0);
        $qty = $qty > 0 ? $qty : 1;

        if ($productId <= 0) {
            return [
                'success' => false,
                'StockInfo' => 1111,
                'message' => 'Product not available.',
            ];
        }

        $stock = $this->stockService->checkStock(
            $productId,
            $qty,
            'insert',
            $cookie,
            $orderType
        );

        if (($stock['StockInfo'] ?? 1111) !== 3333) {
            return [
                'success' => false,
                'StockInfo' => $stock['StockInfo'] ?? 1111,
                'availableStock' =>
                    $stock['availableStock'] ?? null,
                'message' =>
                    $this->stockMessage($stock),
            ];
        }

        $normalizedProduct = $stock['ProdInfo'] ?? null;

        if (!$normalizedProduct) {
            return [
                'success' => false,
                'StockInfo' => 1111,
                'message' => 'Product not available.',
            ];
        }

        $cart = $this->cartSessionService->getCart();

        foreach ($cart as $index => $item) {
            if (
                (int) ($item['ProductID'] ?? 0) === $productId
                && !isset($item['IS_Free_Gift'])
                && !isset($item['Is_Free_Sample'])
            ) {
                $existingQty = (int) ($item['Qty'] ?? 0);

                $newQty = $cookie === 'Yes'
                    ? (
                        $existingQty > $qty
                            ? $existingQty + $qty
                            : $existingQty
                    )
                    : $existingQty + $qty;

                /*
                 * Stock was checked against the final requested
                 * quantity by ProductCheckInStock-equivalent logic.
                 */
                $cart[$index]['Qty'] = $newQty;
                $cart[$index]['gift_wrap'] = $giftWrap;

                $cart[$index] =
                    $this->productService->applyPrice(
                        $cart[$index],
                        $normalizedProduct,
                        $newQty
                    );

                $this->cartSessionService->putCart($cart);
                $this->cartCalculatorService->calculateSubTotal();

                return [
                    'success' => true,
                    'updated' => true,
                    'cart' => $cart,
                ];
            }
        }

        $cartItem =
            $this->productService->buildCartItem(
                $normalizedProduct,
                $qty,
                $orderType
            );

        $cartItem =
            $this->productService->applyPrice(
                $cartItem,
                $normalizedProduct,
                $qty
            );

        $cartItem['gift_wrap'] = $giftWrap;

        $cart[] = $cartItem;

        $this->cartSessionService->putCart($cart);
        $this->cartCalculatorService->calculateSubTotal();

        return [
            'success' => true,
            'updated' => false,
            'cart' => $cart,
        ];
    }

    /**
     * Add a product using only its product ID.
     *
     * The stock service performs the existing product lookup and
     * normalization. The normalized product is then passed to add().
     */
    public function addByProductId(
        int $productId,
        int $qty = 1,
        string $orderType = 'Website',
        string $cookie = 'No',
        string $giftWrap = 'No'
    ): array {
        $qty = $qty > 0 ? $qty : 1;

        $stock = $this->stockService->checkStock(
            $productId,
            $qty,
            'insert',
            $cookie,
            $orderType
        );

        if (($stock['StockInfo'] ?? 1111) !== 3333) {
            return [
                'success' => false,
                'StockInfo' => $stock['StockInfo'] ?? 1111,
                'availableStock' =>
                    $stock['availableStock'] ?? null,
                'message' =>
                    $this->stockMessage($stock),
            ];
        }

        $product = $stock['ProdInfo'] ?? null;

        if (!$product) {
            return [
                'success' => false,
                'StockInfo' => 1111,
                'message' => 'Product not available.',
            ];
        }

        return $this->add(
            $product,
            $qty,
            $orderType,
            $cookie,
            $giftWrap
        );
    }

    /**
     * Update quantity for an existing cart product.
     */
	/**
 * Update an existing product quantity.
 */
	public function update(
    int $productId,
    int $qty,
    string $giftWrap = 'No'
): array {

    $qty = $qty > 0 ? $qty : 1;

    /*
     * IMPORTANT:
     * Products are stored in ShoppingCart.Cart.
     * getCart() returns the complete ShoppingCart structure.
     */
    $cart =
        $this->cartSessionService->getItems();

    $index = null;
    $orderType = 'Website';

    foreach ($cart as $key => $item) {

        if (
            (int) ($item['ProductID'] ?? 0) === $productId
            && !isset($item['IS_Free_Gift'])
            && !isset($item['Is_Free_Sample'])
        ) {

            $index = $key;

            $orderType =
                $item['OrderType'] ?? 'Website';

            break;
        }
    }

    if ($index === null) {

        return [
            'success' => false,
            'Update' => 0,
            'message' =>
                'Product not found in cart.',
        ];
    }

    $stock =
        $this->stockService->checkStock(
            $productId,
            $qty,
            'update',
            'No',
            $orderType
        );

    if (
        ($stock['StockInfo'] ?? 1111) !== 3333
    ) {

        return [
            'success' => false,
            'Update' => 0,
            'StockInfo' =>
                $stock['StockInfo'] ?? 1111,
            'availableStock' =>
                $stock['availableStock'] ?? null,
            'message' =>
                $this->stockMessage($stock),
        ];
    }

    $normalizedProduct =
        $stock['ProdInfo'] ?? null;

    $cart[$index]['Qty'] =
        $qty;

    $cart[$index]['gift_wrap'] =
        $giftWrap;

    if ($normalizedProduct) {

        $cart[$index] =
            $this->productService->applyPrice(
                $cart[$index],
                $normalizedProduct,
                $qty
            );

    } else {

        $cart[$index]['TotPrice'] =
            $this->numberFormat(
                $qty *
                (float) (
                    $cart[$index]['Price']
                    ?? 0
                )
            );
    }

    /*
     * Save ONLY the cart items.
     * Do not overwrite the complete ShoppingCart.
     */
    Session::put(
        'ShoppingCart.Cart',
        $cart
    );

    $this->cartCalculatorService
        ->calculateSubTotal();

    /*
     * Keep your existing recalculation
     * logic here if already present.
     */
    $this->recalculateAfterCartMutation();

    return [
        'success' => true,
        'Update' => 1,
        'cart' =>
            Session::get(
                'ShoppingCart.Cart',
                []
            ),
    ];
}
	
	
	/**
     * Recalculate discounts/certificates after a cart quantity change.
     *
     * This intentionally mirrors the proven legacy UpdateCart()
     * behavior without bringing ShoppingcartController/CartTrait
     * into the new checkout.
     */
    protected function recalculateAfterCartMutation(): void
    {
        /*
         * Gift Certificate must be synced first because the
         * applicable certificate amount depends on the new cart total.
         */
        $this->giftCertificateService->sync();

        /*
         * Legacy UpdateCart() explicitly re-applies the active
         * Yotpo Reward before SetupCart().
         */
        $rewardCode = trim(
            (string) Session::get(
                'ShoppingCart.YotpoRewardCode',
                ''
            )
        );

        if ($rewardCode !== '') {
            $normalUser = Auth::user();

            if (Auth::guard('store')->check()) {
                $normalUser =
                    Auth::guard('web')->user();
            }

            $customerId =
                $normalUser
                    ? Session::get(
                        'sess_icustomerid'
                    )
                    : null;

            $this->couponService->apply(
                $rewardCode,
                $customerId
            );
        }

        /*
         * Same automatic-discount rules used by SetupCart().
         */
        if (
            config('Settings.AUTODISCOUNTFLAG') ===
            'Yes'
        ) {
            $this->autoDiscountService->apply();
        }

        if (
            config('Settings.QUANTITYDISCOUNTFLAG') ===
            'Yes'
        ) {
            $this->quantityDiscountService->apply();
        }

        /*
         * BOGO is also cart-dependent and must be recalculated
         * after quantity changes.
         */
        $this->bogoDiscountService->apply();
    }

    /**
     * Remove cart item.
     *
     * The frontend sends ProductID as cart_id (for example 33032).
     * ShoppingCart.Cart contains the actual cart item array, while
     * CartSessionService::getCart() returns the complete ShoppingCart
     * wrapper. Therefore removal must operate on getItems() and save
     * only ShoppingCart.Cart.
     */
    public function remove(int $cartId): array
    {
        $cart = $this->cartSessionService->getItems();

        $removeIndex = null;

        /*
         * Primary new-checkout contract:
         * cart_id is ProductID for a normal cart item.
         */
        foreach ($cart as $index => $item) {

            if (!is_array($item)) {
                continue;
            }

            if (
                (int) ($item['ProductID'] ?? 0)
                !== $cartId
            ) {
                continue;
            }

            /*
             * Free Gift / Free Sample must not be removed through
             * the normal product-remove fallback.
             */
            if (
                ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
                ||
                ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
            ) {
                continue;
            }

            $removeIndex = $index;
            break;
        }

        /*
         * Backward compatibility:
         * if the caller sends the actual session-cart array index,
         * allow that as well, but never use it to remove a special
         * Free Gift / Free Sample line.
         */
        if (
            $removeIndex === null
            && isset($cart[$cartId])
            && is_array($cart[$cartId])
        ) {
            $candidate = $cart[$cartId];

            if (
                ($candidate['IS_Free_Gift'] ?? 'No') !== 'Yes'
                &&
                ($candidate['Is_Free_Sample'] ?? 'No') !== 'Yes'
            ) {
                $removeIndex = $cartId;
            }
        }

        if ($removeIndex === null) {
            return [
                'success' => false,
                'message' => 'Cart item not found.',
                'cart' => $cart,
            ];
        }

        $removed = $cart[$removeIndex];

        if (
            ($removed['IsYotpoFreeProduct'] ?? 'No') === 'Yes'
        ) {
            Session::forget(
                'ShoppingCart.YotpoFreeGiftCoupon'
            );
        }

        unset($cart[$removeIndex]);

        /*
         * Re-index the actual ShoppingCart.Cart array.
         */
        $cart = array_values($cart);

        /*
         * Preserve the existing legacy Yotpo-only-cart behavior.
         */
        if (
            count($cart) === 1
            &&
            ($cart[0]['IsYotpoFreeProduct'] ?? 'No') === 'Yes'
        ) {
            $cart = [];

            Session::forget(
                'ShoppingCart.YotpoFreeGiftCoupon'
            );
        }

        /*
         * IMPORTANT:
         * Do NOT call putCart($cart) here because putCart() stores
         * the complete ShoppingCart wrapper. We only changed the
         * ShoppingCart.Cart collection.
         */
        $this->cartSessionService->put(
            'Cart',
            $cart
        );

        /*
         * Always recalculate subtotal after a successful removal,
         * including when the cart becomes empty.
         */
        $this->cartCalculatorService->calculateSubTotal();

        /*
         * Removing an item changes cart-dependent discounts and
         * Gift Certificate applicability.
         */
        $this->recalculateAfterCartMutation();

        return [
            'success' => true,
            'message' => 'Item removed successfully.',
            'cart' =>
                $this->cartSessionService->getItems(),
            'removed' => $removed,
        ];
    }

    /**
     * Empty the cart.
     */
    public function clear(): array
    {
        $this->cartSessionService->putCart([]);
        $this->cartCalculatorService->calculateSubTotal();

        return [
            'success' => true,
            'cart' => [],
        ];
    }

    public function getCart(): array
    {
        return $this->cartSessionService->getCart();
    }

    public function getItems(): array
    {
        return Session::get(
            'ShoppingCart.Cart',
            []
        );
    }

    public function hasItems(): bool
    {
        return count($this->getItems()) > 0;
    }

    protected function stockMessage(array $stock): string
    {
        if (($stock['StockInfo'] ?? 0) === 2222) {
            $available =
                $stock['availableStock'] ?? null;

            if ($available !== null) {
                return
                    'The maximum quantity you can add is '
                    . (int) $available
                    . ' pieces.';
            }

            return 'Requested quantity is not available.';
        }

        return 'Product is not available.';
    }

    protected function numberFormat(
        float|int|string $value
    ): float {
        if (function_exists('NumberFormat')) {
            return (float) NumberFormat($value);
        }

        return round((float) $value, 2);
    }
}
