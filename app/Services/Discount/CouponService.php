<?php

namespace App\Services\Discount;

use App\Constants\CheckoutConstants;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Manufacture;
use App\Models\Order;
use App\Models\Products;
use App\Models\ProductsCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CouponService
{
    public function __construct(
        protected FreeGiftService $freeGiftService
    ) {
    }

    /**
     * Apply the currently active coupon.
     *
     * ApplyCouponDiscountSecond() is intentionally NOT included.
     */
    public function apply(
        string $couponCode,
        $customerId = null
    ): array {
        $couponCode = trim($couponCode);
        $customerId = (int) $customerId;

        $error = 0;
        $message = '';
        $couponDiscount = 0.0;
        $freeShipping = false;

        $log = [
            'couponCode' => $couponCode,
            'customerId' => $customerId,
        ];

        addLog(
            'ApplyCouponDiscountStart',
            $log
        );

        if ($couponCode === '') {
            return $this->invalidCoupon(
                'Invalid Coupon Code.',
                $log
            );
        }

        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        if (empty($cart)) {
            return $this->invalidCoupon(
                'Invalid Coupon Code.',
                $log
            );
        }

        /*
         * ---------------------------------------------------------
         * Coupon lookup
         * ---------------------------------------------------------
         *
         * Preserve existing user-type filtering.
         */
        $user = Auth::user();

        if (
            Auth::guard('store')->check()
        ) {
            $user =
                Auth::guard('web')->user();
        }

        $couponQuery =
            Coupon::where(
                'coupon_number',
                $couponCode
            )
            ->where(
                'status',
                '1'
            )
            ->where(
                'start_date',
                '<=',
                DB::raw('curdate()')
            )
            ->where(
                'end_date',
                '>=',
                DB::raw('curdate()')
            );

        if ($user) {
            $couponQuery->where(
                'coupon_user_type',
                $user->eusertype ?: 'Retailer'
            );
        } else {
            $couponQuery->where(
                'coupon_user_type',
                'Retailer'
            );
        }

        $coupon =
            $couponQuery->first();

        if (!$coupon) {
            return $this->invalidCoupon(
                'Invalid Coupon Code.',
                $log
            );
        }

        $log['coupon'] =
            $coupon->toArray();

        /*
         * ---------------------------------------------------------
         * Basic coupon session flags
         * ---------------------------------------------------------
         */
        if (
            (string) $coupon->type === '1'
        ) {
            Session::put(
                'ShoppingCart.CountShipTax',
                $coupon->count_ship_tax
            );

            Session::put(
                'ShoppingCart.CouponPercentage',
                $coupon->discount
            );
        }

        /*
         * Coupon can disable other discounts.
         */
        if (
            $coupon->autodiscount_flag === 'No'
        ) {
            Session::put(
                'ShoppingCart.AutoDiscount',
                0.0
            );

            Session::put(
                'ShoppingCart.AutoDiscountFlag',
                ''
            );
        }

        if (
            $coupon->bogodiscount_flag === 'No'
        ) {
            Session::put(
                'ShoppingCart.DogoDiscount',
                0.0
            );

            Session::put(
                'ShoppingCart.BogoDiscountFlag',
                ''
            );
        }

        if (
            $coupon->quantitydiscount_flag === 'No'
        ) {
            Session::put(
                'ShoppingCart.QuantityDiscount',
                0.0
            );

            Session::put(
                'ShoppingCart.QuantityDiscountFlag',
                ''
            );
        }

        /*
         * ---------------------------------------------------------
         * Excluded SKU list
         * ---------------------------------------------------------
         */
        $excludeSkuList =
            $this->csvToArray(
                $coupon->exclude_product_skus
            );

        /*
         * ---------------------------------------------------------
         * Cart eligibility
         * ---------------------------------------------------------
         */
        $cartInfo =
            array_values($cart);

        $cartItemFound = false;
        $isDealBlocked = true;

        $totalDealPrice = 0.0;

        foreach (
            $cartInfo as $item
        ) {
            $isGiftCertificate =
                $this->isGiftCertificateItem(
                    $item
                );

            if (!$isGiftCertificate) {
                $cartItemFound = true;
            }

            /*
             * Existing deal-product condition.
             */
            $isDeal =
                ($item['IsDealProducts'] ?? '')
                === 'Yes';

            if (
                !$isDeal
            ) {
                $isDealBlocked = false;
            } elseif (
                (
                    $item['DealDiscountFlag']
                    ?? ''
                ) === 'Yes'
                ||
                (
                    $coupon->dealdiscount_flag
                    ?? ''
                ) === 'Yes'
            ) {
                $isDealBlocked = false;
            }

            if (
                $isDeal &&
                (
                    $item['DealDiscountFlag']
                    ?? ''
                ) !== 'Yes'
                &&
                (
                    $coupon->dealdiscount_flag
                    ?? ''
                ) === 'No'
            ) {
                $totalDealPrice +=
                    (float)
                    (
                        $item['TotPrice']
                        ?? 0
                    );
            }
        }

        /*
         * No normal item.
         */
        if (!$cartItemFound) {
            return $this->invalidCartCoupon(
                $log
            );
        }

        /*
         * All cart products are blocked by deal rules.
         */
        if ($isDealBlocked) {
            return $this->invalidCartCoupon(
                $log
            );
        }

        /*
         * ---------------------------------------------------------
         * Gift Certificate amount
         * ---------------------------------------------------------
         */
        $giftCertificateTotal =
            (float)
            Session::get(
                'ShoppingCart.GiftCertiTotal',
                0
            );

        /*
         * ---------------------------------------------------------
         * Subtotals
         * ---------------------------------------------------------
         */
        $subTotal =
            (float)
            Session::get(
                'ShoppingCart.SubTotal',
                0
            );

        $grandTotal =
            (float)
            Session::get(
                'ShoppingCart.GrandTotal',
                $subTotal
            );

        $grandTotalSale =
            (float)
            Session::get(
                'ShoppingCart.GrandTotalSale',
                $subTotal
            );

        $saleTotal =
            $subTotal
            - $giftCertificateTotal
            - $totalDealPrice;

        /*
         * ---------------------------------------------------------
         * One-time coupon validation
         * ---------------------------------------------------------
         */
        $switchCase =
            $this->resolveCouponCase(
                $coupon,
                $customerId
            );

        if ($switchCase === '') {
            return $this->invalidCoupon(
                'Coupon code is invalid or does not exists.',
                $log
            );
        }

        /*
         * ---------------------------------------------------------
         * Apply coupon according to orders.
         * ---------------------------------------------------------
         */
        switch (
            (string) $switchCase
        ) {
            /*
             * -----------------------------------------------------
             * Order Amount
             * -----------------------------------------------------
             */
            case '0':

                $result =
                    $this->applyOrderAmountCoupon(
                        $coupon,
                        $subTotal,
                        $grandTotal,
                        $grandTotalSale,
                        $giftCertificateTotal,
                        $totalDealPrice,
                        $excludeSkuList
                    );

                $couponDiscount =
                    $result['discount'];

                break;

            /*
             * -----------------------------------------------------
             * Product SKU
             * -----------------------------------------------------
             */
            case '1':

                $result =
                    $this->applySkuCoupon(
                        $coupon,
                        $excludeSkuList
                    );

                $couponDiscount =
                    $result['discount'];

                break;

            /*
             * Existing case 2 is intentionally empty.
             */
            case '2':

                $couponDiscount = 0;

                break;

            /*
             * -----------------------------------------------------
             * Product Category
             * -----------------------------------------------------
             */
            case '3':

                $result =
                    $this->applyCategoryCoupon(
                        $coupon,
                        $excludeSkuList
                    );

                $couponDiscount =
                    $result['discount'];

                if (
                    !$result['matched']
                ) {
                    return $this->invalidCartCoupon(
                        $log
                    );
                }

                break;

            /*
             * -----------------------------------------------------
             * Free Shipping
             * -----------------------------------------------------
             */
            case '4':

                $result =
                    $this->applyFreeShippingCoupon(
                        $coupon,
                        $subTotal,
                        $grandTotal,
                        $grandTotalSale,
                        $giftCertificateTotal,
                        $totalDealPrice,
                        $excludeSkuList
                    );

                $couponDiscount =
                    $result['discount'];

                $freeShipping =
                    $result['free_shipping'];

                break;

            /*
             * Existing case 5 is empty.
             */
            case '5':

                $couponDiscount = 0;

                break;

            /*
             * -----------------------------------------------------
             * Product Brand
             * -----------------------------------------------------
             */
            case '6':

                $result =
                    $this->applyBrandCoupon(
                        $coupon,
                        $excludeSkuList
                    );

                $couponDiscount =
                    $result['discount'];

                if (
                    !$result['matched']
                ) {
                    return $this->invalidCartCoupon(
                        $log
                    );
                }

                break;

            /*
             * -----------------------------------------------------
             * Serialized / multi-SKU coupon
             * -----------------------------------------------------
             */
            case '7':

                $result =
                    $this->applySerializedSkuCoupon(
                        $coupon,
                        $excludeSkuList
                    );

                $couponDiscount =
                    $result['discount'];

                if (
                    $result['free_gift_sku']
                ) {
                    $this->insertFreeGift(
                        $result['free_gift_sku']
                    );
                }

                break;

            default:

                return $this->invalidCoupon(
                    'Invalid Coupon Code.',
                    $log
                );
        }

        /*
         * ---------------------------------------------------------
         * Free shipping
         * ---------------------------------------------------------
         */
        if (
            $coupon->allow_free_shipping ===
                'Yes'
            &&
            $coupon->free_shipping_value !== ''
            &&
            $couponDiscount > 0
        ) {
            Session::put(
                'ShoppingCart.PromoCoupon.FreeShipping',
                'Yes'
            );

            Session::put(
                'ShoppingCart.PromoCoupon.FreeShippingCouponModeID',
                $this->csvToArray(
                    $coupon->free_shipping_value
                )
            );

            Session::put(
                'ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',
                'Yes'
            );

            $freeShipping = true;
        }

        /*
         * ---------------------------------------------------------
         * Coupon free gift
         * ---------------------------------------------------------
         */
        if (
            $coupon->allow_free_gift_product ===
                'Yes'
            &&
            $coupon->free_gift_product_value !== ''
            &&
            $couponDiscount > 0
        ) {
            $this->insertFreeGift(
                $coupon->free_gift_product_value
            );
        }

        /*
         * ---------------------------------------------------------
         * Save coupon session
         * ---------------------------------------------------------
         */
        if (
            $couponDiscount > 0 ||
            $freeShipping
        ) {
            $this->saveCouponSession(
                $coupon,
                $couponCode,
                $couponDiscount
            );

            $message =
                'Coupon code applied successfully.';

            $log['CouponDiscount'] =
                $couponDiscount;

            $log['FreeShipping'] =
                $freeShipping;

            addLog(
                'ApplyCouponDiscount',
                $log
            );

            return [
                'error' => 0,
                'message' => $message,
                'discount' =>
                    NumberFormat(
                        $couponDiscount
                    ),
            ];
        }

        return $this->invalidCoupon(
            'Coupon code is invalid or does not exists.',
            $log
        );
    }

    /**
     * Order amount coupon.
     */
  protected function applyOrderAmountCoupon(
    $coupon,
    float $subTotal,
    float $grandTotal,
    float $grandTotalSale,
    float $giftCertificateTotal,
    float $totalDealPrice,
    array $excludeSkuList
): array {

    /*
     * =========================================================
     * OLD CHECKOUT COMPATIBILITY
     * =========================================================
     *
     * Old CartTrait behavior:
     *
     * count_ship_tax = 1
     *
     * GrandTotalSale =
     *     SubTotal
     *     - Deal Price
     *     - Excluded SKU Price
     *     - Gift Certificate
     *     + Shipping
     *     + Tax
     *
     * Do NOT rely on the session GrandTotalSale here because
     * the new One Page Checkout can have a freshly calculated
     * shipping/tax value while the old session value is stale.
     * =========================================================
     */

    $tempSubTotal =
        $grandTotal;

    $tempSaleTotal =
        $grandTotalSale;


    /*
     * Calculate excluded SKU total.
     *
     * This preserves old:
     *
     * TotalExcludePrice
     *
     * behavior.
     */
    $totalExcludePrice = 0.0;

    $cart =
        Session::get(
            'ShoppingCart.Cart',
            []
        );

    if (
        !empty($excludeSkuList)
        &&
        is_array($cart)
    ) {

        foreach ($cart as $item) {

            $sku =
                (string) (
                    $item['SKU'] ?? ''
                );

            if (
                $sku !== ''
                &&
                in_array(
                    $sku,
                    $excludeSkuList,
                    true
                )
            ) {

                $totalExcludePrice +=
                    (float) (
                        $item['TotPrice'] ?? 0
                    );
            }
        }
    }


    /*
     * ---------------------------------------------------------
     * Count Shipping + Tax
     * ---------------------------------------------------------
     */
    if (
        (string)
        $coupon->count_ship_tax ===
        '1'
    ) {

        /*
         * Preserve old session flag.
         */
        Session::put(
            'count_ship_tax',
            1
        );

        /*
         * Current checkout shipping charge.
         */
        $shippingCharge =
            (float) Session::get(
                'ShoppingCart.Shipping.ShippingCharge',
                0
            );

        /*
         * Current checkout tax.
         */
        $taxValue =
            (float) Session::get(
                'ShoppingCart.Tax',
                0
            );


        /*
         * Old behavior:
         *
         * SubTotal
         * - Deal
         * - Excluded
         * - Gift Certificate
         * + Shipping
         * + Tax
         */
        $tempSaleTotal =
            (float) $subTotal
            - (float) $totalDealPrice
            - (float) $totalExcludePrice
            - (float) $giftCertificateTotal
            + $shippingCharge
            + $taxValue;


        /*
         * Keep the subtotal value compatible with
         * the old flow.
         */
        $tempSubTotal =
            (float) $subTotal
            - (float) $totalDealPrice
            - (float) $totalExcludePrice
            - (float) $giftCertificateTotal;

    } else {

        /*
         * Old behavior when shipping/tax is NOT included.
         */
        $tempSubTotal =
            (float) $subTotal
            - (float) $giftCertificateTotal
            - (float) $totalDealPrice
            - (float) $totalExcludePrice;


        $tempSaleTotal =
            (float) $subTotal
            - (float) $giftCertificateTotal
            - (float) $totalDealPrice
            - (float) $totalExcludePrice;
    }


    /*
     * ---------------------------------------------------------
     * Free-gift-only coupon
     * ---------------------------------------------------------
     */
    if (
        $coupon->discount <= 0
        &&
        !empty(
            $coupon->freegift_product_sku
        )
    ) {
        return [
            'discount' => 0,
        ];
    }


    /*
     * ---------------------------------------------------------
     * Minimum/order amount validation
     * ---------------------------------------------------------
     */
    if (
        $tempSaleTotal <
            (float) $coupon->order_amount
        ||
        $tempSaleTotal <
            (float) $coupon->minimum_order_amount
    ) {

        return [
            'discount' => 0,
        ];
    }


    /*
     * ---------------------------------------------------------
     * Percentage / fixed discount
     * ---------------------------------------------------------
     */
    if (
        (string)
        $coupon->type === '1'
    ) {

        $discount =
            $tempSaleTotal
            *
            (
                (float)
                $coupon->discount
                / 100
            );

    } else {

        $discount =
            (float)
            $coupon->discount;
    }


    /*
     * ---------------------------------------------------------
     * Preserve existing item-wise calculation.
     * ---------------------------------------------------------
     */
    $this->applyOrderAmountItemWise(
        $coupon,
        $discount,
        $tempSaleTotal,
        $excludeSkuList
    );


    return [
        'discount' =>
            $discount,
    ];
}
  
   /**
     * Product SKU coupon.
     */
    protected function applySkuCoupon(
        $coupon,
        array $excludeSkuList
    ): array {
        $couponSkus =
            $this->csvToArray(
                $coupon->sku
            );

        if (
            empty($couponSkus)
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        $matched = false;
        $matchedTotal = 0.0;
        $totalAmount = 0.0;

        foreach (
            $cart as $index => $item
        ) {
            if (
                !in_array(
                    $item['SKU'] ?? '',
                    $couponSkus,
                    true
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $item['SKU'] ?? '',
                    $excludeSkuList,
                    true
                )
            ) {
                continue;
            }

            if (
                !$this->isCouponEligibleDeal(
                    $item,
                    $coupon
                )
            ) {
                continue;
            }

            $matched = true;

            $totalAmount +=
                (float)
                (
                    $item['TotPrice']
                    ?? 0
                );

            if (
                (string)
                $coupon->type === '1'
            ) {
                $itemDiscount =
                    (
                        (float)
                        (
                            $item['Price']
                            ?? 0
                        )
                        *
                        (int)
                        (
                            $item['Qty']
                            ?? 0
                        )
                    )
                    *
                    (
                        (float)
                        $coupon->discount
                        / 100
                    );

                $this->setItemWiseCouponDiscount(
                    $index,
                    $coupon,
                    $itemDiscount
                );

                $matchedTotal +=
                    (
                        (float)
                        (
                            $item['Price']
                            ?? 0
                        )
                        *
                        (int)
                        (
                            $item['Qty']
                            ?? 0
                        )
                    );
            }
        }

        if (
            !$matched
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        if (
            (string)
            $coupon->type === '0'
            &&
            $totalAmount > 0
        ) {
            foreach (
                $cart as $index => $item
            ) {
                if (
                    !in_array(
                        $item['SKU'] ?? '',
                        $couponSkus,
                        true
                    )
                ) {
                    continue;
                }

                if (
                    in_array(
                        $item['SKU'] ?? '',
                        $excludeSkuList,
                        true
                    )
                ) {
                    continue;
                }

                if (
                    !$this->isCouponEligibleDeal(
                        $item,
                        $coupon
                    )
                ) {
                    continue;
                }

                $itemDiscount =
                    (
                        (float)
                        $coupon->discount
                        * 100
                    )
                    /
                    $totalAmount;

                $itemDiscount =
                    (
                        (float)
                        (
                            $item['TotPrice']
                            ?? 0
                        )
                        *
                        $itemDiscount
                    )
                    / 100;

                $this->setItemWiseCouponDiscount(
                    $index,
                    $coupon,
                    $itemDiscount
                );
            }

            $discount =
                (float)
                $coupon->discount;
        } else {
            $discount =
                $matchedTotal
                *
                (
                    (float)
                    $coupon->discount
                    / 100
                );
        }

        if (
            $coupon->minimum_order_amount > 0
            &&
            $totalAmount <
                $coupon->minimum_order_amount
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        return [
            'discount' =>
                $discount,
            'matched' => true,
        ];
    }

    /**
     * Category coupon.
     *
     * Uses the existing ProductsCategory pivot query.
     */
    protected function applyCategoryCoupon(
        $coupon,
        array $excludeSkuList
    ): array {
        $categoryIds =
            $this->csvToArray(
                $coupon->sku
            );

        $activeCategoryIds =
            Category::where(
                'status',
                '1'
            )
            ->whereIn(
                'category_id',
                $categoryIds
            )
            ->pluck(
                'category_id'
            )
            ->toArray();

        if (
            empty($activeCategoryIds)
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        $productIds =
            collect($cart)
                ->pluck('ProductID')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

        if (
            empty($productIds)
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        $categoryProductIds =
            ProductsCategory::whereIn(
                'category_id',
                $activeCategoryIds
            )
            ->whereIn(
                'products_id',
                $productIds
            )
            ->distinct()
            ->pluck(
                'products_id'
            )
            ->toArray();

        $matched = false;
        $totalAmount = 0.0;

        foreach (
            $cart as $index => $item
        ) {
            if (
                !in_array(
                    $item['ProductID'] ?? null,
                    $categoryProductIds
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $item['SKU'] ?? '',
                    $excludeSkuList,
                    true
                )
            ) {
                continue;
            }

            if (
                !$this->isCouponEligibleDeal(
                    $item,
                    $coupon
                )
            ) {
                continue;
            }

            $matched = true;

            $totalAmount +=
                (float)
                (
                    $item['TotPrice']
                    ?? 0
                );

            if (
                (string)
                $coupon->type === '1'
            ) {
                $itemDiscount =
                    (
                        (float)
                        (
                            $item['Price']
                            ?? 0
                        )
                        *
                        (int)
                        (
                            $item['Qty']
                            ?? 0
                        )
                    )
                    *
                    (
                        (float)
                        $coupon->discount
                        / 100
                    );

                $this->setItemWiseCouponDiscount(
                    $index,
                    $coupon,
                    $itemDiscount
                );
            }
        }

        if (
            !$matched
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        if (
            $coupon->minimum_order_amount > 0
            &&
            $totalAmount <
                $coupon->minimum_order_amount
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        if (
            (string)
            $coupon->type === '1'
        ) {
            $discount =
                $totalAmount
                *
                (
                    (float)
                    $coupon->discount
                    / 100
                );
        } else {
            $discount =
                (float)
                $coupon->discount;
        }

        return [
            'discount' =>
                $discount,
            'matched' => true,
        ];
    }

    /**
     * Free shipping coupon.
     */
    protected function applyFreeShippingCoupon(
        $coupon,
        float $subTotal,
        float $grandTotal,
        float $grandTotalSale,
        float $giftCertificateTotal,
        float $totalDealPrice,
        array $excludeSkuList
    ): array {
        /*
         * The coupon may still carry a discount.
         * Preserve normal order-amount eligibility.
         */
        $saleTotal =
            $subTotal
            - $giftCertificateTotal
            - $totalDealPrice;

        $discount = 0.0;

        if (
            $saleTotal >=
                (float)
                $coupon->minimum_order_amount
        ) {
            if (
                (string)
                $coupon->type === '1'
            ) {
                $discount =
                    $saleTotal
                    *
                    (
                        (float)
                        $coupon->discount
                        / 100
                    );
            } else {
                $discount =
                    (float)
                    $coupon->discount;
            }
        }

        return [
            'discount' =>
                $discount,

            'free_shipping' =>
                (
                    $coupon->allow_free_shipping ===
                    'Yes'
                    &&
                    $coupon->free_shipping_value !== ''
                    &&
                    (
                        $discount > 0
                        ||
                        (
                            (float)
                            $coupon->discount === 0.0
                        )
                    )
                ),
        ];
    }

    /**
     * Product brand coupon.
     */
    protected function applyBrandCoupon(
        $coupon,
        array $excludeSkuList
    ): array {
        $brandIds =
            $this->csvToArray(
                $coupon->sku
            );

        $activeBrandIds =
            Manufacture::where(
                'status',
                '1'
            )
            ->whereIn(
                'imanufactureid',
                $brandIds
            )
            ->pluck(
                'imanufactureid'
            )
            ->toArray();

        if (
            empty($activeBrandIds)
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        $productIds =
            collect($cart)
                ->pluck('ProductID')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

        if (
            empty($productIds)
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        /*
         * Existing Products brand query.
         */
        $brandProductIds =
            Products::whereIn(
                'imanufactureid',
                $activeBrandIds
            )
            ->whereIn(
                'products_id',
                $productIds
            )
            ->distinct()
            ->pluck(
                'products_id'
            )
            ->toArray();

        $matched = false;
        $totalAmount = 0.0;

        foreach (
            $cart as $index => $item
        ) {
            if (
                !in_array(
                    $item['ProductID'] ?? null,
                    $brandProductIds
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $item['SKU'] ?? '',
                    $excludeSkuList,
                    true
                )
            ) {
                continue;
            }

            if (
                !$this->isCouponEligibleDeal(
                    $item,
                    $coupon
                )
            ) {
                continue;
            }

            $matched = true;

            $totalAmount +=
                (float)
                (
                    $item['TotPrice']
                    ?? 0
                );

            if (
                (string)
                $coupon->type === '1'
            ) {
                $itemDiscount =
                    (
                        (float)
                        (
                            $item['Price']
                            ?? 0
                        )
                        *
                        (int)
                        (
                            $item['Qty']
                            ?? 0
                        )
                    )
                    *
                    (
                        (float)
                        $coupon->discount
                        / 100
                    );

                $this->setItemWiseCouponDiscount(
                    $index,
                    $coupon,
                    $itemDiscount
                );
            }
        }

        if (
            !$matched
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        if (
            $coupon->minimum_order_amount > 0
            &&
            $totalAmount <
                $coupon->minimum_order_amount
        ) {
            return [
                'discount' => 0,
                'matched' => false,
            ];
        }

        if (
            (string)
            $coupon->type === '1'
        ) {
            $discount =
                $totalAmount
                *
                (
                    (float)
                    $coupon->discount
                    / 100
                );
        } else {
            $discount =
                (float)
                $coupon->discount;
        }

        return [
            'discount' =>
                $discount,
            'matched' => true,
        ];
    }

    /**
     * Serialized / case 7 coupon.
     */
    protected function applySerializedSkuCoupon(
        $coupon,
        array $excludeSkuList
    ): array {
        $skuList =
            $this->csvToArray(
                $coupon->sku
            );

        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        $matched = false;
        $totalAmount = 0.0;

        foreach (
            $cart as $index => $item
        ) {
            if (
                !in_array(
                    $item['SKU'] ?? '',
                    $skuList,
                    true
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $item['SKU'] ?? '',
                    $excludeSkuList,
                    true
                )
            ) {
                continue;
            }

            if (
                !$this->isCouponEligibleDeal(
                    $item,
                    $coupon
                )
            ) {
                continue;
            }

            $matched = true;

            $totalAmount +=
                (float)
                (
                    $item['TotPrice']
                    ?? 0
                );

            if (
                (string)
                $coupon->type === '1'
            ) {
                $itemDiscount =
                    (
                        (float)
                        (
                            $item['Price']
                            ?? 0
                        )
                        *
                        (int)
                        (
                            $item['Qty']
                            ?? 0
                        )
                    )
                    *
                    (
                        (float)
                        $coupon->discount
                        / 100
                    );

                $this->setItemWiseCouponDiscount(
                    $index,
                    $coupon,
                    $itemDiscount
                );
            }
        }

        if (
            !$matched
        ) {
            return [
                'discount' => 0,
                'free_gift_sku' => null,
            ];
        }

        if (
            $coupon->minimum_order_amount > 0
            &&
            $totalAmount <
                $coupon->minimum_order_amount
        ) {
            return [
                'discount' => 0,
                'free_gift_sku' => null,
            ];
        }

        if (
            (string)
            $coupon->type === '1'
        ) {
            $discount =
                $totalAmount
                *
                (
                    (float)
                    $coupon->discount
                    / 100
                );
        } else {
            $discount =
                (float)
                $coupon->discount;
        }

        return [
            'discount' =>
                $discount,

            'free_gift_sku' =>
                !empty(
                    $coupon->freegift_product_sku
                )
                    ? $coupon->freegift_product_sku
                    : null,
        ];
    }

    /**
     * Order amount item-wise distribution.
     */
    protected function applyOrderAmountItemWise(
        $coupon,
        float $couponDiscount,
        float $totalAmount,
        array $excludeSkuList
    ): void {
        if (
            $totalAmount <= 0
        ) {
            return;
        }

        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        /*
         * Percentage coupon:
         * direct item amount * percentage.
         */
        if (
            (string)
            $coupon->type === '1'
        ) {
            foreach (
                $cart as $index => $item
            ) {
                if (
                    in_array(
                        $item['SKU'] ?? '',
                        $excludeSkuList,
                        true
                    )
                ) {
                    continue;
                }

                if (
                    !$this->isCouponEligibleDeal(
                        $item,
                        $coupon
                    )
                ) {
                    continue;
                }

                $itemDiscount =
                    (
                        (float)
                        (
                            $item['Price']
                            ?? 0
                        )
                        *
                        (int)
                        (
                            $item['Qty']
                            ?? 0
                        )
                    )
                    *
                    (
                        (float)
                        $coupon->discount
                        / 100
                    );

                $this->setItemWiseCouponDiscount(
                    $index,
                    $coupon,
                    $itemDiscount
                );
            }

            return;
        }

        /*
         * Fixed coupon:
         * distribute proportionally.
         */
        foreach (
            $cart as $index => $item
        ) {
            if (
                in_array(
                    $item['SKU'] ?? '',
                    $excludeSkuList,
                    true
                )
            ) {
                continue;
            }

            if (
                !$this->isCouponEligibleDeal(
                    $item,
                    $coupon
                )
            ) {
                continue;
            }

            $percentage =
                (
                    $couponDiscount * 100
                )
                /
                $totalAmount;

            $itemDiscount =
                (
                    (float)
                    (
                        $item['TotPrice']
                        ?? 0
                    )
                    *
                    $percentage
                )
                / 100;

            $this->setItemWiseCouponDiscount(
                $index,
                $coupon,
                $itemDiscount
            );
        }
    }

    /**
     * Save item-wise coupon/reward discount.
     */
    protected function setItemWiseCouponDiscount(
        int $index,
        $coupon,
        float $amount
    ): void {
        if (
            $amount <= 0
        ) {
            return;
        }

        if (
            $coupon->source === 'Yotpo'
        ) {
            Session::put(
                'ShoppingCart.Cart.'
                . $index
                . '.RewardItemWiseDiscout',
                $amount
            );
        } else {
            Session::put(
                'ShoppingCart.Cart.'
                . $index
                . '.CouponDisItemWiseDiscout',
                $amount
            );
        }
    }

    /**
     * Existing deal-product eligibility.
     */
    protected function isCouponEligibleDeal(
        array $item,
        $coupon
    ): bool {
        if (
            ($item['IsDealProducts'] ?? '')
            !== 'Yes'
        ) {
            return true;
        }

        return
            ($item['DealDiscountFlag'] ?? '')
                === 'Yes'
            ||
            ($coupon->dealdiscount_flag ?? '')
                === 'Yes';
    }

    /**
     * One-time / once-per-customer validation.
     */
    protected function resolveCouponCase(
        $coupon,
        int $customerId
    ): string {
        $switchCase =
            (string)
            $coupon->orders;

        if (
            (string)
            $coupon->is_once === '1'
        ) {
            if (
                $this->couponAlreadyUsed(
                    $coupon,
                    $customerId
                )
            ) {
                return '';
            }

            return $switchCase;
        }

        if (
            (string)
            $coupon->is_once === '2'
        ) {
            if (
                $customerId > 0
                &&
                $this->couponAlreadyUsed(
                    $coupon,
                    $customerId
                )
            ) {
                return '';
            }

            /*
             * Existing guest email check.
             */
            if (
                Session::get('etype') === 'G'
            ) {
                $billing =
                    Session::get(
                        'ShoppingCart.BillingAddress',
                        []
                    );

                $email =
                    $billing['email']
                    ?? '';

                if (
                    $email !== ''
                    &&
                    $this->couponUsedByEmail(
                        $coupon,
                        $email
                    )
                ) {
                    return '';
                }
            }
        }

        return $switchCase;
    }

    /**
     * Existing order usage check.
     *
     * Yotpo uses Second_coupon_id.
     * Normal coupon uses coupon_id.
     */
    protected function couponAlreadyUsed(
        $coupon,
        int $customerId
    ): bool {
        if (
            $customerId <= 0
        ) {
            return false;
        }

        $query =
            Order::where(
                'customer_id',
                $customerId
            )
            ->where(
                'status',
                '!=',
                'Declined'
            )
            ->where(
                'pay_status',
                'Paid'
            );

        if (
            $coupon->source === 'Yotpo'
        ) {
            $query->where(
                'Second_coupon_id',
                trim(
                    $coupon->coupon_number
                )
            );
        } else {
            $query->where(
                'coupon_id',
                (int)
                $coupon->coupon_id
            );
        }

        return $query->exists();
    }

    /**
     * Guest email usage check.
     */
    protected function couponUsedByEmail(
        $coupon,
        string $email
    ): bool {
        $query =
            Order::where(
                'bill_email',
                $email
            );

        if (
            $coupon->source === 'Yotpo'
        ) {
            $query->where(
                'Second_coupon_id',
                trim(
                    $coupon->coupon_number
                )
            );
        } else {
            $query->where(
                'coupon_id',
                (int)
                $coupon->coupon_id
            );
        }

        return $query->exists();
    }

    /**
     * Save PromoCoupon session.
     */
    protected function saveCouponSession(
        $coupon,
        string $couponCode,
        float $couponDiscount
    ): void {
        $isReward =
            (string) $coupon->source === 'Yotpo';

        $discount =
            NumberFormat($couponDiscount);

        /*
         * ---------------------------------------------------------
         * Yotpo Reward
         * ---------------------------------------------------------
         *
         * pu_coupon is shared by Coupon and Reward.
         * source = Yotpo means this record is a Reward.
         *
         * Keep Reward session values separate from PromoCoupon
         * values so DiscountService can report the Reward correctly.
         */
        if ($isReward) {
            Session::put(
                'ShoppingCart.YotpoRewardCode',
                $couponCode
            );

            Session::put(
                'ShoppingCart.YotpoRewardDiscount',
                $discount
            );

            Session::put(
                'ShoppingCart.YotpoRewardCouponID',
                $coupon->coupon_id
            );

            Session::put(
                'ShoppingCart.YotpoReward_Detail',
                $coupon->toArray()
            );

            Session::put(
                'Niche_Fragrances_Membership',
                'Yes'
            );

            /*
             * Do NOT save the Yotpo Reward as a normal PromoCoupon.
             */
            return;
        }

        /*
         * ---------------------------------------------------------
         * Normal Coupon
         * ---------------------------------------------------------
         */
        Session::put(
            'ShoppingCart.PromoCoupon.CouponCode',
            $couponCode
        );

        Session::put(
            'ShoppingCart.PromoCoupon.CouponDiscount',
            $discount
        );

        Session::put(
            'ShoppingCart.PromoCoupon.CouponID',
            $coupon->coupon_id
        );

        Session::put(
            'ShoppingCart.PromoCoupon.Coupon_Detail_CJ',
            $coupon->toArray()
        );

        Session::put(
            'ShoppingCart.PromoCoupon.FirstCouponDiscount',
            $discount
        );
    }

    /**
     * Insert free gift.
     *
     * The actual existing helper is outside this service.
     * Keep this integration point so the existing free-gift
     * service can be called later.
     */
    protected function insertFreeGift(
        string $value
    ): void {
        /*
         * FreeGiftService owns coupon free-gift insertion.
         *
         * CouponService only decides WHEN a gift is applicable;
         * it does not duplicate product/stock/cart construction.
         */
        $result =
            $this->freeGiftService
                ->insertWithCoupon($value);

        if (
            !($result['success'] ?? false)
            &&
            !empty($result['message'])
        ) {
            Session::flash(
                'OutOfStockBundle',
                $result['message']
            );
        }
    }

    /**
     * Gift certificate item check.
     */
    protected function isGiftCertificateItem(
        array $item
    ): bool {
        return
            ($item['IsGiftCertificateItem'] ?? 'No')
            === 'Yes';
    }

    /**
     * Clear coupon related sessions.
     */
    /**
     * Remove the currently applied normal coupon.
     *
     * Calculation/business rules remain in the existing coupon flow.
     * This method only clears coupon-specific state and item-wise values.
     */
    public function removeCoupon(): void
    {
        $cart = Session::get('ShoppingCart.Cart', []);

        foreach (array_keys($cart) as $index) {
            Session::put(
                'ShoppingCart.Cart.' . $index . '.CouponDisItemWiseDiscout',
                0
            );
        }

        Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount', 0);
        Session::put('ShoppingCart.PromoCoupon.SecondCouponDiscount', 0);
        Session::put('ShoppingCart.PromoCoupon.CouponCode', '');
        Session::put('ShoppingCart.PromoCoupon.CouponDiscount', 0);
        Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag', '');
        Session::put('ShoppingCart.PromoCoupon.FreeShipping', '');
        Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', '');
        Session::put('ShoppingCart.PromoCoupon.Coupon_Detail_CJ', []);
        Session::put('ShoppingCart.PromoCoupon.CouponID', '');

        /*
         * Preserve legacy coupon-free-gift removal.
         * Only items explicitly marked as FreeGiftCoupon are removed.
         */
        $cart = Session::get('ShoppingCart.Cart', []);

        foreach ($cart as $index => $item) {
            if (
                isset($item['FreeGiftCoupon'])
                && $item['FreeGiftCoupon'] === 'Yes'
            ) {
                unset($cart[$index]);
            }
        }

        Session::put(
            'ShoppingCart.Cart',
            array_values($cart)
        );

        return;
    }

    /**
     * Remove the currently applied Yotpo reward.
     *
     * The controller keeps responsibility for the existing payment-intent
     * / Yotpo coupon deactivation flow. This method clears the Reward state.
     */
    public function removeYotpoReward(): void
    {
        /*
         * Preserve legacy Yotpo coupon deactivation.
         *
         * pu_coupon is shared with normal coupons, but source=Yotpo
         * identifies the reward. The generated reward is deactivated
         * for the current customer's email and today's start_date,
         * matching the existing flow.
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

            $email = trim(
                (string) ($normalUser->email ?? '')
            );

            if ($email !== '') {
                Coupon::where(
                    'coupon_number',
                    $rewardCode
                )
                    ->where(
                        'source',
                        'Yotpo'
                    )
                    ->where(
                        'customer_email',
                        $email
                    )
                    ->where(
                        'start_date',
                        DB::raw('curdate()')
                    )
                    ->update([
                        'status' => '0',
                    ]);
            }
        }

        $cart = Session::get('ShoppingCart.Cart', []);

        foreach (array_keys($cart) as $index) {
            Session::put(
                'ShoppingCart.Cart.' . $index . '.RewardItemWiseDiscout',
                0
            );
        }

        Session::put('ShoppingCart.YotpoRewardRedeemDiscount', '');
        Session::put('ShoppingCart.YotpoRewardDiscount', '');
        Session::put('ShoppingCart.YotpoRewardCode', '');
        Session::put('ShoppingCart.YotpoRewardCouponID', '');
        Session::put('ShoppingCart.YotpoReward_Detail', []);

        return;
    }

    protected function clearCouponSessions(): void
    {
        Session::forget(
            'ShoppingCart.PromoCoupon'
        );

        Session::forget(
            'Niche_Fragrances_Membership'
        );

        Session::forget(
            'ShoppingCart.YotpoRewardCode'
        );

        Session::forget(
            'ShoppingCart.YotpoRewardDiscount'
        );
    }

    /**
     * Invalid coupon.
     */
    protected function invalidCoupon(
        string $message,
        array $log = []
    ): array {
        $this->clearCouponSessions();

        $log['message'] =
            $message;

        addLog(
            'ApplyDiscountInvalidCoupon',
            $log
        );

        addLog(
            'ApplyCouponDiscount',
            [
                'error' => 1,
                'message' => $message,
            ]
        );

        return [
            'error' => 1,
            'message' => $message,
        ];
    }

    /**
     * Coupon does not apply to current cart.
     */
    protected function invalidCartCoupon(
        array $log = []
    ): array {
        $message =
            'Coupon code does not apply to the item you have in your bag.';

        $this->clearCouponSessions();

        $log['message'] =
            $message;

        addLog(
            'ApplyCouponDiscount',
            [
                'error' => 1,
                'message' => $message,
            ]
        );

        return [
            'error' => 1,
            'message' => $message,
        ];
    }

    /**
     * CSV helper.
     */
    protected function csvToArray(
        ?string $value
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
                array_unique(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            $value
                        )
                    )
                ),
                'strlen'
            )
        );
    }
}
