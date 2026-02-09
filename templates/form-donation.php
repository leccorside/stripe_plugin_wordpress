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
?>

<div class="donation-stripe-form-wrapper" id="donation-stripe-form-wrapper">
    <!-- Overlay de loading em tela cheia -->
    <div class="donation-stripe-fullscreen-loading donation-stripe-hidden" id="donation-stripe-fullscreen-loading" aria-live="polite" aria-busy="false">
        <div class="donation-stripe-fullscreen-loading-content">
            <div class="donation-stripe-fullscreen-loading-icons" aria-hidden="true">
                <span class="donation-stripe-loading-icon donation-stripe-loading-icon-card"></span>
                <span class="donation-stripe-loading-icon donation-stripe-loading-icon-boleto"></span>
            </div>
            <p class="donation-stripe-fullscreen-loading-text"><?php esc_html_e('Processando Pagamento...', 'donation-stripe'); ?></p>
        </div>
    </div>

    <form class="donation-stripe-form" id="donation-stripe-form" novalidate>
        <?php wp_nonce_field('donation_stripe_submit', 'donation_stripe_nonce'); ?>

        <!-- Donation amount -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-amount-preset"><?php esc_html_e('Donation amount', 'donation-stripe'); ?></label>
            <div class="donation-stripe-amount-buttons">
                <button type="button" class="donation-stripe-amount-btn active" data-amount="25" aria-pressed="true">$25</button>
                <button type="button" class="donation-stripe-amount-btn" data-amount="35" aria-pressed="false">$35</button>
                <button type="button" class="donation-stripe-amount-btn" data-amount="50" aria-pressed="false">$50</button>
                <button type="button" class="donation-stripe-amount-btn donation-stripe-amount-btn-other" data-amount="other" aria-pressed="false"><?php esc_html_e('Other', 'donation-stripe'); ?></button>
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
            <label class="donation-stripe-label"><?php esc_html_e('Donation frequency', 'donation-stripe'); ?></label>
            <div class="donation-stripe-frequency-group" role="group" aria-label="<?php esc_attr_e('Donation frequency', 'donation-stripe'); ?>">
                <button type="button" class="donation-stripe-frequency-btn active" data-frequency="once" aria-pressed="true"><?php esc_html_e('Uma única vez', 'donation-stripe'); ?></button>
                <button type="button" class="donation-stripe-frequency-btn" data-frequency="monthly" aria-pressed="false" <?php echo $enable_monthly ? '' : ' disabled'; ?>><?php esc_html_e('Monthly', 'donation-stripe'); ?></button>
                <button type="button" class="donation-stripe-frequency-btn" data-frequency="annual" aria-pressed="false" <?php echo $enable_annual ? '' : ' disabled'; ?>><?php esc_html_e('Annual', 'donation-stripe'); ?></button>
            </div>
            <input type="hidden" name="frequency" id="donation-stripe-frequency" value="once" />
        </div>

        <!-- E-mail -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-email"><?php esc_html_e('E-mail address', 'donation-stripe'); ?></label>
            <input type="email" class="donation-stripe-input" id="donation-stripe-email" name="email" required autocomplete="email" />
        </div>

        <!-- Cardholder name -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-cardholder-name"><?php esc_html_e('Cardholder\'s name', 'donation-stripe'); ?></label>
            <input type="text" class="donation-stripe-input" id="donation-stripe-cardholder-name" name="cardholder_name" required autocomplete="cc-name" />
        </div>

        <!-- Payment method: Card / Boleto -->
        <div class="donation-stripe-field donation-stripe-payment-method-field">
            <div class="donation-stripe-payment-method-group" role="group" aria-label="<?php esc_attr_e('Payment method', 'donation-stripe'); ?>">
                <button type="button" class="donation-stripe-payment-method-btn active" data-method="card" aria-pressed="true">
                    <span class="donation-stripe-payment-icon donation-stripe-icon-card" aria-hidden="true"></span>
                    <span><?php esc_html_e('Cartão', 'donation-stripe'); ?></span>
                </button>
                <button type="button" class="donation-stripe-payment-method-btn" data-method="boleto" aria-pressed="false" <?php echo $enable_boleto ? '' : ' disabled'; ?>>
                    <span class="donation-stripe-payment-icon donation-stripe-icon-boleto" aria-hidden="true"></span>
                    <span><?php esc_html_e('Boleto', 'donation-stripe'); ?></span>
                </button>
            </div>
            <input type="hidden" name="payment_method" id="donation-stripe-payment-method" value="card" />
        </div>

        <!-- Card fields (visible when Cartão selected). Stripe Elements montados via JS (PCI). -->
        <div class="donation-stripe-card-fields" id="donation-stripe-card-fields">
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-card-element"><?php esc_html_e('Número do cartão', 'donation-stripe'); ?></label>
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
                    <label class="donation-stripe-label" for="donation-stripe-expiry-element"><?php esc_html_e('Data de validade', 'donation-stripe'); ?></label>
                    <div id="donation-stripe-expiry-element" class="donation-stripe-stripe-element"></div>
                </div>
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-cvc-element"><?php esc_html_e('Código de segurança', 'donation-stripe'); ?></label>
                    <div class="donation-stripe-cvc-wrap">
                        <div id="donation-stripe-cvc-element" class="donation-stripe-stripe-element"></div>
                        <span class="donation-stripe-cvc-hint" aria-hidden="true"></span>
                    </div>
                </div>
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-country"><?php esc_html_e('País', 'donation-stripe'); ?></label>
                <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-country" name="country">
                    <option value="BR" selected><?php esc_html_e('Brasil', 'donation-stripe'); ?></option>
                    <?php
                    $countries = ['US' => __('Estados Unidos', 'donation-stripe'), 'PT' => __('Portugal', 'donation-stripe'), 'IL' => __('Israel', 'donation-stripe')];
                    foreach ($countries as $code => $name) {
                        if ($code === 'BR') continue;
                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <!-- Boleto fields (visible when Boleto selected) -->
        <div class="donation-stripe-boleto-fields donation-stripe-hidden" id="donation-stripe-boleto-fields">
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-cpf-cnpj"><?php esc_html_e('CPF ou CNPJ', 'donation-stripe'); ?></label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-cpf-cnpj" name="cpf_cnpj" placeholder="000.000.000-00" autocomplete="off" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-country-boleto"><?php esc_html_e('País ou região', 'donation-stripe'); ?></label>
                <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-country-boleto" name="country_boleto">
                    <option value="BR" selected><?php esc_html_e('Brasil', 'donation-stripe'); ?></option>
                </select>
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-address-line1"><?php esc_html_e('Linha 1 do endereço', 'donation-stripe'); ?></label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-address-line1" name="address_line1" autocomplete="address-line1" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-address-line2"><?php esc_html_e('Linha 2 do endereço', 'donation-stripe'); ?></label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-address-line2" name="address_line2" placeholder="<?php esc_attr_e('Apt., suíte, número da unidade etc. (opcional)', 'donation-stripe'); ?>" autocomplete="address-line2" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-city"><?php esc_html_e('Cidade', 'donation-stripe'); ?></label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-city" name="city" autocomplete="address-level2" />
            </div>
            <div class="donation-stripe-field-row">
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-state"><?php esc_html_e('Estado', 'donation-stripe'); ?></label>
                    <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-state" name="state">
                        <?php
                        $states_br = ['AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'];
                        foreach ($states_br as $uf => $name) {
                            echo '<option value="' . esc_attr($uf) . '"' . selected($uf, 'GO', false) . '>' . esc_html($name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-postal-code"><?php esc_html_e('Código postal', 'donation-stripe'); ?></label>
                    <input type="text" class="donation-stripe-input" id="donation-stripe-postal-code" name="postal_code" autocomplete="postal-code" />
                </div>
            </div>
        </div>

        <!-- Legal text -->
        <p class="donation-stripe-legal" id="donation-stripe-legal">
            <?php echo esc_html(__('Ao fornecer seus dados de cartão, você permite que SINAGOGA BEIT JACOB faça a cobrança para pagamentos futuros em conformidade com os respectivos termos.', 'donation-stripe')); ?>
        </p>

        <!-- Submit -->
        <div class="donation-stripe-field donation-stripe-submit-wrap">
            <button type="submit" class="donation-stripe-submit" id="donation-stripe-submit">
                <span class="donation-stripe-submit-text"><?php esc_html_e('Donate', 'donation-stripe'); ?></span>
                <span class="donation-stripe-submit-loading donation-stripe-hidden" aria-hidden="true"><?php esc_html_e('Processando...', 'donation-stripe'); ?></span>
            </button>
        </div>

        <!-- Messages -->
        <div class="donation-stripe-message donation-stripe-message-success donation-stripe-hidden" id="donation-stripe-message-success" role="alert">
            <?php echo esc_html($options['message_success'] ?? __('Obrigado! Sua doação foi processada com sucesso.', 'donation-stripe')); ?>
        </div>
        <div class="donation-stripe-message donation-stripe-message-error donation-stripe-hidden" id="donation-stripe-message-error" role="alert"></div>
    </form>

    <!-- Stripe Elements container (usado pelo JS para Payment Element ou card) -->
    <div id="donation-stripe-element-mount" class="donation-stripe-hidden"></div>
</div>
