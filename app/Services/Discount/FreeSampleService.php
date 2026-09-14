<?php

namespace App\Services\Discount;

use App\Models\Products;
use App\Models\FreeVialSampleProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Services\Cart\ProductNormalizationService;
use Illuminate\Support\Facades\Log;

class FreeSampleService
{
    /**
     * Add free sample product(s) to cart.
     *
     * Migration of:
     * CartTrait::FreeSampleInsertProductValue()
     *
     * Existing behavior:
     * - Remove existing free samples first.
     * - Add only active products.
     * - Check website/vendor stock.
     * - Vendor fallback:
     *      Cosmo
     *      PCA
     *      Nandansons
     *      Perfume Worldwide
     *      ND
     * - Price = 0
     * - Qty = 1
     * - SKU = SAMPLE-{SKU}
     */
    protected ProductNormalizationService $productNormalizationService;

	public function __construct(
		ProductNormalizationService $productNormalizationService
	) {
		$this->productNormalizationService =
			$productNormalizationService;
	} 
  
 public function addSample(
    $productsId,
    $freeSampleRuleStart = null,
    $freeSampleRuleEnd = null
): string {
    /*
     * ---------------------------------------------------------
     * IMPORTANT:
     *
     * freeSampleAdd() currently calls addSample($productsId)
     * without passing the rule.
     *
     * freeSamplePopup() stores the current rule in:
     *
     * ShoppingCart.FreeSamplePendingRule
     *
     * So pull that rule here and store it with every Free Sample
     * cart item.
     * ---------------------------------------------------------
     */
    $pendingRule = Session::pull(
        'ShoppingCart.FreeSamplePendingRule'
    );

    if (
        $freeSampleRuleStart === null
        &&
        isset($pendingRule['start'])
    ) {
        $freeSampleRuleStart =
            $pendingRule['start'];
    }

    if (
        $freeSampleRuleEnd === null
        &&
        isset($pendingRule['end'])
    ) {
        $freeSampleRuleEnd =
            $pendingRule['end'];
    }

    $outOfStockMessage = '';
    $skuList = '';

    $log = [
        'products_id' =>
            $productsId,

        'free_sample_rule_start' =>
            $freeSampleRuleStart,

        'free_sample_rule_end' =>
            $freeSampleRuleEnd,
    ];

    addLog(
        'FreeSampleInsertProductValueStart',
        $log
    );

    /*
     * ---------------------------------------------------------
     * Existing behavior:
     * Method works only when cart exists and has items.
     * ---------------------------------------------------------
     */
    if (
        !Session::has(
            'ShoppingCart.Cart'
        )
        ||
        count(
            Session::get(
                'ShoppingCart.Cart',
                []
            )
        ) <= 0
    ) {
        addLog(
            'FreeSampleInsertProductValue'
        );

        return $outOfStockMessage;
    }

    /*
     * ---------------------------------------------------------
     * Get current cart.
     * ---------------------------------------------------------
     */
    $cart =
        array_values(
            Session::get(
                'ShoppingCart.Cart',
                []
            )
        );

    /*
     * ---------------------------------------------------------
     * Existing Free Sample products are removed first.
     *
     * This preserves existing behavior.
     * ---------------------------------------------------------
     */
    foreach (
        $cart as $index => $cartItem
    ) {
        if (
            isset(
                $cartItem['Is_Free_Sample']
            )
            &&
            $cartItem['Is_Free_Sample']
                === 'Yes'
        ) {
            addLog(
                'FreeSampleUnset'
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

    $freeSampleAdd = 'No';

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
        addLog(
            'FreeSampleInsertProductValue'
        );

        return $outOfStockMessage;
    }

    /*
     * ---------------------------------------------------------
     * Get active products.
     * ---------------------------------------------------------
     */
    $freeSampleProducts =
        Products::whereIn(
            'products_id',
            $productIds
        )
        ->where(
            'status',
            '=',
            '1'
        )
        ->get();

    $totalFreeSample =
        count(
            $freeSampleProducts
        );

    if (
        $totalFreeSample <= 0
    ) {
        addLog(
            'FreeSampleInsertProductValue'
        );

        return $outOfStockMessage;
    }

    /*
     * ---------------------------------------------------------
     * Process each requested sample.
     * ---------------------------------------------------------
     */
    foreach (
        $freeSampleProducts as $product
    ) {
        $freeSample =
            $this->prepareProduct(
                $product
            );

        /*
         * -----------------------------------------------------
         * Stock validation.
         * -----------------------------------------------------
         */
        if (
            !$this->hasAvailableStock(
                $freeSample
            )
        ) {
            $skuList .=
                ($freeSample->sku ?? '')
                . ',';

            continue;
        }

        /*
         * -----------------------------------------------------
         * Product image preparation.
         * -----------------------------------------------------
         */
        $this->prepareImages(
            $freeSample
        );

        /*
         * -----------------------------------------------------
         * Vendor selection.
         * -----------------------------------------------------
         */
        $vendor =
            $this->resolveVendor(
                $freeSample
            );

        /*
         * -----------------------------------------------------
         * Maxaroma 2-day delivery.
         * -----------------------------------------------------
         */
        $cartItem = [];

        if (
            ($freeSample->WebsiteStock ?? '')
                === 'In'
        ) {
            $cartItem[
                'IsMaxaromaTwoDelivery'
            ] =
                $freeSample->maxtwodaydelivery;
        }

        /*
         * -----------------------------------------------------
         * Category.
         * -----------------------------------------------------
         */
        $category =
            $this->getCategory(
                $freeSample->products_id
            );

        $categoryId =
            $category['category_id'];

        /*
         * -----------------------------------------------------
         * Clean product text.
         * -----------------------------------------------------
         */
        $productName =
            remove_html_entities(
                $freeSample->product_name
            );

        $shortDescription =
            remove_html_entities(
                $freeSample->short_description
            );

        /*
         * -----------------------------------------------------
         * Build Free Sample cart item.
         * -----------------------------------------------------
         */
        $cartItem['ProductID'] =
            $freeSample->products_id;

        $cartItem['CategoryID'] =
            $categoryId;

        $cartItem['SKU'] =
            'SAMPLE-'
            . $freeSample->sku;

        $cartItem['ORGSAMPLESKU'] =
            $freeSample->sku;

        $cartItem['OrderType'] =
            'Website';

        $cartItem['ProductName'] =
            stripslashes(
                str_ireplace(
                    [
                        "\r",
                        "\n",
                        '\r',
                        '\n',
                    ],
                    '',
                    $productName
                )
            );

        $cartItem['short_description'] =
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
                        $shortDescription
                    )
                )
            );

        $cartItem['Billing_Image'] =
            $freeSample->billing_image;

        /*
         * Free Sample is always free.
         */
        $cartItem['Price'] =
            0;

        $cartItem['Qty'] =
            1;

        $cartItem['TotPrice'] =
            0;

        $cartItem['Image'] =
            $freeSample->prod_image;

        $cartItem['Prod_URL'] =
            '';

        $cartItem['Is_Free_Sample'] =
            'Yes';

        $cartItem['image_forpopup'] =
            $freeSample->image_forpopup;

        $cartItem['freesampleproductsid'] =
            $freeSample->products_id;

        /*
         * -----------------------------------------------------
         * Store the exact Free Sample rule used when the sample
         * was selected.
         *
         * Example:
         *
         * 201 - 300
         *
         * This is later compared with the newly calculated rule.
         * -----------------------------------------------------
         */
        $cartItem['FreeSampleRuleStart'] =
            $freeSampleRuleStart !== null
                ? (float) $freeSampleRuleStart
                : null;

        $cartItem['FreeSampleRuleEnd'] =
            $freeSampleRuleEnd !== null
                ? (float) $freeSampleRuleEnd
                : null;

        /*
         * Vendor information.
         */
        $cartItem['VendorSKU'] =
            $vendor['VendorSKU'];

        $cartItem['IsCosmo'] =
            $vendor['IsCosmo'];

        $cartItem['IsNandansons'] =
            $vendor['IsNandansons'];

        $cartItem['IsPerfumePW'] =
            $vendor['IsPerfumePW'];

        $cartItem['IsPCA'] =
            $vendor['IsPCA'];

        $cartItem['IsND'] =
            $vendor['IsND'];

        $cartItem['ImanufactureID'] =
            $freeSample->imanufactureid;

        /*
         * Free Sample is never a deal product.
         */
        $cartItem['IsDealProducts'] =
            'No';

        $cartItem['DealDiscountFlag'] =
            'No';

        $cartItem['dealdiscount_flag'] =
            'No';

        $cartItem['manufactureName'] =
            '';

        $cartItem['CategoryName'] =
            '';

        $cartItem['FinalSale'] =
            '';

        /*
         * -----------------------------------------------------
         * Add sample to cart.
         * -----------------------------------------------------
         */
        $cart[] =
            $cartItem;

        $freeSampleAdd =
            'Yes';
    }

