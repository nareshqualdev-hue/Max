<?php

namespace App\Services\Discount;

use App\Constants\CheckoutConstants;
use App\Models\BogoDiscount;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Manufacture;
use App\Models\Products;
use App\Models\ProductsCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class BogoDiscountService
{
    /**
     * Apply BOGO / DOGO discount.
     *
     * Migration of CartTrait::ApplyDogoDiscount()
     */
    public function apply(): void
    {
        $pocketPerfumeCategory =
            CheckoutConstants::POCKET_PERFUME_CATEGORIES;

        $log = [
            'pocketPerfumeCategory' =>
                json_encode($pocketPerfumeCategory),
        ];

        addLog(
            'ApplyDogoDiscountStart',
            $log
        );

        $DogoDiscount = 0;
        $BogoItemWiseDiscout = 0;

        if (
            !Session::has('ShoppingCart.Cart') ||
            count(
                Session::get('ShoppingCart.Cart')
            ) <= 0
        ) {
            return;
        }

        /*
         * Existing:
         *
         * $this->getAllDiscountBlank("Bogo");
         *
         * Clear BOGO related session values.
         */
        $this->clearBogoDiscount();

        /*
         * Keep the same two cart snapshots
         * used by the existing method.
         */
        $CartInfo =
            Session::get(
                'ShoppingCart.Cart'
            );

        $tempCart1 =
            Session::get(
                'ShoppingCart.Cart'
            );

        $GiftCertiTotal = 0;
        $GiftCertiCount = 0;

        if (
            Session::has(
                'ShoppingCart.GiftCertiTotal'
            ) &&
            Session::get(
                'ShoppingCart.GiftCertiTotal'
            ) != ''
        ) {
            $GiftCertiTotal =
                Session::get(
                    'ShoppingCart.GiftCertiTotal'
                );
        }

        if (
            Session::has(
                'ShoppingCart.GiftCertiCount'
            ) &&
            Session::get(
                'ShoppingCart.GiftCertiCount'
            ) != ''
        ) {
            $GiftCertiCount =
                Session::get(
                    'ShoppingCart.GiftCertiCount'
                );
        }

        $subTotal =
            Session::get(
                'ShoppingCart.SubTotal'
            )
            - $GiftCertiTotal;

        $Cart =
            Session::get(
                'ShoppingCart.Cart'
            );

        $TotalItem =
            Session::get(
                'ShoppingCart.TotalItemInCart'
            );

        $DogoDiscountFlag = '';

        /*
         * Same existing validation.
         */
        if (
            $subTotal <= 0 ||
            $TotalItem <= 0
        ) {
            Session::put(
                'ShoppingCart.DogoDiscount',
                0
            );

            return;
        }

        /*
         * Same wholesaler restriction.
         */
        $normaluser =
            Auth::user();

        if (
            Auth::guard('store')->check()
        ) {
            $normaluser =
                Auth::guard('web')->user();
        }

        if (
            $normaluser &&
            Session::get('eusertype') ===
                'Wholesaler'
        ) {
            Session::put(
                'ShoppingCart.DogoDiscount',
                0
            );

            return;
        }

        if (
            Session::has('isPhoneOrder') &&
            Session::has('eusertype') &&
            Session::get('eusertype') ===
                'Wholesaler'
        ) {
            Session::put(
                'ShoppingCart.DogoDiscount',
                0
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * Coupon BOGO flag
         * ---------------------------------------------------------
         */
        $CouponCode =
            $this->getCouponCode();

        if (
            $CouponCode !== ''
        ) {
            $coupon_res =
                Coupon::select(
                    'bogodiscount_flag'
                )
                ->where(
                    'coupon_number',
                    '=',
                    $CouponCode
                )
                ->where(
                    'status',
                    '=',
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
                )
                ->get();

            if (
                $coupon_res &&
                $coupon_res->count() > 0
            ) {
                $log['coupon_res'] =
                    json_encode(
                        $coupon_res
                    );

                addLog(
                    'ApplyDogoDiscount',
                    $log
                );

                if (
                    $coupon_res[0]
                        ->bogodiscount_flag ===
                    'No'
                ) {
                    Session::put(
                        'ShoppingCart.DogoDiscount',
                        0
                    );

                    Session::put(
                        'ShoppingCart.BogoDiscountFlag',
                        ''
                    );

                    $log['DogoDiscount'] =
                        '0';

                    $log['BogoDiscountFlag'] =
                        '';

                    addLog(
                        'ApplyDogoDiscount',
                        $log
                    );

                    return;
                }
            }
        }

        /*
         * ---------------------------------------------------------
         * Clear old BOGO messages.
         * ---------------------------------------------------------
         */
        for (
            $c = 0;
            $c < count($Cart);
            $c++
        ) {
            Session::put(
                'ShoppingCart.Cart.'
                . $c
                . '.BogoDiscountMessage',
                ''
            );
        }

        /*
         * ---------------------------------------------------------
         * Active BOGO rules
         * ---------------------------------------------------------
         */
        $DogoRS =
            BogoDiscount::where(
                'start_date',
                '<=',
                DB::raw('curdate()')
            )
            ->where(
                'end_date',
                '>=',
                DB::raw('curdate()')
            )
            ->where(
                'status',
                '=',
                '1'
            )
            ->orderBy(
                'bogo_discount_id',
                'desc'
            )
            ->get();

        if (
            $DogoRS &&
            $DogoRS->count() > 0
        ) {
            $log['DogoRS'] =
                json_encode(
                    $DogoRS
                );

            $DogoDiscount = 0;

            /*
             * -----------------------------------------------------
             * Process each BOGO rule.
             * -----------------------------------------------------
             */
            for (
                $i = 0;
                $i < $DogoRS->count();
                $i++
            ) {
                $rule =
                    $DogoRS[$i];

                /*
                 * orders = 2
                 *
                 * Multiple SKU
                 */
                if (
                    $rule['orders'] === '2'
                ) {
                    $DogoDiscount =
                        $this->applySkuRule(
                            $rule,
                            $tempCart1,
                            $DogoDiscount,
                            $pocketPerfumeCategory
                        );
                }

                /*
                 * orders = 0
                 *
                 * Category
                 */
                elseif (
                    $rule['orders'] === '0'
                ) {
                    $DogoDiscount =
                        $this->applyCategoryRule(
                            $rule,
                            $tempCart1,
                            $DogoDiscount,
                            $pocketPerfumeCategory
                        );
                }

                /*
                 * orders = 1
                 *
                 * Brand
                 */
                elseif (
                    $rule['orders'] === '1'
                ) {
                    $DogoDiscount =
                        $this->applyBrandRule(
                            $rule,
                            $tempCart1,
                            $DogoDiscount,
                            $pocketPerfumeCategory
                        );
                }
            }
        } else {
            $DogoDiscount = 0;
        }

        /*
         * ---------------------------------------------------------
         * Final session value
         * ---------------------------------------------------------
         */
        Session::put(
            'ShoppingCart.DogoDiscount',
            NumberFormat(
                $DogoDiscount
            )
        );

        $log['DogoDiscount'] =
            $DogoDiscount;

        addLog(
            'ApplyDogoDiscount',
            $log
        );

        return;
    }

    /**
     * -------------------------------------------------------------
     * orders = 2
     *
     * Multiple SKU BOGO
     * -------------------------------------------------------------
     */
    protected function applySkuRule(
        $rule,
        array $tempCart1,
        float $DogoDiscount,
        array $pocketPerfumeCategory
    ): float {
        $QtySKU =
            trim(
                $rule['sku']
            );

        $arr_QtySKU =
            explode(
                ',',
                $QtySKU
            );

        $arr_QtySKU =
            array_unique(
                array_map(
                    'trim',
                    $arr_QtySKU
                )
            );

        $arr_QtySKU =
            array_filter(
                $arr_QtySKU,
                'strlen'
            );

        $Matched_Item_Total = 0;
        $IS_Any_Matched = 0;
        $SKUArrValCheck = [];

        if (
            !is_array($arr_QtySKU) ||
            empty($arr_QtySKU)
        ) {
            return $DogoDiscount;
        }

        $CartVal =
            Session::get(
                'ShoppingCart.Cart'
            );

        $tempCart = [];

        /*
         * Find matching SKU cart items.
         */
        for (
            $a = 0;
            $a < count($CartVal);
            $a++
        ) {
            $IsGiftCertificateItem =
                $this->checkGiftCertificateItem(
                    'IsGiftCertificateItem',
                    $CartVal[$a]
                );

            $FreeGiftCheck =
                isset(
                    $CartVal[$a]['IS_Free_Gift']
                )
                    ? $CartVal[$a]['IS_Free_Gift']
                    : 'No';

            $FreeSample = '';

            if (
                isset(
                    $CartVal[$a]['Is_Free_Sample']
                )
            ) {
                $FreeSample =
                    $CartVal[$a]['Is_Free_Sample'];
            }

            if (
                $IsGiftCertificateItem === 'No' &&
                in_array(
                    $CartVal[$a]['SKU'],
                    $arr_QtySKU
                ) &&
                $CartVal[$a]['IsDealProducts'] !=
                    'Yes' &&
                $FreeGiftCheck != 'Yes' &&
                $FreeSample != 'Yes'
            ) {
                /*
                 * Pocket perfume exclusion.
                 */
                if (
                    isset(
                        $rule['exclude_pocketperfume']
                    ) &&
                    $rule['exclude_pocketperfume'] ===
                        'Yes' &&
                    isset(
                        $CartVal[$a]['CategoryID']
                    ) &&
                    in_array(
                        $CartVal[$a]['CategoryID'],
                        $pocketPerfumeCategory
                    )
                ) {
                    continue;
                }

                /*
                 * Excluded SKU.
                 */
                if (
                    isset(
                        $rule['exclude_product_skus']
                    ) &&
                    $rule['exclude_product_skus'] !=
                        ''
                ) {
                    $exclude_skus_arr =
                        explode(
                            ',',
                            $rule['exclude_product_skus']
                        );

                    if (
                        count(
                            $exclude_skus_arr
                        ) > 0 &&
                        in_array(
                            $CartVal[$a]['SKU'],
                            $exclude_skus_arr
                        )
                    ) {
                        continue;
                    }
                }

                $SKUArrValCheck[] =
                    $CartVal[$a]['SKU'];

                $tempCart[] =
                    $CartVal[$a];
            }
        }

        if (
            count($tempCart) <= 0
        ) {
            return $DogoDiscount;
        }

        $ItemQtyArr = [];
        $SKU_With_Price = [];

        /*
         * Expand Qty into individual records.
         */
        foreach (
            $tempCart as $array
        ) {
            $ItemQtyArr =
                array_merge(
                    $ItemQtyArr,
                    array_fill(
                        0,
                        $array['Qty'],
                        $array
                    )
                );

            if (
                (int)
                $array['Qty'] > 0
            ) {
                for (
                    $q = 0;
                    $q < (int) $array['Qty'];
                    $q++
                ) {
                    $SKU_With_Price[] = [
                        'SKU' =>
                            $array['SKU'],

                        'Price' =>
                            $array['Price'],
                    ];
                }
            }
        }

        $prices =
            array_column(
                $ItemQtyArr,
                'Price'
            );

        $quantites =
            array_column(
                $tempCart,
                'Qty'
            );

        $SKUs =
            array_column(
                $ItemQtyArr,
                'SKU'
            );

        /*
         * BOGO pair count.
         */
        $modCount = 0;

        if (
            intdiv(
                array_sum($quantites),
                2
            ) >= 1
        ) {
            $modCount =
                intdiv(
                    array_sum($quantites),
                    2
                );
        }

        /*
         * Multi quantity discount.
         */
        $QuantityCount = 0;

        if (
            $rule['type'] == '2' &&
            $rule['quantity'] > 0
        ) {
            $ProcessQuantityCount =
                $rule['quantity'] + 1;

            if (
                intdiv(
                    array_sum($quantites),
                    $ProcessQuantityCount
                ) >= 1
            ) {
                $QuantityCount =
                    intdiv(
                        array_sum($quantites),
                        $ProcessQuantityCount
                    );
            }
        }

        /*
         * Sort price.
         */
        if (
            $rule['sortBy'] === 'High'
        ) {
            rsort($prices);

            usort(
                $SKU_With_Price,
                function (
                    $a,
                    $b
                ) {
                    return
                        $b['Price']
                        <=>
                        $a['Price'];
                }
            );
        }
        elseif (
            $rule['sortBy'] === 'Low'
        ) {
            sort($prices);

            usort(
                $SKU_With_Price,
                function (
                    $a,
                    $b
                ) {
                    return
                        $a['Price']
                        <=>
                        $b['Price'];
                }
            );
        }

        $ItemWiseDiscount = [];

        /*
         * Type 0 / Type 1
         */
        if (
            $rule['type'] == '1' ||
            $rule['type'] == '0'
        ) {
            for (
                $a = 0;
                $a < $modCount;
                $a++
            ) {
                /*
                 * Type 1:
                 * Percentage discount.
                 */
                if (
                    $rule['type'] == '1'
                ) {
                    $Percentage =
                        $rule['percentage'];

                    $DisAmount =
                        $prices[$a]
                        *
                        (
                            $Percentage / 100
                        );

                    if (
                        array_key_exists(
                            $SKU_With_Price[$a]['SKU'],
                            $ItemWiseDiscount
                        )
                    ) {
                        $ItemWiseDiscount[
                            $SKU_With_Price[$a]['SKU']
                        ] +=
                            $DisAmount;
                    } else {
                        $ItemWiseDiscount[
                            $SKU_With_Price[$a]['SKU']
                        ] =
                            $DisAmount;
                    }

                    $DogoDiscount +=
                        $DisAmount;
                }

                /*
                 * Type 0:
                 * Default BOGO.
                 */
                else {
                    if (
                        array_key_exists(
                            $SKU_With_Price[$a]['SKU'],
                            $ItemWiseDiscount
                        )
                    ) {
                        $ItemWiseDiscount[
                            $SKU_With_Price[$a]['SKU']
                        ] +=
                            $prices[$a];
                    } else {
                        $ItemWiseDiscount[
                            $SKU_With_Price[$a]['SKU']
                        ] =
                            $prices[$a];
                    }

                    $DogoDiscount +=
                        $prices[$a];
                }
            }
        }

        /*
         * Type 2:
         * Multi quantity percentage.
         */
        elseif (
            $rule['type'] == '2'
        ) {
            for (
                $a = 0;
                $a < $QuantityCount;
                $a++
            ) {
                $Percentage =
                    $rule['percentage'];

                $DisAmount =
                    $prices[$a]
                    *
                    (
                        $Percentage / 100
                    );

                if (
                    array_key_exists(
                        $SKU_With_Price[$a]['SKU'],
                        $ItemWiseDiscount
                    )
                ) {
                    $ItemWiseDiscount[
                        $SKU_With_Price[$a]['SKU']
                    ] +=
                        $DisAmount;
                } else {
                    $ItemWiseDiscount[
                        $SKU_With_Price[$a]['SKU']
                    ] =
                        $DisAmount;
                }

                $DogoDiscount +=
                    $DisAmount;
            }
        }

        /*
         * Apply item-wise discount + message.
         */
        $this->applyItemWiseDiscount(
            $rule,
            $tempCart1,
            $SKUArrValCheck,
            $SKUs,
            $prices,
            $ItemWiseDiscount,
            $modCount,
            $QuantityCount,
            $pocketPerfumeCategory
        );

        return $DogoDiscount;
    }

    /**
     * -------------------------------------------------------------
     * orders = 0
     *
     * Category BOGO
     * -------------------------------------------------------------
     */
    protected function applyCategoryRule(
        $rule,
        array $tempCart1,
        float $DogoDiscount,
        array $pocketPerfumeCategory
    ): float {
        $QtyCatID =
            trim(
                $rule['sku']
            );

        $arr_QtyCatID =
            explode(
                ',',
                $QtyCatID
            );

        /*
         * Get active category IDs.
         */
        $Res_active_CatID =
            Category::where(
                'status',
                '=',
                '1'
            )
            ->whereIn(
                'category_id',
                $arr_QtyCatID
            )
            ->get();

        $arr_active_CatID = [];

        for (
            $h = 0;
            $h < count(
                $Res_active_CatID
            );
            $h++
        ) {
            $arr_active_CatID[] =
                $Res_active_CatID[$h]['category_id'];
        }

        $SKUArrValCheck = [];

        if (
            count(
                $arr_active_CatID
            ) <= 0
        ) {
            return $DogoDiscount;
        }

        /*
         * Get cart product IDs.
         */
        $tempCart =
            Session::get(
                'ShoppingCart.Cart'
            );

        $temp_prod_id = [];

        for (
            $a = 0;
            $a < count($tempCart);
            $a++
        ) {
            $temp_prod_id[$a] =
                $tempCart[$a]['ProductID'];
        }

        /*
         * Exact existing ProductsCategory query.
         */
        $ProdIds =
            ProductsCategory::select(
                'products_id'
            )
            ->distinct()
            ->whereIn(
                'category_id',
                $arr_active_CatID
            )
            ->whereIn(
                'products_id',
                $temp_prod_id
            )
            ->get();

        $cat_prod_id = [];

        for (
            $a = 0;
            $a < count($ProdIds);
            $a++
        ) {
            $cat_prod_id[$a] =
                $ProdIds[$a]['products_id'];
        }

        $total_qty = 0;
        $total_price = 0;
        $total_percentage = false;

        $CartVal =
            Session::get(
                'ShoppingCart.Cart'
            );

        $tempCart = [];

        /*
         * Build eligible category cart.
         */
        for (
            $a = 0;
            $a < count($CartVal);
            $a++
        ) {
            /*
             * Pocket perfume.
             */
            if (
                isset(
                    $rule['exclude_pocketperfume']
                ) &&
                $rule['exclude_pocketperfume'] ===
                    'Yes' &&
                isset(
                    $CartVal[$a]['CategoryID']
                ) &&
                in_array(
                    $CartVal[$a]['CategoryID'],
                    $pocketPerfumeCategory
                )
            ) {
                continue;
            }

            /*
             * Excluded SKU.
             */
            if (
                isset(
                    $rule['exclude_product_skus']
                ) &&
                $rule['exclude_product_skus'] !=
                    ''
            ) {
                $exclude_skus_arr =
                    explode(
                        ',',
                        $rule['exclude_product_skus']
                    );

                if (
                    count(
                        $exclude_skus_arr
                    ) > 0 &&
                    in_array(
                        $CartVal[$a]['SKU'],
                        $exclude_skus_arr
                    )
                ) {
                    continue;
                }
            }

            if (
                !isset(
                    $CartVal[$a]['IS_Free_Gift']
                )
            ) {
                $CartVal[$a]['IS_Free_Gift'] =
                    'No';
            }

            if (
                !isset(
                    $CartVal[$a]['IsDealProducts']
                )
            ) {
                $CartVal[$a]['IsDealProducts'] =
                    'No';
            }

            $FreeSample = '';

            if (
                isset(
                    $CartVal[$a]['Is_Free_Sample']
                )
            ) {
                $FreeSample =
                    $CartVal[$a]['Is_Free_Sample'];
            }

            $IsGiftCertificateItem =
                $this->checkGiftCertificateItem(
                    'IsGiftCertificateItem',
                    $CartVal[$a]
                );

            if (
                $IsGiftCertificateItem === 'No' &&
                in_array(
                    $CartVal[$a]['ProductID'],
                    $cat_prod_id
                ) &&
                $CartVal[$a]['IsDealProducts'] !=
                    'Yes' &&
                isset(
                    $CartVal[$a]['IS_Free_Gift']
                ) &&
                $CartVal[$a]['IS_Free_Gift'] !=
                    'Yes' &&
                $FreeSample !=
                    'Yes'
            ) {
                $tempCart[] =
                    $CartVal[$a];

                $SKUArrValCheck[] =
                    $CartVal[$a]['SKU'];
            }
        }

        if (
            count($tempCart) <= 0
        ) {
            return $DogoDiscount;
        }

        /*
         * Calculate category BOGO.
         */
        $result =
            $this->calculateRuleDiscount(
                $rule,
                $tempCart
            );

        $DogoDiscount +=
            $result['discount'];

        /*
         * Apply item-wise discount/message.
         */
        $this->applyItemWiseDiscount(
            $rule,
            $tempCart1,
            $SKUArrValCheck,
            $result['SKUs'],
            $result['prices'],
            $result['ItemWiseDiscount'],
            $result['modCount'],
            $result['QuantityCount'],
            $pocketPerfumeCategory
        );

        return $DogoDiscount;
    }

    /**
     * -------------------------------------------------------------
     * orders = 1
     *
     * Brand BOGO
     * -------------------------------------------------------------
     */
    protected function applyBrandRule(
        $rule,
        array $tempCart1,
        float $DogoDiscount,
        array $pocketPerfumeCategory
    ): float {
        $QtyBrandID =
            trim(
                $rule['sku']
            );

        $arr_QtyBrandID =
            explode(
                ',',
                $QtyBrandID
            );

        /*
         * Get active brands.
         */
        $Res_active_BrandID =
            Manufacture::where(
                'status',
                '=',
                '1'
            )
            ->whereIn(
                'imanufactureid',
                $arr_QtyBrandID
            )
            ->get();

        $arr_active_BrandID = [];

        for (
            $h = 0;
            $h < $Res_active_BrandID->count();
            $h++
        ) {
            $arr_active_BrandID[] =
                $Res_active_BrandID[$h]
                    ['imanufactureid'];
        }

        $SKUArrValCheck = [];

        if (
            count(
                $arr_active_BrandID
            ) <= 0
        ) {
            return $DogoDiscount;
        }

        /*
         * Get cart Product IDs.
         */
        $tempCart =
            Session::get(
                'ShoppingCart.Cart'
            );

        $temp_prod_id = [];

        for (
            $a = 0;
            $a < count($tempCart);
            $a++
        ) {
            $temp_prod_id[$a] =
                $tempCart[$a]['ProductID'];
        }

        /*
         * Exact existing Products brand query.
         */
        $ProdIds =
            Products::select(
                'products_id'
            )
            ->distinct()
            ->whereIn(
                'imanufactureid',
                $arr_active_BrandID
            )
            ->whereIn(
                'products_id',
                $temp_prod_id
            )
            ->get();

        $brand_prod_id = [];

        for (
            $a = 0;
            $a < count($ProdIds);
            $a++
        ) {
            $brand_prod_id[$a] =
                $ProdIds[$a]['products_id'];
        }

        $CartVal =
            Session::get(
                'ShoppingCart.Cart'
            );

        $tempCart = [];

        /*
         * Build eligible brand cart.
         */
        for (
            $a = 0;
            $a < count($CartVal);
            $a++
        ) {
            /*
             * Pocket perfume.
             */
            if (
                isset(
                    $rule['exclude_pocketperfume']
                ) &&
                $rule['exclude_pocketperfume'] ===
                    'Yes' &&
                isset(
                    $CartVal[$a]['CategoryID']
                ) &&
                in_array(
                    $CartVal[$a]['CategoryID'],
                    $pocketPerfumeCategory
                )
            ) {
                continue;
            }

            /*
             * Excluded SKU.
             */
            if (
                isset(
                    $rule['exclude_product_skus']
                ) &&
                $rule['exclude_product_skus'] !=
                    ''
            ) {
                $exclude_skus_arr =
                    explode(
                        ',',
                        $rule['exclude_product_skus']
                    );

                if (
                    count(
                        $exclude_skus_arr
                    ) > 0 &&
                    in_array(
                        $CartVal[$a]['SKU'],
                        $exclude_skus_arr
                    )
                ) {
                    continue;
                }
            }

            if (
                !isset(
                    $CartVal[$a]['IS_Free_Gift']
                )
            ) {
                $CartVal[$a]['IS_Free_Gift'] =
                    'No';
            }

            if (
                !isset(
                    $CartVal[$a]['IsDealProducts']
                )
            ) {
                $CartVal[$a]['IsDealProducts'] =
                    'No';
            }

            $FreeSample = '';

            if (
                isset(
                    $CartVal[$a]['Is_Free_Sample']
                )
            ) {
                $FreeSample =
                    $CartVal[$a]['Is_Free_Sample'];
            }

            $IsGiftCertificateItem =
                $this->checkGiftCertificateItem(
                    'IsGiftCertificateItem',
                    $CartVal[$a]
                );

            if (
                $IsGiftCertificateItem === 'No' &&
                in_array(
                    $CartVal[$a]['ProductID'],
                    $brand_prod_id
                ) &&
                $CartVal[$a]['IsDealProducts'] !=
                    'Yes' &&
                $CartVal[$a]['IS_Free_Gift'] !=
                    'Yes' &&
                $FreeSample !=
                    'Yes'
            ) {
                $tempCart[] =
                    $CartVal[$a];

                $SKUArrValCheck[] =
                    $CartVal[$a]['SKU'];
            }
        }

        if (
            count($tempCart) <= 0
        ) {
            return $DogoDiscount;
        }

        /*
         * Calculate brand BOGO.
         */
        $result =
            $this->calculateRuleDiscount(
                $rule,
                $tempCart
            );

        $DogoDiscount +=
            $result['discount'];

        /*
         * Apply item-wise discount/message.
         */
        $this->applyItemWiseDiscount(
            $rule,
            $tempCart1,
            $SKUArrValCheck,
            $result['SKUs'],
            $result['prices'],
            $result['ItemWiseDiscount'],
            $result['modCount'],
            $result['QuantityCount'],
            $pocketPerfumeCategory
        );

        return $DogoDiscount;
    }

    /**
     * Calculate common BOGO discount for category/brand.
     *
     * This mirrors the same type/sort/quantity calculation
     * used by the original CartTrait.
     */
    protected function calculateRuleDiscount(
        $rule,
        array $tempCart
    ): array {
        $ItemQtyArr = [];
        $SKU_With_Price = [];

        foreach (
            $tempCart as $array
        ) {
            $ItemQtyArr =
                array_merge(
                    $ItemQtyArr,
                    array_fill(
                        0,
                        $array['Qty'],
                        $array
                    )
                );

            if (
                (int)
                $array['Qty'] > 0
            ) {
                for (
                    $q = 0;
                    $q < (int) $array['Qty'];
                    $q++
                ) {
                    $SKU_With_Price[] = [
                        'SKU' =>
                            $array['SKU'],

                        'Price' =>
                            $array['Price'],
                    ];
                }
            }
        }

        $prices =
            array_column(
                $ItemQtyArr,
                'Price'
            );

        $quantites =
            array_column(
                $tempCart,
                'Qty'
            );

        $SKUs =
            array_column(
                $tempCart,
                'SKU'
            );

        $modCount = 0;

        if (
            intdiv(
                array_sum($quantites),
                2
            ) >= 1
        ) {
            $modCount =
                intdiv(
                    array_sum($quantites),
                    2
                );
        }

        $QuantityCount = 0;

        if (
            $rule['type'] == '2' &&
            $rule['quantity'] > 0
        ) {
            $ProcessQuantityCount =
                $rule['quantity'] + 1;

            if (
                intdiv(
                    array_sum($quantites),
                    $ProcessQuantityCount
                ) >= 1
            ) {
                $QuantityCount =
                    intdiv(
                        array_sum($quantites),
                        $ProcessQuantityCount
                    );
            }
        }

        /*
         * Same High / Low sorting.
         */
        if (
            $rule['sortBy'] === 'High'
        ) {
            rsort($prices);

            usort(
                $SKU_With_Price,
                function (
                    $a,
                    $b
                ) {
                    return
                        $b['Price']
                        <=>
                        $a['Price'];
                }
            );
        }
        elseif (
            $rule['sortBy'] === 'Low'
        ) {
            sort($prices);

            usort(
                $SKU_With_Price,
                function (
                    $a,
                    $b
                ) {
                    return
                        $a['Price']
                        <=>
                        $b['Price'];
                }
            );
        }

        $ItemWiseDiscount = [];
        $discount = 0;

        /*
         * Type 0 / Type 1.
         */
        if (
            $rule['type'] == '1' ||
            $rule['type'] == '0'
        ) {
            for (
                $a = 0;
                $a < $modCount;
                $a++
            ) {
                if (
                    $rule['type'] == '1'
                ) {
                    $Percentage =
                        $rule['percentage'];

                    $DisAmount =
                        $prices[$a]
                        *
                        (
                            $Percentage / 100
                        );

                    $sku =
                        $SKU_With_Price[$a]['SKU'];

                    if (
                        array_key_exists(
                            $sku,
                            $ItemWiseDiscount
                        )
                    ) {
                        $ItemWiseDiscount[$sku]
                            += $DisAmount;
                    } else {
                        $ItemWiseDiscount[$sku]
                            = $DisAmount;
                    }

                    $discount +=
                        $DisAmount;
                } else {
                    $sku =
                        $SKU_With_Price[$a]['SKU'];

                    if (
                        array_key_exists(
                            $sku,
                            $ItemWiseDiscount
                        )
                    ) {
                        $ItemWiseDiscount[$sku]
                            += $prices[$a];
                    } else {
                        $ItemWiseDiscount[$sku]
                            = $prices[$a];
                    }

                    $discount +=
                        $prices[$a];
                }
            }
        }

        /*
         * Type 2.
         */
        elseif (
            $rule['type'] == '2'
        ) {
            for (
                $a = 0;
                $a < $QuantityCount;
                $a++
            ) {
                $Percentage =
                    $rule['percentage'];

                $DisAmount =
                    $prices[$a]
                    *
                    (
                        $Percentage / 100
                    );

                $sku =
                    $SKU_With_Price[$a]['SKU'];

                if (
                    array_key_exists(
                        $sku,
                        $ItemWiseDiscount
                    )
                ) {
                    $ItemWiseDiscount[$sku]
                        += $DisAmount;
                } else {
                    $ItemWiseDiscount[$sku]
                        = $DisAmount;
                }

                $discount +=
                    $DisAmount;
            }
        }

        return [
            'discount' =>
                $discount,

            'ItemWiseDiscount' =>
                $ItemWiseDiscount,

            'prices' =>
                $prices,

            'SKUs' =>
                $SKUs,

            'modCount' =>
                $modCount,

            'QuantityCount' =>
                $QuantityCount,
        ];
    }

    /**
     * Apply BogoItemWiseDiscout and BogoDiscountMessage
     * to original cart items.
     */
    protected function applyItemWiseDiscount(
        $rule,
        array $tempCart1,
        array $SKUArrValCheck,
        array $SKUs,
        array $prices,
        array $ItemWiseDiscount,
        int $modCount,
        int $QuantityCount,
        array $pocketPerfumeCategory
    ): void {
        for (
            $d = 0;
            $d < count($tempCart1);
            $d++
        ) {
            /*
             * Pocket perfume exclusion.
             */
            if (
                isset(
                    $rule['exclude_pocketperfume']
                ) &&
                $rule['exclude_pocketperfume'] ===
                    'Yes' &&
                isset(
                    $tempCart1[$d]['CategoryID']
                ) &&
                in_array(
                    $tempCart1[$d]['CategoryID'],
                    $pocketPerfumeCategory
                )
            ) {
                continue;
            }

            /*
             * Excluded SKU.
             */
            if (
                isset(
                    $rule['exclude_product_skus']
                ) &&
                $rule['exclude_product_skus'] !=
                    ''
            ) {
                $exclude_skus_arr =
                    explode(
                        ',',
                        $rule['exclude_product_skus']
                    );

                if (
                    count(
                        $exclude_skus_arr
                    ) > 0 &&
                    in_array(
                        $tempCart1[$d]['SKU'],
                        $exclude_skus_arr
                    )
                ) {
                    continue;
                }
            }

            $MatchTotal = 0;

            /*
             * Same FinalCount logic.
             */
            $FinalCount =
                $modCount;

            if (
                $rule['type'] == '2' &&
                $rule['quantity'] > 0
            ) {
                $FinalCount =
                    $QuantityCount;
            }

            /*
             * Match price + SKU.
             */
            for (
                $a = 0;
                $a < $FinalCount;
                $a++
            ) {
                if (
                    isset($prices[$a]) &&
                    $tempCart1[$d]['Price'] ==
                        $prices[$a] &&
                    in_array(
                        $tempCart1[$d]['SKU'],
                        $SKUArrValCheck
                    )
                ) {
                    $MatchTotal =
                        $MatchTotal
                        +
                        $prices[$a];
                }
            }

            $CurrentSKU =
                Session::get(
                    'ShoppingCart.Cart.'
                    . $d
                    . '.SKU'
                );

            /*
             * Discount applied.
             */
            if (
                !empty($MatchTotal) &&
                $MatchTotal > 0 &&
                isset(
                    $ItemWiseDiscount[
                        $CurrentSKU
                    ]
                )
            ) {
                Session::put(
                    'ShoppingCart.Cart.'
                    . $d
                    . '.BogoItemWiseDiscout',
                    NumberFormat(
                        $ItemWiseDiscount[
                            $CurrentSKU
                        ]
                    )
                );

                /*
                 * Applied = 1
                 */
                $BogoDiscountMessage =
                    $this->getBogoMessage(
                        $rule,
                        1
                    );

                Session::put(
                    'ShoppingCart.Cart.'
                    . $d
                    . '.BogoDiscountMessage',
                    $BogoDiscountMessage
                );
            }

            /*
             * Eligible SKU but discount not unlocked.
             */
            elseif (
                in_array(
                    $CurrentSKU,
                    $SKUs
                )
            ) {
                /*
                 * Applied = 0
                 */
                $BogoDiscountMessage =
                    $this->getBogoMessage(
                        $rule,
                        0
                    );

                Session::put(
                    'ShoppingCart.Cart.'
                    . $d
                    . '.BogoDiscountMessage',
                    $BogoDiscountMessage
                );
            }
        }
    }

    /**
     * Exact existing SetBogoMessage() behavior.
     */
    protected function getBogoMessage(
        $rule,
        int $applied = 0
    ): string {
        $BogoDiscountMessage =
            '<div class="pdpsuggest-div">';

        $BogoMessage = '';

        $BogoIcon = [
            0 => 'svg-suggestgift',
            1 => 'svg-suggestsales',
            2 => 'svg-suggestsales',
        ];

        $BogoType =
            $rule['type'];

        $Percentage =
            $rule['percentage'] ?? 0;

        $Quantity =
            $rule['quantity'] ?? 0;

        if (
            $applied == 0
        ) {
            if (
                $BogoType == '0'
            ) {
                $BogoMessage =
                    'Buy One Get One Free';
            }
            elseif (
                $BogoType == '1'
            ) {
                $BogoMessage =
                    'You save '
                    . $Percentage
                    . '% on your 2nd bottle';
            }
            elseif (
                $BogoType == '2'
            ) {
                $BogoMessage =
                    'You save '
                    . $Percentage
                    . '% off your '
                    . addOrdinalSuffix(
                        $Quantity + 1
                    )
                    . ' bottle';
            }
        }
        else {
            if (
                $BogoType == '0'
            ) {
                $BogoMessage =
                    'Discount unlocked: 100% off your 2nd bottle!';
            }
            elseif (
                $BogoType == '1'
            ) {
                $BogoMessage =
                    'Discount unlocked: '
                    . $Percentage
                    . '% off your 2nd bottle!';
            }
            elseif (
                $BogoType == '2'
            ) {
                $BogoMessage =
                    'Discount unlocked: '
                    . $Percentage
                    . '% off your '
                    . addOrdinalSuffix(
                        $Quantity + 1
                    )
                    . ' bottles!';
            }
        }

        $DisplayIcon =
            $BogoIcon[$BogoType];

        $BogoDiscountMessage .=
            '
            <svg class="'
            . $DisplayIcon
            . '" aria-hidden="true" role="img"
               width="16" height="16"
               focusable="false">
                <use href="#'
            . $DisplayIcon
            . '" xmlns:xdivnk="http://www.w3.org/1999/xlink"
                   xlink:href="#'
            . $DisplayIcon
            . '"></use>
            </svg>';

        $BogoDiscountMessage .=
            '<span class="pdpsuggest-txt">'
            . $BogoMessage
            . '</span>';

        $BogoDiscountMessage .=
            '</div>';

        return $BogoDiscountMessage;
    }

    /**
     * Clear BOGO values.
     *
     * Equivalent BOGO portion of
     * getAllDiscountBlank("Bogo").
     */
    protected function clearBogoDiscount(): void
    {
        Session::put(
            'ShoppingCart.DogoDiscount',
            0
        );

        Session::put(
            'ShoppingCart.BogoDiscountFlag',
            ''
        );

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
                . '.BogoItemWiseDiscout',
                0
            );

            Session::put(
                'ShoppingCart.Cart.'
                . $index
                . '.BogoDiscountMessage',
                ''
            );
        }
    }

    /**
     * Existing GetAllCoupons('CouponCode') equivalent.
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
     * Existing checkGiftCertificateItem()
     *
     * IMPORTANT:
     * Keep your existing implementation if this helper already
     * exists elsewhere. This service needs the same result:
     * "Yes" / "No".
     */
    protected function checkGiftCertificateItem(
        string $field,
        array $cartItem
    ): string {
        if (
            isset($cartItem[$field])
        ) {
            return
                $cartItem[$field] === 'Yes'
                    ? 'Yes'
                    : 'No';
        }

        /*
         * Existing code treats the item as a normal item
         * when the flag is not present.
         */
        return 'No';
    }
}
