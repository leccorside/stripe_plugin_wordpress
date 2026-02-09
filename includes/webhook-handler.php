<?php
/**
 * Handler de webhooks Stripe: payment_intent.succeeded, invoice.payment_succeeded, invoice.payment_failed, customer.subscription.deleted.
 * Garante idempotência via tabela de eventos e log.
 *
 * @package Donation_Stripe
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Donation_Stripe_Webhook_Handler
{
    private const ROUTE = 'donation-stripe-webhook';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_rewrite']);
        add_action('template_redirect', [__CLASS__, 'handle_webhook']);
        add_action('donation_stripe_webhook_log', [__CLASS__, 'log_event'], 10, 3);
    }

    public static function register_rewrite(): void
    {
        add_rewrite_rule(
            '^' . self::ROUTE . '/?$',
            'index.php?donation_stripe_webhook=1',
            'top'
        );
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'donation_stripe_webhook';
            return $vars;
        });
    }

    /**
     * URL do webhook para configurar no Stripe Dashboard.
     */
    public static function get_webhook_url(): string
    {
        return home_url('/' . self::ROUTE . '/');
    }

    public static function handle_webhook(): void
    {
        if ((int) get_query_var('donation_stripe_webhook', 0) !== 1) {
            return;
        }

        $payload = file_get_contents('php://input');
        $sig = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

        $options = Donation_Stripe_Admin_Settings::get_options();
        $is_live = ($options['stripe_mode'] ?? 'test') === 'live';
        $secret = $is_live
            ? (string) ($options['webhook_secret_live'] ?? '')
            : (string) ($options['webhook_secret_test'] ?? '');
        if ($secret === '') {
            self::respond(400, 'Webhook secret not configured');
        }

        Donation_Stripe_Stripe_Handler::ensure_stripe_loaded();
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
        } catch (UnexpectedValueException $e) {
            self::respond(400, 'Invalid payload');
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            self::respond(400, 'Invalid signature');
        }

        $event_id = $event->id;
        if (self::event_already_processed($event_id)) {
            status_header(200);
            echo wp_json_encode(['received' => true]);
            exit;
        }

        self::store_event($event_id, $event->type, $payload);

        $handled = false;
        switch ($event->type) {
            case 'payment_intent.succeeded':
                self::handle_payment_intent_succeeded($event->data->object);
                $handled = true;
                break;
            case 'invoice.payment_succeeded':
                self::handle_invoice_payment_succeeded($event->data->object);
                $handled = true;
                break;
            case 'invoice.payment_failed':
                self::handle_invoice_payment_failed($event->data->object);
                $handled = true;
                break;
            case 'customer.subscription.deleted':
                self::handle_subscription_deleted($event->data->object);
                $handled = true;
                break;
        }

        do_action('donation_stripe_webhook_log', $event->type, $event_id, $handled);
        status_header(200);
        echo wp_json_encode(['received' => true]);
        exit;
    }

    private static function respond(int $code, string $message): void
    {
        status_header($code);
        echo wp_json_encode(['error' => $message]);
        exit;
    }

    private static function event_already_processed(string $event_id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'donation_stripe_events';
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE event_id = %s LIMIT 1",
            $event_id
        ));
        return $id !== null;
    }

    private static function store_event(string $event_id, string $event_type, string $payload): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'donation_stripe_events';
        $wpdb->insert($table, [
            'event_id'   => $event_id,
            'event_type' => $event_type,
            'payload'    => $payload,
        ], ['%s', '%s', '%s']);
    }

    private static function handle_payment_intent_succeeded(object $payment_intent): void
    {
        // Se for boleto recorrente, atualizar data de pagamento na tabela
        $metadata = $payment_intent->metadata ?? null;
        if ($metadata && isset($metadata->recurring) && $metadata->recurring === 'true') {
            self::update_recurring_boleto_payment($payment_intent);
        }

        // Se NÃO tiver invoice atrelada, enviar e-mail de confirmação de pagamento
        // Isso cobre boletos (únicos e recorrentes customizados) e pagamentos únicos via cartão
        if (empty($payment_intent->invoice)) {
            self::send_payment_intent_email_via_smtp($payment_intent);
        }

        do_action('donation_stripe_payment_succeeded', $payment_intent);
    }

    /**
     * Atualiza a data de pagamento de um boleto recorrente quando pago.
     */
    private static function update_recurring_boleto_payment(object $payment_intent): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'donation_stripe_recurring_boleto';
        $payment_intent_id = $payment_intent->id ?? '';

        if ($payment_intent_id === '') {
            return;
        }

        // Buscar registro pelo payment_intent_id
        $donation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE payment_intent_id = %s AND active = 1 LIMIT 1",
            $payment_intent_id
        ), ARRAY_A);

        if (!$donation) {
            return;
        }

        // Calcular próxima data baseada na frequência (30 dias para mensal, 365 para anual)
        $frequency = $donation['frequency'] ?? 'monthly';
        $days = $frequency === 'annual' ? 365 : 30;
        $paid_at = current_time('mysql');
        $next_boleto_at = date('Y-m-d H:i:s', strtotime("{$paid_at} +{$days} days"));

        // Atualizar last_paid_at e next_boleto_at
        $wpdb->update(
            $table,
            [
                'last_paid_at'   => $paid_at,
                'next_boleto_at' => $next_boleto_at,
            ],
            ['id' => $donation['id']],
            ['%s', '%s'],
            ['%d']
        );
    }

    private static function handle_invoice_payment_succeeded(object $invoice): void
    {
        self::send_invoice_email_via_smtp($invoice);
        do_action('donation_stripe_invoice_payment_succeeded', $invoice);
    }

    private static function send_invoice_email_via_smtp(object $invoice): void
    {
        $email = $invoice->customer_email ?? null;
        if (empty($email) || !is_email($email)) {
            return;
        }

        $hosted_url = $invoice->hosted_invoice_url ?? '';
        $pdf_url = $invoice->invoice_pdf ?? '';
        
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Sua fatura de doação', 'donation-stripe'),
            get_bloginfo('name')
        );
        
        $message = __('Obrigado pela sua doação.', 'donation-stripe') . "\n\n";
        $message .= __('Segue o link para visualizar e baixar sua fatura:', 'donation-stripe') . "\n\n";
        if ($hosted_url !== '') {
            $message .= $hosted_url . "\n\n";
        }
        if ($pdf_url !== '') {
            $message .= __('Link para PDF da fatura:', 'donation-stripe') . "\n" . $pdf_url . "\n\n";
        }
        $message .= __('— ', 'donation-stripe') . get_bloginfo('name');

        self::send_smtp_email($email, $subject, $message);
    }

    /**
     * Envia e-mail de confirmação para PaymentIntents (sem invoice).
     */
    private static function send_payment_intent_email_via_smtp(object $payment_intent): void
    {
        $email = $payment_intent->receipt_email ?? ($payment_intent->metadata->email ?? null);
        
        if (empty($email) || !is_email($email)) {
            return;
        }

        $amount = isset($payment_intent->amount) ? (int) $payment_intent->amount / 100 : 0;
        $currency = strtoupper($payment_intent->currency ?? 'BRL');
        $symbol = $currency === 'BRL' ? 'R$' : '$';
        $name = $payment_intent->metadata->cardholder_name ?? 'Doador';
        $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $payment_intent->created);
        
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Recibo de Doação', 'donation-stripe'),
            get_bloginfo('name')
        );

        $message = sprintf(__('Olá, %s.', 'donation-stripe'), $name) . "\n\n";
        $message .= __('Obrigado pela sua doação! Seu pagamento foi confirmado com sucesso.', 'donation-stripe') . "\n\n";
        
        $message .= __('DETALHES DA TRANSAÇÃO', 'donation-stripe') . "\n";
        $message .= str_repeat('-', 30) . "\n";
        $message .= sprintf(__('Data: %s', 'donation-stripe'), $date) . "\n";
        $message .= sprintf(__('Valor: %s %s', 'donation-stripe'), $symbol, number_format($amount, 2, ',', '.')) . "\n";
        $message .= sprintf(__('Status: %s', 'donation-stripe'), __('Pago', 'donation-stripe')) . "\n";
        $message .= sprintf(__('Referência: %s', 'donation-stripe'), $payment_intent->id) . "\n";
        $message .= str_repeat('-', 30) . "\n\n";
        
        $message .= __('Este e-mail serve como comprovante do seu pagamento.', 'donation-stripe') . "\n\n";
        $message .= __('— ', 'donation-stripe') . get_bloginfo('name');

        self::send_smtp_email($email, $subject, $message);
    }

    /**
     * Função auxiliar para enviar e-mail via SMTP usando configurações do plugin.
     */
    private static function send_smtp_email(string $to, string $subject, string $message): void
    {
        $options = Donation_Stripe_Admin_Settings::get_options();
        if (empty($options['enable_invoice_email'])) {
            return;
        }

        $host = isset($options['smtp_host']) ? trim((string) $options['smtp_host']) : '';
        $from_addr = isset($options['email_from_address']) ? trim((string) $options['email_from_address']) : '';
        
        if ($host === '' || $from_addr === '' || !is_email($from_addr)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Donation Stripe] E-mail SMTP: Host e/ou e-mail remetente não configurados.');
            }
            return;
        }

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
            $sent = wp_mail($to, $subject, $message, $headers);
            remove_filter('phpmailer_init', $callback, 999);
            if (!$sent && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Donation Stripe] wp_mail retornou false.');
            }
        } catch (\Throwable $e) {
            remove_filter('phpmailer_init', $callback, 999);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Donation Stripe] Falha ao enviar e-mail: ' . $e->getMessage());
            }
        }
    }

    private static function handle_invoice_payment_failed(object $invoice): void
    {
        do_action('donation_stripe_invoice_payment_failed', $invoice);
    }

    private static function handle_subscription_deleted(object $subscription): void
    {
        do_action('donation_stripe_subscription_deleted', $subscription);
    }

    public static function log_event(string $type, string $id, bool $handled): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Donation Stripe] Webhook: %s (ID: %s) - Handled: %s', $type, $id, $handled ? 'Yes' : 'No'));
        }
    }
}
