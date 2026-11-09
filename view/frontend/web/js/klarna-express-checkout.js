/**
 * Klarna Express Checkout button.
 *
 * Loads Klarna's Web SDK v2, mounts the payment presentation and button, dynamically resolves
 * shipping options against the Magento quote while the customer interacts with Klarna's UI, and
 * finally places/charges the order through the Paytrail module's Klarna AJAX endpoints.
 *
 * @see https://docs.klarna.com/acquirer/paytrail/express-checkout/web-integration/
 * @see https://docs.klarna.com/acquirer/paytrail/web-sdk/Payment/
 */
define([
    'jquery',
    'mage/cookies'
], function ($) {
    'use strict';

    var SDK_URL = 'https://js.klarna.com/web-sdk/v2/klarna.mjs';

    /**
     * POST data to one of the module's Klarna AJAX endpoints.
     *
     * The controllers read input through RequestInterface::getParam(), which does not parse JSON
     * request bodies, so the data is sent form-encoded (nested objects become shippingAddress[city]).
     *
     * @param {String} url
     * @param {Object} data
     * @return {Promise<Object>}
     */
    function post(url, data) {
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $.extend({ 'form_key': $.mage.cookies.get('form_key') }, data || {})
            }).done(resolve).fail(function (xhr, status, error) {
                reject(new Error(error || status));
            });
        });
    }

    /**
     * Flatten Klarna's stateContext.shipping ({ recipient, address, shippingOption }) into the flat
     * structure expected by QuoteAddressUpdater::applyFull().
     *
     * @param {Object} shipping
     * @return {Object}
     */
    function flattenShipping(shipping) {
        var recipient = shipping.recipient || {},
            address = shipping.address || {},
            result = {
                givenName: recipient.givenName,
                familyName: recipient.familyName,
                email: recipient.email,
                phone: recipient.phone,
                streetAddress: address.streetAddress,
                streetAddress2: address.streetAddress2,
                city: address.city,
                postalCode: address.postalCode,
                region: address.region,
                country: address.country
            };

        // Drop empty values so that the PHP side falls back to the existing quote address data.
        Object.keys(result).forEach(function (key) {
            if (result[key] === undefined || result[key] === null || result[key] === '') {
                delete result[key];
            }
        });

        return result;
    }

    /**
     * Mount Klarna's presentation components (icon, header, subheader) if available.
     *
     * @param {Object} klarna
     * @param {Object} config
     * @return {Promise<Boolean>} false when Klarna instructs to hide itself
     */
    async function mountPresentation(klarna, config) {
        var presentation, paymentOption;

        try {
            presentation = await klarna.Payment.presentation({
                amount: config.amount,
                currency: config.currency,
                locale: config.locale,
                intent: 'PAY'
            });
        } catch (e) {
            // Presentation is cosmetic only, the express button can work without it.
            // eslint-disable-next-line no-console
            console.warn('Klarna Express Checkout: presentation unavailable', e);

            return true;
        }

        if (!presentation) {
            return true;
        }

        if (presentation.instruction === 'HIDE_KLARNA') {
            return false;
        }

        paymentOption = presentation.paymentOption;

        if (paymentOption) {
            paymentOption.icon.component({ shape: 'badge' }).mount('#klarna-payment-icon');
            paymentOption.header.component().mount('#klarna-payment-header');
            paymentOption.subheader.component().mount('#klarna-payment-subheader');
        }

        return true;
    }

    return function (config, element) {
        var $element = $(element),
            selectedShippingOptionReference = '';

        /**
         * Bootstraps the Klarna SDK and wires up the express checkout flow.
         */
        (async function initKlarnaExpressCheckout() {
            try {
                var KlarnaSDK = (await import(SDK_URL)).KlarnaSDK;

                var klarna = await KlarnaSDK({
                    clientId: config.clientId,
                    products: ['PAYMENT'],
                    locale: config.locale
                });

                if (!await mountPresentation(klarna, config)) {
                    $element.hide();

                    return;
                }

                // Event handlers must be registered before the button is mounted.
                klarna.Payment.on('shippingaddresschange', async function (paymentRequest, shippingAddress) {
                    var data;

                    try {
                        data = await post(config.estimateShippingUrl, { shippingAddress: shippingAddress || {} });
                    } catch (e) {
                        data = { success: false };
                    }

                    if (!data.success || !data.shippingOptions || !data.shippingOptions.length) {
                        return { rejectionReason: 'ADDRESS_NOT_SUPPORTED' };
                    }

                    selectedShippingOptionReference = data.selectedShippingOptionReference || '';

                    var response = {
                        amount: data.amount,
                        lineItems: data.lineItems,
                        shippingOptions: data.shippingOptions
                    };

                    if (selectedShippingOptionReference) {
                        response.selectedShippingOptionReference = selectedShippingOptionReference;
                    }

                    return response;
                });

                klarna.Payment.on('shippingoptionselect', async function (paymentRequest, shippingOption) {
                    var reference = shippingOption && shippingOption.shippingOptionReference,
                        data;

                    if (!reference) {
                        return { rejectionReason: 'INVALID_OPTION' };
                    }

                    try {
                        data = await post(config.selectShippingOptionUrl, { shippingOptionReference: reference });
                    } catch (e) {
                        data = { success: false };
                    }

                    if (!data.success) {
                        return { rejectionReason: 'INVALID_OPTION' };
                    }

                    selectedShippingOptionReference = reference;

                    return {
                        amount: data.amount,
                        lineItems: data.lineItems
                    };
                });

                klarna.Payment.on('complete', async function (paymentRequest) {
                    var stateContext = paymentRequest && paymentRequest.stateContext || {},
                        shipping = stateContext.shipping || {},
                        shippingOption = shipping.shippingOption || {},
                        data;

                    $('body').trigger('processStart');

                    try {
                        data = await post(config.completeUrl, {
                            shippingAddress: flattenShipping(shipping),
                            shippingOptionReference: shipping.shippingOptionReference
                                || shippingOption.shippingOptionReference
                                || selectedShippingOptionReference,
                            networkSessionToken: stateContext.klarnaNetworkSessionToken || ''
                        });
                    } catch (e) {
                        data = { success: false, message: e.message };
                    }

                    if (data.success && data.stepUpUrl) {
                        window.location.href = data.stepUpUrl;
                    } else if (data.success) {
                        // The backend returns a relative path, always use the absolute success URL.
                        window.location.href = config.successUrl;
                    } else {
                        // eslint-disable-next-line no-console
                        console.error('Klarna Express Checkout:', data.message);
                        window.location.reload();
                    }

                    // Prevent Klarna's automatic redirect, navigation is handled above.
                    return false;
                });

                klarna.Payment.on('abort', async function (paymentRequest) {
                    // eslint-disable-next-line no-console
                    console.info('Klarna Express Checkout aborted:', paymentRequest && paymentRequest.stateReason);
                });

                klarna.Payment.on('error', async function (error) {
                    // eslint-disable-next-line no-console
                    console.error('Klarna payment error:', error && (error.errorMessage || error.message), error);
                });

                klarna.Payment.button({
                    shape: 'default',
                    theme: 'default',
                    locale: config.locale,
                    intents: ['PAY'],
                    initiate: async function () {
                        return {
                            currency: config.currency,
                            amount: config.amount,
                            shippingConfig: {
                                mode: 'EDITABLE'
                            }
                        };
                    }
                }).mount('#klarna-button-container');
            } catch (err) {
                // eslint-disable-next-line no-console
                console.error('Failed to initialize Klarna Express Checkout:', err);
                $element.hide();
            }
        })();
    };
});
