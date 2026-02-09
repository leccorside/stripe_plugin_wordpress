<?php
/**
 * Processamento de pagamentos Stripe: PaymentIntent (único) e Subscription (recorrente).
 *
 * @package Donation_Stripe
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Donation_Stripe_Stripe_Handler
{
    public static function init(): void
    {
        add_action('wp_ajax_donation_stripe_submit', [__CLASS__, 'handle_submit']);
        add_action('wp_ajax_nopriv_donation_stripe_submit', [__CLASS__, 'handle_submit']);
        add_action('wp_ajax_donation_stripe_send_boleto_email', [__CLASS__, 'send_boleto_email_ajax']);
        add_action('wp_ajax_nopriv_donation_stripe_send_boleto_email', [__CLASS__, 'send_boleto_email_ajax']);
    }

    /**
     * Handler AJAX do formulário: valida, cria PaymentIntent ou Subscription e retorna client_secret ou erro.
     */
    public static function handle_submit(): void
    {
        if (!check_ajax_referer('donation_stripe_submit', 'nonce', false)) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'donation-stripe')], 403);
        }

        $raw = [
            'amount_type'     => isset($_POST['amount_type']) ? sanitize_text_field(wp_unslash($_POST['amount_type'])) : '',
            'amount_custom'   => isset($_POST['amount_custom']) ? sanitize_text_field(wp_unslash($_POST['amount_custom'])) : '',
            'frequency'       => isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : '',
            'email'           => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
            'cardholder_name' => isset($_POST['cardholder_name']) ? sanitize_text_field(wp_unslash($_POST['cardholder_name'])) : '',
            'payment_method'  => isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '',
            'card_number'     => isset($_POST['card_number']) ? sanitize_text_field(wp_unslash($_POST['card_number'])) : '',
            'exp_month'       => isset($_POST['exp_month']) ? sanitize_text_field(wp_unslash($_POST['exp_month'])) : '',
            'exp_year'        => isset($_POST['exp_year']) ? sanitize_text_field(wp_unslash($_POST['exp_year'])) : '',
            'cvc'             => isset($_POST['cvc']) ? sanitize_text_field(wp_unslash($_POST['cvc'])) : '',
            'country'         => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '',
            'cpf_cnpj'        => isset($_POST['cpf_cnpj']) ? sanitize_text_field(wp_unslash($_POST['cpf_cnpj'])) : '',
            'address_line1'   => isset($_POST['address_line1']) ? sanitize_text_field(wp_unslash($_POST['address_line1'])) : '',
            'address_line2'   => isset($_POST['address_line2']) ? sanitize_text_field(wp_unslash($_POST['address_line2'])) : '',
            'city'            => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
            'state'           => isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '',
            'postal_code'     => isset($_POST['postal_code']) ? sanitize_text_field(wp_unslash($_POST['postal_code'])) : '',
        ];

        $errors = self::validate_request($raw);
        if (!empty($errors)) {
            wp_send_json_error(['message' => implode(' ', $errors), 'fields' => $errors]);
        }

        $amount_cents = self::resolve_amount_cents($raw);
        if ($amount_cents <= 0) {
            wp_send_json_error(['message' => __('Valor da doação inválido.', 'donation-stripe')]);
        }

        $options = Donation_Stripe_Admin_Settings::get_options();
        $secret_key = self::get_secret_key($options);
        if ($secret_key === '') {
            wp_send_json_error(['message' => __('Stripe não configurado. Entre em contato com o administrador.', 'donation-stripe')]);
        }

        self::ensure_stripe_loaded();
        \Stripe\Stripe::setApiKey($secret_key);

        try {
            if ($raw['frequency'] === 'once') {
                $result = self::create_payment_intent($raw, $amount_cents, $options);
            } else {
                // Se for boleto recorrente, criar PaymentIntent único e registrar na tabela
                if ($raw['payment_method'] === 'boleto') {
                    $result = self::create_recurring_boleto($raw, $amount_cents, $options);
                } else {
                    $result = self::create_subscription($raw, $amount_cents, $options);
                }
            }
            wp_send_json_success($result);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Valida dados no backend (espelho das regras do frontend).
     *
     * @param array<string, string> $raw
     * @return array<int, string> Lista de mensagens de erro.
     */
    private static function validate_request(array $raw): array
    {
        $errors = [];
        if (!is_email($raw['email'])) {
            $errors[] = __('E-mail inválido.', 'donation-stripe');
        }
        if (empty($raw['cardholder_name'])) {
            $errors[] = __('Nome é obrigatório.', 'donation-stripe');
        }
        if ($raw['payment_method'] === 'boleto') {
            if (empty($raw['cpf_cnpj'])) $errors[] = __('CPF/CNPJ é obrigatório para Boleto.', 'donation-stripe');
            if (empty($raw['address_line1'])) $errors[] = __('Endereço é obrigatório para Boleto.', 'donation-stripe');
            if (empty($raw['city'])) $errors[] = __('Cidade é obrigatória para Boleto.', 'donation-stripe');
            if (empty($raw['postal_code'])) $errors[] = __('CEP é obrigatório para Boleto.', 'donation-stripe');
        }
        return $errors;
    }

    private static function resolve_amount_cents(array $raw): int
    {
        if ($raw['amount_type'] === 'other') {
            return (int) (floatval($raw['amount_custom']) * 100);
        }
        $val = (int) $raw['amount_type'];
        return $val > 0 ? $val * 100 : 0;
    }

    public static function get_secret_key(array $options): string
    {
        $mode = $options['stripe_mode'] ?? 'test';
        return $mode === 'live'
            ? (string) ($options['stripe_secret_key_live'] ?? '')
            : (string) ($options['stripe_secret_key_test'] ?? '');
    }

    public static function ensure_stripe_loaded(): void
    {
        if (!class_exists('\Stripe\Stripe')) {
            $path = plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    /**
     * Cria PaymentIntent para pagamento único (Cartão ou Boleto).
     *
     * @param array<string, string> $raw
     * @param array<string, mixed> $options
     * @return array{client_secret: string, type: string}
     */
    private static function create_payment_intent(array $raw, int $amount_cents, array $options): array
    {
        $currency = 'usd'; // Padrão
        
        // Se boleto, sempre BRL
        if ($raw['payment_method'] === 'boleto') {
             $currency = 'brl';
        } 
        // Se for cartão, verificar país
        elseif ($raw['country'] === 'BR') {
             $currency = 'brl';
             $rate = self::get_usd_to_brl_rate();
             if ($rate > 0) {
                 $amount_cents = (int) round($amount_cents * $rate);
             }
        }

        $payment_method_types = ['card'];
        $payment_method_options = [];

        if ($raw['payment_method'] === 'boleto') {
            if (empty($options['enable_boleto'])) {
                throw new InvalidArgumentException(__('Pagamento por boleto está desativado.', 'donation-stripe'));
            }
            // Boleto já setou currency acima
            $payment_method_types = ['boleto'];
            $payment_method_options = [
                'boleto' => [
                    'expires_after_days' => 3,
                ],
            ];
        } else {
             // Cartão: Se BR, já convertemos. Se não, mantemos USD.
        }

        $intent = \Stripe\PaymentIntent::create([
            'amount'   => $amount_cents,
            'currency' => $currency,
            'payment_method_types' => $payment_method_types,
            'payment_method_options' => $payment_method_options,
            'receipt_email' => $raw['email'],
            'metadata' => [
                'email' => $raw['email'],
                'cardholder_name' => $raw['cardholder_name'],
                'source' => 'donation_form',
                'recurring' => 'false',
            ],
        ]);

        return [
            'client_secret' => $intent->client_secret,
            'type'          => 'payment_intent',
            'payment_intent_id' => $intent->id, // Enviar ID para o frontend poder buscar o link do boleto
        ];
    }

    /**
     * Cria Subscription para pagamento recorrente (Cartão apenas).
     *
     * @param array<string, string> $raw
     * @param array<string, mixed> $options
     * @return array{client_secret: string, subscription_id: string, type: string}
     */
    private static function create_subscription(array $raw, int $amount_cents, array $options): array
    {
        if ($raw['payment_method'] === 'boleto') {
            // Isso não deve ser atingido se o JS estiver correto, mas por segurança:
            throw new InvalidArgumentException(__('Doação recorrente via assinatura nativa não suporta boleto neste fluxo.', 'donation-stripe'));
        }

        $frequency = $raw['frequency'];
        if ($frequency === 'monthly' && empty($options['enable_recurring_monthly'])) {
            throw new InvalidArgumentException(__('Doação mensal está desativada.', 'donation-stripe'));
        }
        if ($frequency === 'annual' && empty($options['enable_recurring_annual'])) {
            throw new InvalidArgumentException(__('Doação anual está desativada.', 'donation-stripe'));
        }

        $interval = $frequency === 'annual' ? 'year' : 'month';
        
        $currency = 'usd';
        if ($raw['country'] === 'BR') {
             $currency = 'brl';
             $rate = self::get_usd_to_brl_rate();
             if ($rate > 0) {
                 $amount_cents = (int) round($amount_cents * $rate);
             }
        }

        // Criar ou recuperar Produto e Preço
        $price = \Stripe\Price::create([
            'unit_amount' => $amount_cents,
            'currency'    => $currency,
            'recurring'   => ['interval' => $interval],
            'product_data' => [
                'name' => 'Doação ' . ($frequency === 'annual' ? 'Anual' : 'Mensal'),
            ],
        ]);

        // Criar Customer
        $customers = \Stripe\Customer::all(['email' => $raw['email'], 'limit' => 1]);
        if (!empty($customers->data)) {
            $customer = $customers->data[0];
        } else {
            $customer = \Stripe\Customer::create([
                'email' => $raw['email'],
                'name'  => $raw['cardholder_name'],
                'metadata' => ['source' => 'donation_form'],
            ]);
        }

        // Criar Subscription (status: incomplete, aguardando pagamento)
        // expand latest_invoice.payment_intent para obter client_secret
        $subscription = \Stripe\Subscription::create([
            'customer' => $customer->id,
            'items'    => [['price' => $price->id]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => ['source' => 'donation_form'],
        ]);

        $invoice = $subscription->latest_invoice;
        if (!$invoice) {
            throw new RuntimeException(__('Falha ao criar assinatura: fatura não encontrada.', 'donation-stripe'));
        }

        $invoice_id = is_string($invoice) ? $invoice : $invoice->id;
        $payment_intent = null;

        // Obter payment_intent: Stripe pode vinculá-lo à invoice de forma assíncrona
        $max_attempts = 10;
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            if ($attempt > 1) {
                usleep(1200000); // 1,2 s entre tentativas
            }
            $invoice = \Stripe\Invoice::retrieve($invoice_id, ['expand' => ['payment_intent']]);
            $payment_intent = $invoice->payment_intent ?? null;
            if ($payment_intent) {
                break;
            }
        }

        if (!$payment_intent) {
            // Fallback: tentar recuperar da assinatura
             $subscription = \Stripe\Subscription::retrieve(
                $subscription->id,
                ['expand' => ['latest_invoice.payment_intent']]
            );
            $latest = $subscription->latest_invoice;
            if (is_object($latest) && isset($latest->payment_intent)) {
                $payment_intent = $latest->payment_intent;
            }
        }

        if (!$payment_intent) {
            throw new RuntimeException(__('Falha em seu pagamento. Verifique os dados, e tente novamente.', 'donation-stripe'));
        }

        if (is_object($payment_intent) && !empty($payment_intent->client_secret)) {
            $client_secret = $payment_intent->client_secret;
        } else {
            $payment_intent_id = is_string($payment_intent) ? $payment_intent : (string) $payment_intent->id;
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            $client_secret = $intent->client_secret;
        }

        return [
            'client_secret'    => $client_secret,
            'subscription_id'  => $subscription->id,
            'type'             => 'subscription',
        ];
    }

    /**
     * Cria PaymentIntent único para boleto recorrente e registra na tabela para geração futura.
     *
     * @param array<string, string> $raw
     * @param array<string, mixed> $options
     * @return array{client_secret: string, type: string}
     */
    private static function create_recurring_boleto(array $raw, int $amount_cents, array $options): array
    {
        if (empty($options['enable_boleto'])) {
            throw new InvalidArgumentException(__('Pagamento por boleto está desativado.', 'donation-stripe'));
        }
        $frequency = $raw['frequency'];
        if ($frequency === 'monthly' && empty($options['enable_recurring_monthly'])) {
            throw new InvalidArgumentException(__('Doação mensal está desativada.', 'donation-stripe'));
        }
        if ($frequency === 'annual' && empty($options['enable_recurring_annual'])) {
            throw new InvalidArgumentException(__('Doação anual está desativada.', 'donation-stripe'));
        }

        // Converter USD -> BRL para boleto (assumindo que o form envia em USD)
        $rate = self::get_usd_to_brl_rate();
        if ($rate > 0) {
            $amount_cents = (int) round($amount_cents * $rate);
        }

        // Criar PaymentIntent único com boleto (BRL)
        $intent = \Stripe\PaymentIntent::create([
            'amount'   => $amount_cents,
            'currency' => 'brl',
            'payment_method_types' => ['boleto'],
            'payment_method_options' => [
                'boleto' => [
                    'expires_after_days' => 3,
                ],
            ],
            'receipt_email' => $raw['email'],
            'metadata' => [
                'email' => $raw['email'],
                'cardholder_name' => $raw['cardholder_name'],
                'source' => 'donation_form',
                'recurring' => 'true',
                'frequency' => $frequency,
            ],
        ]);

        // Registrar na tabela para geração futura
        global $wpdb;
        $table = $wpdb->prefix . 'donation_stripe_recurring_boleto';
        
        $inserted = $wpdb->insert($table, [
            'email'            => $raw['email'],
            'amount_cents'     => $amount_cents,
            'frequency'        => $frequency,
            'payment_intent_id' => $intent->id,
            'last_paid_at'     => null,
            'next_boleto_at'   => null,
            'active'           => 1,
        ]);

        if ($inserted === false) {
            error_log('[Donation Stripe] Erro ao inserir registro de boleto recorrente: ' . $wpdb->last_error);
        }

        return [
            'client_secret' => $intent->client_secret,
            'type'          => 'payment_intent',
            'payment_intent_id' => $intent->id, // Enviar ID para o frontend poder buscar o link do boleto
        ];
    }

    /**
     * Handler AJAX para enviar e-mail com link do boleto após confirmação.
     */
    public static function send_boleto_email_ajax(): void
    {
        if (!check_ajax_referer('donation_stripe_submit', 'nonce', false)) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'donation-stripe')], 403);
        }

        $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field(wp_unslash($_POST['payment_intent_id'])) : '';
        if (empty($payment_intent_id)) {
            wp_send_json_error(['message' => __('ID do pagamento não fornecido.', 'donation-stripe')]);
        }

        $options = Donation_Stripe_Admin_Settings::get_options();
        $secret_key = self::get_secret_key($options);
        if ($secret_key === '') {
            wp_send_json_error(['message' => __('Stripe não configurado.', 'donation-stripe')]);
        }

        self::ensure_stripe_loaded();
        \Stripe\Stripe::setApiKey($secret_key);

        try {
            // Tentar buscar o link do boleto (pode demorar alguns segundos após confirmação)
            $boleto_url = '';
            $max_attempts = 10;
            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                if ($attempt > 1) {
                    usleep(1000000); // 1 segundo entre tentativas
                }
                $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id, ['expand' => ['payment_method']]);
                
                // Buscar link do boleto no payment_method
                if (isset($intent->payment_method)) {
                    $pm = is_string($intent->payment_method)
                        ? \Stripe\PaymentMethod::retrieve($intent->payment_method)
                        : $intent->payment_method;
                    if (isset($pm->boleto) && isset($pm->boleto->hosted_voucher_url)) {
                        $boleto_url = $pm->boleto->hosted_voucher_url;
                        break;
                    }
                }
                
                // Tentar buscar do next_action (Stripe às vezes coloca aqui)
                if (isset($intent->next_action) && isset($intent->next_action->boleto_display_details)) {
                     $boleto_url = $intent->next_action->boleto_display_details->hosted_voucher_url;
                     break;
                }
                
                // Tentar buscar de charges
                if (empty($boleto_url) && isset($intent->charges) && !empty($intent->charges->data)) {
                    foreach ($intent->charges->data as $charge) {
                        if (isset($charge->payment_method_details->boleto->hosted_voucher_url)) {
                            $boleto_url = $charge->payment_method_details->boleto->hosted_voucher_url;
                            break 2;
                        }
                    }
                }
            }

            if (empty($boleto_url)) {
                error_log('[Donation Stripe] Link do boleto não encontrado para PI: ' . $payment_intent_id);
                wp_send_json_error(['message' => __('Link do boleto não disponível ainda. Verifique seu e-mail ou acesse o Stripe Dashboard.', 'donation-stripe')]);
            }

            // Enviar e-mail via SMTP
            $email = $intent->receipt_email ?? $intent->metadata->email ?? '';
            if (empty($email)) {
                wp_send_json_error(['message' => __('E-mail não encontrado.', 'donation-stripe')]);
            }

            self::send_boleto_email_via_smtp($email, $boleto_url, $options);
            wp_send_json_success(['message' => __('E-mail enviado com sucesso.', 'donation-stripe')]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('[Donation Stripe] Erro Stripe ao buscar boleto: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('[Donation Stripe] Erro ao enviar email boleto: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Envia e-mail com link do boleto via SMTP.
     */
    private static function send_boleto_email_via_smtp(string $email, string $boleto_url, array $options): void
    {
        if (empty($options['enable_invoice_email'])) {
            error_log('[Donation Stripe] Envio de boleto cancelado: opção enable_invoice_email desativada.');
            return;
        }

        $host = isset($options['smtp_host']) ? trim((string) $options['smtp_host']) : '';
        $from_addr = isset($options['email_from_address']) ? trim((string) $options['email_from_address']) : '';
        if ($host === '' || $from_addr === '' || !is_email($from_addr)) {
            error_log('[Donation Stripe] Envio de boleto cancelado: SMTP não configurado.');
            return;
        }

        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Seu boleto de doação', 'donation-stripe'),
            get_bloginfo('name')
        );
        $message = __('Olá!', 'donation-stripe') . "\n\n";
        $message .= __('Segue o link para visualizar e pagar seu boleto:', 'donation-stripe') . "\n\n";
        $message .= $boleto_url . "\n\n";
        $message .= __('Obrigado por sua doação!', 'donation-stripe') . "\n\n";
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
            $sent = wp_mail($email, $subject, $message, $headers);
            remove_filter('phpmailer_init', $callback, 999);
            
            if (!$sent && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Donation Stripe] wp_mail retornou false ao enviar boleto.');
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Donation Stripe] Boleto enviado com sucesso para: ' . $email);
                }
            }
        } catch (\Throwable $e) {
            remove_filter('phpmailer_init', $callback, 999);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Donation Stripe] Falha ao enviar e-mail do boleto: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Obtém a taxa de câmbio USD -> BRL via API (com cache de 12h).
     */
    private static function get_usd_to_brl_rate(): float
    {
        // Tentar obter do cache primeiro
        $rate = get_transient('donation_stripe_usd_brl_rate');
        if ($rate !== false) {
            return (float) $rate;
        }

        $api_url = 'https://apilayer.net/api/live?access_key=62ca3cf68944366d307345765e520ecd&currencies=BRL&source=USD&format=1';
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            error_log('[Donation Stripe] Erro ao consultar API de câmbio: ' . $response->get_error_message());
            return 5.0; // Fallback seguro
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['success']) && $data['success'] && isset($data['quotes']['USDBRL'])) {
            $rate = (float) $data['quotes']['USDBRL'];
            // Salvar no cache por 12 horas
            set_transient('donation_stripe_usd_brl_rate', $rate, 12 * HOUR_IN_SECONDS);
            return $rate;
        }

        error_log('[Donation Stripe] Falha na resposta da API de câmbio: ' . print_r($body, true));
        return 5.0; // Fallback seguro
    }
}
