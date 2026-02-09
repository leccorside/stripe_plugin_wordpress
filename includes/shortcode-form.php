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
                'donationAmount'     => __('Donation Amount', 'donation-stripe'),
                'donationFrequency'  => __('Donation Frequency', 'donation-stripe'),
                'oneTime'            => __('One-time', 'donation-stripe'),
                'monthly'            => __('Monthly', 'donation-stripe'),
                'annual'             => __('Annual', 'donation-stripe'),
                'email'              => __('Email Address', 'donation-stripe'),
                'cardholderName'     => __('Cardholder Name', 'donation-stripe'),
                'card'               => __('Card', 'donation-stripe'),
                'boleto'             => __('Boleto', 'donation-stripe'),
                'cardNumber'         => __('Card Number', 'donation-stripe'),
                'expiry'             => __('Expiration Date', 'donation-stripe'),
                'cvc'                => __('Security Code', 'donation-stripe'),
                'country'            => __('Country', 'donation-stripe'),
                'cpfCnpj'            => __('Tax ID (CPF/CNPJ)', 'donation-stripe'),
                'countryRegion'      => __('Country or Region', 'donation-stripe'),
                'addressLine1'       => __('Address Line 1', 'donation-stripe'),
                'addressLine2'       => __('Address Line 2', 'donation-stripe'),
                'city'               => __('City', 'donation-stripe'),
                'state'              => __('State', 'donation-stripe'),
                'postalCode'         => __('Postal Code', 'donation-stripe'),
                'donate'              => __('Donate', 'donation-stripe'),
                'legalText'          => __('By providing your card information, you allow SINAGOGA BEIT JACOB to charge your card for future payments in accordance with their terms.', 'donation-stripe'),
                'required'           => __('Required field.', 'donation-stripe'),
                'invalidEmail'       => __('Invalid email.', 'donation-stripe'),
                'invalidAmount'      => __('Enter a valid amount.', 'donation-stripe'),
                'message_error'      => __('Error processing payment.', 'donation-stripe'),
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
        $title     = isset($atts['title']) ? sanitize_text_field($atts['title']) : __('Thank you for your donation!', 'donation-stripe');
        if (isset($atts['message'])) {
            $message = sanitize_textarea_field($atts['message']);
        } else {
            $message = $is_boleto
                ? __('Your donation will be processed as soon as the boleto payment is confirmed. We appreciate your support.', 'donation-stripe')
                : __('Your donation has been successfully processed. We appreciate your support.', 'donation-stripe');
        }

        $check_icon = '<svg class="donation-stripe-thank-you-icon" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

        $home_url = home_url('/');
        $back_label = isset($atts['back_label']) ? sanitize_text_field($atts['back_label']) : __('Back to Home', 'donation-stripe');

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
