<?php

namespace App\Services\Checkout;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartAttributeService
{
    /**
     * Build cart-level attributes required by checkout.
     *
     * Migrated from ShoppingCartController::SetCartAttributes()
     *
     * IMPORTANT:
     * This service only handles cart attributes.
     * Payment methods, coupons, credit limits and OrderType
     * are handled by their respective services.
     */

	public function getAttributes(): array
{
    $attrs = [];

    $onlyGCPurchased = 1;
    $checkGCPurchasedVal = 0;
    $rewardPointItemWiseTotal = 0;

    $critieostr = '';

    $isVenderItem = 'No';
    $isCosmo = 'No';
    $isNandansons = 'No';
    $isPerfumePW = 'No';
    $isPCA = 'No';
    $isND = 'No';

    $isMaxaromaTwoDelivery = 'No';
    $isMax2day = '';
    $isMaxTwoItem = 'No';

    $cart = Session::get(
        'ShoppingCart.Cart',
        []
    );

    if (
        !empty($cart)
        &&
        is_array($cart)
    ) {
        $tempCart = [];

        foreach ($cart as $shopItem) {

            /*
             * -------------------------------------------------
             * Gift checkbox
             * -------------------------------------------------
             */
            $giftChkOpt = 'Yes';

            if (
                (
                    isset($shopItem['IS_Free_Gift'])
                    &&
                    $shopItem['IS_Free_Gift'] === 'Yes'
                )
                ||
                (
                    isset($shopItem['Is_Free_Sample'])
                    &&
                    $shopItem['Is_Free_Sample'] === 'Yes'
                )
            ) {
                $giftChkOpt = 'No';
            }

            /*
             * -------------------------------------------------
             * Gift Certificate
             * -------------------------------------------------
             */
            $isGiftCertificateItem =
                $this->checkGiftCertificateItem(
                    $shopItem
                );

            if (
                $isGiftCertificateItem === 'Yes'
            ) {
                $giftChkOpt = 'No';
                $checkGCPurchasedVal = 1;
            }

            /*
             * Product cannot have gift option.
             */
            if (
                isset(
                    $shopItem['IsGiftWrapProduct']
                )
                &&
                $shopItem['IsGiftWrapProduct']
                    === 'No'
            ) {
                $giftChkOpt = 'No';
            }

            /*
             * Product having handling time
             * cannot have gift option.
             */
            if (
                isset(
                    $shopItem['HandlingTimeStr']
                )
                &&
                $shopItem['HandlingTimeStr'] !== ''
            ) {
                $giftChkOpt = 'No';
            }

            /*
             * -------------------------------------------------
             * onlyGCPurchased
             * -------------------------------------------------
             */
            if (
                $isGiftCertificateItem === 'No'
            ) {
                $onlyGCPurchased = 0;
            }

            $shopItem['ShowGiftChkOpt'] =
                $giftChkOpt;

            /*
             * -------------------------------------------------
             * Reward item-wise total
             * -------------------------------------------------
             */
            $normalUser =
                $this->getCurrentUser();

            if (
                $normalUser
                &&
                strtolower(
                    $normalUser->eusertype ?? ''
                ) === 'retailer'
                &&
                Session::get(
                    'sess_icustomerid'
                ) > 0
                &&
                Session::get(
                    'etype'
                ) === 'M'
            ) {
                if (
                    isset(
                        $shopItem['PointMultipier']
                    )
                    &&
                    $shopItem['PointMultipier'] > 0
                ) {
                    $rewardItemWise =
                        $shopItem['TotPrice']
                        *
                        $shopItem['PointMultipier'];

                    $rewardItemWise =
                        NumberFormat(
                            $rewardItemWise
                        );

                    $rewardPointItemWiseTotal +=
                        $rewardItemWise;
                }
            }

            /*
             * -------------------------------------------------
             * Critieo string
             * -------------------------------------------------
             */
            $critieostr .=
                '{ id: "'
                . ($shopItem['SKU'] ?? '')
                . '", price: '
                . ($shopItem['Price'] ?? 0)
                . ', quantity: '
                . ($shopItem['Qty'] ?? 0)
                . ' } ,';

            /*
             * -------------------------------------------------
             * Vendor flags
             * -------------------------------------------------
             */
            $vendorFlags = [
                'IsCosmo',
                'IsNandansons',
                'IsPerfumePW',
                'IsPCA',
                'IsND',
            ];

            foreach (
                $vendorFlags as $vendorFlag
            ) {
                if (
                    isset(
                        $shopItem[$vendorFlag]
                    )
                    &&
                    $shopItem[$vendorFlag] === 'Yes'
                    &&
                    isset(
                        $shopItem['VendorSKU']
                    )
                    &&
                    $shopItem['VendorSKU'] !== ''
                ) {
                    $isVenderItem = 'Yes';
                    break;
                }
            }

            /*
             * Individual vendor flags.
             */
            if (
                $this->isVendorItem(
                    $shopItem,
                    'IsCosmo'
                )
            ) {
                $isCosmo = 'Yes';
            }

            if (
                $this->isVendorItem(
                    $shopItem,
                    'IsNandansons'
                )
            ) {
                $isNandansons = 'Yes';
            }

            if (
                $this->isVendorItem(
                    $shopItem,
                    'IsPerfumePW'
                )
            ) {
                $isPerfumePW = 'Yes';
            }

            if (
                $this->isVendorItem(
                    $shopItem,
                    'IsPCA'
                )
            ) {
                $isPCA = 'Yes';
            }

            if (
                $this->isVendorItem(
                    $shopItem,
                    'IsND'
                )
            ) {
                $isND = 'Yes';
            }

            /*
             * -------------------------------------------------
             * Maxaroma Two Delivery
             * -------------------------------------------------
             *
             * IMPORTANT:
             *
             * This follows the OLD ShippingMethods /
             * SetCartAttributes behavior.
             *
             * Old logic uses the cart item's
             * IsMaxaromaTwoDelivery value.
             *
             * Eligible item:
             *   IsMaxaromaTwoDelivery = Yes
             *   ISMaxTwoItem = Yes
             *
             * Non-eligible item:
             *   ISMax2day = No
             *
             * IMPORTANT:
             *
             * Do NOT set ISMax2day = Yes for an eligible item.
             *
             * This is required for mixed carts such as:
             *
             *   $248  -> non Max2Day
             *   $985  -> Max2Day
             *
             * In the OLD checkout:
             *
             *   IsMaxaromaTwoDelivery = Yes
             *   ISMaxTwoItem = Yes
             *   ISMax2dayVal = No
             *
             * which triggers the existing Max2Day upgrade logic.
             */
            if (
                isset(
                    $shopItem[
                        'IsMaxaromaTwoDelivery'
                    ]
                )
                &&
                $shopItem[
                    'IsMaxaromaTwoDelivery'
                ] === 'Yes'
                &&
                $shopItem[
                    'IsMaxaromaTwoDelivery'
                ] !== ''
            ) {

                /*
                 * Exact old behavior.
                 */
                $isMaxaromaTwoDelivery =
                    'Yes';

                $isMaxTwoItem =
                    'Yes';

                /*
                 * DO NOT set:
                 *
                 * $isMax2day = 'Yes';
                 *
                 * Old checkout does not do that here.
                 */
            }
            else {

                /*
                 * Exact old behavior for a
                 * non-Max2Day item.
                 */
                $isMax2day =
                    'No';
            }

            /*
             * -------------------------------------------------
             * Deal product
             * -------------------------------------------------
             *
             * Old behavior:
             *
             * Deal product cannot qualify for
             * Max2Day delivery.
             */
            if (
                isset(
                    $shopItem['IsDealProducts']
                )
                &&
                $shopItem[
                    'IsDealProducts'
                ] === 'Yes'
            ) {
                $isMaxaromaTwoDelivery =
                    'No';
            }

            $tempCart[] =
                $shopItem;
        }

        /*
         * Existing behavior:
         * Updated cart is saved back to session.
         */
        Session::put(
            'ShoppingCart.Cart',
            $tempCart
        );

        Session::put(
            'ShoppingCart.RewardPointItemWiseTotal',
            ceil(
                $rewardPointItemWiseTotal
            )
        );
    }

    /*
     * ---------------------------------------------------------
     * Return cart attributes
     * ---------------------------------------------------------
     */
    $attrs['IsVenderItem'] =
        $isVenderItem;

    $attrs['IsCosmo'] =
        $isCosmo;

    $attrs['IsNandansons'] =
        $isNandansons;

    $attrs['IsPerfumePW'] =
        $isPerfumePW;

    $attrs['IsPCA'] =
        $isPCA;

    $attrs['IsND'] =
        $isND;

    $attrs['IsMaxaromaTwoDelivery'] =
        $isMaxaromaTwoDelivery;

    $attrs['ISMaxTwoItem'] =
        $isMaxTwoItem;

    $attrs['onlyGCPurchased'] =
        $onlyGCPurchased;

    $attrs['CheckGCPurchasedVal'] =
        $checkGCPurchasedVal;

    $attrs['ISMax2dayVal'] =
        $isMax2day;

    $attrs['critieostr'] =
        $critieostr !== ''
            ? substr(
                $critieostr,
                0,
                -1
            )
            : '';

    return $attrs;
}
   /**
     * Check whether the cart item is a vendor item.
     */
    protected function isVendorItem(
        array $shopItem,
        string $vendorFlag
    ): bool {
        return (
            isset(
                $shopItem[$vendorFlag]
            )
            &&
            $shopItem[$vendorFlag] === 'Yes'
            &&
            isset(
                $shopItem['VendorSKU']
            )
            &&
            $shopItem['VendorSKU'] !== ''
        );
    }

