@php
/*
* Country/State and ShippingAddress data are prepared by
* CheckoutService::prepareCheckout().
* No helper/database calls are made from this Blade.
*/
$countries = $Countries ?? [];
$states = $States ?? [];
$shippingAddress = $ShippingAddress ?? [];
$selectedShippingCountry =
$SelectedShippingCountry ?? 'US';
$selectedShippingState =
$SelectedShippingState ?? '';

/*
* Current One Page Checkout state.
* Backend/services remain the source of truth.
*/
$checkoutState = is_array($checkout ?? null)
? $checkout
: [];

$totals = is_array($checkoutState['totals'] ?? null)
? $checkoutState['totals']
: [];

$charges = is_array($totals['Charges'] ?? null)
? $totals['Charges']
: [];

$discounts = is_array($totals['Discounts'] ?? null)
? $totals['Discounts']
: [];

$chargeValue = function ($key) use ($charges) {
return (float) (
$charges[$key]['charge']
?? 0
);
};

$checkoutSubTotal = (float) ($totals['SubTotal'] ?? 0);
$checkoutDiscount = (float) ($totals['TotalDiscount'] ?? 0);
$checkoutShipping = $chargeValue('ShippingCharge');
$checkoutTax = $chargeValue('Tax');
$checkoutInsurance = $chargeValue('ShippingInsurance');
$checkoutSignature = $chargeValue('ShippingSignature');
$checkoutGiftWrap = $chargeValue('GiftWrappingCharge');
$checkoutNetTotal = (float) ($totals['NetTotal'] ?? 0);

/* Current applied Coupon / Yotpo Reward codes. */
$promoCouponCode = (string) Session::get(
'ShoppingCart.PromoCoupon.CouponCode',
''
);

$yotpoRewardCode = (string) Session::get(
'ShoppingCart.YotpoRewardCode',
''
);

$cart = Session::get('ShoppingCart.Cart', []);
$cart = is_array($cart) ? $cart : [];

$cartItemCount = 0;
foreach ($cart as $cartItem) {
$isFreeGift =
(
!empty($cartItem['IS_Free_Gift'])
&& strtolower((string) $cartItem['IS_Free_Gift']) === 'yes'
)
||
(
!empty($cartItem['Is_Free_Gift'])
&& strtolower((string) $cartItem['Is_Free_Gift']) === 'yes'
);

$isFreeSample =
(
!empty($cartItem['IS_Free_Sample'])
&& strtolower((string) $cartItem['IS_Free_Sample']) === 'yes'
)
||
(
!empty($cartItem['Is_Free_Sample'])
&& strtolower((string) $cartItem['Is_Free_Sample']) === 'yes'
);

if ($isFreeGift || $isFreeSample) {
continue;
}

$cartItemCount++;
}

$money = function ($value) {
return '$' . number_format((float) $value, 2);
};

/*
* Cart session keeps the existing MaxAroma field names
* (ProductName, Qty, Price, TotPrice, Image, SKU, ProductID).
* Keep these helpers here so the new checkout Blade does not
* assume the newer lowercase API field names.
*/
$cartItemName = function (array $item) {
return $item['ProductName']
?? $item['products_name']
?? $item['product_name']
?? $item['name']
?? 'Product';
};

$cartItemQty = function (array $item) {
return (int) (
$item['Qty']
?? $item['qty']
?? $item['quantity']
?? 1
);
};

$cartItemPrice = function (array $item) {
if (isset($item['Price']) && $item['Price'] !== '') {
return (float) $item['Price'];
}

return (float) (
$item['final_price']
?? $item['price']
?? $item['products_price']
?? 0
);
};

$cartItemTotal = function (array $item) use ($cartItemPrice, $cartItemQty) {
if (isset($item['TotPrice']) && $item['TotPrice'] !== '') {
return (float) $item['TotPrice'];
}

return $cartItemPrice($item) * $cartItemQty($item);
};