    /*
     * ---------------------------------------------------------
     * Out of stock message.
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
            'FreeSampleInsertProductValueOutofStock',
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
     * Existing behavior:
     * If at least one sample was successfully added,
     * clear the out-of-stock message and save cart.
     * ---------------------------------------------------------
     */
    if (
        count($cart) > 0
        &&
        $freeSampleAdd === 'Yes'
    ) {
        $outOfStockMessage = '';

        Session::put(
            'ShoppingCart.Cart',
            array_values(
                $cart
            )
        );
    }

    addLog(
        'FreeSampleInsertProductValue'
    );

    return $outOfStockMessage;
}
 
    /**
     * Get eligible free-sample products for the existing Free Sample popup.
     *
     * Migration of:
     * ShoppingcartController::getSampleProductsPopup()
     *
     * IMPORTANT:
     * - Existing price/date/status rules are preserved.
     * - Gift Certificate total is deducted exactly as legacy checkout.
     * - Configured sample SKUs are preserved.
     * - Additional random sample logic is preserved.
     * - Cart ORGSAMPLESKU products are prioritised.
     * - Returned array keys match the existing freesample-popup.blade.php.
     */
public function getSampleProductsPopup(
    $totalValue,
    $totalFreeSampleItems = 0
): array {
    $productArr = [];

    /*
     * ---------------------------------------------------------
     * Legacy behavior:
     * If cart contains only Gift Certificate purchase,
     * Free Samples are not available.
     * ---------------------------------------------------------
     */
    if (
        (int) $this->getCartAttribute('onlyGCPurchased') === 1
    ) {
        return [];
    }

    /*
     * Gift Certificate is already excluded from $totalValue
     * in CheckoutCartController::freeSamplePopup().
     *
     * Do not subtract GiftCertiTotal again here.
     */
    $totalValue = (float) $totalValue;

    $cart = Session::get(
        'ShoppingCart.Cart',
        []
    );

    if (empty($cart)) {
        return [];
    }

    $today = Carbon::today()->toDateString();

    /*
     * ---------------------------------------------------------
     * DEBUG:
     * Show all active Free Sample rules available today.
     * ---------------------------------------------------------
     */
    $activeRules =
        FreeVialSampleProduct::where(
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
        ->orderBy(
            'price_start_range',
            'asc'
        )
        ->get([
            'price_start_range',
            'price_end_range',
            'sku',
            'customer_choice',
            'total_samples',
            'status',
            'start_date',
            'end_date',
        ]);

    Log::info(
        'FREE_SAMPLE_ACTIVE_RULES',
        [
            'today' =>
                $today,

            'totalValue' =>
                $totalValue,

            'rules' =>
                $activeRules->toArray(),
        ]
    );

    /*
     * ---------------------------------------------------------
     * Find CURRENT applicable rule.
     *
     * First:
     *     start <= totalValue <= end
     *
     * If no exact range:
     *     fallback to highest start <= totalValue.
     * ---------------------------------------------------------
     */
    $matchingSamples =
        FreeVialSampleProduct::where(
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
            'price_start_range',
            '<=',
            $totalValue
        )
        ->where(
            'price_end_range',
            '>=',
            $totalValue
        )
        ->orderByDesc(
            'price_start_range'
        )
        ->first();

    /*
     * ---------------------------------------------------------
     * No exact range.
     *
     * Preserve existing fallback behavior.
     * ---------------------------------------------------------
     */
    if (!$matchingSamples) {

        $matchingSamples =
            FreeVialSampleProduct::where(
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
                'price_start_range',
                '<=',
                $totalValue
            )
            ->orderByDesc(
                'price_start_range'
            )
            ->first();
    }

    /*
     * ---------------------------------------------------------
     * Rule debug.
     * ---------------------------------------------------------
     */
    Log::info(
        'FREE_SAMPLE_RULE_DEBUG',
        [
            'totalValue' =>
                $totalValue,

            'matched_start' =>
                $matchingSamples->price_start_range
                ?? null,

            'matched_end' =>
                $matchingSamples->price_end_range
                ?? null,

            'matched_sku' =>
                $matchingSamples->sku
                ?? null,

            'customer_choice' =>
                $matchingSamples->customer_choice
                ?? null,
        ]
    );

    if (!$matchingSamples) {

        Log::info(
            'FreeSamplePopupNoRule',
            [
                'totalValue' =>
                    $totalValue,

                'today' =>
                    $today,
            ]
        );

        return [];
    }

    Log::info(
        'FreeSamplePopupMatchedRule',
        [
            'totalValue' =>
                $totalValue,

            'today' =>
                $today,

            'price_start_range' =>
                $matchingSamples->price_start_range
                ?? null,

            'price_end_range' =>
                $matchingSamples->price_end_range
                ?? null,

            'sku' =>
                $matchingSamples->sku
                ?? '',

            'customer_choice' =>
                $matchingSamples->customer_choice
                ?? null,

            'total_samples' =>
                $matchingSamples->total_samples
                ?? null,
        ]
    );

    /*
     * ---------------------------------------------------------
     * Rule SKU validation.
     * ---------------------------------------------------------
     */
    if (
        trim(
            (string) $matchingSamples->sku
        ) === ''
    ) {

        addLog(
            'FreeSamplePopupEmptyRuleSku',
            [
                'totalValue' =>
                    $totalValue,

                'price_start_range' =>
                    $matchingSamples->price_start_range
                    ?? null,

                'price_end_range' =>
                    $matchingSamples->price_end_range
                    ?? null,
            ]
        );

        return [];
    }

    /*
     * ---------------------------------------------------------
     * Configured sample SKUs.
     * ---------------------------------------------------------
     */
    $sampleProducts =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            '#',
                            (string) $matchingSamples->sku
                        )
                    ),
                    'strlen'
                )
            )
        );

    if (empty($sampleProducts)) {
        return [];
    }

    Log::info(
        'FREE_SAMPLE_CONFIGURED_SKUS',
        [
            'totalValue' =>
                $totalValue,

            'ruleStart' =>
                $matchingSamples->price_start_range
                ?? null,

            'ruleEnd' =>
                $matchingSamples->price_end_range
                ?? null,

            'configuredSkus' =>
                $sampleProducts,
        ]
    );

    $totalConfiguredFreeSamples =
        (int) $matchingSamples->total_samples;

    /*
     * ---------------------------------------------------------
     * Existing Free Sample SKUs in cart.
     *
     * These are preferred when selecting fallback/random
     * products, preserving legacy behavior.
     * ---------------------------------------------------------
     */
    $skuIdsVal =
        array_values(
            array_filter(
                array_column(
                    $cart,
                    'ORGSAMPLESKU'
                ),
                'strlen'
            )
        );

    /*
     * ---------------------------------------------------------
     * STEP 1:
     *
     * Load configured products.
     *
     * IMPORTANT:
     *
     * Do NOT decide final popup count here.
     *
     * Every configured product must pass the SAME final
     * isPopupProductInStock() validation.
     * ---------------------------------------------------------
     */
    $configuredProducts =
        Products::from(
            'pu_products as po'
        )
        ->join(
            'pu_products_category as pc',
            'po.products_id',
            '=',
            'pc.products_id'
        )
        ->join(
            'pu_category as c',
            'pc.category_id',
            '=',
            'c.category_id'
        )
        ->join(
            'pu_brand as b',
            'b.brand_id',
            '=',
            'po.brand_id'
        )
        ->join(
            'pu_manufacture as m',
            function ($join) {
                $join->on(
                    'po.imanufactureid',
                    '=',
                    'm.imanufactureid'
                )->on(
                    'b.imanufactureid',
                    '=',
                    'm.imanufactureid'
                );
            }
        )
        ->where(
            'po.status',
            '1'
        )
        ->where(
            'c.status',
            '1'
        )
        ->where(
            'po.current_stock',
            '>',
            0
        )
        ->whereIn(
            'po.product_type',
            [
                'both',
                'retailer',
            ]
        )
        ->whereIn(
            'po.sku',
            $sampleProducts
        )
        ->select(
            'po.*'
        )
        ->groupBy(
            'po.products_id'
        )
        ->get();

    /*
     * ---------------------------------------------------------
     * STEP 2:
     *
     * Validate configured products BEFORE counting them.
     *
     * This is the important fix.
     * ---------------------------------------------------------
     */
    $validConfiguredProducts = collect();

    foreach (
        $configuredProducts as $product
    ) {

        $preparedProduct =
            $this->prepareProduct(
                $product
            );

        if (
            !$this->isPopupProductInStock(
                $preparedProduct
            )
        ) {

            Log::info(
                'FREE_SAMPLE_CONFIGURED_PRODUCT_REJECTED',
                [
                    'sku' =>
                        $product->sku
                        ?? null,

                    'products_id' =>
                        $product->products_id
                        ?? null,

                    'reason' =>
                        'isPopupProductInStock failed',
                ]
            );

            continue;
        }

        $validConfiguredProducts->push(
            $preparedProduct
        );
    }

    Log::info(
        'FREE_SAMPLE_VALID_CONFIGURED_PRODUCTS',
        [
            'configuredSkuCount' =>
                count($sampleProducts),

            'validConfiguredCount' =>
                $validConfiguredProducts->count(),

            'requiredSamples' =>
                $totalConfiguredFreeSamples,
        ]
    );

    /*
     * ---------------------------------------------------------
     * STEP 3:
     *
     * Determine how many valid products we need.
     *
     * Example:
     *
     * Rule total_samples = 9
     *
     * Configured = 3
     * Valid configured = 2
     *
     * Then we need:
     *
     * 9 - 2 = 7 fallback products
     *
     * NOT:
     *
     * 9 - 3 = 6
     *
     * This is the core fix.
     * ---------------------------------------------------------
     */
    $validConfiguredCount =
        $validConfiguredProducts->count();

    $requiredAdditionalProducts =
        max(
            0,
            $totalConfiguredFreeSamples
            - $validConfiguredCount
        );

    /*
     * ---------------------------------------------------------
     * STEP 4:
     *
     * Prepare valid configured products.
     *
     * If configured valid products are more than the rule
     * target, respect total_samples.
     * ---------------------------------------------------------
     */
    $selectedProducts =
        $validConfiguredProducts
            ->shuffle()
            ->take(
                $totalConfiguredFreeSamples
            )
            ->values();

    /*
     * ---------------------------------------------------------
     * STEP 5:
     *
     * If configured valid products are not enough, find
     * additional random candidates.
     *
     * IMPORTANT:
     *
     * We load candidates FIRST.
     * Then validate every candidate using
     * isPopupProductInStock().
     *
     * We keep going until the required number of VALID
     * products has been collected.
     *
     * This prevents:
     *
     * 9 candidates
     * -> 1 invalid
     * -> 8 displayed
     *
     * ---------------------------------------------------------
     */
    $currentSelectedCount =
        $selectedProducts->count();

    $requiredAdditionalProducts =
        max(
            0,
            $totalConfiguredFreeSamples
            - $currentSelectedCount
        );

    if (
        $requiredAdditionalProducts > 0
    ) {

        /*
         * Legacy static category list.
         */
        $childCatArr = [
            252,
            253,
            254,
            255,
        ];

        /*
         * -----------------------------------------------------
         * Get random fallback candidates.
         *
         * Do not use ->take($requiredAdditionalProducts)
         * before stock validation.
         *
         * We need enough candidates to find the required
         * number of VALID products.
         * -----------------------------------------------------
         */
        $randomProducts =
            DB::table(
                'pu_products as po'
            )
            ->join(
                'pu_products_category as pc',
                'po.products_id',
                '=',
                'pc.products_id'
            )
            ->join(
                'pu_category as c',
                'pc.category_id',
                '=',
                'c.category_id'
            )
            ->join(
                'pu_brand as b',
                'b.brand_id',
                '=',
                'po.brand_id'
            )
            ->join(
                'pu_manufacture as m',
                function ($join) {
                    $join->on(
                        'po.imanufactureid',
                        '=',
                        'm.imanufactureid'
                    )->on(
                        'b.imanufactureid',
                        '=',
                        'm.imanufactureid'
                    );
                }
            )
            ->where(
                'po.status',
                '1'
            )
            ->where(
                'c.status',
                '1'
            )
            ->where(
                'po.current_stock',
                '>',
                0
            )
            ->whereIn(
                'po.product_type',
                [
                    'both',
                    'retailer',
                ]
            )
            ->whereIn(
                'pc.category_id',
                $childCatArr
            )
            ->whereNotIn(
                'po.sku',
                $sampleProducts
            )
            ->select(
                'po.*'
            )
            ->get();

        /*
         * Shuffle in PHP.
         *
         * This gives us a random candidate order while allowing
         * us to validate candidates one-by-one until the required
         * valid count is reached.
         */
        $randomProducts =
            $randomProducts->shuffle();

        /*
         * -----------------------------------------------------
         * Cart sample SKUs should be preferred.
         *
         * -----------------------------------------------------
         */
        $cartSkuProducts =
            $randomProducts->filter(
                function ($product) use ($skuIdsVal) {
                    return in_array(
                        $product->sku ?? '',
                        $skuIdsVal,
                        true
                    );
                }
            );

        $otherRandomProducts =
            $randomProducts->reject(
                function ($product) use ($skuIdsVal) {
                    return in_array(
                        $product->sku ?? '',
                        $skuIdsVal,
                        true
                    );
                }
            );

        /*
         * Cart products first, then random products.
         */
        $randomCandidates =
            $cartSkuProducts
                ->merge(
                    $otherRandomProducts
                );

        /*
         * -----------------------------------------------------
         * Validate fallback candidates one by one.
         *
         * DO NOT stop after selecting N candidates.
         *
         * Stop only after N VALID products have been found.
         * -----------------------------------------------------
         */
        $selectedSkuMap = [];

        foreach (
            $selectedProducts as $selectedProduct
        ) {
            $selectedSkuMap[
                (string) ($selectedProduct->sku ?? '')
            ] = true;
        }

        $validRandomCount = 0;

        foreach (
            $randomCandidates as $product
        ) {

            if (
                $validRandomCount >=
                $requiredAdditionalProducts
            ) {
                break;
            }

            $candidateSku =
                (string) (
                    $product->sku
                    ?? ''
                );

            /*
             * Never duplicate a SKU.
             */
            if (
                $candidateSku === ''
                ||
                isset(
                    $selectedSkuMap[
                        $candidateSku
                    ]
                )
            ) {
                continue;
            }

            $preparedProduct =
                $this->prepareProduct(
                    $product
                );

            /*
             * IMPORTANT:
             *
             * This is the final popup stock validation.
             */
            if (
                !$this->isPopupProductInStock(
                    $preparedProduct
                )
            ) {

                Log::info(
                    'FREE_SAMPLE_RANDOM_PRODUCT_REJECTED',
                    [
                        'sku' =>
                            $candidateSku,

                        'products_id' =>
                            $product->products_id
                            ?? null,

                        'reason' =>
                            'isPopupProductInStock failed',
                    ]
                );

                continue;
            }

            /*
             * Valid product.
             */
            $selectedProducts->push(
                $preparedProduct
            );

            $selectedSkuMap[
                $candidateSku
            ] = true;

            $validRandomCount++;
        }

        Log::info(
            'FREE_SAMPLE_RANDOM_VALIDATION_RESULT',
            [
                'requiredAdditionalProducts' =>
                    $requiredAdditionalProducts,

                'validRandomCount' =>
                    $validRandomCount,

                'selectedProductCount' =>
                    $selectedProducts->count(),

                'requiredTotalSamples' =>
                    $totalConfiguredFreeSamples,
            ]
        );
    }

    /*
     * ---------------------------------------------------------
     * STEP 6:
     *
     * Build final popup array.
     *
     * All products in $selectedProducts have already passed
     * isPopupProductInStock().
     * ---------------------------------------------------------
     */
    foreach (
        $selectedProducts as $product
    ) {

        $this->prepareImages(
            $product
        );

        $productsId =
            $product->products_id;

        $productName =
            $product->product_name;

        $sku =
            $product->sku;

        $shortDescription =
            strip_tags(
                $product->short_description
                ?? ''
            );

        $thumbImage =
            $this->getThumbImage(
                $product->image
                ?? ''
            );

        $foundSku =
            in_array(
                $sku,
                $skuIdsVal,
                true
            )
                ? 'Yes'
                : 'No';

        /*
         * -----------------------------------------------------
         * Store current rule metadata.
         * -----------------------------------------------------
         */
        $productArr[] = [
            'products_id' =>
                $productsId,

            'product_name' =>
                $productName,

            'sku' =>
                $sku,

            'thumb_image' =>
                $thumbImage,

            'short_description' =>
                $shortDescription,

            'customer_choice' =>
                $matchingSamples->customer_choice,

            'FoundSku' =>
                $foundSku,

            'free_sample_rule_start' =>
                (float)
                    $matchingSamples->price_start_range,

            'free_sample_rule_end' =>
                (float)
                    $matchingSamples->price_end_range,
        ];
    }

    /*
     * ---------------------------------------------------------
     * Final debug.
     * ---------------------------------------------------------
     */
    Log::info(
        'FREE_SAMPLE_FINAL_PRODUCTS',
        [
            'totalValue' =>
                $totalValue,

            'ruleStart' =>
                $matchingSamples->price_start_range
                ?? null,

            'ruleEnd' =>
                $matchingSamples->price_end_range
                ?? null,

            'ruleSku' =>
                $matchingSamples->sku
                ?? null,

            'customerChoice' =>
                $matchingSamples->customer_choice
                ?? null,

            'configuredSkuCount' =>
                count($sampleProducts),

            'validConfiguredCount' =>
                $validConfiguredCount,

            'totalSamples' =>
                $totalConfiguredFreeSamples,

            'finalProductCount' =>
                count($productArr),

            'products' =>
                array_map(
                    function ($product) {
                        return [
                            'products_id' =>
                                $product['products_id']
                                ?? null,

                            'sku' =>
                                $product['sku']
                                ?? null,

                            'name' =>
                                $product['product_name']
                                ?? null,

                            'FoundSku' =>
                                $product['FoundSku']
                                ?? null,

                            'free_sample_rule_start' =>
                                $product[
                                    'free_sample_rule_start'
                                ]
                                ?? null,

                            'free_sample_rule_end' =>
                                $product[
                                    'free_sample_rule_end'
                                ]
                                ?? null,
                        ];
                    },
                    $productArr
                ),
        ]
    );

    return $productArr;
}
   /**
     * Return the customer-choice count for the current Free Sample rule.
     *
     * Migration of:
     * ShoppingcartController::getSampleProductsCustomerChoice()
     */
    public function getSampleProductsCustomerChoice(
        $totalValue
    ): ?string {
        if (
            (int) $this->getCartAttribute('onlyGCPurchased') === 1
        ) {
            return null;
        }

        $giftCertiTotal = 0;

        if (
            Session::has('ShoppingCart.GiftCertiTotal')
        ) {
            $giftCertiTotal = NumberFormat(
                Session::get(
                    'ShoppingCart.GiftCertiTotal'
                )
            );
        }

        $totalValue =
            (float) $totalValue
            - (float) $giftCertiTotal;

        if (
            empty(
                Session::get(
                    'ShoppingCart.Cart',
                    []
                )
            )
        ) {
            return null;
        }

        $today =
            Carbon::today()->toDateString();

        $matchingSamples =
            FreeVialSampleProduct::where(
                'status',
                '1'
            )
            ->where(
                'price_start_range',
                '<=',
                $totalValue
            )
            ->where(
                'price_end_range',
                '>=',
                $totalValue
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

        if (
            !$matchingSamples
        ) {
            return '0';
        }

        return (string) $matchingSamples->customer_choice;
    }

    /**
     * Remove all Free Sample cart lines.
     *
     * Migration of:
     * ShoppingcartController::removeSampleItemsFromCart()
     *
     * Cart totals are intentionally not calculated here.
     * New Checkout refresh/totals flow owns recalculation.
     */
   public function removeSamples(): void
{
    $cart = array_values(
        Session::get(
            'ShoppingCart.Cart',
            []
        )
    );

    Log::info('removeSamples BEFORE', [
        'cart' => $cart,
    ]);

    if (empty($cart)) {
        return;
    }

    $cart = array_values(
        array_filter(
            $cart,
            function ($cartItem) {
                return !(
                    isset($cartItem['Is_Free_Sample'])
                    &&
                    $cartItem['Is_Free_Sample'] === 'Yes'
                );
            }
        )
    );

    Session::put(
        'ShoppingCart.Cart',
        $cart
    );

    Log::info('removeSamples AFTER', [
        'cart' => Session::get(
            'ShoppingCart.Cart',
            []
        ),
    ]);
}
    /**
     * Read a ShoppingCart attribute safely.
     */
    protected function getCartAttribute(
        string $key,
        $default = null
    ) {
        return Session::get(
            'ShoppingCart.' . $key,
            $default
        );
    }

    /**
     * Popup-specific stock check.
     *
     * The legacy popup called SetProduct() and then checked:
     * stock == Out
     *
     * Current service does not own SetProduct(), so use the stock
     * fields already available on the product record and the same
     * vendor-stock fallback used by addSample().
     */
    protected function isPopupProductInStock(
        $product
    ): bool {
        if (
            isset($product->stock)
        ) {
            return $product->stock !== 'Out';
        }

        return $this->hasAvailableStock(
            $product
        );
    }

    /**
     * Build thumbnail URL used by the old popup.
     */
    protected function getThumbImage(
        string $image
    ): string {
        if (
            !empty($image)
            &&
            file_exists(
                config(
                    'global.PRD_THUMB_IMG_PATH'
                )
                . $image
            )
        ) {
            return
                config(
                    'global.PRD_THUMB_IMG_URL'
                )
                . $image;
        }

        return config(
            'global.NO_IMAGE_THUMB'
        );
    }

    /**
     * Normalize the product object.
     *
     * In the current legacy flow SetProduct() adds normalized
     * product fields. This method keeps the service boundary ready
     * for the ProductService migration.
     */
    protected function prepareProduct($product)
	{
		return $this->productNormalizationService->normalize(
			$product,
			'Website'
		);
	}

    /**
     * Check website/vendor stock.
     *
     * Exact existing stock rule.
     */
    protected function hasAvailableStock(
        $product
    ): bool {
        return
            (
                (float)
                (
                    $product->current_stock
                    ?? 0
                )
                > 0
            )
            ||
            (
                (float)
                (
                    $product->cosmo_current_stock
                    ?? 0
                )
                > 0
                &&
                !empty(
                    $product->cosmo_sku
                )
            )
            ||
            (
                (float)
                (
                    $product->nandansons_current_stock
                    ?? 0
                )
                > 0
                &&
                !empty(
                    $product->nandansons_sku
                )
            )
            ||
            (
                (float)
                (
                    $product->pca_current_stock
                    ?? 0
                )
                > 0
                &&
                !empty(
                    $product->pca_sku
                )
            )
            ||
            (
                (float)
                (
                    $product->perfumeworldwide_currentstock
                    ?? 0
                )
                > 0
                &&
                !empty(
                    $product->perfumeworldwide_sku
                )
            )
            ||
            (
                (float)
                (
                    $product->nd_current_stock
                    ?? 0
                )
                > 0
                &&
                !empty(
                    $product->nd_sku
                )
            );
    }

    /**
     * Resolve vendor in exact existing order.
     *
     * Order:
     * 1. Cosmo
     * 2. PCA
     * 3. Nandansons
     * 4. Perfume Worldwide
     * 5. ND
     */
    protected function resolveVendor(
        $product
    ): array {
        $vendorSku = '';

        $isCosmo = '';
        $isNandansons = '';
        $isPerfumePw = '';
        $isPca = '';
        $isNd = '';

        /*
         * Existing behavior:
         * Vendor is selected only when website stock is Out.
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
                (
                    $product->cosmo_current_stock
                    ?? 0
                )
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
                (
                    $product->pca_current_stock
                    ?? 0
                )
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
                (
                    $product->nandansons_current_stock
                    ?? 0
                )
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
                (
                    $product->perfumeworldwide_currentstock
                    ?? 0
                )
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
                (
                    $product->nd_current_stock
                    ?? 0
                )
                > 0
            ) {
                $isNd =
                    'Yes';

                $vendorSku =
                    $product->nd_sku;
            }
        }

        return [
            'VendorSKU' =>
                $vendorSku,

            'IsCosmo' =>
                $isCosmo,

            'IsNandansons' =>
                $isNandansons,

            'IsPerfumePW' =>
                $isPerfumePw,

            'IsPCA' =>
                $isPca,

            'IsND' =>
                $isNd,
        ];
    }

    /**
     * Prepare product images exactly as existing cart flow.
     */
    protected function prepareImages(
        $product
    ): void {
        if (
            file_exists(
                config(
                    'global.PRD_THUMB_IMG_PATH'
                )
                .
                $product->image
            )
            &&
            !empty(
                $product->image
            )
        ) {
            $thumbImage =
                config(
                    'global.PRD_THUMB_IMG_URL'
                )
                .
                $product->image;
        } else {
            $thumbImage =
                config(
                    'global.NO_IMAGE_THUMB'
                );
        }

        $product->prod_image =
            '<img src="'
            . $thumbImage
            . '" border="0" width="125" />';

        $product->image_forpopup =
            '<img src="'
            . $thumbImage
            . '" border="0" width="75" />';

        $product->billing_image =
            '<img src="'
            . $thumbImage
            . '" border="0" width="195" alt="'
            . e(
                $product->product_name
            )
            . '" title="'
            . e(
                $product->product_name
            )
            . '"/>';
    }

    /**
     * Get the first category used by the existing cart logic.
     *
     * Direct query is intentional.
     * We need the category ID only, so no Eloquent relationship
     * is required here.
     */
    protected function getCategory(
        $productId
    ): array {
        $category =
            DB::table(
                'pu_category as c'
            )
            ->join(
                'pu_products_category as pc',
                'c.category_id',
                '=',
                'pc.category_id'
            )
            ->join(
                'pu_products as p',
                'pc.products_id',
                '=',
                'p.products_id'
            )
            ->where(
                'p.products_id',
                '=',
                $productId
            )
            ->first();

        if (
            !$category
        ) {
            return [
                'category_id' => '0',
                'category_name' => '',
                'breadcrumbs' => '',
            ];
        }

        $breadcrumbs = '';

        $categoryInfo =
            config(
                'CATEGORY_INFO'
            );

        if (
            isset(
                $categoryInfo[
                    'CatForProd'
                ][$category->category_id]
                ['subcatbredcrum']
            )
        ) {
            $breadcrumbs =
                $categoryInfo[
                    'CatForProd'
                ][$category->category_id]
                ['subcatbredcrum'];
        }

        return [
            'category_id' =>
                $category->category_id,

            'category_name' =>
                stripcslashes(
                    $category->category_name
                ),

            'breadcrumbs' =>
                $breadcrumbs,
        ];
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
 * Sync Free Sample items after a cart mutation.
 *
 * This only checks whether the existing Free Sample still belongs
 * to the currently applicable Free Sample rule.
 *
 * @return bool
 */
/**
 * Check whether the existing Free Sample still belongs
 * to the currently applicable Free Sample rule.
 *
 * @return bool
 */
public function syncFreeSamplesAfterCartMutation(
    array $freeSampleItems = []
): bool {

    /*
     * ---------------------------------------------------------
     * Current cart AFTER quantity update + discount
     * recalculation.
     * ---------------------------------------------------------
     */
    $cart = Session::get(
        'ShoppingCart.Cart',
        []
    );

    Log::info(
        'FreeSampleSync START',
        [
            'cart_count' =>
                count($cart),

            'preserved_free_sample_count' =>
                count($freeSampleItems),
        ]
    );

    /*
     * ---------------------------------------------------------
     * Find OLD Free Sample.
     *
     * PRIMARY:
     * Snapshot captured BEFORE cart mutation.
     *
     * FALLBACK:
     * Current cart.
     * ---------------------------------------------------------
     */
    $freeSampleItem = null;

    if (!empty($freeSampleItems)) {

        $freeSampleItem =
            reset($freeSampleItems);

    } else {

        foreach ($cart as $cartItem) {

            if (
                ($cartItem['Is_Free_Sample'] ?? '')
                === 'Yes'
            ) {

                $freeSampleItem =
                    $cartItem;

                break;
            }
        }
    }

    /*
     * ---------------------------------------------------------
     * No OLD Free Sample means there is nothing to sync.
     * ---------------------------------------------------------
     */
    if ($freeSampleItem === null) {

        Log::info(
            'FreeSampleSync STOP: No OLD Free Sample'
        );

        return false;
    }

    /*
     * ---------------------------------------------------------
     * OLD RULE
     *
     * Source of truth:
     * FreeSampleRuleStart / FreeSampleRuleEnd
     * stored on the OLD Free Sample.
     * ---------------------------------------------------------
     */
    $storedRuleStart = null;
    $storedRuleEnd = null;

    if (
        array_key_exists(
            'FreeSampleRuleStart',
            $freeSampleItem
        )
        &&
        array_key_exists(
            'FreeSampleRuleEnd',
            $freeSampleItem
        )
        &&
        $freeSampleItem[
            'FreeSampleRuleStart'
        ] !== null
        &&
        $freeSampleItem[
            'FreeSampleRuleEnd'
        ] !== null
    ) {

        $storedRuleStart =
            (float) $freeSampleItem[
                'FreeSampleRuleStart'
            ];

        $storedRuleEnd =
            (float) $freeSampleItem[
                'FreeSampleRuleEnd'
            ];
    }

    /*
     * ---------------------------------------------------------
     * BACKWARD COMPATIBILITY
     *
     * If OLD rule metadata is missing, identify the rule
     * through the sample SKU only when exactly one active
     * rule contains that SKU.
     * ---------------------------------------------------------
     */
    if (
        $storedRuleStart === null
        ||
        $storedRuleEnd === null
    ) {

        $sampleSku =
            $freeSampleItem[
                'ORGSAMPLESKU'
            ] ?? null;

        if (!$sampleSku) {

            $sampleSku =
                $freeSampleItem[
                    'SKU'
                ] ?? null;
        }

        if (
            $sampleSku
            &&
            strpos(
                $sampleSku,
                'SAMPLE-'
            ) === 0
        ) {

            $sampleSku =
                substr(
                    $sampleSku,
                    7
                );
        }

        if ($sampleSku) {

            $today =
                Carbon::today()->toDateString();

            $activeRules =
                FreeVialSampleProduct::where(
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
                ->get();

            $matchedRules = [];

            foreach (
                $activeRules as $rule
            ) {

                $configuredSkus =
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                '#',
                                (string) $rule->sku
                            )
                        )
                    );

                if (
                    in_array(
                        $sampleSku,
                        $configuredSkus,
                        true
                    )
                ) {

                    $matchedRules[] =
                        $rule;
                }
            }

            /*
             * SKU fallback is safe only when exactly one
             * active rule contains this SKU.
             */
            if (
                count($matchedRules) === 1
            ) {

                $storedRuleStart =
                    (float)
                    $matchedRules[0]
                        ->price_start_range;

                $storedRuleEnd =
                    (float)
                    $matchedRules[0]
                        ->price_end_range;

                Log::info(
                    'FreeSampleSync: Old Rule Found By Unique SKU',
                    [
                        'sampleSku' =>
                            $sampleSku,

                        'oldRuleStart' =>
                            $storedRuleStart,

                        'oldRuleEnd' =>
                            $storedRuleEnd,
                    ]
                );
            }
        }
    }

    /*
     * ---------------------------------------------------------
     * OLD RULE MUST BE KNOWN.
     *
     * Never guess the OLD rule from PendingRule.
     * ---------------------------------------------------------
     */
    if (
        $storedRuleStart === null
        ||
        $storedRuleEnd === null
    ) {

        Log::warning(
            'FreeSampleSync STOP: OLD rule could not be determined',
            [
                'freeSampleItem' =>
                    $freeSampleItem,
            ]
        );

        return false;
    }

    Log::info(
        'FreeSampleSync: OLD RULE',
        [
            'oldRuleStart' =>
                $storedRuleStart,

            'oldRuleEnd' =>
                $storedRuleEnd,
        ]
    );

    /*
     * ---------------------------------------------------------
     * CURRENT FREE SAMPLE ELIGIBILITY
     *
     * IMPORTANT:
     *
     * Use the SAME CheckoutTotalsService calculation
     * used by freeSamplePopup().
     *
     * We resolve it through the Laravel container so
     * FreeSampleService constructor does NOT need a new
     * dependency/property.
     * ---------------------------------------------------------
     */
    $subTotal =
        NumberFormat(
            Session::get(
                'ShoppingCart.SubTotal',
                0
            )
        );

    /*
     * Resolve the existing CheckoutTotalsService.
     *
     * No:
     * $this->checkoutTotalsService
     *
     * No:
     * $this->discountService
     */
    $checkoutTotalsService =
        app(
            \App\Services\Checkout\CheckoutTotalsService::class
        );

    /*
     * Same discount source as freeSamplePopup():
     *
     * CheckoutTotalsService
     *     -> getTotal('discount')
     */
    $totalDiscount =
        (float) $checkoutTotalsService
            ->getTotal('discount');

    $giftCertiTotal =
        NumberFormat(
            Session::get(
                'ShoppingCart.GiftCertiTotal',
                0
            )
        );

    /*
     * Discount total contains Gift Certificate.
     *
     * Remove Gift Certificate from discount first.
     */
    $actualDiscount =
        max(
            0,
            $totalDiscount
            - $giftCertiTotal
        );

    /*
     * IMPORTANT:
     *
     * Do NOT subtract GiftCertiTotal again.
     *
     * Same formula as freeSamplePopup():
     *
     * totalValue =
     *     subTotal - actualDiscount
     */
    $totalValue =
        max(
            0,
            $subTotal
            - $actualDiscount
        );

    Log::info(
        'FreeSampleSync: Eligibility Total',
        [
            'subTotal' =>
                $subTotal,

            'totalDiscount' =>
                $totalDiscount,

            'giftCertiTotal' =>
                $giftCertiTotal,

            'actualDiscount' =>
                $actualDiscount,

            'totalValue' =>
                $totalValue,
        ]
    );

    /*
     * ---------------------------------------------------------
     * CURRENT ACTIVE RULE
     *
     * First try exact matching range.
     * ---------------------------------------------------------
     */
    $today =
        Carbon::today()->toDateString();

    $currentRule =
        FreeVialSampleProduct::where(
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
            'price_start_range',
            '<=',
            $totalValue
        )
        ->where(
            'price_end_range',
            '>=',
            $totalValue
        )
        ->orderByDesc(
            'price_start_range'
        )
        ->first();

    /*
     * ---------------------------------------------------------
     * ABOVE HIGHEST RANGE
     *
     * Example:
     *
     * 100 - 200
     * 201 - 300
     *
     * total = 607
     *
     * Current rule = 201 - 300
     * ---------------------------------------------------------
     */
    if (!$currentRule) {

        $currentRule =
            FreeVialSampleProduct::where(
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
                'price_start_range',
                '<=',
                $totalValue
            )
            ->orderByDesc(
                'price_start_range'
            )
            ->first();
    }

    /*
     * ---------------------------------------------------------
     * CURRENT RULE VALUES
     * ---------------------------------------------------------
     */
    $currentRuleStart = null;
    $currentRuleEnd = null;

    if ($currentRule) {

        $currentRuleStart =
            (float) $currentRule->price_start_range;

        $currentRuleEnd =
            (float) $currentRule->price_end_range;
    }

    Log::info(
        'FreeSampleSync: Current Rule',
        [
            'totalValue' =>
                $totalValue,

            'currentRuleStart' =>
                $currentRuleStart,

            'currentRuleEnd' =>
                $currentRuleEnd,
        ]
    );

    /*
     * ---------------------------------------------------------
     * FINAL RULE COMPARISON
     * ---------------------------------------------------------
     */
    $ruleChanged = false;

    /*
     * OLD + CURRENT rule both exist.
     */
    if (
        $storedRuleStart !== null
        &&
        $storedRuleEnd !== null
        &&
        $currentRuleStart !== null
        &&
        $currentRuleEnd !== null
    ) {

        $ruleChanged =
            (
                $storedRuleStart
                !=
                $currentRuleStart
            )
            ||
            (
                $storedRuleEnd
                !=
                $currentRuleEnd
            );
    }

    /*
     * OLD rule exists but customer is no longer
     * eligible for any Free Sample rule.
     */
    elseif (
        $storedRuleStart !== null
        &&
        $storedRuleEnd !== null
        &&
        (
            $currentRuleStart === null
            ||
            $currentRuleEnd === null
        )
    ) {

        $ruleChanged = true;
    }

    Log::info(
        'FreeSampleSync: FINAL RULE COMPARE',
        [
            'oldRuleStart' =>
                $storedRuleStart,

            'oldRuleEnd' =>
                $storedRuleEnd,

            'currentRuleStart' =>
                $currentRuleStart,

            'currentRuleEnd' =>
                $currentRuleEnd,

            'ruleChanged' =>
                $ruleChanged,
        ]
    );

    /*
     * ---------------------------------------------------------
     * SAME RULE
     * ---------------------------------------------------------
     */
    if (!$ruleChanged) {

        Log::info(
            'FreeSampleSync STOP: RULE UNCHANGED'
        );

        return false;
    }

    /*
     * ---------------------------------------------------------
     * RULE CHANGED
     *
     * Remove ONLY old Free Samples.
     * Normal cart products remain untouched.
     * ---------------------------------------------------------
     */
    Log::info(
        'FreeSampleSync: RULE CHANGED - Removing Old Samples',
        [
            'oldRuleStart' =>
                $storedRuleStart,

            'oldRuleEnd' =>
                $storedRuleEnd,

            'newRuleStart' =>
                $currentRuleStart,

            'newRuleEnd' =>
                $currentRuleEnd,
        ]
    );

    $this->removeSamples();

    Log::info(
        'FreeSampleSync: Cart AFTER removeSamples',
        [
            'cart' =>
                Session::get(
                    'ShoppingCart.Cart',
                    []
                ),
        ]
    );

    /*
     * ---------------------------------------------------------
     * Save NEW rule as pending rule for popup/add flow.
     * ---------------------------------------------------------
     */
    if (
        $currentRuleStart !== null
        &&
        $currentRuleEnd !== null
    ) {

        Session::put(
            'ShoppingCart.FreeSamplePendingRule',
            [
                'start' =>
                    $currentRuleStart,

                'end' =>
                    $currentRuleEnd,
            ]
        );

    } else {

        Session::forget(
            'ShoppingCart.FreeSamplePendingRule'
        );
    }

    /*
     * ---------------------------------------------------------
     * RULE CHANGED = TRUE
     * ---------------------------------------------------------
     */
    return true;
}
}
