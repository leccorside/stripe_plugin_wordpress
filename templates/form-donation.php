<?php
/**
 * Template do formulário de doação – layout idêntico às imagens de referência.
 * Cores, espaçamentos, tipografia e estrutura HTML preservados.
 *
 * @package Donation_Stripe
 * @var array $options Opções do plugin (Donation_Stripe_Admin_Settings::get_options()).
 */

if (!defined('ABSPATH')) {
    exit;
}

$enable_boleto = !empty($options['enable_boleto']);
$enable_monthly = !empty($options['enable_recurring_monthly']);
$enable_annual = !empty($options['enable_recurring_annual']);

// Lista completa de países (agora vindo do controller)
$all_countries = $countries ?? [];

?>

<div class="donation-stripe-form-wrapper" id="donation-stripe-form-wrapper">
    <!-- Overlay de loading em tela cheia -->
    <div class="donation-stripe-fullscreen-loading donation-stripe-hidden" id="donation-stripe-fullscreen-loading" aria-live="polite" aria-busy="false">
        <div class="donation-stripe-fullscreen-loading-content">
            <div class="donation-stripe-fullscreen-loading-icons" aria-hidden="true">
                <span class="donation-stripe-loading-icon donation-stripe-loading-icon-card"></span>
                <span class="donation-stripe-loading-icon donation-stripe-loading-icon-boleto"></span>
            </div>
            <p class="donation-stripe-fullscreen-loading-text"><?php echo esc_html($t['payment_processing']); ?></p>
        </div>
    </div>

    <form class="donation-stripe-form" id="donation-stripe-form" novalidate>
        <?php wp_nonce_field('donation_stripe_submit', 'donation_stripe_nonce'); ?>

        <!-- Donation amount -->
        <div class="donation-stripe-field">
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-amount-preset"><?php echo esc_html($t['donation_amount']); ?></label>
            <p class="donation-stripe-currency-notice" style="font-size: 0.85em; color: #666; margin-bottom: 8px;">
                <?php echo esc_html($t['currency_notice']); ?>
            </p>
            <div class="donation-stripe-amount-buttons">
                <button type="button" class="donation-stripe-amount-btn active" data-amount="25" aria-pressed="true">$25</button>
                <button type="button" class="donation-stripe-amount-btn" data-amount="35" aria-pressed="false">$35</button>
                <button type="button" class="donation-stripe-amount-btn" data-amount="50" aria-pressed="false">$50</button>
                <button type="button" class="donation-stripe-amount-btn donation-stripe-amount-btn-other" data-amount="other" aria-pressed="false"><?php echo esc_html($t['other']); ?></button>
            </div>
            <div class="donation-stripe-amount-custom-wrap donation-stripe-hidden" id="donation-stripe-amount-custom-wrap">
                <span class="donation-stripe-currency-symbol">$</span>
                <input type="text" class="donation-stripe-input donation-stripe-amount-custom" id="donation-stripe-amount-custom" name="amount_custom" inputmode="numeric" placeholder="0" autocomplete="off" aria-describedby="donation-stripe-amount-decimals" />
                <span class="donation-stripe-amount-decimals" id="donation-stripe-amount-decimals" aria-hidden="true">,00</span>
            </div>
            <input type="hidden" name="amount_type" id="donation-stripe-amount-type" value="25" />
        </div>

        <!-- Donation frequency -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label"><?php echo esc_html($t['donation_frequency']); ?></label>
            <div class="donation-stripe-frequency-group" role="group" aria-label="<?php echo esc_attr($t['donation_frequency']); ?>">
                <button type="button" class="donation-stripe-frequency-btn active" data-frequency="once" aria-pressed="true"><?php echo esc_html($t['frequency_once']); ?></button>
                <button type="button" class="donation-stripe-frequency-btn" data-frequency="monthly" aria-pressed="false" <?php echo $enable_monthly ? '' : ' disabled'; ?>><?php echo esc_html($t['frequency_monthly']); ?></button>
                <button type="button" class="donation-stripe-frequency-btn" data-frequency="annual" aria-pressed="false" <?php echo $enable_annual ? '' : ' disabled'; ?>><?php echo esc_html($t['frequency_annual']); ?></button>
            </div>
            <input type="hidden" name="frequency" id="donation-stripe-frequency" value="once" />
        </div>

        <!-- E-mail -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-email"><?php echo esc_html($t['email']); ?></label>
            <input type="email" class="donation-stripe-input" id="donation-stripe-email" name="email" required autocomplete="email" />
        </div>

        <!-- Cardholder name -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-cardholder-name"><?php echo esc_html($t['cardholder_name']); ?></label>
            <input type="text" class="donation-stripe-input" id="donation-stripe-cardholder-name" name="cardholder_name" required autocomplete="cc-name" />
        </div>

        <!-- Payment method: Card / Boleto -->
        <div class="donation-stripe-field donation-stripe-payment-method-field">
            <div class="donation-stripe-payment-method-group" role="group" aria-label="Payment method">
                <button type="button" class="donation-stripe-payment-method-btn active" data-method="card" aria-pressed="true">
                    <span class="donation-stripe-payment-icon donation-stripe-icon-card" aria-hidden="true"></span>
                    <span><?php echo esc_html($t['card']); ?></span>
                </button>
                <button type="button" class="donation-stripe-payment-method-btn" data-method="boleto" aria-pressed="false" style="display:none !important;" <?php echo $enable_boleto ? '' : ' disabled'; ?>>
                    <span class="donation-stripe-payment-icon donation-stripe-icon-boleto" aria-hidden="true"></span>
                    <span><?php echo esc_html($t['boleto']); ?></span>
                </button>
            </div>
            <input type="hidden" name="payment_method" id="donation-stripe-payment-method" value="card" />
        </div>

        <!-- Card fields (visible when Cartão selected). Stripe Elements montados via JS (PCI). -->
        <div class="donation-stripe-card-fields" id="donation-stripe-card-fields">
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-card-element"><?php echo esc_html($t['card_number']); ?></label>
                <div class="donation-stripe-card-number-wrap">
                    <div id="donation-stripe-card-element" class="donation-stripe-stripe-element"></div>
                    <span class="donation-stripe-card-brands" aria-hidden="true">
                        <span class="donation-stripe-brand donation-stripe-brand-visa">VISA</span>
                        <span class="donation-stripe-brand donation-stripe-brand-mc">Mastercard</span>
                    </span>
                </div>
            </div>
            <div class="donation-stripe-field-row">
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-expiry-element"><?php echo esc_html($t['expiration_date']); ?></label>
                    <div id="donation-stripe-expiry-element" class="donation-stripe-stripe-element"></div>
                </div>
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-cvc-element"><?php echo esc_html($t['security_code']); ?></label>
                    <div class="donation-stripe-cvc-wrap">
                        <div id="donation-stripe-cvc-element" class="donation-stripe-stripe-element"></div>
                        <span class="donation-stripe-cvc-hint" aria-hidden="true"></span>
                    </div>
                </div>
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-country"><?php echo esc_html($t['country']); ?></label>
                <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-country" name="country">
                    <option value="BR" selected><?php echo esc_html($all_countries['BR'] ?? 'Brazil'); ?></option>
                    <?php