$cartItemImage = function (array $item) {
$image =
$item['Image']
?? $item['image']
?? $item['products_image']
?? '';

/*
* The existing MaxAroma cart may store Image as a complete
* <img ...> HTML string. The checkout Blade needs only the
* URL for the src attribute.
*/
if (
is_string($image)
&& stripos($image, '<img') !==false
  ) {
  preg_match( '/<img[^>]+src=["\' ]([^"\']+)["\']/i',
  $image,
  $matches
  );

  return $matches[1] ?? '' ;
  }

  return trim((string) $image);
  };

  $cartItemBrand=function (array $item) {
  return $item['manufactureName']
  ?? $item['brand']
  ?? $item['manufacture_name']
  ?? '' ;
  };

  $cartItemSku=function (array $item) {
  return $item['SKU']
  ?? $item['sku']
  ?? $item['products_model']
  ?? '' ;
  };

  $cartItemCategory=function (array $item) {
  return $item['CategoryName']
  ?? $item['category']
  ?? $item['products_category']
  ?? '' ;
  };

  $cartItemProductId=function (array $item) {
  return $item['ProductID']
  ?? $item['product_id']
  ?? $item['id']
  ?? '' ;
  };

  $checkoutEmail=Session::get('sess_useremail')
  ?: ($shippingAddress['email'] ?? '' )
  ?: ($shippingAddress['confirm_email'] ?? '' );

  $checkoutPhone=Session::get('sess_phone')
  ?: ($shippingAddress['phone'] ?? '' );

  $selectedShippingMethodId=(int) (
  Session::get('ShoppingCart.Shipping.ShippingMethodID', 0)
  );

  $selectedShippingMethodName=Session::get( 'ShoppingCart.Shipping.ShippingMethodName' , ''
  );

  $selectedShippingDays=Session::get( 'ShoppingCart.Shipping.ShippingDays' , ''
  );

  $onlyGCPurchased=(int) (
  $checkoutState['onlyGCPurchased']
  ?? 0
  );
  @endphp

  @extends('layouts.app')
  @section('content')
    <style>
      .loader {
        --color-1: #000;
        --size: 1px;

        width: calc(14 * var(--size));
        height: calc(14 * var(--size));
        border-radius: 50%;
        display: block;
        margin: calc(20 * var(--size)) auto;
        position: relative;
        background: var(--color-1);
        box-sizing: border-box;
        animation: blink 1.2s -0.6s ease-in-out infinite;
      }
      .loader::before,
      .loader::after {
        content: '';
        position: absolute;
        top: 0;
        width: calc(14 * var(--size));
        height: calc(14 * var(--size));
        border-radius: 50%;
        background: var(--color-1);
        box-sizing: border-box;
      }
      .loader::before {
        left: calc(-22 * var(--size));
        animation: blink 1.2s -0.8s ease-in-out infinite;
      }
      .loader::after {
        left: calc(22 * var(--size));
        animation: blink 1.2s -0.4s ease-in-out infinite;
      }

      @keyframes blink {
        0%,
        100% {
          opacity: 0.2;
          transform: scale(0.8);
        }
        40% {
          opacity: 1;
          transform: scale(1);
        }
      }
      .order-items-list, .summary-totals, .divShipMethods{
        position:relative;
      }
      .cart_loader,.ship_method_loader{
        visibility: hidden;
        height: 100%;
        background: #e2c8c800;
        position: absolute;
        width: 100%;
        z-index: 99999;
        display: flex;
        justify-content: center;
        align-items: center;
      }
      label.error{font-size:12px;color:red;}
      .loader-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(255, 255, 255, 0.9);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.5s ease, visibility 0.5s ease;
      }

      .loader-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }

      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }

      .loader-hidden {
        opacity: 0;
        visibility: hidden;
      }

    </style>
  <div class="checkout-page" id="checkout-app">
  @include('newcheckout.checkout-header')
  <div id="full-page-loader" class="loader-overlay loader-hidden">
      <div class="loader"></div>
      <p>Please wait while we process your order… Kindly do not refresh the page or close the tab.</p>
  </div>

  <div id="login-loader" class="loader-overlay loader-hidden">
      <div class="loader"></div>
      <p>Please wait while you are logged in.</p>
  </div>

  <main class="checkout-main" role="main" id="main-content">

    <!-- ── LEFT COLUMN ──────────────────────────────────────── -->
    <div class="checkout-form-col">

      <!-- ══ 1. EXPRESS CHECKOUT ══════════════════════════════ -->
      <section class="express-section" aria-labelledby="express-heading">
        <div class="express-header">
          <p class="express-header-title" id="express-heading">Express checkout</p>
        </div>
        <div class="express-buttons" role="group" aria-label="Express payment options">
          <!-- 1. Afterpay -->
          <button class="express-btn express-btn-afterpay" type="button" aria-label="Pay with Afterpay">
            <img src="{{ url('images/checkout-new/afterpay.svg') }}" alt="">
          </button>
          <!-- 2. Amazon Pay -->
          <button class="express-btn express-btn-amazon" type="button" aria-label="Pay with Amazon Pay">
            <img src="{{ url('images/checkout-new/amazon-pay.svg') }}" alt="">
          </button>
          <!-- 3. Link (no brand SVG supplied — accessible wordmark) -->
          <button class="express-btn express-btn-link" type="button" aria-label="Pay with Link">
            Link
          </button>
          <!-- 4. PayPal -->
          <button class="express-btn express-btn-paypal" type="button" aria-label="Pay with PayPal">
            <img src="{{ url('images/checkout-new/paypal.svg') }}" alt="">
          </button>
          <!-- 5. Apple Pay -->
          <button class="express-btn express-btn-apple" type="button" aria-label="Pay with Apple Pay">
            <img src="{{ url('images/checkout-new/apple-pay.svg') }}" alt="">
          </button>
          <!-- 6. Google Pay -->
          <button class="express-btn express-btn-google" type="button" aria-label="Pay with Google Pay">
            <img src="{{ url('images/checkout-new/google-pay.svg') }}" alt="">
          </button>
        </div>

        <div class="divider-text" style="margin-top: var(--space-5); font-size: var(--font-size-xs);">
          <span>or continue with email</span>
        </div>
      </section>

      <!-- ══ 2. CONTACT INFORMATION ════════════════════════════ -->
      <section class="checkout-section" aria-labelledby="contact-heading" id="section-contact">
        <div class="checkout-section-head">
          <div class="checkout-section-head-left">
            <h2 class="step-title" id="contact-heading">Contact</h2>
          </div>
          <button class="section-edit-btn" aria-label="Edit contact information" onclick="editSection('contact')">Edit</button>
        </div>

        <!-- Completed summary state (shown after filling) -->
        <div class="section-summary" id="contact-summary" style="display:none;" aria-live="polite">
          <span id="contact-summary-email">{{ $checkoutEmail }}</span>
          <span id="contact-summary-phone">{{ $checkoutPhone }}</span>
        </div>

        <!-- Form body -->
        <div class="checkout-section-body" id="contact-form">

          @if(Session::has('sess_icustomerid') && (int) Session::get('sess_icustomerid') > 0)

          {{-- =====================================================
                 LOGGED-IN CUSTOMER
                 Old checkout behavior:
                 Guest / Sign In UI is completely hidden after login.
            ====================================================== --}}
          <div
            id="contact-logged-in"
            style="display:flex; flex-direction:column; gap: var(--space-4);">
            <div class="fl-group">
              <input
                class="fl-input"
                type="email"
                id="email"
                name="email"
                value="{{ Session::get('sess_useremail', '') }}"
                placeholder="Email address"
                autocomplete="email"
                readonly
                aria-required="true">
              <label class="fl-label" for="email">
                Email address
              </label>
            </div>

            <div class="fl-group">
              <div class="relative">
                <input
                  class="fl-input"
                  type="tel"
                  id="phone"
                  name="phone"
                  value="{{ Session::get('sess_phone', '') }}"
                  placeholder="Phone number"
                  autocomplete="tel"
                  inputmode="tel"
                  aria-describedby="phone-hint">
                <label class="fl-label" for="phone">
                  Phone number (optional)
                </label>
              </div>

              <span
                class="fl-error-msg"
                id="phone-hint"
                style="display:flex; color: var(--color-text-tertiary);">
                For delivery updates via text
              </span>
            </div>
          </div>

          @else

          <!-- Guest / Sign In toggle -->
          <div class="auth-options">
            <div class="auth-toggle" role="tablist" aria-label="Checkout as guest or sign in">
              <button
                class="auth-tab active"
                role="tab"
                aria-selected="true"
                id="tab-guest"
                aria-controls="panel-guest"
                onclick="switchAuthTab('guest')">
                Continue as Guest
              </button>

              <button
                class="auth-tab"
                role="tab"
                aria-selected="false"
                id="tab-signin"
                aria-controls="panel-signin"
                onclick="switchAuthTab('signin')">
                Sign In
              </button>
            </div>
          </div>

          <!-- Guest Panel -->
          <div
            role="tabpanel"
            id="panel-guest"
            aria-labelledby="tab-guest">
            <div style="display:flex; flex-direction:column; gap: var(--space-4);">

              <div class="fl-group">
                <input
                  class="fl-input"
                  type="email"
                  id="email"
                  name="email"
                  placeholder="Email address"
                  autocomplete="email"
                  inputmode="email"
                  required
                  aria-required="true"
                  aria-describedby="email-error">

                <label class="fl-label" for="email">
                  Email address
                </label>

                <svg
                  class="fl-check-icon"
                  viewBox="0 0 18 18"
                  fill="none"
                  aria-hidden="true">
                  <path
                    d="M3 9L7 13L15 5"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"></path>
                </svg>

                <span
                  class="fl-error-msg"
                  id="email-error"
                  role="alert">
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 12 12"
                    fill="none"
                    aria-hidden="true">
                    <circle
                      cx="6"
                      cy="6"
                      r="5.5"
                      stroke="currentColor"></circle>
                    <path
                      d="M6 3.5V6.5"
                      stroke="currentColor"
                      stroke-linecap="round"></path>
                    <circle
                      cx="6"
                      cy="8.5"
                      r="0.5"
                      fill="currentColor"></circle>
                  </svg>
                  Please enter a valid email address
                </span>
              </div>

              <div class="fl-group">
                <div class="relative">
                  <input
                    class="fl-input"
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="Phone number"
                    autocomplete="tel"
                    inputmode="tel"
                    aria-describedby="phone-hint">

                  <label class="fl-label" for="phone">
                    Phone number (optional)
                  </label>
                </div>

                <span
                  class="fl-error-msg"
                  id="phone-hint"
                  style="display:flex; color: var(--color-text-tertiary);">
                  For delivery updates via text
                </span>
              </div>

              <label
                class="field-check"
                style="margin-top: -8px;">
                <input
                  type="checkbox"
                  id="marketing"
                  name="marketing"
                  checked>

                <div style="display:flex; flex-direction:column; gap:2px;">
                  <span
                    style="font-size: var(--font-size-sm); font-weight: var(--font-weight-medium);">
                    Stay in the loop
                  </span>

                  <span
                    style="font-size: var(--font-size-xs); color: var(--color-text-tertiary);">
                    Receive exclusive deals and new arrivals from MaxAroma
                  </span>
                </div>
              </label>

            </div>
          </div>

          <!-- Sign In Panel (hidden by default) -->
          <div role="tabpanel" id="panel-signin" aria-labelledby="tab-signin" hidden="">
            <form
              id="checkout-login-form"
              method="POST"
              action="{{ url('/login.html') }}">
              @csrf

              <input
                type="hidden"
                name="action"
                value="signin">

              <input
                type="hidden"
                name="fromcheckout"
                value="secure-checkout1">
              <div style="display:flex; flex-direction:column; gap: var(--space-4);">
                <div class="fl-group">
                  <input class="fl-input" type="email" name="email"
                    id="checkout-login-email" placeholder="Email address" autocomplete="email" inputmode="email">
                  <label class="fl-label" for="signin-email">Email address</label>
                </div>
                <div class="fl-group">
                  <input class="fl-input" type="password" name="password"
                    id="checkout-login-password" placeholder="Password" autocomplete="current-password">
                  <label class="fl-label" for="signin-password">Password</label>
                </div>
                <div class="checkout-login-message"></div>
                <div style="display:flex; justify-content:flex-end;">
                  <button type="button" style="font-size:var(--font-size-sm); color:var(--color-text-secondary); text-decoration:underline; background:none; border:none; cursor:pointer;">Forgot password?</button>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Sign In</button>
              </div>
          </div>
          </form>
        </div>

        @endif

      </section>

      <!-- ══ 3. SHIPPING ADDRESS ══════════════════════════════ -->
      <section class="checkout-section" aria-labelledby="shipping-heading" id="section-shipping">
        <div class="checkout-section-head">
          <div class="checkout-section-head-left">
            <h2 class="step-title" id="shipping-heading">Shipping address</h2>
          </div>
          <button class="section-edit-btn" aria-label="Edit shipping address" onclick="editSection('shipping')">Edit</button>
        </div>

        <!-- Completed summary -->
        <div class="section-summary" id="shipping-summary" style="display:none;" aria-live="polite">
          <span id="shipping-summary-name"></span>
          <span id="shipping-summary-address"></span>
          <span id="shipping-summary-location"></span>
        </div>

        <div class="checkout-section-body" id="shipping-form">
          <form method="post" name="frmcheckoutaddress" id="frmcheckoutaddress">
            <!-- Country -->
            <div style="display:flex; flex-direction:column; gap: var(--space-3);">
              <div class="fl-group">
                <select
                  class="fl-input"
                  id="shipping_country"
                  name="shipping[country]"
                  autocomplete="shipping country"
                  aria-label="Country"
                  aria-required="true">
                  <option value=""></option>

                  @foreach($countries as $countryCode => $countryName)
                  <option
                    value="{{ $countryCode }}"
                    {{ $selectedShippingCountry === $countryCode ? 'selected' : '' }}>
                    {{ $countryName }}
                  </option>
                  @endforeach
                </select>

                <label class="fl-label" for="shipping_country">
                  Country / Region
                </label>
              </div>

              <!-- First / Last name -->
              <div class="form-grid-2">
                <div class="fl-group">
                  <input
                    class="fl-input"
                    type="text"
                    id="shipping_first_name"
                    name="shipping_first_name"
                    placeholder="First name"
                    autocomplete="shipping given-name"
                    value="{{ is_array($shippingAddress) ? ($shippingAddress['first_name'] ?? '') : '' }}"
                    aria-required="true">
                  <label class="fl-label" for="shipping_first_name">
                    First name
                  </label>
                </div>

                <div class="fl-group">
                  <input
                    class="fl-input"
                    type="text"
                    id="shipping_last_name"
                    name="shipping_last_name"
                    placeholder="Last name"
                    autocomplete="shipping family-name"
                    value="{{ is_array($shippingAddress) ? ($shippingAddress['last_name'] ?? '') : '' }}"
                    aria-required="true">
                  <label class="fl-label" for="shipping_last_name">
                    Last name
                  </label>
                </div>
              </div>

              <!-- Address -->
              <div class="fl-group" style="position:relative;">
                <input
                  class="fl-input"
                  type="text"
                  id="shipping_address1"
                  name="shipping_address1"
                  placeholder="Address"
                  autocomplete="shipping address-line1"
                  value="{{ is_array($shippingAddress) ? ($shippingAddress['address1'] ?? '') : '' }}"
                  aria-required="true"
                  role="combobox"
                  aria-expanded="false"
                  aria-autocomplete="list"
                  aria-controls="address-suggestions"

                  onkeydown="handleAddressKeydown(event)">

                <label class="fl-label" for="shipping_address1">
                  Address
                </label>

                <span
                  style="position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--color-text-tertiary); pointer-events:none;"
                  aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"></circle>
                    <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                  </svg>
                </span>

                <ul
                  class="addr-suggestions"
                  id="address-suggestions"
                  role="listbox"
                  aria-label="Address suggestions"
                  hidden></ul>
              </div>

              <!-- Address 2 -->
              <div class="fl-group">
                <input
                  class="fl-input"
                  type="text"
                  id="shipping_address2"
                  name="shipping[address2]"
                  placeholder="Apt, suite, etc. (optional)"
                  autocomplete="shipping address-line2"
                  value="{{ is_array($shippingAddress) ? ($shippingAddress['address2'] ?? '') : '' }}">
                <label class="fl-label" for="shipping_address2">
                  Apt, suite, etc. (optional)
                </label>
              </div>

              <!-- City / State / ZIP -->
              <div class="form-grid-3col">

                <div class="fl-group">
                  <input
                    class="fl-input"
                    type="text"
                    id="shipping_city"
                    name="shipping_city"
                    placeholder="City"
                    autocomplete="shipping address-level2"
                    value="{{ is_array($shippingAddress) ? ($shippingAddress['city'] ?? '') : '' }}"
                    aria-required="true">
                  <label class="fl-label" for="shipping_city">
                    City
                  </label>
                </div>

                <div
                  class="fl-group"
                  id="shipping-state-container">
                  @if($selectedShippingCountry === 'US')

                  <select
                    class="fl-input"
                    id="shipping_state"
                    name="shipping_state"
                    autocomplete="shipping address-level1"
                    aria-label="State"
                    aria-required="true">
                    @foreach($states as $stateCode => $stateName)
                    <option
                      value="{{ $stateCode }}"
                      {{ $selectedShippingState === $stateCode ? 'selected' : '' }}>
                      {{ $stateName }}
                    </option>
                    @endforeach
                  </select>

                  <label class="fl-label" for="shipping_state">
                    State
                  </label>

                  @else

                  <input
                    class="fl-input"
                    type="text"
                    id="shipping_state"
                    name="shipping_state"
                    value="{{ $selectedShippingState }}"
                    placeholder="State / Province"
                    autocomplete="shipping address-level1"
                    aria-required="true">

                  <label class="fl-label" for="shipping_state">
                    State / Province
                  </label>

                  @endif
                </div>

                <div class="fl-group">
                  <input
                    class="fl-input"
                    type="text"
                    id="shipping_zip"
                    name="shipping_zip"
                    placeholder="ZIP code"
                    autocomplete="shipping postal-code"
                    value="{{ is_array($shippingAddress) ? ($shippingAddress['zip'] ?? '') : '' }}"
                    inputmode="numeric"
                    aria-required="true"
                    maxlength="10">
                  <label class="fl-label" for="shipping_zip">
                    ZIP code
                  </label>
                </div>

              </div>

              <!-- Save address option -->
              <label class="field-check">
                <input
                  type="checkbox"
                  id="save-address"
                  name="save-address">
                <span style="font-size: var(--font-size-sm);">
                  Save this address for faster checkout next time
                </span>
              </label>

            </div>
          </form>
        </div>
      </section>

      <!-- ══ 4. SHIPPING METHOD ══════════════════════════════ -->
      <section class="checkout-section" aria-labelledby="delivery-heading" id="section-delivery">
        <div class="checkout-section-head">
          <div class="checkout-section-head-left">
            <h2 class="step-title" id="delivery-heading">Shipping method</h2>
          </div>
        </div>

        <div class="checkout-section-body">
          <!-- Order-by urgency line -->
          <div class="divShipMethods">
            <div class="ship_method_loader"><span class="loader"></span></div>
            <fieldset style="border:none; padding:0; margin:0; display:flex; flex-direction:column; gap:var(--space-3);">
              <legend class="visually-hidden">Select shipping method</legend>
            </fieldset>
          </div>
          <!-- Order add-ons: Protect My Order (default ON) · Request Signature · Gift Wrap
               Compact redesign (client spec, 2026-08-06) — same 3 toggles/IDs/
               handlers/pricing, restyled into one bordered list of single-line
               rows instead of 3 tall cards. -->
          <div class="checkout-addons" style="margin-top: var(--space-5);">
            <div class="addon-list">

              <!-- Protect My Order -->
              <label class="addon-row{{ $checkoutInsurance > 0 ? ' active' : '' }}" aria-label="Add Protect My Order">
                <span class="addon-icon" aria-hidden="true">
                  <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <path d="M11 2L3 5.5V10.5C3 15.1 6.6 19.4 11 20.5C15.4 19.4 19 15.1 19 10.5V5.5L11 2Z" stroke="#000" stroke-width="1.5"></path>
                    <path d="M8 11L10 13L14 9" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                  </svg>
                </span>
                <span class="addon-body">
                  <span class="addon-title">Protect My Order</span>
                  <span class="addon-desc">Covers damage, loss &amp; theft</span>
                </span>
                <span class="addon-price" id="protection-addon-price">{{ $checkoutInsurance > 0 ? '+' . $money($checkoutInsurance) : 'Free' }}</span>
                <span class="toggle-switch">
                  <input type="checkbox" id="protection" name="protection" {{ $checkoutInsurance > 0 ? 'checked' : '' }} onchange="handleProtectionToggle(this)">
                  <span class="toggle-track"><span class="toggle-thumb"></span></span>
                </span>
              </label>

              <!-- Request Signature -->
              <label class="addon-row{{ $checkoutSignature > 0 ? ' active' : '' }}" aria-label="Add Request Signature">
                <span class="addon-icon" aria-hidden="true">
                  <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <path d="M3 16C5 13 7 12 8.5 13.5C9.5 14.5 8 16 6.5 15C5 14 6.5 9 8.5 7C10 5.5 11 6.5 10.5 8.5C10 11 8 14.5 10 16C11.5 17 13.5 15.5 15 13" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M16 17H19" stroke="#000" stroke-width="1.5" stroke-linecap="round"></path>
                  </svg>
                </span>
                <span class="addon-body">
                  <span class="addon-title">Request Signature</span>
                  <span class="addon-desc">Signature required on delivery</span>
                </span>
                <span class="addon-price" id="signature-addon-price">{{ $checkoutSignature > 0 ? '+' . $money($checkoutSignature) : 'Free' }}</span>
                <span class="toggle-switch">
                  <input type="checkbox" id="request-signature" name="request-signature" {{ $checkoutSignature > 0 ? 'checked' : '' }} onchange="handleSignatureToggle(this)">
                  <span class="toggle-track"><span class="toggle-thumb"></span></span>
                </span>
              </label>

              <!-- Add Gift Wrap -->
              <label class="addon-row" aria-label="Add Gift Wrap">
                <span class="addon-icon" aria-hidden="true">
                  <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                    <path d="M3 8H19V19H3V8Z" stroke="#000" stroke-width="1.5" stroke-linejoin="round"></path>
                    <path d="M2 8H20V11H2V8Z" stroke="#000" stroke-width="1.5" stroke-linejoin="round"></path>
                    <path d="M11 8V19" stroke="#000" stroke-width="1.5"></path>
                    <path d="M11 8C11 8 9 8 7.5 6.5C6.5 5.5 7.5 3.5 9 4.5C10.5 5.5 11 8 11 8Z" stroke="#000" stroke-width="1.5" stroke-linejoin="round"></path>
                    <path d="M11 8C11 8 13 8 14.5 6.5C15.5 5.5 14.5 3.5 13 4.5C11.5 5.5 11 8 11 8Z" stroke="#000" stroke-width="1.5" stroke-linejoin="round"></path>
                  </svg>
                </span>
                <span class="addon-body">
                  <span class="addon-title">Add Gift Wrap</span>
                  <span class="addon-desc">Premium gift wrapping</span>
                </span>
                <span class="addon-price">{{ $checkoutGiftWrap > 0 ? '+' . $money($checkoutGiftWrap) : 'Free' }}</span>
                <span class="toggle-switch">
                  <input type="checkbox" id="gift-wrap" name="gift-wrap">
                  <span class="toggle-track"><span class="toggle-thumb"></span></span>
                </span>
              </label>

            </div>
          </div>
        </div>
      </section>

      <!-- ══ COMPLETE YOUR ORDER (recommendations, above Payment) ══ -->
      <section class="upsell-section complete-your-order" aria-labelledby="upsell-heading" aria-label="Complete Your Order">
        <p class="upsell-title" id="upsell-heading">Complete Your Order</p>
        <div class="upsell-carousel" role="list" aria-label="Suggested add-on products, swipe to browse">

          <div class="upsell-card" role="listitem">
            <img class="upsell-thumb" src="{{ url('/images/noimage-lrg.jpg') }}" alt="Chanel Chance Eau Tendre" loading="lazy" width="120" height="92">
            <div class="upsell-info">
              <div class="upsell-brand">Chanel</div>
              <div class="upsell-name">Chance Eau Tendre EDP 1.7 oz</div>
              <div class="upsell-price-row">
                <span class="upsell-price">$115.00</span>
              </div>
            </div>
            <button class="upsell-add" aria-label="Add Chanel Chance Eau Tendre to cart" type="button"><span aria-hidden="true">＋</span> Add</button>
          </div>

          <div class="upsell-card" role="listitem">
            <img class="upsell-thumb" src="{{ url('/images/noimage-lrg.jpg') }}" alt="Chanel Chance Eau Tendre" loading="lazy" width="120" height="92">
            <div class="upsell-info">
              <div class="upsell-brand">Versace</div>
              <div class="upsell-name">Bright Crystal EDT 3.4 oz</div>
              <div class="upsell-price-row">
                <span class="upsell-price">$69.99</span>
                <span class="upsell-original">$85.00</span>
              </div>
            </div>
            <button class="upsell-add" aria-label="Add Versace Bright Crystal to cart" type="button"><span aria-hidden="true">＋</span> Add</button>
          </div>

          <div class="upsell-card" role="listitem">
            <img class="upsell-thumb" src="{{ url('/images/noimage-lrg.jpg') }}" alt="Chanel Chance Eau Tendre" loading="lazy" width="120" height="92">
            <div class="upsell-info">
              <div class="upsell-brand">MaxAroma</div>
              <div class="upsell-name">Travel Atomizer 10 ml</div>
              <div class="upsell-price-row">
                <span class="upsell-price">$14.99</span>
              </div>
            </div>
            <button class="upsell-add" aria-label="Add Travel Atomizer to cart" type="button"><span aria-hidden="true">＋</span> Add</button>
          </div>

          <div class="upsell-card" role="listitem">
            <img class="upsell-thumb" src="{{ url('/images/noimage-lrg.jpg') }}" alt="Chanel Chance Eau Tendre" loading="lazy" width="120" height="92">
            <div class="upsell-info">
              <div class="upsell-brand">MaxAroma</div>
              <div class="upsell-name">Signature Gift Box</div>
              <div class="upsell-price-row">
                <span class="upsell-price">$9.99</span>
              </div>
            </div>
            <button class="upsell-add" aria-label="Add Signature Gift Box to cart" type="button"><span aria-hidden="true">＋</span> Add</button>
          </div>

          <div class="upsell-card" role="listitem">
            <img class="upsell-thumb" src="{{ url('/images/noimage-lrg.jpg') }}" alt="Chanel Chance Eau Tendre" loading="lazy" width="120" height="92">
            <div class="upsell-info">
              <div class="upsell-brand">MaxAroma</div>
              <div class="upsell-name">Fragrance Sampler Set</div>
              <div class="upsell-price-row">
                <span class="upsell-price">$24.99</span>
                <span class="upsell-original">$32.00</span>
              </div>
            </div>
            <button class="upsell-add" aria-label="Add Fragrance Sampler Set to cart" type="button"><span aria-hidden="true">＋</span> Add</button>
          </div>

        </div>
      </section>

      <!-- ══ 5. PAYMENT ════════════════════════════════════════ -->
      <section class="checkout-section" aria-labelledby="payment-heading" id="section-payment">
        <div class="checkout-section-head">
          <div class="checkout-section-head-left">
            <h2 class="step-title" id="payment-heading">Payment</h2>
          </div>
          <!-- SSL badge inline -->
          <div style="display:flex; align-items:center; gap:4px; font-size:11px; color: var(--color-text-tertiary);">
            <svg width="12" height="13" viewBox="0 0 12 13" fill="none" aria-hidden="true">
              <path d="M6 1L1 3.5V6.5C1 9.5 3.2 12.2 6 13C8.8 12.2 11 9.5 11 6.5V3.5L6 1Z" stroke="#16A34A" stroke-width="1.2"></path>
            </svg>
            SSL Secure
          </div>
        </div>

        <div class="checkout-section-body">
          <!-- Payment method tabs -->
          <div class="payment-tabs" role="tablist" aria-label="Payment methods" style="margin-bottom: var(--space-5);">
            <button class="payment-tab active" data-method="STRIPE" role="tab" aria-selected="true" id="pay-tab-card" aria-controls="pay-panel-card" onclick="switchPayTab('card')">
              <svg width="16" height="12" viewBox="0 0 16 12" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"></rect>
                <path d="M1 5H15" stroke="currentColor" stroke-width="1.3"></path>
              </svg>
              Card
            </button>
            <button class="payment-tab" data-method="PAYPAL" role="tab" aria-selected="false" id="pay-tab-paypal" aria-controls="pay-panel-paypal" onclick="switchPayTab('paypal')">PayPal</button>
            <button class="payment-tab" data-method="AFFIRM" role="tab" aria-selected="false" id="pay-tab-affirm" aria-controls="pay-panel-affirm" onclick="switchPayTab('affirm')">Affirm</button>
          </div>

          <!-- Card Panel -->
          <div role="tabpanel" id="pay-panel-card" aria-labelledby="pay-tab-card">
            <div style="display:flex; flex-direction:column; gap: var(--space-3);">
              <div id="stripe-card-payment">
                <label class="error" id="stripe-error"></label>
                <div class="fl-group pb-2">
                    <div id="stripe-card-number" class="stripe-element fl-input"></div>
                    <label class="fl-label" for="card-number">Card number</label>
                </div>

                <div class="form-grid-2">
                    <div class="fl-group">
                      <div id="stripe-card-expiry" class="stripe-element fl-input"></div>
                      <label class="fl-label" for="stripe-card-expiry">Expiry</label>
                    </div>

                    <div class="fl-group" style="position:relative;">
                        <div id="stripe-card-cvv" class="stripe-element fl-input"></div>
                        <label class="fl-label" for="stripe-card-cvv">CVV</label>
                    </div>
                </div>

                <div id="stripe-card-error" class="stripe-card-error" role="alert"></div>
            </div>
              {{--
              <div class="fl-group">
                <input class="fl-input" type="text" id="card-number" name="card-number" placeholder="Card number" autocomplete="cc-number" inputmode="numeric" maxlength="19" aria-required="true">
                <label class="fl-label" for="card-number">Card number</label>
              </div>

              <div class="fl-group">
                <input class="fl-input" type="text" id="card-name" name="card-name" placeholder="Name on card" autocomplete="cc-name" aria-required="true">
                <label class="fl-label" for="card-name">Name on card</label>
              </div>

              <div class="form-grid-2">
                <div class="fl-group">
                  <input class="fl-input" type="text" id="card-expiry" name="card-expiry" placeholder="MM / YY" autocomplete="cc-exp" inputmode="numeric" maxlength="7" aria-required="true">
                  <label class="fl-label" for="card-expiry">Expiry date</label>
                </div>
                <div class="fl-group" style="position:relative;">
                  <input class="fl-input" type="text" id="card-cvv" name="card-cvv" placeholder="CVV" autocomplete="cc-csc" inputmode="numeric" maxlength="4" aria-required="true" aria-describedby="cvv-hint">
                  <label class="fl-label" for="card-cvv">CVV</label>
                  <button type="button" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color: var(--color-text-tertiary);" aria-label="What is CVV?" id="cvv-hint">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                      <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.3"></circle>
                      <path d="M8 7V11" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
                      <circle cx="8" cy="5" r="0.7" fill="currentColor"></circle>
                    </svg>
                  </button>
                </div>
              </div>
              --}}
              <!-- Accepted cards row -->
              <div class="accepted-cards" aria-label="Accepted payment cards">
                <img class="card-logo" src="{{ url('images/checkout-new/visa.svg') }}" alt="Visa">
                <img class="card-logo" src="{{ url('images/checkout-new/mastercard.svg') }}" alt="Mastercard">
                <img class="card-logo" src="{{ url('images/checkout-new/american-express.svg') }}" alt="American Express">
                <img class="card-logo" src="{{ url('images/checkout-new/discover.svg') }}" alt="Discover">
                <img class="card-logo" src="{{ url('images/checkout-new/paypal.svg') }}" alt="PayPal">
              </div>
            </div>
          </div>

          <!-- PayPal Panel (hidden) -->
          <div role="tabpanel" id="pay-panel-paypal" aria-labelledby="pay-tab-paypal" hidden="">
            <div style="text-align:center; padding: var(--space-8) var(--space-4);">
              <p style="color: var(--color-text-secondary); font-size: var(--font-size-sm); margin-bottom: var(--space-4);">You'll be redirected to PayPal to complete your payment securely.</p>
              <button type="button" class="btn btn-full" style="background:#003087; color:white; border-color:#003087; height:52px; font-size:var(--font-size-base); font-weight:700;">Continue with PayPal</button>
            </div>
          </div>

          <!-- Affirm Panel (hidden) -->
          <div role="tabpanel" id="pay-panel-affirm" aria-labelledby="pay-tab-affirm" hidden="">
            <div style="padding: var(--space-4);">
              <div class="savings-banner" style="margin-bottom: var(--space-4);">
                <svg class="savings-banner-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                  <path d="M10 2L12.5 7H18L13.5 10.5L15.5 16L10 12.5L4.5 16L6.5 10.5L2 7H7.5L10 2Z" fill="currentColor"></path>
                </svg>
                <span>Pay as low as <strong>$28/month</strong> with Affirm. No hidden fees.</span>
              </div>
              <button type="button" class="btn btn-full" style="background:#000; color:white; height:52px;">Continue with Affirm</button>
            </div>
          </div>

          <!-- Security bar -->
          <div class="security-bar" style="margin-top: var(--space-5);">
            <div class="security-bar-item">
              <svg class="security-bar-icon" viewBox="0 0 14 16" fill="none" aria-hidden="true">
                <path d="M7 1L1 3.5V7.5C1 11.1 3.6 14.4 7 15.5C10.4 14.4 13 11.1 13 7.5V3.5L7 1Z" stroke="currentColor" stroke-width="1.2"></path>
                <path d="M4.5 8L6 10L9.5 6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"></path>
              </svg>
              SSL Encrypted
            </div>
            <div class="security-bar-item">
              <svg class="security-bar-icon" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <rect x="1" y="5" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.2"></rect>
                <path d="M4 5V4C4 2.3 5.3 1 7 1C8.7 1 10 2.3 10 4V5" stroke="currentColor" stroke-width="1.2"></path>
              </svg>
              256-bit Encryption
            </div>
            <div class="security-bar-item">
              <svg class="security-bar-icon" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M7 1L13 4V8C13 11 10.5 13.5 7 14C3.5 13.5 1 11 1 8V4L7 1Z" stroke="currentColor" stroke-width="1.2"></path>
              </svg>
              PCI Compliant
            </div>
          </div>
        </div>
      </section>

      <!-- ══ 7. ORDER REVIEW + PLACE ORDER ════════════════════ -->
      <section class="place-order-section" aria-labelledby="review-heading">
        <h2 class="step-title" id="review-heading" style="margin-bottom: var(--space-5);">Review your order</h2>

        <div class="review-table" aria-label="Order review" style="margin-bottom: var(--space-5); padding-bottom: var(--space-5); border-bottom: 1px solid var(--color-border-default);">
          <div class="review-row">
            <span class="review-row-label">Contact</span>
            <span class="review-row-value">{{ $checkoutEmail }}</span>
            <button class="review-row-edit" onclick="editSection('contact')" aria-label="Edit contact information">Edit</button>
          </div>
          <div class="review-row">
            <span class="review-row-label">Ship to</span>
            <span class="review-row-value">
              {{ trim(
                  ($shippingAddress['address1'] ?? '') .
                  (!empty($shippingAddress['address2']) ? ', ' . $shippingAddress['address2'] : '') .
                  ', ' . ($shippingAddress['city'] ?? '') .
                  (!empty($shippingAddress['state']) ? ', ' . $shippingAddress['state'] : '') .
                  (!empty($shippingAddress['zip']) ? ' ' . $shippingAddress['zip'] : '')
              ) }}
            </span>
            <button class="review-row-edit" onclick="editSection('shipping')" aria-label="Edit shipping address">Edit</button>
          </div>
          <div class="review-row">
            <span class="review-row-label">Method</span>
            <span class="review-row-value">
              {{ $selectedShippingMethodName ?: 'Shipping method not selected' }}
              @if($selectedShippingMethodName && $checkoutShipping <= 0)
                · Free
                @elseif($selectedShippingMethodName)
                · {{ $money($checkoutShipping) }}
                @endif
                @if(is_string($selectedShippingDays) && $selectedShippingDays !=='' )
                · {!! $selectedShippingDays !!}
                @endif
                </span>
                <button class="review-row-edit" onclick="editSection('delivery')" aria-label="Edit shipping method">Edit</button>
          </div>
          <div class="review-row">
            <span class="review-row-label">Payment</span>
            <span class="review-row-value">Visa ending in 4242</span>
            <button class="review-row-edit" onclick="editSection('payment')" aria-label="Edit payment method">Edit</button>
          </div>
        </div>

        <!-- Collapsible order summary (Shopify-style): the mobile sticky
             footer hides itself once this section is in view, so this
             keeps item thumbnails + the expandable breakdown reachable
             right where the customer actually places the order. -->
        <div class="review-summary-card" id="review-summary-card">
          <button type="button" class="review-summary-toggle" id="review-summary-toggle" aria-expanded="false" aria-controls="review-summary-panel" onclick="toggleReviewSummary()" aria-label="Show order summary">
            <div class="review-summary-thumb" aria-hidden="true">
              <img src="{{ url('/images/noimage-lrg.jpg') }}" alt="">
              <span class="review-summary-qty" id="review-summary-qty">{{ $cartItemCount }}</span>
            </div>
            <div class="review-summary-total">
              <span class="place-order-total-label">Total</span>
              <div class="review-summary-tax">Includes {{ $money($checkoutTax) }} tax</div>
            </div>
            <div class="review-summary-amount" id="place-order-total-amount" aria-label="Order total {{ $money($checkoutNetTotal) }}">{{ $money($checkoutNetTotal) }}</div>
            <svg class="review-summary-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
          </button>
          <div class="review-summary-panel" id="review-summary-panel">
            <div class="review-summary-clip">
              <div class="review-summary-inner">
                @foreach(array_slice($cart, 0, 2, true) as $item)
                @php
                $productName = $cartItemName($item);
                $quantity = $cartItemQty($item);
                $itemTotal = $cartItemTotal($item);
                $image = $cartItemImage($item);
                @endphp
                <div class="msum-item">
                  <div class="msum-item-thumb">
                    <img src="{{ $image ?: url('/images/noimage-lrg.jpg') }}" alt="{{ $productName }}">
                    <span class="msum-item-qty" aria-hidden="true">{{ $quantity }}</span>
                  </div>
                  <span class="msum-item-name">{{ $productName }}</span>
                  <span class="msum-item-price">{{ $money($itemTotal) }}</span>
                </div>
                @endforeach
                <div class="msum-divider"></div>
                <div class="msum-row"><span class="msum-row-label">Discount</span><span class="msum-discount" id="msum-discount-value">−{{ $money($checkoutDiscount) }}</span></div>
                <div class="msum-row" id="review-code-discount-row" hidden=""><span class="msum-row-label">Code <span id="review-code-name"></span></span><span class="msum-discount" id="review-code-discount-value">−$0.00</span></div>
                <div class="msum-row" id="review-protection-row" {{ $checkoutInsurance > 0 ? '' : 'hidden' }}><span class="msum-row-label">Protect My Order</span><span>{{ $money($checkoutInsurance) }}</span></div>
                <div class="msum-row" id="review-signature-row" {{ $checkoutSignature > 0 ? '' : 'hidden' }}><span class="msum-row-label">Request Signature</span><span>{{ $money($checkoutSignature) }}</span></div>
                <div class="msum-row" id="review-giftwrap-row" {{ $checkoutGiftWrap > 0 ? '' : 'hidden' }}><span class="msum-row-label">Gift wrap</span><span>{{ $money($checkoutGiftWrap) }}</span></div>
                <div class="msum-row"><span class="msum-row-label">Shipping</span><span class="msum-success" id="msum-shipping-value">{{ $checkoutShipping > 0 ? $money($checkoutShipping) : 'Free' }}</span></div>
                <div class="msum-row"><span class="msum-row-label">Taxes</span><span id="msum-tax-value">{{ $money($checkoutTax) }}</span></div>
                <div class="msum-row msum-total"><span>Total</span><span id="review-summary-total-value">{{ $money($checkoutNetTotal) }}</span></div>
              </div>
            </div>
          </div>
        </div>

        <!-- CTA -->
        <button type="button" class="btn btn-cta btn-full" aria-label="Place order for {{ $money($checkoutNetTotal) }}" id="place-order-btn">
          <span class="btn-text" id="place-order-btn-text">Place Order · {{ $money($checkoutNetTotal) }}</span>
        </button>

        <p class="terms-note">
          By placing your order you agree to MaxAroma's
          <a href="/terms" target="_blank" rel="noopener">Terms of Service</a> and
          <a href="/privacy" target="_blank" rel="noopener">Privacy Policy</a>.
          Your payment is processed securely. We never store your card details.
        </p>
      </section>

    </div><!-- end .checkout-form-col -->

    <!-- ── RIGHT COLUMN — ORDER SUMMARY ───────────────────── -->
    <aside class="checkout-sidebar" aria-label="Order summary" id="order-summary-sidebar">

      <!-- Order Summary Card -->
      <div class="order-summary-card" id="order-summary-card">
        <button class="order-summary-toggle" aria-expanded="false" aria-controls="order-summary-body" onclick="toggleOrderSummary()" type="button">
          <div class="order-summary-toggle-left">
            <div class="summary-cart-icon-wrap" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 450.391 450.391">
                <g>
                  <g>
                    <g>
                      <path d="M143.673,350.322c-25.969,0-47.02,21.052-47.02,47.02c0,25.969,21.052,47.02,47.02,47.02 c25.969,0,47.02-21.052,47.02-47.02C190.694,371.374,169.642,350.322,143.673,350.322z M143.673,423.465 c-14.427,0-26.122-11.695-26.122-26.122c0-14.427,11.695-26.122,26.122-26.122c14.427,0,26.122,11.695,26.122,26.122 C169.796,411.77,158.1,423.465,143.673,423.465z" />
                      <path d="M342.204,350.322c-25.969,0-47.02,21.052-47.02,47.02c0,25.969,21.052,47.02,47.02,47.02s47.02-21.052,47.02-47.02 C389.224,371.374,368.173,350.322,342.204,350.322z M342.204,423.465c-14.427,0-26.122-11.695-26.122-26.122 c0-14.427,11.695-26.122,26.122-26.122s26.122,11.695,26.122,26.122C368.327,411.77,356.631,423.465,342.204,423.465z" />
                      <path d="M448.261,76.037c-2.176-2.377-5.153-3.865-8.359-4.18L99.788,67.155L90.384,38.42 C83.759,19.211,65.771,6.243,45.453,6.028H10.449C4.678,6.028,0,10.706,0,16.477s4.678,10.449,10.449,10.449h35.004 c11.361,0.251,21.365,7.546,25.078,18.286l66.351,200.098l-5.224,12.016c-5.827,15.026-4.077,31.938,4.702,45.453 c8.695,13.274,23.323,21.466,39.184,21.943h203.233c5.771,0,10.449-4.678,10.449-10.449c0-5.771-4.678-10.449-10.449-10.449 H175.543c-8.957-0.224-17.202-4.936-21.943-12.539c-4.688-7.51-5.651-16.762-2.612-25.078l4.18-9.404l219.951-22.988 c24.16-2.661,44.034-20.233,49.633-43.886l25.078-105.012C450.96,81.893,450.36,78.492,448.261,76.037z M404.376,185.228 c-3.392,15.226-16.319,26.457-31.869,27.69l-217.339,22.465L106.58,88.053l320.261,4.702L404.376,185.228z" />
                    </g>
                  </g>
                </g>
              </svg>
              <span class="summary-cart-count" aria-label="{{ $cartItemCount }} items">{{ $cartItemCount }}</span>
            </div>
            <span class="order-summary-toggle-title">Order summary</span>
          </div>
          <div style="display:flex; align-items:center; gap: var(--space-2);">
            <span class="order-summary-toggle-amount" id="mobile-summary-amount" aria-label="Total {{ $money($checkoutNetTotal) }}">{{ $money($checkoutNetTotal) }}</span>
            <svg class="order-summary-toggle-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
            </svg>
          </div>
        </button>

        <div class="order-summary-body" id="order-summary-body" role="region" aria-label="Order details">
          <div class="order-summary-clip">
            <div class="order-summary-inner">

              <!-- ══ DEMO ONLY — promo mode preview switcher ══
                 Lets the client preview all 3 promo states (flat threshold, GWP
                 site-wide, GWP restricted) live, without touching code. Remove
                 this block once PROMO_CONFIG is wired to the real backend. -->
              <div class="promo-demo-switcher compact" style="margin-bottom: var(--space-3);" aria-label="Promo mode preview, for review only">
                <span class="promo-demo-label">🔧 PREVIEW MODE (for review only):</span>
                <button type="button" class="promo-demo-btn active" data-preset="free_samples" aria-pressed="true" disabled>Free Samples</button>
                <button type="button" class="promo-demo-btn" data-preset="gwp_site_wide" aria-pressed="false" disabled>GWP · Site-wide</button>
                <button type="button" class="promo-demo-btn" data-preset="gwp_restricted" aria-pressed="false" disabled>GWP · Restricted (Dior, Versace)</button>
              </div>

              <!-- ══ Purchase-threshold promo module (Free Samples OR Gift With Purchase) ══
                 One module, two mutually exclusive modes. The active mode, threshold,
                 eligibility scope and messaging are all driven by PROMO_CONFIG (backend). -->
              <div class="purchase-threshold-promo" style="margin-bottom: var(--space-4);">
                <div class="free-gift-card compact" id="promo-card" role="status" aria-live="polite">

                  <!-- In-progress state -->
                  <div class="free-gift-state free-gift-progress">
                    <div class="free-gift-head">
                      <div class="free-gift-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                          <path d="M3 8H19V19H3V8Z" stroke="#16A34A" stroke-width="1.5" stroke-linejoin="round"></path>
                          <path d="M2 8H20V11H2V8Z" stroke="#16A34A" stroke-width="1.5" stroke-linejoin="round"></path>
                          <path d="M11 8V19" stroke="#16A34A" stroke-width="1.5"></path>
                          <path d="M11 8C11 8 9 8 7.5 6.5C6.5 5.5 7.5 3.5 9 4.5C10.5 5.5 11 8 11 8Z" stroke="#16A34A" stroke-width="1.5" stroke-linejoin="round"></path>
                          <path d="M11 8C11 8 13 8 14.5 6.5C15.5 5.5 14.5 3.5 13 4.5C11.5 5.5 11 8 11 8Z" stroke="#16A34A" stroke-width="1.5" stroke-linejoin="round"></path>
                        </svg>
                      </div>
                      <div class="free-gift-title" id="promo-heading"><span id="promo-title-text">Unlock your free samples</span></div>
                      <span class="promo-info-wrap">
                        <button type="button" class="promo-info-btn" id="promo-info-btn" hidden="" aria-label="Which products qualify for this offer?" aria-expanded="false" aria-controls="promo-tooltip" onclick="togglePromoTooltip(event)">
                          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"></circle>
                            <path d="M8 7.2V11" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
                            <circle cx="8" cy="5" r="0.9" fill="currentColor"></circle>
                          </svg>
                        </button>
                        <span class="promo-tooltip" id="promo-tooltip" role="tooltip"></span>
                      </span>
                    </div>
                    <div class="free-gift-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="98" id="promo-progressbar" aria-label="Progress toward your promotion">
                      <div class="free-gift-fill" id="promo-fill" style="width: 98.1%;"></div>
                    </div>
                    <p class="free-gift-remaining">
                      You're <strong id="promo-amount">$3.26 away</strong> <span id="promo-remaining-suffix">from free samples!</span>
                    </p>
                  </div>

                  <!-- Unlocked state -->
                  <div class="free-gift-state free-gift-unlocked">
                    <div class="free-gift-unlocked-row">
                      <div class="free-gift-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                          <path d="M3 8H19V19H3V8Z" stroke="#16A34A" stroke-width="1.5" stroke-linejoin="round"></path>
                          <path d="M2 8H20V11H2V8Z" stroke="#16A34A" stroke-width="1.5" stroke-linejoin="round"></path>
                          <path d="M11 8V19" stroke="#16A34A" stroke-width="1.5"></path>
                          <path d="M11 8C11 8 9 8 7.5 6.5C6.5 5.5 7.5 3.5 9 4.5C10.5 5.5 11 8 11 8Z" stroke="#16A34A" stroke-width="1.5" stroke-linejoin="round"></path>
                          <path d="M11 8C11 8 13 8 14.5 6.5C15.5 5.5 14.5 3.5 13 4.5C11.5 5.5 11 8 11 8Z" stroke="#16A34A" stroke-width="1.5" stroke-linejoin="round"></path>
                        </svg>
                      </div>
                      <div>
                        <div class="free-gift-unlocked-title">🎉 Congratulations!</div>
                        <div class="free-gift-unlocked-desc" id="promo-success-desc">You've unlocked your free samples!</div>
                      </div>
                    </div>
                  </div>

                </div>
              </div>

              <!-- Savings banner — DISABLED for now, keep markup for future re-enable
            <div class="savings-banner">
              <svg class="savings-banner-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M10 2C5.6 2 2 5.6 2 10S5.6 18 10 18 18 14.4 18 10 14.4 2 10 2Z" stroke="currentColor" stroke-width="1.3"/>
                <path d="M7 10.5L9 12.5L13 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
              </svg>
              <span>You're saving <strong>$47.03</strong> on this order!</span>
            </div>
            -->

              <!-- Products: first 2 always visible, remainder in scroll area -->
              <div class="order-items-list" id="checkout-cart-items" aria-label="Items in cart">
                @include('newcheckout.cart-loader')
                @foreach(array_slice($cart, 0, 2, true) as $index => $item)
                @php
                $productName = $cartItemName($item);
                $quantity = $cartItemQty($item);
                $itemTotal = $cartItemTotal($item);
                $image = $cartItemImage($item);
                $brand = $cartItemBrand($item);
                $sku = $cartItemSku($item);
                $category = $cartItemCategory($item);
                $productId = $cartItemProductId($item);
                $isFreeGift =
                (
                !empty($item['IS_Free_Gift'])
                && strtolower((string) $item['IS_Free_Gift']) === 'yes'
                )
                ||
                (
                !empty($item['Is_Free_Gift'])
                && strtolower((string) $item['Is_Free_Gift']) === 'yes'
                );
                @endphp

                <div class="order-item-row" data-cart-index="{{ $index }}" data-product-id="{{ $productId }}" data-cart-id="{{ $item['cart_id'] ?? $item['ProductID'] ?? $item['id'] ?? '' }}" data-free-gift="{{ $isFreeGift ? '1' : '0' }}" data-brand="{{ $brand }}" data-category="{{ $category }}">
                  <div class="order-item-image">
                    <img class="order-item-thumb" src="{{ $image ?: url('/images/noimage-lrg.jpg') }}" alt="{{ $productName }}" loading="lazy">
                  </div>
                  <div class="order-item-info">
                    <div class="order-item-brand">{{ $brand }}</div>
                    <div class="order-item-name">{{ $productName }}</div>
                    <div class="order-item-variant"></div>
                    <div class="order-item-sku">{{ $sku }}</div>
					@if(isset($item['FinalSale']) && $item['FinalSale'] != '')
						<div class="order-item-variant">
							{{ $item['FinalSale'] }}
						</div>
					@endif

					@if(Auth::guard('store')->check())
						<div class="order-item-variant">
							{{ (isset($item['OrderType']) && $item['OrderType'] == 'Store') ? 'Store Item' : 'Online Item' }}
						</div>
					@endif

					@if(isset($item['stock_left']) && $item['stock_left'] < 6)
						<div class="order-item-variant stock_left pt-1">
							Only {{ $item['stock_left'] }} left in stock
						</div>
					@endif
                    
                    
                    <div class="order-item-controls">
                      @if($isFreeGift)
                      <div class="qty-stepper" role="group" aria-label="Free Gift quantity">
                        <span class="qty-value" aria-live="polite">{{ $quantity }}</span>
                      </div>
                      <span class="free-gift-label">Free Gift</span>
                      @else
                      <div class="qty-stepper" role="group" aria-label="Quantity for {{ $productName }}">
                        <button class="qty-btn" type="button" aria-label="Decrease quantity" onclick="updateQty(this,-1)">−</button>
                        <span class="qty-value" aria-live="polite">{{ $quantity }}</span>
                        <button class="qty-btn" type="button" aria-label="Increase quantity" onclick="updateQty(this,1)">+</button>
                      </div>
                      <button class="item-remove" type="button" aria-label="Remove {{ $productName }} from cart" onclick="removeItem(this)">Remove</button>
                      @endif
                    </div>
					@php
					   $bogoDiscountMessage =
					   $item['BogoDiscountMessage'] ?? '';
					@endphp	
					 @if(!empty($bogoDiscountMessage))
						<div class="order-item-bogo-message pt-1" id="OrderInfoDiv">
							{!! $bogoDiscountMessage !!}
						</div>
					@endif
                  </div>
                  <div class="order-item-price">
                    <div class="order-item-price-current">{{ $money($itemTotal) }}</div>
                  </div>
                </div>
                @endforeach

                <!-- More items trigger (no nested scroll — opens drawer) -->
                <button type="button" class="view-all-items" onclick="openCartDrawer()" aria-haspopup="dialog" aria-controls="cart-drawer">
                  <span class="view-all-stack" aria-hidden="true">
                    <span class="view-all-thumb" style="background:linear-gradient(135deg,#f5eef0 0%,#e8d5dc 100%);">
                      <img class="view-all-thumb" src="{{ url('/images/noimage-lrg.jpg') }}" alt="">
                    </span>
                    <img class="view-all-thumb" src="{{ url('/images/noimage-lrg.jpg') }}" alt="">
                  </span>
                  <span class="view-all-label"><strong id="more-items-count">+ {{ max($cartItemCount - 2, 0) }} More Items</strong> in your order</span>
                  <span class="view-all-cta">View All Items <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M3 8H13M13 8L9 4M13 8L9 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg></span>
                </button>
              </div>

              <!-- Coupon Code / E-Gift Card / Shipping Rate Calculator
                 accordions — moved above Totals per client feedback
                 (2026-08-05), so they sit right under the item list
                 instead of below the Total row. -->
              <div class="summary-accordion-group" style="margin-top: var(--space-1);">
                <div class="summary-accordion">
                  <button class="summary-accordion-head" onclick="toggleSummaryAccordion('coupon')" aria-expanded="false" aria-controls="coupon-panel" type="button" id="coupon-toggle">
                    <span class="summary-accordion-title">
                      <svg width="15" height="15" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                        <circle cx="4" cy="4" r="1.5" stroke="currentColor" stroke-width="1.2"></circle>
                        <circle cx="10" cy="10" r="1.5" stroke="currentColor" stroke-width="1.2"></circle>
                        <path d="M2 12L12 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"></path>
                      </svg>
                      Have a Coupon Code?
                    </span>
                    <svg class="summary-accordion-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                      <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                  </button>
                  <div class="summary-accordion-body" id="coupon-panel" hidden="">
                    <div class="promo-field">
                      <input type="text" id="promo-code" name="promo-code" placeholder="Enter coupon code" aria-label="Coupon code" autocomplete="off" spellcheck="false">
                      <button type="button" class="btn btn-secondary btn-sm" onclick="applyPromo()">Apply</button>
                    </div>
                    <div
                      id="promo-result"
                      data-coupon-code="{{ $promoCouponCode }}"
                      data-yotpo-code="{{ $yotpoRewardCode }}">
                      @if ($promoCouponCode !== '')
                      <div
                        class="coupon-applied animate-in"
                        data-discount-kind="coupon"
                        data-discount-code="{{ $promoCouponCode }}"
                        role="status"
                        style="margin-top:12px;">
                        <div>
                          <span class="coupon-applied-code">{{ $promoCouponCode }}</span>
                          <span style="font-size:12px;color:var(--color-text-success);margin-left:8px;">Applied</span>
                        </div>
                        <button
                          type="button"
                          id="maxaroma-remove-coupon"
                          class="coupon-remove">Remove</button>
                      </div>
                      @endif

                      @if ($yotpoRewardCode !== '')
                      <div
                        class="coupon-applied animate-in"
                        data-discount-kind="reward"
                        data-discount-code="{{ $yotpoRewardCode }}"
                        role="status"
                        style="margin-top:12px;">
                        <div>
                          <span class="coupon-applied-code">{{ $yotpoRewardCode }}</span>
                          <span style="font-size:12px;color:var(--color-text-success);margin-left:8px;">Applied</span>
                        </div>
                        <button
                          type="button"
                          id="maxaroma-remove-yotpo-reward"
                          class="coupon-remove">Remove</button>
                      </div>
                      @endif
                    </div>
                  </div>
                </div>

                <!-- E-Gift Card accordion -->
                @if (
					strtolower(trim(Session::get('eusertype') ?? '')) != 'wholesaler'
					&&
					trim(Session::get('is_dropshipper') ?? '') != 'Yes'
					&&
					($onlyGCPurchased ?? 0) == 0
				)

                <div class="summary-accordion">
                  <button class="summary-accordion-head" onclick="toggleSummaryAccordion('giftcard')" aria-expanded="false" aria-controls="giftcard-panel" type="button" id="giftcard-toggle">
                    <span class="summary-accordion-title">
                      <svg width="15" height="15" viewBox="0 0 16 14" fill="none" aria-hidden="true">
                        <rect x="1" y="3" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.2"></rect>
                        <path d="M1 6H15" stroke="currentColor" stroke-width="1.2"></path>
                        <path d="M8 3V1.5M8 3C8 3 6.5 1 5 1.8C4 2.4 5 3 8 3ZM8 3C8 3 9.5 1 11 1.8C12 2.4 11 3 8 3Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"></path>
                      </svg>
                      Have an E-Gift Card Code?
                    </span>
                    <svg class="summary-accordion-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                      <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                  </button>
                  <div class="summary-accordion-body" id="giftcard-panel" hidden="">
                    <div class="promo-field">
                      <input type="text" id="giftcard-code" name="giftcard-code" placeholder="Enter gift card code" aria-label="E-Gift card code" autocomplete="off" spellcheck="false">
                      <button type="button" class="btn btn-secondary btn-sm" onclick="applyGiftCard()">Apply</button>
                    </div>
                    <div id="giftcard-result"></div>
                  </div>
                </div>
				@endif
                <!-- Shipping Rate Calculator accordion -->
                <div class="summary-accordion">
                  <button class="summary-accordion-head" onclick="toggleSummaryAccordion('shipcalc')" aria-expanded="false" aria-controls="shipcalc-panel" type="button" id="shipcalc-toggle">
                    <span class="summary-accordion-title">
                      <svg width="16" height="14" viewBox="0 0 18 14" fill="none" aria-hidden="true">
                        <path d="M1 3H11V11H1V3Z" stroke="currentColor" stroke-width="1.2"></path>
                        <path d="M11 6H14L17 9V11H11V6Z" stroke="currentColor" stroke-width="1.2"></path>
                        <circle cx="4" cy="11.5" r="1.3" stroke="currentColor" stroke-width="1.2"></circle>
                        <circle cx="13.5" cy="11.5" r="1.3" stroke="currentColor" stroke-width="1.2"></circle>
                      </svg>
                      Shipping Rate Calculator
                    </span>
                    <svg class="summary-accordion-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                      <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                  </button>
                  <div class="summary-accordion-body" id="shipcalc-panel" hidden="">
                    <p class="shipcalc-hint">Estimate shipping to your destination.</p>
                    <div class="shipcalc-grid">
                      <div class="fl-group">
                        <select class="fl-input fl-input-sm" id="calc-country" aria-label="Country">
                          <option value="US" selected="">United States</option>
                          <option value="CA">Canada</option>
                          <option value="GB">United Kingdom</option>
                          <option value="AU">Australia</option>
                        </select>
                        <label class="fl-label" for="calc-country">Country</label>
                      </div>
                      <div class="fl-group">
                        <select class="fl-input fl-input-sm" id="calc-state" aria-label="State">
                          <option value="NY" selected="">New York</option>
                          <option value="CA">California</option>
                          <option value="TX">Texas</option>
                          <option value="FL">Florida</option>
                        </select>
                        <label class="fl-label" for="calc-state">State / Province</label>
                      </div>
                    </div>
                    <div class="fl-group" style="margin-top: var(--space-2);">
                      <input class="fl-input fl-input-sm" type="text" id="calc-zip" placeholder="ZIP / Postal code" inputmode="numeric" aria-label="ZIP or postal code">
                      <label class="fl-label" for="calc-zip">ZIP / Postal code</label>
                    </div>
                    <button type="button" class="btn btn-secondary btn-full" style="margin-top: var(--space-3);" onclick="recalcShipping()">Calculate Shipping</button>
                    <div id="shipcalc-result" class="shipcalc-result" aria-live="polite"></div>
                  </div>
                </div>
              </div>

              <!-- Totals -->
              <div class="summary-totals" style="margin-top: var(--space-4);">
                @include('newcheckout.cart-loader')
                <div class="summary-row">
                  <span class="summary-row-label">Subtotal ({{ $cartItemCount }} items)</span>
                  <span class="summary-row-value" id="summary-subtotal-value">{{ $money($checkoutSubTotal) }}</span>
                </div>
                <div class="summary-row savings">
                  <span class="summary-row-label">Savings</span>
                  <span class="summary-row-value" id="summary-savings-value">−{{ $money($checkoutDiscount) }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-row-label">Shipping</span>
                  <span class="summary-row-value" id="summary-shipping-value">{{ $checkoutShipping > 0 ? $money($checkoutShipping) : 'Free' }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-row-label">Estimated tax</span>
                  <span class="summary-row-value" id="summary-tax-value">{{ $money($checkoutTax) }}</span>
                </div>
                <div class="summary-row" id="protection-row">
                  <span class="summary-row-label">Protect My Order</span>
                  <span class="summary-row-value">{{ $money($checkoutInsurance) }}</span>
                </div>
                <div class="summary-row" id="signature-row" {{ $checkoutSignature > 0 ? '' : 'hidden' }}>
                  <span class="summary-row-label">Request Signature</span>
                  <span class="summary-row-value">{{ $money($checkoutSignature) }}</span>
                </div>
                <div class="summary-row" id="gift-wrap-row" {{ $checkoutGiftWrap > 0 ? '' : 'hidden' }}>
                  <span class="summary-row-label">Gift wrap</span>
                  <span class="summary-row-value">{{ $money($checkoutGiftWrap) }}</span>
                </div>
                <div class="summary-row total">
                  <span class="summary-row-label">Total</span>
                  <span class="summary-row-value" id="summary-total-value">{{ $money($checkoutNetTotal) }}</span>
                </div>
              </div>

              <!-- Policy strip -->
              <div class="policy-strip" aria-label="Shopping policies">
                <div class="policy-item">
                  <svg class="policy-icon" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M7 1L13 4V8C13 11 10.5 13.5 7 14C3.5 13.5 1 11 1 8V4L7 1Z" stroke="currentColor" stroke-width="1.2"></path>
                  </svg>
                  Secure checkout
                </div>
                <div class="policy-item">
                  <svg class="policy-icon" viewBox="0 0 14 12" fill="none" aria-hidden="true">
                    <path d="M1 9L3.5 11.5L13 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"></path>
                  </svg>
                  100% Authentic
                </div>
                <div class="policy-item">
                  <svg class="policy-icon" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M7 1C9.5 1 11.5 3 11.5 5.5C11.5 8 9.5 13 7 13C4.5 13 2.5 8 2.5 5.5C2.5 3 4.5 1 7 1Z" stroke="currentColor" stroke-width="1.2"></path>
                  </svg>
                  Free Returns
                </div>
              </div>

            </div><!-- end .order-summary-inner -->
          </div><!-- end .order-summary-clip -->
        </div><!-- end .order-summary-body -->
      </div><!-- end .order-summary-card -->

      <!-- Aroma Club reward program -->
      <a class="aroma-club-card" href="https://www.maxaroma.com/reward-point-program.html" target="_blank" rel="noopener" aria-label="Join Aroma Club rewards program — opens in new tab">
        <div class="aroma-club-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M11 2L13.5 7.8L19.8 8.4L15 12.6L16.5 19L11 15.6L5.5 19L7 12.6L2.2 8.4L8.5 7.8L11 2Z" fill="#000"></path>
          </svg>
        </div>
        <div class="aroma-club-info">
          <div class="aroma-club-title">Join Aroma Club</div>
          <div class="aroma-club-desc">Earn reward points on this and every future purchase.</div>
        </div>
        <span class="aroma-club-cta" aria-hidden="true">Learn More <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M3 8H13M13 8L9 4M13 8L9 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg></span>
      </a>

      <!-- Reassurance sidebar block (desktop only; mobile copy lives near footer) -->
      <div class="sidebar-reassurance">
        <p class="sidebar-reassurance-title">Why shop with MaxAroma</p>
        <div class="reassurance-list">
          <div class="reassurance-item">
            <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M3 11L7 7L10 10L15 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"></path>
              <path d="M1 15H17" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
            </svg>
            <span><strong style="font-weight:600;">Free Shipping</strong> on qualifying orders</span>
          </div>
          <div class="reassurance-item">
            <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <circle cx="9" cy="9" r="7.5" stroke="currentColor" stroke-width="1.3"></circle>
              <path d="M5.5 9L8 11.5L12.5 6.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <span><strong style="font-weight:600;">Free Returns</strong> — 30-day hassle-free policy</span>
          </div>
          <div class="reassurance-item">
            <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M9 1L16 4V9C16 13 13 16.5 9 17.5C5 16.5 2 13 2 9V4L9 1Z" stroke="currentColor" stroke-width="1.3"></path>
            </svg>
            <span><strong style="font-weight:600;">Secure Checkout</strong> — SSL encrypted &amp; PCI compliant</span>
          </div>
          <div class="reassurance-item">
            <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M1 3H11V11H1V3Z" stroke="currentColor" stroke-width="1.3"></path>
              <path d="M11 6H14L17 9V11H11V6Z" stroke="currentColor" stroke-width="1.3"></path>
              <circle cx="4" cy="11.5" r="1.4" stroke="currentColor" stroke-width="1.3"></circle>
              <circle cx="13.5" cy="11.5" r="1.4" stroke="currentColor" stroke-width="1.3"></circle>
            </svg>
            <span><strong style="font-weight:600;">Shipping Rate Calculator</strong> — estimate before you buy</span>
          </div>
          <div class="reassurance-item">
            <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M9 1.5L11.5 6.5H17L12.5 10L14.5 15.5L9 12L3.5 15.5L5.5 10L1 6.5H6.5L9 1.5Z" stroke="currentColor" stroke-width="1.3"></path>
            </svg>
            <span><strong style="font-weight:600;">100% Authentic Products</strong> — sourced from authorized distributors</span>
          </div>
          <div class="reassurance-item">
            <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M9 2C5.1 2 2 5.1 2 9S5.1 16 9 16 16 12.9 16 9 12.9 2 9 2Z" stroke="currentColor" stroke-width="1.3"></path>
              <path d="M9 6V9.5L11.5 12" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
            </svg>
            <span><strong style="font-weight:600;">Customer Support Available</strong> — 7 days a week</span>
          </div>
          <div class="reassurance-item">
            <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M9 1.5C6.5 1.5 4.5 3.5 4.5 6C4.5 9 9 16.5 9 16.5S13.5 9 13.5 6C13.5 3.5 11.5 1.5 9 1.5Z" stroke="currentColor" stroke-width="1.3"></path>
              <circle cx="9" cy="6" r="1.5" stroke="currentColor" stroke-width="1.3"></circle>
            </svg>
            <span><strong style="font-weight:600;">Free Samples Available</strong> with select orders</span>
          </div>
          <div class="reassurance-item">
            <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M9 2L11 7H16L12 10.5L13.5 15.5L9 12.5L4.5 15.5L6 10.5L2 7H7L9 2Z" stroke="currentColor" stroke-width="1.3"></path>
            </svg>
            <span><strong style="font-weight:600;">Aroma Club Rewards</strong> — earn points on every order</span>
          </div>
        </div>
      </div>

    </aside>
  </main>

  <!-- Back to cart -->
  <div class="back-to-cart-wrap">
    <a href="https://www.maxaroma.com/checkout/cart" class="back-to-cart">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M9.5 3.5L5 8l4.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
      Back to Cart
    </a>
  </div>

  <!-- Why shop with MaxAroma — mobile only: placed near checkout completion,
       above the footer. On desktop this block lives in the sidebar instead. -->
  <div class="reassurance-bottom-section">
    <div class="sidebar-reassurance">
      <p class="sidebar-reassurance-title">Why shop with MaxAroma</p>
      <div class="reassurance-list">
        <div class="reassurance-item">
          <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M3 11L7 7L10 10L15 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M1 15H17" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
          </svg>
          <span><strong style="font-weight:600;">Free Shipping</strong> on qualifying orders</span>
        </div>
        <div class="reassurance-item">
          <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <circle cx="9" cy="9" r="7.5" stroke="currentColor" stroke-width="1.3"></circle>
            <path d="M5.5 9L8 11.5L12.5 6.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>
          <span><strong style="font-weight:600;">Free Returns</strong> — 30-day hassle-free policy</span>
        </div>
        <div class="reassurance-item">
          <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M9 1L16 4V9C16 13 13 16.5 9 17.5C5 16.5 2 13 2 9V4L9 1Z" stroke="currentColor" stroke-width="1.3"></path>
          </svg>
          <span><strong style="font-weight:600;">Secure Checkout</strong> — SSL encrypted &amp; PCI compliant</span>
        </div>
        <div class="reassurance-item">
          <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M1 3H11V11H1V3Z" stroke="currentColor" stroke-width="1.3"></path>
            <path d="M11 6H14L17 9V11H11V6Z" stroke="currentColor" stroke-width="1.3"></path>
            <circle cx="4" cy="11.5" r="1.4" stroke="currentColor" stroke-width="1.3"></circle>
            <circle cx="13.5" cy="11.5" r="1.4" stroke="currentColor" stroke-width="1.3"></circle>
          </svg>
          <span><strong style="font-weight:600;">Shipping Rate Calculator</strong> — estimate before you buy</span>
        </div>
        <div class="reassurance-item">
          <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M9 1.5L11.5 6.5H17L12.5 10L14.5 15.5L9 12L3.5 15.5L5.5 10L1 6.5H6.5L9 1.5Z" stroke="currentColor" stroke-width="1.3"></path>
          </svg>
          <span><strong style="font-weight:600;">100% Authentic Products</strong> — sourced from authorized distributors</span>
        </div>
        <div class="reassurance-item">
          <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M9 2C5.1 2 2 5.1 2 9S5.1 16 9 16 16 12.9 16 9 12.9 2 9 2Z" stroke="currentColor" stroke-width="1.3"></path>
            <path d="M9 6V9.5L11.5 12" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"></path>
          </svg>
          <span><strong style="font-weight:600;">Customer Support Available</strong> — 7 days a week</span>
        </div>
        <div class="reassurance-item">
          <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M9 1.5C6.5 1.5 4.5 3.5 4.5 6C4.5 9 9 16.5 9 16.5S13.5 9 13.5 6C13.5 3.5 11.5 1.5 9 1.5Z" stroke="currentColor" stroke-width="1.3"></path>
            <circle cx="9" cy="6" r="1.5" stroke="currentColor" stroke-width="1.3"></circle>
          </svg>
          <span><strong style="font-weight:600;">Free Samples Available</strong> with select orders</span>
        </div>
        <div class="reassurance-item">
          <svg class="reassurance-icon" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M9 2L11 7H16L12 10.5L13.5 15.5L9 12.5L4.5 15.5L6 10.5L2 7H7L9 2Z" stroke="currentColor" stroke-width="1.3"></path>
          </svg>
          <span><strong style="font-weight:600;">Aroma Club Rewards</strong> — earn points on every order</span>
        </div>
      </div>
    </div>
  </div>

  @include("checkout-new.checkout-footer")

  @include('layouts.popups')

  <!-- ═══════════════════════════════════════════════════════════
       MOBILE STICKY FOOTER
  ═══════════════════════════════════════════════════════════ -->
  <div class="mobile-summary-overlay" id="mobile-summary-overlay" hidden="" onclick="toggleMobileSummary()"></div>
  <div class="mobile-sticky-footer" role="complementary" aria-label="Checkout action bar" id="mobile-footer" style="opacity: 1; pointer-events: auto;">

    <!-- Expandable order-summary breakdown (slides up) -->
    <div class="mobile-summary-panel" id="mobile-summary-panel">
      <div class="mobile-summary-clip">
        <div class="mobile-summary-content" role="region" aria-label="Order summary details">
          <div class="msum-label">Products</div>
          @foreach(array_slice($cart, 0, 2, true) as $item)
          @php
          $productName = $cartItemName($item);
          $quantity = $cartItemQty($item);
          $itemTotal = $cartItemTotal($item);
          $image = $cartItemImage($item);
          @endphp
          <div class="msum-item">
            <div class="msum-item-thumb">
              <img src="{{ $image ?: url('/images/noimage-lrg.jpg') }}" alt="{{ $productName }}">
              <span class="msum-item-qty" aria-hidden="true">{{ $quantity }}</span>
            </div>
            <span class="msum-item-name">{{ $productName }}</span>
            <span class="msum-item-price">{{ $money($itemTotal) }}</span>
          </div>
          @endforeach
          <div class="msum-divider"></div>

          <!-- Discount code -->
          <div class="msum-promo">
            <div class="msum-promo-field" id="msum-promo-field">
              <input type="text" id="msum-promo-input" class="msum-promo-input" placeholder="Discount code" aria-label="Discount code" autocomplete="off" spellcheck="false" enterkeyhint="done">
              <button type="button" class="btn btn-secondary btn-sm" onclick="applyMobileDiscount()">Apply</button>
            </div>
            <div class="msum-promo-applied" id="msum-promo-applied" hidden="">
              <span><strong id="msum-promo-code-label"></strong> applied</span>
              <button type="button" class="msum-promo-remove" onclick="removeMobileDiscount()" aria-label="Remove discount code">Remove</button>
            </div>
            <p class="msum-promo-msg" id="msum-promo-msg" role="status" aria-live="polite"></p>
          </div>

          <div class="msum-row"><span class="msum-row-label">Discount</span><span class="msum-discount" id="msum-discount-value">−{{ $money($checkoutDiscount) }}</span></div>
          <div class="msum-row" id="msum-code-discount-row" hidden=""><span class="msum-row-label">Code <span id="msum-code-name"></span></span><span class="msum-discount" id="msum-code-discount-value">−$0.00</span></div>
          <div class="msum-row" id="msum-protection-row" {{ $checkoutInsurance > 0 ? '' : 'hidden' }}><span class="msum-row-label">Protect My Order</span><span>{{ $money($checkoutInsurance) }}</span></div>
          <div class="msum-row" id="msum-signature-row" {{ $checkoutSignature > 0 ? '' : 'hidden' }}><span class="msum-row-label">Request Signature</span><span>{{ $money($checkoutSignature) }}</span></div>
          <div class="msum-row" id="msum-giftwrap-row" {{ $checkoutGiftWrap > 0 ? '' : 'hidden' }}><span class="msum-row-label">Gift wrap</span><span>{{ $money($checkoutGiftWrap) }}</span></div>
          <div class="msum-row"><span class="msum-row-label">Shipping</span><span class="msum-success" id="msum-shipping-value">{{ $checkoutShipping > 0 ? $money($checkoutShipping) : 'Free' }}</span></div>
          <div class="msum-row"><span class="msum-row-label">Taxes</span><span id="msum-tax-value">{{ $money($checkoutTax) }}</span></div>
          <div class="msum-row msum-total"><span>Total</span><span id="mobile-summary-total">{{ $money($checkoutNetTotal) }}</span></div>
        </div>
      </div>
    </div>

    <div class="mobile-footer-inner">
      <button type="button" class="mobile-summary-toggle" id="mobile-summary-toggle" aria-expanded="false" aria-controls="mobile-summary-panel" onclick="toggleMobileSummary()" aria-label="Show order summary">
        <div class="mobile-footer-total">
          <div class="mobile-footer-label">Order summary</div>
          <div class="mobile-footer-amount" id="mobile-footer-amount" aria-label="{{ $money($checkoutNetTotal) }}">{{ $money($checkoutNetTotal) }}</div>
        </div>
        <svg class="mobile-summary-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M5 12.5L10 7.5L15 12.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
      </button>
      <button type="button" class="btn btn-cta mobile-footer-btn" onclick="handlePlaceOrder()" aria-label="Place order for {{ $money($checkoutNetTotal) }}">
        Place Order
      </button>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       "Remove Protect My Order?" confirmation (D1)
  ═══════════════════════════════════════════════════════════ -->
  <div class="protect-confirm-overlay" id="protect-confirm-overlay" hidden="" onclick="keepProtection()"></div>
  <div class="protect-confirm-modal" id="protect-confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="protect-confirm-title" aria-describedby="protect-confirm-body" hidden="">
    <div class="protect-confirm-icon" aria-hidden="true">
      <svg width="20" height="20" viewBox="0 0 22 22" fill="none">
        <path d="M11 2L3 5.5V10.5C3 15.1 6.6 19.4 11 20.5C15.4 19.4 19 15.1 19 10.5V5.5L11 2Z" stroke="#000" stroke-width="1.5"></path>
        <path d="M11 8V12" stroke="#000" stroke-width="1.5" stroke-linecap="round"></path>
        <circle cx="11" cy="15" r="1" fill="#000"></circle>
      </svg>
    </div>
    <h2 class="protect-confirm-title" id="protect-confirm-title">Remove Protect My Order?</h2>
    <p class="protect-confirm-body" id="protect-confirm-body">Without this protection, you're responsible for replacing your order if it's lost, stolen, or damaged in transit.</p>
    <div class="protect-confirm-actions">
      <button
		type="button"
		class="btn btn-primary protect-confirm-keep"
		onclick="keepProtection()"
	>
		Keep Protection ·
		<span id="protect-confirm-keep-price">
			{{ $money($checkoutInsurance) }}
		</span>
	</button>
      <button type="button" class="protect-confirm-remove" onclick="removeProtection()">No thanks, remove it</button>
    </div>
  </div>

  <!-- Request Signature confirmation; same structure as Protection. -->
  <div class="protect-confirm-overlay" id="signature-confirm-overlay" hidden="" onclick="keepSignature()"></div>
  <div class="protect-confirm-modal" id="signature-confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="signature-confirm-title" aria-describedby="signature-confirm-body" hidden="">
    <div class="protect-confirm-icon" aria-hidden="true">
      <svg width="20" height="20" viewBox="0 0 22 22" fill="none">
        <path d="M11 2L3 5.5V10.5C3 15.1 6.6 19.4 11 20.5C15.4 19.4 19 15.1 19 10.5V5.5L11 2Z" stroke="#000" stroke-width="1.5"></path>
        <path d="M11 8V12" stroke="#000" stroke-width="1.5" stroke-linecap="round"></path>
        <circle cx="11" cy="15" r="1" fill="#000"></circle>
      </svg>
    </div>
    <h2 class="protect-confirm-title" id="signature-confirm-title">Remove Request Signature?</h2>
    <p class="protect-confirm-body" id="signature-confirm-body">For this order, we automatically add signature requirements free of charge. Opting out of signature requests will void any additional reassurances should your package show as delivered according to tracking.</p>
    <div class="protect-confirm-actions">
      <button type="button" class="btn btn-primary protect-confirm-keep" onclick="keepSignature()">Keep Signature · <span id="signature-confirm-keep-price">{{ $checkoutSignature > 0 ? $money($checkoutSignature) : 'Free' }}</span></button>
      <button type="button" class="protect-confirm-remove" onclick="removeSignature()">No thanks, remove it</button>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       CART DRAWER (right-side on desktop, bottom-sheet on mobile)
       Holds ALL items — no nested scroll in the summary itself.
  ═══════════════════════════════════════════════════════════ -->
  <div class="drawer-overlay" id="cart-drawer-overlay" hidden="" onclick="closeCartDrawer()"></div>
  <aside class="cart-drawer" id="cart-drawer" role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title" hidden="">
    <div class="cart-drawer-handle" aria-hidden="true"></div>
    <header class="cart-drawer-head">
      <h2 class="cart-drawer-title" id="cart-drawer-title">Your Order <span class="cart-drawer-count">({{ $cartItemCount }} items)</span></h2>
      <button class="cart-drawer-close" type="button" onclick="closeCartDrawer()" aria-label="Close order details">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
        </svg>
      </button>
    </header>

    <div class="cart-drawer-body">
      @if(!empty($cart))
      @foreach($cart as $index => $item)
      @php
      $productName = $cartItemName($item);
      $quantity = $cartItemQty($item);
      $itemTotal = $cartItemTotal($item);
      $image = $cartItemImage($item);
      $brand = $cartItemBrand($item);
      $sku = $cartItemSku($item);
      $category = $cartItemCategory($item);
      $productId = $cartItemProductId($item);
      $isFreeGift =
      (
      !empty($item['IS_Free_Gift'])
      && strtolower((string) $item['IS_Free_Gift']) === 'yes'
      )
      ||
      (
      !empty($item['Is_Free_Gift'])
      && strtolower((string) $item['Is_Free_Gift']) === 'yes'
      );
      $bogoDiscountMessage =
	  $item['BogoDiscountMessage'] ?? '';
	  
      @endphp

      <div class="order-item-row" data-cart-index="{{ $index }}" data-product-id="{{ $productId }}" data-cart-id="{{ $item['cart_id'] ?? $item['ProductID'] ?? $item['id'] ?? '' }}" data-free-gift="{{ $isFreeGift ? '1' : '0' }}" data-brand="{{ $brand }}" data-category="{{ $category }}">
        <div class="order-item-image">
          <img class="order-item-thumb" src="{{ $image ?: url('/images/noimage-lrg.jpg') }}" alt="{{ $productName }}" loading="lazy">
        </div>
        <div class="order-item-info" id="OrderInfoDiv">
          <div class="order-item-brand">{{ $brand }}</div>
          <div class="order-item-name">{{ $productName }}</div>
          <div class="order-item-variant"></div>
          <div class="order-item-sku">{{ $sku }}</div>
          @if(isset($item['FinalSale']) && $item['FinalSale'] != '')
			<div class="order-item-variant">
				{{ $item['FinalSale'] }}
			</div>
		@endif

		@if(Auth::guard('store')->check())
			<div class="order-item-variant">
				{{ (isset($item['OrderType']) && $item['OrderType'] == 'Store') ? 'Store Item' : 'Online Item' }}
			</div>
		@endif

		@if(isset($item['stock_left']) && $item['stock_left'] < 6)
			<div class="order-item-variant stock_left pt-1">
				Only {{ $item['stock_left'] }} left in stock
			</div>
		@endif
          
          <div class="order-item-controls">
            @if($isFreeGift)
            <div class="qty-stepper" role="group" aria-label="Free Gift quantity">
              <span class="qty-value" aria-live="polite">{{ $quantity }}</span>
            </div>
            <span class="free-gift-label">Free Gift</span>
            @else
            <div class="qty-stepper" role="group" aria-label="Quantity for {{ $productName }}">
              <button class="qty-btn" type="button" aria-label="Decrease quantity" onclick="updateQty(this,-1)">−</button>
              <span class="qty-value" aria-live="polite">{{ $quantity }}</span>
              <button class="qty-btn" type="button" aria-label="Increase quantity" onclick="updateQty(this,1)">+</button>
            </div>
            <button class="item-remove" type="button" aria-label="Remove {{ $productName }} from cart" onclick="removeItem(this)">Remove</button>
            @endif
          </div>
          @if(!empty($bogoDiscountMessage))
			<div class="order-item-bogo-message pt-1" id="OrderInfoDiv">
				{!! $bogoDiscountMessage !!}
			</div>
		@endif
        </div>
        <div class="order-item-price">
          <div class="order-item-price-current">{{ $money($itemTotal) }}</div>
        </div>
      </div>
      @endforeach
      @else
      <div class="checkout-empty-state">Your cart is empty.</div>
      @endif
    </div>

    <footer class="cart-drawer-foot">
      <div class="cart-drawer-foot-total">
        <span>Subtotal</span>
        <strong>{{ $money($checkoutSubTotal) }}</strong>
      </div>
      <button type="button" class="btn btn-primary btn-full" onclick="closeCartDrawer()">Done</button>
    </footer>
  </aside>

  </div>
  @php $StripeCardVer = filemtime(config('global.SITE_JS_CORE_PATH').'stripe-card.js'); @endphp
  <script src="https://js.stripe.com/v3/"></script>
  <script src="{{config('global.SITE_JS_CORE')}}stripe-card.js?ver={{$StripeCardVer}}"></script>
  <script>
    window.stripePaymentUrls = {
      pay: @json(route('checkout.payment.stripe.pay')),
      verify: @json(route('checkout.payment.stripe.verify'))
    };
    window.selectedPayMethod = $(".payment-tab .active").attr('data-method');
    window.checkoutUrls = {
      createOrder: @json(route('checkout.order.create')),
      updateOrder: @json(route('checkout.order.update')),
      orderReceipt: @json(route('order-receipt'))
    }

    StripeCard.init(
      @json(config('services.stripe.striptkey'))
    );
</script>

  <script>
    /* ============================================================
   MaxAroma Checkout — Interaction Layer
   ============================================================ */

    'use strict';

    // ── Auth Tabs ─────────────────────────────────────────────────
    function switchAuthTab(tab) {
      const tabs = ['guest', 'signin'];
      tabs.forEach(t => {
        const btn = document.getElementById('tab-' + t);
        const panel = document.getElementById('panel-' + t);
        const isActive = t === tab;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive);
        if (isActive) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', '');
        }
      });
    }

    // ── Payment Tabs ──────────────────────────────────────────────
    function switchPayTab(tab) {
      const tabs = ['card', 'paypal', 'affirm'];
      tabs.forEach(t => {
        const btn = document.getElementById('pay-tab-' + t);
        const panel = document.getElementById('pay-panel-' + t);
        const isActive = t === tab;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive);
        if (isActive) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', '');
        }
      });
    }

    // ── Order Summary Toggle (mobile) ─────────────────────────────
    function toggleOrderSummary() {
      const card = document.getElementById('order-summary-card');
      const toggle = card.querySelector('.order-summary-toggle');
      const isOpen = card.classList.contains('open');

      card.classList.toggle('open', !isOpen);
      toggle.setAttribute('aria-expanded', String(!isOpen));
    }

    // ── Summary Accordions (coupon / gift card / shipping calc) ────
    function toggleSummaryAccordion(key) {
      const toggle = document.getElementById(key + '-toggle');
      const panel = document.getElementById(key + '-panel');
      const accordion = toggle.closest('.summary-accordion');
      const isOpen = accordion.classList.contains('open');

      accordion.classList.toggle('open', !isOpen);
      toggle.setAttribute('aria-expanded', String(!isOpen));
      if (isOpen) {
        panel.setAttribute('hidden', '');
      } else {
        panel.removeAttribute('hidden');
        const input = panel.querySelector('input');
        if (input) setTimeout(() => input.focus(), 50);
      }
    }

    function applyPromo() {
      const input = document.getElementById('promo-code');
      const result = document.getElementById('promo-result');
      const code = input ? input.value.trim().toUpperCase() : '';

      if (!input || !result || !code) {
        if (result) {
          result.innerHTML = '<p style="font-size:12px;color:var(--color-text-error);margin-top:8px;">Please enter a code.</p>';
        }
        return;
      }

      if (
        window.MaxaromaCheckoutDiscount &&
        typeof window.MaxaromaCheckoutDiscount.apply === 'function'
      ) {
        result.insertAdjacentHTML(
          'beforeend',
          '<p class="promo-applying-message" style="font-size:12px;margin-top:8px;">Applying coupon...</p>'
        );

        window.MaxaromaCheckoutDiscount.apply(code)
          .done(function(response) {
            const messages = result.querySelectorAll('.promo-applying-message');
            messages.forEach(function(message) {
              message.remove();
            });

            if (
              response &&
              (
                response.status === 'error' ||
                response.error === 1 ||
                response.error === '1'
              )
            ) {
              result.insertAdjacentHTML(
                'beforeend',
                '<p style="font-size:12px;color:var(--color-text-error);margin-top:8px;">' +
                (response.message || 'This coupon could not be applied.') +
                '</p>'
              );
              return;
            }

            if (typeof window.addAppliedCheckoutDiscount === 'function') {
              window.addAppliedCheckoutDiscount(code, response);
            }

            input.value = '';
          })
          .fail(function(xhr) {
            const messages = result.querySelectorAll('.promo-applying-message');
            messages.forEach(function(message) {
              message.remove();
            });

            const response = xhr.responseJSON || {};

            result.insertAdjacentHTML(
              'beforeend',
              '<p style="font-size:12px;color:var(--color-text-error);margin-top:8px;">' +
              (response.message || 'Unable to apply coupon. Please try again.') +
              '</p>'
            );
          });
      }
    }

    function removeCode(key) {
      document.getElementById(key + '-result').innerHTML = '';
      const field = document.querySelector('#' + key + '-panel .promo-field');
      if (field) {
        field.style.display = 'flex';
        field.querySelector('input').value = '';
        field.querySelector('input').focus();
      }
    }

    // ── Shipping Rate Calculator ──────────────────────────────────
    // No separate calculator API exists in the current One Page contract.
    // Do not display fabricated rates. The actual shipping methods are loaded
    // from the checkout shipping endpoint using the checkout address above.
    function recalcShipping() {
      const result = document.getElementById('shipcalc-result');
      if (result) {
        result.innerHTML =
          'Shipping rates are calculated from your checkout address and selected shipping method.';
      }
    }

    // ── Quantity stepper ──────────────────────────────────────────

    // ── Remove item ───────────────────────────────────────────────

    // ── Cart Drawer (right-side / bottom-sheet) ───────────────────
    let _drawerLastFocus = null;

    function openCartDrawer() {
      const drawer = document.getElementById('cart-drawer');
      const overlay = document.getElementById('cart-drawer-overlay');
      _drawerLastFocus = document.activeElement;

      overlay.hidden = false;
      drawer.hidden = false;
      document.body.style.overflow = 'hidden';

      // Move focus into the drawer (close button) for accessibility
      const closeBtn = drawer.querySelector('.cart-drawer-close');
      setTimeout(() => closeBtn && closeBtn.focus(), 50);

      document.addEventListener('keydown', _drawerKeydown);
    }

    function closeCartDrawer() {
      const drawer = document.getElementById('cart-drawer');
      const overlay = document.getElementById('cart-drawer-overlay');

      drawer.hidden = true;
      overlay.hidden = true;
      document.body.style.overflow = '';

      document.removeEventListener('keydown', _drawerKeydown);
      if (_drawerLastFocus) {
        _drawerLastFocus.focus();
        _drawerLastFocus = null;
      }
    }

    function _drawerKeydown(e) {
      const drawer = document.getElementById('cart-drawer');
      if (e.key === 'Escape') {
        closeCartDrawer();
        return;
      }
      if (e.key !== 'Tab') return;

      // Focus trap
      const focusable = drawer.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

    // ── Edit Section ──────────────────────────────────────────────
    function editSection(section) {
      const formId = 'section-' + section;
      const el = document.getElementById(formId);
      if (el) {
        el.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
        setTimeout(() => {
          const firstInput = el.querySelector('input, select, textarea');
          if (firstInput) firstInput.focus();
        }, 300);
      }
    }

    // ── Floating Label Polyfill for selects ──────────────────────
    document.querySelectorAll('.fl-input').forEach(input => {
      if (input.tagName === 'SELECT') {
        input.addEventListener('change', () => {
          input.closest('.fl-group').classList.toggle('has-value', input.value !== '');
        });
      }
    });

    // ── Shipping option selection ─────────────────────────────────
    document.querySelectorAll('.shipping-option input[type="radio"]').forEach(radio => {
      radio.addEventListener('change', () => {
        document.querySelectorAll('.shipping-option').forEach(opt => opt.classList.remove('selected'));
        radio.closest('.shipping-option').classList.add('selected');
      });
    });

    // ── Mobile bottom order summary (collapsible) ─────────────────
    function toggleMobileSummary() {
      const footer = document.getElementById('mobile-footer');
      const toggle = document.getElementById('mobile-summary-toggle');
      const overlay = document.getElementById('mobile-summary-overlay');
      const open = footer.classList.toggle('summary-open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Hide order summary' : 'Show order summary');
      if (overlay) overlay.hidden = !open;
    }

    function toggleReviewSummary() {
      // Desktop: static "Total / Includes tax" display only — the full
      // order-summary card is already always visible in the sidebar there,
      // so this row isn't interactive (matches window.innerWidth against
      // the same 960px breakpoint the CSS uses for mobile vs. desktop).
      if (window.innerWidth > 960) return;
      const card = document.getElementById('review-summary-card');
      const toggle = document.getElementById('review-summary-toggle');
      const open = card.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Hide order summary' : 'Show order summary');
    }

    // ── Backend-driven checkout state ─────────────────────────────
    // Do not calculate checkout totals in the Blade. checkout.js receives the
    // backend totals response and updates the common UI. These handlers only
    // control confirmation dialogs and lightweight UI state.

    let _protectConfirmLastFocus = null;
    let _signatureConfirmLastFocus = null;

    function syncAddonCard(id) {
      const input = document.getElementById(id);
      if (!input) return;
      const row = input.closest('.addon-row');
      if (row) row.classList.toggle('active', input.checked);
    }

    function applyMobileDiscount() {
      const input = document.getElementById('msum-promo-input');
      const msg = document.getElementById('msum-promo-msg');
      const code = input ? input.value.trim().toUpperCase() : '';

      if (!input || !msg || !code) {
        if (msg) {
          msg.textContent = 'Enter a discount code.';
          msg.className = 'msum-promo-msg error';
        }
        return;
      }

      if (
        window.MaxaromaCheckoutDiscount &&
        typeof window.MaxaromaCheckoutDiscount.apply === 'function'
      ) {
        window.MaxaromaCheckoutDiscount.apply(code)
          .done(function(response) {
            if (response && (response.status === 'error' || response.error === 1 || response.error === '1')) {
              msg.textContent = response.message || 'That code isn’t valid.';
              msg.className = 'msum-promo-msg error';
              return;
            }

            const field = document.getElementById('msum-promo-field');
            const applied = document.getElementById('msum-promo-applied');
            const label = document.getElementById('msum-promo-code-label');

            if (field) field.hidden = true;
            if (applied) applied.hidden = false;
            if (label) label.textContent = code;

            msg.textContent = 'Discount applied.';
            msg.className = 'msum-promo-msg success';
          })
          .fail(function(xhr) {
            const response = xhr.responseJSON || {};
            msg.textContent = response.message || 'Unable to apply discount.';
            msg.className = 'msum-promo-msg error';
          });
      }
    }

    function removeMobileDiscount() {
      if (
        window.MaxaromaCheckoutDiscount &&
        typeof window.MaxaromaCheckoutDiscount.removeCoupon === 'function'
      ) {
        window.MaxaromaCheckoutDiscount.removeCoupon();
      }

      const field = document.getElementById('msum-promo-field');
      const applied = document.getElementById('msum-promo-applied');
      const input = document.getElementById('msum-promo-input');
      const msg = document.getElementById('msum-promo-msg');

      if (field) field.hidden = false;
      if (applied) applied.hidden = true;
      if (input) input.value = '';
      if (msg) {
        msg.textContent = '';
        msg.className = 'msum-promo-msg';
      }
    }

    function handleProtectionToggle(checkbox) {

    /*
     * =========================================================
     * PROTECTION ON
     * =========================================================
     */
    if (checkbox.checked) {

        if (
            window.MaxaromaOnePageCheckout &&
            typeof window.MaxaromaOnePageCheckout
                .setShippingInsurance === 'function'
        ) {

            window.MaxaromaOnePageCheckout
                .setShippingInsurance('add');
        }

        syncAddonCard('protection');

        return;
    }

    /*
     * =========================================================
     * PROTECTION OFF REQUEST
     * =========================================================
     *
     * OFF is NOT confirmed yet.
     *
     * Keep Protection ON until the customer explicitly chooses
     * "No thanks, remove it".
     *
     * This prevents the normal totals flow from immediately
     * clearing Insurance and changing the popup price to $0.00.
     */

    const overlay =
        document.getElementById(
            'protect-confirm-overlay'
        );

    const modal =
        document.getElementById(
            'protect-confirm-modal'
        );

    if (!overlay || !modal) {

        /*
         * Confirmation modal is unavailable.
         * Keep Protection ON safely.
         */
        checkbox.checked = true;

        syncAddonCard('protection');

        return;
    }

    /*
     * =========================================================
     * PRESERVE CURRENT INSURANCE AMOUNT
     * =========================================================
     *
     * Read the amount BEFORE opening the confirmation.
     *
     * Example:
     *
     *     Insurance = $3.59
     *
     * Popup must show:
     *
     *     KEEP PROTECTION · $3.59
     */

    const $checkoutInsurance =
        $('#checkout-insurance');

    const $keepProtectionPrice =
        $('#protect-confirm-keep-price');

    if (
        $checkoutInsurance.length &&
        $keepProtectionPrice.length
    ) {

        const currentInsurance =
            $checkoutInsurance
                .text()
                .trim();

        if (
            currentInsurance !== ''
        ) {

            $keepProtectionPrice.text(
                currentInsurance
            );
        }
    }

    /*
     * =========================================================
     * KEEP PROTECTION ON WHILE CONFIRMATION IS OPEN
     * =========================================================
     *
     * The actual remove action is handled only by:
     *
     *     removeProtection()
     *
     * after the customer clicks:
     *
     *     "No thanks, remove it"
     */

    checkbox.checked = true;

    syncAddonCard('protection');

    /*
     * Preserve focus for modal close.
     */
    _protectConfirmLastFocus =
        checkbox;

    /*
     * Open confirmation modal.
     */
    overlay.hidden =
        false;

    modal.hidden =
        false;

    document.body.style.overflow =
        'hidden';

    /*
     * Focus Keep Protection button.
     */
    setTimeout(() => {

        const button =
            modal.querySelector(
                '.protect-confirm-keep'
            );

        if (button) {
            button.focus();
        }

    }, 50);

    document.addEventListener(
        'keydown',
        _protectConfirmKeydown
    );
}
    function closeProtectConfirm() {
      const overlay = document.getElementById('protect-confirm-overlay');
      const modal = document.getElementById('protect-confirm-modal');

      if (overlay) overlay.hidden = true;
      if (modal) modal.hidden = true;

      document.body.style.overflow = '';
      document.removeEventListener('keydown', _protectConfirmKeydown);

      if (_protectConfirmLastFocus) {
        _protectConfirmLastFocus.focus();
        _protectConfirmLastFocus = null;
      }
    }

function keepProtection() {

    const checkbox =
        document.getElementById('protection');

    /*
     * Keep the checkbox ON.
     */
    if (checkbox) {

        checkbox.checked = true;
    }

    /*
     * Keep existing addon UI behavior.
     */
    syncAddonCard('protection');

    /*
     * =========================================================
     * KEEP CURRENT INSURANCE AMOUNT
     * =========================================================
     *
     * Always use the latest Insurance amount currently shown
     * in checkout.
     *
     * Do NOT use the old Blade-rendered modal value.
     */
    const $checkoutInsurance =
        $('#checkout-insurance');

    const $keepProtectionPrice =
        $('#protect-confirm-keep-price');

    if (
        $checkoutInsurance.length &&
        $keepProtectionPrice.length
    ) {

        const currentInsurance =
            $checkoutInsurance
                .text()
                .trim();

        if (
            currentInsurance !== ''
        ) {

            $keepProtectionPrice.text(
                currentInsurance
            );
        }
    }

    /*
     * =========================================================
     * EXISTING BACKEND FLOW
     * =========================================================
     *
     * Keep Protection must restore Insurance
     * in backend/session.
     */
    if (
        window.MaxaromaOnePageCheckout &&
        typeof window.MaxaromaOnePageCheckout
            .setShippingInsurance ===
            'function'
    ) {

        window.MaxaromaOnePageCheckout
            .setShippingInsurance('add');
    }

    /*
     * Existing modal close behavior.
     */
    closeProtectConfirm();
}

    function removeProtection() {
      if (
        window.MaxaromaOnePageCheckout &&
        typeof window.MaxaromaOnePageCheckout.setShippingInsurance === 'function'
      ) {
        window.MaxaromaOnePageCheckout.setShippingInsurance('remove');
      }
      syncAddonCard('protection');
      closeProtectConfirm();
    }

    function _protectConfirmKeydown(e) {
      if (e.key === 'Escape') {
        keepProtection();
        return;
      }

      if (e.key !== 'Tab') return;

      const modal = document.getElementById('protect-confirm-modal');
      if (!modal) return;

      const focusable = modal.querySelectorAll(
        'button:not(:disabled), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );

      if (!focusable.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

	function handleSignatureToggle(checkbox) {

    /*
     * =========================================================
     * SIGNATURE ON
     * =========================================================
     */

    if (checkbox.checked) {

        if (
            window.MaxaromaOnePageCheckout &&
            typeof window.MaxaromaOnePageCheckout
                .setShippingSignature ===
                'function'
        ) {

            window.MaxaromaOnePageCheckout
                .setShippingSignature('add');
        }

        syncAddonCard(
            'request-signature'
        );

        return;
    }

    /*
     * =========================================================
     * SIGNATURE OFF REQUEST
     * =========================================================
     *
     * IMPORTANT:
     *
     * OFF is NOT confirmed yet.
     *
     * Customer has only clicked the toggle.
     * The actual removal happens only after:
     *
     *     "No thanks, remove it"
     *
     * Therefore keep the real checkbox/state ON while the
     * confirmation modal is open.
     */

    const overlay =
        document.getElementById(
            'signature-confirm-overlay'
        );

    const modal =
        document.getElementById(
            'signature-confirm-modal'
        );

    if (
        !overlay ||
        !modal
    ) {

        /*
         * If confirmation UI is unavailable,
         * fail safely and keep Signature ON.
         */
        checkbox.checked = true;

        syncAddonCard(
            'request-signature'
        );

        return;
    }

    /*
     * Restore ON immediately.
     */
    checkbox.checked = true;

    syncAddonCard(
        'request-signature'
    );

    /*
     * Preserve the current Signature state/charge.
     */
    const state =
        window.MaxaromaCheckout?.totalsState;

    if (state) {

        state.signatureApplied =
            true;
    }

    /*
     * Open confirmation modal.
     */
    _signatureConfirmLastFocus =
        checkbox;

    overlay.hidden =
        false;

    modal.hidden =
        false;

    document.body.style.overflow =
        'hidden';

    /*
     * Focus Keep Signature.
     */
    setTimeout(function () {

        const button =
            modal.querySelector(
                '.protect-confirm-keep'
            );

        if (button) {
            button.focus();
        }

    }, 50);

    document.addEventListener(
        'keydown',
        _signatureConfirmKeydown
    );
}

    function closeSignatureConfirm() {
      const overlay = document.getElementById('signature-confirm-overlay');
      const modal = document.getElementById('signature-confirm-modal');

      if (overlay) overlay.hidden = true;
      if (modal) modal.hidden = true;

      document.body.style.overflow = '';
      document.removeEventListener('keydown', _signatureConfirmKeydown);

      if (_signatureConfirmLastFocus) {
        _signatureConfirmLastFocus.focus();
        _signatureConfirmLastFocus = null;
      }
    }

    function keepSignature() {
      const checkbox = document.getElementById('request-signature');
      if (checkbox) checkbox.checked = true;
      syncAddonCard('request-signature');
      closeSignatureConfirm();
    }

    function removeSignature() {
      if (
        window.MaxaromaOnePageCheckout &&
        typeof window.MaxaromaOnePageCheckout.setShippingSignature === 'function'
      ) {
        window.MaxaromaOnePageCheckout.setShippingSignature('remove');
      }
      syncAddonCard('request-signature');
      closeSignatureConfirm();
    }

    function _signatureConfirmKeydown(e) {
      if (e.key === 'Escape') {
        keepSignature();
        return;
      }

      if (e.key !== 'Tab') return;

      const modal = document.getElementById('signature-confirm-modal');
      if (!modal) return;

      const focusable = modal.querySelectorAll(
        'button:not(:disabled), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );

      if (!focusable.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

    /*
     * Signature summary rows are part of the new Blade design. checkout.js
     * remains responsible for the AJAX request and backend totals; this listener
     * only maps that response onto the three existing summary surfaces.
     */
 document.addEventListener(
    'maxaroma:checkout-totals-updated',
    function (event) {

        const detail =
            event.detail || {};

        const response =
            detail.response || {};

        const totals =
            detail.totals || {};

        const signature =
            response.shippingSignature ||
            response.shipping_signature ||
            response.ShippingSignature ||
            {};

        /*
         * =========================================================
         * SIGNATURE CHARGE
         * =========================================================
         *
         * Priority:
         *
         * 1. Current checkout state
         * 2. Event detail
         * 3. Dedicated Signature response
         *
         * Do not let an unrelated totals refresh replace an
         * already selected $3 Signature with Free.
         */

        const checkoutState =
            window.MaxaromaCheckout?.totalsState ||
            {};

        let charge =
            parseFloat(
                checkoutState.signatureCharge ??
                detail.signatureCharge ??
                signature.charge ??
                signature.shipping_signature_charge ??
                signature.amount ??
                0
            );

        if (
            Number.isNaN(charge)
        ) {

            charge = 0;
        }

        /*
         * =========================================================
         * SIGNATURE APPLIED
         * =========================================================
         */

        const applied =
            checkoutState.signatureApplied === true ||
            detail.signatureApplied === true ||
            String(
                signature.applied || ''
            ).toLowerCase() === 'yes';

        const signatureText =
            charge > 0
                ? '$' + charge.toFixed(2)
                : 'Free';

        /*
         * =========================================================
         * SUMMARY ROWS
         * =========================================================
         */

        [
            'signature-row',
            'msum-signature-row',
            'review-signature-row'
        ].forEach(function (id) {

            const row =
                document.getElementById(id);

            if (!row) {
                return;
            }

            row.hidden =
                !applied;

            if (applied) {

                const value =
                    row.querySelector(
                        '.summary-row-value, span:last-child'
                    );

                if (value) {

                    value.textContent =
                        signatureText;
                }
            }
        });

        /*
         * =========================================================
         * ADDON PRICE
         * =========================================================
         */

        const addonPrice =
            document.getElementById(
                'signature-addon-price'
            );

        if (addonPrice) {

            addonPrice.textContent =
                applied && charge > 0
                    ? '+' + signatureText
                    : 'Free';
        }

        /*
         * =========================================================
         * CONFIRMATION MODAL
         * =========================================================
         *
         * IMPORTANT:
         *
         * Once the Remove Signature modal is OPEN, do NOT allow
         * a background checkout-totals event to overwrite its
         * current Keep Signature price.
         *
         * This is the actual fix for:
         *
         *     Keep Signature · $3.00
         *              ↓
         *     Keep Signature · Free
         */

        const confirmModal =
            document.getElementById(
                'signature-confirm-modal'
            );

        const confirmPrice =
            document.getElementById(
                'signature-confirm-keep-price'
            );

        const modalIsOpen =
            confirmModal &&
            !confirmModal.hidden;

        if (
            confirmPrice &&
            !modalIsOpen
        ) {

            confirmPrice.textContent =
                applied && charge > 0
                    ? signatureText
                    : 'Free';
        }

        /*
         * =========================================================
         * CHECKBOX
         * =========================================================
         *
         * Do not change the checkbox while the confirmation
         * modal is open.
         */

        const checkbox =
            document.getElementById(
                'request-signature'
            );

        if (
            checkbox &&
            !modalIsOpen
        ) {

            checkbox.checked =
                applied;

            syncAddonCard(
                'request-signature'
            );
        }

        /*
         * =========================================================
         * OTHER TOTALS
         * =========================================================
         */

        const subtotal =
            parseFloat(
                totals.SubTotal ?? 0
            );

        const discount =
            parseFloat(
                totals.TotalDiscount ?? 0
            );

        const shipping =
            parseFloat(
                detail.shipping ?? 0
            );

        const tax =
            parseFloat(
                detail.tax ?? 0
            );

        const netTotal =
            detail.netTotal != null
                ? parseFloat(
                    detail.netTotal
                )
                : parseFloat(
                    totals.NetTotal ?? 0
                );

        const subtotalEl =
            document.getElementById(
                'summary-subtotal-value'
            );

        if (
            subtotalEl &&
            !Number.isNaN(subtotal)
        ) {

            subtotalEl.textContent =
                '$' +
                subtotal.toFixed(2);
        }

        const savingsEl =
            document.getElementById(
                'summary-savings-value'
            );

        if (
            savingsEl &&
            !Number.isNaN(discount)
        ) {

            savingsEl.textContent =
                '−$' +
                discount.toFixed(2);
        }

        const shippingEl =
            document.getElementById(
                'summary-shipping-value'
            );

        if (shippingEl) {

            shippingEl.textContent =
                shipping > 0
                    ? '$' +
                      shipping.toFixed(2)
                    : 'Free';
        }

        const taxEl =
            document.getElementById(
                'summary-tax-value'
            );

        if (
            taxEl &&
            !Number.isNaN(tax)
        ) {

            taxEl.textContent =
                '$' +
                tax.toFixed(2);
        }

        const mobileShipping =
            document.getElementById(
                'msum-shipping-value'
            );

        if (mobileShipping) {

            mobileShipping.textContent =
                shipping > 0
                    ? '$' +
                      shipping.toFixed(2)
                    : 'Free';
        }

        const mobileTax =
            document.getElementById(
                'msum-tax-value'
            );

        if (
            mobileTax &&
            !Number.isNaN(tax)
        ) {

            mobileTax.textContent =
                '$' +
                tax.toFixed(2);
        }

        const mobileDiscount =
            document.getElementById(
                'msum-discount-value'
            );

        if (
            mobileDiscount &&
            !Number.isNaN(discount)
        ) {

            mobileDiscount.textContent =
                '−$' +
                discount.toFixed(2);
        }
    }
);
    // ── Address autocomplete intentionally disabled ───────────────
    // No verified Places/Address Suggestion API is part of the current checkout
    // contract. The shipping address fields themselves are already dynamic and
    // are consumed by checkout.js for shipping-method calculation.

    /* =========================================================
     * SHIPPING COUNTRY / STATE
     * Cached helper data; no extra country/state request.
     * ========================================================= */
    (function() {

      const states = @json($states);

      function escapeHtml(value) {
        return $('<div>').text(
          value == null ? '' : value
        ).html();
      }

      function renderShippingState(country, currentValue) {

        const container =
          document.getElementById(
            'shipping-state-container'
          );

        if (!container) {
          return;
        }

        const value = currentValue || '';

        if (country === 'US') {

          let options = '';

          Object.keys(states).forEach(function(code) {

            options +=
              '<option value="' +
              escapeHtml(code) +
              '"' +
              (code === value ? ' selected' : '') +
              '>' +
              escapeHtml(states[code]) +
              '</option>';
          });

          container.innerHTML =
            '<select class="fl-input" ' +
            'id="shipping_state" ' +
            'name="shipping[state]" ' +
            'autocomplete="shipping address-level1" ' +
            'aria-label="State" ' +
            'aria-required="true">' +
            options +
            '</select>' +
            '<label class="fl-label" ' +
            'for="shipping_state">State</label>';

          return;
        }

        container.innerHTML =
          '<input class="fl-input" ' +
          'type="text" ' +
          'id="shipping_state" ' +
          'name="shipping[state]" ' +
          'value="' + escapeHtml(value) + '" ' +
          'placeholder="State / Province" ' +
          'autocomplete="shipping address-level1" ' +
          'aria-required="true">' +
          '<label class="fl-label" ' +
          'for="shipping_state">State / Province</label>';
      }

      document.addEventListener(
        'DOMContentLoaded',
        function() {

          const country =
            document.getElementById(
              'shipping_country'
            );

          if (!country) {
            return;
          }

          country.addEventListener(
            'change',
            function() {

              const state =
                document.getElementById(
                  'shipping_state'
                );

              renderShippingState(
                this.value,
                state ? state.value : ''
              );
            }
          );
        }
      );

    })();
  </script>
  @endsection

  <script>
    window.MaxaromaCheckout = window.MaxaromaCheckout || {};

    window.MaxaromaCheckout.csrfToken =
      @json(csrf_token());

    window.MaxaromaCheckout.urls = Object.assign(
      window.MaxaromaCheckout.urls || {}, {
        /*
         * ---------------------------------------------------------
         * Shipping
         * ---------------------------------------------------------
         */

        shippingMethods: @json(
          route('checkoutnew.shipping.methods')
        ),

        setShippingMethod: @json(
          route('checkoutnew.shipping.method')
        ),

        shippingInsurance: @json(
          route('checkoutnew.shipping.insurance')
        ),

        shippingSignature: @json(
          route('checkoutnew.shipping.signature')
        ),

        /*
         * ---------------------------------------------------------
         * Discount
         * ---------------------------------------------------------
         */

        discountApply: @json(
          route('checkoutnew.discount.apply')
        ),

        discountRemove: @json(
          route('checkoutnew.discount.remove')
        ),

        discountRemoveYotpoReward: @json(
          route(
            'checkoutnew.discount.remove-yotpo-reward'
          )
        ),

        /*
         * ---------------------------------------------------------
         * Cart
         * ---------------------------------------------------------
         */

        cartAdd: @json(
          route('checkoutnew.cart.add')
        ),

        cartUpdate: @json(
          route('checkoutnew.cart.update')
        ),

        cartRemove: @json(
          route('checkoutnew.cart.remove')
        ),

        cartClear: @json(
          route('checkoutnew.cart.clear')
        ),

        cartSummary: @json(
          route('checkoutnew.cart.summary')
        ),

        /*
         * ---------------------------------------------------------
         * Address
         * ---------------------------------------------------------
         */

        addressUpdate: @json(
          route('checkoutnew.address.update')
        ),

        /*
         * ---------------------------------------------------------
         * Totals
         * ---------------------------------------------------------
         */

        totals: @json(
          route('checkoutnew.totals')
        ),

        /*
         * ---------------------------------------------------------
         * Payment availability
         *
         * Payment UI/processing is NOT being changed yet.
         * Only availability URL is exposed.
         * ---------------------------------------------------------
         */

        paymentAvailability: @json(
          route('checkoutnew.payment.availability')
        )
      }
    );

    /*
     * ---------------------------------------------------------
     * Checkout state
     * ---------------------------------------------------------
     */

    window.MaxaromaCheckout.selectedShippingMethodId =
      @json($selectedShippingMethodId);

    window.MaxaromaCheckout.onlyGCPurchased =
      @json($onlyGCPurchased);

    window.MaxaromaCheckout.checkout =
      @json($checkoutState);
  </script>
