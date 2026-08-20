# Maxaroma – One Page Checkout Refactor Structure

## Objective

Build a modular One Page Checkout while preserving current working behavior.

> Truth Mode: do not guess missing business logic, do not pull legacy logic into the new checkout without proof, and commonize only genuinely shared logic.

## High-Level Architecture

```text
Designer HTML
     ↓
checkout.js
     ↓
Checkout Controllers
     ↓
CheckoutService
     ↓
CheckoutTotalsService
     ├── DiscountService
     ├── ShippingService
     ├── TaxService
     ├── ShippingInsuranceService
     ├── ShippingSignatureService
     ├── GiftWrappingService
     └── PaymentAvailabilityService
```

## Controllers

```text
app/Http/Controllers/Checkout/
├── CheckoutController.php
├── CheckoutCartController.php
├── CheckoutTotalsController.php
├── CheckoutAddressController.php
├── CheckoutShippingController.php
├── CheckoutDiscountController.php
└── CheckoutPaymentController.php
```

| Controller | Responsibility |
|---|---|
| CheckoutController | Main checkout orchestration |
| CheckoutCartController | Add / Update / Remove / Clear |
| CheckoutTotalsController | Checkout totals |
| CheckoutAddressController | Address operations |
| CheckoutShippingController | Shipping methods |
| CheckoutDiscountController | Coupon / reward |
| CheckoutPaymentController | Payment availability |

Controllers stay thin; business logic belongs in services.

## Services

```text
app/Services/
├── Checkout/
│   ├── CheckoutService.php
│   ├── CheckoutTotalsService.php
│   └── PaymentAvailabilityService.php
├── Cart/
│   ├── CartService.php
│   └── CartCalculatorService.php
├── Discount/
│   ├── DiscountService.php
│   ├── CouponService.php
│   └── DiscountAllocationService.php
├── Shipping/
│   ├── ShippingService.php
│   ├── ShippingInsuranceService.php
│   └── ShippingSignatureService.php
└── Gift/
    └── GiftWrappingService.php
```

## Checkout Flow

```text
refresh()
  ↓
Tax
  ↓
Insurance
  ↓
CheckoutTotalsService
  ↓
PaymentAvailabilityService
  ↓
Checkout Response
```

## Totals

`CheckoutTotalsService` is the central totals layer.

```text
Subtotal
- Total Discount
+ Shipping
+ Tax
+ Gift Wrapping
+ Shipping Signature
+ Shipping Insurance
= NetTotal
```

Common calculations:

```text
getTotalDiscount()
getDiscountedSubTotal()
getNetTotal()
getNetTotalExcludingCharges()
getSignatureEligibleAmount()
```

Only genuinely shared calculations should be commonized.

## Discount Architecture

`DiscountService` is the common source for active discount totals:

```text
Auto Discount
Yotpo Reward
Quantity Discount
Coupon Discount
Gift Certificate
Auto Refer
Auto Reward
Credit Limit
BOGO
```

`CouponService` owns:

```text
Apply Coupon
Remove Coupon
Validate Coupon
Coupon Free Gift trigger
Coupon session/cart updates
```

Flow:

```text
checkout.js
 ↓
/checkout/discount/apply
 ↓
CheckoutDiscountController
 ↓
CouponService
 ↓
CheckoutService::refresh()
```

## Cart Architecture

```text
checkout.js
 ↓
CheckoutCartController
 ↓
CartService
 ↓
CartCalculatorService
 ↓
Session Cart
```

Operations:

```text
Add
Update
Remove
Clear
```

After successful mutation:

```text
CartService
 ↓
CheckoutService::refresh('cart')
 ↓
Fresh checkout totals
 ↓
JSON response
 ↓
checkout.js
```

Backend is the source of truth for totals.

## Shipping

```text
CheckoutShippingController
 ↓
ShippingService
 ↓
Shipping methods / charge
 ↓
CheckoutTotalsService
```

Shipping eligibility may use:

```text
Subtotal - Total Discount
= DiscountedSubTotal
```

This is NOT `NetTotal`.

## Tax

Tax remains owned by `TaxService`.

```text
Checkout Address
 ↓
TaxService
 ↓
Tax
 ↓
CheckoutTotalsService
```

Do not blindly replace tax inputs with `NetTotal`.

## Insurance / Signature / Gift Wrapping

### Insurance

`ShippingInsuranceService` owns the insurance rule. Common totals helpers may provide the required base amount.

### Signature

`ShippingSignatureService` owns eligibility. Common signature-eligible amount calculation belongs in `CheckoutTotalsService`.

### Gift Wrapping