foreach ($all_countries as $code => $name) {
    if ($code === 'BR')
        continue;
    echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
}
?>
                </select>
            </div>
        </div>

        <!-- Boleto fields (visible when Boleto selected) -->
        <div class="donation-stripe-boleto-fields donation-stripe-hidden" id="donation-stripe-boleto-fields">
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-cpf-cnpj"><?php echo esc_html($t['tax_id']); ?></label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-cpf-cnpj" name="cpf_cnpj" placeholder="000.000.000-00" autocomplete="off" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-country-boleto"><?php echo esc_html($t['country_region']); ?></label>
                <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-country-boleto" name="country_boleto">
                    <option value="BR" selected><?php echo esc_html($all_countries['BR'] ?? 'Brazil'); ?></option>
                </select>
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-address-line1"><?php echo esc_html($t['address_line1']); ?></label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-address-line1" name="address_line1" autocomplete="address-line1" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-address-line2"><?php echo esc_html($t['address_line2']); ?></label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-address-line2" name="address_line2" placeholder="<?php echo esc_attr($t['address_line2_placeholder']); ?>" autocomplete="address-line2" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-city"><?php echo esc_html($t['city']); ?></label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-city" name="city" autocomplete="address-level2" />
            </div>
            <div class="donation-stripe-field-row">
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-state"><?php echo esc_html($t['state']); ?></label>
                    <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-state" name="state">
                        <?php
// $states_br agora vem do controller
$states_br = $states_br ?? [];
foreach ($states_br as $uf => $name) {
    echo '<option value="' . esc_attr($uf) . '"' . selected($uf, 'GO', false) . '>' . esc_html($name) . '</option>';
}
?>
                    </select>
                </div>
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-postal-code"><?php echo esc_html($t['postal_code']); ?></label>
                    <input type="text" class="donation-stripe-input" id="donation-stripe-postal-code" name="postal_code" autocomplete="postal-code" />
                </div>
            </div>
        </div>

        <!-- Legal text -->
        <p class="donation-stripe-legal" id="donation-stripe-legal">
            <?php echo esc_html($t['legal_text']); ?>
        </p>

        <!-- Submit -->
        <div class="donation-stripe-field donation-stripe-submit-wrap">
            <button type="submit" class="donation-stripe-submit" id="donation-stripe-submit">
                <span class="donation-stripe-submit-text"><?php echo esc_html($t['donate']); ?></span>
                <span class="donation-stripe-submit-loading donation-stripe-hidden" aria-hidden="true"><?php echo esc_html($t['processing']); ?></span>
            </button>
        </div>

        <!-- Messages -->
        <div class="donation-stripe-message donation-stripe-message-success donation-stripe-hidden" id="donation-stripe-message-success" role="alert">
            <?php echo esc_html($options['message_success'] ?? $t['success_message']); ?>
        </div>
        <div class="donation-stripe-message donation-stripe-message-error donation-stripe-hidden" id="donation-stripe-message-error" role="alert"></div>
    </form>

    <!-- Stripe Elements container (usado pelo JS para Payment Element ou card) -->
    <div id="donation-stripe-element-mount" class="donation-stripe-hidden"></div>
</div>
