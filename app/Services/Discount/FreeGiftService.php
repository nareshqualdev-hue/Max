<?php

namespace App\Services\Discount;
use App\Constants\CheckoutConstants;
use App\Models\Products;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Services\Cart\CartStockService;
class FreeGiftService
{

    /**
     * Determine whether Free Gift processing is allowed.
     *
     * This preserves the legacy guards:
     * - FREEGIFTFLAG must be enabled.
     * - Store checkout does not run the web Free Gift flow.
     * - Wholesaler and dropshipper customers do not receive Free Gifts.
     *
     * Rule calculation itself is intentionally NOT duplicated here.
     * The legacy rule engine remains the source of truth until its
     * exact GetFreeCouponPopup/CheckFreeGiftInCart implementation is
     * migrated into this service.
     */
    public function __construct(
        protected CartStockService $cartStockService
    ) {
    } 
    public function isEligibleCustomer(): bool
    {
        if (config('Settings.FREEGIFTFLAG') !== 'Yes') {
            return false;
        }

        if (Auth::guard('store')->check()) {
            return false;
        }

        if (
            strtolower(
                trim(
                    (string) Session::get('eusertype', '')
                )
            ) === 'wholesaler'
        ) {
            return false;
        }

        if (
            trim(
                (string) Session::get('is_dropshipper', '')
            ) === 'Yes'
        ) {
            return false;
        }

        return true;
    }

    /**
     * Decide the Free Gift UI state from the already-resolved
     * eligible gift list.
     *
     * IMPORTANT:
     * This method does not invent or replace the old rule engine.
     * The caller must provide the exact eligible list produced by
     * the migrated legacy rule calculation.
     *
     * Rules:
     * - one eligible gift => automatic add is allowed
     * - multiple eligible gifts + remaining count => popup
     * - required count already reached => no popup
     */
    public function getPopupDecision(
        array $eligibleGifts,
        int $existingGiftCount = 0,
        int $freeGiftCount = 0
    ): array {
        if (!$this->isEligibleCustomer()) {
            return [
                'status' => 'disabled',
                'shouldPopup' => false,
                'shouldAutoAdd' => false,
                'eligibleGifts' => [],
                'remainingCount' => 0,
            ];
        }

        $eligibleGifts = array_values($eligibleGifts);

        $existingGiftCount =
            max(0, $existingGiftCount);

        $freeGiftCount =
            max(0, $freeGiftCount);

        $remainingCount =
            $freeGiftCount > 0
                ? max(
                    0,
                    $freeGiftCount -
                    $existingGiftCount
                )
                : 1;

        if (empty($eligibleGifts)) {
            return [
                'status' => 'no_rule',
                'shouldPopup' => false,
                'shouldAutoAdd' => false,
                'eligibleGifts' => [],
                'remainingCount' => $remainingCount,
            ];
        }

        /*
         * Required gift count already satisfied.
         */
        if (
            $freeGiftCount > 0
            &&
            $existingGiftCount >= $freeGiftCount
        ) {
            return [
                'status' => 'complete',
                'shouldPopup' => false,
                'shouldAutoAdd' => false,
                'eligibleGifts' => $eligibleGifts,
                'remainingCount' => 0,
            ];
        }

        /*
         * Exactly one eligible gift:
         * preserve the legacy automatic insertion flow.
         */
        if (
            count($eligibleGifts) === 1
            &&
            $remainingCount > 0
        ) {
            return [
                'status' => 'auto_add',
                'shouldPopup' => false,
                'shouldAutoAdd' => true,
                'eligibleGifts' => $eligibleGifts,
                'remainingCount' => $remainingCount,
            ];
        }

        /*
         * Multiple eligible gifts:
         * customer must choose from the popup.
         */
        return [
            'status' => 'popup',
            'shouldPopup' => true,
            'shouldAutoAdd' => false,
            'eligibleGifts' => $eligibleGifts,
            'remainingCount' => $remainingCount,
        ];
    }

