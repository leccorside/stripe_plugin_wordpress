<?php
/**
 * Plugin Name: Doação Stripe - Sinagoga Beit Jacob
 * Plugin URI: https://marcasite.com.br
 * Description: Plugin de doações integrado à Stripe (cartão, boleto, doação única e recorrente).
 * Version: 1.0.0
 * Author: MarcaSite
 * Author URI: https://marcasite.com.br
 * Text Domain: donation-stripe
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DONATION_STRIPE_VERSION', '1.0.0');
define('DONATION_STRIPE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DONATION_STRIPE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DONATION_STRIPE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Verifica requisitos mínimos (PHP 8+, WordPress 6+).
 */
function donation_stripe_check_requirements(): bool
{
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('O plugin Doação Stripe requer PHP 8.0 ou superior.', 'donation-stripe');
            echo '</p></div>';
        });
        return false;
    }
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('O plugin Doação Stripe requer WordPress 6.0 ou superior.', 'donation-stripe');
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Carrega o autoload e os arquivos do plugin.
 */
function donation_stripe_load(): void
{
    if (!donation_stripe_check_requirements()) {
        return;
    }

    require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/admin-settings.php';
    require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/shortcode-form.php';
    require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/stripe-handler.php';
    require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/webhook-handler.php';
    require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/recurring-boleto-cron.php';

    Donation_Stripe_Admin_Settings::init();
    Donation_Stripe_Shortcode_Form::init();
    Donation_Stripe_Stripe_Handler::init();
    Donation_Stripe_Webhook_Handler::init();
    Donation_Stripe_Recurring_Boleto_Cron::init();
}

add_action('plugins_loaded', 'donation_stripe_load');

/**
 * Ativação do plugin: cria tabela de log de eventos se necessário.
 */
function donation_stripe_activate(): void
{
    if (!donation_stripe_check_requirements()) {
        wp_die(esc_html__('Requisitos não atendidos. Verifique PHP e WordPress.', 'donation-stripe'));
    }
    donation_stripe_maybe_create_events_table();
    donation_stripe_maybe_create_recurring_boleto_table();
    require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/webhook-handler.php';
    Donation_Stripe_Webhook_Handler::register_rewrite();
    flush_rewrite_rules();
}

/**
 * Cria tabela para log de eventos Stripe (idempotência e auditoria).
 */
function donation_stripe_maybe_create_events_table(): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'donation_stripe_events';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id varchar(255) NOT NULL,
        event_type varchar(100) NOT NULL,
        payload longtext,
        processed_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY event_id (event_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Cria tabela para doações recorrentes por boleto.
 */
function donation_stripe_maybe_create_recurring_boleto_table(): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'donation_stripe_recurring_boleto';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        amount_cents int(11) NOT NULL,
        frequency varchar(20) NOT NULL,
        payment_intent_id varchar(255) NOT NULL,
        last_paid_at datetime NULL,
        next_boleto_at datetime NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        active tinyint(1) DEFAULT 1,
        PRIMARY KEY (id),
        KEY email (email),
        KEY next_boleto_at (next_boleto_at),
        KEY active (active)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'donation_stripe_activate');

/**
 * Desativação: limpa rewrite rules.
 */
function donation_stripe_deactivate(): void
{
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'donation_stripe_deactivate');
