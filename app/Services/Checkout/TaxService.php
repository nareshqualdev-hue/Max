<?php

namespace App\Services\Checkout;

use App\Models\Customer;
use App\Models\TaxAreas;
use App\Models\TaxAreaState;
use App\Models\TaxRates;
use App\Services\Discount\DiscountService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class TaxService
{
    public function __construct(
        protected DiscountService $discountService
    ) {
    }

    /**
     * Calculate checkout tax.
     *
     * This is the migrated version of the existing
     * TaxCalculation() method.
     *
     * IMPORTANT:
     * Existing business logic is intentionally preserved.
     */
    public function calculate(
        string $shipCountry,
        string $shipState,
        string $shipZip,
        int $onlyGCPurchased,
        $isPayPalSubTotal = 0,
        string $shipCity = '',
        $shippingChargePayPalProductPage = 0
    ) {
        /*
         * ---------------------------------------------------------
         * Reset taxable flags
         * ---------------------------------------------------------
         */
        Session::put(
            'ShoppingCart.TaxableShipping',
            'No'
        );

        Session::put(
            'ShoppingCart.TaxableShippingInsurance',
            'No'
        );

        Session::put(
            'ShoppingCart.TaxableShippingSignature',
            'No'
        );

        /*
         * ---------------------------------------------------------
         * Wholesaler / resale certificate
         * ---------------------------------------------------------
         */
        $this->resolveWholesalerResaleStatus();

        /*
         * Approved wholesale resale certificate OR
         * Gift Certificate only purchase
         *
         * Existing behavior:
         * Tax = 0 and return null.
         */
        if (
            (
                Session::has('eusertype')
                &&
                Session::get('eusertype')
                    === 'Wholesaler'
                &&
                Session::has(
                    'resale_certificate_status'
                )
                &&
                Session::get(
                    'resale_certificate_status'
                ) === 'Approved'
            )
            ||
            $onlyGCPurchased === 1
        ) {
            Session::put(
                'ShoppingCart.Tax',
                0
            );

            return null;
        }

        Log::info(
            'Shipping Infor for Tax',
            [
                'Shipping Infor for Tax' =>
                    Session::get(
                        'eusertype'
                    )
                    . '---'
                    . $shipCountry
                    . '--'
                    . $shipState
                    . '--'
                    . $shipZip
                    . '--'
                    . $isPayPalSubTotal
                    . '--'
                    . $shipCity,
            ]
        );

        Log::warning(
            'User Type Info',
            [
                'User Type Info' =>
                    Session::get(
                        'eusertype'
                    ),
            ]
        );

        $shipCity =
            trim(
                $shipCity
            );

        /*
         * ---------------------------------------------------------
         * Discount / Gift Certificate calculation
         * ---------------------------------------------------------
         */
        $allDiscount =
            $this->getAllDiscounts();
		
        $allDiscount['TotalDiscount'] =
            (float) (
                $allDiscount['TotalDiscount'] ?? 0
            );

        $giftCertiTotal =
            Session::has(
                'ShoppingCart.GiftCertiTotal'
            )
                ? NumberFormat(
                    Session::get(
                        'ShoppingCart.GiftCertiTotal'
                    )
                )
                : 0;

        /*
         * Existing logic:
         *
         * TotalDiscount
         * - credit_limit_discount
         * + phoneorder_manual_discount
         */
        $allDiscount['TotalDiscount'] -=
            (float)
            Session::get(
                'ShoppingCart.credit_limit_discount',
                0
            );

        if (
            Session::has(
                'ShoppingCart.phoneorder_manual_discount'
            )
        ) {
            $allDiscount['TotalDiscount'] +=
                (float)
                Session::get(
                    'ShoppingCart.phoneorder_manual_discount'
                );
        }

        /*
         * Existing subtotal:
         *
         * Cart SubTotal
         * - Gift Certificate
         * - Total Discount
         */
        $subTotal =
            (
                (float)
                Session::get(
                    'ShoppingCart.SubTotal',
                    0
                )
                -
                (
                    $giftCertiTotal
                    +
                    $allDiscount['TotalDiscount']
                )
            );

        /*
         * ---------------------------------------------------------
         * PayPal Product Page subtotal override
         * ---------------------------------------------------------
         */
        $isFromPayPalProductPage =
            !empty(
                $isPayPalSubTotal
            )
                ? 'Yes'
                : 'No';

        if (
            !empty(
                $isPayPalSubTotal
            )
        ) {
            $subTotal =
                $isPayPalSubTotal;
        }

        $subTotal =
            max(
                0,
                NumberFormat(
                    $subTotal
                )
            );

        $shipZip =
            $shipZip ?: '0';

        /*
         * ---------------------------------------------------------
         * Tax calculation callback
         * ---------------------------------------------------------
         *
         * This intentionally keeps the existing behavior:
         *
         * Shipping taxable
         * Insurance taxable
         * Signature taxable
         *
         * PayPal Product Page:
         * return calculated tax
         *
         * Normal checkout:
         * put tax into session and return null
         */
        $calculateTax =
            function ($area) use (
                $subTotal,
                $isFromPayPalProductPage,
                $shippingChargePayPalProductPage
            ) {
                Log::info(
                    'CalculateTaxArea: '
                    . json_encode(
                        $area
                    )
                );

                $taxableSubTotal =
                    (float)
                    $subTotal;

                /*
                 * Shipping charge
                 */
                $shippingChargeTotal =
                    (
                        Session::has(
                            'ShoppingCart.Shipping.ShippingCharge'
                        )
                        &&
                        Session::get(
                            'ShoppingCart.Shipping.ShippingCharge'
                        ) > 0
                    )
                        ? (float)
                        Session::get(
                            'ShoppingCart.Shipping.ShippingCharge'
                        )
                        : 0;

                /*
                 * PayPal Product Page shipping override
                 */
                if (
                    $isFromPayPalProductPage === 'Yes'
                    &&
                    isset(
                        $shippingChargePayPalProductPage
                    )
                    &&
                    $shippingChargePayPalProductPage != 0
                    &&
                    $shippingChargePayPalProductPage
                        != '0.00'
                ) {
                    $shippingChargeTotal =
                        (float)
                        $shippingChargePayPalProductPage;
                }

                /*
                 * Taxable Shipping
                 */
                if (
                    isset(
                        $area->ShippingTaxable
                    )
                    &&
                    $area->ShippingTaxable === 'Yes'
                ) {
                    Session::put(
                        'ShoppingCart.TaxableShipping',
                        'Yes'
                    );

                    $taxableSubTotal +=
                        $shippingChargeTotal;
                }

                /*
                 * Taxable Insurance
                 */
                if (
                    isset(
                        $area->insurance
                    )
                    &&
                    $area->insurance === 'Yes'
                ) {
                    Session::put(
                        'ShoppingCart.TaxableShippingInsurance',
                        'Yes'
                    );

                    $taxableSubTotal +=
                        (float)
                        $this->getCharge(
                            'ShippingInsurance'
                        );
                }

                /*
                 * Taxable Signature
                 */
                if (
                    isset(
                        $area->signature
                    )
                    &&
                    $area->signature === 'Yes'
                ) {
                    Session::put(
                        'ShoppingCart.TaxableShippingSignature',
                        'Yes'
                    );

                    $taxableSubTotal +=
                        (float)
                        $this->getCharge(
                            'ShippingSignature'
                        );
                }

                $taxableSubTotal =
                    max(
                        0,
                        (float)
                        NumberFormat(
                            $taxableSubTotal
                        )
                    );

                /*
                 * Find tax rate
                 */
                $rate =
                    TaxRates::where(
                        'tax_areas_id',
                        $area->tax_areas_id
                    )
                    ->where(
                        'amount_from',
                        '<=',
                        $taxableSubTotal
                    )
                    ->orderBy(
                        'amount_from',
                        'desc'
                    )
                    ->first();

                if (
                    !$rate
                ) {
                    return null;
                }

                Log::info(
                    'CalculateTaxRate: '
                    . json_encode(
                        $rate
                    )
                );

                Session::put(
                    'ShoppingCart.TaxableSubTotal',
                    $taxableSubTotal
                );

                /*
                 * Percentage tax
                 * OR
                 * fixed tax amount
                 */
                $tax =
                    $rate->amount_in_percent === 'Y'
                        ? (
                            $taxableSubTotal
                            *
                            $rate->charge_amount
                        ) / 100
                        : $rate->charge_amount;

                /*
                 * PayPal Product Page:
                 * return tax directly.
                 */
                if (
                    $isFromPayPalProductPage === 'Yes'
                ) {
                    return $tax;
                }

                /*
                 * Normal checkout:
                 * store tax in session.
                 */
                Session::put(
                    'ShoppingCart.Tax',
                    $tax
                );

                return null;
            };

        /*
         * ---------------------------------------------------------
         * PRIORITY 1:
         *
         * City + State + ZIP + Country
         * ---------------------------------------------------------
         */
        if (
            !empty(
                $shipCity
            )
        ) {
            $area =
                TaxAreas::where(
                    'country',
                    $shipCountry
                )
                ->where(
                    'states',
                    $shipState
                )
                ->whereRaw(
                    'LOWER(county) = ?',
                    [
                        strtolower(
                            $shipCity
                        ),
                    ]
                )
                ->where(
                    'zip_from',
                    '<=',
                    (int)
                    $shipZip
                )
                ->where(
                    'zip_to',
                    '>=',
                    (int)
                    $shipZip
                )
                ->where(
                    'status',
                    '1'
                )
                ->orderByRaw(
                    '(zip_to - zip_from) ASC'
                )
                ->first();

            if (
                $area
            ) {
                return $calculateTax(
                    $area
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * PRIORITY 2:
         *
         * ZIP + State + Country
         * ---------------------------------------------------------
         */
        $area =
            TaxAreas::where(
                'country',
                $shipCountry
            )
            ->where(
                'states',
                $shipState
            )
            ->where(
                'zip_from',
                '<=',
                (int)
                $shipZip
            )
            ->where(
                'zip_to',
                '>=',
                (int)
                $shipZip
            )
            ->where(
                'status',
                '1'
            )
            ->orderByRaw(
                '(zip_to - zip_from) ASC'
            )
            ->first();

        if (
            $area
        ) {
            return $calculateTax(
                $area
            );
        }

        /*
         * ---------------------------------------------------------
         * PRIORITY 3:
         *
         * ZIP + Country only
         * ---------------------------------------------------------
         */
        $area =
            TaxAreas::where(
                'country',
                $shipCountry
            )
            ->where(
                'states',
                ''
            )
            ->where(
                'zip_from',
                '<=',
                (int)
                $shipZip
            )
            ->where(
                'zip_to',
                '>=',
                (int)
                $shipZip
            )
            ->where(
                'status',
                '1'
            )
            ->orderByRaw(
                '(zip_to - zip_from) ASC'
            )
            ->first();

        if (
            $area
        ) {
            return $calculateTax(
                $area
            );
        }

        /*
         * ---------------------------------------------------------
         * PRIORITY 4:
         *
         * Country + State
         * ---------------------------------------------------------
         */
        $area =
            TaxAreas::where(
                'country',
                $shipCountry
            )
            ->where(
                'states',
                $shipState
            )
            ->where(
                'zip_from',
                ''
            )
            ->where(
                'zip_to',
                ''
            )
            ->where(
                'status',
                '1'
            )
            ->orderByRaw(
                '(zip_to - zip_from) ASC'
            )
            ->first();

        if (
            $area
        ) {
            return $calculateTax(
                $area
            );
        }

        /*
         * ---------------------------------------------------------
         * PRIORITY 5:
         *
         * City + State + Country
         * ---------------------------------------------------------
         */
        if (
            !empty(
                $shipCity
            )
        ) {
            $area =
                TaxAreas::where(
                    'country',
                    $shipCountry
                )
                ->where(
                    'states',
                    $shipState
                )
                ->whereRaw(
                    'LOWER(county) = ?',
                    [
                        strtolower(
                            $shipCity
                        ),
                    ]
                )
                ->where(
                    'status',
                    '1'
                )
                ->orderByRaw(
                    '(zip_to - zip_from) ASC'
                )
                ->first();

            if (
                $area
            ) {
                return $calculateTax(
                    $area
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * PRIORITY 6:
         *
         * Country
         *
         * Existing code intentionally excludes US here.
         * ---------------------------------------------------------
         */
        $area =
            TaxAreas::where(
                'country',
                $shipCountry
            )
            ->where(
                'status',
                '1'
            )
            ->where(
                'country',
                '!=',
                'US'
            )
            ->orderByRaw(
                '(zip_to - zip_from) ASC'
            )
            ->first();

        if (
            $area
        ) {
            return $calculateTax(
                $area
            );
        }

        /*
         * ---------------------------------------------------------
         * TaxAreaState fallback
         * ---------------------------------------------------------
         */
        $taxAreaState =
            TaxAreaState::where(
                'state',
                $shipState
            )
            ->orderBy(
                'taxt_areas_state_id',
                'DESC'
            )
            ->first();

        if (
            $taxAreaState
        ) {
            $taxRate =
                (float)
                (
                    $taxAreaState->tax_rate
                    ?? 0
                );

            $taxShipping =
                $taxAreaState->shipping
                ?? 'No';

            $taxInsurance =
                $taxAreaState->insurance
                ?? 'No';

            $taxSignature =
                $taxAreaState->signature
                ?? 'No';

            $taxableSubTotal =
                $subTotal;

            /*
             * Taxable shipping
             */
            if (
                $taxShipping === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.TaxableShipping',
                    'Yes'
                );

                $taxableSubTotal +=
                    $this->getCharge(
                        'ShippingCharge'
                    );
            }

            /*
             * Taxable insurance
             */
            if (
                $taxInsurance === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.TaxableShippingInsurance',
                    'Yes'
                );

                $taxableSubTotal +=
                    $this->getCharge(
                        'ShippingInsurance'
                    );
            }

            /*
             * Taxable signature
             */
            if (
                $taxSignature === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.TaxableShippingSignature',
                    'Yes'
                );

                $taxableSubTotal +=
                    $this->getCharge(
                        'ShippingSignature'
                    );
            }

            $taxableSubTotal =
                max(
                    0,
                    (float)
                    NumberFormat(
                        $taxableSubTotal
                    )
                );

            $tax =
                (
                    $taxableSubTotal
                    *
                    $taxRate
                ) / 100;

            Session::put(
                'ShoppingCart.TaxableSubTotal',
                $taxableSubTotal
            );

            Session::put(
                'ShoppingCart.Tax',
                $tax
            );

            return true;
        }

        /*
         * ---------------------------------------------------------
         * Final State/Country fallback
         * ---------------------------------------------------------
         */
        $area =
            TaxAreas::where(
                'country',
                $shipCountry
            )
            ->where(
                'states',
                $shipState
            )
            ->where(
                'status',
                '1'
            )
            ->orderByRaw(
                '(zip_to - zip_from) ASC'
            )
            ->first();

        if (
            $area
        ) {
            return $calculateTax(
                $area
            );
        }

        /*
         * ---------------------------------------------------------
         * Default = 0
         * ---------------------------------------------------------
         */
        if (
            $isFromPayPalProductPage === 'Yes'
        ) {
            return 0;
        }

        Session::put(
            'ShoppingCart.Tax',
            0
        );

        return null;
    }

    /**
     * Resolve wholesaler resale certificate status.
     *
     * Existing logic preserved from TaxCalculation().
     */
    protected function resolveWholesalerResaleStatus(): void
    {
        $whLog = '';

        if (
            Session::has('eusertype')
            &&
            Session::get(
                'eusertype'
            ) === 'Wholesaler'
        ) {
            if (
                Session::has(
                    'sess_useremail'
                )
                &&
                Session::get(
                    'sess_useremail'
                ) !== ''
            ) {
                $whLog .=
                    'Wholesale User Email : '
                    .
                    Session::get(
                        'sess_useremail'
                    );
            }

            if (
                Session::has(
                    'resale_certificate_status'
                )
                &&
                Session::get(
                    'resale_certificate_status'
                ) !== ''
            ) {
                $whLog .=
                    '--Wholesale Resale Certificate Status : '
                    .
                    Session::get(
                        'resale_certificate_status'
                    );
            }

            if (
                $whLog !== ''
            ) {
                Log::info(
                    'Wholesaler Tax ',
                    [
                        'Wholesaler Tax Calculations ' =>
                            $whLog,
                    ]
                );
            }

            if (
                !Session::has(
                    'resale_flag'
                )
            ) {
                $customer =
                    Customer::where(
                        'email',
                        Session::get(
                            'sess_useremail'
                        )
                    )
                    ->where(
                        'status',
                        '1'
                    )
                    ->where(
                        'registration_type',
                        'M'
                    )
                    ->first();

                if (
                    $customer
                    &&
                    !Session::has(
                        'resale_flag'
                    )
                    &&
                    (
                        !Session::has(
                            'resale_certificate_status'
                        )
                        ||
                        Session::get(
                            'resale_certificate_status'
                        ) === 'Pending'
                        ||
                        Session::get(
                            'resale_certificate_status'
                        ) === 'Rejected'
                    )
                ) {
                    Session::put(
                        'resale_certificate_status',
                        $customer
                            ->resale_certificate_status
                    );

                    Session::put(
                        'resale_flag',
                        'Yes'
                    );
                }
            }
        }
    }

    /**
     * Existing discount source.
     *
     * IMPORTANT:
     * Replace this call with your actual DiscountService method
     * if its public API has a different name.
     */
    protected function getAllDiscounts(): array
    {
        $discounts =
            $this->discountService
                ->getAll();

        return is_array(
            $discounts
        )
            ? $discounts
            : [
                'TotalDiscount' => 0,
            ];
    }

    /**
     * Get a charge using the existing checkout charge source.
     *
     * This avoids making TaxService depend on
     * CheckoutTotalsService and creating circular dependencies.
     */
    protected function getCharge(
        string $chargeName
    ): float {
        return match (
            $chargeName
        ) {
            'ShippingCharge' =>
                (float)
                Session::get(
                    'ShoppingCart.Shipping.ShippingCharge',
                    0
                ),

            'ShippingInsurance' =>
                (float)
                Session::get(
                    'shipping_insurance_charge',
                    0
                ),

            'ShippingSignature' =>
                (float)
                Session::get(
                    'ShoppingCart.ShippingSignature',
                    0
                ),

            default =>
                0.0,
        };
    }
}