`GiftWrappingService` calculates the charge. `CheckoutTotalsService` includes that charge in the final total.

## Payment Availability

```text
CheckoutTotalsService
 ↓
NetTotal
 ↓
PaymentAvailabilityService
 ↓
Payment method availability
```

Availability includes:

```text
Stripe
PayPal Express
Amazon Pay
Afterpay
```

`PaymentAvailabilityService` determines availability; it is not payment capture/order processing.

## checkout.js

Current responsibilities:

```text
Shipping methods
Shipping selection
Totals rendering
Coupon Apply
Coupon Remove
Yotpo Reward Remove
Cart API
Checkout UI events
```

Cart endpoints:

```text
/checkout/cart/add
/checkout/cart/update
/checkout/cart/remove
/checkout/cart/clear
```

Discount endpoints:

```text
/checkout/discount/apply
/checkout/discount/remove
/checkout/discount/remove-yotpo-reward
```

Cart response flow:

```text
response.checkout
 ↓
updateTotals(response.checkout)
```

Do not independently calculate checkout totals in JavaScript.

## Legacy Boundary

Do NOT pull these into the new One Page Checkout without proof:

```text
ShoppingcartController.php
CartTrait.php
```

They remain legacy-flow components unless the current new flow explicitly requires them.

## Designer HTML – Pending

Final designer HTML is pending.

When received:

```text
Designer HTML
 ↓
IDs / Classes / data-* attributes
 ↓
checkout.js selector mapping
 ↓
API/event mapping
```

Do not blindly overwrite `checkout.js`. First map HTML ↔ JS, then change only required selectors/events.

## Testing Plan

### Cart

```text
Normal Add
Quantity Update
Remove
Clear
```

### Discounts

```text
Coupon Apply
Coupon Remove
Yotpo Reward
Yotpo Remove
Auto Discount
Quantity Discount
BOGO
Gift Certificate
```

### Charges

```text
Shipping
Tax
Insurance
Shipping Signature
Gift Wrapping
```

### Payment

```text
Stripe
Amazon Pay
Afterpay
PayPal
```

### Final

```text
Place Order
```

For each flow verify:

```text
Subtotal
Discount
DiscountedSubTotal
Shipping
Tax
Insurance
Signature
Gift Wrapping
NetTotal
Payment availability
```

UI total and backend total must match.

## Current Status

### Completed

```text
Checkout architecture                 ✅
CheckoutService                       ✅
CheckoutTotalsService                 ✅
DiscountService                       ✅
CartService                           ✅
CheckoutCartController                ✅
Cart → Checkout refresh               ✅
Coupon Apply / Remove                 ✅
Yotpo Reward Remove                   ✅
Shipping                              ✅
Tax                                   ✅
Insurance                             ✅
Shipping Signature                    ✅
Gift Wrapping                         ✅
Payment Availability                  ✅
Common totals calculations             ✅
Current checkout.js integration       ✅
```

### Pending

```text
Final Designer HTML                   ⏳
Designer HTML ↔ checkout.js mapping   ⏳
Final Cart UI integration             ⏳
Final Shipping UI integration         ⏳
Final Totals UI integration           ⏳
Final Payment UI integration          ⏳
Place Order end-to-end testing        ⏳
Full regression testing               ⏳
Final cleanup / production check      ⏳
```

##  Rules

1. Preserve current working behavior.
2. Never guess missing business logic.
3. Do not introduce legacy controller logic into new checkout without proof.
4. Do not overwrite `checkout.js` unnecessarily.
5. Commonize only genuinely shared logic.
6. If existing code is correct, leave it unchanged.
7. Use the latest project files as source of truth.
8. Verify callers before removing legacy code.
9. Backend is the source of truth for totals.
10. Test before declaring the refactor complete.

## Final Architecture

```text
                    DESIGNER HTML
                         │
                         ▼
                    checkout.js
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
       Cart API       Discount API   Shipping API
          │              │              │
          ▼              ▼              ▼
     CartService     DiscountService  ShippingService
          │              │              │
          └──────────────┼──────────────┘
                         ▼
                  CheckoutService
                         │
                         ▼
              CheckoutTotalsService
                         │
        ┌────────────────┼────────────────┐
        ▼                ▼                ▼
       Tax          Insurance        Signature
        │                │                │
        └────────────────┼────────────────┘
                         ▼
                      NetTotal
                         │
                         ▼
             PaymentAvailabilityService
                         │
                         ▼
                   Payment / Order
```

## Guiding Principle

**Keep the new checkout modular, keep the old working flow safe, and refactor only verified shared logic without behavior regression.**
