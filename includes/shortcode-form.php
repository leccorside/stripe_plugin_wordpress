<?php
/**
 * Shortcode [donation_stripe] – renderiza o formulário de doação e enfileira assets apenas quando usado.
 *
 * @package Donation_Stripe
 */

declare(strict_types = 1)
;

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

        $lang = self::get_current_lang();
        $t = self::get_translations($lang);
        $countries = self::get_countries($lang);
        $states_br = self::get_states_br();

        ob_start();
        include DONATION_STRIPE_PLUGIN_DIR . 'templates/form-donation.php';
        return (string)ob_get_clean();
    }

    /**
     * Detecta o idioma atual baseado na URL.
     * Se conter '/en/', retorna 'en'. Caso contrário, 'pt'.
     */
    private static function get_current_lang(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/en/') !== false) {
            return 'en';
        }
        return 'pt';
    }

    /**
     * Retorna as traduções para o idioma selecionado.
     */
    private static function get_translations(string $lang): array
    {
        $translations = [
            'pt' => [
                'donation_amount' => 'Valor da Doação',
                'currency_notice' => 'Nota: Todas as doações são em Dólares Americanos (USD).',
                'other' => 'Outro',
                'donation_frequency' => 'Frequência de Doação',
                'frequency_once' => 'Uma vez',
                'frequency_monthly' => 'Mensal',
                'frequency_annual' => 'Anual',
                'email' => 'Endereço de E-mail',
                'cardholder_name' => 'Nome do Titular do Cartão',
                'card' => 'Cartão',
                'boleto' => 'Boleto',
                'card_number' => 'Número do Cartão',
                'expiration_date' => 'Data de Validade',
                'security_code' => 'Código de Segurança',
                'country' => 'País',
                'tax_id' => 'CPF/CNPJ',
                'country_region' => 'País ou Região',
                'address_line1' => 'Endereço Linha 1',
                'address_line2' => 'Endereço Linha 2',
                'address_line2_placeholder' => 'Apto, bloco, unidade, etc. (opcional)',
                'city' => 'Cidade',
                'state' => 'Estado',
                'postal_code' => 'CEP',
                'legal_text' => 'Ao fornecer as informações do seu cartão, você permite que a SINAGOGA BEIT JACOB cobre seu cartão para pagamentos futuros de acordo com os termos.',
                'donate' => 'Doar',
                'processing' => 'Processando...',
                'payment_processing' => 'Processando Pagamento...',
                'success_message' => 'Obrigado! Sua doação foi processada com sucesso.',
                'error_required' => 'Campo obrigatório.',
                'error_email' => 'E-mail inválido.',
                'error_amount' => 'Informe um valor válido.',
                'error_processing' => 'Erro ao processar pagamento.',
            ],
            'en' => [
                'donation_amount' => 'Donation Amount',
                'currency_notice' => 'Note: All donation amounts are in US Dollars (USD).',
                'other' => 'Other',
                'donation_frequency' => 'Donation Frequency',
                'frequency_once' => 'One-time',
                'frequency_monthly' => 'Monthly',
                'frequency_annual' => 'Annual',
                'email' => 'Email Address',
                'cardholder_name' => 'Cardholder Name',
                'card' => 'Card',
                'boleto' => 'Boleto',
                'card_number' => 'Card Number',
                'expiration_date' => 'Expiration Date',
                'security_code' => 'Security Code',
                'country' => 'Country',
                'tax_id' => 'Tax ID (CPF/CNPJ)',
                'country_region' => 'Country or Region',
                'address_line1' => 'Address Line 1',
                'address_line2' => 'Address Line 2',
                'address_line2_placeholder' => 'Apt., suite, unit number, etc. (optional)',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'legal_text' => 'By providing your card information, you allow SINAGOGA BEIT JACOB to charge your card for future payments in accordance with their terms.',
                'donate' => 'Donate',
                'processing' => 'Processing...',
                'payment_processing' => 'Processing Payment...',
                'success_message' => 'Thank you! Your donation has been successfully processed.',
                'error_required' => 'Required field.',
                'error_email' => 'Invalid email.',
                'error_amount' => 'Enter a valid amount.',
                'error_processing' => 'Error processing payment.',
            ],
        ];

        return $translations[$lang] ?? $translations['pt'];
    }

    /**
     * Retorna a lista de países traduzida.
     */
    private static function get_countries(string $lang): array
    {
        $countries_en = [
            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa',
            'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba',
            'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
            'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
            'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda',
            'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana',
            'BV' => 'Bouvet Island', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
            'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile',
            'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands', 'CO' => 'Colombia',
            'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, the Democratic Republic of the',
            'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote D\'Ivoire', 'HR' => 'Croatia',
            'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark',
            'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador',
            'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea',
            'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands (Malvinas)', 'FO' => 'Faroe Islands',
            'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana',
            'PF' => 'French Polynesia', 'TF' => 'French Southern Territories', 'GA' => 'Gabon',
            'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana',
            'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada',
            'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HM' => 'Heard Island and Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)', 'HN' => 'Honduras', 'HK' => 'Hong Kong',
            'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic of', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel',
            'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan',
            'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea, Democratic People\'s Republic of',
            'KR' => 'Korea, Republic of', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho',
            'LR' => 'Liberia', 'LY' => 'Libyan Arab Jamahiriya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
            'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia, the Former Yugoslav Republic of',
            'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali',
            'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania',
            'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico', 'FM' => 'Micronesia, Federated States of',
            'MD' => 'Moldova, Republic of', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'MS' => 'Montserrat',
            'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
            'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger',
            'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied', 'PA' => 'Panama', 'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn',
            'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar',
            'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'RW' => 'Rwanda',
            'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia',
            'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia',
            'SN' => 'Senegal', 'CS' => 'Serbia and Montenegro', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone',
            'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands',
            'SO' => 'Somalia', 'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands', 'ES' => 'Spain', 'LK' => 'Sri Lanka',
            'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen', 'SZ' => 'Swaziland',
            'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan, Province of China', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania, United Republic of',
            'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau',
            'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
            'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda',
            'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
            'UM' => 'United States Minor Outlying Islands', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu', 'VE' => 'Venezuela', 'VN' => 'Viet Nam', 'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
            'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
        ];

        $countries_pt = [
            'AF' => 'Afeganistão', 'AL' => 'Albânia', 'DZ' => 'Argélia', 'AS' => 'Samoa Americana',
            'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antártida',
            'AG' => 'Antígua e Barbuda', 'AR' => 'Argentina', 'AM' => 'Armênia', 'AW' => 'Aruba',
            'AU' => 'Austrália', 'AT' => 'Áustria', 'AZ' => 'Azerbaijão', 'BS' => 'Bahamas',
            'BH' => 'Bahrein', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Bielorrússia',
            'BE' => 'Bélgica', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermudas',
            'BT' => 'Butão', 'BO' => 'Bolívia', 'BA' => 'Bósnia e Herzegovina', 'BW' => 'Botsuana',
            'BV' => 'Ilha Bouvet', 'BR' => 'Brasil', 'IO' => 'Território Britânico do Oceano Índico',
            'BN' => 'Brunei', 'BG' => 'Bulgária', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
            'KH' => 'Camboja', 'CM' => 'Camarões', 'CA' => 'Canadá', 'CV' => 'Cabo Verde',
            'KY' => 'Ilhas Cayman', 'CF' => 'República Centro-Africana', 'TD' => 'Chade', 'CL' => 'Chile',
            'CN' => 'China', 'CX' => 'Ilha Christmas', 'CC' => 'Ilhas Cocos (Keeling)', 'CO' => 'Colômbia',
            'KM' => 'Comores', 'CG' => 'Congo', 'CD' => 'República Democrática do Congo',
            'CK' => 'Ilhas Cook', 'CR' => 'Costa Rica', 'CI' => 'Costa do Marfim', 'HR' => 'Croácia',
            'CU' => 'Cuba', 'CY' => 'Chipre', 'CZ' => 'República Tcheca', 'DK' => 'Dinamarca',
            'DJ' => 'Djibuti', 'DM' => 'Dominica', 'DO' => 'República Dominicana', 'EC' => 'Equador',
            'EG' => 'Egito', 'SV' => 'El Salvador', 'GQ' => 'Guiné Equatorial', 'ER' => 'Eritreia',
            'EE' => 'Estônia', 'ET' => 'Etiópia', 'FK' => 'Ilhas Malvinas', 'FO' => 'Ilhas Faroé',
            'FJ' => 'Fiji', 'FI' => 'Finlândia', 'FR' => 'França', 'GF' => 'Guiana Francesa',
            'PF' => 'Polinésia Francesa', 'TF' => 'Territórios Franceses do Sul', 'GA' => 'Gabão',
            'GM' => 'Gâmbia', 'GE' => 'Geórgia', 'DE' => 'Alemanha', 'GH' => 'Gana',
            'GI' => 'Gibraltar', 'GR' => 'Grécia', 'GL' => 'Groenlândia', 'GD' => 'Granada',
            'GP' => 'Guadalupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GN' => 'Guiné',
            'GW' => 'Guiné-Bissau', 'GY' => 'Guiana', 'HT' => 'Haiti', 'HM' => 'Ilhas Heard e McDonald',
            'VA' => 'Vaticano', 'HN' => 'Honduras', 'HK' => 'Hong Kong',
            'HU' => 'Hungria', 'IS' => 'Islândia', 'IN' => 'Índia', 'ID' => 'Indonésia',
            'IR' => 'Irã', 'IQ' => 'Iraque', 'IE' => 'Irlanda', 'IL' => 'Israel',
            'IT' => 'Itália', 'JM' => 'Jamaica', 'JP' => 'Japão', 'JO' => 'Jordânia',
            'KZ' => 'Cazaquistão', 'KE' => 'Quênia', 'KI' => 'Kiribati', 'KP' => 'Coreia do Norte',
            'KR' => 'Coreia do Sul', 'KW' => 'Kuwait', 'KG' => 'Quirguistão',
            'LA' => 'Laos', 'LV' => 'Letônia', 'LB' => 'Líbano', 'LS' => 'Lesoto',
            'LR' => 'Libéria', 'LY' => 'Líbia', 'LI' => 'Liechtenstein', 'LT' => 'Lituânia',
            'LU' => 'Luxemburgo', 'MO' => 'Macau', 'MK' => 'Macedônia do Norte',
            'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malásia', 'MV' => 'Maldivas', 'ML' => 'Mali',
            'MT' => 'Malta', 'MH' => 'Ilhas Marshall', 'MQ' => 'Martinica', 'MR' => 'Mauritânia',
            'MU' => 'Maurício', 'YT' => 'Mayotte', 'MX' => 'México', 'FM' => 'Micronésia',
            'MD' => 'Moldávia', 'MC' => 'Mônaco', 'MN' => 'Mongólia', 'MS' => 'Montserrat',
            'MA' => 'Marrocos', 'MZ' => 'Moçambique', 'MM' => 'Mianmar', 'NA' => 'Namíbia',
            'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Holanda', 'AN' => 'Antilhas Holandesas',
            'NC' => 'Nova Caledônia', 'NZ' => 'Nova Zelândia', 'NI' => 'Nicarágua', 'NE' => 'Níger',
            'NG' => 'Nigéria', 'NU' => 'Niue', 'NF' => 'Ilha Norfolk', 'MP' => 'Ilhas Marianas do Norte',
            'NO' => 'Noruega', 'OM' => 'Omã', 'PK' => 'Paquistão', 'PW' => 'Palau',
            'PS' => 'Palestina', 'PA' => 'Panamá', 'PG' => 'Papua Nova Guiné',
            'PY' => 'Paraguai', 'PE' => 'Peru', 'PH' => 'Filipinas', 'PN' => 'Pitcairn',
            'PL' => 'Polônia', 'PT' => 'Portugal', 'PR' => 'Porto Rico', 'QA' => 'Catar',
            'RE' => 'Reunião', 'RO' => 'Romênia', 'RU' => 'Rússia', 'RW' => 'Ruanda',
            'SH' => 'Santa Helena', 'KN' => 'São Cristóvão e Nevis', 'LC' => 'Santa Lúcia',
            'PM' => 'São Pedro e Miquelon', 'VC' => 'São Vicente e Granadinas',
            'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'São Tomé e Príncipe', 'SA' => 'Arábia Saudita',
            'SN' => 'Senegal', 'CS' => 'Sérvia e Montenegro', 'SC' => 'Seychelles', 'SL' => 'Serra Leoa',
            'SG' => 'Cingapura', 'SK' => 'Eslováquia', 'SI' => 'Eslovênia', 'SB' => 'Ilhas Salomão',
            'SO' => 'Somália', 'ZA' => 'África do Sul',
            'GS' => 'Geórgia do Sul e Ilhas Sandwich do Sul', 'ES' => 'Espanha', 'LK' => 'Sri Lanka',
            'SD' => 'Sudão', 'SR' => 'Suriname', 'SJ' => 'Svalbard e Jan Mayen', 'SZ' => 'Suazilândia',
            'SE' => 'Suécia', 'CH' => 'Suíça', 'SY' => 'Síria',
            'TW' => 'Taiwan', 'TJ' => 'Tajiquistão', 'TZ' => 'Tanzânia',
            'TH' => 'Tailândia', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau',
            'TO' => 'Tonga', 'TT' => 'Trinidad e Tobago', 'TN' => 'Tunísia', 'TR' => 'Turquia',
            'TM' => 'Turcomenistão', 'TC' => 'Ilhas Turcas e Caicos', 'TV' => 'Tuvalu', 'UG' => 'Uganda',
            'UA' => 'Ucrânia', 'AE' => 'Emirados Árabes Unidos', 'GB' => 'Reino Unido', 'US' => 'Estados Unidos',
            'UM' => 'Ilhas Menores Distantes dos Estados Unidos', 'UY' => 'Uruguai', 'UZ' => 'Uzbequistão',
            'VU' => 'Vanuatu', 'VE' => 'Venezuela', 'VN' => 'Vietnã', 'VG' => 'Ilhas Virgens Britânicas',
            'VI' => 'Ilhas Virgens Americanas', 'WF' => 'Wallis e Futuna', 'EH' => 'Saara Ocidental',
            'YE' => 'Iêmen', 'ZM' => 'Zâmbia', 'ZW' => 'Zimbábue'
        ];

        return ($lang === 'en') ? $countries_en : $countries_pt;
    }

    private static function get_states_br(): array
    {
        return [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
        ];
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
        $js_url = DONATION_STRIPE_PLUGIN_URL . 'assets/js/script.js';
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

        // Detecta idioma para enviar ao JS
        $lang = self::get_current_lang();
        $t = self::get_translations($lang);

        wp_localize_script('donation-stripe-form', 'donationStripeConfig', [
            'publishableKey' => $publishable_key,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('donation_stripe_submit'),
            'thankYouPageUrl' => !empty($options['thank_you_page_url']) ? esc_url($options['thank_you_page_url']) : '',
            'i18n' => [
                'donationAmount' => $t['donation_amount'],
                'donationFrequency' => $t['donation_frequency'],
                'oneTime' => $t['frequency_once'],
                'monthly' => $t['frequency_monthly'],
                'annual' => $t['frequency_annual'],
                'email' => $t['email'],
                'cardholderName' => $t['cardholder_name'],
                'card' => $t['card'],
                'boleto' => $t['boleto'],
                'cardNumber' => $t['card_number'],
                'expiry' => $t['expiration_date'],
                'cvc' => $t['security_code'],
                'country' => $t['country'],
                'cpfCnpj' => $t['tax_id'],
                'countryRegion' => $t['country_region'],
                'addressLine1' => $t['address_line1'],
                'addressLine2' => $t['address_line2'],
                'city' => $t['city'],
                'state' => $t['state'],
                'postalCode' => $t['postal_code'],
                'donate' => $t['donate'],
                'legalText' => $t['legal_text'],
                'required' => $t['error_required'],
                'invalidEmail' => $t['error_email'],
                'invalidAmount' => $t['error_amount'],
                'message_error' => $t['error_processing'],
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
        $lang = self::get_current_lang();
        $t = self::get_translations($lang);

        $is_boleto = isset($_GET['method']) && $_GET['method'] === 'boleto';
        $title = isset($atts['title']) ? sanitize_text_field($atts['title']) : ($lang === 'en' ? 'Thank you for your donation!' : 'Obrigado por sua doação!');

        if (isset($atts['message'])) {
            $message = sanitize_textarea_field($atts['message']);
        }
        else {
            if ($is_boleto) {
                $message = ($lang === 'en')
                    ? 'Your donation will be processed as soon as the boleto payment is confirmed. We appreciate your support.'
                    : 'Sua doação será processada assim que o pagamento do boleto for confirmado. Agradecemos seu apoio.';
            }
            else {
                $message = ($t['success_message']);
            }
        }

        $check_icon = '<svg class="donation-stripe-thank-you-icon" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

        $home_url = home_url('/');
        $back_label = isset($atts['back_label']) ? sanitize_text_field($atts['back_label']) : ($lang === 'en' ? 'Back to Home' : 'Voltar para Início');

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
        return (string)ob_get_clean();
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
