<?php

namespace App\Services\Discount;

use App\Models\Products;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

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
    public function addGift(
        $productsId,
        $freeProductsId = 0,
        $oneGift = 'No'
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
             * OneGift = Yes:
             *
             * remove existing normal free gifts
             * before adding the new one.
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
                    $cart
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
        array $cart
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
    'Yes';
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
        if (!$this->isEligibleCustomer()) {
            return [
                'status' => 'disabled',
                'rule' => null,
                'eligibleGifts' => [],
                'existingGiftCount' => $totalFreeGiftItems,
                'remainingCount' => 0,
            ];
        }

        if (
            config('Settings.CHECKOUT_SHOIPPINGCART') !== 'Yes'
            || $totalValue <= 0
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
         * Build real purchase totals only.
         */
        $brandTotals = [];
        $categoryTotals = [];
        $brandCategoryTotals = [];
        $purchaseTotal = 0.0;

        foreach ($cart as $item) {
            if (
                ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
                || ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
                || ($item['IsDealProducts'] ?? 'No') === 'Yes'
            ) {
                continue;
            }

            $lineTotal = (float) ($item['TotPrice'] ?? 0);
            if ($lineTotal <= 0) {
                continue;
            }

            $purchaseTotal += $lineTotal;

            $brandId = (string) (
                $item['ImanufactureID']
                ?? $item['imanufactureid']
                ?? ''
            );

            $categoryId = (string) (
                $item['CategoryID']
                ?? $item['category_id']
                ?? ''
            );

            if ($brandId !== '') {
                $brandTotals[$brandId] =
                    ($brandTotals[$brandId] ?? 0) + $lineTotal;
            }

            if ($categoryId !== '') {
                $categoryTotals[$categoryId] =
                    ($categoryTotals[$categoryId] ?? 0) + $lineTotal;
            }

            if ($brandId !== '' && $categoryId !== '') {
                $key = $brandId . '_' . $categoryId;
                $brandCategoryTotals[$key] =
                    ($brandCategoryTotals[$key] ?? 0) + $lineTotal;
            }
        }

        /*
         * Use the already-calculated discounted cart value when supplied.
         * The legacy caller passes SubTotal - TotalDiscount.
         */
        $purchaseTotal =
            $totalValue > 0
                ? (float) $totalValue
                : $purchaseTotal;

        $queryBase = DB::table('pu_free_gift_product')
            ->where('status', '1')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);

        $queries = [];

        /*
         * Price/general rule.
         */
        $queries[] = (clone $queryBase)
            ->where('flag_range', '');

        /*
         * Brand rule.
         */
        $brandQuery = (clone $queryBase)
            ->where('flag_range', 'Brand')
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
            $brandQuery->whereRaw('1 = 0');
        }

        $queries[] = $brandQuery->select(
            'pu_free_gift_product.*'
        );

        /*
         * Category rule.
         */
        $categoryQuery = (clone $queryBase)
            ->where('flag_range', 'Category')
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
            $categoryQuery->whereRaw('1 = 0');
        }

        $queries[] = $categoryQuery->select(
            'pu_free_gift_product.*'
        );

        /*
         * Brand + Category rule.
         * Both mappings must match.
         */
        $comboQuery = (clone $queryBase)
            ->where('flag_range', 'Brand,Category')
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
            $comboQuery->whereRaw('1 = 0');
        }

        if (!empty($categoryTotals)) {
            $comboQuery->whereIn(
                'c.categoryid',
                array_keys($categoryTotals)
            );
        } else {
            $comboQuery->whereRaw('1 = 0');
        }

        $queries[] = $comboQuery->select(
            'pu_free_gift_product.*'
        );

        /*
         * Union all candidates and keep highest range first.
         * Rule priority is applied explicitly below.
         */
        $combined = array_shift($queries);
        foreach ($queries as $query) {
            $combined = $combined->unionAll($query);
        }

        $candidates = DB::query()
            ->fromSub($combined, 'fg')
            ->orderByDesc('price_end_range')
            ->get();

        if ($candidates->isEmpty()) {
            return [
                'status' => 'no_rule',
                'rule' => null,
                'eligibleGifts' => [],
                'existingGiftCount' => $totalFreeGiftItems,
                'remainingCount' => 0,
            ];
        }

        $candidateIds = $candidates
            ->pluck('products_id')
            ->unique()
            ->values()
            ->all();

        $brandMap = DB::table('pu_freegift_brand')
            ->whereIn('products_id', $candidateIds)
            ->get()
            ->groupBy('products_id');

        $categoryMap = DB::table('pu_freegift_category')
            ->whereIn('products_id', $candidateIds)
            ->get()
            ->groupBy('products_id');

        /*
         * Highest priority first:
         * Brand+Category > Brand > Category > Price.
         *
         * Within a rule type, higher price_end_range wins.
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

        foreach ($candidates as $candidate) {
            $flag = trim((string) $candidate->flag_range);
            $qualifyingTotal = 0.0;

            $ruleBrands = $brandMap->get(
                $candidate->products_id,
                collect()
            );

            $ruleCategories = $categoryMap->get(
                $candidate->products_id,
                collect()
            );

            if ($flag === '') {
                $qualifyingTotal = $purchaseTotal;
            } elseif ($flag === 'Brand') {
                foreach ($ruleBrands as $brand) {
                    $id = (string) $brand->imanufactureid;
                    $qualifyingTotal +=
                        (float) ($brandTotals[$id] ?? 0);
                }
            } elseif ($flag === 'Category') {
                foreach ($ruleCategories as $category) {
                    $id = (string) $category->categoryid;
                    $qualifyingTotal +=
                        (float) ($categoryTotals[$id] ?? 0);
                }
            } elseif ($flag === 'Brand,Category') {
                foreach ($ruleBrands as $brand) {
                    foreach ($ruleCategories as $category) {
                        $key =
                            $brand->imanufactureid
                            . '_'
                            . $category->categoryid;

                        $qualifyingTotal +=
                            (float) (
                                $brandCategoryTotals[$key] ?? 0
                            );
                    }
                }
            }

            /*
             * Per-rule exclusions.
             */
            $excludeSkus = array_values(
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

            $excludePocket =
                trim(
                    (string) (
                        $candidate->exclude_pocketperfume
                        ?? ''
                    )
                ) === 'Yes';

            if (
                !empty($excludeSkus)
                || $excludePocket
            ) {
                /*
                 * Recalculate this rule from cart so exclusions are
                 * applied only to this candidate. This preserves the
                 * legacy per-rule exclusion behavior.
                 */
                $qualifyingTotal = $this->calculateRuleTotal(
                    $cart,
                    $flag,
                    $ruleBrands,
                    $ruleCategories,
                    $purchaseTotal,
                    $excludeSkus,
                    $excludePocket
                );
            }

            if (
                $qualifyingTotal
                <
                (float) ($candidate->price_start_range ?? 0)
            ) {
                continue;
            }

            $candidatePriority =
                $priority[$flag] ?? 0;

            if (
                $candidatePriority > $selectedPriority
                ||
                (
                    $candidatePriority === $selectedPriority
                    &&
                    (float) ($candidate->price_end_range ?? 0)
                    >
                    (float) (
                        $selectedRule->price_end_range ?? 0
                    )
                )
            ) {
                $selectedRule = $candidate;
                $selectedQualifyingTotal = $qualifyingTotal;
                $selectedPriority = $candidatePriority;
            }
        }

        if (!$selectedRule) {
            return [
                'status' => 'no_rule',
                'rule' => null,
                'eligibleGifts' => [],
                'existingGiftCount' => $totalFreeGiftItems,
                'remainingCount' => 0,
            ];
        }

        /*
 * =========================================================
 * GET ACTUAL FREE GIFT PRODUCTS
 * =========================================================
 *
 * IMPORTANT:
 * pu_free_gift_product.products_id is the FREE GIFT RULE ID.
 *
 * The actual gift products are identified by the rule's
 * SKU field.
 *
 * This preserves legacy GetFreeCouponPopup() behaviour.
 */
