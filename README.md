# Plugin Doação Stripe – Sinagoga Beit Jacob

Plugin WordPress profissional para doações via Stripe, com suporte a **cartão de crédito**, **boleto**, doação **única**, **mensal** e **anual**. O plugin gerencia automaticamente a conversão de moeda (USD/BRL) para cartões brasileiros e boletos.

## Requisitos

- PHP 8.0+
- WordPress 6.0+
- Conta Stripe (chaves Test e Live)
- Composer (para instalar o Stripe PHP SDK)

## Instalação

1. Coloque a pasta do plugin em `wp-content/plugins/` (ou use o nome `plugin-doacao-stripe` ao empacotar).
2. No diretório do plugin, execute:
   ```bash
   composer install --no-dev
   ```
   Isso instala o Stripe PHP SDK em `vendor/`.
3. Ative o plugin em **Plugins** no WordPress.
4. Em **Doações > Configurações**:
   - **Chaves Stripe**: Preencha as chaves Test e/ou Live e selecione o modo de operação (Teste ou Produção).
   - **Opções de Pagamento**: Ative boleto e doações recorrentes (mensal/anual) conforme necessário.
   - **Cliente (SMTP)**: Configure os dados do servidor SMTP para envio de faturas, recibos e links de boletos.
5. Configure o webhook no [Stripe Dashboard](https://dashboard.stripe.com/webhooks): URL do endpoint (ex.: `https://seusite.com/donation-stripe-webhook/`) e eventos: `payment_intent.succeeded`, `invoice.payment_succeeded`, `invoice.payment_failed`, `customer.subscription.deleted`. Cole o **Signing Secret** em **Doações > Configurações** (Webhook Signing Secret Test/Live).
6. **Para doações recorrentes por boleto:** configure o cron do servidor para rodar diariamente. Veja a seção "Cron para boletos recorrentes" abaixo.
7. Insira o shortcode `[donation_stripe]` em qualquer página ou post para exibir o formulário.

## Clonando do Git (Instalação Manual)

Se você clonou este repositório diretamente do GitHub, a pasta `vendor` (dependências) não estará incluída. Você precisa instalá-la manualmente:

1. Acesse a pasta do plugin via terminal:
   ```bash
   cd wp-content/plugins/plugin_stripe_beit_jacob
   ```
2. Instale as dependências usando o Composer:
   ```bash
   composer install --no-dev
   ```
   *Nota: Se você não tiver acesso SSH ou Composer no servidor, faça este processo em seu computador local e envie a pasta `vendor` gerada para o servidor via FTP.*

## Funcionalidades Principais

### Conversão Automática de Moeda (USD/BRL)
O formulário exibe valores em **Dólar (USD)** por padrão. No entanto, o plugin detecta automaticamente se o pagamento deve ser processado em **Reais (BRL)**:
- **Cartão Brasileiro:** Se o doador selecionar "Brasil" como país, o valor em USD é convertido automaticamente para BRL usando a cotação atual (consumida via API `apilayer.net` com cache de 12h) antes de processar a cobrança. Isso evita erros de moeda não suportada na conta Stripe brasileira.
- **Boleto:** Como boletos são exclusivamente brasileiros, a conversão para BRL é sempre aplicada.
- **Cartões Internacionais:** Permanecem em USD.

### Status de Doações
A aba **Status de Doações** no painel admin permite visualizar todas as transações (sucesso, falha, processando):
- Filtra automaticamente pelo modo de operação (Teste ou Produção).
- Busca em tempo real por nome, e-mail ou valor.
- Paginação AJAX para navegar entre os registros sem recarregar a página.
- Exibe detalhes como recorrência (Mensal/Anual) e método (Cartão/Boleto).

### E-mail e SMTP
O plugin possui um sistema de envio de e-mail integrado via SMTP para garantir a entrega de:
- Confirmação de pagamento (Recibo).
- Link para pagamento de boleto (assim que gerado).
- Notificações de renovação de boleto recorrente.

## Shortcode

- **`[donation_stripe]`** – Renderiza o formulário completo. CSS e JS são carregados apenas nas páginas em que o shortcode é usado.

### Parâmetros de Redirecionamento
Se você configurar uma página de agradecimento (Thank You Page) nas configurações do shortcode (via script global `donationStripeConfig.thankYouPageUrl`), o plugin redirecionará o usuário após o sucesso, anexando os parâmetros:
- `method`: `card` ou `boleto`
- `payment_intent`: ID da transação (ex: `pi_3Sx...`)
- `payment_intent_client_secret`: (Opcional, dependendo do fluxo)

Exemplo de URL final: `https://seusite.com/obrigado/?method=boleto&payment_intent=pi_123456...`

## Cron para boletos recorrentes

O plugin suporta **doações recorrentes por boleto** (mensal ou anual). Como o Stripe não tem recorrência nativa para boleto, o plugin gera novos boletos automaticamente após cada pagamento.

### Configuração do cron do servidor (recomendado)

**Importante:** Use o cron do servidor, não o do WordPress, para garantir que os boletos sejam gerados mesmo sem visitas ao site.

#### Descobrindo o caminho completo

1. **Via script PHP:**
   Execute o script manualmente para ver o caminho:
   ```bash
   php /caminho/do/plugin/cron-boleto-recurring.php --show-path
   ```

2. **Padrões comuns:**
   - cPanel: `/home/usuario/public_html/`
   - Plesk: `/var/www/vhosts/dominio.com/httpdocs/`

#### Configurando o cron

No cPanel ou crontab, adicione uma tarefa diária (ex: 02:00 AM):

```bash
/usr/bin/php /home/usuario/public_html/wp-content/plugins/plugin_stripe_beit_jacob/cron-boleto-recurring.php >> /home/usuario/public_html/wp-content/plugins/plugin_stripe_beit_jacob/logs/boleto-cron.log 2>&1
```

**Sobre os logs:**
- O arquivo `boleto-cron.log` será criado automaticamente no diretório `logs/` dentro do plugin.
- Verifique este arquivo para monitorar a geração automática de boletos.

## Estrutura de Arquivos

```
plugin-doacao-stripe/
├── plugin-doacao-stripe.php        # Arquivo principal
├── cron-boleto-recurring.php       # Script para cron do servidor
├── includes/
│   ├── admin-settings.php          # Painel Admin (Configurações, Status, SMTP)
│   ├── shortcode-form.php          # Renderização do Shortcode
│   ├── stripe-handler.php          # Lógica de Pagamento e Conversão de Moeda
│   ├── webhook-handler.php         # Webhooks e E-mails Transacionais
│   └── recurring-boleto-cron.php   # Lógica de Recorrência de Boleto
├── templates/
│   └── form-donation.php           # HTML do Formulário
├── assets/
│   ├── css/                        # Estilos (Admin e Frontend)
│   └── js/                         # Scripts (Stripe Elements, AJAX, Admin)
├── vendor/                         # Stripe PHP SDK (via Composer)
├── composer.json
└── README.md
```

## Segurança e boas práticas

- **Nonce** em todo submit do formulário e verificação no AJAX.
- **Sanitização** de todos os inputs no PHP.
- **PCI Compliance:** Cartão tratado apenas por Stripe.js/Elements; servidor nunca recebe número de cartão.
- **Webhooks:** Assinatura verificada; eventos processados com idempotência.
- **Cache de API:** A cotação do dólar é armazenada em cache por 12h para otimizar performance.
