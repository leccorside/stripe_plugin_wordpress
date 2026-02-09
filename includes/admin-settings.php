<?php
/**
 * Página de configurações administrativas do plugin Doação Stripe.
 * Usa WordPress Settings API, nonce, sanitização e validação.
 *
 * @package Donation_Stripe
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Donation_Stripe_Admin_Settings
{
    private const OPTION_GROUP = 'donation_stripe_settings';
    private const OPTION_NAME = 'donation_stripe_options';
    private const PAGE_SLUG = 'donation-stripe-settings';
    private const CAPABILITY = 'manage_options';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_donation_stripe_update_statuses', [__CLASS__, 'ajax_update_statuses']);
        add_action('wp_ajax_donation_stripe_fetch_donations', [__CLASS__, 'ajax_fetch_donations']);
    }

    /**
     * Adiciona menu "Doações" > "Configurações".
     */
    public static function add_menu(): void
    {
        add_menu_page(
            __('Doações', 'donation-stripe'),
            __('Doações', 'donation-stripe'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-heart',
            30
        );
        add_submenu_page(
            self::PAGE_SLUG,
            __('Configurações', 'donation-stripe'),
            __('Configurações', 'donation-stripe'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Registra configurações via Settings API.
     */
    public static function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_options'],
            ]
        );

        $section_general = 'donation_stripe_section_keys';
        add_settings_section(
            $section_general,
            __('Chaves da API Stripe', 'donation-stripe'),
            [__CLASS__, 'section_keys_callback'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'stripe_mode',
            __('Modo de Operação', 'donation-stripe'),
            [__CLASS__, 'field_select_callback'],
            self::PAGE_SLUG,
            $section_general,
            [
                'label_for' => 'stripe_mode',
                'name' => 'stripe_mode',
                'options' => [
                    'test' => __('Teste', 'donation-stripe'),
                    'live' => __('Produção (Live)', 'donation-stripe'),
                ],
                'description' => __('Selecione "Teste" para desenvolvimento e "Produção" para aceitar pagamentos reais.', 'donation-stripe'),
            ]
        );

        add_settings_field(
            'stripe_publishable_key_test',
            __('Chave Publicável (Teste)', 'donation-stripe'),
            [__CLASS__, 'field_password_callback'],
            self::PAGE_SLUG,
            $section_general,
            ['label_for' => 'stripe_publishable_key_test', 'name' => 'stripe_publishable_key_test']
        );

        add_settings_field(
            'stripe_secret_key_test',
            __('Chave Secreta (Teste)', 'donation-stripe'),
            [__CLASS__, 'field_password_callback'],
            self::PAGE_SLUG,
            $section_general,
            ['label_for' => 'stripe_secret_key_test', 'name' => 'stripe_secret_key_test']
        );

        add_settings_field(
            'webhook_secret_test',
            __('Segredo do Webhook (Teste)', 'donation-stripe'),
            [__CLASS__, 'field_password_callback'],
            self::PAGE_SLUG,
            $section_general,
            ['label_for' => 'webhook_secret_test', 'name' => 'webhook_secret_test']
        );

        add_settings_field(
            'stripe_publishable_key_live',
            __('Chave Publicável (Produção)', 'donation-stripe'),
            [__CLASS__, 'field_password_callback'],
            self::PAGE_SLUG,
            $section_general,
            ['label_for' => 'stripe_publishable_key_live', 'name' => 'stripe_publishable_key_live']
        );

        add_settings_field(
            'stripe_secret_key_live',
            __('Chave Secreta (Produção)', 'donation-stripe'),
            [__CLASS__, 'field_password_callback'],
            self::PAGE_SLUG,
            $section_general,
            ['label_for' => 'stripe_secret_key_live', 'name' => 'stripe_secret_key_live']
        );

        add_settings_field(
            'webhook_secret_live',
            __('Segredo do Webhook (Produção)', 'donation-stripe'),
            [__CLASS__, 'field_password_callback'],
            self::PAGE_SLUG,
            $section_general,
            ['label_for' => 'webhook_secret_live', 'name' => 'webhook_secret_live']
        );

        // Seção Pagamento
        $section_payment = 'donation_stripe_section_payment';
        add_settings_section(
            $section_payment,
            __('Opções de Pagamento', 'donation-stripe'),
            null,
            self::PAGE_SLUG
        );

        add_settings_field(
            'enable_boleto',
            __('Habilitar Boleto?', 'donation-stripe'),
            [__CLASS__, 'field_checkbox_callback'],
            self::PAGE_SLUG,
            $section_payment,
            [
                'label_for' => 'enable_boleto',
                'name' => 'enable_boleto',
                'description' => __('Permitir doações via Boleto Bancário.', 'donation-stripe'),
            ]
        );

        add_settings_field(
            'enable_recurring_monthly',
            __('Habilitar Doação Mensal?', 'donation-stripe'),
            [__CLASS__, 'field_checkbox_callback'],
            self::PAGE_SLUG,
            $section_payment,
            [
                'label_for' => 'enable_recurring_monthly',
                'name' => 'enable_recurring_monthly',
                'description' => __('Permitir que o usuário escolha "Mensalmente".', 'donation-stripe'),
            ]
        );

        add_settings_field(
            'enable_recurring_annual',
            __('Habilitar Doação Anual?', 'donation-stripe'),
            [__CLASS__, 'field_checkbox_callback'],
            self::PAGE_SLUG,
            $section_payment,
            [
                'label_for' => 'enable_recurring_annual',
                'name' => 'enable_recurring_annual',
                'description' => __('Permitir que o usuário escolha "Anualmente".', 'donation-stripe'),
            ]
        );

        add_settings_field(
            'thank_you_page_url',
            __('URL da Página de Obrigado', 'donation-stripe'),
            [__CLASS__, 'field_text_callback'],
            self::PAGE_SLUG,
            $section_payment,
            [
                'label_for' => 'thank_you_page_url',
                'name' => 'thank_you_page_url',
                'description' => __('URL para redirecionamento após sucesso. Ex: https://seusite.com/obrigado/', 'donation-stripe'),
            ]
        );

        // Seção E-mail (SMTP)
        $section_email = 'donation_stripe_section_email';
        add_settings_section(
            $section_email,
            __('Configurações de E-mail (SMTP)', 'donation-stripe'),
            [__CLASS__, 'section_email_callback'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'enable_invoice_email',
            __('Enviar E-mail de Fatura/Recibo?', 'donation-stripe'),
            [__CLASS__, 'field_checkbox_callback'],
            self::PAGE_SLUG,
            $section_email,
            [
                'label_for' => 'enable_invoice_email',
                'name' => 'enable_invoice_email',
                'description' => __('Ativar envio de e-mails de invoice/recibo via SMTP configurado abaixo.', 'donation-stripe'),
            ]
        );

        add_settings_field(
            'smtp_host',
            __('SMTP Host', 'donation-stripe'),
            [__CLASS__, 'field_text_callback'],
            self::PAGE_SLUG,
            $section_email,
            ['label_for' => 'smtp_host', 'name' => 'smtp_host']
        );

        add_settings_field(
            'smtp_port',
            __('SMTP Port', 'donation-stripe'),
            [__CLASS__, 'field_text_callback'],
            self::PAGE_SLUG,
            $section_email,
            ['label_for' => 'smtp_port', 'name' => 'smtp_port', 'description' => 'Ex: 587 ou 465']
        );

        add_settings_field(
            'smtp_encryption',
            __('Criptografia', 'donation-stripe'),
            [__CLASS__, 'field_select_callback'],
            self::PAGE_SLUG,
            $section_email,
            [
                'label_for' => 'smtp_encryption',
                'name' => 'smtp_encryption',
                'options' => [
                    'tls' => 'TLS',
                    'ssl' => 'SSL',
                    'none' => 'Nenhuma',
                ]
            ]
        );

        add_settings_field(
            'smtp_user',
            __('Usuário SMTP', 'donation-stripe'),
            [__CLASS__, 'field_text_callback'],
            self::PAGE_SLUG,
            $section_email,
            ['label_for' => 'smtp_user', 'name' => 'smtp_user']
        );

        add_settings_field(
            'smtp_password',
            __('Senha SMTP', 'donation-stripe'),
            [__CLASS__, 'field_password_callback'],
            self::PAGE_SLUG,
            $section_email,
            ['label_for' => 'smtp_password', 'name' => 'smtp_password']
        );

        add_settings_field(
            'email_from_address',
            __('E-mail do Remetente', 'donation-stripe'),
            [__CLASS__, 'field_text_callback'],
            self::PAGE_SLUG,
            $section_email,
            ['label_for' => 'email_from_address', 'name' => 'email_from_address']
        );

        add_settings_field(
            'email_from_name',
            __('Nome do Remetente', 'donation-stripe'),
            [__CLASS__, 'field_text_callback'],
            self::PAGE_SLUG,
            $section_email,
            ['label_for' => 'email_from_name', 'name' => 'email_from_name']
        );
    }

    public static function sanitize_options(array $input): array
    {
        $output = [];
        $fields = [
            'stripe_mode',
            'stripe_publishable_key_test', 'stripe_secret_key_test', 'webhook_secret_test',
            'stripe_publishable_key_live', 'stripe_secret_key_live', 'webhook_secret_live',
            'enable_boleto', 'enable_recurring_monthly', 'enable_recurring_annual',
            'thank_you_page_url',
            'enable_invoice_email', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_password',
            'email_from_address', 'email_from_name'
        ];

        foreach ($fields as $field) {
            if (isset($input[$field])) {
                if ($field === 'enable_boleto' || $field === 'enable_recurring_monthly' || $field === 'enable_recurring_annual' || $field === 'enable_invoice_email') {
                    $output[$field] = 1;
                } else {
                    $output[$field] = sanitize_text_field($input[$field]);
                }
            }
        }
        return $output;
    }

    public static function section_keys_callback(): void
    {
        echo '<p>' . esc_html__('Insira as chaves da API Stripe. Obtenha no Dashboard do Stripe.', 'donation-stripe') . '</p>';
    }

    public static function section_email_callback(): void
    {
        echo '<p>' . esc_html__('Configure o servidor SMTP para envio de faturas e recibos.', 'donation-stripe') . '</p>';
    }

    public static function field_text_callback(array $args): void
    {
        $options = self::get_options();
        $val = isset($options[$args['name']]) ? $options[$args['name']] : '';
        echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($args['name']) . ']" value="' . esc_attr($val) . '" class="regular-text" />';
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public static function field_password_callback(array $args): void
    {
        $options = self::get_options();
        $val = isset($options[$args['name']]) ? $options[$args['name']] : '';
        echo '<input type="password" id="' . esc_attr($args['label_for']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($args['name']) . ']" value="' . esc_attr($val) . '" class="regular-text" />';
    }

    public static function field_select_callback(array $args): void
    {
        $options = self::get_options();
        $val = isset($options[$args['name']]) ? $options[$args['name']] : '';
        echo '<select id="' . esc_attr($args['label_for']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($args['name']) . ']">';
        foreach ($args['options'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($val, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public static function field_checkbox_callback(array $args): void
    {
        $options = self::get_options();
        $checked = isset($options[$args['name']]) && $options[$args['name']] == 1;
        
        echo '<label>';
        echo '<input type="checkbox" id="' . esc_attr($args['label_for']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($args['name']) . ']" value="1" ' . checked($checked, true, false) . ' /> ';
        if (!empty($args['description'])) {
            echo '<span class="description" style="vertical-align: middle; margin-left: 5px;">' . esc_html($args['description']) . '</span>';
        }
        echo '</label>';
    }

    public static function get_options(): array
    {
        return (array) get_option(self::OPTION_NAME, []);
    }

    /**
     * Renderiza a página de configurações.
     */
    public static function render_page(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'stripe';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configurações Doação Stripe', 'donation-stripe'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr(self::PAGE_SLUG); ?>&tab=stripe" class="nav-tab <?php echo $active_tab === 'stripe' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Chaves Stripe', 'donation-stripe'); ?>
                </a>
                <a href="?page=<?php echo esc_attr(self::PAGE_SLUG); ?>&tab=smtp" class="nav-tab <?php echo $active_tab === 'smtp' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Cliente (SMTP)', 'donation-stripe'); ?>
                </a>
                <a href="?page=<?php echo esc_attr(self::PAGE_SLUG); ?>&tab=status" class="nav-tab <?php echo $active_tab === 'status' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Status de Doações', 'donation-stripe'); ?>
                </a>
            </nav>

            <div class="donation-stripe-tab-content">
                <?php
                if ($active_tab === 'stripe') {
                    self::render_stripe_tab();
                } elseif ($active_tab === 'smtp') {
                    self::render_smtp_tab();
                } elseif ($active_tab === 'status') {
                    self::render_status_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private static function render_stripe_tab(): void
    {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields(self::OPTION_GROUP);
            // Renderizar apenas seções relacionadas a chaves e pagamentos
            do_settings_sections(self::PAGE_SLUG);
            
            // Hack para ocultar seções que não são desta tab (WP Settings API não suporta tabs nativamente fácil)
            // Uma solução melhor seria registrar settings separadamente, mas para simplificar:
            // Vamos renderizar tudo e o CSS/JS oculta? Não, o do_settings_sections imprime tudo.
            // Solução: registrar sections com prefixo ou filtrar no callback?
            // Como registramos todas as sections na mesma page slug, elas aparecem juntas.
            // Para separar, teríamos que usar page slugs diferentes ou callbacks condicionais.
            // Vamos assumir que render_stripe_tab deve mostrar chaves e pagamentos, e ocultar SMTP.
            // Mas do_settings_sections renderiza TODAS.
            // Vamos ajustar o register_settings para adicionar sections apenas quando necessário? Não, init roda antes.
            // Vamos ocultar via CSS as sections indesejadas?
            ?>
            <style>
                /* Ocultar seção SMTP nesta aba */
                h2:nth-of-type(3), table:nth-of-type(3) { display: none; }
            </style>
            <?php
            submit_button(__('Salvar alterações', 'donation-stripe'));
            ?>
        </form>
        <?php
    }

    private static function render_smtp_tab(): void
    {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields(self::OPTION_GROUP);
            do_settings_sections(self::PAGE_SLUG);
            ?>
            <style>
                /* Ocultar seções Stripe e Pagamento nesta aba */
                h2:nth-of-type(1), table:nth-of-type(1),
                h2:nth-of-type(2), table:nth-of-type(2) { display: none; }
            </style>
            <?php
            submit_button(__('Salvar alterações', 'donation-stripe'));
            ?>
        </form>
        <?php
    }

    /**
     * Renderiza aba Status de Doações.
     */
    private static function render_status_tab(): void
    {
        $search = isset($_GET['donation_search']) ? sanitize_text_field(wp_unslash($_GET['donation_search'])) : '';
        $page = isset($_GET['donation_page']) ? max(1, (int) $_GET['donation_page']) : 1;
        
        // URL base para fallback sem JS
        $base_url = admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=status');
        ?>
        <div class="donation-stripe-status-table-wrap">
            <h2><?php esc_html_e('Status das Doações', 'donation-stripe'); ?></h2>
            
            <!-- Campo de busca -->
            <div class="donation-stripe-search-wrap" style="margin: 20px 0;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" id="donation-stripe-search-form">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />
                    <input type="hidden" name="tab" value="status" />
                    <label for="donation_search" class="screen-reader-text"><?php esc_html_e('Buscar doações', 'donation-stripe'); ?></label>
                    <input type="search" 
                           id="donation_search" 
                           name="donation_search" 
                           value="<?php echo esc_attr($search); ?>" 
                           placeholder="<?php esc_attr_e('Buscar por nome, e-mail ou valor...', 'donation-stripe'); ?>"
                           style="width: 300px; padding: 0px 10px;" />
                    <button type="submit" class="button"><?php esc_html_e('Buscar', 'donation-stripe'); ?></button>
                    <button type="button" id="donation-stripe-clear-search" class="button" style="<?php echo $search === '' ? 'display:none;' : ''; ?>"><?php esc_html_e('Limpar', 'donation-stripe'); ?></button>
                </form>
            </div>
            
            <div id="donation-stripe-results-container">
                <?php self::render_donations_table_content($page, $search); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Endpoint AJAX para buscar doações (busca e paginação).
     */
    public static function ajax_fetch_donations(): void
    {
        check_ajax_referer('donation_stripe_admin', 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Sem permissão.', 'donation-stripe')], 403);
            return;
        }

        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        ob_start();
        self::render_donations_table_content($page, $search);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Renderiza o conteúdo da tabela de doações e paginação.
     *
     * @param int $page Página atual
     * @param string $search Termo de busca
     */
    private static function render_donations_table_content(int $page, string $search): void
    {
        $per_page = 20;
        $all_donations = self::get_donations_status($search);
        $total = count($all_donations);
        $total_pages = (int) ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $donations = array_slice($all_donations, $offset, $per_page);
        
        // URL base para links (útil se o JS falhar ou para atributos href)
        $base_url = admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=status');
        if ($search !== '') {
            $base_url = add_query_arg('donation_search', urlencode($search), $base_url);
        }

        if (empty($donations)) {
            echo '<p>' . esc_html__('Nenhuma doação encontrada.', 'donation-stripe') . '</p>';
            return;
        }
        ?>
        
        <p style="margin-bottom: 10px;">
            <?php
            $start = $offset + 1;
            $end = min($offset + $per_page, $total);
            printf(
                /* translators: %1$d: início, %2$d: fim, %3$d: total */
                esc_html__('Mostrando %1$d-%2$d de %3$d doações', 'donation-stripe'),
                $start,
                $end,
                $total
            );
            ?>
        </p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Nome', 'donation-stripe'); ?></th>
                    <th><?php esc_html_e('E-mail', 'donation-stripe'); ?></th>
                    <th><?php esc_html_e('Valor', 'donation-stripe'); ?></th>
                    <th><?php esc_html_e('Recorrência', 'donation-stripe'); ?></th>
                    <th><?php esc_html_e('Status', 'donation-stripe'); ?></th>
                    <th><?php esc_html_e('Data', 'donation-stripe'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($donations as $donation): ?>
                    <tr>
                        <td><?php echo esc_html($donation['name'] ?: '—'); ?></td>
                        <td><?php echo esc_html($donation['email']); ?></td>
                        <td>
                            <?php
                            $currency_symbol = $donation['currency'] === 'BRL' ? 'R$' : '$';
                            echo esc_html($currency_symbol . ' ' . number_format($donation['amount'], 2, ',', '.'));
                            ?>
                        </td>
                        <td><?php echo esc_html($donation['frequency']); ?></td>
                        <td>
                            <span class="donation-status donation-status-<?php echo esc_attr(strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $donation['status']))); ?>" 
                                  data-payment-intent-id="<?php echo esc_attr($donation['payment_intent_id'] ?? ''); ?>">
                                <?php echo esc_html($donation['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $date = $donation['created_at'] ?? '';
                            if ($date) {
                                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date)));
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages donation-stripe-pagination" style="float: right; margin: 10px 0;">
                    <?php
                    // Primeira página
                    if ($page > 1) {
                        echo '<a class="button" href="' . esc_url(add_query_arg('donation_page', 1, $base_url)) . '" data-page="1">&laquo;</a> ';
                        echo '<a class="button" href="' . esc_url(add_query_arg('donation_page', $page - 1, $base_url)) . '" data-page="' . ($page - 1) . '">&lsaquo;</a> ';
                    }
                    
                    // Páginas numeradas
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a class="button" href="' . esc_url(add_query_arg('donation_page', 1, $base_url)) . '" data-page="1">1</a> ';
                        if ($start_page > 2) {
                            echo '<span class="button disabled">...</span> ';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i === $page) {
                            echo '<span class="button button-primary" style="margin-left: 2px;">' . esc_html($i) . '</span> ';
                        } else {
                            echo '<a class="button" href="' . esc_url(add_query_arg('donation_page', $i, $base_url)) . '" data-page="' . $i . '" style="margin-left: 2px;">' . esc_html($i) . '</a> ';
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="button disabled">...</span> ';
                        }
                        echo '<a class="button" href="' . esc_url(add_query_arg('donation_page', $total_pages, $base_url)) . '" data-page="' . $total_pages . '">' . esc_html($total_pages) . '</a> ';
                    }
                    
                    // Última página
                    if ($page < $total_pages) {
                        echo '<a class="button" href="' . esc_url(add_query_arg('donation_page', $page + 1, $base_url)) . '" data-page="' . ($page + 1) . '">&rsaquo;</a> ';
                        echo '<a class="button" href="' . esc_url(add_query_arg('donation_page', $total_pages, $base_url)) . '" data-page="' . $total_pages . '">&raquo;</a>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    public static function enqueue_assets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }
        wp_enqueue_style('donation-stripe-admin', DONATION_STRIPE_PLUGIN_URL . 'assets/css/admin.css', [], DONATION_STRIPE_VERSION);
        wp_enqueue_script('donation-stripe-admin', DONATION_STRIPE_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], DONATION_STRIPE_VERSION, true);
        
        // Localizar script com dados para AJAX
        wp_localize_script('donation-stripe-admin', 'donationStripeAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('donation_stripe_admin'),
        ]);
    }

    /**
     * Busca doações da tabela de boletos recorrentes e eventos Stripe.
     *
     * @param string $search Termo de busca (opcional)
     * @return array<int, array<string, mixed>>
     */
    public static function get_donations_status(string $search = ''): array
    {
        global $wpdb;
        $donations = [];

        $options = self::get_options();
        $is_live = ($options['stripe_mode'] ?? 'test') === 'live';

        // Buscar boletos recorrentes
        $table_recurring = $wpdb->prefix . 'donation_stripe_recurring_boleto';
        // Aumentamos o limite para tentar capturar registros do modo atual mesmo misturado
        $recurring = $wpdb->get_results(
            "SELECT * FROM $table_recurring ORDER BY created_at DESC LIMIT 200",
            ARRAY_A
        );

        // Buscar eventos de webhook
        $table_events = $wpdb->prefix . 'donation_stripe_events';
        $events = $wpdb->get_results(
            "SELECT * FROM $table_events WHERE event_type IN ('payment_intent.succeeded', 'invoice.payment_succeeded', 'payment_intent.payment_failed', 'payment_intent.requires_action') ORDER BY processed_at DESC LIMIT 200",
            ARRAY_A
        );

        // Processar boletos recorrentes
        foreach ($recurring as $donation) {
            $payment_intent_id = $donation['payment_intent_id'] ?? '';
            
            // Verifica status no Stripe com a chave atual (Live ou Test)
            // Se o ID não existir no modo atual, retornará null e pulamos este registro
            $status_data = self::get_payment_status_from_stripe($payment_intent_id);
            if ($status_data === null) {
                continue;
            }

            $status = is_array($status_data) ? $status_data['status'] : $status_data;
            $name = is_array($status_data) && isset($status_data['name']) ? $status_data['name'] : '';
            
            $frequency = $donation['frequency'] === 'monthly' ? __('Mensal', 'donation-stripe') : __('Anual', 'donation-stripe');
            $frequency_display = sprintf(__('%s (Boleto)', 'donation-stripe'), $frequency);
            
            $donations[] = [
                'type' => 'recurring_boleto',
                'email' => $donation['email'] ?? '',
                'name' => $name,
                'amount' => (int) ($donation['amount_cents'] ?? 0) / 100,
                'currency' => 'BRL',
                'frequency' => $frequency_display,
                'status' => $status,
                'payment_intent_id' => $payment_intent_id,
                'created_at' => $donation['created_at'] ?? '',
                'last_paid_at' => $donation['last_paid_at'] ?? null,
                'next_boleto_at' => $donation['next_boleto_at'] ?? null,
                'active' => !empty($donation['active']),
            ];
        }

        // Processar eventos de pagamento
        foreach ($events as $event) {
            $payload = json_decode($event['payload'] ?? '{}', true);
            if (empty($payload['data']['object'])) {
                continue;
            }

            // Filtro de Modo (Test vs Live)
            // O objeto Stripe (Event ou PaymentIntent) possui propriedade livemode
            // Geralmente no root do evento ou dentro do object
            $event_livemode = isset($payload['livemode']) ? (bool) $payload['livemode'] : null;
            if ($event_livemode === null && isset($payload['data']['object']['livemode'])) {
                $event_livemode = (bool) $payload['data']['object']['livemode'];
            }
            
            // Se conseguimos identificar o modo e ele difere do modo atual, ignorar
            if ($event_livemode !== null && $event_livemode !== $is_live) {
                continue;
            }

            $object = $payload['data']['object'];
            $event_type = $event['event_type'] ?? '';

            if ($event_type === 'payment_intent.succeeded' || $event_type === 'payment_intent.payment_failed' || $event_type === 'payment_intent.requires_action') {
                $amount = isset($object['amount']) ? (int) $object['amount'] / 100 : 0;
                $currency = strtoupper($object['currency'] ?? 'usd');
                $metadata = $object['metadata'] ?? [];
                $is_recurring = isset($metadata['recurring']) && $metadata['recurring'] === 'true';
                
                // Identificar método de pagamento
                $payment_method_types = $object['payment_method_types'] ?? [];
                $is_boleto = in_array('boleto', $payment_method_types, true);
                $payment_method = $is_boleto ? __('Boleto', 'donation-stripe') : __('Cartão', 'donation-stripe');

                // Evitar duplicatas (se já está na lista de boletos recorrentes ou já foi processado)
                $payment_intent_id = trim($object['id'] ?? '');
                $exists = false;
                foreach ($donations as $don) {
                    if (trim($don['payment_intent_id']) === $payment_intent_id) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) {
                    continue;
                }

                // Buscar status atualizado do Stripe (em tempo real)
                $status_data = self::get_payment_status_from_stripe($payment_intent_id);
                $status = is_array($status_data) ? $status_data['status'] : $status_data;
                $name_from_stripe = is_array($status_data) && isset($status_data['name']) ? $status_data['name'] : '';

                // Montar texto de frequência com método
                if ($is_recurring) {
                    $freq_text = $metadata['frequency'] === 'annual' ? __('Anual', 'donation-stripe') : __('Mensal', 'donation-stripe');
                    $frequency_display = sprintf(__('%s (%s)', 'donation-stripe'), $freq_text, $payment_method);
                } else {
                    $frequency_display = sprintf(__('Única vez (%s)', 'donation-stripe'), $payment_method);
                }

                $donations[] = [
                    'type' => $is_recurring ? ($is_boleto ? 'recurring_boleto' : 'recurring_card') : ($is_boleto ? 'one_time_boleto' : 'one_time_card'),
                    'email' => $object['receipt_email'] ?? $metadata['email'] ?? '',
                    'name' => $name_from_stripe ?: ($metadata['cardholder_name'] ?? ''),
                    'amount' => $amount,
                    'currency' => $currency,
                    'frequency' => $frequency_display,
                    'status' => $status,
                    'payment_intent_id' => $payment_intent_id,
                    'created_at' => isset($object['created']) ? date('Y-m-d H:i:s', $object['created']) : ($event['processed_at'] ?? ''),
                    'last_paid_at' => $event['processed_at'] ?? null,
                    'next_boleto_at' => null,
                    'active' => true,
                ];
            } elseif ($event_type === 'invoice.payment_succeeded') {
                $amount = isset($object['amount_paid']) ? (int) $object['amount_paid'] / 100 : 0;
                $currency = strtoupper($object['currency'] ?? 'usd');
                $customer_email = $object['customer_email'] ?? '';
                $metadata = $object['metadata'] ?? [];
                $customer_name = $metadata['cardholder_name'] ?? '';
                
                // Invoice geralmente é de assinatura (cartão)
                // Tentar identificar frequência do subscription
                $frequency_text = __('Mensal', 'donation-stripe'); // padrão
                if (isset($object['subscription'])) {
                    $subscription_id = is_string($object['subscription']) ? $object['subscription'] : ($object['subscription']['id'] ?? '');
                    if ($subscription_id) {
                        try {
                            $options = self::get_options();
                            $secret_key = self::get_secret_key_for_stripe($options);
                            if ($secret_key !== '') {
                                Donation_Stripe_Stripe_Handler::ensure_stripe_loaded();
                                \Stripe\Stripe::setApiKey($secret_key);
                                $subscription = \Stripe\Subscription::retrieve($subscription_id);
                                $interval = $subscription->items->data[0]->price->recurring->interval ?? 'month';
                                $frequency_text = $interval === 'year' ? __('Anual', 'donation-stripe') : __('Mensal', 'donation-stripe');
                            }
                        } catch (\Throwable $e) {
                            // Se falhar, usar padrão mensal
                        }
                    }
                }
                $frequency_display = sprintf(__('%s (Cartão)', 'donation-stripe'), $frequency_text);

                // Buscar status atualizado do PaymentIntent do Stripe (em tempo real)
                $payment_intent_id = trim($object['payment_intent'] ?? '');
                $exists = false;
                foreach ($donations as $don) {
                    if (trim($don['payment_intent_id']) === $payment_intent_id) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) {
                    continue;
                }

                $status = __('Pago', 'donation-stripe'); // padrão para invoice.payment_succeeded
                if ($payment_intent_id) {
                    $status_data = self::get_payment_status_from_stripe($payment_intent_id);
                    $status = is_array($status_data) ? $status_data['status'] : $status_data;
                    if (is_array($status_data) && isset($status_data['name']) && empty($customer_name)) {
                        $customer_name = $status_data['name'];
                    }
                }

                $donations[] = [
                    'type' => 'subscription',
                    'email' => $customer_email,
                    'name' => $customer_name,
                    'amount' => $amount,
                    'currency' => $currency,
                    'frequency' => $frequency_display,
                    'status' => $status,
                    'payment_intent_id' => $payment_intent_id,
                    'created_at' => isset($object['created']) ? date('Y-m-d H:i:s', $object['created']) : ($event['processed_at'] ?? ''),
                    'last_paid_at' => $event['processed_at'] ?? null,
                    'next_boleto_at' => null,
                    'active' => true,
                ];
            }
        }

        // Ordenar por data de criação (mais recente primeiro)
        usort($donations, static function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        // Aplicar filtro de busca se fornecido
        if ($search !== '') {
            $search_lower = mb_strtolower($search, 'UTF-8');
            $donations = array_filter($donations, static function ($donation) use ($search_lower) {
                $name = mb_strtolower($donation['name'] ?? '', 'UTF-8');
                $email = mb_strtolower($donation['email'] ?? '', 'UTF-8');
                $amount = (string) $donation['amount'];
                $frequency = mb_strtolower($donation['frequency'] ?? '', 'UTF-8');
                
                return (
                    strpos($name, $search_lower) !== false ||
                    strpos($email, $search_lower) !== false ||
                    strpos($amount, $search_lower) !== false ||
                    strpos($frequency, $search_lower) !== false
                );
            });
            // Reindexar array após filter
            $donations = array_values($donations);
        }

        return $donations;
    }

    /**
     * Endpoint AJAX para atualizar status dos pagamentos em tempo real.
     */
    public static function ajax_update_statuses(): void
    {
        check_ajax_referer('donation_stripe_admin', 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Sem permissão.', 'donation-stripe')], 403);
            return;
        }

        $payment_intent_ids = isset($_POST['payment_intent_ids']) && is_array($_POST['payment_intent_ids'])
            ? array_map('sanitize_text_field', $_POST['payment_intent_ids'])
            : [];

        if (empty($payment_intent_ids)) {
            wp_send_json_error(['message' => __('Nenhum ID de pagamento fornecido.', 'donation-stripe')], 400);
            return;
        }

        $statuses = [];
        foreach ($payment_intent_ids as $payment_intent_id) {
            if (empty($payment_intent_id)) {
                continue;
            }
            
            $status_data = self::get_payment_status_from_stripe($payment_intent_id);
            $status = is_array($status_data) ? $status_data['status'] : $status_data;
            
            // Normalizar classe CSS do status
            $status_class = strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $status));
            
            $statuses[$payment_intent_id] = [
                'status' => $status,
                'status_class' => $status_class,
            ];
        }

        wp_send_json_success(['statuses' => $statuses]);
    }

    /**
     * Busca status do pagamento no Stripe e retorna status + nome se disponível.
     * Retorna null se o pagamento não for encontrado no modo atual (Live/Test).
     *
     * @param string $payment_intent_id ID do PaymentIntent no Stripe
     * @return string|array{status: string, name?: string}|null
     */
    private static function get_payment_status_from_stripe(string $payment_intent_id)
    {
        if (empty($payment_intent_id)) {
            return __('Aguardando', 'donation-stripe');
        }

        try {
            $options = self::get_options();
            $secret_key = self::get_secret_key_for_stripe($options);
            if ($secret_key === '') {
                return __('Desconhecido', 'donation-stripe');
            }

            Donation_Stripe_Stripe_Handler::ensure_stripe_loaded();
            \Stripe\Stripe::setApiKey($secret_key);
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id, ['expand' => ['customer']]);

            $status = $intent->status ?? 'unknown';
            $status_map = [
                'succeeded' => __('Pago', 'donation-stripe'),
                'processing' => __('Processando', 'donation-stripe'),
                'requires_payment_method' => __('Aguardando pagamento', 'donation-stripe'),
                'requires_confirmation' => __('Aguardando confirmação', 'donation-stripe'),
                'requires_action' => __('Processando Pagamento', 'donation-stripe'),
                'canceled' => __('Cancelado', 'donation-stripe'),
            ];

            $status_text = $status_map[$status] ?? ucfirst($status);
            
            // Verificar mensagem de erro do Stripe (last_payment_error)
            if (isset($intent->last_payment_error) && !empty($intent->last_payment_error->message)) {
                $error_msg = $intent->last_payment_error->message;
                
                // Tradução de erros comuns
                if (stripos($error_msg, 'declined') !== false) {
                    $status_text = __('Cartão recusado', 'donation-stripe');
                } elseif (stripos($error_msg, 'insufficient funds') !== false) {
                    $status_text = __('Saldo insuficiente', 'donation-stripe');
                } elseif (stripos($error_msg, 'expired card') !== false) {
                    $status_text = __('Cartão expirado', 'donation-stripe');
                } elseif (stripos($error_msg, 'incorrect cvc') !== false) {
                    $status_text = __('CVC incorreto', 'donation-stripe');
                } elseif (stripos($error_msg, 'invalid number') !== false) {
                    $status_text = __('Número inválido', 'donation-stripe');
                } else {
                    $status_text = $error_msg; // Mensagem original se não houver tradução
                }
            }
            
            // Tentar buscar nome do metadata ou customer
            $name = '';
            if (isset($intent->metadata->cardholder_name)) {
                $name = $intent->metadata->cardholder_name;
            } elseif (isset($intent->customer) && is_object($intent->customer) && isset($intent->customer->name)) {
                $name = $intent->customer->name;
            }

            if ($name !== '') {
                return ['status' => $status_text, 'name' => $name];
            }

            return $status_text;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Se o erro for "resource_missing" (ex: ID não encontrado no modo atual), retornar null para ignorar
            if ($e->getHttpStatus() === 404 || strpos($e->getMessage(), 'No such payment_intent') !== false) {
                return null;
            }
            return __('Erro ao buscar', 'donation-stripe');
        } catch (\Throwable $e) {
            return __('Erro ao buscar', 'donation-stripe');
        }
    }

    /**
     * Obtém chave secreta do Stripe para uso interno.
     */
    private static function get_secret_key_for_stripe(array $options): string
    {
        $mode = $options['stripe_mode'] ?? 'test';
        return $mode === 'live'
            ? (string) ($options['stripe_secret_key_live'] ?? '')
            : (string) ($options['stripe_secret_key_test'] ?? '');
    }
}
