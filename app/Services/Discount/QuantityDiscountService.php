<?php

namespace App\Services\Discount;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Manufacture;
use App\Models\Products;
use App\Models\ProductsCategory;
use App\Models\QuantityDiscount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use App\Constants\CheckoutConstants;

class QuantityDiscountService
{
    

    /**
     * Apply Quantity Discount.
     *
     * Migration of CartTrait::ApplyQuantityDiscount()
     */
    public function apply(): void
    {
        $quantityDiscount = 0;
        $newSubTotal = 0;
        $quantityDiscountItemWise = 0;

        $log = [
            'QuantityDiscount' =>
                $quantityDiscount,

            'NewSubTotal' =>
                $newSubTotal,

            'QuantityDiscountItemWise' =>
                $quantityDiscountItemWise,

            'pocketPerfumeCategory' =>
                json_encode(
                   CheckoutConstants::POCKET_PERFUME_CATEGORIES
                ),
        ];

        addLog(
            'ApplyQuantityDiscountStart',
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
                    'ShoppingCart.Cart'
                )
            ) <= 0
        ) {
            return;
        }

        /*
         * Existing:
         *
         * $this->getAllDiscountBlank("Quantity");
         *
         * Clear old quantity-discount values.
         */
        $this->clearQuantityDiscount();

        /*
         * ---------------------------------------------------------
         * Wholesaler restriction.
         * ---------------------------------------------------------
         */
        $normalUser = Auth::user();

        if (
            Auth::guard('store')->check()
        ) {
            $normalUser =
                Auth::guard('web')->user();
        }

        if (
            $normalUser &&
            Session::get('eusertype') ===
                'Wholesaler'
        ) {
            Session::put(
                'ShoppingCart.QuantityDiscount',
                0
            );

            return;
        }

        /*
         * Existing phone-order wholesaler restriction.
         */
        if (
            Session::has('isPhoneOrder') &&
            Session::has('eusertype') &&
            Session::get('eusertype') ===
                'Wholesaler'
        ) {
            Session::put(
                'ShoppingCart.QuantityDiscount',
                0
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * Subtotal
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.SubTotal'
            )
        ) {
            $newSubTotal =
                NumberFormat(
                    Session::get(
                        'ShoppingCart.SubTotal'
                    )
                );
        }

        /*
         * ---------------------------------------------------------
         * Gift Certificate
         * ---------------------------------------------------------
         */
        $giftCertiTotal = 0;
        $giftCertiCount = 0;

        if (
            Session::has(
                'ShoppingCart.GiftCertiTotal'
            )
        ) {
            $giftCertiTotal =
                NumberFormat(
                    Session::get(
                        'ShoppingCart.GiftCertiTotal'
                    )
                );

            $giftCertiCount =
                NumberFormat(
                    Session::get(
                        'ShoppingCart.GiftCertiCount'
                    )
                );
        }

        $subTotal =
            $newSubTotal
            - $giftCertiTotal;

        $log['GiftCertiTotal'] =
            $giftCertiTotal;

        $cart =
            Session::get(
                'ShoppingCart.Cart'
            );

        /*
         * Gift certificate items are excluded
         * from quantity count.
         */
        $totalItem =
            Session::get(
                'ShoppingCart.TotalItemInCart'
            )
            - $giftCertiCount;

        $quantityDiscountFlag = '';

        /*
         * ---------------------------------------------------------
         * No eligible subtotal / quantity.
         * ---------------------------------------------------------
         */
        if (
            $subTotal <= 0 ||
            $totalItem <= 0
        ) {
            Session::put(
                'ShoppingCart.QuantityDiscount',
                0
            );

            Session::put(
                'ShoppingCart.QuantityDiscountFlag',
                ''
            );

            $log['QuantityDiscount'] = '0';

            $log['QuantityDiscountFlag'] =
                '';

            addLog(
                'ApplyQuantityDiscount',
                $log
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * Coupon Quantity Discount flag.
         * ---------------------------------------------------------
         */
        $couponCode =
            $this->getCouponCode();

        if ($couponCode !== '') {

            $today =
                date('Y-m-d');

            $coupon =
                Coupon::select(
                    'quantitydiscount_flag'
                )
                ->where(
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
                    $today
                )
                ->where(
                    'end_date',
                    '>=',
                    $today
                )
                ->first();

            if ($coupon) {

                $log['coupon_res'] =
                    json_encode($coupon);

                if (
                    $coupon->quantitydiscount_flag
                    === 'No'
                ) {
                    Session::put(
                        'ShoppingCart.QuantityDiscount',
                        0
                    );

                    Session::put(
                        'ShoppingCart.QuantityDiscountFlag',
                        ''
                    );

                    $log[
                        'QuantityDiscount_1'
                    ] = '0';

                    $log[
                        'QuantityDiscountFlag_1'
                    ] = '';

                    addLog(
                        'ApplyQuantityDiscount',
                        $log
                    );

                    return;
                }
            }
        }

        /*
         * ---------------------------------------------------------
         * Load active Quantity Discount rules.
         * ---------------------------------------------------------
         */
        $today =
            date('Y-m-d');

        $quantityRules =
            QuantityDiscount::where(
                'status',
                '1'
            )
            ->where(
                'start_date',
                '<=',
                $today
            )
            ->where(
                'end_date',
                '>=',
                $today
            )
            ->where(
                'quantity',
                '<=',
                $totalItem
            )
            ->orderByDesc(
                'quantity_discount_id'
            )
            ->get();

        $totalQuantityDiscountRecords =
            $quantityRules->count();

        $cartItems =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        $totalItems =
            count($cartItems);

        $totalExcludePrice = 0;

        $log['QtyRS'] =
            $quantityRules->toJson();

        /*
         * ---------------------------------------------------------
         * No active rules.
         * ---------------------------------------------------------
         */
        if (
            $totalQuantityDiscountRecords <= 0
        ) {
            Session::put(
                'ShoppingCart.QuantityDiscount',
                0
            );

            Session::put(
                'ShoppingCart.QuantityDiscountFlag',
                ''
            );

            $log['QuantityDiscount'] = 0;
            $log['QuantityDiscountFlag'] = '';

            addLog(
                'ApplyQuantityDiscount',
                $log
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * Existing active category IDs cache.
         * ---------------------------------------------------------
         */
        $allActiveCategoryIds =
            Cache::rememberForever(
                'active_category_ids',
                function () {
                    return Category::where(
                        'status',
                        '1'
                    )
                    ->pluck(
                        'category_id'
                    )
                    ->map(
                        fn ($id) =>
                            (string) $id
                    )
                    ->toArray();
                }
            );

        /*
         * ---------------------------------------------------------
         * Existing active brand IDs cache.
         * ---------------------------------------------------------
         */
        $allActiveBrandIds =
            Cache::rememberForever(
                'active_brand_ids',
                function () {
                    return Manufacture::where(
                        'status',
                        '1'
                    )
                    ->pluck(
                        'imanufactureid'
                    )
                    ->map(
                        fn ($id) =>
                            (string) $id
                    )
                    ->toArray();
                }
            );

        /*
         * ---------------------------------------------------------
         * Process quantity rules.
         * ---------------------------------------------------------
         */
        $skuRemoveArr = '';

        for (
            $i = 0;
            $i < $totalQuantityDiscountRecords;
            $i++
        ) {
            $rule =
                $quantityRules[$i];

            $quantityDiscountFlag =
                $rule->discount_coupon_flag;

            $excludeSkuList =
                $this->getExcludeSkuList(
                    $rule->exclude_sku
                );

            $totalExcludePrice = 0;

            /*
             * Existing exclude SKU total-price calculation.
             */
            if (
                $totalItems > 0 &&
                trim(
                    (string)
                    $rule->exclude_sku
                ) !== ''
            ) {
                foreach (
                    $cartItems as $cartItem
                ) {
                    if (
                        in_array(
                            $cartItem['SKU'] ?? '',
                            $excludeSkuList,
                            true
                        )
                    ) {
                        $totalExcludePrice +=
                            (float)
                            (
                                $cartItem[
                                    'TotPrice'
                                ] ?? 0
                            );
                    }
                }

                $log[
                    'ExcludeSKUListArr'
                ] =
                    json_encode(
                        $excludeSkuList
                    );

                $log[
                    'TotalExcludePrice_1'
                ] =
                    json_encode(
                        $totalExcludePrice
                    );
            }

            /*
             * -----------------------------------------------------
             * orders == 2
             *
             * Multiple Brand IDs
             * -----------------------------------------------------
             */
            if (
                (string)
                $rule->orders === '2'
            ) {
                $result =
                    $this->applyBrandRule(
                        $rule,
                        $cartItems,
                        $allActiveBrandIds,
                        $excludeSkuList,
                        $skuRemoveArr
                    );

                if (
                    $result['matched']
                ) {
                    $quantityDiscountFlag =
                        $rule->discount_coupon_flag;

                    $quantityDiscount =
                        $this->selectHigherDiscount(
                            $quantityDiscount,
                            $result['discount']
                        );

                    $skuRemoveArr =
                        $result[
                            'skuRemoveArr'
                        ];
                }

                continue;
            }

            /*
             * -----------------------------------------------------
             * orders == 1
             *
             * Category IDs
             * -----------------------------------------------------
             */
            if (
                (string)
                $rule->orders === '1'
            ) {
                $result =
                    $this->applyCategoryRule(
                        $rule,
                        $cartItems,
                        $allActiveCategoryIds,
                        $excludeSkuList,
                        $skuRemoveArr
                    );

                if (
                    $result['matched']
                ) {
                    $quantityDiscountFlag =
                        $rule->discount_coupon_flag;

                    $quantityDiscount =
                        $this->selectHigherDiscount(
                            $quantityDiscount,
                            $result['discount']
                        );

                    $skuRemoveArr =
                        $result[
                            'skuRemoveArr'
                        ];
                }

                continue;
            }

            /*
             * -----------------------------------------------------
             * orders == ''
             *
             * Generic rule:
             * when SKU is blank, apply the rule to all eligible
             * cart items. This preserves the Old Checkout behavior
             * for generic Quantity Discount rules.
             *
             * Otherwise process the rule as a normal SKU rule.
             * -----------------------------------------------------
             */
            if (
                trim(
                    (string)
                    $rule->sku
                ) === ''
            ) {
                $result =
                    $this->applyGenericRule(
                        $rule,
                        $cartItems,
                        $excludeSkuList
                    );

                if (
                    $result['matched']
                ) {
                    $quantityDiscountFlag =
                        $rule->discount_coupon_flag;

                    $quantityDiscount =
                        $this->selectHigherDiscount(
                            $quantityDiscount,
                            $result['discount']
                        );

                    /*
                     * Generic rule does not populate SKU remove
                     * list because it applies to the eligible cart
                     * as a whole.
                     */
                    break;
                }

                continue;
            }

            $result =
                $this->applySkuRule(
                    $rule,
                    $cartItems,
                    $excludeSkuList,
                    $skuRemoveArr,
                    $subTotal
                );

            if (
                $result['matched']
            ) {
                $quantityDiscountFlag =
                    $rule->discount_coupon_flag;

                $quantityDiscount =
                    $this->selectHigherDiscount(
                        $quantityDiscount,
                        $result['discount']
                    );

                $skuRemoveArr =
                    $result[
                        'skuRemoveArr'
                    ];

                /*
                 * Existing normal-rule behavior:
                 * once matching rule is found, stop.
                 */
                break;
            }
        }

        /*
         * ---------------------------------------------------------
         * Final session values.
         * ---------------------------------------------------------
         */
        Session::put(
            'ShoppingCart.QuantityDiscount',
            NumberFormat(
                $quantityDiscount
            )
        );

        Session::put(
            'ShoppingCart.QuantityDiscountFlag',
            $quantityDiscountFlag
        );

        $log['QuantityDiscount'] =
            $quantityDiscount;

        $log['QuantityDiscountFlag'] =
            $quantityDiscountFlag;

        addLog(
            'ApplyQuantityDiscount',
            $log
        );

        return;
    }

    /**
     * Generic quantity-discount rule.
     *
     * Old Checkout behavior for a Quantity Discount rule with
     * orders = '' and a blank SKU: apply the rule to all eligible
     * cart items.
     *
     * Excluded:
     * - Deal products
     * - Free gifts
     * - Free samples
     * - Explicitly excluded SKUs
     * - Pocket perfume when configured
     */
    protected function applyGenericRule(
        $rule,
        array $cart,
        array $excludeSkuList
    ): array {
        $matched = false;
        $totalQty = 0;
        $totalAmount = 0;

        foreach (
            $cart as $index => $item
        ) {
            if (
                ($item['IsDealProducts'] ?? '') ===
                'Yes'
            ) {
                continue;
            }

            if (
                ($item['IS_Free_Gift'] ?? '') ===
                'Yes'
            ) {
                continue;
            }

            if (
                ($item['Is_Free_Sample'] ?? '') ===
                'Yes'
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
                $rule->exclude_pocketperfume ===
                    'Yes' &&
                $this->isPocketPerfume(
                    $item['CategoryID'] ?? 0
                )
            ) {
                continue;
            }

            $matched = true;

            $qty =
                (int)
                (
                    $item['Qty'] ?? 0
                );

            $lineAmount =
                (float)
                (
                    $item['TotPrice'] ?? 0
                );

            $totalQty += $qty;
            $totalAmount += $lineAmount;
        }

        if (
            !$matched ||
            $totalQty <
                (int)
                $rule->quantity
        ) {
            return [
                'matched' => false,
                'discount' => 0,
                'skuRemoveArr' => '',
            ];
        }

        /*
         * Percentage discount is calculated only from the
         * eligible generic-rule amount.
         */
        if (
            (int)
            $rule->type === 1
        ) {
            $discount =
                $totalAmount *
                (
                    (float)
                    $rule->quantity_discount_amount
                    / 100
                );

            /*
             * Preserve item-wise discount used by checkout.
             */
            foreach (
                $cart as $index => $item
            ) {
                if (
                    ($item['IsDealProducts'] ?? '') ===
                    'Yes'
                ) {
                    continue;
                }

                if (
                    ($item['IS_Free_Gift'] ?? '') ===
                    'Yes'
                ) {
                    continue;
                }

                if (
                    ($item['Is_Free_Sample'] ?? '') ===
                    'Yes'
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
                    $rule->exclude_pocketperfume ===
                        'Yes' &&
                    $this->isPocketPerfume(
                        $item['CategoryID'] ?? 0
                    )
                ) {
                    continue;
                }

                $itemDiscount =
                    (
                        (float)
                        (
                            $item['Price'] ?? 0
                        )
                        *
                        (
                            (int)
                            (
                                $item['Qty'] ?? 0
                            )
                        )
                    )
                    *
                    (
                        (float)
                        $rule->quantity_discount_amount
                        / 100
                    );

                Session::put(
                    'ShoppingCart.Cart.'
                    . $index
                    . '.QuantityItemWiseDiscout',
                    $itemDiscount
                );
            }
        } else {
            /*
             * Fixed generic discount.
             */
            $discount =
                (float)
                $rule->quantity_discount_amount;
        }

        return [
            'matched' => true,
            'discount' =>
                NumberFormat($discount),
            'skuRemoveArr' => '',
        ];
    }

    /**
     * Normal SKU quantity-discount rule.
     *
     * Existing logic:
     * - pocket perfume exclusion
     * - free gift exclusion
     * - free sample exclusion
     * - deal product exclusion
     * - excluded SKU
     * - SKURemoveArrNew
     * - quantity threshold
     */
    protected function applySkuRule(
        $rule,
        array $cart,
        array $excludeSkuList,
        string $skuRemoveArr,
        float $subTotal
    ): array {
        $matched = false;

        $totalQty = 0;
        $matchedItemTotal = 0;
        $totalAmount = 0;

        $skuList =
            $this->csvToArray(
                $rule->sku
            );

        $skuRemoveArrNew =
            $this->csvToArray(
                $skuRemoveArr
            );

        foreach (
            $cart as $index => $item
        ) {
            $sku =
                $item['SKU'] ?? '';

            if (
                !in_array(
                    $sku,
                    $skuList,
                    true
                )
            ) {
                continue;
            }

            if (
                $this->isExcludedItem(
                    $item,
                    $rule,
                    $excludeSkuList,
                    $skuRemoveArrNew
                )
            ) {
                continue;
            }

            $qty =
                (int)
                (
                    $item['Qty'] ?? 0
                );

            $lineTotal =
                (float)
                (
                    $item['TotPrice'] ?? 0
                );

            $totalQty += $qty;
            $totalAmount += $lineTotal;

            /*
             * Existing matched item total uses:
             * Price * Qty
             */
            $matchedItemTotal +=
                (
                    (float)
                    (
                        $item['Price'] ?? 0
                    )
                    *
                    $qty
                );

            if (
                $qty >=
                    (int)
                    $rule->quantity
                ||
                $totalQty >=
                    (int)
                    $rule->quantity
            ) {
                $matched = true;

                $skuRemoveArr .=
                    $sku . ',';
            }
        }

        if (
            !$matched ||
            $totalQty <
                (int)
                $rule->quantity
        ) {
            return [
                'matched' => false,
                'discount' => 0,
                'skuRemoveArr' =>
                    $skuRemoveArr,
            ];
        }

        /*
         * Percentage.
         */
        if (
            (int)
            $rule->type === 1
        ) {
            $discount =
                $matchedItemTotal
                *
                (
                    (float)
                    $rule->quantity_discount_amount
                    / 100
                );
        }

        /*
         * Fixed amount.
         */
        else {
            $discount =
                (float)
                $rule->quantity_discount_amount;
        }

        /*
         * Existing item-wise percentage discount.
         */
        if (
            (int)
            $rule->type === 1
        ) {
            foreach (
                $cart as $index => $item
            ) {
                $sku =
                    $item['SKU'] ?? '';

                if (
                    !in_array(
                        $sku,
                        $skuList,
                        true
                    )
                ) {
                    continue;
                }

                if (
                    $this->isExcludedItem(
                        $item,
                        $rule,
                        $excludeSkuList,
                        $this->csvToArray(
                            $skuRemoveArr
                        )
                    )
                ) {
                    continue;
                }

                if (
                    $totalQty >=
                    (int)
                    $rule->quantity
                ) {
                    $itemDiscount =
                        (
                            (float)
                            (
                                $item['Price']
                                ?? 0
                            )
                            *
                            (
                                (int)
                                (
                                    $item['Qty']
                                    ?? 0
                                )
                            )
                        )
                        *
                        (
                            (float)
                            $rule->quantity_discount_amount
                            / 100
                        );

                    Session::put(
                        'ShoppingCart.Cart.'
                        . $index
                        . '.QuantityItemWiseDiscout',
                        $itemDiscount
                    );
                }
            }
        }

        return [
            'matched' => true,

            'discount' =>
                NumberFormat(
                    $discount
                ),

            'skuRemoveArr' =>
                $skuRemoveArr,
        ];
    }

    /**
     * Category quantity-discount rule.
     *
     * Existing source uses active category IDs,
     * category product IDs and category matching.
     */
    protected function applyCategoryRule(
        $rule,
        array $cart,
        array $allActiveCategoryIds,
        array $excludeSkuList,
        string $skuRemoveArr
    ): array {
        $quantityDiscount1 = 0;

        $totalQty = 0;
        $totalPrice = 0;
        $totalAmount = 0;

        $foundCategory = false;
        $totalPercentage = false;

        $categoryIds =
            $this->csvToArray(
                $rule->sku
            );

        $activeCategoryIds =
            array_values(
                array_intersect(
                    $categoryIds,
                    $allActiveCategoryIds
                )
            );

        if (
            empty($activeCategoryIds)
        ) {
            return [
                'matched' => false,
                'discount' => 0,
                'skuRemoveArr' =>
                    $skuRemoveArr,
            ];
        }

        /*
         * Existing source resolves products belonging
         * to the active categories.
         */
        $productIds =
            $this->getCategoryProductIds(
                $activeCategoryIds,
                $cart
            );

        $skuRemoveArrNew =
            $this->csvToArray(
                $skuRemoveArr
            );

        foreach (
            $cart as $index => $item
        ) {
            if (
                !in_array(
                    $item['ProductID'] ?? null,
                    $productIds
                )
            ) {
                continue;
            }

            if (
                $this->isExcludedItem(
                    $item,
                    $rule,
                    $excludeSkuList,
                    $skuRemoveArrNew
                )
            ) {
                continue;
            }

            $qty =
                (int)
                (
                    $item['Qty'] ?? 0
                );

            $totalQty += $qty;

            $totalPrice +=
                (
                    (float)
                    (
                        $item['Price'] ?? 0
                    )
                    *
                    $qty
                );

            $totalAmount +=
                (float)
                (
                    $item['TotPrice'] ?? 0
                );

            if (
                $qty >=
                    (int)
                    $rule->quantity
                ||
                $totalQty >=
                    (int)
                    $rule->quantity
            ) {
                $foundCategory = true;

                if (
                    (int)
                    $rule->type === 1
                ) {
                    $totalPercentage = true;
                }

                $skuRemoveArr .=
                    (
                        $item['SKU']
                        ?? ''
                    )
                    . ',';
            }
        }

        if (
            !$foundCategory
        ) {
            return [
                'matched' => false,
                'discount' => 0,
                'skuRemoveArr' =>
                    $skuRemoveArr,
            ];
        }

        /*
         * Existing source:
         *
         * if total_percentage == true:
         * QuantityDiscount1 = total_price
         */
        if (
            $totalPercentage
        ) {
            $quantityDiscount1 =
                $totalPrice
                + $quantityDiscount1;
        }

        /*
         * Existing category item-wise discount
         * for percentage rules.
         */
        if (
            $totalQty >=
            (int)
            $rule->quantity
        ) {
            foreach (
                $cart as $index => $item
            ) {
                if (
                    !in_array(
                        $item['ProductID'] ?? null,
                        $productIds
                    )
                ) {
                    continue;
                }

                if (
                    $this->isExcludedItem(
                        $item,
                        $rule,
                        $excludeSkuList,
                        $this->csvToArray(
                            $skuRemoveArr
                        )
                    )
                ) {
                    continue;
                }

                if (
                    (int)
                    $rule->type === 1
                ) {
                    if (
                        $totalAmount > 0
                    ) {
                        $itemWisePercentage =
                            (
                                (float)
                                $rule->quantity_discount_amount
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
                                $itemWisePercentage
                            )
                            / 100;

                        Session::put(
                            'ShoppingCart.Cart.'
                            . $index
                            . '.QuantityItemWiseDiscout',
                            $itemDiscount
                        );
                    }
                }
            }
        }

        if (
            (int)
            $rule->type === 1
        ) {
            $quantityDiscount =
                $quantityDiscount1
                *
                (
                    (float)
                    $rule->quantity_discount_amount
                    / 100
                );
        } else {
            $quantityDiscount =
                (float)
                $rule->quantity_discount_amount;
        }

        return [
            'matched' => true,

            'discount' =>
                NumberFormat(
                    $quantityDiscount
                ),

            'skuRemoveArr' =>
                $skuRemoveArr,
        ];
    }

    /**
     * Brand quantity-discount rule.
     *
     * This contains the exact Products query discussed earlier.
     */
    protected function applyBrandRule(
        $rule,
        array $cart,
        array $allActiveBrandIds,
        array $excludeSkuList,
        string $skuRemoveArr
    ): array {
        $quantityDiscount1 = 0;

        $totalQty = 0;
        $totalPrice = 0;
        $totalAmount = 0;

        $foundBrand = false;
        $totalPercentage = false;

        $brandIds =
            $this->csvToArray(
                trim(
                    (string)
                    $rule->sku
                )
            );

        $activeBrandIds =
            array_values(
                array_intersect(
                    $brandIds,
                    $allActiveBrandIds
                )
            );

        if (
            empty($activeBrandIds)
        ) {
            return [
                'matched' => false,
                'discount' => 0,
                'skuRemoveArr' =>
                    $skuRemoveArr,
            ];
        }

        /*
         * EXACT helper requested by you.
         */
        $brandProductIds =
            $this->getBrandProductIds(
                $activeBrandIds,
                $cart
            );

        $skuRemoveArrNew =
            $this->csvToArray(
                $skuRemoveArr
            );

        foreach (
            $cart as $index => $item
        ) {
            /*
             * Pocket perfume exclusion.
             */
            if (
                trim(
                    (string)
                    $rule->exclude_pocketperfume
                ) === 'Yes' &&
                $this->isPocketPerfume(
                    $item['CategoryID'] ?? 0
                )
            ) {
                continue;
            }

            if (
                !in_array(
                    $item['ProductID'] ?? null,
                    $brandProductIds
                )
            ) {
                continue;
            }

            if (
                $this->isExcludedItem(
                    $item,
                    $rule,
                    $excludeSkuList,
                    $skuRemoveArrNew
                )
            ) {
                continue;
            }

            $qty =
                (int)
                (
                    $item['Qty'] ?? 0
                );

            $totalQty += $qty;

            $totalPrice +=
                (
                    (float)
                    (
                        $item['Price'] ?? 0
                    )
                    *
                    $qty
                );

            $totalAmount +=
                (float)
                (
                    $item['TotPrice'] ?? 0
                );

            if (
                $qty >=
                    (int)
                    $rule->quantity
                ||
                $totalQty >=
                    (int)
                    $rule->quantity
            ) {
                $foundBrand = true;

                if (
                    (int)
                    $rule->type === 1
                ) {
                    $totalPercentage = true;
                }

                $skuRemoveArr .=
                    (
                        $item['SKU']
                        ?? ''
                    )
                    . ',';
            }

            /*
             * Existing percentage item-wise discount.
             */
            if (
                $totalQty >=
                (int)
                $rule->quantity
                &&
                (int)
                $rule->type === 1
            ) {
                if (
                    empty(
                        Session::get(
                            'ShoppingCart.Cart.'
                            . $index
                            . '.QuantityItemWiseDiscout'
                        )
                    )
                ) {
                    $itemDiscount =
                        (
                            (float)
                            (
                                $item['Price']
                                ?? 0
                            )
                            *
                            (
                                (int)
                                (
                                    $item['Qty']
                                    ?? 0
                                )
                            )
                        )
                        *
                        (
                            (float)
                            $rule->quantity_discount_amount
                            / 100
                        );

                    Session::put(
                        'ShoppingCart.Cart.'
                        . $index
                        . '.QuantityItemWiseDiscout',
                        $itemDiscount
                    );
                }
            }
        }

        if (
            $totalPercentage
        ) {
            $quantityDiscount1 =
                $totalPrice
                + $quantityDiscount1;
        }

        if (
            !$foundBrand
        ) {
            return [
                'matched' => false,
                'discount' => 0,
                'skuRemoveArr' =>
                    $skuRemoveArr,
            ];
        }

        /*
         * Existing source compares new discount
         * against already calculated QuantityDiscount.
         *
         * The caller performs the same higher-value selection.
         */
        if (
            (int)
            $rule->type === 1
        ) {
            $quantityDiscount =
                $quantityDiscount1
                *
                (
                    (float)
                    $rule->quantity_discount_amount
                    / 100
                );
        } else {
            $quantityDiscount =
                (float)
                $rule->quantity_discount_amount;
        }

        return [
            'matched' => true,

            'discount' =>
                NumberFormat(
                    $quantityDiscount
                ),

            'skuRemoveArr' =>
                $skuRemoveArr,
        ];
    }

    /**
     * EXACT brand -> product query discussed earlier.
     *
     * Existing source:
     *
     * Products::whereIn('imanufactureid', $arr_active_BrandID)
     *     ->whereIn('products_id', $temp_prod_id)
     *     ->distinct()
     *     ->pluck('products_id')
     *     ->toArray();
     */
    protected function getBrandProductIds(
        array $activeBrandIds,
        array $cart
    ): array {
        $tempProductIds =
            collect($cart)
                ->pluck('ProductID')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

        addLog(
            'ApplyQuantityDiscount',
            [
                'temp_prod_id' =>
                    $tempProductIds,
            ]
        );

        if (
            empty($activeBrandIds) ||
            empty($tempProductIds)
        ) {
            return [];
        }

        $brandProductIds =
            Products::whereIn(
                'imanufactureid',
                $activeBrandIds
            )
            ->whereIn(
                'products_id',
                $tempProductIds
            )
            ->distinct()
            ->pluck(
                'products_id'
            )
            ->toArray();

        addLog(
            'ApplyQuantityDiscount',
            [
                'brand_prod_id' =>
                    $brandProductIds,
            ]
        );

        return $brandProductIds;
    }

    /**
     * Resolve products belonging to selected categories.
     *
     * Same purpose as the existing category product query.
     */
    protected function getCategoryProductIds(
        array $activeCategoryIds,
        array $cart
    ): array {
        $tempProductIds =
            collect($cart)
                ->pluck('ProductID')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

        if (
            empty($activeCategoryIds) ||
            empty($tempProductIds)
        ) {
            return [];
        }

        /*
         * Category membership is stored in the existing
         * pu_products_category mapping table.
         *
         * Do not query category_id from pu_products:
         * that column does not exist on the Products table.
         */
        return ProductsCategory::whereIn(
            'category_id',
            $activeCategoryIds
        )
        ->whereIn(
            'products_id',
            $tempProductIds
        )
        ->distinct()
        ->pluck(
            'products_id'
        )
        ->toArray();
    }

    /**
     * Common item exclusions.
     */
    protected function isExcludedItem(
        array $item,
        $rule,
        array $excludeSkuList,
        array $skuRemoveArrNew
    ): bool {
        /*
         * Deal product.
         */
        if (
            isset(
                $item['IsDealProducts']
            ) &&
            $item['IsDealProducts'] ===
                'Yes'
        ) {
            return true;
        }

        /*
         * Free Gift.
         */
        if (
            isset(
                $item['IS_Free_Gift']
            ) &&
            $item['IS_Free_Gift'] ===
                'Yes'
        ) {
            return true;
        }

        /*
         * Free Sample.
         */
        if (
            isset(
                $item['Is_Free_Sample']
            ) &&
            $item['Is_Free_Sample'] ===
                'Yes'
        ) {
            return true;
        }

        /*
         * Explicit excluded SKU.
         */
        if (
            in_array(
                $item['SKU'] ?? '',
                $excludeSkuList,
                true
            )
        ) {
            return true;
        }

        /*
         * Previously consumed SKU.
         */
        if (
            in_array(
                $item['SKU'] ?? '',
                $skuRemoveArrNew,
                true
            )
        ) {
            return true;
        }

        /*
         * Pocket perfume.
         */
        if (
            trim(
                (string)
                $rule->exclude_pocketperfume
            ) === 'Yes' &&
            $this->isPocketPerfume(
                $item['CategoryID'] ?? 0
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * Existing pocket perfume check.
     */
   protected function isPocketPerfume($categoryId): bool
    {
        return in_array(
            (int) $categoryId,
            CheckoutConstants::POCKET_PERFUME_CATEGORIES,
            true
        );
    }


    /**
     * Get coupon code.
     *
     * Equivalent to GetAllCoupons('CouponCode')
     * for this service.
     */
    protected function getCouponCode(): string
    {
        return (string)
            Session::get(
                'ShoppingCart.PromoCoupon.CouponCode',
                ''
            );
    }

    /**
     * Excluded SKU parser.
     */
    protected function getExcludeSkuList(
        ?string $value
    ): array {
        if (
            trim(
                (string) $value
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

    /**
     * CSV parser.
     */
    protected function csvToArray(
        ?string $value
    ): array {
        if (
            trim(
                (string) $value
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

    /**
     * Keep the higher discount.
     *
     * Existing source compares MatchNewQTYDiscount
     * and newly calculated QuantityDiscount.
     */
    protected function selectHigherDiscount(
        float $current,
        float $new
    ): float {
        if (
            $new > $current
        ) {
            return $new;
        }

        return $current;
    }

    /**
     * Clear quantity discount and item-wise values.
     *
     * Equivalent to the Quantity part of
     * getAllDiscountBlank("Quantity").
     */
    protected function clearQuantityDiscount(): void
    {
        Session::put(
            'ShoppingCart.QuantityDiscount',
            0
        );

        Session::put(
            'ShoppingCart.QuantityDiscountFlag',
            ''
        );

        /*
         * Existing helper clears item-wise quantity
         * discounts from all cart items.
         */
        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        foreach (
            array_keys($cart)
            as $index
        ) {
            Session::put(
                'ShoppingCart.Cart.'
                . $index
                . '.QuantityItemWiseDiscout',
                0
            );
        }
    }
}
