/**
 * PAXdesign Booking - Admin Script
 * Team Management + Settings Interface
 * Version: 2.4.2
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ── Settings page ──────────────────────────────────────────
        // Only runs when the settings form is present on the page.
        if ($('#paxdesignSettingsForm').length) {
            initSettingsPage();
        }

        // ── Team management page ───────────────────────────────────
        // Only runs when the team table is present on the page.
        if (!$('#paxdesignTeamTableBody').length) { return; }

        // Initialize sortable
        $('#paxdesignTeamTableBody').sortable({
            handle: '.paxdesign-col-drag',
            placeholder: 'ui-sortable-placeholder',
            helper: function(e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            update: function(event, ui) {
                updateOrderNumbers();
                updateStatistics();
            }
        });
        
        // Update order numbers after sorting
        function updateOrderNumbers() {
            $('#paxdesignTeamTableBody tr').each(function(index) {
                $(this).find('.paxdesign-order-number').text(index + 1);
            });
        }
        
        // Update statistics
        function updateStatistics() {
            var total = $('#paxdesignTeamTableBody tr').length;
            var enabled = $('#paxdesignTeamTableBody tr').find('.paxdesign-enabled-toggle:checked').length;
            var disabled = total - enabled;
            var withServices = $('#paxdesignTeamTableBody tr').find('.pa-pill-green').length;
            
            $('#paxdesignStatTotal').text(total);
            $('#paxdesignStatEnabled').text(enabled);
            $('#paxdesignStatDisabled').text(disabled);
            $('#paxdesignStatServices').text(withServices);
        }
        
        // Handle toggle changes — dim row + update status dot
        $('.paxdesign-enabled-toggle').on('change', function() {
            var $row = $(this).closest('tr');
            var on = $(this).is(':checked');
            $row.toggleClass('pa-row-off', !on);
            $row.find('.pa-online').toggleClass('on', on).toggleClass('off', !on);
            updateStatistics();
        });
        
        // Show save status
        function showSaveStatus(type, message) {
            var $status = $('#paxdesignSaveStatus');
            $status.removeClass('success error saving show');
            $status.addClass(type + ' show');
            $status.text(message);

            if (type !== 'saving') {
                setTimeout(function() {
                    $status.removeClass('show');
                }, 3000);
            }
        }

        function setBusyState($element, busy) {
            if (!$element || !$element.length) return;
            $element.toggleClass('is-loading', !!busy);
            $element.attr('aria-busy', busy ? 'true' : 'false');
        }
        
        // Collect settings data
        function collectSettings() {
            var settings = {};
            
            $('#paxdesignTeamTableBody tr').each(function(index) {
                var memberId = $(this).data('member-id');
                var enabled = $(this).find('.paxdesign-enabled-toggle').is(':checked');
                var availability = $(this).find('.paxdesign-availability-select').val();
                
                settings[memberId] = {
                    enabled: enabled,
                    order: index + 1,
                    availability: availability
                };
            });
            
            return settings;
        }
        
        // Handle form submission
        $('#paxdesignTeamManagementForm').on('submit', function(e) {
            e.preventDefault();
            
            var settings = collectSettings();
            var $form = $('#paxdesignTeamManagementForm');
            var $submit = $form.find('button[type="submit"]').first();
            
            showSaveStatus('saving', 'Speichern…');
            setBusyState($form, true);
            setBusyState($submit, true);
            
            $.ajax({
                url: paxdesignAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'paxdesign_update_team_settings',
                    nonce: paxdesignAdmin.nonce,
                    settings: JSON.stringify(settings)
                },
                success: function(response) {
                    if (response.success) {
                        showSaveStatus('success', '✓ Gespeichert – live auf der Website aktiv');
                        formChanged = false;
                        // Flush any WordPress object cache so the next frontend
                        // request gets the updated team data immediately.
                        if (typeof wp !== 'undefined' && wp.apiFetch) {
                            wp.apiFetch({ path: '/' }); // warm the cache
                        }
                    } else {
                        showSaveStatus('error', '✗ Speichern fehlgeschlagen');
                    }
                },
                error: function() {
                    showSaveStatus('error', '✗ Verbindungsfehler');
                },
                complete: function() {
                    setBusyState($submit, false);
                    setBusyState($form, false);
                }
            });
        });
        
        // Keyboard shortcut: Ctrl+S / Cmd+S to save
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                $('#paxdesignTeamManagementForm').submit();
            }
        });
        
        // Confirm before leaving with unsaved changes
        var formChanged = false;
        
        $('.paxdesign-enabled-toggle, .paxdesign-availability-select').on('change', function() {
            formChanged = true;
        });
        
        $('#paxdesignTeamTableBody').on('sortupdate', function() {
            formChanged = true;
        });
        
        $('#paxdesignTeamManagementForm').on('submit', function() {
            formChanged = false;
        });
        
        $(window).on('beforeunload', function() {
            if (formChanged) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

    }); // end document.ready

    // ── Settings page initialisation ──────────────────────────────
    function initSettingsPage() {

        // SMTP toggle show/hide
        $('#paxdesign_smtp_enabled').on('change', function() {
            var on = $(this).is(':checked');
            $('#paxdesignSmtpFields').toggle(on);
            $('#paxdesignSmtpToggleLabel').text(on ? 'Aktiv' : 'Inaktiv').toggleClass('on', on);
        });

        // Chat toggle show/hide
        $('#paxdesign_chat_enabled').on('change', function() {
            var on = $(this).is(':checked');
            $('#paxdesignChatFields').toggle(on);
            $('#paxdesignChatToggleLabel').text(on ? 'Aktiv' : 'Inaktiv').toggleClass('on', on);
        });

        $('#paxdesign_chat_show_prices').on('change', function() {
            $('#paxdesignChatPriceHintsWrap').toggle($(this).is(':checked'));
        });

        // Password visibility toggle
        $(document).on('click', '.paxdesign-toggle-pass', function() {
            var $inp = $(this).siblings('input');
            $inp.attr('type', $inp.attr('type') === 'password' ? 'text' : 'password');
        });

        // Provider quick-fill chips
        $(document).on('click', '.paxdesign-chip', function() {
            var $b = $(this);
            $('#paxdesign_smtp_host').val($b.data('host'));
            $('#paxdesign_smtp_port').val($b.data('port'));
            $('#paxdesign_smtp_encryption').val($b.data('enc'));
            $('.paxdesign-chip').removeClass('active');
            $b.addClass('active');
        });

        // OpenAI connection test
        $('#paxdesignTestOpenAI').on('click', function() {
            var $btn = $(this);
            var $res = $('#paxdesignOpenAITestResult');
            $btn.prop('disabled', true).text('Teste\u2026');
            $res.removeClass('ok error').text('');
            setBusyState($btn, true);
            setBusyState($res, true);
            $.post(paxdesignAdmin.ajaxUrl, {
                action: 'paxdesign_test_openai',
                nonce:  paxdesignAdmin.nonce
            }, function(r) {
                if (r.success) {
                    var msg = r.data.message || 'Verbindung erfolgreich.';
                    if (r.data.model) {
                        msg += ' (Modell: ' + r.data.model + ')';
                    }
                    $res.addClass('ok').text('\u2713 ' + msg);
                } else {
                    $res.addClass('error').text('\u2717 ' + (r.data && r.data.message ? r.data.message : 'Test fehlgeschlagen.'));
                }
            }).fail(function() {
                $res.addClass('error').text('\u2717 Verbindungsfehler');
            }).always(function() {
                setBusyState($res, false);
                setBusyState($btn, false);
                $btn.prop('disabled', false).text('Verbindung testen');
            });
        });

        // Test email send
        $('#paxdesignSendTestEmail').on('click', function() {
            var $btn = $(this);
            var $res = $('#paxdesignTestResult');
            var to   = $('#paxdesignTestEmailTo').val() || paxdesignAdmin.notifEmail;
            $btn.prop('disabled', true).text('Sende\u2026');
            $res.removeClass('ok error').text('');
            setBusyState($btn, true);
            setBusyState($res, true);
            $.post(paxdesignAdmin.ajaxUrl, {
                action: 'paxdesign_send_test_email',
                nonce:  paxdesignAdmin.nonce,
                to:     to
            }, function(r) {
                $res.addClass(r.success ? 'ok' : 'error')
                    .text((r.success ? '\u2713 ' : '\u2717 ') + r.data.message);
            }).fail(function() {
                $res.addClass('error').text('\u2717 Verbindungsfehler');
            }).always(function() {
                setBusyState($res, false);
                setBusyState($btn, false);
                $btn.prop('disabled', false).text('Test senden');
            });
        });
    }

})(jQuery);