$freeGiftSkus = [];

if (
    isset($selectedRule->sku) &&
    trim((string) $selectedRule->sku) !== ''
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

if (empty($freeGiftSkus)) {
    return [
        'status' => 'no_rule',
        'rule' => [
            'id' =>
                (int) $selectedRule->products_id,
            'flag_range' =>
                (string) $selectedRule->flag_range,
            'freegift_add_count' =>
                (int) (
                    $selectedRule
                        ->freegift_add_count ?? 1
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
                        ->freegift_add_count ?? 1
                ) -
                $totalFreeGiftItems
            ),
    ];
}

/*
 * Actual gift products.
 *
 * Same as legacy:
 * Products::whereIn('sku', $FreeGiftValue)
 *     ->where('is_free_gift_products', 'Yes')
 *     ->where('status', '1')
 */
$eligibleProducts =
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
    ->get()
    ->filter(
        function ($product) {
            return $this->isStockValidForGift(
                $product
            );
        }
    )
    ->values();

if ($eligibleProducts->isEmpty()) {
    return [
        'status' => 'no_rule',
        'rule' => [
            'id' =>
                (int) $selectedRule->products_id,
            'flag_range' =>
                (string) $selectedRule->flag_range,
            'freegift_add_count' =>
                (int) (
                    $selectedRule
                        ->freegift_add_count ?? 1
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
                        ->freegift_add_count ?? 1
                ) -
                $totalFreeGiftItems
            ),
    ];
}
        $eligibleGifts = $eligibleProducts
            ->map(function ($product) use (
                $selectedRule,
                $selectedQualifyingTotal
            ) {
                return [
                    'products_id' =>
                        (int) $product->products_id,
                    'free_gift_products_id' =>
                        (int) (
                            $selectedRule->free_gift_products_id
                            ?? 0
                        ),
                    'freegift_add_count' =>
                        (int) (
                            $selectedRule->freegift_add_count
                            ?? 1
                        ),
                    'flag_range' =>
                        (string) $selectedRule->flag_range,
                    'price_start_range' =>
                        (float) (
                            $selectedRule->price_start_range
                            ?? 0
                        ),
                    'price_end_range' =>
                        (float) (
                            $selectedRule->price_end_range
                            ?? 0
                        ),
                    'qualifyingTotal' =>
                        round($selectedQualifyingTotal, 2),
                    'sku' =>
                        $product->sku,
                    'product_name' =>
                        $product->product_name,
                    'prod_image' =>
                        $product->prod_image ?? '',
                    'billing_image' =>
                        $product->billing_image ?? '',
                ];
            })
            ->values()
            ->all();

        $freeGiftCount = (int) (
            $selectedRule->freegift_add_count ?? 1
        );

        $decision = $this->getPopupDecision(
            $eligibleGifts,
            $totalFreeGiftItems,
            $freeGiftCount
        );

        $existingRuleId = $this->getExistingGiftRuleId($cart);

        $selectedRuleId =
            (int) (
                $selectedRule->products_id
                ?? $selectedRule->id
                ?? 0
            );

        $decision['rule'] = [
            'id' => $selectedRuleId,
            'flag_range' =>
                (string) $selectedRule->flag_range,
            'freegift_add_count' =>
                $freeGiftCount,
            'qualifyingTotal' =>
                round($selectedQualifyingTotal, 2),
        ];

        $decision['ruleChanged'] =
            $existingRuleId > 0
            && $selectedRuleId > 0
            && $existingRuleId !== $selectedRuleId;

        $decision['qualificationLost'] = false;

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
        $brandIds = $ruleBrands
            ->pluck('imanufactureid')
            ->map(fn ($id) => (string) $id)
            ->all();

        $categoryIds = $ruleCategories
            ->pluck('categoryid')
            ->map(fn ($id) => (string) $id)
            ->all();

        $total = 0.0;

        foreach ($cart as $item) {
            if (
                ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
                || ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
                || ($item['IsDealProducts'] ?? 'No') === 'Yes'
            ) {
                continue;
            }

            $sku = trim(
                (string) ($item['SKU'] ?? '')
            );

            if (
                $sku !== ''
                && in_array($sku, $excludeSkus, true)
            ) {
                continue;
            }

            if (
                $excludePocket
                && $this->isPocketPerfume(
                    $item['CategoryID'] ?? 0
                )
            ) {
                continue;
            }

            $brandId = (string) (
                $item['ImanufactureID']
                ?? $item['imanufactureid']
                ?? ''
            );

            $categoryId = (string) (
                $item['CategoryID']
                ?? $item['category_id']
                ?? ''
            );

            $matches = match ($flag) {
                '' =>
                    true,

                'Brand' =>
                    in_array(
                        $brandId,
                        $brandIds,
                        true
                    ),

                'Category' =>
                    in_array(
                        $categoryId,
                        $categoryIds,
                        true
                    ),

                'Brand,Category' =>
                    in_array(
                        $brandId,
                        $brandIds,
                        true
                    )
                    &&
                    in_array(
                        $categoryId,
                        $categoryIds,
                        true
                    ),

                default =>
                    false,
            };

            if ($matches) {
                $total +=
                    (float) ($item['TotPrice'] ?? 0);
            }
        }

        return $total;
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
    protected function isPocketPerfume(
        $categoryId
    ): bool {
        if (
            class_exists(
                \App\Services\Checkout\CheckoutConstants::class
            )
        ) {
            $categories =
                \App\Services\Checkout\CheckoutConstants::POCKET_PERFUME_CATEGORIES
                ?? [];

            return in_array(
                (int) $categoryId,
                $categories,
                true
            );
        }

        return false;
    }

}
