/**
 * JavaScript do painel administrativo do plugin Doação Stripe.
 * Navegação entre tabs, atualização automática de status e paginação/busca via AJAX.
 *
 * @package Donation_Stripe
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // --- Gestão de Tabs ---
        var $tabs = $('.nav-tab-wrapper .nav-tab');
        
        $tabs.on('click', function () {
            var href = $(this).attr('href');
            if (href && href.indexOf('tab=') !== -1) {
                var tab = href.split('tab=')[1];
                if (tab) {
                    localStorage.setItem('donation_stripe_active_tab', tab);
                }
            }
        });
        
        if (window.location.href.indexOf('tab=') === -1) {
            var savedTab = localStorage.getItem('donation_stripe_active_tab');
            if (savedTab) {
                var currentUrl = window.location.href;
                var separator = currentUrl.indexOf('?') === -1 ? '?' : '&';
                window.location.href = currentUrl + separator + 'tab=' + savedTab;
            }
        }

        // --- Verificação se estamos na aba de Status ---
        if ($('#donation-stripe-results-container').length > 0 || $('#donation-stripe-search-form').length > 0) {
            
            var updateInterval = null;
            var isUpdating = false;
            var searchXhr = null;
            var lastQuery = '';
            // Removido lastPage pois não estava sendo usado efetivamente para controle de estado complexo

            // --- Funções de Polling ---

            function collectPaymentIntentIds() {
                var ids = [];
                $('.donation-status[data-payment-intent-id]').each(function () {
                    var id = $(this).attr('data-payment-intent-id');
                    if (id && id.trim() !== '') {
                        ids.push(id);
                    }
                });
                return ids;
            }

            function updateStatuses(statuses) {
                $.each(statuses, function (paymentIntentId, data) {
                    var $statusElement = $('.donation-status[data-payment-intent-id="' + paymentIntentId + '"]');
                    if ($statusElement.length > 0) {
                        var currentClasses = $statusElement.attr('class') || '';
                        var classesToRemove = currentClasses.split(' ').filter(function(cls) {
                            return cls.startsWith('donation-status-') && cls !== 'donation-status';
                        });
                        $statusElement.removeClass(classesToRemove.join(' '));
                        $statusElement.addClass('donation-status-' + data.status_class);
                        $statusElement.text(data.status);
                    }
                });
            }

            function fetchStatuses() {
                if (isUpdating) return;

                var paymentIntentIds = collectPaymentIntentIds();
                if (paymentIntentIds.length === 0) return;

                isUpdating = true;

                if (typeof donationStripeAdmin === 'undefined' || !donationStripeAdmin.ajaxurl) {
                    console.error('Donation Stripe: donationStripeAdmin não definido.');
                    isUpdating = false;
                    return;
                }

                $.ajax({
                    url: donationStripeAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'donation_stripe_update_statuses',
                        nonce: donationStripeAdmin.nonce,
                        payment_intent_ids: paymentIntentIds
                    },
                    success: function (response) {
                        if (response.success && response.data && response.data.statuses) {
                            updateStatuses(response.data.statuses);
                        }
                    },
                    complete: function () {
                        isUpdating = false;
                    }
                });
            }

            // --- Funções de Busca e Paginação ---

            function loadDonations(page, search) {
                var $container = $('#donation-stripe-results-container');
                // Removido efeito de opacidade para feedback mais instantâneo ou usar loader específico se necessário

                if (typeof donationStripeAdmin === 'undefined' || !donationStripeAdmin.ajaxurl) {
                    console.error('Donation Stripe: donationStripeAdmin não definido.');
                    return;
                }

                // Abortar requisição anterior se houver
                if (searchXhr && searchXhr.readyState !== 4) {
                    searchXhr.abort();
                }

                searchXhr = $.ajax({
                    url: donationStripeAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'donation_stripe_fetch_donations',
                        nonce: donationStripeAdmin.nonce,
                        page: page,
                        search: search
                    },
                    success: function (response) {
                        if (response.success && response.data && response.data.html) {
                            $container.html(response.data.html);
                            lastQuery = search;
                            
                            // Atualizar URL (opcional, para UX)
                            var newUrl = new URL(window.location.href);
                            newUrl.searchParams.set('donation_page', page);
                            if (search) {
                                newUrl.searchParams.set('donation_search', search);
                            } else {
                                newUrl.searchParams.delete('donation_search');
                            }
                            window.history.pushState({path: newUrl.href}, '', newUrl.href);

                            // Reiniciar polling nos novos elementos
                            fetchStatuses();
                        } else {
                            console.error('Erro na resposta AJAX:', response);
                        }
                    },
                    error: function (xhr, status, error) {
                        if (status !== 'abort') {
                            console.error('Erro AJAX:', error);
                        }
                    }
                });
            }

            // --- Event Handlers (Usando delegação no body para robustez) ---

            // 1. Submit do Formulário de Busca
            $('body').on('submit', '#donation-stripe-search-form', function (e) {
                e.preventDefault(); 
                var search = $('#donation_search').val();
                var trimmed = search.trim();
                
                if (trimmed !== '') {
                    $('#donation-stripe-clear-search').show();
                } else {
                    $('#donation-stripe-clear-search').hide();
                }

                loadDonations(1, trimmed);
            });

            // 2. Busca em tempo real ao digitar (mínimo 3 caracteres)
            $('body').on('input', '#donation_search', function () {
                var search = $(this).val();
                var trimmed = search.trim();

                if (trimmed.length >= 3) {
                    $('#donation-stripe-clear-search').show();
                    loadDonations(1, trimmed);
                    return;
                }

                if (trimmed.length === 0) {
                    $('#donation-stripe-clear-search').hide();
                    loadDonations(1, '');
                }
            });

            // 3. Clique no Botão Limpar
            $('body').on('click', '#donation-stripe-clear-search', function (e) {
                e.preventDefault();
                
                // Abortar qualquer busca em andamento
                if (searchXhr && searchXhr.readyState !== 4) {
                    searchXhr.abort();
                }

                $('#donation_search').val('');
                $(this).hide();
                lastQuery = ''; // Resetar query anterior
                loadDonations(1, '');
            });

            // 4. Clique na Paginação
            $('body').on('click', '.donation-stripe-pagination a', function (e) {
                e.preventDefault(); 
                
                var page = $(this).data('page');
                
                // Fallback: tentar extrair da URL do link
                if (!page) {
                    var href = $(this).attr('href');
                    if (href) {
                        var match = href.match(/donation_page=(\d+)/);
                        if (match && match[1]) {
                            page = match[1];
                        }
                    }
                }
                
                if (!page) page = 1;
                
                var search = $('#donation_search').val();
                var trimmed = (search || '').trim();
                
                // Usar lastQuery se o input atual for inválido (ex: < 3 chars e > 0)
                // Se input vazio, busca vazia. Se >= 3, busca input.
                var searchToUse = trimmed;
                if (trimmed.length > 0 && trimmed.length < 3) {
                    // Se o usuário digitou 1 ou 2 letras e clicou na paginação (raro, mas possível se ele apagou)
                    // Melhor usar vazio ou lastQuery? 
                    // Se ele apagou para 2 letras, a tabela ainda mostra o resultado de 3 letras (lastQuery) ou vazio?
                    // Com a lógica de input, se < 3 e > 0, não faz busca. A tabela mantém o anterior.
                    searchToUse = lastQuery; 
                }

                loadDonations(page, searchToUse);
            });

            // --- Inicialização ---
            fetchStatuses();
            updateInterval = setInterval(fetchStatuses, 30000);

            $(window).on('beforeunload', function () {
                if (updateInterval) clearInterval(updateInterval);
            });
            
            // Popstate para botão voltar
            window.addEventListener('popstate', function(event) {
               window.location.reload(); 
            });
        }
    });
})(jQuery);
