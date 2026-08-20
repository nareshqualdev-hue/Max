<?php

namespace App\Services\Cart;

use Illuminate\Support\Facades\Session;

class ProductNormalizationService
{
    /**
     * Replacement for the existing VendorTrait::SetProduct()
     * behavior used by cart stock/price checks.
     *
     * Existing SetProduct() does three things:
     * 1. Determines system stock.
     * 2. Determines the effective product price.
     * 3. Writes retail_price/current_stock back onto the product.
     *
     * The rules below are taken from the existing VendorTrait.
     */
    public function normalize(
        object $product,
        string $orderType = 'Website'
    ): object {
        if ($orderType === 'Store') {
            $product->stock =
                $this->getSystemStockAvailable(
                    $product,
                    $orderType
                );

            $priceData =
                $this->getSystemStockProductPrice(
                    $product,
                    $orderType
                );

            $product->WebsiteStock =
                $product->stock === 'In'
                    ? 'In'
                    : 'Out';

            $product->product_price =
                $priceData['price'];

            $product->retail_price =
                $priceData['retail_price'];

            $product->current_stock =
                $priceData['current_stock'];

            return $product;
        }

        $product->stock =
            $this->getSystemStockAvailable(
                $product
            );

        $priceData =
            $this->getSystemStockProductPrice(
                $product
            );

        $product->WebsiteStock =
            $this->getSystemWebsiteCurrentStock(
                $product
            );

        $product->product_price =
            $priceData['price'];

        if (
            $product->product_price
            >
            ($product->retail_price ?? 0)
        ) {
            $product->retail_price =
                $product->product_price;
        }

        $product->retail_price =
            $priceData['retail_price'];

        $product->current_stock =
            $priceData['current_stock'];

        return $product;
    }

    /**
     * Existing getSystemStockAvalilable() behavior.
     */
    public function getSystemStockAvailable(
        object $product,
        string $orderType = 'Website'
    ): string {
        if ($orderType === 'Store') {
            return
                ((float) (
                    $product->store_currentStock ?? 0
                ) > 0)
                    ? 'In'
                    : 'Out';
        }

        $websiteStockAvailable =
            (float) (
                $product->current_stock ?? 0
            )
            >
            (float) (
                $product->minimum_stock ?? 0
            );

        $vendors = [
            [
                'stock' =>
                    $product->cosmo_current_stock ?? 0,
                'sku' =>
                    $product->cosmo_sku ?? '',
            ],
            [
                'stock' =>
                    $product->nandansons_current_stock ?? 0,
                'sku' =>
                    $product->nandansons_sku ?? '',
            ],
            [
                'stock' =>
                    $product->pca_current_stock ?? 0,
                'sku' =>
                    $product->pca_sku ?? '',
            ],
            [
                'stock' =>
                    $product->perfumeworldwide_currentstock
                    ?? 0,
                'sku' =>
                    $product->perfumeworldwide_sku
                    ?? '',
            ],
            [
                'stock' =>
                    $product->nd_current_stock ?? 0,
                'sku' =>
                    $product->nd_sku ?? '',
            ],
        ];

        foreach ($vendors as $vendor) {
            if (
                (float) $vendor['stock'] > 0
                &&
                $vendor['sku'] !== ''
            ) {
                return 'In';
            }
        }

        return $websiteStockAvailable
            ? 'In'
            : 'Out';
    }