    /**
     * Add free gift product into cart.
     *
     * Migration of:
     * CartTrait::FreeGiftInsertProductValue()
     *
     * Returns:
     * - Same free gift product already added
     * - null when FreeGiftCoupon already exists
     * - Out of stock message
     * - empty string when successfully added
     */
 
/**
 * Insert the Free Gift configured on the coupon record.
 *
 * Legacy source of truth:
 * CartTrait::FreeGiftInsertWithCoupon($products_sku)
 *
 * The caller passes the coupon table's freegift_product_sku.
 */
public function insertWithCoupon(string $productValue): array
{
    $productValue = trim($productValue);

    if ($productValue === '') {
        return [
            'success' => false,
            'message' => 'Free gift product is not configured.',
        ];
    }

    $cart = Session::get(
        'ShoppingCart.Cart',
        []
    );

    if (
        !is_array($cart)
        ||
        count($cart) === 0
    ) {
        return [
            'success' => false,
            'message' =>
                'Free gift cannot be added because the cart is empty.',
        ];
    }

    /*
     * ---------------------------------------------------------
     * Coupon can contain one or more SKUs.
     * ---------------------------------------------------------
     */
    $skus = array_values(
        array_filter(
            array_map(
                'trim',
                explode(',', $productValue)
            ),
            static fn ($sku) => $sku !== ''
        )
    );

    if (empty($skus)) {
        return [
            'success' => false,
            'message' =>
                'Free gift product is not configured.',
        ];
    }

    /*
     * ---------------------------------------------------------
     * Find active products by configured coupon SKU.
     * ---------------------------------------------------------
     */
    $products = Products::query()
        ->whereIn('sku', $skus)
        ->where('status', '1')
        ->get();

    if ($products->isEmpty()) {

        addLog(
            'FreeGiftInsertWithCouponProductNotFound',
            [
                'products_sku' => $productValue,
                'skus' => $skus,
            ]
        );

        return [
            'success' => false,
            'message' =>
                'Free gift product is not available.',
        ];
    }

    /*
     * ---------------------------------------------------------
     * IMPORTANT:
     *
     * If the configured coupon Free Gift is ALREADY in cart,
     * keep it.
     *
     * This is required because CheckoutService can re-apply
     * the active coupon during checkout refresh.
     *
     * Do NOT remove the existing coupon gift and recreate it.
     * ---------------------------------------------------------
     */
    foreach ($cart as $item) {

        if (
            isset($item['FreeGiftCoupon'])
            &&
            $item['FreeGiftCoupon'] === 'Yes'
            &&
            isset($item['IS_Free_Gift'])
            &&
            $item['IS_Free_Gift'] === 'Yes'
        ) {

            $existingGiftSku =
                trim(
                    (string) (
                        $item['ProductSKU']
                        ??
                        $item['products_sku']
                        ??
                        $item['SKU']
                        ??
                        ''
                    )
                );

            /*
             * The cart SKU for a Free Gift can be stored as
             * GIFT-{product sku}.
             *
             * Therefore compare both the normal SKU and
             * GIFT-{SKU}.
             */
            foreach ($skus as $configuredSku) {

                $configuredSku =
                    trim($configuredSku);

                $giftSku =
                    'GIFT-' . $configuredSku;

                if (
                    $existingGiftSku ===
                        $configuredSku
                    ||
                    $existingGiftSku ===
                        $giftSku
                ) {

                    addLog(
                        'FreeGiftInsertWithCouponAlreadyExists',
                        [
                            'products_sku' =>
                                $productValue,

                            'configured_sku' =>
                                $configuredSku,

                            'existing_sku' =>
                                $existingGiftSku,

                            'ProductID' =>
                                $item['ProductID']
                                ?? null,
                        ]
                    );

                    return [
                        'success' => true,

                        'message' =>
                            'Free gift already exists.',

                        'cart_item' =>
                            $item,

                        'cart_items' =>
                            [$item],
                    ];
                }
            }
        }
    }

    /*
     * ---------------------------------------------------------
     * IMPORTANT:
     *
     * We may have an OLD / automatic Free Gift in cart.
     *
     * Remove only a normal Free Gift.
     *
     * NEVER remove:
     * - Free Sample
     * - Coupon Free Gift
     * - Normal cart product
     * ---------------------------------------------------------
     */
    foreach ($cart as $index => $item) {

        $isFreeGift =
            isset($item['IS_Free_Gift'])
            &&
            $item['IS_Free_Gift'] === 'Yes';

        $isFreeSample =
            isset($item['Is_Free_Sample'])
            &&
            $item['Is_Free_Sample'] === 'Yes';

        $isCouponGift =
            isset($item['FreeGiftCoupon'])
            &&
            $item['FreeGiftCoupon'] === 'Yes';

        if (
            $isFreeGift
            &&
            !$isFreeSample
            &&
            !$isCouponGift
        ) {
            unset($cart[$index]);
        }
    }

    $cart = array_values($cart);

    /*
     * ---------------------------------------------------------
     * Try each configured SKU.
     * ---------------------------------------------------------
     */
    $addedItems = [];
    $outOfStockSku = '';

    foreach ($products as $rawProduct) {

        /*
         * -----------------------------------------------------
         * Resolve stock/vendor information BEFORE building
         * the Free Gift cart item.
         * -----------------------------------------------------
         */
        $stock = $this->cartStockService->checkStock(
            (int) $rawProduct->products_id,
            1,
            'insert',
            'No',
            'Website'
        );

        if (
            ($stock['StockInfo'] ?? 1111) !== 3333
            ||
            empty($stock['ProdInfo'])
        ) {

            $outOfStockSku =
                $rawProduct->sku;

            addLog(
                'FreeGiftInsertWithCouponStockFailed',
                [
                    'products_sku' =>
                        $rawProduct->sku,

                    'products_id' =>
                        $rawProduct->products_id,

                    'StockInfo' =>
                        $stock['StockInfo'] ?? null,
                ]
            );

            continue;
        }

        /*
         * Use the stock-resolved product.
         */
        $product =
            $stock['ProdInfo'];

        /*
         * -----------------------------------------------------
         * Build the Free Gift using existing New Checkout
         * Free Gift builder.
         * -----------------------------------------------------
         */
        $result =
            $this->addProductToCart(
                $product,
                $product->products_id,
                '',
                $cart
            );

        if (
            !is_array($result)
            ||
            !($result['added'] ?? false)
        ) {

            $outOfStockSku =
                $result['sku']
                ??
                $product->sku;

            addLog(
                'FreeGiftInsertWithCouponBuilderFailed',
                [
                    'products_sku' =>
                        $product->sku,

                    'products_id' =>
                        $product->products_id,

                    'result' =>
                        $result,
                ]
            );

            continue;
        }

        /*
         * -----------------------------------------------------
         * Get updated cart.
         * -----------------------------------------------------
         */
        $cart =
            array_values(
                $result['cart'] ?? $cart
            );

        /*
         * -----------------------------------------------------
         * Find actual inserted Free Gift.
         * -----------------------------------------------------
         */
        $giftIndex = null;

        foreach (
            $cart as $index => $item
        ) {

            if (
                isset($item['ProductID'])
                &&
                (string)
                    $item['ProductID']
                    ===
                    (string)
                    $product->products_id
                &&
                isset($item['IS_Free_Gift'])
                &&
                $item['IS_Free_Gift'] === 'Yes'
            ) {

                $giftIndex =
                    $index;

                break;
            }
        }

        if ($giftIndex === null) {

            addLog(
                'FreeGiftInsertWithCouponCartInsertFailed',
                [
                    'products_sku' =>
                        $product->sku,

                    'products_id' =>
                        $product->products_id,

                    'result' =>
                        $result,
                ]
            );

            continue;
        }

        /*
         * -----------------------------------------------------
         * Mark this gift as coupon-generated.
         * -----------------------------------------------------
         */
        $cart[$giftIndex]['FreeGiftCoupon'] =
            'Yes';

        $cart[$giftIndex]['FreeGiftAutoAdded'] =
            'No';

        /*
         * Coupon Free Gift is always zero price.
         */
        $cart[$giftIndex]['Price'] =
            0;

        $cart[$giftIndex]['Qty'] =
            1;

        $cart[$giftIndex]['TotPrice'] =
            0;

        $addedItems[] =
            $cart[$giftIndex];

        /*
         * One valid coupon gift is enough.
         */
        break;
    }

    /*
     * ---------------------------------------------------------
     * Nothing inserted.
     * ---------------------------------------------------------
     */
    if (empty($addedItems)) {

        $message =
            'The Free bundle is out of stock and cannot be added to your order';

        if ($outOfStockSku !== '') {
            $message .=
                ' ' . $outOfStockSku;
        }

        Session::flash(
            'OutOfStockBundle',
            $message
        );

        addLog(
            'FreeGiftInsertWithCouponFailed',
            [
                'products_sku' =>
                    $productValue,

                'skus' =>
                    $skus,

                'out_of_stock_sku' =>
                    $outOfStockSku,
            ]
        );

        return [
            'success' => false,
            'message' => $message,
        ];
    }

    /*
     * ---------------------------------------------------------
     * Save final cart.
     * ---------------------------------------------------------
     */
    Session::put(
        'ShoppingCart.Cart',
        array_values($cart)
    );

    addLog(
        'FreeGiftInsertWithCoupon',
        [
            'products_sku' =>
                $productValue,

            'products_id' =>
                array_column(
                    $addedItems,
                    'ProductID'
                ),

            'FreeGiftCoupon' =>
                'Yes',
        ]
    );

    return [
        'success' => true,

        'message' =>
            'Free gift added successfully.',

        'cart_item' =>
            $addedItems[0],

        'cart_items' =>
            $addedItems,
    ];
}
 public function addGift(
    $productsId,
    $freeProductsId = 0,
    $oneGift = 'No',
    $autoAdded = false
): ?string {
    $outOfStockMessage = '';
    $skuList = '';

    $log = [
        'products_id' => $productsId,
        'freeproductsid' => $freeProductsId,
        'OneGift' => $oneGift,
    ];

    addLog(
        'FreeGiftInsertProductValueStart',
        $log
    );

    /*
     * ---------------------------------------------------------
     * Cart must exist.
     * ---------------------------------------------------------
     */
    if (
        !Session::has('ShoppingCart.Cart') ||
        count(
            Session::get(
                'ShoppingCart.Cart',
                []
            )
        ) <= 0
    ) {
        return null;
    }

    $cart =
        array_values(
            Session::get(
                'ShoppingCart.Cart',
                []
            )
        );

    /*
     * ---------------------------------------------------------
     * Product IDs.
     * ---------------------------------------------------------
     */
    $productIds =
        $this->csvToArray(
            $productsId
        );

    if (
        empty($productIds)
    ) {
        return null;
    }

    /*
     * ---------------------------------------------------------
     * Existing Product IDs + Free Gift flags.
     * ---------------------------------------------------------
     */
    $productIdValues =
        array_column(
            $cart,
            'ProductID'
        );

    $isFreeGiftValues =
        array_column(
            $cart,
            'IS_Free_Gift'
        );

    /*
     * ---------------------------------------------------------
     * Same free gift check.
     *
     * Existing logic:
     *
     * If requested product is already in cart
     * and a free gift exists in cart:
     *
     * "Same free gift product already added"
     * ---------------------------------------------------------
     */
    foreach (
        $productIds as $productId
    ) {
        if (
            in_array(
                $productId,
                $productIdValues
            )
            &&
            in_array(
                'Yes',
                $isFreeGiftValues
            )
        ) {
            $message =
                'Same free gift product already added';

            $log['message'] =
                $message;

            addLog(
                'SameFreeGift',
                $log
            );

            return $message;
        }
    }

    /*
     * ---------------------------------------------------------
     * Existing FreeGiftCoupon / OneGift behavior.
     * ---------------------------------------------------------
     */
    foreach (
        $cart as $index => $cartItem
    ) {
        /*
         * Existing coupon-selected gift already exists.
         *
         * Do not add another gift.
         */
        if (
            isset(
                $cartItem['FreeGiftCoupon']
            )
            &&
            $cartItem['FreeGiftCoupon']
                === 'Yes'
        ) {
            addLog(
                'FreeGiftNull'
            );

            return null;
        }

        /*
         * -----------------------------------------------------
         * FREE GIFT HAS PRIORITY OVER FREE SAMPLE
         * -----------------------------------------------------
         *
         * When an automatic Free Gift is being added,
         * remove any existing Free Sample from the cart.
         *
         * This gives:
         *
         *     Free Gift > Free Sample
         *
         * Existing Free Gift logic remains unchanged.
         */
        if (
            $autoAdded === true
            &&
            isset(
                $cartItem['Is_Free_Sample']
            )
            &&
            $cartItem['Is_Free_Sample']
                === 'Yes'
        ) {
            addLog(
                'FreeSampleUnsetByFreeGift',
                [
                    'ProductID' =>
                        $cartItem['ProductID'] ?? null,

                    'SKU' =>
                        $cartItem['SKU'] ?? null,
                ]
            );

            unset(
                $cart[$index]
            );

            continue;
        }

        /*
         * -----------------------------------------------------
         * OneGift = Yes:
         *
         * remove existing normal free gifts
         * before adding the new one.
         * -----------------------------------------------------
         */
        if (
            isset(
                $cartItem['IS_Free_Gift']
            )
            &&
            $cartItem['IS_Free_Gift']
                === 'Yes'
            &&
            $oneGift === 'Yes'
        ) {
            addLog(
                'FreeGiftUnset'
            );

            unset(
                $cart[$index]
            );
        }
    }

    $cart =
        array_values(
            $cart
        );

    Session::put(
        'ShoppingCart.Cart',
        $cart
    );

    /*
     * ---------------------------------------------------------
     * Active products.
     * ---------------------------------------------------------
     */
    $products =
        Products::whereIn(
            'products_id',
            $productIds
        )
        ->where(
            'status',
            '1'
        )
        ->get();

    if (
        $products->count() <= 0
    ) {
        return null;
    }

    /*
     * ---------------------------------------------------------
     * Add each requested product.
     * ---------------------------------------------------------
     */
    foreach (
        $products as $product
    ) {
        $result =
            $this->addProductToCart(
                $product,
                $freeProductsId,
                $skuList,
                $cart,
                $autoAdded
            );

        if (
            $result['added']
        ) {
            $cart =
                $result['cart'];
        } else {
            $skuList .=
                $result['sku']
                . ',';
        }
    }

    /*
     * ---------------------------------------------------------
     * Out-of-stock message.
     * ---------------------------------------------------------
     */
    if (
        $skuList !== ''
    ) {
        $skuList =
            rtrim(
                $skuList,
                ','
            );

        $outOfStockMessage =
            'The Free bundle is out of stock and cannot be added to your order and out of stock products '
            . $skuList;

        $log['OutofStockMsg'] =
            $outOfStockMessage;

        addLog(
            'FreeGiftInsertProductValueOutofStock',
            $log
        );

        Session::flash(
            'OutOfStockBundle',
            'The Free bundle is out of stock and cannot be added to your order '
            . $skuList
        );
    }

    /*
     * ---------------------------------------------------------
     * Save cart.
     *
     * Existing flow only saves the cart when a gift
     * was successfully added.
     * ---------------------------------------------------------
     */
    if (
        !empty($cart)
    ) {
        Session::put(
            'ShoppingCart.Cart',
            array_values(
                $cart
            )
        );

        /*
         * Existing cart pricing recalculation happens
         * after cart mutation.
         *
         * We intentionally do not duplicate
         * CalculateSubTotal() here.
         */
    }

    addLog(
        'FreeGiftInsertProductValue',
        [
            'products_id' =>
                $productsId,

            'freeproductsid' =>
                $freeProductsId,

            'OneGift' =>
                $oneGift,

            'cart_count' =>
                count($cart),
        ]
    );

    return $outOfStockMessage;
}
    /**
     * Add one product to cart.
     */
    protected function addProductToCart(
        $product,
        $freeProductsId,
        string $skuList,
        array $cart,
        bool $autoAdded = false
    ): array {
        /*
         * ---------------------------------------------------------
         * Vendor values.
         *
         * Existing source initializes all as "No".
         * ---------------------------------------------------------
         */
        $vendorSku = '';

        $isCosmo =
            'No';

        $isNandansons =
            'No';

        $isPerfumePw =
            'No';

        $isPca =
            'No';

        $isNd =
            'No';

        /*
         * ---------------------------------------------------------
         * Website stock = Out
         *
         * Existing fallback order:
         *
         * Cosmo
         * PCA
         * Nandansons
         * Perfume Worldwide
         * ND
         * ---------------------------------------------------------
         */
        if (
            ($product->stock ?? '')
            === 'Out'
        ) {
            if (
                !empty(
                    $product->cosmo_sku
                )
                &&
                (float)
                $product->cosmo_current_stock
                    > 0
            ) {
                $isCosmo =
                    'Yes';

                $vendorSku =
                    $product->cosmo_sku;
            }

            elseif (
                !empty(
                    $product->pca_sku
                )
                &&
                (float)
                $product->pca_current_stock
                    > 0
            ) {
                $isPca =
                    'Yes';

                $vendorSku =
                    $product->pca_sku;
            }

            elseif (
                !empty(
                    $product->nandansons_sku
                )
                &&
                (float)
                $product->nandansons_current_stock
                    > 0
            ) {
                $isNandansons =
                    'Yes';

                $vendorSku =
                    $product->nandansons_sku;
            }

            elseif (
                !empty(
                    $product->perfumeworldwide_sku
                )
                &&
                (float)
                $product->perfumeworldwide_currentstock
                    > 0
            ) {
                $isPerfumePw =
                    'Yes';

                $vendorSku =
                    $product->perfumeworldwide_sku;
            }

            elseif (
                !empty(
                    $product->nd_sku
                )
                &&
                (float)
                $product->nd_current_stock
                    > 0
            ) {
                $isNd =
                    'Yes';

                $vendorSku =
                    $product->nd_sku;
            }
        }

        /*
         * ---------------------------------------------------------
         * Stock validation.
         *
         * Website stock or any vendor stock must be available.
         * ---------------------------------------------------------
         */
        $hasWebsiteStock =
            (
                (float)
                (
                    $product->current_stock
                    ?? 0
                )
                > 0
            );

        $hasVendorStock =
            $vendorSku !== '';

        if (
            !$hasWebsiteStock
            &&
            !$hasVendorStock
        ) {
            return [
                'added' =>
                    false,

                'sku' =>
                    $product->sku,

                'cart' =>
                    $cart,
            ];
        }

        /*
         * ---------------------------------------------------------
         * Category.
         *
         * Existing source gets category through
         * products_category.
         *
         * We only need the first category ID here.
         * ---------------------------------------------------------
         */
        $categoryId =
            $this->getCategoryId(
                $product->products_id
            );

        /*
         * ---------------------------------------------------------
         * Website 2-day delivery.
         * ---------------------------------------------------------
         */
        $item =
            [];

        if (
            ($product->WebsiteStock ?? '')
            === 'In'
        ) {
            $item[
                'IsMaxaromaTwoDelivery'
            ] =
                $product->maxtwodaydelivery;
        }

        /*
         * ---------------------------------------------------------
         * Existing cart structure.
         * ---------------------------------------------------------
         */
        $item['ProductID'] =
            $product->products_id;

        $item['SKU'] =
            'GIFT-'
            . $product->sku;

        $item['ORGSKU'] =
            $product->sku;

        $item['CategoryID'] =
            $categoryId;

        $item['ProductName'] =
            stripslashes(
                str_ireplace(
                    [
                        "\r",
                        "\n",
                        '\r',
                        '\n',
                    ],
                    '',
                    remove_html_entities(
                        $product->product_name
                    )
                )
            );

        $item['short_description'] =
            strip_tags(
                stripslashes(
                    str_ireplace(
                        [
                            "\r",
                            "\n",
                            '\r',
                            '\n',
                        ],
                        '',
                        remove_html_entities(
                            $product->short_description
                        )
                    )
                )
            );

        $item['Billing_Image'] =
            $this->billingImage(
                $product
            );

        /*
         * Free gift is always zero price.
         */
        $item['Price'] =
            0;

        $item['Qty'] =
            1;

        $item['TotPrice'] =
            0;

        $item['Image'] =
            $this->productImage(
                $product
            );

        $item['Prod_URL'] =
            '';

       $item['IS_Free_Gift'] =
    'Yes';

$item['FreeGiftAutoAdded'] =
    $autoAdded ? 'Yes' : 'No';
        /*
         * Important:
         * Existing FreeGiftInsertProductValue()
         * sets FreeGiftCoupon = Yes.
         */
        /*
         * Automatic rule-generated Free Gifts in the legacy
         * checkout did not carry FreeGiftCoupon=Yes.
         * Coupon-selected gifts use that flag, so keep the
         * distinction intact.
         */
        $item['image_forpopup'] =
            $this->popupImage(
                $product
            );

        $item['freeproductsid'] =
            $freeProductsId;

        $item['VendorSKU'] =
            $vendorSku;

        $item['IsCosmo'] =
            $isCosmo;

        $item['IsNandansons'] =
            $isNandansons;

        $item['IsPerfumePW'] =
            $isPerfumePw;

        $item['IsPCA'] =
            $isPca;

        $item['IsND'] =
            $isNd;

        $item['ImanufactureID'] =
            $product->imanufactureid;

        $item['IsDealProducts'] =
            'No';

        $item['DealDiscountFlag'] =
            'No';

        $item['dealdiscount_flag'] =
            'No';

        $item['manufactureName'] =
            '';

        $item['CategoryName'] =
            '';

        $item['FinalSale'] =
            '';

        /*
         * ---------------------------------------------------------
         * Legacy cart-array compatibility.
         *
         * Keep the same keys used by the old checkout Free Gift
         * item so existing cart/checkout consumers receive the
         * same shape. These are data fields only; no pricing logic
         * is changed here.
         * --------------------------------------------------------- */
        $item['AutoItemWiseDiscout'] =
            0;

        $item['QuantityItemWiseDiscout'] =
            0;

        $item['CouponDisItemWiseDiscout'] =
            0;

        $item['RewardItemWiseDiscout'] =
            0;

        $item['BogoItemWiseDiscout'] =
            0;

        $item['BogoDiscountMessage'] =
            '';

        $item['BogoDiscountID'] =
            0;

        $item['ShowGiftChkOpt'] =
            'No';

        $item['ItemWiseCouponDiscount'] =
            0;

        /*
         * ---------------------------------------------------------
         * Add to cart.
         * ---------------------------------------------------------
         */
        $cart[] =
            $item;

        return [
            'added' =>
                true,

            'sku' =>
                $product->sku,

            'cart' =>
                $cart,
        ];
    }

