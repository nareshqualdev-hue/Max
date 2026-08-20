<?php

namespace App\Services\Discount;

use App\Models\Products;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

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
    public function addSample($productsId): string
    {
        $outOfStockMessage = '';
        $skuList = '';

        $log = [
            'products_id' => $productsId,
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
            !Session::has('ShoppingCart.Cart')
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
         * IMPORTANT:
         * The old duplicate-check block was commented out.
         * We preserve that behavior.
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
         *
         * Exact existing query:
         *
         * Products::whereIn('products_id', ...)
         *          ->where('status', '=', '1')
         *          ->get()
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
            /*
             * Existing CartTrait calls SetProduct().
             *
             * New architecture should provide the normalized
             * product object from ProductService/Repository.
             *
             * For now the Products model itself is used because
             * all required stock/vendor fields are already present
             * in the returned product record in the existing flow.
             */
            $freeSample =
                $this->prepareProduct(
                    $product
                );

            /*
             * -----------------------------------------------------
             * Stock validation.
             *
             * Exact existing condition:
             *
             * current_stock > 0
             *
             * OR vendor stock + vendor SKU available.
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
             *
             * Existing priority:
             *
             * Cosmo
             * PCA
             * Nandansons
             * Perfume Worldwide
             * ND
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
             *
             * Existing code uses:
             *
             * pu_category
             *     JOIN pu_products_category
             *     JOIN pu_products
             *
             * We keep direct query because only category information
             * required for the cart item is fetched.
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
             * Build exact Free Sample cart item.
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

            /*
             * Existing code calls CalculateSubTotal()
             * immediately after each sample.
             *
             * We intentionally do NOT duplicate that calculation
             * here. New checkout flow will recalculate totals through
             * the Cart/Checkout totals service after cart mutation.
             */
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
         *
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
     * Normalize the product object.
     *
     * In the current legacy flow SetProduct() adds normalized
     * product fields. This method keeps the service boundary ready
     * for the ProductService migration.
     */
    protected function prepareProduct($product)
    {
        return $product;
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
}