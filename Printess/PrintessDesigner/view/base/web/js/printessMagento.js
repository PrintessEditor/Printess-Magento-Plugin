class PrintessMagentoIntegration {
    constructor(shopSettings, product, cartItem) {
        this.PRODUCT_FORM_SELECTOR = "#product_addtocart_form";
        this.formatPriceCallback = null;
        this.shopSettings = shopSettings;
        this.product = product;
        this.cartItem = cartItem;
        if (this.product && this.product.variants) {
            this.product.variants.forEach((variant) => {
                if (variant.formFields && typeof variant.formFields === "string") {
                    variant.formFields = JSON.parse(variant.formFields);
                }
            });
        }
    }
    getGlobalConfig() {
        return (window && window["printessGlobalConfig"] ? window["printessGlobalConfig"] : {});
    }
    recordForEach(record, callback) {
        if (record && typeof callback === "function") {
            for (const key in record) {
                if (record.hasOwnProperty(key)) {
                    if (callback(key, record[key]) === false) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
    filterRecord(record, callback) {
        const ret = {};
        if (record && typeof callback === "function") {
            for (const key in record) {
                if (record.hasOwnProperty(key)) {
                    if (callback(key, record[key]) === true) {
                        ret[key] = record[key];
                    }
                }
            }
        }
        return ret;
    }
    mapRecord(record, callback) {
        const ret = [];
        if (record && typeof callback === "function") {
            this.recordForEach(record, (key, value) => {
                ret.push(callback(key, value));
            });
        }
        return ret;
    }
    parseAttributeId(attributeReference) {
        let ret = -1;
        if (attributeReference.indexOf("super_attribute[") === 0 && attributeReference[attributeReference.length - 1] === "]") {
            ret = parseInt(attributeReference.substring(16, attributeReference.length - 1));
        }
        return ret;
    }
    getVariantOptions() {
        const options = {};
        if (this.product && this.product.variants) {
            this.product.variants.forEach((variant) => {
                if (variant.options) {
                    variant.options.forEach((option) => {
                        if (typeof options[option.optionId] === "undefined") {
                            options[option.optionId] = {
                                id: option.optionId,
                                name: option.label,
                                values: {}
                            };
                        }
                        if (typeof options[option.optionId].values[option.valueIndex] === "undefined") {
                            options[option.optionId].values[option.valueIndex] = option.optionTitle;
                        }
                    });
                }
            });
        }
        return options;
    }
    getCurrentProductOptions(retType = "array") {
        let ret = null;
        const ignoreValues = {
            "selected_configurable_option": true,
            "related_product": true,
            "item": true,
            "form_key": true,
            "saveToken": true,
            "thumbnailUrl": true,
            "printessSaveToken": true,
            "printessThumbnailUrl": true,
            "printessItemOptions": true,
            "product": true
        };
        if (retType === "nameLookup") {
            ret = {};
        }
        else if (retType === "idLookup") {
            ret = {};
        }
        else {
            ret = [];
        }
        const form = document.querySelector(this.PRODUCT_FORM_SELECTOR);
        if (!form) {
            console.error("Form not found: " + this.PRODUCT_FORM_SELECTOR);
            return ret;
        }
        const formData = new FormData(form);
        const options = this.getVariantOptions();
        for (const pair of formData.entries()) {
            if (pair[0]) {
                if (!ignoreValues[pair[0]]) {
                    let option = {
                        name: pair[0],
                        value: pair[1].toString()
                    };
                    if (pair[0].indexOf("super_attribute") === 0) {
                        option.id = this.parseAttributeId(pair[0]);
                        option.valueId = parseInt(pair[1]);
                        if (typeof options[option.id] !== "undefined") {
                            option.name = options[option.id].name;
                            if (typeof options[option.id].values[option.valueId] !== "undefined") {
                                option.value = options[option.id].values[option.valueId];
                            }
                        }
                    }
                    if (retType === "nameLookup") {
                        ret[option.name] = option;
                    }
                    else if (retType === "idLookup") {
                        ret[option.id] = option;
                    }
                    else {
                        ret.push(option);
                    }
                }
            }
        }
        return ret;
    }
    getVariantByProductOptions(options) {
        let variants = this.product.variants;
        if (!variants || variants.length === 0) {
            return null;
        }
        const variantOptions = this.getVariantOptions();
        const filteredOptions = {};
        this.recordForEach(options, (key, value) => {
            for (const optionId in variantOptions) {
                if (variantOptions.hasOwnProperty(optionId)) {
                    if (variantOptions[optionId].name === key) {
                        filteredOptions[key] = value;
                    }
                }
            }
        });
        //Try variant form field mappings first
        this.recordForEach(filteredOptions, (key, value) => {
            variants = variants.filter((variant) => {
                let ret = false;
                (variant.formFields || []).forEach((ff) => {
                    if (ff.name === key && ff.value === value) {
                        ret = true;
                    }
                });
                return ret;
            });
        });
        if (variants.length > 0) {
            return variants[0];
        }
        //Not found via form field mappings, try via variant options
        variants = this.product.variants;
        this.recordForEach(filteredOptions, (key, value) => {
            variants = variants.filter((variant) => {
                if (variant.options) {
                    const option = variant.options.filter(x => x.label === key && (x.optionTitle === value || x.defaultTitle === value));
                    return option && option.length > 0;
                }
                return false;
            });
        });
        if (variants.length > 0) {
            return variants[0];
        }
        return null;
    }
    addItemsToBasket(context, saveToken, thumbnailUrl, params) {
        const saveTokenEdit = document.getElementById("printess-savetoken-field");
        const thumbnailUrlEdit = document.getElementById("printess-thumbnail_url-field");
        const itemOptionField = document.getElementById("printess-item_options-field");
        let form = null;
        if (saveTokenEdit) {
            saveTokenEdit.value = saveToken;
            form = saveTokenEdit.form;
        }
        if (thumbnailUrlEdit) {
            thumbnailUrlEdit.value = thumbnailUrl;
            console.log("thumbnailUrl: " + thumbnailUrl);
            if (!form) {
                form = thumbnailUrlEdit.form;
            }
        }
        if (itemOptionField) {
            let currentFormFields = {};
            if (this.cartItem) {
                currentFormFields = this.cartItem.options;
            }
            else {
                this.getCurrentProductOptions("array").forEach((x) => {
                    currentFormFields[x.name] = x.value;
                });
            }
            const globalConfig = this.getGlobalConfig();
            if (globalConfig && globalConfig.formFields) {
                for (const property in globalConfig.formFields) {
                    if (globalConfig.formFields.hasOwnProperty(property)) {
                        currentFormFields[property] = globalConfig.formFields[property];
                    }
                }
            }
            if (params && typeof params.pageCount !== "undefined") {
                currentFormFields["pageCount"] = params.pageCount.toString();
                currentFormFields["minPages"] = params.minPages.toString();
                currentFormFields["additionalPages"] = params.additionalPages.toString();
            }
            itemOptionField.value = JSON.stringify(currentFormFields);
        }
        const globalConfig = this.getGlobalConfig();
        if (globalConfig && typeof globalConfig.onAddToBasket === "function") {
            try {
                globalConfig.onAddToBasket(saveToken, thumbnailUrl);
            }
            catch (e) {
                console.error(e);
            }
        }
        const button = document.getElementById("product-addtocart-button");
        if (button) {
            button.click();
            window.require([
                'Magento_Customer/js/customer-data'
            ], function (customerData) {
                setTimeout(function () {
                    var sections = ['cart'];
                    customerData.invalidate(sections);
                    customerData.reload(sections, true);
                }, 1000);
            });
        }
        else if (form) {
            form.submit();
        }
    }
    saveBasketItem(context, saveToken, thumbnailUrl, params) {
        let productEntityId = this.product.entityId || -1;
        let quantity = this.product.quantity || 1;
        if (window.checkoutConfig && window.checkoutConfig.quoteItemData) {
            //get the json for the basket item
            const basketItems = window.checkoutConfig.quoteItemData;
            let basketItem = null;
            if (basketItems && basketItems.length > 0) {
                basketItem = basketItems.find((item) => {
                    return item.item_id == this.cartItem.basketItemId;
                });
            }
            if (basketItem) {
                productEntityId = basketItem.product.entity_id;
                quantity = basketItem.qty;
            }
        }
        const formKey = ('; ' + document.cookie).split(`; form_key=`).pop().split(';')[0];
        let urlParams = "product=" + encodeURIComponent(productEntityId);
        urlParams += "&item=" + encodeURIComponent(productEntityId);
        urlParams += "&selected_configurable_option=";
        urlParams += "&related_product=";
        urlParams += "&form_key=" + encodeURIComponent(formKey);
        if (this.cartItem.options) {
            for (var key in this.cartItem.options) {
                if (this.cartItem.options.hasOwnProperty(key)) {
                    urlParams += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(this.cartItem.options[key]);
                }
            }
        }
        let currentFormFields = {};
        if (this.cartItem) {
            currentFormFields = this.cartItem.options;
        }
        else {
            this.getCurrentProductOptions("array").forEach((x) => {
                currentFormFields[x.name] = x.value;
            });
        }
        if (params && typeof params.pageCount !== "undefined") {
            currentFormFields["pageCount"] = params.pageCount.toString();
            currentFormFields["minPages"] = params.minPages.toString();
            currentFormFields["additionalPages"] = params.additionalPages.toString();
        }
        const selectedVariant = this.getVariantByProductOptions(currentFormFields);
        if (selectedVariant) {
            urlParams += "&sku=" + encodeURIComponent(selectedVariant.sku);
            if (selectedVariant && selectedVariant.options) {
                selectedVariant.options.forEach((x) => {
                    urlParams += "&" + encodeURIComponent("super_attribute[" + x.optionId + "]") + "=" + encodeURIComponent(x.valueIndex);
                    urlParams += "&" + encodeURIComponent("options[" + x.optionId + "]") + "=" + encodeURIComponent(x.valueIndex);
                });
            }
        }
        urlParams += "&qty=" + encodeURIComponent(quantity);
        urlParams += "&printess_save_token=" + encodeURIComponent(saveToken);
        urlParams += "&printess_thumbnail_url=" + encodeURIComponent(thumbnailUrl);
        urlParams += "&printessItemOptions=" + encodeURIComponent(JSON.stringify(currentFormFields));
        const globalConfig = this.getGlobalConfig();
        if (globalConfig && typeof globalConfig.onAddToBasket === "function") {
            try {
                globalConfig.onAddToBasket(saveToken, thumbnailUrl);
            }
            catch (e) {
                console.error(e);
            }
        }
        this.replaceBasketItem(urlParams, formKey).then(() => {
            window.require([
                'Magento_Customer/js/customer-data'
            ], function (customerData) {
                if (customerData) {
                    var sections = ['cart'];
                    customerData.invalidate(sections);
                    customerData.reload(sections, true);
                }
            });
            window.location = window.location;
        });
    }
    async replaceBasketItem(addUrlParams, formKey) {
        console.log("[replaceBasketItem] addToCartLink:", this.cartItem.addToCartLink);
        let response = await fetch(this.cartItem.addToCartLink, {
            method: "POST",
            mode: "cors",
            cache: "no-cache",
            credentials: "same-origin",
            body: addUrlParams,
            redirect: "manual",
            referrerPolicy: "no-referrer",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            }
        });
        if (!response.ok && response.type !== "opaqueredirect") {
            throw "Unable to create new Basket item: [" + response.status + "] " + response.statusText;
        }
        let deleteUrlParams = "form_key=" + encodeURIComponent(formKey);
        deleteUrlParams += "&uenc=" + encodeURIComponent(this.cartItem.deleteItemJson.data.uenc);
        deleteUrlParams += "&id=" + encodeURIComponent(this.cartItem.deleteItemJson.data.id);
        response = await fetch(this.cartItem.deleteItemJson.action, {
            method: "POST",
            body: deleteUrlParams,
            mode: "cors",
            cache: "no-cache",
            credentials: "same-origin",
            redirect: "manual",
            referrerPolicy: "no-referrer",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            }
        });
        if (!response.ok && response.type !== "opaqueredirect") {
            throw "Unable to delete Basket item: [" + response.status + "] " + response.statusText;
        }
    }
    static async getBasketItemAndProductInfo(context) {
        const ret = {
            settings: {},
            product: {},
            cartItem: {},
            saveToken: "",
            legalText: ""
        };
        const response = await fetch("/rest/V1/printess/getProductInfo", {
            method: "POST",
            mode: "cors",
            cache: "no-cache",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json"
            },
            redirect: "manual",
            referrerPolicy: "no-referrer",
            body: JSON.stringify({ "id": context.product_id, "sku": context.product_sku, "itemId": context.item_id })
        });
        if (response.status > 200) {
            alert("Unable to load product information");
            console.error(`Unable to load product information [${response.status}] ${response.statusText}: ${await response.text()}`);
            return null;
        }
        let info = await response.json();
        if (typeof info === "string") {
            info = JSON.parse(info);
        }
        const selectedProductOptions = [];
        if (context.options) {
            for (var prop in context.options) {
                if (context.options.hasOwnProperty(prop) && context.options[prop].hasOwnProperty("option_id") && context.options[prop].hasOwnProperty("option_value")) {
                    selectedProductOptions.push(context.options[prop]);
                }
            }
        }
        const getOptionValue = (optionId, optionValue) => {
            if (info.options && info.options[optionId]) {
                for (const key in info.options[optionId]) {
                    if (info.options[optionId][key] === optionValue) {
                        return key;
                    }
                }
            }
            return optionValue;
        };
        const options = {};
        let formFields = {};
        if (context.options) {
            for (var key in context.options) {
                if (key != "printess_save_token" && key != "printess_thumbnail_url") {
                    if (context.options.hasOwnProperty(key)) {
                        if (typeof context.options[key].value !== "undefined") {
                            if (typeof context.options[key]["option_id"] !== "undefined") {
                                options["options[" + context.options[key]["option_id"] + "]"] = getOptionValue(context.options[key]["option_id"], context.options[key].value);
                            }
                            else {
                                options[key] = getOptionValue(context.options[key]["option_id"], context.options[key].value);
                            }
                        }
                        else {
                            if (typeof context.options[key]["option_id"] !== "undefined") {
                                options["options[" + context.options[key]["option_id"] + "]"] = getOptionValue(context.options[key]["option_id"], context.options[key].value);
                            }
                            else {
                                options[key] = context.options[key];
                            }
                        }
                    }
                }
            }
        }
        ret.settings = {
            shopToken: info.editorSettings.shopToken,
            editorUrl: info.editorSettings.editorUrl,
            editorVersion: info.editorSettings.editorVersion,
            hidePricesInEditor: info.editorSettings.hidePriceInEditor === true,
            priceFormat: info.priceFormat,
            uiSettings: {
                showStartupAnimation: info.editorSettings.showStartupAnimation,
                startupLogoUrl: info.editorSettings.customLogoUrl,
                theme: null,
                startupBackgroundColor: "#000000",
                uiVersion: info.editorSettings.uiVersion || ""
            }
        };
        ret.product = {
            name: info.productName,
            price: info.productPrice,
            quantity: context.qty || 1,
            entityId: info.entityId,
            variants: info.variants,
            formFields: info.formFields ? JSON.parse(info.formFields) : {}
        };
        ret.cartItem = {
            basketItemId: context.item_id,
            addToCartLink: info.addToCartLink,
            deleteItemJson: typeof info.deleteJson !== "string" ? info.deleteJson : JSON.parse(info.deleteJson),
            options: options
        };
        ret.legalText = info.legalText;
        ret.saveToken = ret.cartItem.options["printess_save_token"] ? ret.cartItem.options["printess_save_token"].value : "";
        return ret;
    }
    createShopContext(options) {
        if (!options.templateName) {
            console.error("No template name provided");
        }
        const that = this;
        const context = {
            onSave: null,
            templateNameOrSaveToken: options.templateName,
            stickers: [],
            legalText: options.legalText || "",
            legalTextUrl: options.legalTextUrl || "",
            snippetPrices: [],
            chargeEachStickerUsage: false,
            hidePricesInEditor: typeof this.shopSettings.hidePricesInEditor !== "undefined" && this.shopSettings.hidePricesInEditor === true,
            getMergeTemplates: function () { return []; },
            getProductName: function () { return that.product.name; },
            getPriceInfo: function () { return {}; },
            formatMoney: function (price) {
                if (typeof that.formatPriceCallback !== "function" && typeof window["require"] === "function") {
                    const quote = window["require"]("Magento_Checkout/js/model/quote");
                    const priceUtils = window["require"]('Magento_Catalog/js/price-utils');
                    if (quote && priceUtils) {
                        that.formatPriceCallback = function (price) {
                            return priceUtils.formatPrice(price, quote.getPriceFormat());
                        };
                    }
                }
                if (typeof that.formatPriceCallback === "function") {
                    return that.formatPriceCallback(price);
                }
                else {
                    return parseFloat("" + price).toFixed(2);
                }
            },
            getCurrentFormFieldValues: function () {
                let ret = {};
                if (that.cartItem) {
                    ret = that.cartItem.options;
                }
                else {
                    that.getCurrentProductOptions("array").forEach((x) => {
                        ret[x.name] = x.value;
                    });
                }
                const selectedVariant = that.getVariantByProductOptions(ret);
                if (selectedVariant && selectedVariant.formFields) {
                    selectedVariant.formFields.forEach((x) => {
                        ret[x.name] = x.value;
                    });
                }
                const globalConfig = that.getGlobalConfig();
                if (globalConfig && globalConfig.formFields) {
                    const formFields = typeof globalConfig.formFields === "function" ? globalConfig.formFields() : globalConfig.formFields;
                    for (const property in formFields) {
                        if (formFields.hasOwnProperty(property)) {
                            ret[property] = formFields[property];
                        }
                    }
                }
                return ret;
            },
            getPriceForFormFields: function (formFields) {
                let productOptions = {};
                if (that.cartItem) {
                    productOptions = that.cartItem.options;
                }
                else {
                    that.getCurrentProductOptions("array").forEach((x) => {
                        productOptions[x.name] = x.value;
                    });
                }
                const selectedVariant = that.getVariantByProductOptions(productOptions);
                if (selectedVariant) {
                    return selectedVariant.price;
                }
                return that.product.price;
            },
            onFormFieldChanged: (formField, value, formFieldLabel, valueLabel) => {
                const availableOptions = that.getVariantOptions();
                let selectedOptionName = "";
                let selectedOptionId = 0;
                let selectedValueName = "";
                let selectedValueId = 0;
                for (const optionId in availableOptions) {
                    if (availableOptions.hasOwnProperty(optionId)) {
                        const option = availableOptions[optionId];
                        if (option.name === formField || option.name === formFieldLabel) {
                            selectedOptionName = option.name;
                            selectedOptionId = option.id;
                            for (const valueId in option.values) {
                                if (option.values.hasOwnProperty(valueId)) {
                                    if (option.values[valueId] === value || option.values[valueId] === valueLabel) {
                                        selectedValueName = option.values[valueId];
                                        selectedValueId = parseInt(valueId);
                                        break;
                                    }
                                }
                            }
                            if (selectedValueName) {
                                break;
                            }
                        }
                    }
                }
                if (selectedValueName) {
                    //Checkboxen
                    const swatchAttribute = document.querySelector(".swatch-attribute[data-attribute-id='" + selectedOptionId + "']");
                    if (swatchAttribute) {
                        swatchAttribute.setAttribute("data-option-selected", selectedValueId.toString());
                        swatchAttribute.querySelectorAll(".swatch-option").forEach((x) => {
                            if (x.getAttribute("data-option-id") === selectedValueId.toString()) {
                                x.classList.add("selected");
                            }
                            else {
                                x.classList.remove("selected");
                            }
                        });
                        //in case this is a select option, get the select input
                        const selectElement = swatchAttribute.querySelector("select.swatch-select");
                        if (selectElement) {
                            selectElement.value = selectedValueId.toString();
                            for (let index = 0; index < selectElement.options.length; ++index) {
                                const currentOption = selectElement.options[index];
                                currentOption.selected = currentOption.value === selectedValueId.toString();
                            }
                        }
                    }
                    //Set the input value in case of check box
                    const checkboxInput = document.querySelector("[name='super_attribute\\[" + selectedOptionId.toString() + "\\]']");
                    if (checkboxInput) {
                        checkboxInput.value = selectedValueId.toString();
                    }
                    if (that.cartItem) {
                        if (!that.cartItem.options) {
                            that.cartItem.options = {};
                        }
                        that.cartItem.options[selectedOptionName] = selectedValueName;
                    }
                }
            },
            onAddToBasket: function (saveToken, thumbnailUrl, params) {
                if (!that.cartItem) {
                    that.addItemsToBasket.call(that, context, saveToken, thumbnailUrl, params);
                }
                else {
                    that.saveBasketItem.call(that, context, saveToken, thumbnailUrl, params);
                }
            },
            getFormFieldMappings() {
                if (that.product.formFields) {
                    return that.product.formFields;
                }
                return {};
            }
        };
        return context;
    }
    show(options) {
        if (!this.formatPriceCallback && typeof window["require"] === "function") {
            window["require"](['Magento_Catalog/js/price-utils'], (priceUtils) => {
                if (priceUtils) {
                    this.formatPriceCallback = function (price) {
                        return priceUtils.formatPrice(price, this.shopSettings.priceFormat);
                    };
                }
            });
        }
        const globalConfig = this.getGlobalConfig();
        if (globalConfig && globalConfig.attachParams) {
            for (const property in globalConfig.attachParams) {
                if (globalConfig.attachParams.hasOwnProperty(property)) {
                    if (!this.shopSettings.attachParams) {
                        this.shopSettings.attachParams = {};
                    }
                    this.shopSettings.attachParams[property] = globalConfig.attachParams[property];
                }
            }
        }
        if (typeof window["initPrintessEditor"] === "function") {
            const editor = window["initPrintessEditor"](this.shopSettings);
            editor.show(this.createShopContext(options));
        }
    }
    static async createFromBasketItem(basketItem) {
        const options = await PrintessMagentoIntegration.getBasketItemAndProductInfo(basketItem);
        const cartItem = {};
        const editor = new PrintessMagentoIntegration(options.settings, options.product, options.cartItem);
        return {
            legalText: options.legalText,
            saveToken: options.saveToken,
            editor: editor
        };
    }
    static async editPrintessBasketItem(itemData) {
        const basketItem = {
            "product_sku": itemData["product_sku"],
            "product_id": itemData["product_id"].toString(),
            "options": {},
            "item_id": itemData["item_id"].toString(),
            "qty": itemData["qty"]
        };
        let saveToken = "";
        if (itemData["options"]) {
            if (itemData["options"]["printess_item_options"]) {
                basketItem["options"] = JSON.parse(itemData["options"]["printess_item_options"].value);
            }
            if (itemData["options"]["printess_save_token"]) {
                saveToken = itemData["options"]["printess_save_token"].value;
            }
        }
        if (saveToken) {
            const result = await PrintessMagentoIntegration.createFromBasketItem(basketItem);
            if (result) {
                result.editor.show({
                    templateName: saveToken,
                    legalText: result.legalText
                });
            }
        }
    }
}