    /**
     * Get first category ID for product.
     *
     * Direct pivot query is intentional:
     * we only need category_id, not full relationship data.
     */
    protected function getCategoryId(
        $productId
    ): int {
        return (int)
            (
                DB::table(
                    'pu_products_category'
                )
                ->where(
                    'products_id',
                    $productId
                )
                ->value(
                    'category_id'
                )
                ?? 0
            );
    }

    /**
     * Existing image format.
     */
    protected function productImage(
        $product
    ): string {
        $image =
            $product->prod_image
            ?? '';

        if (
            trim((string) $image) === ''
            && !empty($product->image)
        ) {
            $image = $product->image;
        }

        $imageUrl =
            $this->resolveProductImageUrl(
                $image,
                'large'
            );

        return
            '<img src="'
            . e($imageUrl)
            . '" border="0" width="125" alt="'
            . e(
                $product->product_name
            )
            . '" />';
    }

    /**
     * Existing popup image format.
     */
    protected function popupImage(
        $product
    ): string {
        $image =
            $product->prod_image
            ?? '';

        if (
            trim((string) $image) === ''
            && !empty($product->image)
        ) {
            $image = $product->image;
        }

        $imageUrl =
            $this->resolveProductImageUrl(
                $image,
                'thumb'
            );

        return
            '<img src="'
            . e($imageUrl)
            . '" border="0" width="75" alt="'
            . e(
                $product->product_name
            )
            . '" />';
    }

