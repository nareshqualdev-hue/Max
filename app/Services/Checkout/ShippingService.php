<?php

namespace App\Services\Checkout;

use Illuminate\Support\Facades\Session;
use App\Models\ShippingMode;
use App\Models\ShippingRule;
use App\Models\ShippingRate;
use App\Models\ShippingHoliday;

class ShippingService
{
    public function __construct(
        protected CheckoutTotalsService $checkoutTotalsService
    ) {
    }

    /**
     * Get available shipping methods for the current
     * checkout shipping address.
     *
     * IMPORTANT:
     * This is the service version of the old ShippingMethods()
     * calculation flow.
     *
     * No view is rendered here.
     */
    public function getAvailableMethods(
        array $address = [],
        array $flags = []
    ): array {
        $shippingAddress =
            $this->resolveAddress($address);

        $shipCountry =
            $shippingAddress['country'];

        $shipState =
            $shippingAddress['state'];

        $shipZip =
            $shippingAddress['zip'];

        $shipCity =
            $shippingAddress['city'];

        $shipAddress1 =
            $shippingAddress['address1'];

        $shipAddress2 =
            $shippingAddress['address2'];

        $shippingFlags =
            $this->resolveShippingFlags(
                $flags
            );

        $isMaxaromaTwoDelivery =
            $shippingFlags['IsMaxaromaTwoDelivery'];

        $isMaxTwoItem =
            $shippingFlags['ISMaxTwoItem'];

        $isVendorItem =
            $shippingFlags['IsVenderItem'];

        $isCosmo =
            $shippingFlags['IsCosmo'];

        $isNandansons =
            $shippingFlags['IsNandansons'];

        $isPerfumePW =
            $shippingFlags['IsPerfumePW'];

        $isPCA =
            $shippingFlags['IsPCA'];

        $isND =
            $shippingFlags['IsND'];

        $isMax2DayVal =
            $shippingFlags['ISMax2dayVal'];

        $onlyGCPurchased =
            $shippingFlags['onlyGCPurchased'];

        /*
         * ---------------------------------------------------------
         * Max2Day eligibility
         * ---------------------------------------------------------
         */
        $shippingModeIdMain =
            $this->checkAvailableShippingMethod(
                29,
                $shipCountry,
                $shipState,
                $shipZip
            );

        $mainParts =
            $this->parseAvailableShippingMethod(
                $shippingModeIdMain
            );

        $isTwoDay =
            $mainParts['shipping_mode_id'] > 0
                ? 'Yes'
                : 'No';

        /*
         * ---------------------------------------------------------
         * PO BOX check
         * ---------------------------------------------------------
         */
        $addressCheck =
            $this->isPoBoxAddress(
                $shipAddress1,
                $shipAddress2
            )
                ? 'Yes'
                : 'No';

        if (
            $addressCheck === 'Yes'
        ) {
            $isTwoDay = 'No';
        }

        /*
         * ---------------------------------------------------------
         * APO / FPO check
         * ---------------------------------------------------------
         */
        $apoFpo =
            $this->isApoFpoAddress(
                $shipAddress1,
                $shipAddress2,
                $shipCity
            )
                ? 'Yes'
                : 'No';

        /*
         * ---------------------------------------------------------
         * Shipping modes
         * ---------------------------------------------------------
         */
        $shippingModes =
            ShippingMode::where(
                'status',
                '=',
                '1'
            )
            ->orderBy(
                'display_position',
                'asc'
            )
            ->get();

        $chargeInfo = [];

        $messages = [];

        $max2Days = 0;

        $messageSuccess = 0;

        $isPickup = 'No';

        $selectedShippingMethodId =
            (int) Session::get(
                'ShoppingCart.Shipping.ShippingMethodID',
                0
            );

        /*
         * ---------------------------------------------------------
         * Loop through shipping methods
         * ---------------------------------------------------------
         */
        foreach (
            $shippingModes as $shippingMode
        ) {
            $shippingModeId =
                (int)
                $shippingMode->shipping_mode_id;

            /*
             * PO BOX:
             *
             * Existing logic allows only methods 9 and 22.
             */
            if (
                $addressCheck === 'Yes'
                &&
                $shippingModeId !== 9
                &&
                $shippingModeId !== 22
            ) {
                continue;
            }

            if (
                $addressCheck === 'Yes'
                &&
                $shippingModeId === 29
            ) {
                continue;
            }

            /*
             * Vendor items cannot use pickup.
             */
            if (
                $isVendorItem === 'Yes'
                &&
                $shippingModeId === 46
            ) {
                $isPickup = 'No';

                continue;
            }

            if (
                $shippingModeId === 46
            ) {
                $isPickup = 'Yes';
            }

            /*
             * -----------------------------------------------------
             * Check method availability.
             * Preserve the old Max2Day logic exactly.
             * -----------------------------------------------------
             */
            $availableMethod = '';

            if (
                $isTwoDay === 'Yes'
                &&
                $isMaxaromaTwoDelivery === 'Yes'
                &&
                in_array(
                    $shippingModeId,
                    [
                        22,
                        34,
                        29,
                        46,
                    ],
                    true
                )
            ) {
                $availableMethod =
                    $this->checkAvailableShippingMethod(
                        $shippingModeId,
                        $shipCountry,
                        $shipState,
                        $shipZip
                    );
            }
            elseif (
                $isTwoDay === 'No'
                &&
                $isMaxaromaTwoDelivery === 'Yes'
            ) {
                $availableMethod =
                    $this->checkAvailableShippingMethod(
                        $shippingModeId,
                        $shipCountry,
                        $shipState,
                        $shipZip
                    );
            }
            elseif (
                $isTwoDay === 'Yes'
                &&
                $isMaxaromaTwoDelivery === 'No'
                &&
                $shippingModeId !== 29
            ) {
                $availableMethod =
                    $this->checkAvailableShippingMethod(
                        $shippingModeId,
                        $shipCountry,
                        $shipState,
                        $shipZip
                    );
            }
            elseif (
                $isTwoDay === 'No'
                &&
                $isMaxaromaTwoDelivery === 'No'
            ) {
                $availableMethod =
                    $this->checkAvailableShippingMethod(
                        $shippingModeId,
                        $shipCountry,
                        $shipState,
                        $shipZip
                    );
            }

            /*
             * Existing wholesaler override.
             */
            if (
                strtolower(
                    Session::get(
                        'eusertype',
                        ''
                    )
                ) === 'wholesaler'
            ) {
                $availableMethod =
                    $this->checkAvailableShippingMethod(
                        $shippingModeId,
                        $shipCountry,
                        $shipState,
                        $shipZip
                    );
            }

            $availableParts =
                $this->parseAvailableShippingMethod(
                    $availableMethod
                );

            $resolvedShippingModeId =
                $availableParts[
                    'shipping_mode_id'
                ];

            if (
                $resolvedShippingModeId <= 0
            ) {
                continue;
            }

            $normalWeight =
                $availableParts[
                    'normal_weight'
                ];

            $lightWeight =
                $availableParts[
                    'light_weight'
                ];

            $heavyWeight =
                $availableParts[
                    'heavy_weight'
                ];

            /*
             * -----------------------------------------------------
             * Max2Day messages
             * -----------------------------------------------------
             */
            if (
                $addressCheck === 'No'
                &&
                $isMaxaromaTwoDelivery === 'No'
                &&
                $isTwoDay === 'No'
                &&
                strtolower(
                    Session::get(
                        'eusertype',
                        ''
                    )
                ) !== 'wholesaler'
            ) {
                $messages[] =
                    'Your order is not eligible for Max2days shipping as one of the item is not eligible.<br/>Since you are shipping to a different address this order is not eligible for Max2days shipping option.';
            }
            elseif (
                $addressCheck === 'No'
                &&
                $isTwoDay === 'No'
                &&
                strtolower(
                    Session::get(
                        'eusertype',
                        ''
                    )
                ) !== 'wholesaler'
            ) {
                $messages[] =
                    'Since you are shipping to a different address this order is not eligible for Max2days shipping option.';
            }
            elseif (
                $addressCheck === 'No'
                &&
                $isMaxaromaTwoDelivery === 'Yes'
                &&
                $isMaxTwoItem === 'Yes'
                &&
                $isMax2DayVal === 'No'
                &&
                strtolower(
                    Session::get(
                        'eusertype',
                        ''
                    )
                ) !== 'wholesaler'
                &&
                $isVendorItem === 'No'
            ) {
                $messages[] =
                    'Great News, Your order was Upgraded to Free Second Day Shipping Service.';

                $messageSuccess = 1;
            }
            elseif (
                $addressCheck === 'No'
                &&
                $isMaxaromaTwoDelivery === 'Yes'
                &&
                $isMaxTwoItem === 'Yes'
                &&
                $isMax2DayVal === 'No'
                &&
                strtolower(
                    Session::get(
                        'eusertype',
                        ''
                    )
                ) !== 'wholesaler'
                &&
                $isVendorItem === 'Yes'
            ) {
                $messages[] =
                    'Great News, Your order was Upgraded to Free Second Day Shipping Service.Order was Upgraded to Free 2DAY Shipping Service, Please add 2 Extra Business days because of some items in your cart.';

                $messageSuccess = 1;
            }

            /*
             * PO BOX + Max2Day.
             */
            if (
                $addressCheck === 'Yes'
                &&
                $resolvedShippingModeId === 22
            ) {
                $messages[] =
                    'Your order is not eligible for Max2days shipping because our carrier does not ship using this service to PO BOX Addresses';
            }

            /*
             * -----------------------------------------------------
             * Calculate charge
             * -----------------------------------------------------
             */
            $chargeResult =
                $this->calculateAvailableShippingCharge(
                    $shipZip,
                    $shipState,
                    $shipCountry,
                    $resolvedShippingModeId
                );

            $chargeParts =
                explode(
                    '###',
                    $chargeResult
                );

            $tempCharge =
                isset(
                    $chargeParts[0]
                )
                    ? (float) $chargeParts[0]
                    : 0;

            $days =
                isset(
                    $chargeParts[1]
                )
                    ? (int) $chargeParts[1]
                    : 0;

            /*
             * -----------------------------------------------------
             * Vendor +3 days
             * -----------------------------------------------------
             */
            $vendorDays = 0;

            if (
                $isVendorItem === 'Yes'
                &&
                $isPerfumePW === 'Yes'
            ) {
                $days += 3;
                $vendorDays = 3;
            }
            elseif (
                $isVendorItem === 'Yes'
                &&
                (
                    $isCosmo === 'Yes'
                    ||
                    $isPCA === 'Yes'
                    ||
                    $isNandansons === 'Yes'
                    ||
                    $isPerfumePW === 'Yes'
                    ||
                    $isND === 'Yes'
                )
            ) {
                $days += 3;
                $vendorDays = 3;
            }

            /*
             * -----------------------------------------------------
             * Shipping weight charges
             * -----------------------------------------------------
             */
            $weightCharges =
                $this->calculateWeightCharges(
                    $normalWeight,
                    $lightWeight,
                    $heavyWeight
                );

            $tempCharge +=
                $weightCharges;

            /*
             * -----------------------------------------------------
             * Current time cutoff.
             *
             * Existing logic:
             * after 2 PM on weekdays → +1 day.
             * -----------------------------------------------------
             */
            $days =
                $this->applyTimeCutoff(
                    $days
                );

            /*
             * Weekend adjustment for methods
             * 33 / 34 / 29.
             */
            $days =
                $this->applyWeekendAdjustment(
                    $days,
                    $resolvedShippingModeId
                );

            /*
             * -----------------------------------------------------
             * Estimated delivery date
             * -----------------------------------------------------
             */
            $delivery =
                $this->calculateDeliveryDate(
                    $days
                );

            /*
             * -----------------------------------------------------
             * Selected method
             * -----------------------------------------------------
             */
            $checked = '';

            $active = '';

            if (
                $selectedShippingMethodId <= 0
            ) {
                if (
                    $resolvedShippingModeId === 29
                ) {
                    $checked = 'checked';
                    $active = 'active';
                }
                elseif (
                    count($chargeInfo) === 0
                ) {
                    $checked = 'checked';
                    $active = 'active';
                }
            }
            elseif (
                $selectedShippingMethodId ===
                $resolvedShippingModeId
            ) {
                $checked = 'checked';
                $active = 'active';
            }

            /*
             * Existing Max2Day tracking.
             */
            if (
                $resolvedShippingModeId === 29
                &&
                $tempCharge <= 0
            ) {
                $max2Days = 1;
            }

            /*
             * -----------------------------------------------------
             * Free shipping coupon.
             * -----------------------------------------------------
             */
            $tempCharge =
                $this->applyFreeShippingCoupon(
                    $tempCharge,
                    $resolvedShippingModeId
                );

            /*
             * -----------------------------------------------------
             * Final shipping method.
             * -----------------------------------------------------
             */
            $chargeInfo[] = [
                'active' =>
                    $active,

                'days' =>
                    $days,

                'charge' =>
                    $this->formatPrice(
                        $tempCharge
                    ),

                'chargewithoutformat' =>
                    $tempCharge,

                'checked' =>
                    $checked,

                'shipping_mode_id' =>
                    $resolvedShippingModeId,

                'display_date' =>
                    date(
                        'D, F d',
                        strtotime(
                            $delivery['date']
                        )
                    ),

                'estdate' =>
                    date(
                        'm/d/Y',
                        strtotime(
                            $delivery['date']
                        )
                    ),

                'method_name' =>
                    $shippingMode->type,

                'charge_str' =>
                    $tempCharge > 0
                        ? $this->formatPrice(
                            $tempCharge,
                            true
                        )
                        : '<span class="clsfree">Free</span>',

                'estimateShipDate' =>
                    $delivery['message'],

                'dateSort' =>
                    $delivery['date'],
            ];

            /*
             * Vendor popup session values.
             */
            if (
                $active === 'active'
                &&
                $isVendorItem === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.VendorShippingDateVal.setVendorshipDay',
                    $vendorDays
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * Remove Max2Day method when free Max2Day exists.
         * ---------------------------------------------------------
         */
        if (
            count($chargeInfo) > 0
        ) {
            $newMethods = [];

            foreach (
                $chargeInfo as $method
            ) {
                if (
                    $max2Days === 1
                    &&
                    (int)
                    $method[
                        'shipping_mode_id'
                    ] === 22
                ) {
                    continue;
                }

                if (
                    (int)
                    $method[
                        'shipping_mode_id'
                    ] === 22
                    &&
                    $isPickup === 'Yes'
                ) {
                    $method['dateSort'] = -1;
                }

                $newMethods[] = $method;
            }

            $chargeInfo = $newMethods;
        }

        /*
         * ---------------------------------------------------------
         * APO/FPO:
         * only shipping method 47.
         * ---------------------------------------------------------
         */
        if (
            $apoFpo === 'Yes'
            &&
            count($chargeInfo) > 0
        ) {
            $apoMethods = [];

            foreach (
                $chargeInfo as $method
            ) {
                if (
                    (int)
                    $method[
                        'shipping_mode_id'
                    ] === 47
                ) {
                    $apoMethods[] = $method;
                }
            }

            if (
                count($apoMethods) > 0
            ) {
                $chargeInfo =
                    $apoMethods;

                Session::put(
                    'ShoppingCart.Shipping.ShippingMethodID',
                    47
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * Sort by estimated delivery.
         * ---------------------------------------------------------
         */
        if (
            count($chargeInfo) > 0
        ) {
            $sortDates =
                array_column(
                    $chargeInfo,
                    'dateSort'
                );

            array_multisort(
                $sortDates,
                SORT_ASC,
                $chargeInfo
            );
        }

        /*
         * ---------------------------------------------------------
         * Select first shipping method if none selected.
         * ---------------------------------------------------------
         */
        if (
            count($chargeInfo) > 0
        ) {
            $this->setDefaultShippingMethod(
                $chargeInfo,
                $shipCountry,
                $shipState,
                $shipZip,
                $shipCity,
                $onlyGCPurchased
            );
        }

        addLog(
            'ShippingService',
            [
                'shipping_address' =>
                    $shippingAddress,

                'shipping_methods' =>
                    json_encode(
                        $chargeInfo
                    ),

                'messages' =>
                    json_encode(
                        array_unique(
                            $messages
                        )
                    ),
            ]
        );

        return [
            'shipping_methods' =>
                $chargeInfo,

            'messages' =>
                array_values(
                    array_unique(
                        $messages
                    )
                ),

            'datediff' =>
                $this->getShippingCutoffDifference(),

            'message_success' =>
                $messageSuccess,

            'is_pickup' =>
                $isPickup,

            'apo_fpo' =>
                $apoFpo,

            'address_check' =>
                $addressCheck,
        ];
    }

    /**
     * Get the remaining time until the existing 2 PM
     * shipping cutoff.
     */
    protected function getShippingCutoffDifference(): string
    {
        $now = date_create(
            date('Y-m-d H:i:s')
        );

        $cutoff = date_create(
            date('Y-m-d 14:00:00')
        );

        if ($now >= $cutoff) {
            $cutoff = date_create(
                date(
                    'Y-m-d 14:00:00',
                    strtotime('+1 day')
                )
            );
        }

        while (
            $cutoff->format('N') >= 6
        ) {
            $cutoff->modify('+1 day');
        }

        $diff = $now->diff($cutoff);

        return $diff->format(
            '%h hours %i minutes'
        );
    }

    /**
     * Set selected shipping method.
     *
     * This replaces the calculation portion of the old
     * SetShippingMethod() method.
     */
    public function setShippingMethod(
        int $shippingMethodId,
        array $address = [],
        array $flags = []
    ): array {
		
        $shippingAddress =
            $this->resolveAddress(
                $address
            );

        $result =
            $this->checkShippingMethod(
                $shippingMethodId,
                $shippingAddress['country'],
                $shippingAddress['state'],
                $shippingAddress['zip'],
                $flags['IsCosmo'] ?? 'No',
                $flags['IsNandansons'] ?? 'No',
                $flags['IsPerfumePW'] ?? 'No',
                $flags['IsPCA'] ?? 'No',
                $flags['IsND'] ?? 'No',
                $flags['IsVenderItem'] ?? 'No'
            );

        if (
            $result['status'] !== 'success'
        ) {
            return $result;
        }

        $this->calculateShippingCharge(
            $shippingAddress['zip'],
            $shippingAddress['state'],
            $shippingAddress['country'],
            $shippingMethodId
        );
        /*
         * Keep the existing session values.
         */
        Session::put(
            'ShoppingCart.Shipping.ShippingMethodID',
            $shippingMethodId
        );

        if (
            isset(
                $result['ShippingMethodName']
            )
        ) {
            Session::put(
                'ShoppingCart.Shipping.ShippingMethodName',
                $result['ShippingMethodName']
            );
        }

        return [
            'status' =>
                'success',

            'shipping_method_id' =>
                $shippingMethodId,

            'shipping_charge' =>
                (float)
                Session::get(
                    'ShoppingCart.Shipping.ShippingCharge',
                    0
                ),

            'shipping_days' =>
                Session::get(
                    'ShoppingCart.Shipping.ShippingDays'
                ),

            'estimated_delivery_date' =>
                Session::get(
                    'ShoppingCart.EstimatedDeliveryDate'
                ),
        ];
    }

    /**
     * Exact migration of CheckShippingMethod().
     */
    public function checkShippingMethod(
        int $shippingModeId,
        string $shipCountry,
        string $shipState,
        string $shipZip,
        string $isCosmo = 'No',
        string $isNandansons = 'No',
        string $isPerfumePW = 'No',
        string $isPCA = 'No',
        string $isND = 'No',
        string $isVendorItem = 'No'
    ): array {
        $shippingModeId =
            (int) $shippingModeId;

        $shippingMethod =
            ShippingMode::where(
                'shipping_mode_id',
                '=',
                $shippingModeId
            )->first();

        if (
            !$shippingMethod
            ||
            trim($shipCountry) === ''
        ) {
            return [
                'status' =>
                    'fail',

                'error' =>
                    'The shipping method you selected is not available to your destination. Please select a different method.',
            ];
        }

        $rule =
            $this->findShippingRule(
                $shippingModeId,
                $shipCountry,
                $shipState,
                $shipZip
            );

        if (
            !$rule
        ) {
            return [
                'status' =>
                    'fail',

                'error' =>
                    'The shipping method you selected is not available to your destination. Please select a different method.',
            ];
        }

        Session::put(
            'ShoppingCart.Shipping.ShippingMethodName',
            $shippingMethod->type
        );

        Session::put(
            'ShoppingCart.Shipping.ShippingMethodID',
            $shippingMethod->shipping_mode_id
        );

        $days = 0;

        if (
            (
                $isVendorItem === 'Yes'
                &&
                $isPerfumePW === 'Yes'
            )
            ||
            (
                $isVendorItem === 'Yes'
                &&
                (
                    $isCosmo === 'Yes'
                    ||
                    $isPCA === 'Yes'
                    ||
                    $isNandansons === 'Yes'
                    ||
                    $isPerfumePW === 'Yes'
                    ||
                    $isND === 'Yes'
                )
            )
        ) {
            $days =
                $this->getShippingChargeDays(
                    $shipZip,
                    $shipState,
                    $shipCountry,
                    $shippingModeId
                );

            $days += 3;
        }

        /*
         * Vendor popup name.
         */
        if (
            $isVendorItem === 'Yes'
        ) {
            if (
                $isCosmo === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.VendorShippingDateVal.setVendorNameVal',
                    'IsCosmo'
                );
            }

            if (
                $isNandansons === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.VendorShippingDateVal.setVendorNameVal',
                    'ISNandansons'
                );
            }

            if (
                $isPCA === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.VendorShippingDateVal.setVendorNameVal',
                    'IsPCA'
                );
            }

            if (
                $isPerfumePW === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.VendorShippingDateVal.setVendorNameVal',
                    'IsPWW'
                );
            }

            if (
                $isND === 'Yes'
            ) {
                Session::put(
                    'ShoppingCart.VendorShippingDateVal.setVendorNameVal',
                    'IsND'
                );
            }
        }

        if (
            $days > 0
        ) {
            $startDate =
                date('Y-m-d');

            $endDate =
                date(
                    'Y-m-d',
                    strtotime(
                        '+' . $days . 'days'
                    )
                );

            $weekendDays =
                $this->countWeekendDays(
                    $startDate,
                    $endDate
                );

            $holidayDays =
                ShippingHoliday::whereBetween(
                    'holiday_date',
                    [
                        $startDate,
                        $endDate,
                    ]
                )
                ->where(
                    'holiday_status',
                    '=',
                    '1'
                )
                ->where(
                    'holiday_date',
                    '!=',
                    date('Y-m-d')
                )
                ->count();

            $exactShipDay =
                $days
                +
                $weekendDays
                +
                $holidayDays;

            $approxShipDate =
                date(
                    'Y-m-d',
                    strtotime(
                        '+' .
                        $exactShipDay .
                        'days'
                    )
                );

            $extraDays = 0;

            $day =
                $this->checkDay(
                    $approxShipDate
                );

            if (
                $day === 'saturday'
            ) {
                $extraDays = 2;
            }
            elseif (
                $day === 'sunday'
            ) {
                $extraDays = 1;
            }

            $days =
                $exactShipDay
                +
                $extraDays;

            Session::put(
                'ShoppingCart.VendorShippingDateVal.setVendorshipDay',
                $days
            );

            $date =
                date(
                    'M d',
                    strtotime(
                        '+' . $days . 'days'
                    )
                );

            Session::put(
                'ShoppingCart.Shipping.ShippingDays',
                'Estimated Delivery on or before <b>' .
                $date .
                '</b>'
            );
        }
		
		
 
		
        return [
            'status' =>
                'success',

            'ShipMethodID' =>
                $shippingModeId,

            'ShippingMethodName' =>
                $shippingMethod->type,

            'days' =>
                $days,
        ];
    }

    /**
     * Exact migration of CalculateShippingCharge().
     */
    public function calculateShippingCharge(
        string $shipZip,
        string $shipState,
        string $shipCountry,
        int $shippingModeId
    ): float {
        $subTotal =
            $this->checkoutTotalsService
                ->getDiscountedSubTotal();

        $shipCountry =
            substr(
                $shipCountry,
                0,
                2
            );

        $shippingModeId =
            (int)
            $shippingModeId;

        $rule =
            $this->findShippingRule(
                $shippingModeId,
                $shipCountry,
                $shipState,
                $shipZip
            );

        if (
            !$rule
        ) {
            Session::put(
                'ShoppingCart.Shipping.ShippingCharge',
                0
            );

            return 0.0;
        }

        $shippingRuleId =
            $rule->shipping_rule_id;

        $ruleType =
            $rule->rule_type;

        $rateQuery =
            ShippingRate::where(
                'shipping_rule_id',
                '=',
                $shippingRuleId
            );

        if (
            $ruleType == 1
        ) {
            $rateQuery
                ->where(
                    'order_amount',
                    '<=',
                    $subTotal
                );
        }
        elseif (
            $ruleType == 0
            ||
            $ruleType == 2
        ) {
            $totalItem =
                (int)
                Session::get(
                    'ShoppingCart.TotalItemInCart',
                    0
                );

            $rateQuery
                ->where(
                    'order_amount',
                    '<=',
                    $totalItem
                );
        }

        $rowRate =
            $rateQuery
                ->orderBy(
                    'order_amount',
                    'desc'
                )
                ->limit(1)
                ->first();

        $charge =
            $rowRate
                ? (float)
                $rowRate->charge
                : 0.0;

        /*
         * Free shipping.
         */
        if (
            $rule->is_free_ship === 'Yes'
            &&
            $rule->free_ship_amt <= $subTotal
        ) {
            $charge = 0;
        }

        /*
         * Proportional shipping.
         */
        $totalItem =
            (int)
            Session::get(
                'ShoppingCart.TotalItemInCart',
                0
            );

        if (
            $rule->prop_item > 0
            &&
            $rule->prop_charge > 0
            &&
            $totalItem >= $rule->prop_item
        ) {
            $extraItem =
                (
                    $totalItem
                    -
                    $rule->prop_item
                )
                +
                1;

            $charge +=
                (
                    $rule->prop_charge
                    *
                    $extraItem
                );
        }

        /*
         * Normal / Light / Heavy item charges.
         */
        $normalCharge =
            (float)
            (
                $rule->normal_charge ?: 0
            );

        $lightCharge =
            (float)
            (
                $rule->light_charge ?: 0
            );

        $heavyCharge =
            (float)
            (
                $rule->heavy_charge ?: 0
            );

        $weightCharges =
            $this->calculateWeightCharges(
                $normalCharge,
                $lightCharge,
                $heavyCharge
            );

        $charge +=
            $weightCharges;

        /*
         * Coupon free shipping.
         */
        $charge =
            $this->applyFreeShippingCoupon(
                $charge,
                $shippingModeId
            );

        Session::put(
            'ShoppingCart.Shipping.ShippingCharge',
            $charge
        );

        addLog(
            'CalculateShippingCharge',
            [
                'shipping_mode_id' =>
                    $shippingModeId,

                'shipping_rule_id' =>
                    $shippingRuleId,

                'subtotal_after_discount' =>
                    $subTotal,

                'shipping_charge' =>
                    $charge,
            ]
        );

        return $charge;
    }

    /**
     * Find shipping rule in exact priority:
     *
     * 1. ZIP + State + Country
     * 2. ZIP + Country
     * 3. State + Country
     * 4. Country only
     */
    protected function findShippingRule(
        int $shippingModeId,
        string $shipCountry,
        string $shipState,
        string $shipZip
    ) {
        if (
            trim($shipCountry) === ''
        ) {
            return null;
        }

        $rule =
            ShippingRule::where(
                'shipping_mode_id',
                '=',
                $shippingModeId
            )
            ->where(
                'zipcode_to',
                '>=',
                $shipZip
            )
            ->where(
                'zipcode_from',
                '<=',
                $shipZip
            )
            ->where(
                'state',
                'like',
                '%' . $shipState . '%'
            )
            ->where(
                'country',
                'like',
                '%' . $shipCountry . '%'
            )
            ->first();

        if (
            $rule
        ) {
            return $rule;
        }

        $rule =
            ShippingRule::where(
                'shipping_mode_id',
                '=',
                $shippingModeId
            )
            ->where(
                'zipcode_to',
                '>=',
                $shipZip
            )
            ->where(
                'zipcode_from',
                '<=',
                $shipZip
            )
            ->where(
                'country',
                'like',
                '%' . $shipCountry . '%'
            )
            ->first();

        if (
            $rule
        ) {
            return $rule;
        }

        $rule =
            ShippingRule::where(
                'shipping_mode_id',
                '=',
                $shippingModeId
            )
            ->where(
                'state',
                'like',
                '%' . $shipState . '%'
            )
            ->where(
                'country',
                'like',
                '%' . $shipCountry . '%'
            )
            ->first();

        if (
            $rule
        ) {
            return $rule;
        }

        return ShippingRule::where(
            'shipping_mode_id',
            '=',
            $shippingModeId
        )
        ->where(
            'state',
            '=',
            ''
        )
        ->where(
            'zipcode_to',
            '=',
            ''
        )
        ->where(
            'zipcode_from',
            '=',
            ''
        )
        ->where(
            'country',
            'like',
            '%' . $shipCountry . '%'
        )
        ->first();
    }

    /**
     * Resolve shipping address.
     *
     * One Page Checkout:
     * address is passed directly.
     *
     * Legacy compatibility:
     * fallback to session.
     */
    protected function resolveAddress(
        array $address
    ): array {
        return [
            'country' =>
                trim(
                    $address['country']
                    ??
                    Session::get(
                        'ShoppingCart.ShippingAddress.country',
                        ''
                    )
                ),

            'state' =>
                trim(
                    $address['state']
                    ??
                    Session::get(
                        'ShoppingCart.ShippingAddress.state',
                        ''
                    )
                ),

            'zip' =>
                trim(
                    $address['zip']
                    ??
                    Session::get(
                        'ShoppingCart.ShippingAddress.zip',
                        ''
                    )
                ),

            'city' =>
                trim(
                    $address['city']
                    ??
                    Session::get(
                        'ShoppingCart.ShippingAddress.city',
                        ''
                    )
                ),

            'address1' =>
                trim(
                    $address['address1']
                    ??
                    Session::get(
                        'ShoppingCart.ShippingAddress.address1',
                        ''
                    )
                ),

            'address2' =>
                trim(
                    $address['address2']
                    ??
                    Session::get(
                        'ShoppingCart.ShippingAddress.address2',
                        ''
                    )
                ),
        ];
    }

    /**
     * Resolve shipping flags.
     */
    protected function resolveShippingFlags(
        array $flags
    ): array {
        $keys = [
            'IsMaxaromaTwoDelivery',
            'ISMaxTwoItem',
            'IsVenderItem',
            'IsCosmo',
            'IsNandansons',
            'IsPerfumePW',
            'IsPCA',
            'IsND',
            'ISMax2dayVal',
            'onlyGCPurchased',
        ];

        $result = [];

        foreach (
            $keys as $key
        ) {
            $result[$key] =
                $flags[$key]
                ??
                Session::get(
                    'ShoppingCart.' . $key,
                    'No'
                );
        }

        return $result;
    }

    /**
     * PO BOX detection.
     */
    protected function isPoBoxAddress(
        string $address1,
        string $address2
    ): bool {
        $pattern =
            '/\bP\.?\s*O\.?(\s*B\.?\s*O\.?\s*X|\s*Box|\d+)?\b/i';

        return
            preg_match(
                $pattern,
                $address1
            ) === 1
            ||
            preg_match(
                $pattern,
                $address2
            ) === 1;
    }

    /**
     * APO / FPO detection.
     */
    protected function isApoFpoAddress(
        string $address1,
        string $address2,
        string $city
    ): bool {
        foreach (
            [
                $address1,
                $address2,
                $city,
            ] as $value
        ) {
            if (
                preg_match(
                    '/apo|fpo/i',
                    $value
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate item weight charges.
     */
    protected function calculateWeightCharges(
        float $normalCharge,
        float $lightCharge,
        float $heavyCharge
    ): float {
        $normalTotal = 0;
        $lightTotal = 0;
        $heavyTotal = 0;

        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        foreach (
            $cart as $item
        ) {
            $qty =
                (int)
                (
                    $item['Qty']
                    ??
                    0
                );

            $weight =
                $item['shipping_weightVal']
                ??
                '';

            if (
                $weight === 'Normal'
                &&
                $normalCharge > 0
            ) {
                $normalTotal +=
                    $normalCharge *
                    $qty;
            }

            if (
                $weight === 'Light'
                &&
                $lightCharge > 0
            ) {
                $lightTotal +=
                    $lightCharge *
                    $qty;
            }

            if (
                $weight === 'Heavy'
                &&
                $heavyCharge > 0
            ) {
                $heavyTotal +=
                    $heavyCharge *
                    $qty;
            }
        }

        return
            $normalTotal
            +
            $lightTotal
            +
            $heavyTotal;
    }

    /**
     * Apply the old 2 PM cutoff.
     */
    protected function applyTimeCutoff(
        int $days
    ): int {
        $hour =
            (int)
            date('H');

        $day =
            date('l');

        if (
            $hour >= 14
            &&
            $day !== 'Saturday'
            &&
            $day !== 'Sunday'
        ) {
            $days++;
        }

        return $days;
    }

    /**
     * Existing weekend rule for methods
     * 33 / 34 / 29.
     */
    protected function applyWeekendAdjustment(
        int $days,
        int $shippingModeId
    ): int {
        $day =
            date('l');

        if (
            in_array(
                $shippingModeId,
                [
                    33,
                    34,
                    29,
                ],
                true
            )
        ) {
            if (
                $day === 'Saturday'
            ) {
                $days += 2;
            }
            elseif (
                $day === 'Sunday'
            ) {
                $days += 1;
            }
        }

        return $days;
    }

    /**
     * Calculate estimated delivery date.
     */
    protected function calculateDeliveryDate(
        int $days
    ): array {
        if (
            $days <= 0
        ) {
            return [
                'date' =>
                    date('Y-m-d'),

                'message' =>
                    '',
            ];
        }

        $holidayDates =
            ShippingHoliday::where(
                'holiday_status',
                '=',
                '1'
            )
            ->where(
                'holiday_date',
                '>',
                date('Y-m-d')
            )
            ->pluck(
                'holiday_date'
            )
            ->map(
                fn ($date) =>
                    date(
                        'Y-m-d',
                        strtotime($date)
                    )
            )
            ->toArray();

        $currentDate =
            date('Y-m-d');

        $k = $days;

        for (
            $d = 1;
            $d <= $k;
            $d++
        ) {
            $date =
                date(
                    'Y-m-d',
                    strtotime(
                        '+' .
                        $d .
                        'days'
                    )
                );

            $day =
                $this->checkDay(
                    $date
                );

            if (
                $day === 'saturday'
                ||
                $day === 'sunday'
            ) {
                $k++;
            }
            elseif (
                in_array(
                    $date,
                    $holidayDates,
                    true
                )
            ) {
                $k++;
            }
        }

        $estimatedDate =
            date(
                'Y-m-d',
                strtotime(
                    '+' .
                    $k .
                    'days'
                )
            );

        return [
            'date' =>
                $estimatedDate,

            'message' =>
                'Estimated Delivery on or before <b>' .
                date(
                    'M d',
                    strtotime(
                        $estimatedDate
                    )
                ) .
                '</b>',
        ];
    }

    /**
     * Count weekend days.
     */
    protected function countWeekendDays(
        string $start,
        string $end
    ): int {
        $count = 0;

        $current =
            strtotime($start);

        $endTimestamp =
            strtotime($end);

        while (
            $current <= $endTimestamp
        ) {
            $day =
                date(
                    'D',
                    $current
                );

            if (
                $day === 'Sat'
                ||
                $day === 'Sun'
            ) {
                $count++;
            }

            $current +=
                24 * 60 * 60;
        }

        return $count;
    }

    /**
     * Check day.
     */
    protected function checkDay(
        string $date
    ): ?string {
        $weekday =
            strtolower(
                date(
                    'l',
                    strtotime($date)
                )
            );

        if (
            $weekday === 'saturday'
            ||
            $weekday === 'sunday'
        ) {
            return $weekday;
        }

        return null;
    }

    /**
     * Free shipping coupon logic.
     */
    protected function applyFreeShippingCoupon(
        float $charge,
        int $shippingModeId
    ): float {
        $modeIds =
            Session::get(
                'ShoppingCart.PromoCoupon.FreeShippingCouponModeID',
                []
            );

        if (
            !is_array($modeIds)
        ) {
            $modeIds =
                explode(
                    ',',
                    (string) $modeIds
                );
        }

        if (
            Session::get(
                'ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag'
            ) === 'Yes'
            &&
            in_array(
                $shippingModeId,
                $modeIds
            )
            &&
            Session::get(
                'ShoppingCart.PromoCoupon.FreeShipping'
            ) === 'Yes'
        ) {
            return 0.0;
        }

        if (
            Session::get(
                'ShoppingCart.PromoCoupon.FreeShipping'
            ) === 'Yes'
            &&
            $shippingModeId ==
            Session::get(
                'ShoppingCart.PromoCoupon.FreeShippingModeID'
            )
        ) {
            return 0.0;
        }

        return $charge;
    }

    /**
     * Set first/default selected shipping method.
     */
    protected function setDefaultShippingMethod(
        array $methods,
        string $shipCountry,
        string $shipState,
        string $shipZip,
        string $shipCity,
        $onlyGCPurchased
    ): void {
        $selectedId =
            (int)
            Session::get(
                'ShoppingCart.Shipping.ShippingMethodID',
                0
            );

        $selected = null;

        if (
            $selectedId > 0
        ) {
            foreach (
                $methods as $method
            ) {
                if (
                    (int)
                    $method[
                        'shipping_mode_id'
                    ] === $selectedId
                ) {
                    $selected = $method;
                    break;
                }
            }
        }

        if (
            !$selected
        ) {
            $selected =
                $methods[0];
        }

        Session::put(
            'ShoppingCart.EstimatedDeliveryDate',
            $selected['estdate']
        );

        Session::put(
            'ShoppingCart.Shipping.ShippingMethodName',
            $selected['method_name']
        );

        Session::put(
            'ShoppingCart.Shipping.ShippingMethodID',
            $selected['shipping_mode_id']
        );

        Session::put(
            'ShoppingCart.VendorShippingDateVal.setVendorshipDay',
            $selected['days']
        );

        Session::put(
            'ShoppingCart.Shipping.ShippingDays',
            $selected['estimateShipDate']
        );

        Session::put(
            'ShoppingCart.Shipping.ShippingCharge',
            NumberFormat(
                $selected[
                    'chargewithoutformat'
                ]
            )
        );
    }

    /**
     * Parse the existing:
     *
     * shipping_mode_id###normal###light###heavy
     */
    protected function parseAvailableShippingMethod(
        $value
    ): array {
        if (
            is_array($value)
        ) {
            return [
                'shipping_mode_id' =>
                    (int)
                    (
                        $value[0]
                        ??
                        0
                    ),

                'normal_weight' =>
                    (float)
                    (
                        $value[1]
                        ??
                        0
                    ),

                'light_weight' =>
                    (float)
                    (
                        $value[2]
                        ??
                        0
                    ),

                'heavy_weight' =>
                    (float)
                    (
                        $value[3]
                        ??
                        0
                    ),
            ];
        }

        if (
            !is_string($value)
            ||
            $value === ''
        ) {
            return [
                'shipping_mode_id' =>
                    0,

                'normal_weight' =>
                    0,

                'light_weight' =>
                    0,

                'heavy_weight' =>
                    0,
            ];
        }

        $parts =
            explode(
                '###',
                $value
            );

        return [
            'shipping_mode_id' =>
                (int)
                (
                    $parts[0]
                    ??
                    0
                ),

            'normal_weight' =>
                (float)
                (
                    $parts[1]
                    ??
                    0
                ),

            'light_weight' =>
                (float)
                (
                    $parts[2]
                    ??
                    0
                ),

            'heavy_weight' =>
                (float)
                (
                    $parts[3]
                    ??
                    0
                ),
        ];
    }

    /**
     * -------------------------------------------------------------
     * LEGACY DEPENDENCIES
     * -------------------------------------------------------------
     *
     * IMPORTANT:
     *
     * The complete bodies of these methods are not included in the
     * supplied source excerpt. Do NOT invent their logic here.
     *
     * During migration, move their exact existing implementations
     * into this service.
     */

    protected function checkAvailableShippingMethod(
        int $shippingModeId,
        string $shipCountry,
        string $shipState,
        string $shipZip
    ) {
        $shippingModeId = (int) $shippingModeId;

        $shippingMethod = ShippingMode::where('status', '=', '1')
            ->where('shipping_mode_id', '=', $shippingModeId)
            ->first();

        if (!$shippingMethod || trim($shipCountry) === '') {
            return false;
        }

        // Z + S + C
        $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
            ->where('zipcode_to', '>=', $shipZip)
            ->where('zipcode_from', '<=', $shipZip)
            ->where('state', 'like', '%' . $shipState . '%')
            ->where('country', 'like', '%' . $shipCountry . '%')
            ->first();

        // Z + C
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('zipcode_to', '>=', $shipZip)
                ->where('zipcode_from', '<=', $shipZip)
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        // S + C
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('state', 'like', '%' . $shipState . '%')
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        // Country only
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('state', '=', '')
                ->where('zipcode_to', '=', '')
                ->where('zipcode_from', '=', '')
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        if (!$rule) {
            return false;
        }

        $normalCharge = $rule->normal_charge === '' ? 0 : $rule->normal_charge;
        $lightCharge = $rule->light_charge === '' ? 0 : $rule->light_charge;
        $heavyCharge = $rule->heavy_charge === '' ? 0 : $rule->heavy_charge;

        $shippingModeId = (int) $shippingMethod->shipping_mode_id;

        $deusertype = '';
        if (Session::get('is_dropshipper') === 'Yes') {
            $deusertype = 'Dropshipper';
        }

        if (
            $shippingMethod->eusertype == $deusertype &&
            Session::get('sess_icustomerid') != '' &&
            Session::get('eusertype') == 'Wholesaler'
        ) {
            return $shippingModeId . '###' . $normalCharge . '###' . $lightCharge . '###' . $heavyCharge;
        }

        if (
            $shippingMethod->eusertype == Session::get('eusertype') &&
            Session::get('is_dropshipper') != 'Yes'
        ) {
            return $shippingModeId . '###' . $normalCharge . '###' . $lightCharge . '###' . $heavyCharge;
        }

        if (
            Session::get('sess_icustomerid') == '' &&
            $shippingMethod->eusertype == 'Retailer'
        ) {
            return $shippingModeId . '###' . $normalCharge . '###' . $lightCharge . '###' . $heavyCharge;
        }

        if (
            Session::get('sess_icustomerid') != '' &&
            $shippingMethod->eusertype == 'Retailer' &&
            Session::get('eusertype') == ''
        ) {
            return $shippingModeId . '###' . $normalCharge . '###' . $lightCharge . '###' . $heavyCharge;
        }

        return false;
    }

    protected function calculateAvailableShippingCharge(
        string $shipZip,
        string $shipState,
        string $shipCountry,
        int $shippingModeId,
        string $paypalSubtotal = '',
        int $paypalProductQty = 0
    ) {
        if ($paypalSubtotal != '') {
            $subTotal = $paypalSubtotal;
        } else {
            $subTotal =
                $this->checkoutTotalsService
                    ->getDiscountedSubTotal();
        }

        $shipCountry = substr($shipCountry, 0, 2);
        $shippingModeId = (int) $shippingModeId;

        if ($shipCountry == '') {
            return '0###0';
        }

        // Z + S + C
        $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
            ->where('zipcode_to', '>=', $shipZip)
            ->where('zipcode_from', '<=', $shipZip)
            ->where('state', 'like', '%' . $shipState . '%')
            ->where('country', 'like', '%' . $shipCountry . '%')
            ->first();

        // Z + C
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('zipcode_to', '>=', $shipZip)
                ->where('zipcode_from', '<=', $shipZip)
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        // S + C
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('state', 'like', '%' . $shipState . '%')
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        // Country only
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('state', '=', '')
                ->where('zipcode_to', '=', '')
                ->where('zipcode_from', '=', '')
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        if (!$rule) {
            return '0###0';
        }

        $shippingRuleId = $rule->shipping_rule_id;
        $ruleType = $rule->rule_type;
        $days = $rule->days;

        $totalItem = 0;

        if ($ruleType == 1) {
            $rowRate = ShippingRate::where('shipping_rule_id', '=', $shippingRuleId)
                ->where('order_amount', '<=', $subTotal)
                ->orderBy('order_amount', 'desc')
                ->first();
        } elseif ($ruleType == 0 || $ruleType == 2) {
            if ($paypalSubtotal != '') {
                $totalItem = $paypalProductQty;
            } else {
                $totalItem = Session::get('ShoppingCart.TotalItemInCart', 0);
            }

            $rowRate = ShippingRate::where('shipping_rule_id', '=', $shippingRuleId)
                ->where('order_amount', '<=', $totalItem)
                ->orderBy('order_amount', 'desc')
                ->first();
        } else {
            $rowRate = null;
        }

        $charge = $rowRate ? $rowRate->charge : 0;

        if (
            $rule->is_free_ship == 'Yes' &&
            $rule->free_ship_amt <= $subTotal
        ) {
            $charge = 0;
        }

        $tempShippingCharge = $charge > 0 ? $charge : 0;

        // Proportional shipping
        if (
            $rule->prop_item > 0 &&
            $rule->prop_charge > 0 &&
            $totalItem >= $rule->prop_item
        ) {
            $extraItem = ($totalItem - $rule->prop_item) + 1;
            $propShippingCharge = $rule->prop_charge * $extraItem;
            $tempShippingCharge += $propShippingCharge;
        }

        // Free shipping coupon
        $freeShippingModeIds = Session::get(
            'ShoppingCart.PromoCoupon.FreeShippingCouponModeID',
            []
        );

        if (!is_array($freeShippingModeIds)) {
            $freeShippingModeIds = explode(',', (string) $freeShippingModeIds);
        }

        if (
            Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' &&
            in_array($shippingModeId, $freeShippingModeIds) &&
            Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes'
        ) {
            $tempShippingCharge = 0;
        }

        if (
            Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' &&
            $shippingModeId == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID')
        ) {
            $tempShippingCharge = 0;
        }

        return $tempShippingCharge . '###' . $days;
    }

    protected function getShippingChargeDays(
        string $shipZip,
        string $shipState,
        string $shipCountry,
        int $shippingModeId
    ): int {
        $subTotal =
            $this->checkoutTotalsService
                ->getDiscountedSubTotal();

        $shipCountry = substr($shipCountry, 0, 2);
        $shippingModeId = (int) $shippingModeId;

        if ($shipCountry == '') {
            return 0;
        }

        // Z + S + C
        $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
            ->where('zipcode_to', '>=', $shipZip)
            ->where('zipcode_from', '<=', $shipZip)
            ->where('state', 'like', '%' . $shipState . '%')
            ->where('country', 'like', '%' . $shipCountry . '%')
            ->first();

        // Z + C
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('zipcode_to', '>=', $shipZip)
                ->where('zipcode_from', '<=', $shipZip)
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        // S + C
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('state', 'like', '%' . $shipState . '%')
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        // Country only
        if (!$rule) {
            $rule = ShippingRule::where('shipping_mode_id', '=', $shippingModeId)
                ->where('state', '=', '')
                ->where('zipcode_to', '=', '')
                ->where('zipcode_from', '=', '')
                ->where('country', 'like', '%' . $shipCountry . '%')
                ->first();
        }

        if (!$rule) {
            return 0;
        }

        return (int) $rule->days;
    }

    /**
     * Price formatting helper.
     *
     * Uses existing global helpers when available.
     */
    protected function formatPrice(
        float $amount,
        bool $withFormatting = false
    ): string {
        if (
            function_exists('Price')
        ) {
            return Price(
                $amount,
                $withFormatting
            );
        }

        return number_format(
            $amount,
            2,
            '.',
            ''
        );
    }
}
