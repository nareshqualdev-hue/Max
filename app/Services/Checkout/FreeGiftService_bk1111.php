<?php

namespace App\Services\Discount;

use App\Models\Products;
use App\Services\Cart\CartStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class FreeGiftService
{
    public function __construct(
        protected CartStockService $cartStockService
    ) {
    }

    /**
     * Add/replace a coupon free-gift in the session cart.
     *
     * This preserves the legacy cart fields used by checkout:
     * IS_Free_Gift = Yes
     * FreeGiftCoupon = Yes
     *
     * The value can be a single SKU. If the caller passes a
     * comma-separated value, each SKU is tried in order and the
     * first valid/in-stock product is used.
     */
    public function insertWithCoupon(
        string $productValue
    ): array {
        $productValue = trim($productValue);

        if ($productValue === '') {
            return [
                'success' => false,
                'message' => 'Free gift product is not configured.',
            ];
        }

        $skus = array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(',', $productValue)
                ),
                'strlen'
            )
        );

        foreach ($skus as $sku) {
            $result = $this->addSku($sku);

            if ($result['success'] ?? false) {
                return $result;
            }
        }

        Session::flash(
            'OutOfStockBundle',
            'The Free bundle is out of stock and cannot be added to your order'
        );

        return [
            'success' => false,
            'message' =>
                'The Free bundle is out of stock and cannot be added to your order',
        ];
    }

    /**
     * Remove coupon-generated free gifts only.
     *
     * Do not remove manually selected free gifts or samples.
     */
    public function removeCouponGift(): bool
    {
        $cart = Session::get(
            'ShoppingCart.Cart',
            []
        );

        $cart = array_values(
            array_filter(
                $cart,
                function ($item) {
                    return !(
                        isset($item['FreeGiftCoupon'])
                        &&
                        $item['FreeGiftCoupon'] === 'Yes'
                    );
                }
            )
        );

        Session::put(
            'ShoppingCart.Cart',
            $cart
        );

        return true;
    }

    protected function addSku(string $sku): array
    {
        if ($sku === '') {
            return [
                'success' => false,
            ];
        }

        $product = Products::query()
            ->where('sku', $sku)
            ->where('status', '1')
            ->first();

        if (!$product) {
            return [
                'success' => false,
            ];
        }

        $stock = $this->cartStockService->checkStock(
            (int) $product->products_id,
            1,
            'insert',
            'No',
            'Website'
        );

        if (
            ($stock['StockInfo'] ?? 1111) !== 3333
            || empty($stock['ProdInfo'])
        ) {
            return [
                'success' => false,
            ];
        }

        $product = $stock['ProdInfo'];

        $category = $this->getCategory(
            (int) $product->products_id
        );

        $brand = DB::table('pu_manufacture')
            ->where(
                'imanufactureid',
                $product->imanufactureid ?? 0
            )
            ->first();

        $imageUrl = $this->productImageUrl($product);

        $cartItem = [];

        if (
            ($product->WebsiteStock ?? '') === 'In'
        ) {
            $cartItem['IsMaxaromaTwoDelivery'] =
                $product->maxtwodaydelivery ?? '';
        }

        $cartItem['ProductID'] =
            $product->products_id;

        /*
         * Preserve the legacy GIFT-SKU convention.
         */
        $cartItem['SKU'] =
            'GIFT-' . $product->sku;

        $cartItem['ORGSKU'] =
            $product->sku;

        $cartItem['CategoryID'] =
            $category['id'];

        $cartItem['ProductName'] =
            $this->cleanText(
                $product->product_name ?? ''
            );

        $cartItem['short_description'] =
            strip_tags(
                $this->cleanText(
                    $product->short_description ?? ''
                )
            );

        $cartItem['Billing_Image'] =
            '<img src="' . e($imageUrl) .
            '" border="0" width="195" alt="' .
            e($product->product_name ?? '') .
            '" title="' .
            e($product->product_name ?? '') .
            '"/>';

        $cartItem['Price'] = 0;
        $cartItem['Qty'] = 1;
        $cartItem['TotPrice'] = 0;

        $cartItem['Image'] =
            '<img src="' . e($imageUrl) .
            '" border="0" width="125" alt="' .
            e($product->product_name ?? '') .
            '" />';

        $cartItem['Prod_URL'] = '';

        $cartItem['IS_Free_Gift'] = 'Yes';
        $cartItem['FreeGiftCoupon'] = 'Yes';

        $cartItem['image_forpopup'] =
            '<img src="' . e($imageUrl) .
            '" border="0" width="75" alt="' .
            e($product->product_name ?? '') .
            '" />';

        $cartItem['freeproductsid'] =
            $product->products_id;

        $cartItem['VendorSKU'] =
            $this->vendorSku($product);

        $cartItem['IsCosmo'] =
            $this->vendorFlag($product, 'cosmo');

        $cartItem['IsNandansons'] =
            $this->vendorFlag($product, 'nandansons');

        $cartItem['IsPerfumePW'] =
            $this->vendorFlag(
                $product,
                'perfumeworldwide'
            );

        $cartItem['IsPCA'] =
            $this->vendorFlag($product, 'pca');

        $cartItem['IsND'] =
            $this->vendorFlag($product, 'nd');

        $cartItem['ImanufactureID'] =
            $product->imanufactureid ?? 0;

        $cartItem['IsDealProducts'] = 'No';
        $cartItem['DealDiscountFlag'] = 'No';
        $cartItem['dealdiscount_flag'] = 'No';

        $cartItem['manufactureName'] =
            $brand->vmanufacture ?? '';

        $cartItem['CategoryName'] =
            $category['breadcrumb'];

        $cartItem['FinalSale'] = '';

        /*
         * Legacy behavior: a coupon free gift replaces the
         * existing free-gift line rather than stacking it.
         */
        $cart = Session::get(
            'ShoppingCart.Cart',
            []
        );

        $cart = array_values(
            array_filter(
                $cart,
                function ($item) {
                    return !(
                        isset($item['IS_Free_Gift'])
                        &&
                        $item['IS_Free_Gift'] === 'Yes'
                    );
                }
            )
        );

        $cart[] = $cartItem;

        Session::put(
            'ShoppingCart.Cart',
            $cart
        );

        $this->calculateSubTotal();

        return [
            'success' => true,
            'message' =>
                'Free gift added successfully.',
            'cart_item' => $cartItem,
        ];
    }

    protected function getCategory(int $productId): array
    {
        $category = DB::table('pu_category as c')
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
                $productId
            )
            ->first();

        if (!$category) {
            return [
                'id' => 0,
                'breadcrumb' => '',
            ];
        }

        $catInfo =
            config('CATEGORY_INFO');

        $breadcrumb =
            $catInfo['CatForProd']
                [$category->category_id]
                ['subcatbredcrum']
            ?? '';

        return [
            'id' =>
                (int) $category->category_id,
            'breadcrumb' =>
                $breadcrumb,
        ];
    }

    protected function productImageUrl(
        object $product
    ): string {
        $image = $product->image ?? '';

        if (
            $image !== ''
            &&
            file_exists(
                config('global.PRD_THUMB_IMG_PATH')
                . $image
            )
        ) {
            return
                config('global.PRD_THUMB_IMG_URL')
                . $image;
        }

        return config('global.NO_IMAGE_THUMB');
    }

    protected function vendorSku(
        object $product
    ): string {
        $vendors = [
            'cosmo_sku' =>
                'cosmo_current_stock',
            'pca_sku' =>
                'pca_current_stock',
            'nandansons_sku' =>
                'nandansons_current_stock',
            'perfumeworldwide_sku' =>
                'perfumeworldwide_currentstock',
            'nd_sku' =>
                'nd_current_stock',
        ];

        foreach ($vendors as $skuField => $stockField) {
            if (
                !empty($product->{$skuField})
                &&
                (float) ($product->{$stockField} ?? 0) > 0
            ) {
                return $product->{$skuField};
            }
        }

        return '';
    }

    protected function vendorFlag(
        object $product,
        string $vendor
    ): string {
        $map = [
            'cosmo' => [
                'sku' => 'cosmo_sku',
                'stock' => 'cosmo_current_stock',
            ],
            'pca' => [
                'sku' => 'pca_sku',
                'stock' => 'pca_current_stock',
            ],
            'nandansons' => [
                'sku' => 'nandansons_sku',
                'stock' => 'nandansons_current_stock',
            ],
            'perfumeworldwide' => [
                'sku' => 'perfumeworldwide_sku',
                'stock' => 'perfumeworldwide_currentstock',
            ],
            'nd' => [
                'sku' => 'nd_sku',
                'stock' => 'nd_current_stock',
            ],
        ];

        if (!isset($map[$vendor])) {
            return '';
        }

        return
            !empty(
                $product->{$map[$vendor]['sku']}
            )
            &&
            (float) (
                $product->{$map[$vendor]['stock']}
                ?? 0
            ) > 0
                ? 'Yes'
                : '';
    }

    protected function calculateSubTotal(): void
    {
        $cart = Session::get(
            'ShoppingCart.Cart',
            []
        );

        $subTotal = 0.0;

        foreach ($cart as $item) {
            $subTotal +=
                (float) (
                    $item['TotPrice'] ?? 0
                );
        }

        Session::put(
            'ShoppingCart.SubTotal',
            NumberFormat($subTotal)
        );
    }

    protected function cleanText(
        string $value
    ): string {
        return stripslashes(
            str_ireplace(
                [
                    "\r",
                    "\n",
                    '\\r',
                    '\\n',
                ],
                '',
                $value
            )
        );
    }
}