    /**
     * Existing billing image format.
     */
    protected function billingImage(
        $product
    ): string {
        $image =
            $product->billing_image
            ?? '';

        if (
            trim((string) $image) === ''
            && !empty($product->prod_image)
        ) {
            $image = $product->prod_image;
        }

        if (
            trim((string) $image) === ''
            && !empty($product->image)
        ) {
            $image = $product->image;
        }

        $imageUrl =
            $this->resolveProductImageUrl(
                $image,
                'large'
            );

        return
            '<img src="'
            . e($imageUrl)
            . '" border="0" width="195" alt="'
            . e(
                $product->product_name
            ) . '" title="' .
            e(
                $product->product_name
            ) . '" />';
    }

    /**
     * Resolve a Free Gift image to the same full Maxaroma image URL
     * format used by the existing product/cart flow.
     *
     * - Keeps an existing absolute URL.
     * - Extracts src from existing <img ...> values.
     * - Uses the configured large/thumb image URL for a filename.
     * - Uses the configured no-image URL when the image is missing
     *   or the actual image file does not exist.
     */
    protected function resolveProductImageUrl(
        $image,
        string $size
    ): string {
        $image =
            trim((string) $image);

        /*
         * If the database field contains an <img> tag,
         * use its src value as the source.
         */
        if (
            str_contains(
                $image,
                '<img'
            )
        ) {
            if (
                preg_match(
                    '/<img[^>]+src=["\']([^"\']*)["\']/i',
                    $image,
                    $matches
                )
            ) {
                $image =
                    trim(
                        (string) (
                            $matches[1]
                            ?? ''
                        )
                    );
            } else {
                $image = '';
            }
        }

        /*
         * Empty image -> existing configured No Image URL.
         */
        if ($image === '') {
            return $size === 'thumb'
                ? config(
                    'global.NO_IMAGE_THUMB'
                )
                : config(
                    'global.NO_IMAGE_LARGE'
                );
        }

        /*
         * Already an absolute URL.
         */
        if (
            preg_match(
                '#^https?://#i',
                $image
            )
        ) {
            return $image;
        }

        /*
         * Protocol-relative URL.
         */
        if (
            str_starts_with(
                $image,
                '//'
            )
        ) {
            return
                request()->getScheme()
                . ':'
                . $image;
        }

        /*
         * Use the same configured image location as the
         * existing Maxaroma product flow.
         */
        if (
            $size === 'thumb'
        ) {
            $imagePath =
                config(
                    'global.PRD_THUMB_IMG_PATH'
                );

            $imageUrl =
                config(
                    'global.PRD_THUMB_IMG_URL'
                );

            $noImage =
                config(
                    'global.NO_IMAGE_THUMB'
                );
        } else {
            $imagePath =
                config(
                    'global.PRD_LARGE_IMG_PATH'
                );

            $imageUrl =
                config(
                    'global.PRD_LARGE_IMG_URL'
                );

            $noImage =
                config(
                    'global.NO_IMAGE_LARGE'
                );
        }

        /*
         * Normalize accidental leading slash because the
         * configured image URL already owns its path.
         */
        $filename =
            ltrim(
                stripslashes($image),
                '/'
            );

        /*
         * Only return the product image when the actual
         * file exists. Otherwise return the configured
         * full No Image URL.
         */
        if (
            $imagePath
            &&
            file_exists(
                rtrim(
                    $imagePath,
                    '/'
                )
                . '/'
                . $filename
            )
        ) {
            return
                rtrim(
                    (string) $imageUrl,
                    '/'
                )
                . '/'
                . $filename;
        }

        return
            $noImage;
    }

    /**
     * CSV helper.
     */
    protected function csvToArray(
        $value
    ): array {
        if (
            trim(
                (string)
                $value
            ) === ''
        ) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        $value
                    )
                ),
                'strlen'
            )
        );
    }
    /**
     * Remove Free Gifts that were automatically added by the
     * checkout Free Gift rule flow.
     *
     * Truth Mode:
     * - Do not remove normal cart products.
     * - Do not remove Free Samples.
     * - Do not change the existing FreeGiftCoupon behaviour.
     * - Remove only the automatic rule-generated gift shape used
     *   by addProductToCart(): IS_Free_Gift=Yes, GIFT-* SKU and
     *   freeproductsid=0.
     *
     * The current automatic gift response uses freeproductsid=0.
     * Coupon-generated Free Gifts use the same GIFT-* /
     * FreeGiftCoupon fields but carry their product id in
     * freeproductsid, so they are left untouched here.
     */