    /**
     * Get current authenticated user.
     *
     * Same guard behavior as the existing controller.
     */
    protected function getCurrentUser()
    {
        $normalUser =
            Auth::user();

        if (
            Auth::guard('store')->check()
        ) {
            $normalUser =
                Auth::guard('web')->user();
        }

        return $normalUser;
    }

    /**
     * Gift Certificate checker.
     *
     * The original ShoppingCartController delegates this to
     * checkGiftCertificateItem().
     *
     * DO NOT duplicate that business rule here.
     *
     * Connect this method to the existing Gift Certificate service
     * when that service is migrated.
     */
    protected function checkGiftCertificateItem(
        array $shopItem
    ): string {
        /*
         * Current cart structure still contains the SKU based
         * Gift Certificate products.
         *
         * Keep the existing global configuration as the fallback
         * until the original checkGiftCertificateItem() is moved
         * into GiftCertificateService.
         */
        $giftCertificateSku =
            config(
                'global.GIFT_CERTIFICATE_SKU'
            );

        $giftCertificateSku1 =
            config(
                'global.GIFT_CERTIFICATE_SKU1'
            );

        $sku =
            $shopItem['SKU']
            ?? '';

        if (
            $sku !== ''
            &&
            (
                $sku === $giftCertificateSku
                ||
                $sku === $giftCertificateSku1
            )
        ) {
            return 'Yes';
        }

        return 'No';
    }
}
