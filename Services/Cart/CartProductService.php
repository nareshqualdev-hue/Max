<?php

namespace App\Services\Cart;

use Illuminate\Support\Facades\DB;

class CartProductService
{
    /**
     * Build the common cart-item data from the normalized product.
     *
     * This intentionally keeps the existing cart field names so the
     * current checkout/cart business logic can continue to consume
     * the session cart without a breaking field rename.
     */
    public function buildCartItem(
        object $product,
        int $qty = 1,
        string $orderType = 'Website'
    ): array {
        $qty = $qty > 0 ? $qty : 1;

        $category = $this->getCategory($product);

        $item = [];

        if (
            isset($product->WebsiteStock)
            && $product->WebsiteStock === 'In'
        ) {
            $item['IsMaxaromaTwoDelivery'] =
                $product->maxtwodaydelivery ?? '';
        }

        $item['ProductID'] =
            $product->products_id ?? 0;

        $item['SKU'] =
            $product->sku ?? '';

        $item['ORGSKU'] =
            $product->sku ?? '';

        $item['CategoryID'] =
            $category['id'];

        $item['ProductName'] =
            $this->cleanText(
                $product->product_name ?? ''
            );

        $item['short_description'] =
            strip_tags(
                $this->cleanText(
                    $product->short_description ?? ''
                )
            );

        $item['Billing_Image'] =
            $product->billing_image ?? '';

        $item['Price'] =
            $this->getBasePrice($product);

        $item['Qty'] = $qty;

        $item['TotPrice'] =
            $this->numberFormat(
                $item['Price'] * $qty
            );

        $item['Image'] =
            $product->prod_image ?? '';

        $item['Prod_URL'] =
            $product->Prod_URL ?? '';

        $item['CategoryName'] =
            $category['breadcrumb'];

        $item['ImanufactureID'] =
            $product->imanufactureid ?? 0;

        $item['manufactureName'] =
            $product->manufactureName
            ?? $product->vmanufacture
            ?? '';

        $item['IsDealProducts'] =
            $product->IsDealProducts ?? 'No';

        $item['DealDiscountFlag'] =
            $product->DealDiscountFlag ?? 'No';

        $item['dealdiscount_flag'] =
            $product->dealdiscount_flag ?? 'No';

        $item['FinalSale'] =
            $product->FinalSale ?? '';

        $item['VendorSKU'] =
            $product->VendorSKU ?? '';

        $item['IsCosmo'] =
            $product->IsCosmo ?? '';

        $item['IsNandansons'] =
            $product->IsNandansons ?? '';

        $item['IsPerfumePW'] =
            $product->IsPerfumePW ?? '';

        $item['IsPCA'] =
            $product->IsPCA ?? '';

        $item['IsND'] =
            $product->IsND ?? '';

        $item['OrderType'] =
            $orderType;

        return $item;
    }

    /**
     * Preserve the existing product pricing behavior as a separate
     * step so CartService does not contain pricing implementation.
     */
    public function calculateProductPrice(
        object $product,
        int $qty = 1,
        string $sku = ''
    ): array {
        $qty = $qty > 0 ? $qty : 1;

        $price =
            (float) (
                $product->product_price ?? 0
            );

        $dealOfWeek = [];

        if ($sku === '') {
            $sku = $product->sku ?? '';
        }

        if ($sku !== '') {
            $dealOfWeek =
                GetDealOfWeek(
                    $sku,
                    'Weekly',
                    'Cart'
                );
        }

        if (
            is_array($dealOfWeek)
            && isset($dealOfWeek[$sku]['deal_price'])
            && (float) $dealOfWeek[$sku]['deal_price']
                < $price
        ) {
            $price =
                (float) $dealOfWeek[$sku]['deal_price'];
        }

        $markupPercent = '';
        $markupValue = '';

        if (
            config('Settings.WHOLESALE_MARKUP') === 'Yes'
            && $this->isWholesaler()
        ) {
            $specialPrice =
                GetSpecialPricePercentandValue(
                    $qty
                );

            $parts =
                explode(
                    '#',
                    (string) $specialPrice
                );

            $markupPercent =
                $parts[0] ?? '';

            $markupValue =
                $parts[1] ?? '';

            if ($markupPercent !== '') {
                $price =
                    $price
                    -
                    (
                        $price
                        * (float) $markupPercent
                        / 100
                    );
            }
        }

        return [
            'Price' =>
                $this->numberFormat($price),
            'Markup_Percent' =>
                $markupPercent,
            'Markup_Value' =>
                $markupValue,
            'ActualWholesalePrice' =>
                $product->product_price ?? $price,
        ];
    }

    /**
     * Apply the calculated price fields to a cart item.
     */
    public function applyPrice(
        array $cartItem,
        object $product,
        int $qty
    ): array {
        $priceData =
            $this->calculateProductPrice(
                $product,
                $qty,
                $cartItem['SKU'] ?? ''
            );

        $cartItem['Price'] =
            $priceData['Price'];

        $cartItem['TotPrice'] =
            $this->numberFormat(
                $qty * (float) $cartItem['Price']
            );

        if ($this->isWholesaler()) {
            if (
                !isset(
                    $cartItem['ActualWholesalePrice']
                )
            ) {
                $cartItem['ActualWholesalePrice'] =
                    $priceData['ActualWholesalePrice'];
            }

            $cartItem['Markup_Percent'] =
                $priceData['Markup_Percent'];

            $cartItem['Markup_Value'] =
                $priceData['Markup_Value'];
        }

        return $cartItem;
    }

    /**
     * Existing product-category lookup used by the cart item.
     */
    public function getCategory(
        object $product
    ): array {
        $categoryId = 0;
        $breadcrumb = '';

        if (
            !isset($product->products_id)
            || (int) $product->products_id <= 0
        ) {
            return [
                'id' => $categoryId,
                'breadcrumb' => $breadcrumb,
            ];
        }

        $category =
            DB::table('pu_category as c')
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
                    $product->products_id
                )
                ->first();

        if ($category) {
            $categoryId =
                (int) $category->category_id;

            $categoryInfo =
                config(
                    'CATEGORY_INFO',
                    []
                );

            $breadcrumb =
                $categoryInfo[
                    'CatForProd'
                ][$categoryId][
                    'subcatbredcrum'
                ]
                ?? $category->category_name
                ?? '';
        }

        return [
            'id' => $categoryId,
            'breadcrumb' => $breadcrumb,
        ];
    }

    protected function getBasePrice(
        object $product
    ): float {
        return (float) (
            $product->product_price ?? 0
        );
    }

    protected function isWholesaler(): bool
    {
        return strtolower(
            trim(
                (string) session(
                    'eusertype',
                    ''
                )
            )
        ) === 'wholesaler';
    }

    protected function cleanText(
        mixed $value
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
                (string) $value
            )
        );
    }

    protected function numberFormat(
        float|int|string $value
    ): float {
        if (function_exists('NumberFormat')) {
            return (float) NumberFormat($value);
        }

        return round((float) $value, 2);
    }
}