public function removeAutoAddedFreeGifts(): int
{
    $cart = Session::get(
        'ShoppingCart.Cart',
        []
    );

    if (
        !is_array($cart) ||
        empty($cart)
    ) {
        return 0;
    }

    $removed = 0;
    $newCart = [];

    foreach ($cart as $item) {

        $isFreeGift =
            ($item['IS_Free_Gift'] ?? 'No') === 'Yes';

        $isFreeSample =
            ($item['Is_Free_Sample'] ?? 'No') === 'Yes';

        $sku =
            strtoupper(
                trim(
                    (string) (
                        $item['SKU'] ?? ''
                    )
                )
            );

        /*
         * Only gifts automatically added by
         * the Free Gift rule engine are removable.
         *
         * Do NOT use FreeGiftCoupon here.
         * Auto-added gifts may also contain the
         * legacy FreeGiftCoupon = Yes flag.
         */
        $isRuleAutoGift =
            $isFreeGift
            && !$isFreeSample
            && str_starts_with(
                $sku,
                'GIFT-'
            )
            && (
                ($item['FreeGiftAutoAdded'] ?? 'No')
                === 'Yes'
            );

        if ($isRuleAutoGift) {

            $removed++;

            continue;
        }

        /*
         * Preserve:
         * - normal products
         * - Free Samples
         * - coupon-generated Free Gifts
         */
        $newCart[] = $item;
    }

    if ($removed > 0) {

        Session::put(
            'ShoppingCart.Cart',
            array_values($newCart)
        );

        Log::info(
            'Free Gift automatic removal',
            [
                'removedCount' =>
                    $removed,

                'reason' =>
                    'qualification_lost',
            ]
        );
    }

    return $removed;
}


   /**
     * Resolve the legacy Free Gift rule for the current cart.
     *
     * This is a migration of the existing GetFreeCouponPopup()
     * candidate/range calculation. It deliberately does NOT modify
     * the cart. Free Gift / Free Sample lines are excluded from
     * qualifying totals.
     *
     * @return array
     */
     