    /**
     * Existing getSystemStockProductPrice() behavior:
     *
     * - Store: sale_price/our_price
     * - Website wholesaler: wholesale_price/our_price
     * - Website retailer: sale_price/our_price
     * - If a vendor has valid stock, SKU and price, the cheapest
     *   available vendor price is selected.
     */
    public function getSystemStockProductPrice(
        object $product,
        string $orderType = 'Website'
    ): array {
        /*
         * IMPORTANT:
         * Preserve the exact VendorTrait priority:
         *
         * 1. Store pricing for Store orders.
         * 2. Website own-stock pricing FIRST when
         *    current_stock > minimum_stock.
         * 3. Only when own website stock is NOT available,
         *    select the cheapest eligible vendor.
         * 4. If no vendor is eligible, fall back to the
         *    existing Maxaroma own pricing.
         *
         * Do not move vendor selection before the own-stock
         * check. That would change the existing price behavior.
         */
        $isWholesaler =
            Session::get('eusertype')
            &&
            strtolower(
                (string) Session::get('eusertype')
            ) === 'wholesaler';

        if ($orderType === 'Store') {

            if (
                ($product->sale_price ?? 0) > 0
                &&
                ($product->sale_price ?? 0)
                <
                ($product->our_price ?? 0)
            ) {
                return [
                    'price' =>
                        (float) $product->sale_price,
                    'retail_price' =>
                        (float) (
                            $product->retail_price ?? 0
                        ),
                    'current_stock' =>
                        (float) (
                            $product->current_stock ?? 0
                        ),
                ];
            }

            if (
                ($product->our_price ?? 0) > 0
            ) {
                return [
                    'price' =>
                        (float) $product->our_price,
                    'retail_price' =>
                        (float) (
                            $product->retail_price ?? 0
                        ),
                    'current_stock' =>
                        (float) (
                            $product->current_stock ?? 0
                        ),
                ];
            }

            return [
                'price' => 0,
                'retail_price' =>
                    (float) (
                        $product->retail_price ?? 0
                    ),
                'current_stock' =>
                    (float) (
                        $product->current_stock ?? 0
                    ),
            ];
        }

        /*
         * EXACT OLD BEHAVIOR:
         * If Maxaroma website stock is available,
         * vendor price must NOT override it.
         */
        if (
            (float) ($product->current_stock ?? 0)
            >
            (float) ($product->minimum_stock ?? 0)
        ) {

            if ($isWholesaler) {

                if (
                    ($product->wholesale_price ?? 0) > 0
                ) {
                    return [
                        'price' =>
                            (float) $product->wholesale_price,
                        'retail_price' =>
                            (float) (
                                $product->retail_price ?? 0
                            ),
                        'current_stock' =>
                            (float) (
                                $product->current_stock ?? 0
                            ),
                    ];
                }

                if (
                    ($product->our_price ?? 0) > 0
                ) {
                    return [
                        'price' =>
                            (float) $product->our_price,
                        'retail_price' =>
                            (float) (
                                $product->retail_price ?? 0
                            ),
                        'current_stock' =>
                            (float) (
                                $product->current_stock ?? 0
                            ),
                    ];
                }

            } else {

                if (
                    ($product->sale_price ?? 0) > 0
                    &&
                    ($product->sale_price ?? 0)
                    <
                    ($product->our_price ?? 0)
                ) {
                    return [
                        'price' =>
                            (float) $product->sale_price,
                        'retail_price' =>
                            (float) (
                                $product->retail_price ?? 0
                            ),
                        'current_stock' =>
                            (float) (
                                $product->current_stock ?? 0
                            ),
                    ];
                }

                if (
                    ($product->our_price ?? 0) > 0
                ) {
                    return [
                        'price' =>
                            (float) $product->our_price,
                        'retail_price' =>
                            (float) (
                                $product->retail_price ?? 0
                            ),
                        'current_stock' =>
                            (float) (
                                $product->current_stock ?? 0
                            ),
                    ];
                }
            }
        }

        /*
         * Own website stock is not available.
         * Now use eligible vendor pricing.
         *
         * Vendor order is kept the same as VendorTrait.
         * Selection is by LOWEST eligible price, not by
         * vendor-name priority.
         */
        $vendors = [
            'cosmo' => [
                'sku' =>
                    $product->cosmo_sku ?? '',
                'stock' =>
                    $product->cosmo_current_stock ?? 0,
                'price' =>
                    $isWholesaler
                        ? (
                            $product->cosmo_wholesale_price
                            ?? 0
                        )
                        : (
                            $product->cosmo_our_price
                            ?? 0
                        ),
                'retail_price' =>
                    $product->cosmo_retail_price ?? 0,
            ],

            'pca' => [
                'sku' =>
                    $product->pca_sku ?? '',
                'stock' =>
                    $product->pca_current_stock ?? 0,
                'price' =>
                    $isWholesaler
                        ? (
                            $product->pca_wholesale_price
                            ?? 0
                        )
                        : (
                            $product->pca_our_price
                            ?? 0
                        ),

                /*
                 * VendorTrait uses the main product retail_price
                 * for PCA. Preserve that exact behavior.
                 */
                'retail_price' =>
                    $product->retail_price ?? 0,
            ],

            'nandansons' => [
                'sku' =>
                    $product->nandansons_sku ?? '',
                'stock' =>
                    $product->nandansons_current_stock ?? 0,
                'price' =>
                    $isWholesaler
                        ? (
                            $product->nandansons_wholesale_price
                            ?? 0
                        )
                        : (
                            $product->nandansons_our_price
                            ?? 0
                        ),

                /*
                 * VendorTrait uses the main product retail_price
                 * for Nandansons. Preserve that exact behavior.
                 */
                'retail_price' =>
                    $product->retail_price ?? 0,
            ],

            'nd' => [
                'sku' =>
                    $product->nd_sku ?? '',
                'stock' =>
                    $product->nd_current_stock ?? 0,
                'price' =>
                    $isWholesaler
                        ? (
                            $product->nd_wholesale_price
                            ?? 0
                        )
                        : (
                            $product->nd_our_price
                            ?? 0
                        ),
                'retail_price' =>
                    $product->nd_retail_price ?? 0,
            ],

            'perfumeworldwide' => [
                'sku' =>
                    $product->perfumeworldwide_sku
                    ?? '',
                'stock' =>
                    $product->perfumeworldwide_currentstock
                    ?? 0,
                'price' =>
                    $isWholesaler
                        ? (
                            $product->perfumeworldwide_wholesale_price
                            ?? 0
                        )
                        : (
                            $product->perfumeworldwide_our_price
                            ?? 0
                        ),
                'retail_price' =>
                    $product->perfumeworldwide_retail_price
                    ?? 0,
            ],
        ];

        $availableVendors =
            array_filter(
                $vendors,
                static function (array $vendor): bool {
                    return
                        $vendor['sku'] !== ''
                        &&
                        (float) $vendor['stock'] > 0
                        &&
                        (float) $vendor['price'] > 0;
                }
            );

        if (!empty($availableVendors)) {

            uasort(
                $availableVendors,
                static function (
                    array $a,
                    array $b
                ): int {
                    return
                        (float) $a['price']
                        <=>
                        (float) $b['price'];
                }
            );

            $bestVendor =
                reset($availableVendors);

            return [
                'price' =>
                    (float) $bestVendor['price'],
                'retail_price' =>
                    (float) $bestVendor['retail_price'],
                'current_stock' =>
                    (float) $bestVendor['stock'],
            ];
        }

        /*
         * EXACT OLD FALLBACK:
         * If no eligible vendor is found, use Maxaroma
         * own pricing even though website stock is not
         * currently above minimum.
         */
        if ($isWholesaler) {

            if (
                ($product->wholesale_price ?? 0) > 0
            ) {
                return [
                    'price' =>
                        (float) $product->wholesale_price,
                    'retail_price' =>
                        (float) (
                            $product->retail_price ?? 0
                        ),
                    'current_stock' =>
                        (float) (
                            $product->current_stock ?? 0
                        ),
                ];
            }

            if (
                ($product->our_price ?? 0) > 0
            ) {
                return [
                    'price' =>
                        (float) $product->our_price,
                    'retail_price' =>
                        (float) (
                            $product->retail_price ?? 0
                        ),
                    'current_stock' =>
                        (float) (
                            $product->current_stock ?? 0
                        ),
                ];
            }

        } else {

            if (
                ($product->sale_price ?? 0) > 0
                &&
                ($product->sale_price ?? 0)
                <
                ($product->our_price ?? 0)
            ) {
                return [
                    'price' =>
                        (float) $product->sale_price,
                    'retail_price' =>
                        (float) (
                            $product->retail_price ?? 0
                        ),
                    'current_stock' =>
                        (float) (
                            $product->current_stock ?? 0
                        ),
                ];
            }

            if (
                ($product->our_price ?? 0) > 0
            ) {
                return [
                    'price' =>
                        (float) $product->our_price,
                    'retail_price' =>
                        (float) (
                            $product->retail_price ?? 0
                        ),
                    'current_stock' =>
                        (float) (
                            $product->current_stock ?? 0
                        ),
                ];
            }
        }

        return [
            'price' => 0,
            'retail_price' =>
                (float) (
                    $product->retail_price ?? 0
                ),
            'current_stock' =>
                (float) (
                    $product->current_stock ?? 0
                ),
        ];
    }

    public function getSystemWebsiteCurrentStock(
        object $product
    ): string {
        $currentStock =
            (float) (
                $product->current_stock ?? 0
            );

        $minimumStock =
            (float) (
                $product->minimum_stock ?? 0
            );

        return $currentStock > $minimumStock
            ? 'In'
            : 'Out';
    }
}
