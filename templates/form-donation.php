<?php
/**
 * Template do formulário de doação – layout idêntico às imagens de referência.
 * Cores, espaçamentos, tipografia e estrutura HTML preservados.
 *
 * @package Donation_Stripe
 * @var array $options Opções do plugin (Donation_Stripe_Admin_Settings::get_options()).
 */

if (!defined('ABSPATH')) {
    exit;
}

$enable_boleto = !empty($options['enable_boleto']);
$enable_monthly = !empty($options['enable_recurring_monthly']);
$enable_annual = !empty($options['enable_recurring_annual']);

// Lista completa de países
$all_countries = [
    'AF' => 'Afghanistan',
    'AL' => 'Albania',
    'DZ' => 'Albania',
    'AS' => 'American Samoa',
    'AD' => 'Andorra',
    'AO' => 'Angola',
    'AI' => 'Anguilla',
    'AQ' => 'Antarctica',
    'AG' => 'Antigua and Barbuda',
    'AR' => 'Argentina',
    'AM' => 'Armenia',
    'AW' => 'Aruba',
    'AU' => 'Australia',
    'AT' => 'Austria',
    'AZ' => 'Azerbaijan',
    'BS' => 'Bahamas',
    'BH' => 'Bahrain',
    'BD' => 'Bangladesh',
    'BB' => 'Barbados',
    'BY' => 'Belarus',
    'BE' => 'Belgium',
    'BZ' => 'Belize',
    'BJ' => 'Belize',
    'BM' => 'Bermuda',
    'BT' => 'Bhutan',
    'BO' => 'Bolivia',
    'BA' => 'Bosnia and Herzegovina',
    'BW' => 'Botswana',
    'BV' => 'Bouvet Island',
    'BR' => 'Brazil',
    'IO' => 'British Indian Ocean Territory',
    'BN' => 'Brunei Darussalam',
    'BG' => 'Bulgaria',
    'BF' => 'Burkina Faso',
    'BI' => 'Burundi',
    'KH' => 'Cambodia',
    'CM' => 'Cameroon',
    'CA' => 'Canada',
    'CV' => 'Cape Verde',
    'KY' => 'Cayman Islands',
    'CF' => 'Central African Republic',
    'TD' => 'Chad',
    'CL' => 'Chile',
    'CN' => 'China',
    'CX' => 'Christmas Island',
    'CC' => 'Cocos (Keeling) Islands',
    'CO' => 'Colombia',
    'KM' => 'Comoros',
    'CG' => 'Congo',
    'CD' => 'Congo, the Democratic Republic of the',
    'CK' => 'Cook Islands',
    'CR' => 'Costa Rica',
    'CI' => 'Cote D\'Ivoire',
    'HR' => 'Croatia',
    'CU' => 'Cuba',
    'CY' => 'Cyprus',
    'CZ' => 'Czech Republic',
    'DK' => 'Denmark',
    'DJ' => 'Djibouti',
    'DM' => 'Djibouti',
    'DO' => 'Dominican Republic',
    'EC' => 'Ecuador',
    'EG' => 'Egypt',
    'SV' => 'El Salvador',
    'GQ' => 'Equatorial Guinea',
    'ER' => 'Eritrea',
    'EE' => 'Estonia',
    'ET' => 'Ethiopia',
    'FK' => 'Falkland Islands (Malvinas)',
    'FO' => 'Faroe Islands',
    'FJ' => 'Fiji',
    'FI' => 'Finland',
    'FR' => 'France',
    'GF' => 'French Guiana',
    'PF' => 'French Polynesia',
    'TF' => 'French Southern Territories',
    'GA' => 'Gabon',
    'GM' => 'Gambia',
    'GE' => 'Georgia',
    'DE' => 'Germany',
    'GH' => 'Ghana',
    'GI' => 'Gibraltar',
    'GR' => 'Greece',
    'GL' => 'Greenland',
    'GD' => 'Grenada',
    'GP' => 'Guadeloupe',
    'GU' => 'Guam',
    'GT' => 'Guatemala',
    'GN' => 'Guinea',
    'GW' => 'Guinea-Bissau',
    'GY' => 'Guyana',
    'HT' => 'Haiti',
    'HM' => 'Heard Island and Mcdonald Islands',
    'VA' => 'Holy See (Vatican City State)',
    'HN' => 'Honduras',
    'HK' => 'Hong Kong',
    'HU' => 'Hungary',
    'IS' => 'Iceland',
    'IN' => 'India',
    'ID' => 'Indonesia',
    'IR' => 'Iran, Islamic Republic of',
    'IQ' => 'Iraq',
    'IE' => 'Ireland',
    'IL' => 'Israel',
    'IT' => 'Italy',
    'JM' => 'Jamaica',
    'JP' => 'Japan',
    'JO' => 'Jordan',
    'KZ' => 'Kazakhstan',
    'KE' => 'Kenya',
    'KI' => 'Kiribati',
    'KP' => 'Korea, Democratic People\'s Republic of',
    'KR' => 'Korea, Republic of',
    'KW' => 'Kuwait',
    'KG' => 'Kyrgyzstan',
    'LA' => 'Lao People\'s Democratic Republic',
    'LV' => 'Latvia',
    'LB' => 'Lebanon',
    'LS' => 'Lesotho',
    'LR' => 'Liberia',
    'LY' => 'Libyan Arab Jamahiriya',
    'LI' => 'Liechtenstein',
    'LT' => 'Lithuania',
    'LU' => 'Luxembourg',
    'MO' => 'Macao',
    'MK' => 'Macedonia, the Former Yugoslav Republic of',
    'MG' => 'Madagascar',
    'MW' => 'Malawi',
    'MY' => 'Malaysia',
    'MV' => 'Maldives',
    'ML' => 'Mali',
    'MT' => 'Malta',
    'MH' => 'Marshall Islands',
    'MQ' => 'Martinique',
    'MR' => 'Mauritania',
    'MU' => 'Mauritius',
    'YT' => 'Mayotte',
    'MX' => 'Mexico',
    'FM' => 'Micronesia, Federated States of',
    'MD' => 'Moldova, Republic of',
    'MC' => 'Monaco',
    'MN' => 'Mongolia',
    'MS' => 'Montserrat',
    'MA' => 'Morocco',
    'MZ' => 'Mozambique',
    'MM' => 'Myanmar',
    'NA' => 'Namibia',
    'NR' => 'Nauru',
    'NP' => 'Nepal',
    'NL' => 'Netherlands',
    'AN' => 'Netherlands Antilles',
    'NC' => 'New Caledonia',
    'NZ' => 'New Zealand',
    'NI' => 'Nicaragua',
    'NE' => 'Niger',
    'NG' => 'Nigeria',
    'NU' => 'Niue',
    'NF' => 'Norfolk Island',
    'MP' => 'Northern Mariana Islands',
    'NO' => 'Norway',
    'OM' => 'Oman',
    'PK' => 'Pakistan',
    'PW' => 'Palau',
    'PS' => 'Palestinian Territory, Occupied',
    'PA' => 'Panama',
    'PG' => 'Papua New Guinea',
    'PY' => 'Paraguay',
    'PE' => 'Peru',
    'PH' => 'Philippines',
    'PN' => 'Pitcairn',
    'PL' => 'Poland',
    'PT' => 'Portugal',
    'PR' => 'Puerto Rico',
    'QA' => 'Qatar',
    'RE' => 'Reunion',
    'RO' => 'Romania',
    'RU' => 'Russian Federation',
    'RW' => 'Rwanda',
    'SH' => 'Saint Helena',
    'KN' => 'Saint Kitts and Nevis',
    'LC' => 'Saint Lucia',
    'PM' => 'Saint Pierre and Miquelon',
    'VC' => 'Saint Vincent and the Grenadines',
    'WS' => 'Samoa',
    'SM' => 'San Marino',
    'ST' => 'Sao Tome and Principe',
    'SA' => 'Saudi Arabia',
    'SN' => 'Senegal',
    'CS' => 'Serbia and Montenegro',
    'SC' => 'Seychelles',
    'SL' => 'Sierra Leone',
    'SG' => 'Singapore',
    'SK' => 'Slovakia',
    'SI' => 'Slovenia',
    'SB' => 'Solomon Islands',
    'SO' => 'Somalia',
    'ZA' => 'South Africa',
    'GS' => 'South Georgia and the South Sandwich Islands',
    'ES' => 'Spain',
    'LK' => 'Sri Lanka',
    'SD' => 'Sudan',
    'SR' => 'Suriname',
    'SJ' => 'Svalbard and Jan Mayen',
    'SZ' => 'Swaziland',
    'SE' => 'Sweden',
    'CH' => 'Switzerland',
    'SY' => 'Syrian Arab Republic',
    'TW' => 'Taiwan, Province of China',
    'TJ' => 'Tajikistan',
    'TZ' => 'Tanzania, United Republic of',
    'TH' => 'Thailand',
    'TL' => 'Timor-Leste',
    'TG' => 'Togo',
    'TK' => 'Tokelau',
    'TO' => 'Tonga',
    'TT' => 'Trinidad and Tobago',
    'TN' => 'Tunisia',
    'TR' => 'Turkey',
    'TM' => 'Turkmenistan',
    'TC' => 'Turks and Caicos Islands',
    'TV' => 'Tuvalu',
    'UG' => 'Uganda',
    'UA' => 'Ukraine',
    'AE' => 'United Arab Emirates',
    'GB' => 'United Kingdom',
    'US' => 'United States',
    'UM' => 'United States Minor Outlying Islands',
    'UY' => 'Uruguay',
    'UZ' => 'Uzbekistan',
    'VU' => 'Vanuatu',
    'VE' => 'Venezuela',
    'VN' => 'Viet Nam',
    'VG' => 'Virgin Islands, British',
    'VI' => 'Virgin Islands, U.S.',
    'WF' => 'Wallis and Futuna',
    'EH' => 'Western Sahara',
    'YE' => 'Yemen',
    'ZM' => 'Zambia',
    'ZW' => 'Zimbabwe'
];
?>

