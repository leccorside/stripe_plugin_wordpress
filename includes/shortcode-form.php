<?php
/**
 * Shortcode [donation_stripe] – renderiza o formulário de doação e enfileira assets apenas quando usado.
 *
 * @package Donation_Stripe
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Donation_Stripe_Shortcode_Form
{
    private static bool $assets_loaded = false;

    public static function init(): void
    {
        add_shortcode('donation_stripe', [__CLASS__, 'render_shortcode']);
        add_shortcode('donation_stripe_thank_you', [__CLASS__, 'render_thank_you_shortcode']);
    }

    /**
     * Renderiza o shortcode [donation_stripe].
     *
     * @param array<string, string> $atts Atributos do shortcode (reservado para uso futuro).
     * @return string HTML do formulário.
     */
    public static function render_shortcode(array $atts = []): string
    {
        self::enqueue_assets();
        $options = Donation_Stripe_Admin_Settings::get_options();
        ob_start();
        include DONATION_STRIPE_PLUGIN_DIR . 'templates/form-donation.php';
        return (string) ob_get_clean();
    }

    /**
     * Carrega CSS e JS somente quando o shortcode está na página.
     */
    private static function enqueue_assets(): void
    {
        if (self::$assets_loaded) {
            return;
        }
        self::$assets_loaded = true;

        $css_url = DONATION_STRIPE_PLUGIN_URL . 'assets/css/style.css';
        $js_url  = DONATION_STRIPE_PLUGIN_URL . 'assets/js/script.js';
        $version = DONATION_STRIPE_VERSION;

        wp_enqueue_style(
            'donation-stripe-form',
            $css_url,
            [],
            $version
        );
        wp_enqueue_script(
            'donation-stripe-form',
            $js_url,
            ['stripe-js'],
            $version,
            true
        );

        $options = Donation_Stripe_Admin_Settings::get_options();
        $is_test = ($options['stripe_mode'] ?? 'test') === 'test';
        $publishable_key = $is_test
            ? ($options['stripe_publishable_key_test'] ?? '')
            : ($options['stripe_publishable_key_live'] ?? '');

        wp_localize_script('donation-stripe-form', 'donationStripeConfig', [
            'publishableKey'   => $publishable_key,
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('donation_stripe_submit'),
            'thankYouPageUrl'  => !empty($options['thank_you_page_url']) ? esc_url($options['thank_you_page_url']) : '',
            'i18n'             => [
                'donationAmount'     => __('Donation amount', 'donation-stripe'),
                'donationFrequency'  => __('Donation frequency', 'donation-stripe'),
                'oneTime'            => __('Uma única vez', 'donation-stripe'),
                'monthly'            => __('Monthly', 'donation-stripe'),
                'annual'             => __('Annual', 'donation-stripe'),
                'email'              => __('E-mail address', 'donation-stripe'),
                'cardholderName'     => __('Cardholder\'s name', 'donation-stripe'),
                'card'               => __('Cartão', 'donation-stripe'),
                'boleto'             => __('Boleto', 'donation-stripe'),
                'cardNumber'         => __('Número do cartão', 'donation-stripe'),
                'expiry'             => __('Data de validade', 'donation-stripe'),
                'cvc'                => __('Código de segurança', 'donation-stripe'),
                'country'            => __('País', 'donation-stripe'),
                'cpfCnpj'            => __('CPF ou CNPJ', 'donation-stripe'),
                'countryRegion'      => __('País ou região', 'donation-stripe'),
                'addressLine1'       => __('Linha 1 do endereço', 'donation-stripe'),
                'addressLine2'       => __('Linha 2 do endereço', 'donation-stripe'),
                'city'               => __('Cidade', 'donation-stripe'),
                'state'              => __('Estado', 'donation-stripe'),
                'postalCode'         => __('Código postal', 'donation-stripe'),
                'donate'              => __('Donate', 'donation-stripe'),
                'legalText'          => __('Ao fornecer seus dados de cartão, você permite que SINAGOGA BEIT JACOB faça a cobrança para pagamentos futuros em conformidade com os respectivos termos.', 'donation-stripe'),
                'required'           => __('Campo obrigatório.', 'donation-stripe'),
                'invalidEmail'       => __('E-mail inválido.', 'donation-stripe'),
                'invalidAmount'      => __('Informe um valor válido.', 'donation-stripe'),
            ],
        ]);

        // Stripe.js (carregado apenas quando shortcode está na página).
        $stripe_js = 'https://js.stripe.com/v3/';
        wp_enqueue_script(
            'stripe-js',
            $stripe_js,
            [],
            null,
            true
        );
    }

    /**
     * Renderiza o shortcode [donation_stripe_thank_you] – página de obrigado após doação.
     *
     * @param array<string, string> $atts Atributos do shortcode (reservado para uso futuro).
     * @return string HTML da página de obrigado.
     */
    public static function render_thank_you_shortcode(array $atts = []): string
    {
        $is_boleto = isset($_GET['method']) && $_GET['method'] === 'boleto';
        $title     = isset($atts['title']) ? sanitize_text_field($atts['title']) : __('Obrigado pela sua doação!', 'donation-stripe');
        if (isset($atts['message'])) {
            $message = sanitize_textarea_field($atts['message']);
        } else {
            $message = $is_boleto
                ? __('Sua doação será processada assim que o pagamento do boleto for confirmado. Agradecemos seu apoio.', 'donation-stripe')
                : __('Sua doação foi processada com sucesso. Agradecemos seu apoio.', 'donation-stripe');
        }

        $check_icon = '<svg class="donation-stripe-thank-you-icon" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

        $home_url = home_url('/');
        $back_label = isset($atts['back_label']) ? sanitize_text_field($atts['back_label']) : __('Voltar à home', 'donation-stripe');

        ob_start();
        ?>
        <div class="donation-stripe-thank-you">
            <div class="donation-stripe-thank-you-inner">
                <div class="donation-stripe-thank-you-icon-wrap"><?php echo $check_icon; ?></div>
                <h2 class="donation-stripe-thank-you-title"><?php echo esc_html($title); ?></h2>
                <p class="donation-stripe-thank-you-message"><?php echo esc_html($message); ?></p>
            </div>
            <a href="<?php echo esc_url($home_url); ?>" class="donation-stripe-thank-you-back"><?php echo esc_html($back_label); ?></a>
        </div>
        <?php
        self::enqueue_thank_you_styles();
        return (string) ob_get_clean();
    }

    /**
     * Enfileira CSS da página de obrigado (apenas quando o shortcode é usado).
     */
    private static function enqueue_thank_you_styles(): void
    {
        if (wp_style_is('donation-stripe-thank-you', 'enqueued')) {
            return;
        }
        wp_register_style('donation-stripe-thank-you', false, [], DONATION_STRIPE_VERSION);
        wp_enqueue_style('donation-stripe-thank-you');
        wp_add_inline_style('donation-stripe-thank-you', self::get_thank_you_css());
    }

    private static function get_thank_you_css(): string
    {
        return '
        .donation-stripe-thank-you { max-width: 560px; margin: 2rem auto; padding: 2rem; text-align: center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .donation-stripe-thank-you-inner { background: #fff; border-radius: 12px; padding: 2.5rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .donation-stripe-thank-you-icon-wrap { margin-bottom: 1rem; }
        .donation-stripe-thank-you-icon { width: 64px; height: 64px; color: #22c55e; margin: 0 auto; display: block; }
        .donation-stripe-thank-you-title { margin: 0 0 1rem; font-size: 1.5rem; color: #32325d; }
        .donation-stripe-thank-you-message { margin: 0; font-size: 1rem; color: #6b7c93; line-height: 1.6; }
        .donation-stripe-thank-you-back { display: inline-block; margin-top: 1.5rem; padding: 12px 24px; font-size: 1rem; font-weight: 600; color: #fff; background: #5469d4; border-radius: 8px; text-decoration: none; transition: background 0.15s; }
        .donation-stripe-thank-you-back:hover { background: #4358c4; color: #fff; }
        ';
    }
}
