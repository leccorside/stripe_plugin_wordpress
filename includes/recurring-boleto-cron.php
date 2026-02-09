<?php
/**
 * Cron job para gerar novos boletos de doações recorrentes.
 * Roda diariamente e verifica quais doações precisam gerar novo boleto.
 *
 * @package Donation_Stripe
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Donation_Stripe_Recurring_Boleto_Cron
{
    public static function init(): void
    {
        add_action('donation_stripe_generate_recurring_boletos', [__CLASS__, 'generate_pending_boletos']);
        if (!wp_next_scheduled('donation_stripe_generate_recurring_boletos')) {
            wp_schedule_event(time(), 'daily', 'donation_stripe_generate_recurring_boletos');
        }
    }

    /**
     * Gera novos boletos para doações recorrentes que chegaram na data.
     */
    public static function generate_pending_boletos(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'donation_stripe_recurring_boleto';
        $now = current_time('mysql');

        // Buscar doações ativas que precisam gerar novo boleto
        // next_boleto_at não pode ser NULL (primeiro boleto ainda não foi pago)
        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE active = 1 AND next_boleto_at IS NOT NULL AND next_boleto_at <= %s ORDER BY next_boleto_at ASC",
            $now
        ), ARRAY_A);

        if (empty($pending)) {
            return;
        }

        Donation_Stripe_Stripe_Handler::ensure_stripe_loaded();
        $options = Donation_Stripe_Admin_Settings::get_options();
        $mode = $options['stripe_mode'] ?? 'test';
        $secret_key = $mode === 'live'
            ? (string) ($options['stripe_secret_key_live'] ?? '')
            : (string) ($options['stripe_secret_key_test'] ?? '');
        if ($secret_key === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Donation Stripe] Cron: Stripe não configurado, não é possível gerar boletos.');
            }
            return;
        }
        \Stripe\Stripe::setApiKey($secret_key);

        foreach ($pending as $donation) {
            try {
                self::create_and_send_next_boleto($donation, $options);
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Donation Stripe] Erro ao gerar boleto recorrente ID ' . $donation['id'] . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Cria novo PaymentIntent com boleto e envia por e-mail.
     *
     * @param array<string, mixed> $donation
     * @param array<string, mixed> $options
     */
    private static function create_and_send_next_boleto(array $donation, array $options): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'donation_stripe_recurring_boleto';

        // Criar novo PaymentIntent com boleto
        $intent = \Stripe\PaymentIntent::create([
            'amount'   => (int) $donation['amount_cents'],
            'currency' => 'brl',
            'payment_method_types' => ['boleto'],
            'payment_method_options' => [
                'boleto' => [
                    'expires_after_days' => 3,
                ],
            ],
            'receipt_email' => $donation['email'],
            'metadata' => [
                'email' => $donation['email'],
                'source' => 'donation_recurring_boleto',
                'recurring' => 'true',
                'frequency' => $donation['frequency'],
                'original_donation_id' => (string) $donation['id'],
            ],
        ]);

        // Para boleto, precisamos confirmar o PaymentIntent para gerar o link
        // Usar dados mínimos de billing (email já está no PaymentIntent)
        try {
            $intent = $intent->confirm([
                'payment_method_data' => [
                    'type' => 'boleto',
                    'billing_details' => [
                        'email' => $donation['email'],
                    ],
                ],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Se falhar, tentar recuperar o PaymentIntent atualizado
            $intent = \Stripe\PaymentIntent::retrieve($intent->id, ['expand' => ['payment_method']]);
        }

        // Buscar o link do boleto
        $boleto_url = '';
        if (isset($intent->payment_method)) {
            $pm = is_string($intent->payment_method)
                ? \Stripe\PaymentMethod::retrieve($intent->payment_method)
                : $intent->payment_method;
            if (isset($pm->boleto) && isset($pm->boleto->hosted_voucher_url)) {
                $boleto_url = $pm->boleto->hosted_voucher_url;
            }
        }

        // Calcular próxima data (30 dias para mensal, 365 para anual após o pagamento deste novo boleto)
        $days = $donation['frequency'] === 'annual' ? 365 : 30;
        $next_boleto_at = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        // Atualizar registro: novo payment_intent_id e próxima data
        // Não atualizar last_paid_at aqui - será atualizado pelo webhook quando o boleto for pago
        $wpdb->update(
            $table,
            [
                'payment_intent_id' => $intent->id,
                'next_boleto_at'   => $next_boleto_at,
            ],
            ['id' => $donation['id']],
            ['%s', '%s'],
            ['%d']
        );

        // Enviar e-mail com o boleto
        self::send_boleto_email($donation['email'], $intent, $options);
    }

    /**
     * Envia e-mail ao cliente com o link do boleto.
     */
    private static function send_boleto_email(string $email, object $intent, array $options): void
    {
        if (empty($options['enable_invoice_email'])) {
            return;
        }

        $host = isset($options['smtp_host']) ? trim((string) $options['smtp_host']) : '';
        $from_addr = isset($options['email_from_address']) ? trim((string) $options['email_from_address']) : '';
        if ($host === '' || $from_addr === '' || !is_email($from_addr)) {
            return;
        }


        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Novo boleto de doação recorrente', 'donation-stripe'),
            get_bloginfo('name')
        );
        $message = __('Olá!', 'donation-stripe') . "\n\n";
        $message .= __('É hora de renovar sua doação recorrente. Segue o novo boleto:', 'donation-stripe') . "\n\n";
        if ($boleto_url !== '') {
            $message .= $boleto_url . "\n\n";
        }
        $message .= __('Obrigado por continuar apoiando nossa causa!', 'donation-stripe') . "\n\n";
        $message .= __('— ', 'donation-stripe') . get_bloginfo('name');

        $from_name = isset($options['email_from_name']) ? trim((string) $options['email_from_name']) : '';
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        if ($from_name !== '') {
            $headers[] = 'From: ' . $from_name . ' <' . $from_addr . '>';
        } else {
            $headers[] = 'From: ' . $from_addr;
        }

        $smtp_options = [
            'host'       => $host,
            'port'       => isset($options['smtp_port']) ? (string) $options['smtp_port'] : '587',
            'encryption' => isset($options['smtp_encryption']) ? (string) $options['smtp_encryption'] : 'tls',
            'user'       => isset($options['smtp_user']) ? trim((string) $options['smtp_user']) : '',
            'password'   => isset($options['smtp_password']) ? (string) $options['smtp_password'] : '',
        ];

        $callback = static function ($phpmailer) use ($smtp_options, $from_addr, $from_name) {
            if (!$phpmailer || !is_object($phpmailer)) {
                return;
            }
            if (method_exists($phpmailer, 'isSMTP')) {
                $phpmailer->isSMTP();
            } elseif (method_exists($phpmailer, 'IsSMTP')) {
                $phpmailer->IsSMTP();
            }
            $phpmailer->Host = $smtp_options['host'];
            $phpmailer->Port = (int) $smtp_options['port'];
            $phpmailer->SMTPAuth = ($smtp_options['user'] !== '');
            $phpmailer->Username = $smtp_options['user'];
            $phpmailer->Password = $smtp_options['password'];
            $phpmailer->SMTPSecure = $smtp_options['encryption'] === 'ssl' ? 'ssl' : ($smtp_options['encryption'] === 'tls' ? 'tls' : '');
            $phpmailer->From = $from_addr;
            $phpmailer->FromName = $from_name !== '' ? $from_name : $from_addr;
        };

        try {
            add_filter('phpmailer_init', $callback, 999);
            wp_mail($email, $subject, $message, $headers);
            remove_filter('phpmailer_init', $callback, 999);
        } catch (\Throwable $e) {
            remove_filter('phpmailer_init', $callback, 999);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Donation Stripe] Falha ao enviar e-mail do boleto recorrente: ' . $e->getMessage());
            }
        }
    }
}
