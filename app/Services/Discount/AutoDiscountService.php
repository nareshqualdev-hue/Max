<?php

namespace App\Services\Discount;

use App\Models\AutoDiscount;
use App\Models\Coupon;
use App\Models\Manufacture;
use App\Models\Products;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use App\Constants\CheckoutConstants;
class AutoDiscountService
{
  

    /**
     * Apply automatic discount to current cart.
     *
     * Existing ApplyAutoDiscount() behavior is being moved here.
     */
    public function apply(): void
    {
		
		    if (
        config('Settings.AUTODISCOUNTFLAG') !== 'Yes'
			) {
				return;
			}

        $autoDiscount = 0;
        $newSubTotal = 0;
        $discountCouponFlag = '';

        $log = [
            'pocketPerfumeCategory' =>
                json_encode(
                    CheckoutConstants::POCKET_PERFUME_CATEGORIES
                ),
        ];

        addLog(
            'ApplyAutoDiscountStart',
            $log
        );

        /*
         * No cart -> nothing to calculate.
         */
        if (
            !Session::has('ShoppingCart.Cart') ||
            count(
                Session::get('ShoppingCart.Cart', [])
            ) === 0
        ) {
            return;
        }

        /*
         * Existing ApplyAutoDiscount() first clears all
         * item-wise AutoItemWiseDiscout values.
         *
         * This prevents stale item discounts when the cart,
         * rule, coupon, or exclusions change.
         */
        $this->clearAutoItemWiseDiscounts();

        /*
         * Existing logic clears previous Auto Discount
         * before recalculating.
         */
        $this->clearAutoDiscount();

        /*
         * ---------------------------------------------------------
         * Wholesaler restriction
         * ---------------------------------------------------------
         *
         * Existing:
         *
         * logged-in wholesaler -> no auto discount
         */
        $normalUser = Auth::user();

        if (Auth::guard('store')->check()) {
            $normalUser = Auth::guard('web')->user();
        }

        if (
            $normalUser &&
            Session::get('eusertype') === 'Wholesaler'
        ) {
            Session::put(
                'ShoppingCart.AutoDiscount',
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
            Session::get('eusertype') === 'Wholesaler'
        ) {
            Session::put(
                'ShoppingCart.AutoDiscount',
                0
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * Prepare subtotal
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.SubTotal'
            )
        ) {
            $newSubTotal = NumberFormat(
                Session::get(
                    'ShoppingCart.SubTotal'
                )
            );
        }

        $giftCertificateTotal = 0;

        if (
            Session::has(
                'ShoppingCart.GiftCertiTotal'
            )
        ) {
            $giftCertificateTotal =
                NumberFormat(
                    Session::get(
                        'ShoppingCart.GiftCertiTotal'
                    )
                );
        }

        $subTotal =
            $newSubTotal
            - $giftCertificateTotal;

        /*
         * Existing getDealSubTotal() must be migrated
         * into a dedicated Cart/Discount helper.
         *
         * For now read the existing session value if present.
         */
        $dealSubTotal =
            $this->getDealSubTotal();

        $subTotal =
            $subTotal
            - $dealSubTotal;

        addLog(
            'ApplyAutoDiscount',
            [
                'NewSubTotal' =>
                    $newSubTotal,

                'GiftCertiTotal' =>
                    $giftCertificateTotal,

                'DealSubTotal' =>
                    $dealSubTotal,

                'subTotal' =>
                    $subTotal,
            ]
        );

        /*
         * Existing rule:
         * no positive subtotal => no auto discount.
         */
        if ($subTotal <= 0) {

            $this->clearAutoDiscount();

            return;
        }

        /*
         * ---------------------------------------------------------
         * Coupon autodiscount flag
         * ---------------------------------------------------------
         */
        $couponCode = Session::get(
            'ShoppingCart.PromoCoupon.CouponCode',
            ''
        );

        $today = date('Y-m-d');

        if ($couponCode !== '') {

            $coupon = Coupon::select(
                'autodiscount_flag'
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

                addLog(
                    'ApplyAutoDiscount',
                    [
                        'coupon_res' =>
                            $coupon,
                    ]
                );

                if (
                    $coupon->autodiscount_flag ===
                    'No'
                ) {
                    $this->clearAutoDiscount();

                    return;
                }
            }
        }

        /*
         * ---------------------------------------------------------
         * Load applicable AutoDiscount rules.
         * ---------------------------------------------------------
         */
        $baseQuery = AutoDiscount::where(
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
                'status',
                '1'
            );

        /*
         * First preference:
         * subtotal falls inside order amount range.
         */
        $autoRules = (clone $baseQuery)
            ->where(
                'end_order_amount',
                '>=',
                $subTotal
            )
            ->where(
                'order_amount',
                '<=',
                $subTotal
            )
            ->orderByDesc(
                'end_order_amount'
            )
            ->get();

        /*
         * Existing fallback.
         */
        if ($autoRules->isEmpty()) {

            $autoRules = (clone $baseQuery)
                ->where(
                    'end_order_amount',
                    '<=',
                    $subTotal
                )
                ->orderByDesc(
                    'end_order_amount'
                )
                ->get();
        }

        addLog(
            'ApplyAutoDiscount',
            [
                'AutoRS' =>
                    $autoRules,
            ]
        );

        if ($autoRules->isEmpty()) {
            return;
        }

        /*
         * ---------------------------------------------------------
         * Cart preparation
         * ---------------------------------------------------------
         */
        $cart = Session::get(
            'ShoppingCart.Cart',
            []
        );

        $totalItems =
            count($cart);

        $totalAutoDiscountRecords =
            $autoRules->count();

        $totalExcludePrice = 0;

        $amountBasedDiscountExcludeSku =
            'No';

        $skuRemoveArr = '';

        /*
         * Existing active manufacturer cache.
         */
        $activeBrandIds =
            $this->getActiveBrandIds();

        /*
         * ---------------------------------------------------------
         * Process each AutoDiscount rule
         * ---------------------------------------------------------
         */
        for (
            $i = 0;
            $i < $totalAutoDiscountRecords;
            $i++
        ) {

            $rule = $autoRules[$i];

            $discountCouponFlag =
                $rule->discount_coupon_flag;

            $excludeSkuList =
                $this->getExcludedSkus(
                    $rule->exclude_sku
                );

            $totalExcludePrice = 0;

            /*
             * Excluded SKU price.
             */
            if ($totalItems > 0) {

                $totalExcludePrice =
                    $this->calculateExcludedSkuPrice(
                        $cart,
                        $excludeSkuList
                    );
            }

            /*
             * Exclude pocket perfume.
             */
            if (
                $rule->exclude_pocketperfume ===
                'Yes'
            ) {
                $totalExcludePrice +=
                    $this->calculatePocketPerfumePrice(
                        $cart
                    );
            }

            /*
             * -----------------------------------------------------
             * SKU based rule
             * -----------------------------------------------------
             *
             * The existing code has SKU/order branches.
             */
            if (
                isset($rule->orders) &&
                $rule->orders === '0'
            ) {

                $result =
                    $this->applySkuRule(
                        $rule,
                        $cart,
                        $excludeSkuList,
                        $skuRemoveArr,
                        $subTotal,
                        $totalExcludePrice
                    );

                if ($result['matched']) {

                    $autoDiscount =
                        $this->mergeDiscount(
                            $autoDiscount,
                            $result['discount']
                        );

                    $skuRemoveArr =
                        $result['skuRemoveArr'];

                    $amountBasedDiscountExcludeSku =
                        $result[
                            'amountBasedDiscountExcludeSku'
                        ];

                    $discountCouponFlag =
                        $result['discountCouponFlag'];
                }
            }

            /*
             * -----------------------------------------------------
             * Brand / order rule
             * -----------------------------------------------------
             */
            elseif (
                isset($rule->orders) &&
                $rule->orders === '1'
            ) {

                if (
                    !empty(
                        trim(
                            (string) $rule->sku
                        )
                    )
                ) {

                    $result =
                        $this->applyBrandRule(
                            $rule,
                            $cart,
                            $excludeSkuList,
                            $skuRemoveArr,
                            $activeBrandIds
                        );

                    if ($result['matched']) {

                        $autoDiscount =
                            $this->mergeDiscount(
                                $autoDiscount,
                                $result['discount']
                            );

                        $skuRemoveArr =
                            $result['skuRemoveArr'];

                        $discountCouponFlag =
                            $result[
                                'discountCouponFlag'
                            ];
                    }
                }
            }

            /*
             * -----------------------------------------------------
             * Amount based fallback rule
             * -----------------------------------------------------
             */
            else {

                $result =
                    $this->applyAmountBasedRule(
                        $rule,
                        $cart,
                        $excludeSkuList,
                        $skuRemoveArr,
                        $subTotal,
                        $totalExcludePrice
                    );

                if ($result['matched']) {

                    $autoDiscount =
                        $result['discount'];

                    $discountCouponFlag =
                        $result[
                            'discountCouponFlag'
                        ];

                    break;
                }
            }
        }

        /*
         * ---------------------------------------------------------
         * Save final result
         * ---------------------------------------------------------
         */
        Session::put(
            'ShoppingCart.AutoDiscount',
            NumberFormat(
                $autoDiscount
            )
        );

        Session::put(
            'ShoppingCart.AutoDiscountFlag',
            $discountCouponFlag
        );

        addLog(
            'ApplyAutoDiscountEnd',
            [
                'AutoDiscount' =>
                    $autoDiscount,

                'discount_coupon_flag' =>
                    $discountCouponFlag,
            ]
        );

        return;
    }

    /**
     * Clear all item-wise Auto Discount values.
     *
     * Matches legacy getAllDiscountBlank('Auto').
     */
    protected function clearAutoItemWiseDiscounts(): void
    {
        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        foreach (
            $cart as $index => $item
        ) {
            Session::put(
                'ShoppingCart.Cart.'
                . $index
                . '.AutoItemWiseDiscout',
                0
            );
        }
    }

    /**
     * Clear existing automatic discount.
     */
    protected function clearAutoDiscount(): void
    {
        Session::put(
            'ShoppingCart.AutoDiscount',
            0
        );

        Session::put(
            'ShoppingCart.AutoDiscountFlag',
            ''
        );
    }

    /**
     * Existing pocket perfume categories.
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
     * Parse excluded SKU list.
     */
    protected function getExcludedSkus(
        ?string $value
    ): array {
        if (
            empty(trim((string) $value))
        ) {
            return [];
        }

        return array_values(
            array_filter(
                array_unique(
                    array_map(
                        'trim',
                        explode(',', $value)
                    )
                ),
                'strlen'
            )
        );
    }

    /**
     * Calculate excluded SKU price.
     */
    protected function calculateExcludedSkuPrice(
        array $cart,
        array $excludedSkus
    ): float {
        if (empty($excludedSkus)) {
            return 0;
        }

        $total = 0;

        foreach ($cart as $item) {

            if (
                isset($item['SKU']) &&
                in_array(
                    $item['SKU'],
                    $excludedSkus,
                    true
                )
            ) {
                $total +=
                    (float) (
                        $item['TotPrice'] ?? 0
                    );
            }
        }

        return $total;
    }

    /**
     * Calculate pocket perfume exclusion.
     */
    protected function calculatePocketPerfumePrice(
        array $cart
    ): float {
        $total = 0;

        foreach ($cart as $item) {

            if (
                !empty(
                    $item['CategoryID']
                ) &&
                $this->isPocketPerfume(
                    $item['CategoryID']
                )
            ) {
                $total +=
                    (float) (
                        $item['TotPrice'] ?? 0
                    );
            }
        }

        return $total;
    }

    /**
     * Active manufacturer IDs.
     *
     * Existing Cache::rememberForever() logic.
     */
    protected function getActiveBrandIds(): array
    {
        return Cache::rememberForever(
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
    }

    /**
     * SKU rule processor.
     *
     * NOTE:
     * The source has additional branches which must remain
     * exactly aligned with the latest CartTrait.
     */
    protected function applySkuRule(
    $rule,
    array $cart,
    array $excludedSkus,
    string $skuRemoveArr,
    float $subTotal,
    float $totalExcludePrice
): array {
    $matched = false;
    $discount = 0;
    $amountBasedExclude = 'No';

    $ruleSkus = $this->getExcludedSkus(
        $rule->sku
    );

    $removeSkus = $this->csvToArray(
        $skuRemoveArr
    );

    $discountBase = 0;

    /*
     * ---------------------------------------------------------
     * FIRST PASS
     * ---------------------------------------------------------
     *
     * Find eligible SKU items.
     *
     * Keep the existing Old Checkout exclusions:
     *
     * - Deal Products
     * - Free Gift
     * - Free Sample
     * - exclude_sku
     * - already processed SKU
     * - Pocket Perfume when configured
     */
    foreach ($cart as $index => $item) {

        $sku = $item['SKU'] ?? '';

        if (
            !in_array(
                $sku,
                $ruleSkus,
                true
            )
        ) {
            continue;
        }

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
                $sku,
                $excludedSkus,
                true
            )
        ) {
            continue;
        }

        if (
            in_array(
                $sku,
                $removeSkus,
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

        /*
         * Old percentage logic uses Price * Qty.
         */
        $lineAmount =
            (float) (
                $item['Price'] ?? 0
            )
            *
            (float) (
                $item['Qty'] ?? 0
            );

        /*
         * Old fixed-discount allocation uses TotPrice.
         */
        $lineTotal =
            (float) (
                $item['TotPrice'] ?? 0
            );

        $discountBase +=
            $lineAmount;

        /*
         * Keep existing SKU remove behavior.
         */
        $skuRemoveArr .=
            $sku . ',';

        /*
         * -----------------------------------------------------
         * PERCENTAGE SKU DISCOUNT
         * -----------------------------------------------------
         */
        if (
            (int) $rule->type === 1
        ) {

            $itemDiscount =
                $lineAmount
                *
                (
                    (float)
                    $rule->auto_discount_amount
                    / 100
                );

            $this->putItemDiscount(
                $item,
                $cart,
                $itemDiscount
            );
        }
    }

    if (!$matched) {
        return [
            'matched' => false,

            'discount' => 0,

            'skuRemoveArr' =>
                $skuRemoveArr,

            'amountBasedDiscountExcludeSku' =>
                $amountBasedExclude,

            'discountCouponFlag' =>
                $rule->discount_coupon_flag,
        ];
    }

    /*
     * ---------------------------------------------------------
     * PERCENTAGE DISCOUNT
     * ---------------------------------------------------------
     */
    if (
        (int) $rule->type === 1
    ) {

        $discount =
            $discountBase
            *
            (
                (float)
                $rule->auto_discount_amount
                / 100
            );

        return [
            'matched' => true,

            'discount' => $discount,

            'skuRemoveArr' =>
                $skuRemoveArr,

            'amountBasedDiscountExcludeSku' =>
                $amountBasedExclude,

            'discountCouponFlag' =>
                $rule->discount_coupon_flag,
        ];
    }

    /*
     * ---------------------------------------------------------
     * FIXED SKU DISCOUNT
     * ---------------------------------------------------------
     *
     * IMPORTANT:
     *
     * Old checkout distributes the fixed discount across the
     * matching SKU items according to their TotPrice.
     *
     * Example:
     *
     * SKU A = $100
     * SKU B = $50
     * Fixed discount = $30
     *
     * Total eligible = $150
     *
     * A gets $20
     * B gets $10
     *
     * Keep the same behavior.
     */
    $fixedDiscount =
        (float)
        $rule->auto_discount_amount;

    $totalEligiblePrice = 0.0;

    /*
     * Calculate TotalAmount exactly from eligible SKU items.
     */
    foreach ($cart as $item) {

        $sku = $item['SKU'] ?? '';

        if (
            !in_array(
                $sku,
                $ruleSkus,
                true
            )
        ) {
            continue;
        }

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
                $sku,
                $excludedSkus,
                true
            )
        ) {
            continue;
        }

        if (
            in_array(
                $sku,
                $removeSkus,
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

        $totalEligiblePrice +=
            (float) (
                $item['TotPrice'] ?? 0
            );
    }

    /*
     * Old code only applies the fixed SKU discount when
     * matching SKU amount is greater than zero.
     */
    if (
        $totalEligiblePrice > 0 &&
        $fixedDiscount > 0
    ) {

        $amountBasedExclude = 'Yes';

        /*
         * Same ratio used by Old Checkout:
         *
         * fixedDiscount * 100 / TotalAmount
         */
        $itemDiscountPercentage =
            (
                $fixedDiscount * 100
            ) /
            $totalEligiblePrice;

        /*
         * Allocate fixed discount item-wise.
         */
        foreach (
            $cart as $index => $item
        ) {

            $sku =
                $item['SKU'] ?? '';

            if (
                !in_array(
                    $sku,
                    $ruleSkus,
                    true
                )
            ) {
                continue;
            }

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
                    $sku,
                    $excludedSkus,
                    true
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $sku,
                    $removeSkus,
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

            /*
             * Old fixed SKU allocation uses TotPrice.
             */
            $itemTotal =
                (float) (
                    $item['TotPrice'] ?? 0
                );

            $itemDiscount =
                (
                    $itemTotal
                    *
                    $itemDiscountPercentage
                ) / 100;

            Session::put(
                'ShoppingCart.Cart.'
                . $index
                . '.AutoItemWiseDiscout',
                $itemDiscount
            );
        }

        $discount = $fixedDiscount;
    }

    return [
        'matched' => true,

        'discount' => $discount,

        'skuRemoveArr' =>
            $skuRemoveArr,

        'amountBasedDiscountExcludeSku' =>
            $amountBasedExclude,

        'discountCouponFlag' =>
            $rule->discount_coupon_flag,
    ];
}
    /**
     * Brand rule processor.
     */
    protected function applyBrandRule(
        $rule,
        array $cart,
        array $excludedSkus,
        string $skuRemoveArr,
        array $activeBrandIds
    ): array {
        $matched = false;
        $discountBase = 0;

        $brandIds =
            array_values(
                array_intersect(
                    $this->csvToArray(
                        $rule->sku
                    ),
                    $activeBrandIds
                )
            );

        if (empty($brandIds)) {
            return [
                'matched' => false,
                'discount' => 0,
                'skuRemoveArr' =>
                    $skuRemoveArr,
                'discountCouponFlag' =>
                    $rule->discount_coupon_flag,
            ];
        }

        $productIds =
            collect($cart)
                ->pluck('ProductID')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

        addLog(
            'ApplyAutoDiscount',
            [
                'arr_active_BrandID' =>
                    $brandIds,

                'temp_prod_id' =>
                    $productIds,
            ]
        );

        $brandProductIds =
            Products::whereIn(
                'imanufactureid',
                $brandIds
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

        addLog(
            'ApplyAutoDiscount',
            [
                'brand_prod_id' =>
                    $brandProductIds,
            ]
        );

        $removeSkus =
            $this->csvToArray(
                $skuRemoveArr
            );

        foreach ($cart as $item) {

            $sku =
                $item['SKU'] ?? '';

            if (
                !in_array(
                    $item['ProductID'] ?? null,
                    $brandProductIds
                )
            ) {
                continue;
            }

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
                    $sku,
                    $excludedSkus,
                    true
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $sku,
                    $removeSkus,
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

            $lineAmount =
                (float) (
                    $item['Price'] ?? 0
                )
                *
                (float) (
                    $item['Qty'] ?? 0
                );

            $discountBase +=
                $lineAmount;

            $skuRemoveArr .=
                $sku . ',';

            if (
                (int) $rule->type === 1
            ) {
                $itemDiscount =
                    $lineAmount
                    *
                    (
                        (float)
                        $rule->auto_discount_amount
                        / 100
                    );

                $this->putItemDiscount(
                    $item,
                    $cart,
                    $itemDiscount
                );
            }
        }

        if (!$matched) {
            return [
                'matched' => false,
                'discount' => 0,
                'skuRemoveArr' =>
                    $skuRemoveArr,
                'discountCouponFlag' =>
                    $rule->discount_coupon_flag,
            ];
        }

        $discount =
            (int) $rule->type === 1
                ? $discountBase *
                    (
                        (float)
                        $rule->auto_discount_amount
                        / 100
                    )
                : (float)
                    $rule->auto_discount_amount;

        return [
            'matched' => true,
            'discount' => $discount,
            'skuRemoveArr' =>
                $skuRemoveArr,
            'discountCouponFlag' =>
                $rule->discount_coupon_flag,
        ];
    }

    /**
     * Amount-based fallback rule.
     */
    protected function applyAmountBasedRule(
        $rule,
        array $cart,
        array $excludedSkus,
        string $skuRemoveArr,
        float $subTotal,
        float $totalExcludePrice
    ): array {
        /*
         * Legacy behavior:
         * The amount-based fallback uses ONLY rules where sku=''
         * and first tries the current subtotal range, then the
         * highest previous applicable range.
         */
        $baseQuery =
            AutoDiscount::where(
                'start_date',
                '<=',
                date('Y-m-d')
            )
                ->where(
                    'end_date',
                    '>=',
                    date('Y-m-d')
                )
                ->where(
                    'status',
                    '1'
                )
                ->where(
                    'sku',
                    ''
                );

        $autoRule =
            (clone $baseQuery)
                ->where(
                    'end_order_amount',
                    '>=',
                    $subTotal
                )
                ->where(
                    'order_amount',
                    '<=',
                    $subTotal
                )
                ->orderByDesc(
                    'end_order_amount'
                )
                ->first();

        if (!$autoRule) {
            $autoRule =
                (clone $baseQuery)
                    ->where(
                        'end_order_amount',
                        '<=',
                        $subTotal
                    )
                    ->orderByDesc(
                        'end_order_amount'
                    )
                    ->first();
        }

        if (!$autoRule) {
            return [
                'matched' => false,
                'discount' => 0,
                'discountCouponFlag' =>
                    $rule->discount_coupon_flag,
            ];
        }

        addLog(
            'ApplyAutoDiscount',
            [
                'AutoRS1' =>
                    $autoRule,
            ]
        );

        /*
         * IMPORTANT:
         * Recalculate exclusions from the FALLBACK rule itself.
         *
         * The previous implementation passed the exclusion data
         * from the outer rule. That could apply the wrong
         * exclude_sku / pocket-perfume flag to the fallback rule.
         */
        $autoRuleExcludedSkus =
            $this->getExcludedSkus(
                $autoRule->exclude_sku
            );

        $removeSkus =
            $this->csvToArray(
                $skuRemoveArr
            );

        $ruleTotalExcludePrice = 0.0;
        $eligibleTotalPrice = 0.0;

        /*
         * First calculate the exact eligible amount.
         */
        foreach (
            $cart as $index => $item
        ) {
            $sku =
                $item['SKU'] ?? '';

            $lineTotal =
                (float) (
                    $item['TotPrice'] ?? 0
                );

            /*
             * Legacy exclusions.
             */
            if (
                in_array(
                    $sku,
                    $autoRuleExcludedSkus,
                    true
                )
            ) {
                $ruleTotalExcludePrice +=
                    $lineTotal;

                continue;
            }

            if (
                in_array(
                    $sku,
                    $removeSkus,
                    true
                )
            ) {
                continue;
            }

            /*
             * Deal products, Free Gifts and Free Samples
             * are not eligible for the amount-based rule.
             */
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

            /*
             * Pocket Perfume exclusion is controlled by the
             * backend Yes/No flag on THIS fallback rule.
             */
            if (
                $autoRule->exclude_pocketperfume ===
                'Yes' &&
                $this->isPocketPerfume(
                    $item['CategoryID'] ?? 0
                )
            ) {
                $ruleTotalExcludePrice +=
                    $lineTotal;

                continue;
            }

            $eligibleTotalPrice +=
                $lineTotal;
        }

        /*
         * Legacy amount-based rule uses subtotal minus
         * excluded SKU / Pocket Perfume amount.
         */
        $eligibleSubtotal =
            max(
                0,
                $subTotal -
                $ruleTotalExcludePrice
            );

        /*
         * Percentage discount.
         */
        if (
            (int) $autoRule->type === 1
        ) {

            $discount =
                $eligibleSubtotal *
                (
                    (float)
                    $autoRule->auto_discount_amount
                    / 100
                );

            /*
             * Preserve legacy item-wise percentage discount.
             */
            foreach (
                $cart as $index => $item
            ) {
                $sku =
                    $item['SKU'] ?? '';

                if (
                    in_array(
                        $sku,
                        $autoRuleExcludedSkus,
                        true
                    ) ||
                    in_array(
                        $sku,
                        $removeSkus,
                        true
                    ) ||
                    ($item['IsDealProducts'] ?? '') ===
                        'Yes' ||
                    ($item['IS_Free_Gift'] ?? '') ===
                        'Yes' ||
                    ($item['Is_Free_Sample'] ?? '') ===
                        'Yes'
                ) {
                    continue;
                }

                if (
                    $autoRule->exclude_pocketperfume ===
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
                        ($item['Price'] ?? 0)
                        *
                        (float)
                        ($item['Qty'] ?? 0)
                    ) *
                    (
                        (float)
                        $autoRule->auto_discount_amount
                        / 100
                    );

                Session::put(
                    'ShoppingCart.Cart.'
                    . $index
                    . '.AutoItemWiseDiscout',
                    $itemDiscount
                );
            }

        } else {

            /*
             * Fixed amount discount:
             * distribute the fixed amount proportionally over
             * eligible cart items, exactly like old logic.
             */
            $discount =
                (float)
                $autoRule->auto_discount_amount;

            if (
                $eligibleTotalPrice > 0 &&
                $discount > 0
            ) {

                $itemPercentage =
                    (
                        $discount * 100
                    ) /
                    $eligibleTotalPrice;

                foreach (
                    $cart as $index => $item
                ) {
                    $sku =
                        $item['SKU'] ?? '';

                    if (
                        in_array(
                            $sku,
                            $autoRuleExcludedSkus,
                            true
                        ) ||
                        in_array(
                            $sku,
                            $removeSkus,
                            true
                        ) ||
                        ($item['IsDealProducts'] ?? '') ===
                            'Yes' ||
                        ($item['IS_Free_Gift'] ?? '') ===
                            'Yes' ||
                        ($item['Is_Free_Sample'] ?? '') ===
                            'Yes'
                    ) {
                        continue;
                    }

                    if (
                        $autoRule->exclude_pocketperfume ===
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
                            ($item['TotPrice'] ?? 0)
                            *
                            $itemPercentage
                        ) / 100;

                    Session::put(
                        'ShoppingCart.Cart.'
                        . $index
                        . '.AutoItemWiseDiscout',
                        $itemDiscount
                    );
                }
            }
        }

        return [
            'matched' => true,
            'discount' => $discount,
            'discountCouponFlag' =>
                $autoRule->discount_coupon_flag,
        ];
    }

    /**
     * Merge multiple applicable discounts.
     */
    protected function mergeDiscount(
        float $current,
        float $new
    ): float {
        if ($new <= 0) {
            return $current;
        }

        return $current + $new;
    }

    /**
     * Store item-wise discount.
     */
    protected function putItemDiscount(
        array $item,
        array $cart,
        float $discount
    ): void {
        $index = array_search(
            $item,
            $cart,
            true
        );

        if ($index === false) {
            return;
        }

        Session::put(
            'ShoppingCart.Cart.'
            . $index
            . '.AutoItemWiseDiscout',
            $discount
        );
    }

    /**
     * Existing getDealSubTotal() bridge.
     *
     * This MUST later move to CartCalculatorService.
     */
    protected function getDealSubTotal(): float
    {
        return (float) Session::get(
            'ShoppingCart.DealSubTotal',
            0
        );
    }

    /**
     * Convert CSV string to array.
     */
    protected function csvToArray(
        ?string $value
    ): array {
        if (
            empty(trim((string) $value))
        ) {
            return [];
        }

        return array_values(
            array_filter(
                array_unique(
                    array_map(
                        'trim',
                        explode(',', $value)
                    )
                ),
                'strlen'
            )
        );
    }
}
