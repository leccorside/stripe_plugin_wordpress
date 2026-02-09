/**
 * Formulário de doação Stripe – validação frontend, integração Stripe.js (Elements), estados loading/sucesso/erro.
 * JavaScript puro (sem jQuery). Compatível com qualquer tema WordPress.
 *
 * @package Donation_Stripe
 */

(function () {
    'use strict';

    const CONFIG = typeof donationStripeConfig !== 'undefined' ? donationStripeConfig : {};
    const PUBLISHABLE_KEY = CONFIG.publishableKey || '';
    const AJAX_URL = CONFIG.ajaxUrl || '';
    const NONCE = CONFIG.nonce || '';
    const I18N = CONFIG.i18n || {};

    let stripe = null;
    let cardNumberElement = null;
    let cardExpiryElement = null;
    let cardCvcElement = null;
    let elements = null;

    /**
     * Inicialização quando o DOM estiver pronto e Stripe disponível.
     */
    function init() {
        const wrapper = document.getElementById('donation-stripe-form-wrapper');
        if (!wrapper) return;

        const form = document.getElementById('donation-stripe-form');
        if (!form) return;

        initAmountButtons(form);
        initMasks(form);
        initFrequencyButtons(form);
        initPaymentMethodButtons(form);
        initBoletoCountrySync();

        initStripeAndMountCard();
        form.addEventListener('submit', handleSubmit);
    }

    /**
     * Inicializa Stripe e monta os Elements do cartão quando o Stripe.js estiver disponível
     * (o script do Stripe pode carregar de forma assíncrona e não estar pronto no primeiro init).
     */
    function initStripeAndMountCard() {
        if (!PUBLISHABLE_KEY) return;
        if (typeof Stripe !== 'undefined') {
            stripe = Stripe(PUBLISHABLE_KEY);
            tryMountCardElements();
            return;
        }
        var attempts = 0;
        var maxAttempts = 100;
        var interval = setInterval(function () {
            attempts++;
            if (typeof Stripe !== 'undefined') {
                clearInterval(interval);
                stripe = Stripe(PUBLISHABLE_KEY);
                tryMountCardElements();
                return;
            }
            if (attempts >= maxAttempts) clearInterval(interval);
        }, 50);
        window.addEventListener('load', function onLoad() {
            window.removeEventListener('load', onLoad);
            if (stripe) return;
            if (typeof Stripe !== 'undefined') {
                stripe = Stripe(PUBLISHABLE_KEY);
                tryMountCardElements();
            }
        });
    }

    function tryMountCardElements() {
        if (!stripe || cardNumberElement) return;
        var cardFields = document.getElementById('donation-stripe-card-fields');
        if (cardFields && !cardFields.classList.contains('donation-stripe-hidden')) {
            mountCardElements();
        }
    }

    /**
     * Botões de valor: $25, $35, $50, Other.
     */
    function initAmountButtons(form) {
        const buttons = form.querySelectorAll('.donation-stripe-amount-btn');
        const customWrap = form.querySelector('.donation-stripe-amount-custom-wrap');
        const customInput = form.querySelector('.donation-stripe-amount-custom');
        const amountTypeInput = form.querySelector('#donation-stripe-amount-type');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const amount = this.getAttribute('data-amount');
                buttons.forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-pressed', 'true');
                amountTypeInput.value = amount;
                if (amount === 'other') {
                    if (customWrap) {
                        customWrap.classList.remove('donation-stripe-hidden');
                        if (customInput) customInput.focus();
                    }
                } else {
                    if (customWrap) customWrap.classList.add('donation-stripe-hidden');
                    if (customInput) customInput.value = '';
                }
            });
        });

        if (customInput) {
            customInput.addEventListener('input', function () {
                amountTypeInput.value = 'other';
                buttons.forEach(function (b) {
                    if (b.getAttribute('data-amount') === 'other') {
                        b.classList.add('active');
                        b.setAttribute('aria-pressed', 'true');
                    } else {
                        b.classList.remove('active');
                        b.setAttribute('aria-pressed', 'false');
                    }
                });
            });
        }
    }

    /**
     * Máscaras: valor em dólar (apenas números, formato moeda) e CPF/CNPJ.
     */
    function initMasks(form) {
        const amountInput = form.querySelector('.donation-stripe-amount-custom');
        const cpfCnpjInput = form.querySelector('#donation-stripe-cpf-cnpj');
        if (amountInput) maskDollarInput(amountInput);
        if (cpfCnpjInput) maskCpfCnpjInput(cpfCnpjInput);
    }

    /**
     * Máscara valor em dólar: preenchimento da esquerda para a direita.
     * O input contém só a parte inteira (ex.: "20" ou "1.234"); o ",00" fica em um span ao lado.
     * Assim "20" não vira "2.000,00" e o backspace apaga de verdade.
     */
    function maskDollarInput(input) {
        input.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '');
            if (digits.length === 0) {
                this.value = '';
                return;
            }
            this.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        });
        input.addEventListener('blur', function () {
            if (this.value.replace(/\D/g, '').length === 0) {
                this.value = '';
            }
        });
    }

    /**
     * Converte valor do input (só parte inteira, ex. "1.234") para número para envio (1234.00).
     */
    function parseDollarMasked(value) {
        if (!value || !value.trim()) return '';
        var digits = value.replace(/\D/g, '');
        if (digits.length === 0) return '';
        return digits + '.00';
    }

    /**
     * Máscara CPF (000.000.000-00) ou CNPJ (00.000.000/0000-00): aceita apenas números.
     */
    function maskCpfCnpjInput(input) {
        input.addEventListener('input', function () {
            var d = this.value.replace(/\D/g, '');
            if (d.length <= 11) {
                this.value = formatCpf(d);
            } else {
                d = d.slice(0, 14);
                this.value = formatCnpj(d);
            }
        });
    }

    function formatCpf(d) {
        if (d.length <= 3) return d;
        if (d.length <= 6) return d.slice(0, 3) + '.' + d.slice(3);
        if (d.length <= 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
        return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9, 11);
    }

    function formatCnpj(d) {
        if (d.length <= 2) return d;
        if (d.length <= 5) return d.slice(0, 2) + '.' + d.slice(2);
        if (d.length <= 8) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5);
        if (d.length <= 12) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12);
        return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12) + '-' + d.slice(12, 14);
    }

    /**
     * Botões de frequência: Uma única vez, Monthly, Annual.
     */
    function initFrequencyButtons(form) {
        const buttons = form.querySelectorAll('.donation-stripe-frequency-btn');
        const frequencyInput = form.querySelector('#donation-stripe-frequency');

        buttons.forEach(function (btn) {
            if (btn.disabled) return;
            btn.addEventListener('click', function () {
                const freq = this.getAttribute('data-frequency');
                buttons.forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-pressed', 'true');
                frequencyInput.value = freq;
            });
        });
    }

    /**
     * Botões de método de pagamento: Cartão / Boleto.
     */
    function initPaymentMethodButtons(form) {
        const buttons = form.querySelectorAll('.donation-stripe-payment-method-btn');
        const methodInput = form.querySelector('#donation-stripe-payment-method');
        const cardFields = document.getElementById('donation-stripe-card-fields');
        const boletoFields = document.getElementById('donation-stripe-boleto-fields');
        const legalBlock = document.getElementById('donation-stripe-legal');

        function showCard() {
            if (cardFields) cardFields.classList.remove('donation-stripe-hidden');
            if (boletoFields) boletoFields.classList.add('donation-stripe-hidden');
            if (legalBlock) legalBlock.classList.remove('donation-stripe-hidden');
            if (!stripe && typeof Stripe !== 'undefined' && PUBLISHABLE_KEY) stripe = Stripe(PUBLISHABLE_KEY);
            if (stripe && !cardNumberElement) mountCardElements();
        }

        function showBoleto() {
            if (cardFields) cardFields.classList.add('donation-stripe-hidden');
            if (boletoFields) boletoFields.classList.remove('donation-stripe-hidden');
            if (legalBlock) legalBlock.classList.add('donation-stripe-hidden');
        }

        buttons.forEach(function (btn) {
            if (btn.disabled) return;
            btn.addEventListener('click', function () {
                const method = this.getAttribute('data-method');
                buttons.forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-pressed', 'true');
                methodInput.value = method;
                if (method === 'card') showCard();
                else showBoleto();
            });
        });
    }

    /**
     * Sincroniza país do boleto com país do cartão (opcional).
     */
    function initBoletoCountrySync() {
        const countryCard = document.getElementById('donation-stripe-country');
        const countryBoleto = document.getElementById('donation-stripe-country-boleto');
        if (countryCard && countryBoleto) {
            countryCard.addEventListener('change', function () {
                countryBoleto.value = this.value;
            });
        }
    }

    /**
     * Monta Stripe Elements (CardNumber, CardExpiry, CardCvc) para coleta segura de cartão.
     * Monta apenas uma vez (evita duplicar no DOM).
     */
    function mountCardElements() {
        if (!stripe || cardNumberElement) return;
        const cardEl = document.getElementById('donation-stripe-card-element');
        const expiryEl = document.getElementById('donation-stripe-expiry-element');
        const cvcEl = document.getElementById('donation-stripe-cvc-element');
        if (!cardEl || !expiryEl || !cvcEl) return;

        elements = stripe.elements();
        const style = {
            base: {
                fontSize: '16px',
                color: '#32325d',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                '::placeholder': { color: '#aab7c4' }
            },
            invalid: {
                color: '#9e2146'
            }
        };

        cardNumberElement = elements.create('cardNumber', { style: style });
        cardExpiryElement = elements.create('cardExpiry', { style: style });
        cardCvcElement = elements.create('cardCvc', { style: style });

        cardNumberElement.mount(cardEl);
        cardExpiryElement.mount(expiryEl);
        cardCvcElement.mount(cvcEl);
    }

    /**
     * Validação frontend.
     * @returns {string[]} Lista de mensagens de erro.
     */
    function validateForm(form) {
        const errors = [];
        const paymentMethod = form.querySelector('#donation-stripe-payment-method').value;
        const amountType = form.querySelector('#donation-stripe-amount-type').value;
        const amountCustom = (form.querySelector('.donation-stripe-amount-custom') || {}).value || '';
        const email = (form.querySelector('#donation-stripe-email') || {}).value || '';
        const cardholderName = (form.querySelector('#donation-stripe-cardholder-name') || {}).value || '';

        if (!email.trim()) {
            errors.push(I18N.required || 'Campo obrigatório.');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push(I18N.invalidEmail || 'E-mail inválido.');
        }
        if (!cardholderName.trim()) {
            errors.push(I18N.required || 'Campo obrigatório.');
        }
        if (amountType === 'other') {
            const num = parseFloat(parseDollarMasked(amountCustom));
            if (isNaN(num) || num <= 0) {
                errors.push(I18N.invalidAmount || 'Informe um valor válido.');
            }
        }

        if (paymentMethod === 'boleto') {
            const cpf = (form.querySelector('#donation-stripe-cpf-cnpj') || {}).value || '';
            const line1 = (form.querySelector('#donation-stripe-address-line1') || {}).value || '';
            const city = (form.querySelector('#donation-stripe-city') || {}).value || '';
            const postal = (form.querySelector('#donation-stripe-postal-code') || {}).value || '';
            if (!cpf.trim()) errors.push(I18N.required || 'Campo obrigatório.');
            if (!line1.trim()) errors.push(I18N.required || 'Campo obrigatório.');
            if (!city.trim()) errors.push(I18N.required || 'Campo obrigatório.');
            if (!postal.trim()) errors.push(I18N.required || 'Campo obrigatório.');
        }

        return errors;
    }

    /**
     * Coleta dados do formulário para envio AJAX (sem dados de cartão – PCI).
     */
    function getFormData(form) {
        const data = new FormData();
        data.append('action', 'donation_stripe_submit');
        data.append('nonce', NONCE);
        data.append('amount_type', form.querySelector('#donation-stripe-amount-type').value);
        var amountCustomEl = form.querySelector('.donation-stripe-amount-custom');
        var amountCustomRaw = amountCustomEl ? amountCustomEl.value : '';
        data.append('amount_custom', parseDollarMasked(amountCustomRaw) || '');
        data.append('frequency', form.querySelector('#donation-stripe-frequency').value);
        data.append('email', (form.querySelector('#donation-stripe-email') || {}).value || '');
        data.append('cardholder_name', (form.querySelector('#donation-stripe-cardholder-name') || {}).value || '');
        data.append('payment_method', form.querySelector('#donation-stripe-payment-method').value);
        data.append('country', (form.querySelector('#donation-stripe-country') || {}).value || 'BR');
        data.append('cpf_cnpj', (form.querySelector('#donation-stripe-cpf-cnpj') || {}).value || '');
        data.append('address_line1', (form.querySelector('#donation-stripe-address-line1') || {}).value || '');
        data.append('address_line2', (form.querySelector('#donation-stripe-address-line2') || {}).value || '');
        data.append('city', (form.querySelector('#donation-stripe-city') || {}).value || '');
        data.append('state', (form.querySelector('#donation-stripe-state') || {}).value || '');
        data.append('postal_code', (form.querySelector('#donation-stripe-postal-code') || {}).value || '');
        return data;
    }

    /**
     * Envia formulário: AJAX para obter client_secret, depois confirma com Stripe.js.
     */
    function handleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('.donation-stripe-submit');
        const msgSuccess = document.getElementById('donation-stripe-message-success');
        const msgError = document.getElementById('donation-stripe-message-error');

        clearMessages(form);
        removeFieldErrors(form);

        const validationErrors = validateForm(form);
        if (validationErrors.length > 0) {
            showError(validationErrors.join(' '));
            return;
        }

        setLoading(form, true);
        if (msgError) msgError.classList.add('donation-stripe-hidden');
        if (msgSuccess) msgSuccess.classList.add('donation-stripe-hidden');

        const formData = getFormData(form);

        fetch(AJAX_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (body) {
                if (body.success && body.data && body.data.client_secret) {
                    const paymentMethod = form.querySelector('#donation-stripe-payment-method').value;
                    const paymentIntentId = body.data.payment_intent_id || null;
                    
                    let promise;
                    if (paymentMethod === 'card') {
                        promise = confirmCardPayment(body.data.client_secret, body.data.type);
                    } else {
                        promise = confirmBoletoPayment(body.data.client_secret, form, paymentIntentId);
                    }
                    
                    // Passar dados para o próximo then
                    return promise.then(function(result) {
                        return { body: body, result: result };
                    });
                } else {
                    throw new Error(body.data && body.data.message ? body.data.message : (I18N.message_error || 'Erro ao processar.'));
                }
            })
            .then(function (dataWrapper) {
                setLoading(form, false);
                var paymentMethod = form.querySelector('#donation-stripe-payment-method').value;
                if (msgSuccess) {
                    msgSuccess.classList.remove('donation-stripe-hidden');
                    msgSuccess.scrollIntoView({ behavior: 'smooth' });
                }
                form.reset();
                resetAmountButtons(form);
                resetFrequencyButtons(form);
                resetPaymentMethodButtons(form);
                
                var thankYouUrl = CONFIG.thankYouPageUrl || '';
                if (thankYouUrl) {
                    var sep = thankYouUrl.indexOf('?') >= 0 ? '&' : '?';
                    var queryParams = [];
                    
                    if (paymentMethod === 'boleto') {
                        queryParams.push('method=boleto');
                    } else {
                        queryParams.push('method=card');
                    }

                    // Tentar capturar o ID do pagamento para passar na URL
                    var pid = null;
                    if (dataWrapper.body && dataWrapper.body.data && dataWrapper.body.data.payment_intent_id) {
                        pid = dataWrapper.body.data.payment_intent_id;
                    } else if (dataWrapper.result && dataWrapper.result.paymentIntent) {
                        pid = dataWrapper.result.paymentIntent.id;
                    }
                    
                    if (pid) {
                        queryParams.push('payment_intent=' + encodeURIComponent(pid));
                    }
                    
                    if (queryParams.length > 0) {
                        thankYouUrl += sep + queryParams.join('&');
                    }

                    setTimeout(function () {
                        window.location.href = thankYouUrl;
                    }, 2000);
                }
            })
            .catch(function (err) {
                setLoading(form, false);
                showError(err.message || (I18N.message_error || 'Erro ao processar.'));
                if (msgError) {
                    msgError.textContent = err.message || '';
                    msgError.classList.remove('donation-stripe-hidden');
                    msgError.scrollIntoView({ behavior: 'smooth' });
                }
            });
    }

    /**
     * Confirma pagamento com cartão via Stripe.js (PaymentMethod + confirmCardPayment).
     */
    function confirmCardPayment(clientSecret, type) {
        if (!stripe || !cardNumberElement || !cardExpiryElement || !cardCvcElement) {
            return Promise.reject(new Error('Stripe não inicializado.'));
        }
        return stripe.createPaymentMethod({
            type: 'card',
            card: cardNumberElement,
            billing_details: {
                name: document.getElementById('donation-stripe-cardholder-name').value.trim()
            }
        }).then(function (pmResult) {
            if (pmResult.error) {
                throw new Error(pmResult.error.message);
            }
            if (type === 'subscription') {
                return stripe.confirmCardPayment(clientSecret, {
                    payment_method: pmResult.paymentMethod.id
                });
            }
            return stripe.confirmCardPayment(clientSecret, {
                payment_method: pmResult.paymentMethod.id
            });
        }).then(function (result) {
            if (result.error) {
                throw new Error(result.error.message);
            }
            return result;
        });
    }

    /**
     * Confirma pagamento com boleto (billing details do formulário).
     * @param {string} clientSecret
     * @param {HTMLFormElement} form
     * @param {string|null} paymentIntentId
     */
    function confirmBoletoPayment(clientSecret, form, paymentIntentId) {
        if (!stripe) return Promise.reject(new Error('Stripe não inicializado.'));
        const email = (form.querySelector('#donation-stripe-email') || {}).value || '';
        const name = (form.querySelector('#donation-stripe-cardholder-name') || {}).value || '';
        const line1 = (form.querySelector('#donation-stripe-address-line1') || {}).value || '';
        const line2 = (form.querySelector('#donation-stripe-address-line2') || {}).value || '';
        const city = (form.querySelector('#donation-stripe-city') || {}).value || '';
        const state = (form.querySelector('#donation-stripe-state') || {}).value || '';
        const postal = (form.querySelector('#donation-stripe-postal-code') || {}).value || '';
        const country = (form.querySelector('#donation-stripe-country-boleto') || form.querySelector('#donation-stripe-country') || {}).value || 'BR';
        const cpfCnpj = (form.querySelector('#donation-stripe-cpf-cnpj') || {}).value || '';
        var taxId = cpfCnpj.replace(/\D/g, '');

        var paymentMethodData = {
            billing_details: {
                name: name,
                email: email,
                address: {
                    line1: line1,
                    city: city,
                    postal_code: postal,
                    country: country
                }
            }
        };

        if (line2 && line2.trim()) {
            paymentMethodData.billing_details.address.line2 = line2;
        }
        if (state && state.trim()) {
            paymentMethodData.billing_details.address.state = state;
        }

        if (taxId) {
            paymentMethodData.boleto = {
                tax_id: taxId
            };
        }

        return stripe.confirmBoletoPayment(clientSecret, {
            payment_method: paymentMethodData
        }).then(function (result) {
            if (result.error) {
                throw new Error(result.error.message);
            }
            // Após confirmar o boleto, enviar e-mail com o link
            if (paymentIntentId) {
                return sendBoletoEmail(paymentIntentId).then(function () {
                    return result;
                }).catch(function (err) {
                    // Não quebrar o fluxo se o e-mail falhar, apenas logar
                    console.warn('Falha ao enviar e-mail do boleto:', err);
                    return result;
                });
            }
            return result;
        });
    }

    /**
     * Envia e-mail com link do boleto via AJAX.
     */
    function sendBoletoEmail(paymentIntentId) {
        const data = new FormData();
        data.append('action', 'donation_stripe_send_boleto_email');
        data.append('nonce', NONCE);
        data.append('payment_intent_id', paymentIntentId);

        return fetch(AJAX_URL, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json();
        }).then(function (body) {
            if (!body.success) {
                throw new Error(body.data && body.data.message ? body.data.message : 'Erro ao enviar e-mail');
            }
        });
    }

    function setLoading(form, loading) {
        const fullscreenEl = document.getElementById('donation-stripe-fullscreen-loading');
        if (loading) {
            form.classList.add('is-loading');
            const btn = form.querySelector('.donation-stripe-submit');
            if (btn) btn.disabled = true;
            if (fullscreenEl) {
                fullscreenEl.classList.remove('donation-stripe-hidden');
                fullscreenEl.setAttribute('aria-busy', 'true');
            }
        } else {
            form.classList.remove('is-loading');
            const btn = form.querySelector('.donation-stripe-submit');
            if (btn) btn.disabled = false;
            if (fullscreenEl) {
                fullscreenEl.classList.add('donation-stripe-hidden');
                fullscreenEl.setAttribute('aria-busy', 'false');
            }
        }
    }

    function showError(msg) {
        const el = document.getElementById('donation-stripe-message-error');
        if (el) {
            el.textContent = msg;
            el.classList.remove('donation-stripe-hidden');
            el.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function clearMessages(form) {
        const s = document.getElementById('donation-stripe-message-success');
        const e = document.getElementById('donation-stripe-message-error');
        if (s) s.classList.add('donation-stripe-hidden');
        if (e) {
            e.textContent = '';
            e.classList.add('donation-stripe-hidden');
        }
    }

    function removeFieldErrors(form) {
        form.querySelectorAll('.donation-stripe-field.has-error').forEach(function (f) {
            f.classList.remove('has-error');
            const msg = f.querySelector('.donation-stripe-field-error-msg');
            if (msg) msg.remove();
        });
    }

    function resetAmountButtons(form) {
        const buttons = form.querySelectorAll('.donation-stripe-amount-btn');
        const typeInput = form.querySelector('#donation-stripe-amount-type');
        const customWrap = form.querySelector('.donation-stripe-amount-custom-wrap');
        buttons.forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        const otherBtn = form.querySelector('.donation-stripe-amount-btn-other');
        if (otherBtn) {
            otherBtn.classList.add('active');
            otherBtn.setAttribute('aria-pressed', 'true');
        }
        if (typeInput) typeInput.value = 'other';
        if (customWrap) customWrap.classList.remove('donation-stripe-hidden');
    }

    function resetFrequencyButtons(form) {
        const buttons = form.querySelectorAll('.donation-stripe-frequency-btn');
        const freqInput = form.querySelector('#donation-stripe-frequency');
        buttons.forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        if (buttons.length) {
            buttons[0].classList.add('active');
            buttons[0].setAttribute('aria-pressed', 'true');
        }
        if (freqInput) freqInput.value = 'once';
    }

    function resetPaymentMethodButtons(form) {
        const buttons = form.querySelectorAll('.donation-stripe-payment-method-btn');
        const methodInput = form.querySelector('#donation-stripe-payment-method');
        const cardFields = document.getElementById('donation-stripe-card-fields');
        const boletoFields = document.getElementById('donation-stripe-boleto-fields');
        const legalBlock = document.getElementById('donation-stripe-legal');
        buttons.forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        if (buttons.length) {
            buttons[0].classList.add('active');
            buttons[0].setAttribute('aria-pressed', 'true');
        }
        if (methodInput) methodInput.value = 'card';
        if (cardFields) cardFields.classList.remove('donation-stripe-hidden');
        if (boletoFields) boletoFields.classList.add('donation-stripe-hidden');
        if (legalBlock) legalBlock.classList.remove('donation-stripe-hidden');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
