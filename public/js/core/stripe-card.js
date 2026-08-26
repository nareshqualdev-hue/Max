window.StripeCard = {

    stripe: null,
    elements: null,

    cardNumber: null,
    cardExpiry: null,
    cardCvc: null,

    initialized: false,
    ready: {
        cardNumber: false,
        cardExpiry: false,
        cardCvc: false
    },

    async init(publishableKey) {

        /*
         * Prevent duplicate initialization.
         */
        if (this.initialized) {
            return;
        }

        const numberElement =
            document.querySelector('#stripe-card-number');

        const expiryElement =
            document.querySelector('#stripe-card-expiry');

        const cvcElement =
            document.querySelector('#stripe-card-cvv');

        /*
         * Stripe container does not exist.
         */
        if (
            !numberElement ||
            !expiryElement ||
            !cvcElement
        ) {
            console.warn(
                'Stripe card elements are not available.'
            );

            return;
        }

        this.stripe =
            Stripe(publishableKey);

        this.elements =
            this.stripe.elements();

        /*
         * Create Elements.
         */
        this.cardNumber =
            this.elements.create('cardNumber');

        this.cardExpiry =
            this.elements.create('cardExpiry');

        this.cardCvc =
            this.elements.create('cardCvc');

        /*
         * Mount.
         */
        this.cardNumber.mount(
            '#stripe-card-number'
        );

        this.cardExpiry.mount(
            '#stripe-card-expiry'
        );

        this.cardCvc.mount(
            '#stripe-card-cvv'
        );

        /*
         * Wait until Stripe confirms that
         * each Element is ready.
         */
        const readyPromises = [

            new Promise(resolve => {

                this.cardNumber.once(
                    'ready',
                    () => {
                        this.ready.cardNumber = true;
                        resolve();
                    }
                );

            }),

            new Promise(resolve => {

                this.cardExpiry.once(
                    'ready',
                    () => {
                        this.ready.cardExpiry = true;
                        resolve();
                    }
                );

            }),

            new Promise(resolve => {

                this.cardCvc.once(
                    'ready',
                    () => {
                        this.ready.cardCvc = true;
                        resolve();
                    }
                );

            })
        ];

        await Promise.all(
            readyPromises
        );

        /*
         * Validation errors.
         */
        this.cardNumber.on(
            'change',
            this.handleChange.bind(this)
        );

        this.cardExpiry.on(
            'change',
            this.handleChange.bind(this)
        );

        this.cardCvc.on(
            'change',
            this.handleChange.bind(this)
        );

        this.initialized = true;

        console.log(
            'Stripe Card initialized.'
        );
    },

    async createPaymentMethod() {

        if (!this.initialized) {
            throw new Error(
                'Stripe Card is not initialized.'
            );
        }

        /*
         * Extra safety check.
         */
        if (
            !this.ready.cardNumber ||
            !this.ready.cardExpiry ||
            !this.ready.cardCvc
        ) {
            throw new Error(
                'Stripe Card is still loading. Please wait.'
            );
        }

        /*
         * IMPORTANT:
         *
         * Use the mounted Stripe Element.
         */
        const result =
            await this.stripe
                .createPaymentMethod({
                    type: 'card',
                    card: this.cardNumber
                });

        if (result.error) {

            this.showError(
                result.error.message
            );

            throw new Error(
                result.error.message
            );
        }

        return result.paymentMethod;
    },

    async pay() {

        this.clearError();
        /*
         * Make sure Stripe is ready.
         */
        const paymentMethod =
            await this.createPaymentMethod();

        /*
         * Only the PaymentMethod ID goes
         * to Laravel.
         */
        const response =
            await fetch(
                window.stripePaymentUrls.pay,
                {
                    method: 'POST',

                    credentials: 'same-origin',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            this.csrfToken()
                    },

                    body: JSON.stringify({
                        payment_method_id:
                            paymentMethod.id
                    })
                }
            );

        const result =
            await response.json();

        if (!response.ok) {

            throw new Error(
                result.message ||
                'Payment failed.'
            );
        }

        /*
         * Payment completed.
         */
        if (result.success) {

            return {
                success: true,

                paymentIntentId:
                    result.payment_intent_id
            };
        }

        /*
         * 3DS / SCA required.
         */
        if (result.requires_action) {

            return this.handleAction(
                result.client_secret
            );
        }

        throw new Error(
            result.message ||
            'Payment failed.'
        );
    },

    async handleAction(
        clientSecret
    ) {

        const result =
            await this.stripe
                .handleCardAction(
                    clientSecret
                );

        if (result.error) {

            this.showError(
                result.error.message
            );

            throw new Error(
                result.error.message
            );
        }

        const response =
            await fetch(
                window.stripePaymentUrls.verify,
                {
                    method: 'POST',

                    credentials: 'same-origin',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            this.csrfToken()
                    },

                    body: JSON.stringify({
                        payment_intent_id:
                            result.paymentIntent.id
                    })
                }
            );

        const verification =
            await response.json();

        if (
            !response.ok ||
            !verification.success
        ) {
            throw new Error(
                verification.message ||
                'Payment verification failed.'
            );
        }

        return {
            success: true,

            paymentIntentId:
                verification.payment_intent_id
        };
    },

    handleChange(event) {

        if (!event.error) {
            return;
        }

        this.showError(
            event.error.message
        );
    },

    showError(message) {

        const element =
            document.querySelector('#stripe-error');

        if (element) {
            element.textContent = message || '';
            $('html, body').animate({
                scrollTop: $(element).offset().top - 100 // Offset by 40px for spacing/fixed headers
            }, 500);
        }
    },

    clearError() {

        this.showError('');
    },

    csrfToken() {

        const element =
            document.querySelector(
                'meta[name="csrf-token"]'
            );

        return element
            ? element.content
            : '';
    }
};