public function resolveEligibleGifts(
    array $cart,
    float $totalValue,
    int $totalFreeGiftItems = 0,
    int $freeGiftProductId = 0
): array {

    /*
     * =========================================================
     * CUSTOMER ELIGIBILITY
     * =========================================================
     */
    if (!$this->isEligibleCustomer()) {
        return [
            'status' => 'disabled',
            'rule' => null,
            'eligibleGifts' => [],
            'existingGiftCount' => $totalFreeGiftItems,
            'remainingCount' => 0,
        ];
    }

    /*
     * =========================================================
     * CHECKOUT / TOTAL CHECK
     * =========================================================
     */
    if (
        config('Settings.CHECKOUT_SHOIPPINGCART') !== 'Yes'
        ||
        $totalValue <= 0
    ) {
        return [
            'status' => 'no_rule',
            'rule' => null,
            'eligibleGifts' => [],
            'existingGiftCount' => $totalFreeGiftItems,
            'remainingCount' => 0,
        ];
    }

    $today = date('Y-m-d');

    /*
     * =========================================================
     * BUILD REAL PURCHASE TOTALS
     * =========================================================
     *
     * Free Gift / Free Sample / Deal products do not
     * participate in Free Gift rule calculation.
     */
    $brandTotals = [];
    $categoryTotals = [];
    $brandCategoryTotals = [];
    $purchaseTotal = 0.0;

    foreach ($cart as $item) {

        if (
            ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
            ||
            ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
            ||
            ($item['IsDealProducts'] ?? 'No') === 'Yes'
        ) {
            continue;
        }

        $lineTotal =
            (float) (
                $item['TotPrice'] ?? 0
            );

        if ($lineTotal <= 0) {
            continue;
        }

        $purchaseTotal += $lineTotal;

        $brandId = (string) (
            $item['ImanufactureID']
            ??
            $item['imanufactureid']
            ??
            ''
        );

        $categoryId = (string) (
            $item['CategoryID']
            ??
            $item['category_id']
            ??
            ''
        );

        if ($brandId !== '') {

            $brandTotals[$brandId] =
                ($brandTotals[$brandId] ?? 0)
                +
                $lineTotal;
        }

        if ($categoryId !== '') {

            $categoryTotals[$categoryId] =
                ($categoryTotals[$categoryId] ?? 0)
                +
                $lineTotal;
        }

        /*
         * Keep this existing total available.
         *
         * It is not used for the new Brand,Category
         * qualification logic below, but keeping it avoids
         * disturbing other existing calculations.
         */
        if (
            $brandId !== ''
            &&
            $categoryId !== ''
        ) {

            $key =
                $brandId
                . '_'
                .
                $categoryId;

            $brandCategoryTotals[$key] =
                ($brandCategoryTotals[$key] ?? 0)
                +
                $lineTotal;
        }
    }

    /*
     * =========================================================
     * USE DISCOUNTED VALUE
     * =========================================================
     *
     * Existing caller passes:
     *
     * SubTotal - TotalDiscount
     */
    $purchaseTotal =
        $totalValue > 0
            ? (float) $totalValue
            : $purchaseTotal;

    /*
     * =========================================================
     * ACTIVE RULE QUERY
     * =========================================================
     */
    $queryBase =
        DB::table('pu_free_gift_product')
            ->where('status', '1')
            ->whereDate(
                'start_date',
                '<=',
                $today
            )
            ->whereDate(
                'end_date',
                '>=',
                $today
            );

    $queries = [];

    /*
     * =========================================================
     * PRICE / GENERAL RULE
     * =========================================================
     */
    $queries[] =
        (clone $queryBase)
            ->where(
                'flag_range',
                ''
            );

    /*
     * =========================================================
     * BRAND RULE
     * =========================================================
     */
    $brandQuery =
        (clone $queryBase)
            ->where(
                'flag_range',
                'Brand'
            )
            ->join(
                'pu_freegift_brand as b',
                'pu_free_gift_product.products_id',
                '=',
                'b.products_id'
            );

    if (!empty($brandTotals)) {

        $brandQuery->whereIn(
            'b.imanufactureid',
            array_keys($brandTotals)
        );

    } else {

        $brandQuery->whereRaw(
            '1 = 0'
        );
    }

    $queries[] =
        $brandQuery->select(
            'pu_free_gift_product.*'
        );

    /*
     * =========================================================
     * CATEGORY RULE
     * =========================================================
     */
    $categoryQuery =
        (clone $queryBase)
            ->where(
                'flag_range',
                'Category'
            )
            ->join(
                'pu_freegift_category as c',
                'pu_free_gift_product.products_id',
                '=',
                'c.products_id'
            );

    if (!empty($categoryTotals)) {

        $categoryQuery->whereIn(
            'c.categoryid',
            array_keys($categoryTotals)
        );

    } else {

        $categoryQuery->whereRaw(
            '1 = 0'
        );
    }

    $queries[] =
        $categoryQuery->select(
            'pu_free_gift_product.*'
        );

    /*
     * =========================================================
     * BRAND + CATEGORY RULE
     * =========================================================
     *
     * BOTH mappings must exist for the rule itself.
     *
     * Qualification amount is calculated below as:
     *
     * matching Brand total
     * +
     * matching Category total
     *
     * Same cart line is counted only once.
     */
    $comboQuery =
        (clone $queryBase)
            ->where(
                'flag_range',
                'Brand,Category'
            )
            ->join(
                'pu_freegift_brand as b',
                'pu_free_gift_product.products_id',
                '=',
                'b.products_id'
            )
            ->join(
                'pu_freegift_category as c',
                'pu_free_gift_product.products_id',
                '=',
                'c.products_id'
            );

    if (!empty($brandTotals)) {

        $comboQuery->whereIn(
            'b.imanufactureid',
            array_keys($brandTotals)
        );

    } else {

        $comboQuery->whereRaw(
            '1 = 0'
        );
    }

    if (!empty($categoryTotals)) {

        $comboQuery->whereIn(
            'c.categoryid',
            array_keys($categoryTotals)
        );

    } else {

        $comboQuery->whereRaw(
            '1 = 0'
        );
    }

    $queries[] =
        $comboQuery->select(
            'pu_free_gift_product.*'
        );

    /*
     * =========================================================
     * COMBINE CANDIDATES
     * =========================================================
     */
    $combined =
        array_shift($queries);

    foreach ($queries as $query) {

        $combined =
            $combined->unionAll($query);
    }

    $candidates =
        DB::query()
            ->fromSub(
                $combined,
                'fg'
            )
            ->orderByDesc(
                'price_start_range'
            )
            ->orderByDesc(
                'price_end_range'
            )
            ->get();

    /*
     * =========================================================
     * NO CANDIDATES
     * =========================================================
     */
    if ($candidates->isEmpty()) {

        return [
            'status' => 'no_rule',
            'rule' => null,
            'eligibleGifts' => [],
            'existingGiftCount' =>
                $totalFreeGiftItems,
            'remainingCount' => 0,
        ];
    }

    /*
     * =========================================================
     * RULE MAPPINGS
     * =========================================================
     */
    $candidateIds =
        $candidates
            ->pluck('products_id')
            ->unique()
            ->values()
            ->all();

    $brandMap =
        DB::table('pu_freegift_brand')
            ->whereIn(
                'products_id',
                $candidateIds
            )
            ->get()
            ->groupBy(
                'products_id'
            );

    $categoryMap =
        DB::table('pu_freegift_category')
            ->whereIn(
                'products_id',
                $candidateIds
            )
            ->get()
            ->groupBy(
                'products_id'
            );

    /*
     * =========================================================
     * RULE TYPE PRIORITY
     * =========================================================
     *
     * ONLY used when price_start_range is the same.
     *
     * Old checkout behaviour:
     *
     * Brand + Category
     *      >
     * Brand
     *      >
     * Category
     *      >
     * Price
     */
    $priority = [
        'Brand,Category' => 4,
        'Brand' => 3,
        'Category' => 2,
        '' => 1,
    ];

    $selectedRule = null;
    $selectedQualifyingTotal = 0.0;
    $selectedPriority = -1;
    $selectedStart = -1.0;
    $selectedEnd = -1.0;

    /*
     * =========================================================
     * FIND BEST VALID RULE
     * =========================================================
     */
    foreach ($candidates as $candidate) {

        $flag =
            trim(
                (string) $candidate->flag_range
            );

        $qualifyingTotal = 0.0;

        $ruleBrands =
            $brandMap->get(
                $candidate->products_id,
                collect()
            );

        $ruleCategories =
            $categoryMap->get(
                $candidate->products_id,
                collect()
            );

        /*
         * -----------------------------------------------------
         * PRICE RULE
         * -----------------------------------------------------
         */
        if ($flag === '') {

            $qualifyingTotal =
                $purchaseTotal;
        }

        /*
         * -----------------------------------------------------
         * BRAND RULE
         * -----------------------------------------------------
         */
        elseif ($flag === 'Brand') {

            foreach ($ruleBrands as $brand) {

                $id =
                    (string)
                    $brand->imanufactureid;

                $qualifyingTotal +=
                    (float) (
                        $brandTotals[$id]
                        ?? 0
                    );
            }
        }

        /*
         * -----------------------------------------------------
         * CATEGORY RULE
         * -----------------------------------------------------
         */
        elseif ($flag === 'Category') {

            foreach ($ruleCategories as $category) {

                $id =
                    (string)
                    $category->categoryid;

                $qualifyingTotal +=
                    (float) (
                        $categoryTotals[$id]
                        ?? 0
                    );
            }
        }

        /*
         * -----------------------------------------------------
         * BRAND + CATEGORY RULE
         * -----------------------------------------------------
         *
         * NEW LOGIC:
         *
         * Matching Brand products
         * +
         * Matching Category products
         *
         * are counted together.
         *
         * If one product matches both Brand and Category,
         * it is counted only once.
         */
        elseif ($flag === 'Brand,Category') {

            /*
             * -------------------------------------------------
             * Find rule Brand IDs
             * -------------------------------------------------
             */
            $ruleBrandIds =
                $ruleBrands
                    ->pluck(
                        'imanufactureid'
                    )
                    ->map(
                        fn ($id) =>
                            (string) $id
                    )
                    ->unique()
                    ->values()
                    ->all();

            /*
             * -------------------------------------------------
             * Find rule Category IDs
             * -------------------------------------------------
             */
            $ruleCategoryIds =
                $ruleCategories
                    ->pluck(
                        'categoryid'
                    )
                    ->map(
                        fn ($id) =>
                            (string) $id
                    )
                    ->unique()
                    ->values()
                    ->all();

            /*
             * -------------------------------------------------
             * Track already counted cart lines.
             * -------------------------------------------------
             */
            $matchedIndexes = [];

            foreach (
                $cart as $index => $item
            ) {

                /*
                 * Skip Free Gift.
                 */
                if (
                    ($item['IS_Free_Gift'] ?? 'No')
                    === 'Yes'
                ) {
                    continue;
                }

                /*
                 * Skip Free Sample.
                 */
                if (
                    ($item['Is_Free_Sample'] ?? 'No')
                    === 'Yes'
                ) {
                    continue;
                }

                /*
                 * Skip Deal Product.
                 */
                if (
                    ($item['IsDealProducts'] ?? 'No')
                    === 'Yes'
                ) {
                    continue;
                }

                $lineTotal =
                    (float) (
                        $item['TotPrice'] ?? 0
                    );

                if ($lineTotal <= 0) {
                    continue;
                }

                $brandId =
                    (string) (
                        $item['ImanufactureID']
                        ??
                        $item['imanufactureid']
                        ??
                        ''
                    );

                $categoryId =
                    (string) (
                        $item['CategoryID']
                        ??
                        $item['category_id']
                        ??
                        ''
                    );

                /*
                 * -------------------------------------------------
                 * Check Brand match.
                 * -------------------------------------------------
                 */
                $matchesBrand =
                    in_array(
                        $brandId,
                        $ruleBrandIds,
                        true
                    );

                /*
                 * -------------------------------------------------
                 * Check Category match.
                 * -------------------------------------------------
                 */
                $matchesCategory =
                    in_array(
                        $categoryId,
                        $ruleCategoryIds,
                        true
                    );

                /*
                 * -------------------------------------------------
                 * Brand OR Category match.
                 *
                 * Same line counted only once.
                 * -------------------------------------------------
                 */
                if (
                    $matchesBrand
                    ||
                    $matchesCategory
                ) {

                    if (
                        !isset(
                            $matchedIndexes[$index]
                        )
                    ) {

                        $qualifyingTotal +=
                            $lineTotal;

                        $matchedIndexes[$index] =
                            true;
                    }
                }
            }
        }

        /*
         * Unknown rule type.
         */
        else {

            continue;
        }

        /*
         * =====================================================
         * EXCLUDE SKU
         * =====================================================
         *
         * Old checkout uses # separator.
         */
        $excludeSkus =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            '#',
                            (string) (
                                $candidate->exclude_sku
                                ?? ''
                            )
                        )
                    ),
                    'strlen'
                )
            );

        /*
         * Also support comma separated values without
         * changing existing # behaviour.
         */
        if (
            empty($excludeSkus)
            &&
            !empty(
                trim(
                    (string) (
                        $candidate->exclude_sku
                        ?? ''
                    )
                )
            )
        ) {

            $excludeSkus =
                array_values(
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                ',',
                                (string) (
                                    $candidate->exclude_sku
                                    ?? ''
                                )
                            )
                        ),
                        'strlen'
                    )
                );
        }

        /*
         * =====================================================
         * EXCLUDE POCKET PERFUME
         * =====================================================
         */
        $excludePocket =
            trim(
                (string) (
                    $candidate->exclude_pocketperfume
                    ?? ''
                )
            ) === 'Yes';

        /*
         * =====================================================
         * APPLY PER-RULE EXCLUSIONS
         * =====================================================
         *
         * calculateRuleTotal() contains the same
         * Brand,Category logic.
         */
        if (
            !empty($excludeSkus)
            ||
            $excludePocket
        ) {

            $qualifyingTotal =
                $this->calculateRuleTotal(
                    $cart,
                    $flag,
                    $ruleBrands,
                    $ruleCategories,
                    $purchaseTotal,
                    $excludeSkus,
                    $excludePocket
                );
        }

        $qualifyingTotal =
            (float) $qualifyingTotal;

        $start =
            (float) (
                $candidate->price_start_range
                ?? 0
            );

        $end =
            (float) (
                $candidate->price_end_range
                ?? 0
            );

        /*
         * =====================================================
         * RANGE CHECK
         * =====================================================
         *
         * Preserve existing behaviour:
         *
         * qualifyingTotal must reach start range.
         *
         * Do not change upper-bound behaviour here.
         */
        if (
            $qualifyingTotal < $start
        ) {

            continue;
        }

        $candidatePriority =
            $priority[$flag] ?? 0;

        /*
         * =====================================================
         * SELECT RULE
         * =====================================================
         *
         * 1. Highest start range wins.
         *
         * 2. Same start:
         *    Brand+Category
         *    Brand
         *    Category
         *    Price
         *
         * 3. Same priority:
         *    Higher end range wins.
         */
        $shouldSelect = false;

        if (
            $start > $selectedStart
        ) {

            $shouldSelect = true;

        } elseif (
            $start == $selectedStart
            &&
            $candidatePriority > $selectedPriority
        ) {

            $shouldSelect = true;

        } elseif (
            $start == $selectedStart
            &&
            $candidatePriority == $selectedPriority
            &&
            $end > $selectedEnd
        ) {

            $shouldSelect = true;
        }

        if ($shouldSelect) {

            $selectedRule =
                $candidate;

            $selectedQualifyingTotal =
                $qualifyingTotal;

            $selectedPriority =
                $candidatePriority;

            $selectedStart =
                $start;

            $selectedEnd =
                $end;
        }
    }

    /*
     * =========================================================
     * NO VALID RULE
     * =========================================================
     */
    if (!$selectedRule) {

        return [
            'status' => 'no_rule',
            'rule' => null,
            'eligibleGifts' => [],
            'existingGiftCount' =>
                $totalFreeGiftItems,
            'remainingCount' => 0,
        ];
    }

    /*
     * =========================================================
     * FREE GIFT RULE ID
     * =========================================================
     */
    $selectedRuleId =
        (int) (
            $selectedRule->products_id
            ?? 0
        );

    /*
     * =========================================================
     * FREE GIFT SKU LIST
     * =========================================================
     */
    $freeGiftSkus = [];

    if (
        isset($selectedRule->sku)
        &&
        trim(
            (string) $selectedRule->sku
        ) !== ''
    ) {

        $freeGiftSkus =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            '#',
                            (string) $selectedRule->sku
                        )
                    ),
                    'strlen'
                )
            );
    }

    /*
     * =========================================================
     * NO FREE GIFT SKU
     * =========================================================
     */
    if (empty($freeGiftSkus)) {

        return [
            'status' => 'no_rule',

            'rule' => [
                'id' =>
                    $selectedRuleId,

                'flag_range' =>
                    (string)
                    $selectedRule->flag_range,

                'freegift_add_count' =>
                    (int) (
                        $selectedRule
                            ->freegift_add_count
                        ?? 1
                    ),

                'qualifyingTotal' =>
                    round(
                        $selectedQualifyingTotal,
                        2
                    ),
            ],

            'eligibleGifts' => [],

            'existingGiftCount' =>
                $totalFreeGiftItems,

            'remainingCount' =>
                max(
                    0,
                    (int) (
                        $selectedRule
                            ->freegift_add_count
                        ?? 1
                    )
                    -
                    $totalFreeGiftItems
                ),
        ];
    }

    /*
     * =========================================================
     * GET ACTUAL FREE GIFT PRODUCTS
     * =========================================================
     */
    $productRes =
        Products::whereIn(
            'sku',
            $freeGiftSkus
        )
        ->where(
            'is_free_gift_products',
            'Yes'
        )
        ->where(
            'status',
            '1'
        )
        ->get();

    /*
     * =========================================================
     * STOCK FILTER
     * =========================================================
     */
    $eligibleProducts =
        $productRes
            ->filter(
                function ($product) {

                    return $this->isStockValidForGift(
                        $product
                    );
                }
            )
            ->values();

    /*
     * =========================================================
     * NO STOCK
     * =========================================================
     */
    if (
        $eligibleProducts->isEmpty()
    ) {

        return [
            'status' => 'no_rule',

            'rule' => [
                'id' =>
                    $selectedRuleId,

                'flag_range' =>
                    (string)
                    $selectedRule->flag_range,

                'freegift_add_count' =>
                    (int) (
                        $selectedRule
                            ->freegift_add_count
                        ?? 1
                    ),

                'qualifyingTotal' =>
                    round(
                        $selectedQualifyingTotal,
                        2
                    ),
            ],

            'eligibleGifts' => [],

            'existingGiftCount' =>
                $totalFreeGiftItems,

            'remainingCount' =>
                max(
                    0,
                    (int) (
                        $selectedRule
                            ->freegift_add_count
                        ?? 1
                    )
                    -
                    $totalFreeGiftItems
                ),
        ];
    }

    /*
     * =========================================================
     * BUILD ELIGIBLE GIFT RESPONSE
     * =========================================================
     */
    $eligibleGifts =
        $eligibleProducts
            ->map(
                function ($product) use (
                    $selectedRule,
                    $selectedQualifyingTotal,
                    $selectedRuleId
                ) {

                    return [

                        'products_id' =>
                            (int)
                            $product->products_id,

                        'free_gift_products_id' =>
                            $selectedRuleId,

                        'freegift_add_count' =>
                            (int) (
                                $selectedRule
                                    ->freegift_add_count
                                ?? 1
                            ),

                        'flag_range' =>
                            (string)
                            $selectedRule->flag_range,

                        'price_start_range' =>
                            (float) (
                                $selectedRule
                                    ->price_start_range
                                ?? 0
                            ),

                        'price_end_range' =>
                            (float) (
                                $selectedRule
                                    ->price_end_range
                                ?? 0
                            ),

                        'qualifyingTotal' =>
                            round(
                                $selectedQualifyingTotal,
                                2
                            ),

                        'sku' =>
                            $product->sku,

                        'product_name' =>
                            $product->product_name,

                        'prod_image' =>
                            $product->prod_image
                            ?? '',

                        'billing_image' =>
                            $product->billing_image
                            ?? '',
                    ];
                }
            )
            ->values()
            ->all();

    /*
     * =========================================================
     * FREE GIFT COUNT
     * =========================================================
     */
    $freeGiftCount =
        (int) (
            $selectedRule
                ->freegift_add_count
            ?? 1
        );

    /*
     * =========================================================
     * POPUP / AUTO ADD DECISION
     * =========================================================
     */
    $decision =
        $this->getPopupDecision(
            $eligibleGifts,
            $totalFreeGiftItems,
            $freeGiftCount
        );

    /*
     * =========================================================
     * EXISTING RULE ID
     * =========================================================
     */
    $existingRuleId =
        $this->getExistingGiftRuleId(
            $cart
        );

    /*
     * =========================================================
     * FINAL RULE DATA
     * =========================================================
     */
    $decision['rule'] = [

        'id' =>
            $selectedRuleId,

        'flag_range' =>
            (string)
            $selectedRule->flag_range,

        'freegift_add_count' =>
            $freeGiftCount,

        'price_start_range' =>
            (float) (
                $selectedRule
                    ->price_start_range
                ?? 0
            ),

        'price_end_range' =>
            (float) (
                $selectedRule
                    ->price_end_range
                ?? 0
            ),

        'qualifyingTotal' =>
            round(
                $selectedQualifyingTotal,
                2
            ),
    ];

    /*
     * =========================================================
     * RULE CHANGED
     * =========================================================
     */
    $decision['ruleChanged'] =
        $existingRuleId > 0
        &&
        $selectedRuleId > 0
        &&
        $existingRuleId !== $selectedRuleId;

    $decision['qualificationLost'] =
        false;

    return $decision;
}
  /**
     * Calculate a candidate-specific qualifying total with
     * exclude_sku / exclude_pocketperfume applied.
     */
    protected function calculateRuleTotal(
    array $cart,
    string $flag,
    $ruleBrands,
    $ruleCategories,
    float $purchaseTotal,
    array $excludeSkus,
    bool $excludePocket
): float {

    $brandIds =
        $ruleBrands
            ->pluck('imanufactureid')
            ->map(
                fn ($id) => (string) $id
            )
            ->unique()
            ->values()
            ->all();

    $categoryIds =
        $ruleCategories
            ->pluck('categoryid')
            ->map(
                fn ($id) => (string) $id
            )
            ->unique()
            ->values()
            ->all();

    $total = 0.0;

    /*
     * =========================================================
     * BRAND + CATEGORY SEPARATE TOTALS
     * =========================================================
     *
     * For Brand,Category rule:
     *
     * Brand qualifying products
     * +
     * Category qualifying products
     *
     * are considered.
     *
     * A product matching BOTH is counted only ONCE.
     */
    $matchedItemIndexes = [];

    foreach ($cart as $index => $item) {

        /*
         * -----------------------------------------------------
         * EXCLUDE FREE GIFT / SAMPLE / DEAL
         * -----------------------------------------------------
         */
        if (
            ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
            ||
            ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
            ||
            ($item['IsDealProducts'] ?? 'No') === 'Yes'
        ) {
            continue;
        }

        /*
         * -----------------------------------------------------
         * LINE TOTAL
         * -----------------------------------------------------
         */
        $lineTotal =
            (float) (
                $item['TotPrice'] ?? 0
            );

        if ($lineTotal <= 0) {
            continue;
        }

        /*
         * -----------------------------------------------------
         * SKU
         * -----------------------------------------------------
         */
        $sku =
            trim(
                (string) (
                    $item['SKU'] ?? ''
                )
            );

        /*
         * -----------------------------------------------------
         * EXCLUDE SKU
         * -----------------------------------------------------
         */
        if (
            $sku !== ''
            &&
            in_array(
                $sku,
                $excludeSkus,
                true
            )
        ) {
            continue;
        }

        /*
         * -----------------------------------------------------
         * EXCLUDE POCKET PERFUME
         * -----------------------------------------------------
         */
        if (
            $excludePocket
            &&
            $this->isPocketPerfume(
                $item['CategoryID'] ?? 0
            )
        ) {
            continue;
        }

        /*
         * -----------------------------------------------------
         * PRODUCT BRAND / CATEGORY
         * -----------------------------------------------------
         */
        $brandId =
            (string) (
                $item['ImanufactureID']
                ??
                $item['imanufactureid']
                ??
                ''
            );

        $categoryId =
            (string) (
                $item['CategoryID']
                ??
                $item['category_id']
                ??
                ''
            );

        /*
         * =====================================================
         * PRICE / GENERAL RULE
         * =====================================================
         */
        if ($flag === '') {

            $total += $lineTotal;

            continue;
        }

        /*
         * =====================================================
         * BRAND RULE
         * =====================================================
         */
        if ($flag === 'Brand') {

            if (
                in_array(
                    $brandId,
                    $brandIds,
                    true
                )
            ) {

                $total += $lineTotal;
            }

            continue;
        }

        /*
         * =====================================================
         * CATEGORY RULE
         * =====================================================
         */
        if ($flag === 'Category') {

            if (
                in_array(
                    $categoryId,
                    $categoryIds,
                    true
                )
            ) {

                $total += $lineTotal;
            }

            continue;
        }

        /*
         * =====================================================
         * BRAND + CATEGORY RULE
         * =====================================================
         *
         * IMPORTANT:
         *
         * Brand and Category are evaluated independently.
         *
         * Example:
         *
         * Anfas product        = $1,520
         * Skincare product     = $496
         *
         * qualifying total     = $2,016
         *
         * If the SAME product is both Anfas + Skincare,
         * it is counted only once.
         */
        if (
            $flag === 'Brand,Category'
        ) {

            $matchesBrand =
                in_array(
                    $brandId,
                    $brandIds,
                    true
                );

            $matchesCategory =
                in_array(
                    $categoryId,
                    $categoryIds,
                    true
                );

            if (
                $matchesBrand
                ||
                $matchesCategory
            ) {

                /*
                 * Count this cart line only once.
                 */
                if (
                    !isset(
                        $matchedItemIndexes[$index]
                    )
                ) {

                    $total += $lineTotal;

                    $matchedItemIndexes[$index] =
                        true;
                }
            }

            continue;
        }
    }

    return (float) $total;
}
    /**
     * Stock-valid gift product for popup.
     */
    protected function isStockValidForGift(
        object $product
    ): bool {
        if (
            (float) ($product->current_stock ?? 0)
            >
            (float) ($product->minimum_stock ?? 0)
        ) {
            return true;
        }

        $vendors = [
            [
                'sku' => $product->cosmo_sku ?? '',
                'stock' => $product->cosmo_current_stock ?? 0,
            ],
            [
                'sku' => $product->pca_sku ?? '',
                'stock' => $product->pca_current_stock ?? 0,
            ],
            [
                'sku' => $product->nandansons_sku ?? '',
                'stock' => $product->nandansons_current_stock ?? 0,
            ],
            [
                'sku' =>
                    $product->perfumeworldwide_sku ?? '',
                'stock' =>
                    $product->perfumeworldwide_currentstock ?? 0,
            ],
            [
                'sku' => $product->nd_sku ?? '',
                'stock' => $product->nd_current_stock ?? 0,
            ],
        ];

        foreach ($vendors as $vendor) {
            if (
                trim((string) $vendor['sku']) !== ''
                && (float) $vendor['stock'] > 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Existing Free Gift rule identifier stored on the cart item.
     */
    protected function getExistingGiftRuleId(
        array $cart
    ): int {
        foreach ($cart as $item) {
            if (
                ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
            ) {
                return (int) (
                    $item['freeproductsid']
                    ?? $item['FreeGiftRuleId']
                    ?? 0
                );
            }
        }

        return 0;
    }

    /**
     * Pocket-perfume helper.
     *
     * The project already exposes these IDs through
     * CheckoutConstants. If unavailable, no category is treated
     * as pocket perfume rather than inventing IDs.
     */
   /**
 * Check whether a product category is a Pocket Perfume category.
 *
 * Source of truth:
 * App\Constants\CheckoutConstants
 */
	protected function isPocketPerfume(
		$categoryId
	): bool {
		$categories =
			\App\Constants\CheckoutConstants::POCKET_PERFUME_CATEGORIES;

		return in_array(
			(int) $categoryId,
			$categories,
			true
		);
	}
	/**
 * Remove ALL Free Gifts from the checkout cart.
 *
 * Used when the customer no longer qualifies for
 * any Free Gift rule.
 *
 * Important:
 * - Normal products are preserved.
 * - Free Samples are preserved.
 * - All actual Free Gift lines are removed,
 *   regardless of FreeGiftAutoAdded value.
 */
public function removeAllFreeGifts(): int
{
    $cart =
        Session::get(
            'ShoppingCart.Cart',
            []
        );

    if (
        !is_array($cart)
        ||
        empty($cart)
    ) {
        return 0;
    }

    $removed = 0;

    $newCart = [];

    foreach (
        $cart as $item
    ) {

        $isFreeGift =
            ($item['IS_Free_Gift'] ?? 'No')
            === 'Yes';

        $isFreeSample =
            ($item['Is_Free_Sample'] ?? 'No')
            === 'Yes';

        /*
         * Remove every Free Gift.
         *
         * Free Sample is NOT a Free Gift and
         * must remain in the cart.
         */
        if (
            $isFreeGift
            &&
            !$isFreeSample
        ) {

            $removed++;

            continue;
        }

        /*
         * Preserve:
         * - normal products
         * - Free Samples
         */
        $newCart[] =
            $item;
    }

    if (
        $removed > 0
    ) {

        Session::put(
            'ShoppingCart.Cart',
            array_values(
                $newCart
            )
        );

        Log::info(
            'Free Gift all removal',
            [
                'removedCount' =>
                    $removed,

                'reason' =>
                    'qualification_lost',
            ]
        );
    }

    return $removed;
}
}
