(function ($) {
    'use strict';
    const checkout = window.MaxaromaCheckout || {};
    const urls = checkout.urls || {};
    console.log('AT CHECKOUT JS LOAD:', window.MaxaromaCheckout);
    console.log('AT CHECKOUT JS URLS:', urls);
    const csrfToken =
        checkout.csrfToken ||
        $('meta[name="csrf-token"]').attr('content');

    let shippingRequest = null;
    let shippingMethodRequest = null;
    let shippingInsuranceRequest = null;
    let shippingSignatureRequest = null;
    let shippingTimer = null;

    let currentInsuranceValue = null;
    let currentSignatureCharge = null;
    let currentSignatureApplied = null;

    function showLoader(show) {
        //$('#shipping-method-loader').toggle(!!show);
        if (show === true)
            $(".cart_loader").css('visibility', 'visible');
        else
            $(".cart_loader").css('visibility', 'hidden');
    }

    function showFullPageLoader(show) {
        //$('#shipping-method-loader').toggle(!!show);
        if (show === true)
            $("#full-page-loader").removeClass('loader-hidden');
        else
            $("#full-page-loader").addClass('loader-hidden');
    }

    function showShipMethodLoader(show) {
        if (show === true)
            $(".ship_method_loader").css('visibility', 'visible');
        else
            $(".ship_method_loader").css('visibility', 'hidden');
    }

    function showMessage(selector, message, type) {
        const className =
            type === 'error'
                ? 'alert alert-danger'
                : 'alert alert-info';

        $(selector).html(
            message
                ? '<div class="' + className + '">' + message + '</div>'
                : ''
        );
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    window.announce = function (message) {
        if (!message) {
            return;
        }

        let liveRegion =
            document.getElementById(
                'maxaroma-live-region'
            );

        if (!liveRegion) {

            liveRegion =
                document.createElement('div');

            liveRegion.id =
                'maxaroma-live-region';

            liveRegion.setAttribute(
                'aria-live',
                'polite'
            );

            liveRegion.setAttribute(
                'aria-atomic',
                'true'
            );

            liveRegion.style.position =
                'absolute';

            liveRegion.style.width =
                '1px';

            liveRegion.style.height =
                '1px';

            liveRegion.style.padding =
                '0';

            liveRegion.style.margin =
                '-1px';

            liveRegion.style.overflow =
                'hidden';

            liveRegion.style.clip =
                'rect(0, 0, 0, 0)';

            liveRegion.style.whiteSpace =
                'nowrap';

            liveRegion.style.border =
                '0';

            document.body.appendChild(
                liveRegion
            );
        }

        liveRegion.textContent =
            message;
    };

    function SetFormValidation() {
        $("#checkout-login-form").validate({
            rules: {
                email: { required: true, email: true },
                password: { required: true }
            },
            messages: {
                email: { required: "Please enter email.", email: "Please enter valid email." },
                password: { required: "Please enter password." }
            },
            highlight: function(element, errorClass, validClass) {
                // Leave empty so no class is added to the input element
            }
        });
        $("#frmcheckoutaddress").validate({
            rules: {
                shipping_first_name: { required: true },
                shipping_last_name: { required: true },
                shipping_address1: { required: true },
                shipping_city: { required: true },
                shipping_state: { required: true },
                shipping_zip: { required: true }
            },
            messages: {
                shipping_first_name: { required: "Please enter first name." },
                shipping_last_name: { required: "Please enter last name." },
                shipping_address1: { required: "Please enter address." },
                shipping_city: { required: "Please enter city." },
                shipping_state: { required: "Please enter state." },
                shipping_zip: { required: "Please enter zip code" }
            },
            highlight: function(element, errorClass, validClass) {
                // Leave empty so no class is added to the input element
            },
            invalidHandler: function (event, validator) {
                if (validator.errorList.length) {
                    var firstErrorElement = validator.errorList[0].element;
                    $('html, body').animate({
                        scrollTop: $(firstErrorElement).offset().top // Offset by 40px for spacing/fixed headers
                    }, 500, function () {
                        $(firstErrorElement).focus();
                    });
                }
            }
        });
    }

    async function processCardPayment(payment_method_id,OrderID) {
        try {
            const payment = await StripeCard.pay({
                payment_method_id: payment_method_id,
                order_id: OrderID
            });
            console.log(payment);
            if (payment.success) {
                alert("Payment Successfull");
                await setOrderStatus(payment.paymentIntentId,OrderID);
            }

        } catch (error) {

            console.error(
                error
            );

            alert(
                error.message
            );

        } finally {

        }
    }

    async function createCheckoutOrder()
    {
        try {
            const paymentMethod = await StripeCard.createPaymentMethod();
            if (paymentMethod) {
                showFullPageLoader(true);
                const response =
                    await fetch(
                        window.checkoutUrls.createOrder,
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type':'application/json',
                                'Accept':'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                payment_method_id: paymentMethod.id
                            })
                        }
                    );

                const result = await response.json();
                if (!response.ok) {
                    throw new Error(
                        result.message ||
                        'Unable to create order.'
                    );
                }
                if(result.status === true)
                {
                    var order_id = result.order_id
                    processCardPayment(paymentMethod.id,order_id);
                }
                return result;
            }
        } catch (error) {
            console.error(error);
            //alert(error.message);
        } finally {

        }
    }

    async function setOrderStatus(payment_intent_id,order_id)
    {
        try {
                let payload = {
                    payment_intent_id: payment_intent_id,
                    order_id: order_id
                };
                const response =
                    await fetch(
                        window.checkoutUrls.updateOrder,
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type':'application/json',
                                'Accept':'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(payload)
                        }
                    );

                const result = await response.json();
                if (!response.ok) {
                    throw new Error(
                        result.message ||
                        'Unable to update order.'
                    );
                }
                if(result.status === true)
                {
                   window.location.href=window.checkoutUrls.orderReceipt;
                }
                return result;
        } catch (error) {
            console.error(error);
            alert(error.message);
        } finally {

        }
    }

    SetFormValidation();

    $(document).on('click',"#place-order-btn",function(){
        const btn = document.getElementById('place-order-btn');
        if (!btn) return;
        if($("#frmcheckoutaddress").valid())
        {
        }

        btn.classList.add('btn-loading');
        btn.setAttribute('aria-busy', 'true');
        btn.setAttribute('aria-label', 'Processing your order…');
        btn.disabled = true;

        // Simulate processing
        setTimeout(() => {
            btn.classList.remove('btn-loading');
            btn.disabled = false;
            // In real implementation: redirect to confirmation page
        }, 2000);
        //processCardPayment();
        createCheckoutOrder();
    });

    function getShippingAddress() {
        return {
            first_name: $('#shipping_first_name').val() || '',
            last_name: $('#shipping_last_name').val() || '',
            address1: $('#shipping_address1').val() || '',
            address2: $('#shipping_address2').val() || '',
            city: $('#shipping_city').val() || '',
            state: $('#shipping_state').val() || '',
            zip: $('#shipping_zip').val() || '',
            country: $('#shipping_country').val() || ''
        };
    }

    function getShippingFlags() {
        return {
            IsCosmo: $('#one-page-checkout').data('is-cosmo') || 'No',
            IsNandansons: $('#one-page-checkout').data('is-nandansons') || 'No',
            IsPerfumePW: $('#one-page-checkout').data('is-perfumepw') || 'No',
            IsPCA: $('#one-page-checkout').data('is-pca') || 'No',
            IsND: $('#one-page-checkout').data('is-nd') || 'No',
            IsVenderItem: $('#one-page-checkout').data('is-vender-item') || 'No',
            IsMaxaromaTwoDelivery:
                $('#one-page-checkout').data('is-maxaroma-two-delivery') || 'No',
            onlyGCPurchased:
                parseInt(checkout.onlyGCPurchased || 0, 10)
        };
    }

    function addressReady(address) {
        return (
            address.country !== '' &&
            address.state !== '' &&
            address.zip !== ''
        );
    }

    function formatMoney(value) {
        const number = parseFloat(value || 0);

        return '$' + number.toFixed(2);
    }

    function getMethodId(method) {
        return parseInt(
            method.shipping_mode_id ||
            method.shipping_method_id ||
            method.id ||
            0,
            10
        );
    }

    function getMethodName(method) {
        return (
            method.shipping_method_name ||
            method.shipping_mode_name ||
            method.name ||
            method.title ||
            'Shipping Method'
        );
    }

    function getMethodCharge(method) {
        return parseFloat(
            method.shipping_charge ??
            method.charge ??
            method.amount ??
            method.price ??
            0
        );
    }

    function getMethodDays(method) {
        return (
            method.shipping_days ||
            method.days ||
            method.delivery_days ||
            ''
        );
    }

    function renderShippingMethods(response) {

        const $list = $('#section-delivery fieldset');

        if (!$list.length) {
            return;
        }
        showShipMethodLoader(true);
        const shippingData =
            response.shippingMethods || {};

        const shippingMessages =
            shippingData.messages ||
            response.messages ||
            [];

        const datediff =
            shippingData.datediff ||
            response.datediff ||
            '';

        let methods =
            shippingData.shipping_methods ||
            response.shipping_methods ||
            [];

        if (!Array.isArray(methods)) {
            methods = Object.values(methods || {});
        }

        if (!methods.length) {
            $list.html(
                '<div class="shipping-empty">' +
                'No shipping methods are available for this address.' +
                '</div>'
            );
            showShipMethodLoader(false);
            return;
        }

        const selectedId = parseInt(
            response.selectedShippingMethodId ||
            response.selected_shipping_method_id ||
            checkout.selectedShippingMethodId ||
            0,
            10
        );

        let html = '';

        methods.forEach(function (method) {

            const methodId = parseInt(
                method.shipping_mode_id || 0,
                10
            );

            if (!methodId) {
                return;
            }

            const methodName =
                method.method_name ||
                'Shipping Method';

            const charge =
                parseFloat(
                    method.chargewithoutformat || 0
                );

            const chargeText =
                method.charge_str ||
                (
                    charge > 0
                        ? formatMoney(charge)
                        : 'FREE'
                );

            const eta =
                method.display_date
                    ? 'Arrives ' + method.display_date
                    : (
                        method.estimateShipDate || ''
                    );

            const estDate =
                method.estdate || '';

            const checked =
                methodId === selectedId
                    ? ' checked'
                    : '';

            const selectedClass =
                methodId === selectedId
                    ? ' selected'
                    : '';

            html +=
                '<label class="shipping-option' +
                selectedClass +
                '" for="shipping-method-' +
                methodId +
                '">' +

                '<input ' +
                'type="radio" ' +
                'id="shipping-method-' +
                methodId +
                '" ' +
                'name="shipping" ' +
                'value="' +
                methodId +
                '" ' +
                'data-est-date="' +
                escapeHtml(estDate) +
                '" ' +
                checked +
                '>' +

                '<div class="shipping-option-info">' +

                '<div class="shipping-option-name">' +
                methodName +
                '</div>' +

                '<div class="shipping-option-eta">' +
                eta +
                '</div>' +
                '</div>' +

                '<div class="shipping-option-price' +
                (
                    charge <= 0
                        ? ' free'
                        : ''
                ) +
                '">' +
                chargeText +
                '</div>' +

                '</label>';
        });

        let shippingMessageHtml = '';

        if (
            Array.isArray(shippingMessages) &&
            shippingMessages.length
        ) {
            shippingMessageHtml =
                shippingMessages
                    .map(function (message) {
                        return (
                            '<div class="shipping-method-message">' +
                            message +
                            '</div>'
                        );
                    })
                    .join('');
        }

        let shippingCutoffHtml = '';

        if (datediff) {
            shippingCutoffHtml =
                ' <p class="ship-urgency" role="status">' +
                'Place your order in the next ' +
                '<strong>' +
                escapeHtml(datediff) +
                '</strong> ' +
                'for delivery by the listed dates.' +
                '</p>';
        }

        $list.html(
            '<legend class="visually-hidden">' +
            'Select shipping method' +
            '</legend>' +
            shippingCutoffHtml +
            shippingMessageHtml +
            html
        );

        const $checked =
            $list.find(
                'input[name="shipping"]:checked'
            );

        if ($checked.length) {
            $checked.trigger('change');
        }
        else {
            updateOnTimeDeliveryRate();
        }
        showShipMethodLoader(false);
    }

    function updateOnTimeDeliveryRate() {

        $('.shipping-option-confidence').remove();

        const $selected = $('input[name="shipping"]:checked');

        if (!$selected.length) {
            return;
        }

        $selected
            .closest('.shipping-option')
            .find('.shipping-option-info')
            .append(
                '<div class="shipping-option-confidence">' +
                '<svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">' +
                '<path d="M7 1.5L9 5.5H13L9.5 8L11 12L7 9.5L3 12L4.5 8L1 5.5H5L7 1.5Z" fill="currentColor"></path>' +
                '</svg>' +
                '98% on-time delivery rate' +
                '</div>'
            );
    }

  function updateTotals(response) {

    response = response || {};

    window.MaxaromaCheckout =
        window.MaxaromaCheckout || {};

    window.MaxaromaCheckout.totalsState =
        window.MaxaromaCheckout.totalsState || {};

    const state =
        window.MaxaromaCheckout.totalsState;

    const totals =
        response.totals ||
        response.Totals ||
        {};

    const charges =
        totals.Charges ||
        totals.charges ||
        {};

    const shippingResponse =
        response.shipping;

    const taxResponse =
        response.tax;

    const insuranceResponse =
        response.insurance;

    /*
     * =========================================================
     * SHIPPING
     * =========================================================
     */

    const shipping =
        totals.shipping ??
        totals.Shipping ??
        totals.shipping_charge ??
        totals.ShippingCharge ??
        response.shipping_charge ??
        response.shippingCharge ??
        (
            typeof shippingResponse === 'number'
                ? shippingResponse
                : (
                    shippingResponse?.shipping_charge ??
                    shippingResponse?.charge ??
                    shippingResponse?.amount ??
                    shippingResponse?.ShippingCharge ??
                    0
                )
        );

    /*
     * =========================================================
     * TAX
     * =========================================================
     */

    const tax =
        totals.tax ??
        totals.Tax ??
        totals.tax_amount ??
        totals.TaxAmount ??
        response.tax_amount ??
        response.taxAmount ??
        (
            charges?.Tax?.charge ??
            charges?.tax?.charge ??
            (
                typeof taxResponse === 'number'
                    ? taxResponse
                    : (
                        taxResponse?.tax ??
                        taxResponse?.tax_amount ??
                        taxResponse?.Tax ??
                        taxResponse?.amount ??
                        taxResponse?.charge ??
                        0
                    )
            )
        );

    /*
     * =========================================================
     * SHIPPING INSURANCE
     * =========================================================
     *
     * IMPORTANT:
     *
     * Insurance amount and Insurance ON/OFF state
     * are separate values.
     *
     * A Signature AJAX response may not contain the
     * Insurance amount. In that case DO NOT replace the
     * existing Insurance amount with 0.
     *
     * Existing Insurance behavior remains unchanged.
     * =========================================================
     */

    const insuranceChargeFromTotals =
        charges?.ShippingInsurance?.charge ??
        charges?.shipping_insurance?.charge ??
        totals?.ShippingInsurance?.charge ??
        totals?.shipping_insurance?.charge;

    const hasInsuranceValue =
        totals.insurance !== undefined ||
        totals.Insurance !== undefined ||
        totals.shipping_insurance !== undefined ||
        totals.shipping_insurance_charge !== undefined ||
        totals.ShippingInsurance?.charge !== undefined ||
        insuranceChargeFromTotals !== undefined ||
        response.shipping_insurance_charge !== undefined ||
        response.shippingInsuranceCharge !== undefined ||
        typeof insuranceResponse === 'number' ||
        (
            insuranceResponse &&
            typeof insuranceResponse === 'object' &&
            (
                insuranceResponse.charge !== undefined ||
                insuranceResponse.shipping_insurance_charge !== undefined ||
                insuranceResponse.amount !== undefined
            )
        );

    let insuranceValue =
        totals.insurance ??
        totals.Insurance ??
        totals.shipping_insurance ??
        totals.shipping_insurance_charge ??
        totals.ShippingInsurance?.charge ??
        insuranceChargeFromTotals ??
        response.shipping_insurance_charge ??
        response.shippingInsuranceCharge ??
        (
            typeof insuranceResponse === 'number'
                ? insuranceResponse
                : (
                    insuranceResponse?.charge ??
                    insuranceResponse?.shipping_insurance_charge ??
                    insuranceResponse?.amount
                )
        );

    /*
     * If this response does not contain Insurance,
     * preserve the last known Insurance amount.
     *
     * This is required for:
     *
     * Signature ON
     *     -> updateTotals(signatureResponse)
     *     -> Insurance must remain $17.31
     *
     * Signature OFF
     *     -> updateTotals(signatureResponse)
     *     -> Insurance must remain $17.31
     */
    if (
        !hasInsuranceValue &&
        state.insurance !== undefined &&
        state.insurance !== null
    ) {

        insuranceValue =
            state.insurance;
    }

    /*
     * Final Insurance amount.
     */
    const insurance =
        parseFloat(
            insuranceValue || 0
        );

    /*
     * =========================================================
     * INSURANCE APPLIED STATE
     * =========================================================
     */

    let explicitInsuranceApplied =
        null;

    if (
        response.insurance_applied !==
        undefined
    ) {

        explicitInsuranceApplied =
            String(
                response.insurance_applied
            ).toLowerCase() === 'yes';

    } else if (
        response.insuranceApplied !==
        undefined
    ) {

        explicitInsuranceApplied =
            response.insuranceApplied === true ||
            String(
                response.insuranceApplied
            ).toLowerCase() === 'yes';

    } else if (
        response.shippingInsuranceApplied !==
        undefined
    ) {

        explicitInsuranceApplied =
            response.shippingInsuranceApplied === true ||
            String(
                response.shippingInsuranceApplied
            ).toLowerCase() === 'yes';

    } else if (
        insuranceResponse &&
        typeof insuranceResponse === 'object' &&
        insuranceResponse.applied !==
        undefined
    ) {

        explicitInsuranceApplied =
            String(
                insuranceResponse.applied
            ).toLowerCase() === 'yes';
    }

    /*
     * Backend is source of truth when it explicitly
     * provides Insurance applied state.
     */
    if (
        explicitInsuranceApplied !==
        null
    ) {

        state.insuranceApplied =
            explicitInsuranceApplied;

    } else if (
        state.insuranceApplied ===
        undefined
    ) {

        /*
         * No explicit backend state.
         *
         * Use current checkbox state.
         */
        const $protection =
            $('#protection');

        if ($protection.length) {

            state.insuranceApplied =
                $protection.is(':checked');

        } else {

            /*
             * Final legacy fallback only when no UI
             * state exists.
             */
            state.insuranceApplied =
                insurance > 0;
        }
    }

    const insuranceApplied =
        state.insuranceApplied === true;

    /*
     * Keep last known Insurance amount.
     *
     * IMPORTANT:
     * This is what allows a Signature response that does
     * not contain Insurance to preserve $17.31.
     */
    state.insurance =
        insurance;

    /*
     * =========================================================
     * SHIPPING SIGNATURE
     * =========================================================
     */

    let signatureCharge =
        parseFloat(
            state.signatureCharge ||
            0
        );

    let explicitSignatureApplied =
        null;

    const signatureResponse =
        response.shippingSignature ||
        response.shipping_signature;

    if (
        signatureResponse &&
        typeof signatureResponse ===
        'object'
    ) {

        if (
            signatureResponse.charge !==
            undefined
        ) {

            signatureCharge =
                parseFloat(
                    signatureResponse.charge ||
                    0
                );
        }

        if (
            signatureResponse.applied !==
            undefined
        ) {

            explicitSignatureApplied =
                String(
                    signatureResponse.applied
                ).toLowerCase() === 'yes';
        }
    }

    if (
        explicitSignatureApplied !==
        null
    ) {

        state.signatureApplied =
            explicitSignatureApplied;

    } else if (
        state.signatureApplied ===
        undefined
    ) {

        const $signature =
            $('#request-signature');

        state.signatureApplied =
            $signature.length
                ? $signature.is(':checked')
                : false;
    }

    state.signatureCharge =
        signatureCharge;

    const signatureApplied =
        state.signatureApplied === true;

    /*
     * =========================================================
     * SUBTOTAL
     * =========================================================
     */

    const subtotal =
        parseFloat(
            totals.SubTotal ??
            totals.subTotal ??
            totals.subtotal ??
            0
        );

    $('#summary-subtotal-value')
        .text(
            formatMoney(subtotal)
        )
        .attr(
            'data-value',
            subtotal
        );

    $('#checkout-subtotal')
        .text(
            formatMoney(subtotal)
        )
        .attr(
            'data-value',
            subtotal
        );

    /*
     * =========================================================
     * DISCOUNTS
     * =========================================================
     */

    const discounts =
        totals.Discounts ||
        totals.discounts ||
        {};

    $('.discount-row').each(function () {

        const $row =
            $(this);

        const discountKey =
            $row.attr(
                'data-discount-key'
            );

        const discount =
            discounts[discountKey];

        const amount =
            parseFloat(
                discount?.discount ??
                0
            );

        if (
            discount &&
            amount > 0
        ) {

            $row
                .removeAttr('hidden')
                .find('.summary-row-label')
                .text(
                    discount.label ||
                    discountKey
                );

            $row
                .find('.discount-value')
                .text(
                    '-' +
                    formatMoney(amount)
                );

        } else {

            $row.attr(
                'hidden',
                true
            );
        }
    });

    /*
     * Dynamic discount rows.
     */
    const $summaryTotals =
        $('.summary-totals');

    const $totalRow =
        $('#summary-total-value')
            .closest('.summary-row');

    Object.keys(discounts)
        .forEach(function (discountKey) {

            const discount =
                discounts[discountKey];

            const amount =
                parseFloat(
                    discount?.discount ??
                    0
                );

            if (
                !discount ||
                amount <= 0
            ) {
                return;
            }

            let $row =
                $summaryTotals.find(
                    '.discount-row[data-discount-key="' +
                    discountKey +
                    '"]'
                );

            if (!$row.length) {

                $row = $(
                    '<div class="summary-row discount-row">' +
                        '<span class="summary-row-label"></span>' +
                        '<span class="summary-row-value discount-value"></span>' +
                    '</div>'
                );

                $row.attr(
                    'data-discount-key',
                    discountKey
                );

                $row.insertBefore(
                    $totalRow
                );
            }

            $row
                .removeAttr('hidden')
                .find('.summary-row-label')
                .text(
                    discount.label ||
                    discountKey
                );

            $row
                .find('.discount-value')
                .text(
                    '-' +
                    formatMoney(amount)
                );
        });

    /*
     * =========================================================
     * SHIPPING
     * =========================================================
     */

    const $shippingValue =
        $('#summary-shipping-value');

    if ($shippingValue.length) {

        if (
            parseFloat(shipping || 0) <= 0
        ) {

            $shippingValue
                .text('Free')
                .css(
                    'color',
                    'var(--color-text-success)'
                );

        } else {

            $shippingValue
                .text(
                    formatMoney(shipping)
                )
                .css(
                    'color',
                    ''
                );
        }
    }

    $('#checkout-shipping')
        .text(
            formatMoney(shipping)
        )
        .attr(
            'data-value',
            shipping
        );

    /*
     * =========================================================
     * TAX
     * =========================================================
     */

    $('#summary-tax-value')
        .text(
            formatMoney(tax)
        );

    $('#checkout-tax')
        .text(
            formatMoney(tax)
        )
        .attr(
            'data-value',
            tax
        );

    /*
     * =========================================================
     * PROTECT MY ORDER
     * =========================================================
     */

    const $protection =
        $('#protection');

    if ($protection.length) {

        $protection.prop(
            'checked',
            insuranceApplied
        );

        $protection
            .closest('.addon-row')
            .toggleClass(
                'active',
                insuranceApplied
            );
    }

    /*
     * Addon price.
     */

    if (insuranceApplied) {

        $('#protection-addon-price')
            .text(
                '+' +
                formatMoney(insurance)
            );

        $('#protect-confirm-keep-price')
            .text(
                formatMoney(insurance)
            );

    } else {

        $('#protection-addon-price')
            .text(
                '+$0.00'
            );

        $('#protect-confirm-keep-price')
            .text(
                '$0.00'
            );
    }

    /*
     * Summary protection row.
     */

    const $protectionRow =
        $('#protection-row');

    if ($protectionRow.length) {

        if (insuranceApplied) {

            $protectionRow
                .removeAttr('hidden')
                .find('.summary-row-value')
                .text(
                    formatMoney(insurance)
                );

        } else {

            $protectionRow.attr(
                'hidden',
                true
            );
        }
    }

    /*
     * Existing checkout insurance element.
     */

    $('#checkout-insurance')
        .text(
            formatMoney(
                insuranceApplied
                    ? insurance
                    : 0
            )
        )
        .attr(
            'data-value',
            insuranceApplied
                ? insurance
                : 0
        );

    /*
     * =========================================================
     * SHIPPING SIGNATURE
     * =========================================================
     */

    const $signature =
        $('#request-signature');

    if ($signature.length) {

        $signature.prop(
            'checked',
            signatureApplied
        );

        $signature
            .closest('.addon-row')
            .toggleClass(
                'active',
                signatureApplied
            );
    }

    if (signatureApplied) {

        $('#signature-addon-price')
            .text(
                signatureCharge > 0
                    ? '+' +
                      formatMoney(
                          signatureCharge
                      )
                    : 'Free'
            );

    } else {

        $('#signature-addon-price')
            .text(
                'Free'
            );
    }

    const $signatureRow =
        $('#signature-row');

    if ($signatureRow.length) {

        if (signatureApplied) {

            $signatureRow
                .removeAttr('hidden')
                .find('.summary-row-value')
                .text(
                    signatureCharge > 0
                        ? formatMoney(
                            signatureCharge
                        )
                        : 'Free'
                );

        } else {

            $signatureRow.attr(
                'hidden',
                true
            );
        }
    }

    /*
     * =========================================================
     * GIFT WRAP
     * =========================================================
     */

    const giftWrap =
        parseFloat(
            charges.GiftWrappingCharge?.charge ??
            charges.GiftWrapping?.charge ??
            response.giftWrapping?.charge ??
            0
        );

    const $giftWrapRow =
        $('#gift-wrap-row');

    if ($giftWrapRow.length) {

        if (giftWrap > 0) {

            $giftWrapRow
                .removeAttr('hidden')
                .find('.summary-row-value')
                .text(
                    formatMoney(giftWrap)
                );

        } else {

            $giftWrapRow.attr(
                'hidden',
                true
            );
        }
    }

    /*
     * =========================================================
     * FINAL TOTAL
     * =========================================================
     */

    const netTotal =
        totals.NetTotal ??
        totals.netTotal ??
        totals.net_total ??
        totals.TotalAmount ??
        totals.totalAmount ??
        totals.Total ??
        totals.total ??
        totals.GrandTotal ??
        totals.grandTotal ??
        response.NetTotal ??
        response.netTotal ??
        response.GrandTotal ??
        response.grandTotal;

    if (
        netTotal !== undefined &&
        netTotal !== null
    ) {

        const numericTotal =
            parseFloat(
                netTotal
            );

        const formattedTotal =
            formatMoney(
                numericTotal
            );

        $('#summary-total-value')
            .text(
                formattedTotal
            )
            .attr(
                'data-value',
                numericTotal
            );

        $('#review-summary-total-value')
            .text(
                formattedTotal
            );

        $('#checkout-grand-total')
            .text(
                formattedTotal
            )
            .attr(
                'data-value',
                numericTotal
            );

        $('#place-order-btn-text')
            .text(
                'Place Order · ' +
                formattedTotal
            );

        $('#place-order-btn')
            .attr(
                'aria-label',
                'Place order for ' +
                formattedTotal
            );

        $('#mobile-summary-amount')
            .text(
                formattedTotal
            );

        $('#mobile-footer-amount')
            .text(
                formattedTotal
            );
    }

    /*
     * =========================================================
     * SAVE RESPONSE
     * =========================================================
     */

    restoreGiftCertificateState(
        response
    );

    window.MaxaromaCheckout.checkout =
        response;

    /*
     * =========================================================
     * NOTIFY CHECKOUT
     * =========================================================
     */

    document.dispatchEvent(
        new CustomEvent(
            'maxaroma:checkout-totals-updated',
            {
                detail: {

                    response:
                        response,

                    totals:
                        totals,

                    subtotal:
                        subtotal,

                    shipping:
                        shipping,

                    tax:
                        tax,

                    insurance:
                        insurance,

                    insuranceApplied:
                        insuranceApplied,

                    signatureCharge:
                        signatureCharge,

                    signatureApplied:
                        signatureApplied,

                    giftWrap:
                        giftWrap,

                    netTotal:
                        netTotal !== undefined &&
                        netTotal !== null
                            ? parseFloat(
                                netTotal
                            )
                            : null
                }
            }
        )
    );
}
  
    function saveShippingAddress(callback) {
        /*
         * Phase 1 intentionally does not invent a new address endpoint.
         *
         * The existing project has multiple legacy address flows.
         * Once the final CheckoutAddressController contract is confirmed,
         * this function will call that endpoint before shipping methods.
         *
         * For now, shipping methods use the current address payload directly.
         */
        if (typeof callback === 'function') {
            callback();
        }
    }

    function loadShippingMethods() {
        const address = getShippingAddress();
        console.log(
            'AT SHIPPING METHOD FUNCTION:',
            window.MaxaromaCheckout
        );

        console.log(
            'AT SHIPPING METHOD URL:',
            window.MaxaromaCheckout?.urls?.shippingMethods
        );
        console.log(
            'AT SIGNATURE FUNCTION:',
            window.MaxaromaCheckout
        );

        console.log(
            'AT SIGNATURE URL:',
            window.MaxaromaCheckout?.urls?.shippingSignature
        );
        if (!addressReady(address)) {
            $('#section-delivery fieldset').html(
                '<div class="checkout-empty-state">' +
                'Enter country, state and ZIP code to see shipping methods.' +
                '</div>'
            );

            return;
        }
        const shippingMethodsUrl =
            urls.shippingMethods ||
            '/checkoutnew/shipping-methods';

        if (shippingRequest) {
            shippingRequest.abort();
        }

        showLoader(true);
        showShipMethodLoader(true);
        showMessage('#shipping-method-messages', '');

        shippingRequest = $.ajax({
            type: 'POST',
            url: shippingMethodsUrl,
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            dataType: 'json',
            data: {
                country: address.country,
                state: address.state,
                zip: address.zip,
                city: address.city,
                flags: getShippingFlags(),
                IsCosmo: getShippingFlags().IsCosmo,
                IsNandansons: getShippingFlags().IsNandansons,
                IsPerfumePW: getShippingFlags().IsPerfumePW,
                IsPCA: getShippingFlags().IsPCA,
                IsND: getShippingFlags().IsND,
                IsVenderItem: getShippingFlags().IsVenderItem,
                IsMaxaromaTwoDelivery:
                    getShippingFlags().IsMaxaromaTwoDelivery,
                ISMaxTwoItem:
                    getShippingFlags().ISMaxTwoItem,
                ISMax2dayVal:
                    getShippingFlags().ISMax2dayVal,
                onlyGCPurchased:
                    getShippingFlags().onlyGCPurchased
            }
        })
            .done(function (response) {

                console.log("SHIPPING SUCCESS:", response);
                if (
                    response.status &&
                    response.status !== 'success'
                ) {
                    showMessage(
                        '#shipping-method-messages',
                        response.message ||
                        'Unable to load shipping methods.',
                        'error'
                    );

                    return;
                }

                renderShippingMethods(response);
                updateTotals(response);
            })
            .fail(function (xhr, status, error) {
                console.log("SHIPPING FAILED:", {
                    status: status,
                    error: error,
                    httpStatus: xhr.status,
                    response: xhr.responseText
                });

                if (status === 'abort') {
                    return;
                }

                let message =
                    'Unable to load shipping methods. Please try again.';

                if (
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {
                    message = xhr.responseJSON.message;
                }

                showMessage(
                    '#shipping-method-messages',
                    message,
                    'error'
                );
            })
            .always(function () {
                shippingRequest = null;
                showLoader(false);
            });
    }

    function queueShippingMethodsLoad() {
        showLoader(true);
        showShipMethodLoader(true);
        clearTimeout(shippingTimer);

        shippingTimer = setTimeout(function () {
            saveShippingAddress(loadShippingMethods);
        }, 350);
    }

    function setShippingMethod(shippingMethodId) {
        const address = getShippingAddress();

        if (!shippingMethodId || !addressReady(address)) {
            return;
        }

        if (!urls.setShippingMethod) {
            showMessage(
                '#shipping-method-messages',
                'Shipping method URL is not configured.',
                'error'
            );

            return;
        }

        if (shippingMethodRequest) {
            shippingMethodRequest.abort();
        }

        showLoader(true);
        showMessage('#shipping-method-messages', '');

        shippingMethodRequest = $.ajax({
            type: 'POST',
            url: urls.setShippingMethod,
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            dataType: 'json',
            data: {
                ShipMethodID: shippingMethodId,

                country: address.country,
                state: address.state,
                zip: address.zip,
                city: address.city,

                IsCosmo: getShippingFlags().IsCosmo,
                IsNandansons: getShippingFlags().IsNandansons,
                IsPerfumePW: getShippingFlags().IsPerfumePW,
                IsPCA: getShippingFlags().IsPCA,
                IsND: getShippingFlags().IsND,
                IsVenderItem: getShippingFlags().IsVenderItem,
                IsMaxaromaTwoDelivery:
                    getShippingFlags().IsMaxaromaTwoDelivery,
                ISMaxTwoItem:
                    getShippingFlags().ISMaxTwoItem,
                ISMax2dayVal:
                    getShippingFlags().ISMax2dayVal,
                onlyGCPurchased:
                    getShippingFlags().onlyGCPurchased,

                EstDate:
                    $('#section-delivery')
                        .find('input[name="shipping"]:checked')
                        .data('est-date') || '',

                action:
                    checkout.action || '',

                isPayPalSubTotal:
                    checkout.isPayPalSubTotal || 0,

                shipping_charge_paypal_product_page:
                    checkout.shippingChargePayPalProductPage || 0
            }
        })
            .done(function (response) {
                if (
                    response.status &&
                    response.status !== 'success'
                ) {
                    showMessage(
                        '#shipping-method-messages',
                        response.message ||
                        'Unable to set shipping method.',
                        'error'
                    );

                    return;
                }

                checkout.selectedShippingMethodId =
                    parseInt(shippingMethodId, 10);

                updateTotals(response);

                if (response.message) {
                    showMessage(
                        '#shipping-method-messages',
                        response.message,
                        'success'
                    );
                }
            })
            .fail(function (xhr, status) {
                if (status === 'abort') {
                    return;
                }

                let message =
                    'Unable to update shipping method. Please try again.';

                if (
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {
                    message = xhr.responseJSON.message;
                }

                showMessage(
                    '#shipping-method-messages',
                    message,
                    'error'
                );
            })
            .always(function () {
                shippingMethodRequest = null;
                showLoader(false);
            });
    }

    function copyBillingToShipping() {
        const fields = [
            'first_name',
            'last_name',
            'address1',
            'address2',
            'city',
            'state',
            'zip',
            'country'
        ];

        fields.forEach(function (field) {
            $('#shipping_' + field).val(
                $('#billing_' + field).val() || ''
            );
        });
    }

    $(document).on(
        'change',
        '#billing_as_shipping',
        function () {
            if ($(this).is(':checked')) {
                copyBillingToShipping();
            }

            queueShippingMethodsLoad();
        }
    );

    $(document).on(
        'input change',
        '#shipping_country, #shipping_state, #shipping_zip, #shipping_city',
        function () {
            queueShippingMethodsLoad();
        }
    );

    $(document).on(
        'change',
        '#shipping_first_name, #shipping_last_name, #shipping_address1, #shipping_address2',
        function () {
            /*
             * Names do not normally change shipping availability,
             * so do not trigger an API call here.
             */
        }
    );

    $(document).on(
        'change',
        '#section-delivery input[name="shipping"]',
        function () {
            updateOnTimeDeliveryRate();
            const shippingMethodId =
                parseInt($(this).val(), 10);

            if (!shippingMethodId) {
                return;
            }

            setShippingMethod(shippingMethodId);

        }
    );

    $(document).on(
        'click',
        '#checkout-login-toggle',
        function () {
            $('#checkout-login-box').slideToggle(150);
        }
    );

    $(document).ready(function () {

        /*
         * Restore server-side Insurance/Signature state
         * BEFORE shipping methods / totals AJAX starts.
         */
        restoreCheckoutAddonState();

        const address =
            getShippingAddress();

        if (addressReady(address)) {
            loadShippingMethods();
        }
    });

    window.MaxaromaOnePageCheckout = {
        loadShippingMethods: loadShippingMethods,
        setShippingMethod: setShippingMethod,
        getShippingAddress: getShippingAddress,
        setShippingInsurance: setShippingInsurance,
        setShippingSignature: setShippingSignature
    };

    /* =========================================================
       COUPON / PROMO CODE - NEW DESIGN INTEGRATION
       ========================================================= */

    window.applyPromo = function () {

        const input =
            document.getElementById('promo-code');

        const result =
            document.getElementById('promo-result');

        if (!input || !result) {
            return;
        }

        const code =
            input.value
                .trim()
                .toUpperCase();

        if (!code) {

            result.innerHTML =
                '<p style="font-size:12px;color:var(--color-text-error);margin-top:8px;">' +
                'Please enter a code.' +
                '</p>';

            return;
        }

        const button =
            document.querySelector(
                '#promo-panel button[type="button"]'
            );

        if (button) {
            button.disabled = true;
        }

        /*
         * IMPORTANT:
         *
         * Do not remove/hide the coupon input.
         * Existing applied Coupon/Reward must remain visible.
         */
        result.insertAdjacentHTML(
            'beforeend',
            '<p class="promo-applying-message" ' +
            'style="font-size:12px;margin-top:8px;">' +
            'Applying coupon...' +
            '</p>'
        );

        queueDiscountMutation({

            type: 'POST',

            url:
                urls.discountApply ||
                '/checkout/discount/apply',

            headers: {
                'X-CSRF-TOKEN':
                    csrfToken
            },

            dataType: 'json',

            data: {
                coupon_code: code
            }

        })
            .done(function (response) {

                result
                    .querySelectorAll(
                        '.promo-applying-message'
                    )
                    .forEach(function (element) {
                        element.remove();
                    });

                if (
                    response.error === 1 ||
                    response.error === '1' ||
                    response.status === 'error'
                ) {

                    result.insertAdjacentHTML(
                        'beforeend',
                        '<p class="promo-error" ' +
                        'style="' +
                        'font-size:12px;' +
                        'color:var(--color-text-error);' +
                        'margin-top:8px;' +
                        '">' +
                        (
                            response.message ||
                            'This coupon could not be applied.'
                        ) +
                        '</p>'
                    );

                    return;
                }

                /*
                 * =====================================================
                 * SUCCESS
                 * =====================================================
                 *
                 * IMPORTANT:
                 *
                 * 1. Coupon input stays visible.
                 * 2. Existing Reward/Coupon stays visible.
                 * 3. New applied code is appended.
                 * 4. Coupon + Reward can both be shown.
                 */

                const safeCode =
                    escapeHtml(code);

                /*
                 * Prevent duplicate display of same code.
                 */
                const alreadyApplied =
                    Array.from(
                        result.querySelectorAll(
                            '.coupon-applied'
                        )
                    ).some(function (row) {

                        const rowCode =
                            (
                                row.getAttribute(
                                    'data-discount-code'
                                ) || ''
                            ).toUpperCase();

                        return rowCode === code;
                    });

                if (!alreadyApplied) {

                    const appliedHtml =
                        '<div ' +
                        'class="coupon-applied animate-in" ' +
                        'data-discount-code="' +
                        safeCode +
                        '" ' +
                        'style="margin-top:12px;" ' +
                        'role="status">' +

                        '<div>' +

                        '<span class="coupon-applied-code">' +
                        safeCode +
                        '</span>' +

                        '<span style="' +
                        'font-size:12px;' +
                        'color:var(--color-text-success);' +
                        'margin-left:8px;">' +
                        'Applied' +
                        '</span>' +

                        '</div>' +

                        '<button ' +
                        'type="button" ' +
                        'class="coupon-remove" ' +
                        'data-discount-code="' +
                        safeCode +
                        '">' +
                        'Remove' +
                        '</button>' +

                        '</div>';

                    result.insertAdjacentHTML(
                        'beforeend',
                        appliedHtml
                    );
                }

                /*
                 * Clear input so another Coupon/Reward
                 * can be entered.
                 */
                input.value = '';

                /*
                 * Recalculate existing selected shipping
                 * method / totals.
                 *
                 * Existing checkout flow preserved.
                 */
                const selectedMethod =
                    $('input[name="shipping"]:checked')
                        .val();

                if (
                    selectedMethod &&
                    window.MaxaromaOnePageCheckout &&
                    typeof window.MaxaromaOnePageCheckout
                        .setShippingMethod ===
                    'function'
                ) {

                    window.MaxaromaOnePageCheckout
                        .setShippingMethod(
                            parseInt(
                                selectedMethod,
                                10
                            )
                        );
                }

            })
            .fail(function (xhr) {

                result
                    .querySelectorAll(
                        '.promo-applying-message'
                    )
                    .forEach(function (element) {
                        element.remove();
                    });

                const response =
                    xhr.responseJSON ||
                    {};

                result.insertAdjacentHTML(
                    'beforeend',
                    '<p class="promo-error" ' +
                    'style="' +
                    'font-size:12px;' +
                    'color:var(--color-text-error);' +
                    'margin-top:8px;' +
                    '">' +
                    (
                        response.message ||
                        'Unable to apply coupon. Please try again.'
                    ) +
                    '</p>'
                );

            })
            .always(function () {

                if (button) {
                    button.disabled = false;
                }

            });
    };
    /* =========================================================
       REMOVE COUPON
       ========================================================= */
    $(document).on(
        'click',
        '.coupon-remove, #maxaroma-remove-coupon, #maxaroma-remove-yotpo-reward',
        function (e) {

            e.preventDefault();

            const button = $(this);

            const result =
                document.getElementById(
                    'promo-result'
                );

            /*
             * Determine whether this is:
             *
             * Coupon
             * OR
             * Yotpo Reward
             */
            let discountType =
                button.attr(
                    'data-discount-kind'
                );

            if (!discountType) {

                if (
                    button.attr('id') ===
                    'maxaroma-remove-yotpo-reward'
                ) {
                    discountType = 'reward';
                } else {
                    discountType = 'coupon';
                }
            }

            /*
             * Select correct backend endpoint.
             */
            const removeUrl =
                discountType === 'reward'
                    ? (
                        urls.discountRemoveYotpoReward ||
                        '/checkout/discount/remove-yotpo-reward'
                    )
                    : (
                        urls.discountRemove ||
                        '/checkout/discount/remove'
                    );

            /*
             * Prevent double click.
             */
            button.prop(
                'disabled',
                true
            );

            queueDiscountMutation({

                type: 'POST',

                url: removeUrl,

                headers: {
                    'X-CSRF-TOKEN':
                        csrfToken
                },

                dataType: 'json',

                data: {}

            })
                .done(function (response) {

                    if (
                        response.error === 1 ||
                        response.error === '1' ||
                        response.status === 'error'
                    ) {

                        button.prop(
                            'disabled',
                            false
                        );

                        if (result) {

                            result.insertAdjacentHTML(
                                'beforeend',
                                '<p style="' +
                                'font-size:12px;' +
                                'color:var(--color-text-error);' +
                                'margin-top:8px;' +
                                '">' +
                                (
                                    response.message ||
                                    'Unable to remove discount.'
                                ) +
                                '</p>'
                            );
                        }

                        return;
                    }

                    /*
                     * =====================================================
                     * SUCCESS
                     * =====================================================
                     *
                     * IMPORTANT:
                     *
                     * Remove ONLY the clicked row.
                     *
                     * Do NOT:
                     *
                     * result.innerHTML = '';
                     *
                     * Otherwise Coupon + Reward બંને disappear.
                     */

                    const row =
                        button.closest(
                            '.coupon-applied'
                        );

                    if (row.length) {

                        row.remove();

                    } else if (result) {

                        /*
                         * Compatibility for old static button.
                         */
                        button.closest(
                            '[role="status"]'
                        ).remove();
                    }

                    /*
                     * Keep Coupon/Reward input visible.
                     */
                    const input =
                        document.getElementById(
                            'promo-code'
                        );

                    if (input) {

                        input.value = '';

                        const field =
                            input.closest(
                                '.promo-field'
                            );

                        if (field) {
                            field.style.display = '';
                        }
                    }

                    /*
                     * Clear only corresponding UI state.
                     */
                    if (result) {

                        if (
                            discountType ===
                            'reward'
                        ) {

                            delete result.dataset
                                .yotpoCode;

                        } else {

                            delete result.dataset
                                .couponCode;
                        }
                    }

                    /*
                     * Existing checkout recalculation.
                     */
                    const selectedMethod =
                        $(
                            'input[name="shipping"]:checked'
                        ).val();

                    if (
                        selectedMethod &&
                        window.MaxaromaOnePageCheckout &&
                        typeof window
                            .MaxaromaOnePageCheckout
                            .setShippingMethod ===
                        'function'
                    ) {

                        window.MaxaromaOnePageCheckout
                            .setShippingMethod(
                                parseInt(
                                    selectedMethod,
                                    10
                                )
                            );
                    }

                })
                .fail(function (xhr) {

                    button.prop(
                        'disabled',
                        false
                    );

                    const response =
                        xhr.responseJSON ||
                        {};

                    if (result) {

                        result.insertAdjacentHTML(
                            'beforeend',
                            '<p style="' +
                            'font-size:12px;' +
                            'color:var(--color-text-error);' +
                            'margin-top:8px;' +
                            '">' +
                            (
                                response.message ||
                                'Unable to remove discount.'
                            ) +
                            '</p>'
                        );
                    }
                });
        }
    );
    /* =========================================================
       CART API - NEW CHECKOUT ARCHITECTURE
       =========================================================
       Integrated into the existing checkout.js.
       No separate checkout cart JS file.
       ========================================================= */
    function getFreeGiftImageUrl(gift) {

        gift = gift || {};

        const imageValue =
            gift.Image ||
            gift.Billing_Image ||
            gift.image ||
            gift.products_image ||
            gift.product_image ||
            '';

        if (!imageValue) {
            return '/images/noimage-lrg.jpg';
        }

        const imageString =
            String(imageValue).trim();

        /*
         * Backend returned full <img ...> HTML.
         */
        const imgMatch =
            imageString.match(
                /<img[^>]+src=["']([^"']+)["']/i
            );

        if (
            imgMatch &&
            imgMatch[1]
        ) {
            return imgMatch[1];
        }

        /*
         * Backend returned src="..."
         */
        const srcMatch =
            imageString.match(
                /src=["']([^"']+)["']/i
            );

        if (
            srcMatch &&
            srcMatch[1]
        ) {
            return srcMatch[1];
        }

        /*
         * Backend returned plain image URL/path.
         */
        if (
            imageString.indexOf('<') === -1
        ) {
            return imageString;
        }

        return '/images/noimage-lrg.jpg';
    }

    function appendFreeGiftCartItems(response) {

        response = response || {};

        /*
         * handleCartResponse() already normalizes the final
         * backend cart into response.cart.
         *
         * Supported:
         * response.cart
         * response.cart.Cart
         */
        let cart = [];

        if (
            Array.isArray(
                response.cart
            )
        ) {

            cart =
                response.cart;

        } else if (
            response.cart &&
            Array.isArray(
                response.cart.Cart
            )
        ) {

            cart =
                response.cart.Cart;
        }

        if (!cart.length) {
            return;
        }

        /*
         * Only Free Gift items.
         */
        const freeGifts =
            cart.filter(
                function (item) {

                    return (
                        item &&
                        (
                            String(
                                item.IS_Free_Gift ||
                                ''
                            ).toLowerCase() ===
                            'yes'

                            ||

                            String(
                                item.Is_Free_Gift ||
                                ''
                            ).toLowerCase() ===
                            'yes'
                        )
                    );
                }
            );

        if (!freeGifts.length) {
            return;
        }

        const mainList =
            document.getElementById(
                'checkout-cart-items'
            );

        if (!mainList) {
            return;
        }

        freeGifts.forEach(
            function (gift) {

                const productId =
                    parseInt(
                        gift.ProductID ||
                        0,
                        10
                    );

                if (!productId) {
                    return;
                }

                /*
                 * Do not append duplicate Free Gift.
                 */
                const existing =
                    mainList.querySelector(
                        '.order-item-row' +
                        '[data-product-id="' +
                        productId +
                        '"]' +
                        '[data-free-gift="1"]'
                    );

                if (existing) {

                    /*
                     * Existing row found.
                     * Update its quantity only.
                     */
                    const existingQty =
                        existing.querySelector(
                            '.qty-value'
                        );

                    if (existingQty) {

                        existingQty.textContent =
                            parseInt(
                                gift.Qty ||
                                1,
                                10
                            );
                    }

                    return;
                }

                const productName =
                    gift.ProductName ||
                    'Free Gift';

                const brand =
                    gift.manufactureName ||
                    '';

                let sku =
                    gift.ORGSKU ||
                    gift.SKU ||
                    '';

                sku =
                    String(sku);

                if (
                    sku &&
                    sku.toUpperCase()
                        .indexOf('GIFT-') !== 0
                ) {

                    sku =
                        'GIFT-' +
                        sku;
                }

                const qty =
                    parseInt(
                        gift.Qty ||
                        1,
                        10
                    ) || 1;

                /*
                 * =================================================
                 * IMAGE
                 * =================================================
                 *
                 * First try Image.
                 * Then Billing_Image.
                 *
                 * Backend currently returns:
                 * <img src="...">
                 */
                let imageUrl =
                    '/images/noimage-lrg.jpg';

                const imageHtml =
                    gift.Image ||
                    gift.Billing_Image ||
                    '';

                if (
                    typeof imageHtml ===
                    'string'
                ) {

                    const match =
                        imageHtml.match(
                            /<img[^>]+src=["']([^"']*)["']/i
                        );

                    if (
                        match &&
                        match[1] &&
                        match[1].trim()
                    ) {

                        imageUrl =
                            match[1].trim();
                    }
                }

                /*
                 * If backend sends a direct URL/path.
                 */
                if (
                    imageUrl ===
                    '/images/noimage-lrg.jpg'
                    &&
                    typeof imageHtml ===
                    'string'
                    &&
                    imageHtml.indexOf('<img') ===
                    -1
                    &&
                    imageHtml.trim()
                ) {

                    imageUrl =
                        imageHtml.trim();
                }

                /*
                 * Create Free Gift row.
                 */
                const row =
                    document.createElement(
                        'div'
                    );

                row.className =
                    'order-item-row';

                row.dataset.productId =
                    String(productId);

                row.dataset.cartId =
                    String(
                        gift.CartID ||
                        gift.cart_id ||
                        productId
                    );

                row.dataset.freeGift =
                    '1';

                row.dataset.brand =
                    brand;

                row.dataset.category =
                    gift.CategoryName ||
                    '';

                row.innerHTML =

                    '<div class="order-item-image">' +

                    '<img ' +
                    'class="order-item-thumb" ' +
                    'src="' +
                    imageUrl.replace(
                        /"/g,
                        '&quot;'
                    ) +
                    '" ' +
                    'alt="' +
                    productName.replace(
                        /"/g,
                        '&quot;'
                    ) +
                    '" ' +
                    'loading="lazy">' +

                    '</div>' +

                    '<div class="order-item-info">' +

                    '<div class="order-item-brand">' +
                    brand +
                    '</div>' +

                    '<div class="order-item-name">' +
                    productName +
                    '</div>' +

                    '<div class="order-item-variant">' +
                    'Free Gift' +
                    '</div>' +

                    '<div class="order-item-sku">' +
                    sku +
                    '</div>' +

                    '<div class="order-item-controls">' +

                    '<div ' +
                    'class="qty-stepper" ' +
                    'role="group" ' +
                    'aria-label="Free Gift quantity">' +

                    '<span ' +
                    'class="qty-value" ' +
                    'aria-live="polite">' +
                    qty +
                    '</span>' +

                    '</div>' +

                    '<span class="free-gift-label">' +
                    'Free Gift' +
                    '</span>' +

                    '</div>' +

                    '</div>' +

                    '<div class="order-item-price">' +

                    '<div ' +
                    'class="order-item-price-current">' +
                    '$0.00' +
                    '</div>' +

                    '</div>';

                /*
                 * =================================================
                 * MAIN CHECKOUT SUMMARY
                 * =================================================
                 *
                 * Always append the newly returned Free Gift.
                 *
                 * Do NOT wait for Qty 7.
                 */
                const moreButton =
                    mainList.querySelector(
                        '.view-all-items'
                    );

                if (moreButton) {

                    mainList.insertBefore(
                        row,
                        moreButton
                    );

                } else {

                    mainList.appendChild(
                        row
                    );
                }

                /*
                 * =================================================
                 * CART DRAWER
                 * =================================================
                 */
                const drawerBody =
                    document.querySelector(
                        '.cart-drawer-body'
                    );

                if (drawerBody) {

                    const drawerRow =
                        row.cloneNode(
                            true
                        );

                    drawerBody.appendChild(
                        drawerRow
                    );
                }
            }
        );

        /*
         * Keep existing cart count logic.
         */
        if (
            typeof updateCartItemCountAfterChange ===
            'function'
        ) {

            updateCartItemCountAfterChange(
                cart
            );
        }
    }
    function handleCartResponse(response) {

        response = response || {};

        if (response.checkout) {
            updateTotals(
                response.checkout
            );
        }

        /*
         * =====================================================
         * NORMAL CART
         * =====================================================
         */
        let cart = [];

        if (
            response.cart &&
            Array.isArray(
                response.cart.Cart
            )
        ) {

            cart =
                response.cart.Cart;

        } else if (
            Array.isArray(
                response.cart
            )
        ) {

            cart =
                response.cart;
        }

        /*
         * =====================================================
         * FINAL FREE GIFT CART
         * =====================================================
         *
         * IMPORTANT:
         * After Free Gift add/remove, the backend returns
         * the FINAL cart inside response.freeGift.cart.
         *
         * This must be used instead of the old response.cart.
         */
        let freeGiftCart = [];

        if (
            response.freeGift &&
            response.freeGift.cart &&
            Array.isArray(
                response.freeGift.cart.Cart
            )
        ) {

            freeGiftCart =
                response.freeGift.cart.Cart;

        } else if (
            response.freeGift &&
            Array.isArray(
                response.freeGift.cart
            )
        ) {

            freeGiftCart =
                response.freeGift.cart;

        } else if (
            response.freeGift &&
            response.freeGift.checkout &&
            response.freeGift.checkout.cart &&
            Array.isArray(
                response.freeGift.checkout.cart.Cart
            )
        ) {

            freeGiftCart =
                response.freeGift.checkout.cart.Cart;
        }

        /*
         * =====================================================
         * FINAL UI CART
         * =====================================================
         *
         * If Free Gift processing returned a final cart,
         * ALWAYS use that cart.
         *
         * This fixes:
         *
         * Refresh
         * Qty 6 -> Qty 5
         * Backend removes Free Gift
         * UI must also remove Free Gift
         */
        let finalUiCart = cart;

        if (
            response.freeGift &&
            (
                (
                    response.freeGift.cart &&
                    Array.isArray(
                        response.freeGift.cart.Cart
                    )
                ) ||
                Array.isArray(
                    response.freeGift.cart
                ) ||
                (
                    response.freeGift.checkout &&
                    response.freeGift.checkout.cart &&
                    Array.isArray(
                        response.freeGift.checkout.cart.Cart
                    )
                )
            )
        ) {

            finalUiCart =
                freeGiftCart;
        }

        /*
         * =====================================================
         * UI RESPONSE
         * =====================================================
         */
        const uiResponse = {
            ...response,
            cart: finalUiCart
        };

        /*
         * Backend owns Free Gift add/remove.
         * Frontend only renders final backend state.
         */
        appendFreeGiftCartItems(
            uiResponse
        );

        handleFreeGiftResponse(
            uiResponse
        );

        document.dispatchEvent(
            new CustomEvent(
                'maxaroma:checkout-cart-updated',
                {
                    detail: {
                        response:
                            response,

                        cart:
                            finalUiCart,

                        checkout:
                            response.checkout ||
                            null
                    }
                }
            )
        );

        return response;
    }
    function cartRequest(url, data) {

        if (!url) {
            return $.Deferred()
                .reject({
                    responseJSON: {
                        status: 'error',
                        message: 'Cart URL is not configured.'
                    }
                })
                .promise();
        }

        return $.ajax({
            type: 'POST',
            url: url,
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            dataType: 'json',
            data: data || {}
        })
            .then(function (response) {

                /*
                 * Backend success response.
                 */
                if (
                    response &&
                    (
                        response.success === true ||
                        response.status === 'success'
                    )
                ) {
                    return handleCartResponse(response);
                }

                /*
                 * Do not modify UI when backend says failure.
                 */
                return response;
            });
    }

    function addToCheckoutCart(productId, qty, options) {

        options = options || {};

        return cartRequest(
            urls.cartAdd || '/checkoutnew/cart/add',
            {
                product_id: parseInt(productId, 10),
                qty: parseInt(qty, 10) || 1,
                order_type:
                    options.orderType || 'Website',
                cookie:
                    options.cookie || 'No',
                gift_wrap:
                    options.giftWrap || 'No'
            }
        );
    }

    function updateCheckoutCart(productId, qty, giftWrap) {

        productId = parseInt(productId, 10);
        qty = parseInt(qty, 10);

        if (!productId || !qty || qty < 1) {
            return $.Deferred()
                .reject({
                    responseJSON: {
                        status: 'error',
                        message: 'Invalid cart quantity.'
                    }
                })
                .promise();
        }

        return cartRequest(
            urls.cartUpdate || '/checkoutnew/cart/update',
            {
                product_id: productId,
                qty: qty,
                gift_wrap: giftWrap || 'No'
            }
        );
    }

    function removeFromCheckoutCart(cartId) {

        cartId = parseInt(cartId, 10);

        if (Number.isNaN(cartId) || cartId < 0) {
            return $.Deferred()
                .reject({
                    responseJSON: {
                        status: 'error',
                        message: 'Invalid cart item.'
                    }
                })
                .promise();
        }

        return cartRequest(
            urls.cartRemove || '/checkoutnew/cart/remove',
            {
                cart_id: cartId
            }
        );
    }

    function clearCheckoutCart() {

        return cartRequest(
            urls.cartClear || '/checkoutnew/cart/clear',
            {}
        );
    }

    function updateQty(btn, delta) {

        const row =
            btn.closest('.order-item-row');

        if (!row) {
            return;
        }

        const stepper =
            btn.closest('.qty-stepper');

        if (!stepper) {
            return;
        }

        const valEl =
            stepper.querySelector('.qty-value');

        if (!valEl) {
            return;
        }

        const productId =
            parseInt(
                row.dataset.productId || '0',
                10
            );

        if (!productId) {
            console.error(
                'updateQty: product_id missing',
                row
            );
            return;
        }

        const currentQty =
            parseInt(
                valEl.textContent || '1',
                10
            ) || 1;

        const nextQty =
            Math.max(
                1,
                currentQty + parseInt(delta, 10)
            );

        if (nextQty === currentQty) {
            return;
        }

        if (row.dataset.cartUpdating === '1') {
            return;
        }

        row.dataset.cartUpdating = '1';

        const buttons =
            stepper.querySelectorAll('.qty-btn');

        buttons.forEach(function (button) {
            button.disabled = true;
        });
        showLoader(true);
        updateCheckoutCart(
            productId,
            nextQty,
            'No'
        )
            .done(function (response) {

                if (
                    !response ||
                    response.success !== true
                ) {
                    console.error(
                        'Cart quantity update failed:',
                        response
                    );
                    return;
                }

                /*
                 * =====================================================
                 * FINAL BACKEND CART
                 * =====================================================
                 */
                let responseCart = [];

                if (
                    Array.isArray(
                        response.cart
                    )
                ) {

                    responseCart =
                        response.cart;

                } else if (
                    response.cart &&
                    Array.isArray(
                        response.cart.Cart
                    )
                ) {

                    responseCart =
                        response.cart.Cart;

                } else if (
                    response.freeGift &&
                    Array.isArray(
                        response.freeGift.cart
                    )
                ) {

                    responseCart =
                        response.freeGift.cart;

                } else if (
                    response.freeGift &&
                    response.freeGift.cart &&
                    Array.isArray(
                        response.freeGift.cart.Cart
                    )
                ) {

                    responseCart =
                        response.freeGift.cart.Cart;
                }

                /*
                 * =====================================================
                 * FINAL QTY / LINE TOTAL
                 * =====================================================
                 */
                let finalQty =
                    nextQty;

                let lineTotal =
                    null;

                if (
                    responseCart.length
                ) {

                    const cartItem =
                        responseCart.find(
                            function (item) {

                                return (
                                    parseInt(
                                        item.ProductID ||
                                        0,
                                        10
                                    ) === productId
                                );
                            }
                        );

                    if (cartItem) {

                        finalQty =
                            parseInt(
                                cartItem.Qty ||
                                nextQty,
                                10
                            );

                        if (
                            cartItem.TotPrice !==
                            undefined &&
                            cartItem.TotPrice !==
                            null &&
                            cartItem.TotPrice !== ''
                        ) {

                            lineTotal =
                                parseFloat(
                                    String(
                                        cartItem.TotPrice
                                    ).replace(
                                        /[^0-9.-]/g,
                                        ''
                                    )
                                );
                        }

                        if (
                            (
                                lineTotal === null ||
                                isNaN(lineTotal)
                            ) &&
                            cartItem.Price !==
                            undefined
                        ) {

                            const unitPrice =
                                parseFloat(
                                    String(
                                        cartItem.Price
                                    ).replace(
                                        /[^0-9.-]/g,
                                        ''
                                    )
                                );

                            if (
                                !isNaN(unitPrice)
                            ) {

                                lineTotal =
                                    unitPrice *
                                    finalQty;
                            }
                        }
                    }
                }

                /*
                 * =====================================================
                 * UPDATE CURRENT QTY UI
                 * =====================================================
                 */
                valEl.textContent =
                    finalQty;

                /*
                 * =====================================================
                 * UPDATE CURRENT LINE PRICE
                 * =====================================================
                 */
                if (
                    lineTotal !== null &&
                    !isNaN(lineTotal)
                ) {

                    const priceEl =
                        row.querySelector(
                            '.order-item-price-current'
                        );

                    if (priceEl) {

                        priceEl.textContent =
                            '$' +
                            lineTotal.toFixed(2);
                    }
                }

                /*
                 * =====================================================
                 * UPDATE DUPLICATE PRODUCT ROWS
                 * =====================================================
                 */
                document
                    .querySelectorAll(
                        '.order-item-row[data-product-id="' +
                        productId +
                        '"]'
                    )
                    .forEach(function (itemRow) {

                        const quantityElement =
                            itemRow.querySelector(
                                '.qty-value'
                            );

                        if (quantityElement) {

                            quantityElement.textContent =
                                finalQty;
                        }

                        if (
                            lineTotal !== null &&
                            !isNaN(lineTotal)
                        ) {

                            const priceElement =
                                itemRow.querySelector(
                                    '.order-item-price-current'
                                );

                            if (priceElement) {

                                priceElement.textContent =
                                    '$' +
                                    lineTotal.toFixed(2);
                            }
                        }
                    });

                /*
                 * =====================================================
                 * UPDATE SUBTOTAL
                 * =====================================================
                 *
                 * Same logic as Remove Item.
                 *
                 * Backend final cart is the source of truth.
                 *
                 * Example:
                 *
                 * Product $10 × Qty 1
                 * Subtotal = $10
                 *
                 * Qty +1
                 *
                 * Product $10 × Qty 2
                 * Subtotal = $20
                 *
                 * Qty -1
                 *
                 * Product $10 × Qty 1
                 * Subtotal = $10
                 */
                updateCheckoutDrawerSubtotal({
                    cart: responseCart
                });

                /*
                 * =====================================================
                 * FREE GIFT
                 * =====================================================
                 *
                 * Do not change Free Gift logic here.
                 *
                 * Backend response already contains the final
                 * Free Gift state.
                 */

            })
            .fail(function (xhr) {

                const response =
                    xhr.responseJSON || {};

                console.error(
                    'Cart quantity update request failed:',
                    response
                );
            })
            .always(function () {

                delete row.dataset.cartUpdating;

                buttons.forEach(function (button) {
                    button.disabled = false;
                });
                showLoader(false);
            });
    }

    function handleFreeGiftResponse(response) {

        response = response || {};

        const freeGift =
            response.freeGift ||
            response.free_gift ||
            null;

        /*
         * =====================================================
         * FINAL BACKEND CART
         * =====================================================
         */
        let cart = [];

        if (Array.isArray(response.cart)) {

            cart = response.cart;

        } else if (
            response.cart &&
            Array.isArray(response.cart.Cart)
        ) {

            cart = response.cart.Cart;
        }

        /*
         * =====================================================
         * FREE GIFT STATUS
         * =====================================================
         */
        const status =
            String(
                freeGift &&
                    freeGift.status
                    ? freeGift.status
                    : ''
            ).toLowerCase();

        const removedFreeGiftCount =
            parseInt(
                freeGift &&
                    freeGift.removedFreeGiftCount
                    ? freeGift.removedFreeGiftCount
                    : 0,
                10
            );

        /*
         * =====================================================
         * REMOVE OLD FREE GIFT FROM UI FIRST
         * =====================================================
         *
         * IMPORTANT:
         *
         * Rule change:
         *
         * $400
         * -> old Free Gift
         *
         * $450
         * -> old Free Gift removed by backend
         * -> new rule popup
         *
         * Backend can return:
         *
         * status = popup
         * removedFreeGiftCount = 1
         *
         * Therefore we MUST remove the old UI gift
         * BEFORE opening the popup.
         */
        if (
            removedFreeGiftCount > 0
        ) {

            document
                .querySelectorAll(
                    '.order-item-row[data-free-gift="1"]'
                )
                .forEach(function (row) {

                    row.remove();
                });

            document
                .querySelectorAll(
                    '.cart-drawer-body .order-item-row[data-free-gift="1"]'
                )
                .forEach(function (row) {

                    row.remove();
                });

            if (
                typeof updateCartItemCountAfterChange ===
                'function'
            ) {

                updateCartItemCountAfterChange(
                    cart
                );
            }
        }

        /*
         * =====================================================
         * MULTIPLE FREE GIFT POPUP
         * =====================================================
         *
         * IMPORTANT:
         *
         * This now happens AFTER old Free Gift UI removal.
         */
        if (
            status === 'popup' &&
            freeGift
        ) {

            openFreeGiftPopup(
                freeGift
            );

            return;
        }

        /*
         * =====================================================
         * FREE GIFT REMOVED / NO RULE
         * =====================================================
         */
        const freeGiftRemoved =
            status === 'removed' ||
            status === 'no_rule' ||
            status === 'none' ||
            status === 'qualification_lost';

        if (freeGiftRemoved) {

            /*
             * In case backend says removed but did not provide
             * removedFreeGiftCount, still clean stale UI.
             */
            document
                .querySelectorAll(
                    '.order-item-row[data-free-gift="1"]'
                )
                .forEach(function (row) {

                    row.remove();
                });

            document
                .querySelectorAll(
                    '.cart-drawer-body .order-item-row[data-free-gift="1"]'
                )
                .forEach(function (row) {

                    row.remove();
                });

            if (
                typeof updateCartItemCountAfterChange ===
                'function'
            ) {

                updateCartItemCountAfterChange(
                    cart
                );
            }

            return;
        }

        /*
         * =====================================================
         * NO FREE GIFT RESPONSE
         * =====================================================
         */
        if (!freeGift) {
            return;
        }

        /*
         * =====================================================
         * AUTOMATIC SINGLE GIFT
         * =====================================================
         */
        if (
            status === 'auto_add' ||
            status === 'auto_added'
        ) {

            return;
        }
    }
    function addFreeGiftFromCheckout(
        productId,
        freeProductId,
        oneGift
    ) {

        productId =
            parseInt(
                productId || 0,
                10
            );

        freeProductId =
            parseInt(
                freeProductId || 0,
                10
            );

        if (!productId) {
            return $.Deferred()
                .reject()
                .promise();
        }

        /*
         * Free Gift uses the NEW checkout cart/add route.
         *
         * Auto gift:
         * oneGift = true
         *
         * Popup selected gifts:
         * oneGift = false
         *
         * Backend FreeGiftService remains the source of truth.
         */
        return cartRequest(
            urls.cartAdd ||
            '/checkoutnew/cart/add',
            {
                product_id:
                    productId,

                qty:
                    1,

                order_type:
                    'Website',

                cookie:
                    'No',

                gift_wrap:
                    'No',

                free_gift:
                    true,

                free_product_id:
                    freeProductId,

                free_gift_one:
                    oneGift !== false
            }
        )
            .done(function (response) {

                if (
                    response &&
                    response.checkout &&
                    typeof updateTotals ===
                    'function'
                ) {

                    updateTotals(
                        response.checkout
                    );
                }

                /*
                 * Backend already returned the final cart.
                 * Do not make another unnecessary add request.
                 */
                document.dispatchEvent(
                    new CustomEvent(
                        'maxaroma:free-gift-added',
                        {
                            detail: {
                                response:
                                    response
                            }
                        }
                    )
                );
            })
            .fail(function (xhr) {

                console.error(
                    'Free Gift add failed:',
                    xhr.responseJSON || {}
                );
            });
    }

    function openFreeGiftPopup(freeGift) {

        freeGift = freeGift || {};

        const popup =
            document.getElementById(
                'FreeGiftViewPopup'
            );

        if (!popup) {
            console.error(
                'Free Gift popup #FreeGiftViewPopup not found.'
            );
            return;
        }

        const popupHtml =
            freeGift.popupHtml || '';

        if (!popupHtml) {
            console.error(
                'Free Gift popup HTML is empty.',
                freeGift
            );
            return;
        }

        $('#FreeGiftViewPopup')
            .html(popupHtml);

        if (
            typeof $.fn.modal === 'function'
        ) {
            $('#FreeGiftViewPopup')
                .modal('show');
        }
    }

    $(document).off(
        'click.maxaromaFreeGift',
        '#btnfreegift'
    );

    $(document).on(
        'click.maxaromaFreeGift',
        '#btnfreegift',
        function (e) {

            e.preventDefault();
            e.stopImmediatePropagation();

            const button =
                this;

            const selectedProducts =
                $('input[name="txtradio[]"]:checked')
                    .map(function () {
                        return $(this).val();
                    })
                    .get();

            const freeProductId =
                parseInt(
                    $('#freeproductsid').first().val() ||
                    0,
                    10
                );

            /*
             * No gift selected.
             */
            if (
                !selectedProducts.length
            ) {

                return;
            }

            /*
             * Prevent double click.
             */
            if (
                button.dataset.freeGiftAdding ===
                '1'
            ) {
                return;
            }

            button.dataset.freeGiftAdding =
                '1';

            button.disabled =
                true;

            /*
             * Multiple gifts can be selected.
             *
             * Therefore:
             * free_gift_one = false
             *
             * FreeGiftService will NOT remove
             * the previously selected gift.
             */
            let requests = [];

            selectedProducts.forEach(
                function (productId) {

                    requests.push(
                        addFreeGiftFromCheckout(
                            productId,
                            freeProductId,
                            false
                        )
                    );
                }
            );

            $.when
                .apply(
                    $,
                    requests
                )
                .done(function () {

                    /*
                     * Close the existing old popup.
                     */
                    const popup =
                        document.querySelector(
                            '#FreeGiftViewPopup'
                        );

                    if (popup) {

                        if (
                            typeof $(popup).modal ===
                            'function'
                        ) {

                            $(popup).modal(
                                'hide'
                            );

                        } else {

                            popup.style.display =
                                'none';

                            popup.classList.remove(
                                'show'
                            );
                        }
                    }

                    /*
                     * Refresh checkout UI from backend
                     * source of truth.
                     */
                    refreshFreeGiftCartUI();

                    /*
                     * Refresh totals/cart state once.
                     */
                    if (
                        typeof loadShippingMethods ===
                        'function'
                    ) {

                        loadShippingMethods();
                    }
                })
                .fail(function (xhr) {

                    console.error(
                        'Free Gift popup add failed:',
                        xhr &&
                            xhr.responseJSON
                            ? xhr.responseJSON
                            : xhr
                    );
                })
                .always(function () {

                    delete
                        button.dataset.freeGiftAdding;

                    button.disabled =
                        false;
                });
        }
    );
    function removeItem(btn) {

        const row =
            btn.closest('.order-item-row');

        if (!row) {
            return;
        }

        const cartId =
            parseInt(
                row.dataset.cartId || '',
                10
            );

        if (Number.isNaN(cartId) || cartId < 0) {
            console.error(
                'removeItem: cart_id missing',
                row
            );
            return;
        }

        /*
         * Prevent duplicate remove requests.
         */
        if (row.dataset.cartRemoving === '1') {
            return;
        }

        row.dataset.cartRemoving = '1';

        btn.disabled = true;

        removeFromCheckoutCart(cartId)
            .done(function (response) {
                if (
                    !response ||
                    response.success !== true
                ) {
                    console.error(
                        'Cart remove failed:',
                        response
                    );

                    btn.disabled = false;
                    delete row.dataset.cartRemoving;

                    return;
                }

                /*
                 * =====================================================
                 * FINAL BACKEND CART
                 * =====================================================
                 *
                 * Backend response is the source of truth.
                 */
                let cart = [];

                if (
                    Array.isArray(response.cart)
                ) {

                    cart =
                        response.cart;

                } else if (
                    response.cart &&
                    Array.isArray(
                        response.cart.Cart
                    )
                ) {

                    cart =
                        response.cart.Cart;
                }

                /*
                 * =====================================================
                 * EMPTY CART
                 * =====================================================
                 */
                if (!cart.length) {

                    window.location.href =
                        '/shoppingcart';

                    return;
                }

                /*
                 * =====================================================
                 * REMOVE DELETED PRODUCT FROM BOTH UI LOCATIONS
                 * =====================================================
                 */
                document
                    .querySelectorAll(
                        '.order-item-row[data-cart-id="' +
                        cartId +
                        '"]'
                    )
                    .forEach(function (element) {

                        element.style.transition =
                            'opacity .15s ease';

                        element.style.opacity =
                            '0';

                        setTimeout(function () {

                            element.remove();

                        }, 150);
                    });

                /*
                 * =====================================================
                 * RE-SYNC ORDER SUMMARY
                 * =====================================================
                 */
                setTimeout(function () {

                    syncCheckoutSummaryAfterCartChange(
                        cart
                    );

                    /*
                     * =================================================
                     * UPDATE SUBTOTAL FROM FINAL BACKEND CART
                     * =================================================
                     *
                     * IMPORTANT:
                     *
                     * Do NOT call:
                     *
                     * updateTotals(response.checkout)
                     *
                     * here.
                     *
                     * The cart response is the source of truth
                     * after remove.
                     */
                    updateCheckoutDrawerSubtotal({
                        cart: cart
                    });

                }, 180);

            })
            .fail(function (xhr) {

                const response =
                    xhr.responseJSON || {};

                console.error(
                    'Cart remove request failed:',
                    response
                );

                btn.disabled = false;

                delete row.dataset.cartRemoving;
            });
    }
    function syncCheckoutSummaryAfterCartChange(cart) {

        if (!Array.isArray(cart)) {
            return;
        }

        const mainList =
            document.getElementById(
                'checkout-cart-items'
            );

        const drawerBody =
            document.querySelector(
                '.cart-drawer-body'
            );

        if (!mainList || !drawerBody) {

            updateCartItemCountAfterChange(
                cart
            );

            return;
        }

        /*
         * =====================================================
         * BACKEND CART = SOURCE OF TRUTH
         * =====================================================
         */

        const currentIds =
            new Set(
                cart.map(function (item) {

                    return String(
                        item && (
                            item.cart_id ??
                            item.ProductID ??
                            item.id ??
                            ''
                        )
                    );

                })
            );

        /*
         * =====================================================
         * REMOVE STALE NORMAL ITEMS FROM CART DRAWER
         * =====================================================
         *
         * Free Gift / Free Sample are NOT touched here.
         */
        drawerBody
            .querySelectorAll(
                '.order-item-row'
            )
            .forEach(function (row) {

                const isFreeGift =
                    row.dataset.freeGift === '1';

                if (isFreeGift) {
                    return;
                }

                const rowId =
                    String(
                        row.dataset.cartId || ''
                    );

                if (
                    !currentIds.has(rowId)
                ) {

                    row.remove();
                }
            });

        /*
         * =====================================================
         * REMOVE CURRENT NORMAL SUMMARY ROWS
         * =====================================================
         *
         * We will rebuild the first two positions from the
         * current backend cart.
         *
         * This is what makes:
         *
         * A
         * B
         *
         * become:
         *
         * B
         * C
         *
         * after A is removed.
         */
        mainList
            .querySelectorAll(
                '.order-item-row:not([data-free-gift="1"])'
            )
            .forEach(function (row) {

                row.remove();

            });

        /*
         * =====================================================
         * MORE ITEMS BUTTON
         * =====================================================
         */
        const moreButton =
            mainList.querySelector(
                '.view-all-items'
            );

        if (!moreButton) {

            updateCartItemCountAfterChange(
                cart
            );

            return;
        }

        /*
         * =====================================================
         * GET CURRENT NORMAL PRODUCTS FROM DRAWER
         * =====================================================
         *
         * Drawer already contains the latest product HTML.
         * We use it as the existing UI template.
         */
        const normalDrawerRows =
            Array.from(
                drawerBody.querySelectorAll(
                    '.order-item-row:not([data-free-gift="1"])'
                )
            ).filter(function (row) {

                return currentIds.has(
                    String(
                        row.dataset.cartId || ''
                    )
                );

            });

        /*
         * =====================================================
         * SHOW FIRST TWO PRODUCTS IN ORDER SUMMARY
         * =====================================================
         */
        normalDrawerRows
            .slice(0, 2)
            .forEach(function (drawerRow) {

                const summaryRow =
                    drawerRow.cloneNode(true);

                /*
                 * Make sure the cloned row remains a normal
                 * removable product row.
                 */
                summaryRow.dataset.freeGift =
                    '0';

                summaryRow.dataset.cartId =
                    drawerRow.dataset.cartId;

                summaryRow.dataset.productId =
                    drawerRow.dataset.productId || '';

                /*
                 * Remove animation/state from drawer clone.
                 */
                delete summaryRow.dataset.cartRemoving;

                summaryRow.style.opacity =
                    '1';

                /*
                 * Make remove button usable.
                 */
                summaryRow
                    .querySelectorAll(
                        '.item-remove'
                    )
                    .forEach(function (button) {

                        button.disabled =
                            false;

                    });

                /*
                 * Insert before:
                 *
                 * + More Items
                 */
                mainList.insertBefore(
                    summaryRow,
                    moreButton
                );
            });

        /*
         * =====================================================
         * UPDATE MORE ITEMS COUNT
         * =====================================================
         */
        updateCartItemCountAfterChange(
            cart
        );
    }
    function updateCheckoutDrawerSubtotal(response) {

        if (!response) {
            return;
        }

        /*
         * =====================================================
         * FINAL BACKEND CART
         * =====================================================
         */
        let cart = [];

        if (
            Array.isArray(response.cart)
        ) {

            cart =
                response.cart;

        } else if (
            response.cart &&
            Array.isArray(
                response.cart.Cart
            )
        ) {

            cart =
                response.cart.Cart;
        }

        /*
         * =====================================================
         * CALCULATE SUBTOTAL
         * =====================================================
         *
         * IMPORTANT:
         *
         * Use Unit Price × Quantity.
         *
         * Do NOT use TotPrice here because TotPrice can be
         * stale in the cart response immediately after remove.
         *
         * Free Gift / Free Sample are excluded.
         */
        let subtotal = 0;

        cart.forEach(function (item) {

            if (!item) {
                return;
            }

            const isFreeGift =
                String(
                    item.IS_Free_Gift ??
                    item.Is_Free_Gift ??
                    ''
                ).toLowerCase() === 'yes';

            const isFreeSample =
                String(
                    item.IS_Free_Sample ??
                    item.Is_Free_Sample ??
                    ''
                ).toLowerCase() === 'yes';

            if (
                isFreeGift ||
                isFreeSample
            ) {
                return;
            }

            const unitPrice =
                parseFloat(
                    String(
                        item.Price ??
                        item.ItemPrice ??
                        item.final_price ??
                        item.price ??
                        0
                    ).replace(
                        /[^0-9.-]/g,
                        ''
                    )
                ) || 0;

            const quantity =
                parseInt(
                    item.Qty ??
                    item.qty ??
                    item.quantity ??
                    1,
                    10
                ) || 1;

            subtotal +=
                unitPrice * quantity;
        });

        /*
         * =====================================================
         * FORMAT
         * =====================================================
         */
        const formattedSubtotal =
            '$' +
            subtotal.toFixed(2);

        /*
         * =====================================================
         * MAIN CHECKOUT SUMMARY SUBTOTAL
         * =====================================================
         */
        $('#summary-subtotal-value')
            .text(
                formattedSubtotal
            )
            .attr(
                'data-value',
                subtotal
            );

        $('#checkout-subtotal')
            .text(
                formattedSubtotal
            )
            .attr(
                'data-value',
                subtotal
            );

        /*
         * =====================================================
         * CART DRAWER SUBTOTAL
         * =====================================================
         *
         * Exact selector from checkout-page.blade.php:
         *
         * .cart-drawer-foot-total
         *      <span>Subtotal</span>
         *      <strong>$20.00</strong>
         *
         * So update the STRONG directly.
         */
        const drawerSubtotal =
            document.querySelector(
                '.cart-drawer-foot-total strong'
            );

        if (drawerSubtotal) {

            drawerSubtotal.textContent =
                formattedSubtotal;
        }

        /*
         * =====================================================
         * DEBUG
         * =====================================================
         */
        console.log(
            'Checkout drawer subtotal updated:',
            {
                itemCount: cart.length,
                subtotal: subtotal
            }
        );
    }

    function updateCartItemCountAfterChange(cart) {

        if (!Array.isArray(cart)) {
            return;
        }

        /*
         * IMPORTANT:
         *
         * More Items is based on NUMBER OF CART ITEMS/ROWS,
         * NOT product quantity.
         *
         * Example:
         *
         * Product A Qty 7
         * Free Gift Qty 1
         *
         * Normal item count = 1
         * More Items = 0
         *
         * Free Gift / Free Sample are excluded.
         */

        let itemCount = 0;

        cart.forEach(function (item) {

            if (!item) {
                return;
            }

            const isFreeGift =
                String(
                    item.IS_Free_Gift ||
                    item.Is_Free_Gift ||
                    ''
                ).toLowerCase() === 'yes';

            const isFreeSample =
                String(
                    item.IS_Free_Sample ||
                    item.Is_Free_Sample ||
                    ''
                ).toLowerCase() === 'yes';

            /*
             * Free Gift / Free Sample are not counted
             * as normal cart items.
             */
            if (
                isFreeGift ||
                isFreeSample
            ) {
                return;
            }

            /*
             * Count ONE cart line only.
             *
             * Do NOT use Qty here.
             */
            itemCount++;
        });

        /*
         * First 2 normal cart items are displayed.
         * Remaining normal cart lines are "+ X More Items".
         */
        const moreCount =
            Math.max(
                itemCount - 2,
                0
            );

        const moreItems =
            document.getElementById(
                'more-items-count'
            );

        if (moreItems) {

            moreItems.textContent =
                '+ ' +
                moreCount +
                ' More Items';
        }

        /*
         * Summary cart count.
         *
         * IMPORTANT:
         * This is also now based on normal cart lines,
         * not Qty.
         */
        document
            .querySelectorAll(
                '.summary-cart-count, ' +
                '.cart-drawer-count'
            )
            .forEach(function (element) {

                if (
                    element.classList.contains(
                        'cart-drawer-count'
                    )
                ) {

                    element.textContent =
                        '(' +
                        itemCount +
                        ' items)';

                } else {

                    element.textContent =
                        itemCount;
                }
            });

        /*
         * Review order item count.
         */
        const reviewQty =
            document.getElementById(
                'review-summary-qty'
            );

        if (reviewQty) {

            reviewQty.textContent =
                itemCount;
        }
    }

    window.updateQty = updateQty;
    window.removeItem = removeItem;

    /*
     * Public API.
     *
     * Final Blade/template selectors will be connected later.
     * Do not add UI selectors here until the final template is ready.
     */
    window.MaxaromaCheckoutCart = {
        add: addToCheckoutCart,
        update: updateCheckoutCart,
        remove: removeFromCheckoutCart,
        clear: clearCheckoutCart
    };

    $(document).on(
        'submit',
        '#checkout-login-form',
        function (e) {

            // IMPORTANT: prevent normal form submit / page refresh
            e.preventDefault();
            const $form = $(this);
            const $button = $form.find(
                'button[type="submit"]'
            );
            const $message = $form.find(
                '.checkout-login-message'
            );
            const email = $.trim(
                $form.find('[name="email"]').val()
            );
            const password =
                $form.find('[name="password"]').val();
            /*
            // Clear previous message
            $message
                .removeClass('error success')
                .html('')
                .hide();

            // Email required
            if (!email) {
                $message
                    .addClass('error')
                    .html('Please enter your email address.')
                    .show();

                return false;
            }

            // Email format
            const emailRegex =
                /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailRegex.test(email)) {
                $message
                    .addClass('error')
                    .html('Please enter a valid email address.')
                    .show();

                return false;
            }

            // Password required
            if (!password) {
                $message
                    .addClass('error')
                    .html('Please enter your password.')
                    .show();

                return false;
            }

            $button.prop('disabled', true);
            */
            if($("#checkout-login-form").valid())
            {
                $.ajax({
                    type: 'POST',
                    url: $form.attr('action'),
                    dataType: 'json',
                    data: $form.serialize(),

                    headers: {
                        'X-CSRF-TOKEN': $form
                            .find('[name="_token"]')
                            .val()
                    }

                }).done(function (response) {

                    if (
                        response.status === 'success' &&
                        response.error === 0
                    ) {
                        window.location.href =
                            response.redirect ||
                            '/secure-checkout1';

                        return;
                    }

                    $message
                        .removeClass('success')
                        .addClass('error')
                        .html(
                            response.message ||
                            'Invalid email or password.'
                        )
                        .show();

                    $button.prop('disabled', false);

                }).fail(function (xhr) {

                    let message =
                        'Invalid email or password.';

                    if (
                        xhr.responseJSON &&
                        xhr.responseJSON.message
                    ) {
                        message = xhr.responseJSON.message;
                    }

                    $message
                        .removeClass('success')
                        .addClass('error')
                        .html(message)
                        .show();

                    $button.prop('disabled', false);
                });
            }
            return false;
        }
    );
    /* =========================================================
       SHIPPING INSURANCE
       ========================================================= */

    function setShippingInsurance(action) {

        action =
            action === 'remove'
                ? 'remove'
                : 'add';

        if (!urls.shippingInsurance) {

            showMessage(
                '#shipping-method-messages',
                'Shipping insurance URL is not configured.',
                'error'
            );

            return;
        }

        if (shippingInsuranceRequest) {
            shippingInsuranceRequest.abort();
        }

        const $checkbox =
            $('#protection');

        /*
         * Remember the user's explicit choice immediately.
         *
         * This is important because updateTotals() can run again
         * after shipping/tax recalculation.
         */
        window.MaxaromaCheckout =
            window.MaxaromaCheckout || {};

        window.MaxaromaCheckout.totalsState =
            window.MaxaromaCheckout.totalsState || {};

        window.MaxaromaCheckout
            .totalsState
            .insuranceApplied =
            action === 'add';

        /*
         * Update UI immediately.
         */
        if ($checkbox.length) {

            $checkbox.prop(
                'checked',
                action === 'add'
            );

            $checkbox
                .closest('.addon-row')
                .toggleClass(
                    'active',
                    action === 'add'
                );
        }

        shippingInsuranceRequest =
            $.ajax({

                type: 'POST',

                url:
                    urls.shippingInsurance,

                headers: {
                    'X-CSRF-TOKEN':
                        csrfToken
                },

                dataType: 'json',

                data: {
                    action: action
                }

            })
                .done(function (response) {

                    if (
                        response.status &&
                        response.status !== 'success'
                    ) {

                        /*
                         * API failed.
                         * Restore the opposite state.
                         */
                        const previousState =
                            action !== 'add';

                        window.MaxaromaCheckout
                            .totalsState
                            .insuranceApplied =
                            previousState;

                        if ($checkbox.length) {

                            $checkbox.prop(
                                'checked',
                                previousState
                            );

                            $checkbox
                                .closest('.addon-row')
                                .toggleClass(
                                    'active',
                                    previousState
                                );
                        }

                        showMessage(
                            '#shipping-method-messages',
                            response.message ||
                            'Unable to update shipping insurance.',
                            'error'
                        );

                        return;
                    }

                    /*
                     * Backend is source of truth after successful AJAX.
                     */
                    if (
                        response.insurance_applied !==
                        undefined
                    ) {

                        window.MaxaromaCheckout
                            .totalsState
                            .insuranceApplied =
                            String(
                                response.insurance_applied
                            ).toLowerCase() === 'yes';

                    } else if (
                        response.applied !== undefined
                    ) {

                        window.MaxaromaCheckout
                            .totalsState
                            .insuranceApplied =
                            String(
                                response.applied
                            ).toLowerCase() === 'yes';
                    }

                    updateTotals(response);
                })
                .fail(function (xhr, status) {

                    if (status === 'abort') {
                        return;
                    }

                    /*
                     * API failed.
                     * Restore the previous state.
                     */
                    const previousState =
                        action !== 'add';

                    window.MaxaromaCheckout
                        .totalsState
                        .insuranceApplied =
                        previousState;

                    if ($checkbox.length) {

                        $checkbox.prop(
                            'checked',
                            previousState
                        );

                        $checkbox
                            .closest('.addon-row')
                            .toggleClass(
                                'active',
                                previousState
                            );
                    }

                    const response =
                        xhr.responseJSON || {};

                    showMessage(
                        '#shipping-method-messages',
                        response.message ||
                        'Unable to update shipping insurance.',
                        'error'
                    );
                })
                .always(function () {

                    shippingInsuranceRequest =
                        null;
                });
    }
    /* =========================================================
       INSURANCE CHECKBOX
       ========================================================= */

    $(document).on(
        'change',
        '#protection, #shipinsurance, #shipping_insurance',
        function () {

            const action =
                $(this).is(':checked')
                    ? 'add'
                    : 'remove';

            setShippingInsurance(action);
        }
    );

    /* =========================================================
       SIGNATURE CHECKBOX
       ========================================================= */

    $(document).on(
        'change',
        '#request-signature',
        function () {

            const action =
                $(this).is(':checked')
                    ? 'add'
                    : 'remove';

            setShippingSignature(action);
        }
    );
 
	function setShippingSignature(action) {

    action =
        action === 'remove'
            ? 'remove'
            : 'add';

    if (!urls.shippingSignature) {

        showMessage(
            '#shipping-method-messages',
            'Shipping signature URL is not configured.',
            'error'
        );

        return;
    }

    if (shippingSignatureRequest) {
        shippingSignatureRequest.abort();
    }

    const $checkbox =
        $('#request-signature');

    window.MaxaromaCheckout =
        window.MaxaromaCheckout || {};

    window.MaxaromaCheckout.totalsState =
        window.MaxaromaCheckout.totalsState || {};

    /*
     * =====================================================
     * USER EXPLICIT ACTION
     * =====================================================
     *
     * The customer explicitly clicked the checkbox.
     *
     * Keep the UI responsive immediately.
     */
    const requestedState =
        action === 'add';

    window.MaxaromaCheckout
        .totalsState
        .signatureApplied =
        requestedState;

    if ($checkbox.length) {

        $checkbox.prop(
            'checked',
            requestedState
        );

        $checkbox
            .closest('.addon-row')
            .toggleClass(
                'active',
                requestedState
            );
    }

    shippingSignatureRequest =
        $.ajax({

            type: 'POST',

            url:
                urls.shippingSignature,

            headers: {
                'X-CSRF-TOKEN':
                    csrfToken
            },

            dataType: 'json',

            data: {
                action: action
            }

        })
            .done(function (response) {

                /*
                 * =================================================
                 * BACKEND ERROR
                 * =================================================
                 */

                if (
                    response.status &&
                    response.status !== 'success'
                ) {

                    /*
                     * Restore the state before this
                     * explicit user action.
                     */
                    const previousState =
                        !requestedState;

                    window.MaxaromaCheckout
                        .totalsState
                        .signatureApplied =
                        previousState;

                    if ($checkbox.length) {

                        $checkbox.prop(
                            'checked',
                            previousState
                        );

                        $checkbox
                            .closest('.addon-row')
                            .toggleClass(
                                'active',
                                previousState
                            );
                    }

                    showMessage(
                        '#shipping-method-messages',
                        response.message ||
                        'Unable to update shipping signature.',
                        'error'
                    );

                    return;
                }

                /*
                 * =================================================
                 * BACKEND IS SOURCE OF TRUTH
                 * =================================================
                 *
                 * IMPORTANT:
                 *
                 * Do NOT determine applied state from charge.
                 *
                 * Example:
                 *
                 * applied = Yes
                 * charge  = 0
                 *
                 * This is a valid FREE Signature.
                 */
                let appliedState =
                    null;

                const signatureResponse =
                    response.shippingSignature ||
                    response.shipping_signature ||
                    response.ShippingSignature ||
                    null;

                if (
                    signatureResponse &&
                    typeof signatureResponse ===
                    'object' &&
                    signatureResponse.applied !==
                    undefined
                ) {

                    appliedState =
                        isAppliedFlag(
                            signatureResponse.applied
                        );

                } else if (
                    response.applied !==
                    undefined
                ) {

                    appliedState =
                        isAppliedFlag(
                            response.applied
                        );
                }

                /*
                 * If backend did not return explicit state,
                 * keep the state requested by the user.
                 *
                 * Do NOT use charge > 0 here.
                 */
                if (appliedState === null) {

                    appliedState =
                        requestedState;
                }

                window.MaxaromaCheckout
                    .totalsState
                    .signatureApplied =
                    appliedState;

                /*
                 * Keep checkbox synchronized.
                 */
                if ($checkbox.length) {

                    $checkbox.prop(
                        'checked',
                        appliedState
                    );

                    $checkbox
                        .closest('.addon-row')
                        .toggleClass(
                            'active',
                            appliedState
                        );
                }

                /*
                 * Backend already returned recalculated
                 * totals. updateTotals() will preserve the
                 * explicit applied state and will show:
                 *
                 * charge = 0
                 * applied = Yes
                 *
                 * as "Free" + ON.
                 */
                updateTotals(response);
            })
            .fail(function (xhr, status) {

                if (status === 'abort') {
                    return;
                }

                /*
                 * Request failed.
                 *
                 * Restore the opposite of the requested
                 * state because the backend did not confirm
                 * the user's action.
                 */
                const previousState =
                    !requestedState;

                window.MaxaromaCheckout
                    .totalsState
                    .signatureApplied =
                    previousState;

                if ($checkbox.length) {

                    $checkbox.prop(
                        'checked',
                        previousState
                    );

                    $checkbox
                        .closest('.addon-row')
                        .toggleClass(
                            'active',
                            previousState
                        );
                }

                const response =
                    xhr.responseJSON || {};

                showMessage(
                    '#shipping-method-messages',
                    response.message ||
                    'Unable to update shipping signature.',
                    'error'
                );
            })
            .always(function () {

                shippingSignatureRequest =
                    null;
            });
}
 	function restoreCheckoutAddonState() {

    window.MaxaromaCheckout =
        window.MaxaromaCheckout || {};

    window.MaxaromaCheckout.totalsState =
        window.MaxaromaCheckout.totalsState || {};

    const state =
        window.MaxaromaCheckout
            .totalsState;

    const checkoutState =
        window.MaxaromaCheckout.checkout ||
        {};

    /*
     * =====================================================
     * SHIPPING INSURANCE
     * =====================================================
     */

    let insuranceApplied = null;

    if (
        checkoutState.insurance_applied !==
        undefined
    ) {

        insuranceApplied =
            isAppliedFlag(
                checkoutState.insurance_applied
            );

    } else if (
        checkoutState.insuranceApplied !==
        undefined
    ) {

        insuranceApplied =
            isAppliedFlag(
                checkoutState.insuranceApplied
            );
    }

    if (insuranceApplied !== null) {

        state.insuranceApplied =
            insuranceApplied;

        const $protection =
            $('#protection');

        if ($protection.length) {

            $protection.prop(
                'checked',
                insuranceApplied
            );

            $protection
                .closest('.addon-row')
                .toggleClass(
                    'active',
                    insuranceApplied
                );
        }
    }

    /*
     * =====================================================
     * SHIPPING SIGNATURE
     * =====================================================
     *
     * New Checkout requirement:
     *
     * 1. Signature must NOT be automatically enabled.
     * 2. Do NOT call setShippingSignature('add') here.
     * 3. If backend explicitly returns an applied state,
     *    use that state.
     * 4. A $0 charge can still mean Signature is applied.
     *
     * Therefore:
     *
     * applied state != charge amount.
     * =====================================================
     */

    let signatureApplied = null;

    if (
        checkoutState.shippingSignature &&
        typeof checkoutState.shippingSignature ===
        'object' &&
        checkoutState.shippingSignature.applied !==
        undefined
    ) {

        signatureApplied =
            isAppliedFlag(
                checkoutState.shippingSignature.applied
            );

    } else if (
        checkoutState.shipping_signature &&
        typeof checkoutState.shipping_signature ===
        'object' &&
        checkoutState.shipping_signature.applied !==
        undefined
    ) {

        signatureApplied =
            isAppliedFlag(
                checkoutState.shipping_signature.applied
            );

    } else if (
        checkoutState.signatureApplied !==
        undefined
    ) {

        signatureApplied =
            isAppliedFlag(
                checkoutState.signatureApplied
            );

    } else if (
        checkoutState.shipping_signature_applied !==
        undefined
    ) {

        signatureApplied =
            isAppliedFlag(
                checkoutState.shipping_signature_applied
            );
    }

    /*
     * If backend did not provide an explicit Signature
     * state, keep the existing checkbox/state.
     *
     * IMPORTANT:
     * Do NOT use charge > 0 as the fallback because
     * FREE Signature has charge = 0 but can still be ON.
     *
     * New checkout initial state is OFF.
     */

    if (signatureApplied === null) {

        if (
            state.signatureApplied !==
            undefined
        ) {

            signatureApplied =
                state.signatureApplied === true;

        } else {

            signatureApplied =
                false;
        }
    }

    state.signatureApplied =
        signatureApplied;

    const $signature =
        $('#request-signature');

    if ($signature.length) {

        $signature.prop(
            'checked',
            signatureApplied
        );

        $signature
            .closest('.addon-row')
            .toggleClass(
                'active',
                signatureApplied
            );
    }

    /*
     * IMPORTANT:
     *
     * DO NOT call:
     *
     * setShippingSignature('add');
     *
     * here.
     *
     * Signature must only be added when the customer
     * explicitly enables the checkbox.
     */
}
    window.applyGiftCard = function () {

        const input =
            document.getElementById('giftcard-code');

        const result =
            document.getElementById('giftcard-result');

        if (!input || !result) {
            return;
        }

        const code =
            input.value.trim().toUpperCase();

        if (!code) {

            result.innerHTML =
                '<p style="font-size:12px;' +
                'color:var(--color-text-error);' +
                'margin-top:8px;" role="alert">' +
                'Please enter a gift card code.' +
                '</p>';

            return;
        }

        const button =
            document.querySelector(
                '#giftcard-panel button[type="button"]'
            );

        if (button) {
            button.disabled = true;
        }

        result.innerHTML =
            '<p style="font-size:12px;margin-top:8px;">' +
            'Applying gift card...' +
            '</p>';

        queueDiscountMutation({

            type: 'POST',

            url:
                '/checkoutnew/gift-certificate',

            headers: {
                'X-CSRF-TOKEN':
                    $('meta[name="csrf-token"]').attr('content')
            },

            dataType: 'json',

            data: {
                action: 'apply',
                code: code
            }

        })
            .done(function (response) {

                if (
                    !response ||
                    response.status === 'error' ||
                    response.error === 1 ||
                    response.error === '1'
                ) {

                    result.innerHTML =
                        '<p style="font-size:12px;' +
                        'color:var(--color-text-error);' +
                        'margin-top:8px;" role="alert">' +
                        (
                            response &&
                                response.message
                                ? response.message
                                : 'Unable to apply Gift Certificate.'
                        ) +
                        '</p>';

                    return;
                }

                /*
                 * Gift Certificate successfully applied.
                 */

                const field =
                    input.closest('.promo-field');

                if (field) {
                    field.style.display = 'none';
                }

                const giftCertificate =
                    response.giftCertificate || {};

                const appliedValue =
                    parseFloat(
                        giftCertificate.value || 0
                    );

                result.innerHTML =
                    '<div class="coupon-applied animate-in" ' +
                    'style="margin-top:12px;" ' +
                    'role="status" ' +
                    'aria-live="polite">' +

                    '<div>' +

                    '<span class="coupon-applied-code">' +
                    $('<div>')
                        .text(
                            giftCertificate.code || code
                        )
                        .html() +
                    '</span>' +

                    '<span style="' +
                    'font-size:12px;' +
                    'color:var(--color-text-success);' +
                    'margin-left:8px;">' +
                    'Gift card applied!' +
                    '</span>' +

                    '</div>' +

                    '<div style="font-size:12px;margin-top:4px;">' +
                    'Applied: $' +
                    appliedValue.toFixed(2) +
                    '</div>' +

                    '<button ' +
                    'type="button" ' +
                    'class="giftcard-remove" ' +
                    'onclick="removeGiftCard(\'giftcard\')"' +
                    '>' +
                    'Remove' +
                    '</button>' +

                    '</div>';

                /*
                 * Backend already recalculated totals.
                 * Update the checkout UI from response.
                 */

                if (
                    response.totals &&
                    typeof updateTotals === 'function'
                ) {
                    updateTotals(response);
                }

                announce(
                    'Gift card ' +
                    (giftCertificate.code || code) +
                    ' applied.'
                );

            })
            .fail(function (xhr) {

                const response =
                    xhr.responseJSON || {};

                result.innerHTML =
                    '<p style="font-size:12px;' +
                    'color:var(--color-text-error);' +
                    'margin-top:8px;" role="alert">' +
                    (
                        response.message ||
                        'Unable to apply Gift Certificate. Please try again.'
                    ) +
                    '</p>';

            })
            .always(function () {

                if (button) {
                    button.disabled = false;
                }

            });
    }
    window.removeGiftCard = function () {

        const input =
            document.getElementById('giftcard-code');

        const result =
            document.getElementById('giftcard-result');

        const field =
            input
                ? input.closest('.promo-field')
                : null;

        /*
         * Prevent duplicate clicks while request is running.
         */
        const button =
            document.querySelector(
                '#giftcard-result .giftcard-remove'
            );

        if (button) {
            button.disabled = true;
        }

        queueDiscountMutation({

            type: 'POST',

            url:
                '/checkoutnew/gift-certificate',

            headers: {
                'X-CSRF-TOKEN':
                    $('meta[name="csrf-token"]').attr('content')
            },

            dataType: 'json',

            data: {
                action: 'remove',
                code: ''
            }

        })
            .done(function (response) {

                if (
                    !response ||
                    response.status === 'error' ||
                    response.error === 1 ||
                    response.error === '1'
                ) {

                    if (result) {

                        result.innerHTML =
                            '<p style="' +
                            'font-size:12px;' +
                            'color:var(--color-text-error);' +
                            'margin-top:8px;' +
                            '">' +
                            (
                                response &&
                                    response.message
                                    ? response.message
                                    : 'Unable to remove Gift Certificate.'
                            ) +
                            '</p>';
                    }

                    return;
                }

                /*
                 * =====================================================
                 * SUCCESS
                 * =====================================================
                 *
                 * Backend GiftCertificateService::remove()
                 * already clears the Gift Certificate session.
                 *
                 * Do NOT call another remove handler.
                 */

                if (input) {
                    input.value = '';
                }

                if (field) {
                    field.style.display = 'flex';
                }

                if (result) {
                    result.innerHTML = '';
                }

                /*
                 * Keep frontend checkout state synchronized.
                 */
                window.MaxaromaCheckout =
                    window.MaxaromaCheckout || {};

                window.MaxaromaCheckout.checkout =
                    window.MaxaromaCheckout.checkout || {};

                window.MaxaromaCheckout.checkout
                    .giftCertificate = {
                    code: '',
                    value: 0,
                    applicableValue: 0,
                    remainingValue: 0
                };

                /*
                 * Backend already returned fresh totals.
                 */
                if (
                    response.totals &&
                    typeof updateTotals === 'function'
                ) {

                    updateTotals(response);
                }

                announce(
                    'Gift card removed.'
                );

            })
            .fail(function (xhr) {

                const response =
                    xhr.responseJSON || {};

                if (result) {

                    result.innerHTML =
                        '<p style="' +
                        'font-size:12px;' +
                        'color:var(--color-text-error);' +
                        'margin-top:8px;' +
                        '">' +
                        (
                            response.message ||
                            'Unable to remove Gift Certificate.'
                        ) +
                        '</p>';
                }

            })
            .always(function () {

                /*
                 * Button is removed on success,
                 * but re-enable it if request failed.
                 */
                if (
                    button &&
                    result &&
                    result.contains(button)
                ) {
                    button.disabled = false;
                }

            });
    };

    function restoreGiftCertificateState(response) {

        response = response || {};

        const result =
            document.getElementById('giftcard-result');

        const input =
            document.getElementById('giftcard-code');

        if (!result) {
            return;
        }

        const giftCertificate =
            response.giftCertificate ||
            {};

        const code =
            String(
                giftCertificate.code ||
                ''
            ).trim();

        const value =
            parseFloat(
                giftCertificate.value ||
                0
            );

        /*
         * No Gift Certificate applied.
         */
        if (!code || value <= 0) {

            if (input) {
                input.value = '';
            }

            result.innerHTML = '';

            const field =
                input
                    ? input.closest('.promo-field')
                    : null;

            if (field) {
                field.style.display = '';
            }

            return;
        }

        /*
         * Gift Certificate is still applied
         * after refresh.
         */

        const safeCode =
            escapeHtml(code);

        result.innerHTML =
            '<div class="coupon-applied animate-in" ' +
            'style="margin-top:12px;" ' +
            'role="status" ' +
            'aria-live="polite">' +

            '<div>' +

            '<span class="coupon-applied-code">' +
            safeCode +
            '</span>' +

            '<span style="' +
            'font-size:12px;' +
            'color:var(--color-text-success);' +
            'margin-left:8px;">' +
            'Gift card applied!' +
            '</span>' +

            '</div>' +

            '<div style="font-size:12px;margin-top:4px;">' +
            'Applied: $' +
            value.toFixed(2) +
            '</div>' +

            '<button ' +
            'type="button" ' +
            'class="giftcard-remove" ' +
            'onclick="removeGiftCard()">' +
            'Remove' +
            '</button>' +

            '</div>';

        if (input) {
            input.value = '';

            const field =
                input.closest('.promo-field');

            if (field) {
                field.style.display = 'none';
            }
        }
    }
    /* =========================================================
       DISCOUNT / GIFT CARD AJAX QUEUE
       ========================================================= */

    let discountMutationQueue = Promise.resolve();

    function queueDiscountMutation(ajaxOptions) {

        const deferred = $.Deferred();

        discountMutationQueue =
            discountMutationQueue
                .catch(function () {
                    /*
                     * Previous request failed.
                     * Continue with next queued request.
                     */
                })
                .then(function () {

                    return $.ajax(ajaxOptions)
                        .done(function (response) {

                            deferred.resolve(response);

                        })
                        .fail(function (xhr) {

                            deferred.reject(xhr);

                        });

                })
                .catch(function (error) {

                    deferred.reject(error);

                });

        return deferred.promise();
    }
    function appendNewFreeGiftCartItem(cartItem) {

        if (!cartItem) {
            return;
        }

        const productId =
            parseInt(
                cartItem.ProductID ||
                cartItem.product_id ||
                cartItem.id ||
                0,
                10
            );

        const cartId =
            parseInt(
                cartItem.CartID ||
                cartItem.cart_id ||
                0,
                10
            );

        if (!productId) {
            return;
        }

        /*
         * Do not append the same Free Gift twice.
         */
        const existingGift =
            document.querySelector(
                '.order-item-row[data-product-id="' +
                productId +
                '"][data-free-gift="1"]'
            );

        if (existingGift) {
            return;
        }

        /*
         * Find an existing cart row and clone it.
         *
         * This preserves the designer's existing HTML/design.
         * We are NOT creating a new cart design here.
         */
        const template =
            document.querySelector(
                '.order-item-row:not([data-free-gift="1"])'
            );

        if (!template) {
            console.error(
                'Free Gift: existing cart row template not found.'
            );

            return;
        }

        const row =
            template.cloneNode(true);

        row.dataset.productId =
            String(productId);

        if (cartId) {
            row.dataset.cartId =
                String(cartId);
        }

        row.dataset.freeGift =
            '1';

        /*
         * Free Gift quantity.
         */
        const quantity =
            parseInt(
                cartItem.Qty ||
                cartItem.qty ||
                cartItem.quantity ||
                1,
                10
            ) || 1;

        const controls =
            row.querySelector(
                '.order-item-controls'
            );

        if (controls) {

            const stepper =
                controls.querySelector(
                    '.qty-stepper'
                );

            if (stepper) {

                const qtyValue =
                    stepper.querySelector(
                        '.qty-value'
                    );

                if (qtyValue) {
                    qtyValue.textContent =
                        quantity;
                }

                /*
                 * Free Gift:
                 * Keep the existing quantity box design,
                 * but remove only +/- buttons.
                 */
                stepper.querySelectorAll(
                    '.qty-btn'
                ).forEach(function (button) {
                    button.remove();
                });
            }

            /*
             * Free Gift label stays beside quantity.
             */
            const existingLabel =
                controls.querySelector(
                    '.free-gift-label'
                );

            if (!existingLabel) {

                const label =
                    document.createElement(
                        'span'
                    );

                label.className =
                    'free-gift-label';

                label.textContent =
                    'Free Gift';

                controls.appendChild(
                    label
                );
            }

            /*
             * Free Gift must not have Remove action.
             */
            controls.querySelectorAll(
                '.item-remove'
            ).forEach(function (button) {
                button.remove();
            });
        }

        /*
         * Product name.
         */
        const productName =
            cartItem.ProductName ||
            cartItem.products_name ||
            cartItem.product_name ||
            cartItem.name ||
            'Free Gift';

        const sku =
            cartItem.ORGSKU ||
            cartItem.SKU ||
            cartItem.sku ||
            '';

        let giftSku = String(sku);

        if (
            giftSku &&
            giftSku.toUpperCase().indexOf('GIFT-') !== 0
        ) {
            giftSku = 'GIFT-' + giftSku;
        }

        const skuElement =
            row.querySelector(
                '.order-item-sku'
            );

        if (skuElement) {

            skuElement.textContent =
                'Item SKU: ' +
                giftSku;
        }

        const nameElement =
            row.querySelector(
                '.order-item-name'
            );

        if (nameElement) {
            nameElement.textContent =
                productName;
        }

        /*
         * Product image.
         */
        const imageUrl =
            getFreeGiftImageUrl(
                cartItem
            );

        const imageElement =
            row.querySelector(
                'img'
            );

        if (imageElement) {

            imageElement.src =
                imageUrl;

            imageElement.removeAttribute(
                'srcset'
            );
        }

        /*
         * Free Gift should not be treated as a normal
         * paid quantity item.
         *
         * Keep existing price HTML/design but show $0.00.
         */
        const priceElement =
            row.querySelector(
                '.order-item-price-current'
            );

        if (priceElement) {
            priceElement.textContent =
                '$0.00';
        }

        /*
         * Free Gift must not have a normal quantity
         * +/- control or remove button.
         */
        row.querySelectorAll(
            '.qty-stepper, .item-remove'
        ).forEach(function (element) {
            element.remove();
        });

        /*
         * Add Free Gift label if the existing row has
         * a suitable product-name area.
         */
        const nameContainer =
            row.querySelector(
                '.order-item-name'
            );

        if (
            nameContainer &&
            !row.querySelector(
                '.free-gift-label'
            )
        ) {

            const label =
                document.createElement(
                    'span'
                );

            label.className =
                'free-gift-label';

            label.textContent =
                'FREE GIFT';

            nameContainer.appendChild(
                label
            );
        }

        /*
         * ---------------------------------------------------------
         * MAIN CHECKOUT LIST
         * ---------------------------------------------------------
         *
         * Append ONLY the new Free Gift.
         */
        const mainList =
            document.getElementById(
                'checkout-cart-items'
            );

        if (mainList) {

            const moreButton =
                mainList.querySelector(
                    '.view-all-items'
                );

            if (moreButton) {

                mainList.insertBefore(
                    row,
                    moreButton
                );

            } else {

                mainList.appendChild(
                    row
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * CART DRAWER
         * ---------------------------------------------------------
         *
         * Use another clone so both locations have
         * independent DOM nodes.
         */
        const drawerBody =
            document.querySelector(
                '.cart-drawer-body'
            );

        if (drawerBody) {

            const drawerRow =
                row.cloneNode(true);

            drawerBody.appendChild(
                drawerRow
            );
        }

        /*
         * Update count using the actual current cart.
         *
         * Free Gift is intentionally excluded from the
         * normal "+ X More Items" count by the existing
         * updateCartItemCountAfterChange() logic.
         */
    }
    function refreshFreeGiftCartUI() {

        const cartSummaryUrl =
            urls.cartSummary;

        if (!cartSummaryUrl) {
            console.error(
                'Free Gift: cartSummary URL is not configured.'
            );

            return;
        }

        $.ajax({
            type: 'POST',
            url: cartSummaryUrl,
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN':
                    csrfToken
            }
        })
            .done(function (response) {

                if (
                    !response ||
                    response.success !== true ||
                    !Array.isArray(response.cart)
                ) {
                    return;
                }

                /*
                 * Find only Free Gift items.
                 */
                const freeGifts =
                    response.cart.filter(
                        function (item) {

                            return (
                                item &&
                                (
                                    String(
                                        item.IS_Free_Gift ||
                                        ''
                                    ).toLowerCase() ===
                                    'yes' ||

                                    String(
                                        item.Is_Free_Gift ||
                                        ''
                                    ).toLowerCase() ===
                                    'yes'
                                )
                            );
                        }
                    );

                if (!freeGifts.length) {
                    return;
                }

                /*
                 * Append only newly added Free Gift rows.
                 */
                freeGifts.forEach(
                    function (gift) {

                        appendNewFreeGiftCartItem(
                            gift
                        );
                    }
                );

                /*
                 * Keep existing count logic.
                 */
                updateCartItemCountAfterChange(
                    response.cart
                );
            })
            .fail(function (xhr) {

                console.error(
                    'Free Gift cart refresh failed:',
                    xhr.responseJSON || {}
                );
            });
    }

})(jQuery);
/* =========================================================
   CHECKOUT LOGIN
   ========================================================= */