<div class="donation-stripe-form-wrapper" id="donation-stripe-form-wrapper">
    <!-- Overlay de loading em tela cheia -->
    <div class="donation-stripe-fullscreen-loading donation-stripe-hidden" id="donation-stripe-fullscreen-loading" aria-live="polite" aria-busy="false">
        <div class="donation-stripe-fullscreen-loading-content">
            <div class="donation-stripe-fullscreen-loading-icons" aria-hidden="true">
                <span class="donation-stripe-loading-icon donation-stripe-loading-icon-card"></span>
                <span class="donation-stripe-loading-icon donation-stripe-loading-icon-boleto"></span>
            </div>
            <p class="donation-stripe-fullscreen-loading-text">Processing Payment...</p>
        </div>
    </div>

    <form class="donation-stripe-form" id="donation-stripe-form" novalidate>
        <?php wp_nonce_field('donation_stripe_submit', 'donation_stripe_nonce'); ?>

        <!-- Donation amount -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-amount-preset">Donation Amount</label>
            <p class="donation-stripe-currency-notice" style="font-size: 0.85em; color: #666; margin-bottom: 8px;">
                Note: All donation amounts are in US Dollars (USD).
            </p>
            <div class="donation-stripe-amount-buttons">
                <button type="button" class="donation-stripe-amount-btn active" data-amount="25" aria-pressed="true">$25</button>
                <button type="button" class="donation-stripe-amount-btn" data-amount="35" aria-pressed="false">$35</button>
                <button type="button" class="donation-stripe-amount-btn" data-amount="50" aria-pressed="false">$50</button>
                <button type="button" class="donation-stripe-amount-btn donation-stripe-amount-btn-other" data-amount="other" aria-pressed="false">Other</button>
            </div>
            <div class="donation-stripe-amount-custom-wrap donation-stripe-hidden" id="donation-stripe-amount-custom-wrap">
                <span class="donation-stripe-currency-symbol">$</span>
                <input type="text" class="donation-stripe-input donation-stripe-amount-custom" id="donation-stripe-amount-custom" name="amount_custom" inputmode="numeric" placeholder="0" autocomplete="off" aria-describedby="donation-stripe-amount-decimals" />
                <span class="donation-stripe-amount-decimals" id="donation-stripe-amount-decimals" aria-hidden="true">,00</span>
            </div>
            <input type="hidden" name="amount_type" id="donation-stripe-amount-type" value="25" />
        </div>

        <!-- Donation frequency -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label">Donation Frequency</label>
            <div class="donation-stripe-frequency-group" role="group" aria-label="Donation frequency">
                <button type="button" class="donation-stripe-frequency-btn active" data-frequency="once" aria-pressed="true">One-time</button>
                <button type="button" class="donation-stripe-frequency-btn" data-frequency="monthly" aria-pressed="false" <?php echo $enable_monthly ? '' : ' disabled'; ?>>Monthly</button>
                <button type="button" class="donation-stripe-frequency-btn" data-frequency="annual" aria-pressed="false" <?php echo $enable_annual ? '' : ' disabled'; ?>>Annual</button>
            </div>
            <input type="hidden" name="frequency" id="donation-stripe-frequency" value="once" />
        </div>

        <!-- E-mail -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-email">Email Address</label>
            <input type="email" class="donation-stripe-input" id="donation-stripe-email" name="email" required autocomplete="email" />
        </div>

        <!-- Cardholder name -->
        <div class="donation-stripe-field">
            <label class="donation-stripe-label" for="donation-stripe-cardholder-name">Cardholder Name</label>
            <input type="text" class="donation-stripe-input" id="donation-stripe-cardholder-name" name="cardholder_name" required autocomplete="cc-name" />
        </div>

        <!-- Payment method: Card / Boleto -->
        <div class="donation-stripe-field donation-stripe-payment-method-field">
            <div class="donation-stripe-payment-method-group" role="group" aria-label="Payment method">
                <button type="button" class="donation-stripe-payment-method-btn active" data-method="card" aria-pressed="true">
                    <span class="donation-stripe-payment-icon donation-stripe-icon-card" aria-hidden="true"></span>
                    <span>Card</span>
                </button>
                <button type="button" class="donation-stripe-payment-method-btn" data-method="boleto" aria-pressed="false" style="display:none !important;" <?php echo $enable_boleto ? '' : ' disabled'; ?>>
                    <span class="donation-stripe-payment-icon donation-stripe-icon-boleto" aria-hidden="true"></span>
                    <span>Boleto</span>
                </button>
            </div>
            <input type="hidden" name="payment_method" id="donation-stripe-payment-method" value="card" />
        </div>

        <!-- Card fields (visible when Cartão selected). Stripe Elements montados via JS (PCI). -->
        <div class="donation-stripe-card-fields" id="donation-stripe-card-fields">
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-card-element">Card Number</label>
                <div class="donation-stripe-card-number-wrap">
                    <div id="donation-stripe-card-element" class="donation-stripe-stripe-element"></div>
                    <span class="donation-stripe-card-brands" aria-hidden="true">
                        <span class="donation-stripe-brand donation-stripe-brand-visa">VISA</span>
                        <span class="donation-stripe-brand donation-stripe-brand-mc">Mastercard</span>
                    </span>
                </div>
            </div>
            <div class="donation-stripe-field-row">
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-expiry-element">Expiration Date</label>
                    <div id="donation-stripe-expiry-element" class="donation-stripe-stripe-element"></div>
                </div>
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-cvc-element">Security Code</label>
                    <div class="donation-stripe-cvc-wrap">
                        <div id="donation-stripe-cvc-element" class="donation-stripe-stripe-element"></div>
                        <span class="donation-stripe-cvc-hint" aria-hidden="true"></span>
                    </div>
                </div>
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-country">Country</label>
                <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-country" name="country">
                    <option value="BR" selected>Brazil</option>
                    <?php
                    foreach ($all_countries as $code => $name) {
                        if ($code === 'BR') continue;
                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <!-- Boleto fields (visible when Boleto selected) -->
        <div class="donation-stripe-boleto-fields donation-stripe-hidden" id="donation-stripe-boleto-fields">
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-cpf-cnpj">Tax ID (CPF/CNPJ)</label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-cpf-cnpj" name="cpf_cnpj" placeholder="000.000.000-00" autocomplete="off" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-country-boleto">Country or Region</label>
                <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-country-boleto" name="country_boleto">
                    <option value="BR" selected>Brazil</option>
                </select>
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-address-line1">Address Line 1</label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-address-line1" name="address_line1" autocomplete="address-line1" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-address-line2">Address Line 2</label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-address-line2" name="address_line2" placeholder="Apt., suite, unit number, etc. (optional)" autocomplete="address-line2" />
            </div>
            <div class="donation-stripe-field">
                <label class="donation-stripe-label" for="donation-stripe-city">City</label>
                <input type="text" class="donation-stripe-input" id="donation-stripe-city" name="city" autocomplete="address-level2" />
            </div>
            <div class="donation-stripe-field-row">
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-state">State</label>
                    <select class="donation-stripe-input donation-stripe-select" id="donation-stripe-state" name="state">
                        <?php
                        $states_br = ['AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'];
                        foreach ($states_br as $uf => $name) {
                            echo '<option value="' . esc_attr($uf) . '"' . selected($uf, 'GO', false) . '>' . esc_html($name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="donation-stripe-field">
                    <label class="donation-stripe-label" for="donation-stripe-postal-code">Postal Code</label>
                    <input type="text" class="donation-stripe-input" id="donation-stripe-postal-code" name="postal_code" autocomplete="postal-code" />
                </div>
            </div>
        </div>

        <!-- Legal text -->
        <p class="donation-stripe-legal" id="donation-stripe-legal">
            By providing your card information, you allow SINAGOGA BEIT JACOB to charge your card for future payments in accordance with their terms.
        </p>

        <!-- Submit -->
        <div class="donation-stripe-field donation-stripe-submit-wrap">
            <button type="submit" class="donation-stripe-submit" id="donation-stripe-submit">
                <span class="donation-stripe-submit-text">Donate</span>
                <span class="donation-stripe-submit-loading donation-stripe-hidden" aria-hidden="true">Processing...</span>
            </button>
        </div>

        <!-- Messages -->
        <div class="donation-stripe-message donation-stripe-message-success donation-stripe-hidden" id="donation-stripe-message-success" role="alert">
            <?php echo esc_html($options['message_success'] ?? 'Thank you! Your donation has been successfully processed.'); ?>
        </div>
        <div class="donation-stripe-message donation-stripe-message-error donation-stripe-hidden" id="donation-stripe-message-error" role="alert"></div>
    </form>

    <!-- Stripe Elements container (usado pelo JS para Payment Element ou card) -->
    <div id="donation-stripe-element-mount" class="donation-stripe-hidden"></div>
</div>
