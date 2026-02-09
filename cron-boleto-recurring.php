<?php
/**
 * Script standalone para cron do servidor - gera boletos recorrentes automaticamente.
 * 
 * Configure no crontab do servidor para rodar diariamente:
 * 0 2 * * * /usr/bin/php /caminho/completo/wp-content/plugins/plugin_stripe_beit_jacob/cron-boleto-recurring.php >> /caminho/logs/boleto-cron.log 2>&1
 * 
 * Ou via cPanel: Tarefas Agendadas (Cron Jobs)
 * 
 * Para descobrir o caminho completo:
 * - No FileZilla, o caminho relativo é /public_html/wp-content/plugins/plugin_stripe_beit_jacob
 * - O caminho completo geralmente é /home/usuario/public_html/wp-content/plugins/plugin_stripe_beit_jacob
 * - Execute este script manualmente para ver o caminho: php cron-boleto-recurring.php
 * 
 * @package Donation_Stripe
 */

// Modo debug: se executado com --show-path, mostra o caminho e sai
if (isset($argv[1]) && $argv[1] === '--show-path') {
    echo "Caminho do script: " . __FILE__ . "\n";
    echo "Caminho absoluto: " . realpath(__FILE__) . "\n";
    exit(0);
}

// Carregar WordPress (busca wp-load.php subindo diretórios)
$wp_load_path = dirname(__FILE__);
$found = false;
for ($i = 0; $i < 10; $i++) {
    $wp_load_path = dirname($wp_load_path);
    $wp_config = $wp_load_path . '/wp-load.php';
    if (file_exists($wp_config)) {
        require_once $wp_config;
        $found = true;
        break;
    }
}

if (!$found || !defined('ABSPATH')) {
    $script_path = realpath(__FILE__);
    die("Erro: WordPress não encontrado.\n" .
        "Caminho do script: {$script_path}\n" .
        "Verifique se o arquivo está em wp-content/plugins/plugin_stripe_beit_jacob/\n" .
        "Execute com --show-path para ver o caminho completo: php cron-boleto-recurring.php --show-path\n");
}

// Verificar se o plugin está ativo
if (!defined('DONATION_STRIPE_PLUGIN_DIR')) {
    die("Erro: Plugin Doação Stripe não está ativo.\n");
}

// Carregar classes do plugin
require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/admin-settings.php';
require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/stripe-handler.php';
require_once DONATION_STRIPE_PLUGIN_DIR . 'includes/recurring-boleto-cron.php';

// Criar diretório de logs se não existir
$log_dir = dirname(__FILE__) . '/logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

// Executar geração de boletos
try {
    Donation_Stripe_Recurring_Boleto_Cron::generate_pending_boletos();
    echo "[" . date('Y-m-d H:i:s') . "] Cron executado com sucesso.\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    if (isset($e->getTrace()[0])) {
        echo "Arquivo: " . ($e->getFile() ?? 'N/A') . " Linha: " . ($e->getLine() ?? 'N/A') . "\n";
    }
    exit(1);
}
