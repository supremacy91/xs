/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [
        'jquery',
        "underscore",
        'Magento_Ui/js/form/form',
        'ko',
        'Magento_Customer/js/model/customer',
        'Magento_Customer/js/model/address-list',
        'Magento_Checkout/js/model/address-converter',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/create-shipping-address',
        'Magento_Checkout/js/action/select-shipping-address',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-address/form-popup-state',
        'Magento_Checkout/js/model/shipping-service',
        'Magento_Checkout/js/action/select-shipping-method',
        'Magento_Checkout/js/model/shipping-rate-registry',
        '../action/set-shipping-information',
        'Magento_Checkout/js/model/step-navigator',
        'Magento_Ui/js/modal/modal',
        'Magento_Checkout/js/model/checkout-data-resolver',
        'Magento_Checkout/js/checkout-data',
        'uiRegistry',
        'mage/translate',
        'Magento_Checkout/js/model/shipping-rate-service'
    ],
    function(
        $,
        _,
        Component,
        ko,
        customer,
        addressList,
        addressConverter,
        quote,
        createShippingAddress,
        selectShippingAddress,
        shippingRatesValidator,
        formPopUpState,
        shippingService,
        selectShippingMethodAction,
        rateRegistry,
        setShippingInformationAction,
        stepNavigator,
        modal,
        checkoutDataResolver,
        checkoutData,
        registry,
        $t
    ) {
        'use strict';

        // $(document).ready(function(){
        var popUp = null;
        return Component.extend({
            defaults: {
                template: 'Parcelpro_Shipment/shipping'
            },
            visible: ko.observable(!quote.isVirtual()),
            errorValidationMessage: ko.observable(false),
            isCustomerLoggedIn: customer.isLoggedIn,
            isFormPopUpVisible: formPopUpState.isVisible,
            isFormInline: addressList().length == 0,
            isNewAddressAdded: ko.observable(false),
            saveInAddressBook: true,
            quoteIsVirtual: quote.isVirtual(),

            initialize: function () {
                var self = this;
                this._super();
                if (!quote.isVirtual()) {
                    stepNavigator.registerStep(
                        'shipping',
                        '',
                        'Shipping',
                        this.visible, _.bind(this.navigate, this),
                        10
                    );
                }
                checkoutDataResolver.resolveShippingAddress();

                var hasNewAddress = addressList.some(function (address) {
                    return address.getType() == 'new-customer-address';
                });

                this.isNewAddressAdded(hasNewAddress);

                this.isFormPopUpVisible.subscribe(function (value) {
                    if (value) {
                        self.getPopUp().openModal();
                    }
                });

                quote.shippingMethod.subscribe(function (value) {
                    self.errorValidationMessage(false);
                });

                registry.async('checkoutProvider')(function (checkoutProvider) {
                    var shippingAddressData = checkoutData.getShippingAddressFromData();
                    if (shippingAddressData) {
                        checkoutProvider.set(
                            'shippingAddress',
                            $.extend({}, checkoutProvider.get('shippingAddress'), shippingAddressData)
                        );
                    }
                    checkoutProvider.on('shippingAddress', function (shippingAddressData) {
                        checkoutData.setShippingAddressFromData(shippingAddressData);
                    });
                });

                window.addEventListener("message", function (event) {
                    if (event.origin === "https://login.parcelpro.nl") {
                        var msg = event.data;
                        if (msg == "closewindow") {
                            popup_close();
                        } else {
                            AddressIsParcelshop(msg);
                            popup_close();
                        }
                    } else {
                        console.log(event.origin + "!== https://login.parcelpro.nl");
                    }
                }, false);

                jQuery(document).on('click', "#s_method_parcelpro_dhl_parcelshop", function () {
                    jQuery('#modal').show();
                    jQuery('#afhaalpunt_frame').attr('src', self.ParcelProKiezerUrl() + '&carrier=DHL');
                });

                jQuery(document).on('click', "#s_method_parcelpro_postnl_pakjegemak", function () {
                    jQuery('#modal').show();
                    jQuery('#afhaalpunt_frame').attr('src', self.ParcelProKiezerUrl() + '&carrier=PostNL');
                });

                return this;
            },

            navigate: function () {
                //load data from server for shipping step
            },

            initElement: function(element) {
                if (element.index === 'shipping-address-fieldset') {
                    shippingRatesValidator.bindChangeHandlers(element.elems(), false);
                }
            },

            getPopUp: function() {
                var self = this;
                if (!popUp) {
                    var buttons = this.popUpForm.options.buttons;
                    this.popUpForm.options.buttons = [
                        {
                            text: buttons.save.text ? buttons.save.text : $t('Save Address'),
                            class: buttons.save.class ? buttons.save.class : 'action primary action-save-address',
                            click: self.saveNewAddress.bind(self)
                        },
                        {
                            text: buttons.cancel.text ? buttons.cancel.text: $t('Cancel'),
                            class: buttons.cancel.class ? buttons.cancel.class : 'action secondary action-hide-popup',
                            click: function() {
                                this.closeModal();
                            }
                        }
                    ];
                    this.popUpForm.options.closed = function() {
                        self.isFormPopUpVisible(false);
                    };
                    popUp = modal(this.popUpForm.options, $(this.popUpForm.element));
                }
                return popUp;
            },

            /** Show address form popup */
            showFormPopUp: function() {
                this.isFormPopUpVisible(true);
            },


            /** Save new shipping address */
            saveNewAddress: function() {
                this.source.set('params.invalid', false);
                this.source.trigger('shippingAddress.data.validate');

                if (!this.source.get('params.invalid')) {
                    var addressData = this.source.get('shippingAddress');
                    addressData.save_in_address_book = this.saveInAddressBook ? 1 : 0;

                    // New address must be selected as a shipping address
                    var newShippingAddress = createShippingAddress(addressData);
                    selectShippingAddress(newShippingAddress);
                    checkoutData.setSelectedShippingAddress(newShippingAddress.getKey());
                    checkoutData.setNewCustomerShippingAddress(addressData);
                    this.getPopUp().closeModal();
                    this.isNewAddressAdded(true);
                }
            },

            /** Shipping Method view **/
            rates: shippingService.getShippingRates(),
            isLoading: shippingService.isLoading,
            isSelected: ko.computed(function () {
                    // Parcel Pro Afhaalpunt
                    if($('#modal').is(':visible')) return false;
                    var postcode = null;
                    var street = null;

                    if(customer.isLoggedIn()) {
                        if (typeof checkoutData.getShippingAddressFromData() !== "undefined"
                            && checkoutData.getShippingAddressFromData() !== null) {
                            postcode = checkoutData.getShippingAddressFromData().postcode;
                            street = checkoutData.getShippingAddressFromData().street;
                        } else {
                            if(customer.customerData.addresses.length >= 1 ){
                                postcode = customer.customerData.addresses[0].postcode;
                                street = customer.customerData.addresses[0].street[0];
                            }
                        }
                    }

                    function ParcelProKiezerUrl() {
                        var url = "https://login.parcelpro.nl/plugin/afhaalpunt/parcelpro-kiezer.html";
                        url += "?";
                        url += "id=" + window.checkoutConfig.config.gebruikerID;
                        url += "&postcode=" + (customer.isLoggedIn() ? postcode : jQuery('input[name=postcode]').val());
                        url += "&adres=" + (customer.isLoggedIn() ? street :  jQuery('input[name^=street]').first().val());
                        url += "&origin=" + window.location.protocol + "//" + window.location.hostname;
                        return url;
                    }
                    if(quote.shippingMethod()) {
                        if (quote.shippingMethod().method_code == "postnl_pakjegemak") {
                            if (!AddressIsParcelshop()) {
                                jQuery('#modal').show();
                                jQuery('#afhaalpunt_frame').attr('src', ParcelProKiezerUrl() + '&carrier=PostNL');
                            }
                        }
                        if (quote.shippingMethod().method_code == "dhl_parcelshop") {
                            if (!AddressIsParcelshop()) {
                                jQuery('#modal').show();
                                jQuery('#afhaalpunt_frame').attr('src', ParcelProKiezerUrl() + '&carrier=DHL');
                            }
                        }
                    }
                    return quote.shippingMethod()
                        ? quote.shippingMethod().carrier_code + '_' + quote.shippingMethod().method_code
                        : null;
                }
            ),

            selectShippingMethod: function(shippingMethod) {
                selectShippingMethodAction(shippingMethod);
                checkoutData.setSelectedShippingRate(shippingMethod.carrier_code + '_' + shippingMethod.method_code);
                return true;
            },

            setShippingInformation: function () {
                if (this.validateShippingInformation()) {
                    setShippingInformationAction().done(
                        function() {
                            stepNavigator.next();
                        }
                    );
                }
            },

            validateShippingInformation: function () {
                if (quote.shippingMethod().method_code == "postnl_pakjegemak" || quote.shippingMethod().method_code == "dhl_parcelshop") {
                    if (jQuery("#shipping_method\\:company").val() === "") {
                        this.errorValidationMessage('Selecteer een afhaallocatie of een andere verzendmethode');
                        return false;
                    }
                }

                var shippingAddress,
                    addressData,
                    loginFormSelector = 'form[data-role=email-with-possible-login]',
                    emailValidationResult = customer.isLoggedIn();

                if (!quote.shippingMethod()) {
                    this.errorValidationMessage('Please specify a shipping method');
                    return false;
                }

                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }

                if (!emailValidationResult) {
                    $(loginFormSelector + ' input[name=username]').focus();
                }

                if (this.isFormInline) {
                    this.source.set('params.invalid', false);
                    this.source.trigger('shippingAddress.data.validate');
                    if (this.source.get('shippingAddress.custom_attributes')) {
                        this.source.trigger('shippingAddress.custom_attributes.data.validate');
                    };
                    if (this.source.get('params.invalid')
                        || !quote.shippingMethod().method_code
                        || !quote.shippingMethod().carrier_code
                        || !emailValidationResult
                    ) {
                        return false;
                    }
                    shippingAddress = quote.shippingAddress();
                    addressData = addressConverter.formAddressDataToQuoteAddress(
                        this.source.get('shippingAddress')
                    );

                    //Copy form data to quote shipping address object
                    for (var field in addressData) {
                        if (addressData.hasOwnProperty(field)
                            && shippingAddress.hasOwnProperty(field)
                            && typeof addressData[field] != 'function'
                        ) {
                            shippingAddress[field] = addressData[field];
                        }
                    }

                    if (customer.isLoggedIn() && (quote.shippingMethod().method_code != "postnl_pakjegemak" && quote.shippingMethod().method_code != "dhl_parcelshop")) {
                        shippingAddress.save_in_address_book = 1;
                    }else{
                        shippingAddress.save_in_address_book = 0;
                        addressData.save_in_address_book = 0;
                    }

                    selectShippingAddress(shippingAddress);
                }
                return true;
            },
            ParcelProKiezerUrl: function () {
                var postcode = null;
                var street = null;

                if(window.isCustomerLoggedIn) {
                    if (typeof checkoutData.getShippingAddressFromData() !== "undefined"
                        && checkoutData.getShippingAddressFromData() !== null
                        && checkoutData.getSelectedShippingAddress() == 'new-customer-address'
                    ) {
                        postcode = checkoutData.getShippingAddressFromData().postcode;
                        street = checkoutData.getShippingAddressFromData().street;
                    } else {
                        if(checkoutData.getSelectedShippingAddress() != null){
                            var parts = checkoutData.getSelectedShippingAddress().split('customer-address');
                            postcode = window.customerData.addresses[ ( parts[1] -1 ) ].postcode;
                            street = window.customerData.addresses[ ( parts[1] -1 ) ].street[0];
                        }else{
                            if(window.customerData.addresses.length >=1 ){
                                postcode = window.customerData.addresses[0].postcode;
                                street = window.customerData.addresses[0].street[0];
                            }else{
                                postcode = (jQuery('input[name=postcode]').val() != '' ? jQuery('input[name=postcode]').val() : '');
                            }
                        }
                    }
                }else{
                    postcode = jQuery('input[name=postcode]').val();
                    street = jQuery('input[name^=street]').first().val();
                }

                var url = "https://login.parcelpro.nl/plugin/afhaalpunt/parcelpro-kiezer.html";
                url += "?";
                url += "id=" + window.checkoutConfig.config.gebruikerID;
                url += "&postcode=" + postcode;
                url += "&adres=" + street
                url += "&origin=" + window.location.protocol + "//" + window.location.hostname;
                return url;
            }
        });
        // });
    }
);
