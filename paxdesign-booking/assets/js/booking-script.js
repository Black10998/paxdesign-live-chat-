/**
 * PAXdesign Booking System JavaScript
 * Version: 3.3.2 — scoped to #paxdesign-booking-root
 */

(function($) {
    'use strict';

    var ROOT = '#paxdesign-booking-root';
    var $root = null;

    function root() {
        if (!$root || !$root.length) {
            $root = $(ROOT);
        }
        return $root;
    }

    function $in(selector) {
        return root().find(selector);
    }
    
    let bookingData = {
        member: null,
        service: null,
        serviceDetails: null,
        date: null,
        time: null,
        currentStep: 1
    };
    
    let currentMonth = new Date();
    let selectedDate = null;
    let preselectedServiceKey = null;
    let currentWidgetMode = 'chat';

    var WIDGET_MODE_TITLES = {
        hub:     { title: 'Live Chat',        subtitle: 'PAXdesign · Support & Beratung' },
        booking: { title: 'Termin buchen',    subtitle: 'PAXdesign · Kostenlose Erstberatung' },
        chat:    { title: 'Live Chat',        subtitle: 'PAXdesign · Support & Beratung' }
    };

    var PRESELECT_STORAGE_KEY = 'paxdesign-selected-service';
    var PRESELECT_DETAILS_STORAGE_KEY = 'paxdesign-selected-service-details';
    var scrollLockY = 0;
    var mobileViewportBound = false;

    // Live team member data — refreshed from server every time the widget opens
    // so availability changes in the admin are reflected immediately.
    let liveTeamMembers = paxdesignBooking.teamMembers;

    function refreshTeamMembers(callback) {
        $.post(paxdesignBooking.ajaxUrl, {
            action: 'paxdesign_get_team_members'
        }, function(response) {
            if (response && response.success && response.data && typeof response.data === 'object') {
                liveTeamMembers = response.data;
                // Keep paxdesignBooking.teamMembers in sync for any code that still reads it
                paxdesignBooking.teamMembers = liveTeamMembers;
            }
            // Always call callback — even if fetch failed, render with whatever data we have
            if (typeof callback === 'function') callback();
        }).fail(function() {
            // Network error — render with existing data
            if (typeof callback === 'function') callback();
        });
    }

    function renderTeamCards() {
        var $grid = $in('.paxdesign-booking-team-grid');
        $grid.empty();

        var availabilityLabels = {
            available:   'Verfügbar',
            busy:        'Beschäftigt',
            vacation:    'Im Urlaub',
            unavailable: 'Nicht verfügbar'
        };

        $.each(liveTeamMembers, function(key, member) {
            // Skip members that are disabled in the admin — they should not appear at all
            if (member.enabled === false || member.enabled === 0) {
                return true; // continue $.each
            }

            var avail      = member.availability || 'available';
            // A member is selectable only when their availability is 'available'
            var selectable = (avail === 'available');
            var badgeHtml  = (avail !== 'available')
                ? '<span class="paxdesign-availability-badge paxdesign-badge-' + avail + '">'
                  + (availabilityLabels[avail] || avail)
                  + '</span>'
                : '';

            var classes = 'paxdesign-booking-team-card paxdesign-availability-' + avail;
            if (!selectable) classes += ' paxdesign-not-selectable';
            if (member.is_founder) classes += ' paxdesign-booking-team-card--founder';

            var founderBadge = member.is_founder
                ? '<span class="paxdesign-booking-founder-badge">Gründer &amp; Inhaber</span>'
                : '';
            var roleEnHtml = member.role_en
                ? '<p class="paxdesign-booking-team-role-en">' + member.role_en + '</p>'
                : '';

            var $card = $(
                '<div class="' + classes + '"'
                + ' data-member="' + key + '"'
                + ' data-has-services="' + (member.has_services ? 'true' : 'false') + '"'
                + ' data-availability="' + avail + '"'
                + (!selectable ? ' data-disabled="true"' : '')
                + '>'
                + '<div class="paxdesign-booking-team-avatar">'
                + '<img src="' + member.image + '" alt="' + member.name + '">'
                + badgeHtml
                + '</div>'
                + '<div class="paxdesign-booking-team-info">'
                + founderBadge
                + '<h3 class="paxdesign-booking-team-name">' + member.name + '</h3>'
                + '<p class="paxdesign-booking-team-role">' + member.role + '</p>'
                + roleEnHtml
                + '</div>'
                + '</div>'
            );

            $grid.append($card);
        });
    }

    function getPreselectedServiceName() {
        try {
            return sessionStorage.getItem(PRESELECT_STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function clearPreselectedServiceName() {
        try {
            sessionStorage.removeItem(PRESELECT_STORAGE_KEY);
            sessionStorage.removeItem(PRESELECT_DETAILS_STORAGE_KEY);
        } catch (e) {}
    }

    function extractServiceDetailsFromTrigger($btn) {
        var $card = $btn.closest('.card-container[data-card]');
        var name = $.trim($btn.attr('data-service') || '');
        var cardId = $card.length ? $.trim($card.attr('data-card') || '') : '';
        var description = $card.length ? $.trim($card.find('.card-description').first().text()) : '';
        var features = [];

        if ($card.length) {
            $card.find('.features-list li').each(function() {
                var featureText = $.trim($(this).text());
                if (featureText) {
                    features.push(featureText);
                }
            });

            if (!name) {
                name = $.trim($card.find('.card-title').first().text());
            }
        }

        var category = '';
        if (cardId && cardId.indexOf('sec') === 0) {
            category = 'Code & Asset Protection';
        }

        if (!name) {
            return null;
        }

        return {
            name: name,
            cardId: cardId,
            description: description,
            features: features,
            category: category
        };
    }

    function buildServiceDetailsFromKey(serviceKey) {
        if (!serviceKey || !paxdesignBooking.services || !paxdesignBooking.services[serviceKey]) {
            return null;
        }

        var service = paxdesignBooking.services[serviceKey];

        return {
            name: service.name,
            cardId: serviceKey,
            description: service.description || '',
            features: service.features || [],
            category: service.category || ''
        };
    }

    function mergeServiceDetails(primary, fallback) {
        if (!primary && !fallback) {
            return null;
        }
        if (!primary) {
            return fallback;
        }
        if (!fallback) {
            return primary;
        }

        return {
            name: primary.name || fallback.name,
            cardId: primary.cardId || fallback.cardId,
            description: primary.description || fallback.description,
            features: (primary.features && primary.features.length) ? primary.features : (fallback.features || []),
            category: primary.category || fallback.category
        };
    }

    function loadPreselectedServiceDetails() {
        var parsed = null;

        try {
            var raw = sessionStorage.getItem(PRESELECT_DETAILS_STORAGE_KEY);
            if (raw) {
                parsed = JSON.parse(raw);
            }
        } catch (e) {
            parsed = null;
        }

        var serviceName = getPreselectedServiceName();
        var fallbackKey = resolveServiceKey(serviceName);
        var fallback = fallbackKey ? buildServiceDetailsFromKey(fallbackKey) : null;

        return mergeServiceDetails(parsed, fallback);
    }

    function setBookingServiceDetails(details, serviceKey) {
        if (serviceKey) {
            bookingData.service = serviceKey;
        } else if (details) {
            bookingData.service = resolveServiceKey(details.cardId || details.name);
        }

        if (details) {
            bookingData.serviceDetails = details;
            return;
        }

        if (bookingData.service) {
            bookingData.serviceDetails = buildServiceDetailsFromKey(bookingData.service);
        }
    }

    function renderServiceDetailsPanel($target, details) {
        if (!$target || !$target.length) {
            return;
        }

        $target.empty();

        if (!details) {
            return;
        }

        $target.append($('<strong class="paxdesign-booking-summary-service-name"></strong>').text(details.name));

        if (details.description) {
            $target.append($('<span class="paxdesign-booking-summary-service-description"></span>').text(details.description));
        }

        if (details.features && details.features.length) {
            var $list = $('<ul class="paxdesign-booking-summary-service-features"></ul>');
            details.features.forEach(function(feature) {
                $list.append($('<li></li>').text(feature));
            });
            $target.append($('<span class="paxdesign-booking-summary-service-details-label"></span>').text('Details:'));
            $target.append($list);
        }

        if (details.category) {
            $target.append(
                $('<span class="paxdesign-booking-summary-service-category"></span>').text('Kategorie: ' + details.category)
            );
        }
    }

    function resolveServiceKey(nameOrKey) {
        if (!nameOrKey) {
            return null;
        }

        if (paxdesignBooking.services && paxdesignBooking.services[nameOrKey]) {
            return nameOrKey;
        }

        if (paxdesignBooking.serviceNameMap && paxdesignBooking.serviceNameMap[nameOrKey]) {
            return paxdesignBooking.serviceNameMap[nameOrKey];
        }

        var services = paxdesignBooking.services || {};
        for (var key in services) {
            if (services.hasOwnProperty(key) && services[key].name === nameOrKey) {
                return key;
            }
        }

        return null;
    }

    function getSelectableMembers() {
        var members = [];
        $.each(liveTeamMembers, function(key, member) {
            if (member.enabled === false || member.enabled === 0) {
                return true;
            }
            if ((member.availability || 'available') !== 'available') {
                return true;
            }
            members.push({ key: key, member: member });
        });
        return members;
    }

    function findServiceMemberKey() {
        var selectable = getSelectableMembers();
        var i;

        for (i = 0; i < selectable.length; i++) {
            if (selectable[i].member.has_services) {
                return selectable[i].key;
            }
        }

        return selectable.length === 1 ? selectable[0].key : null;
    }

    function activateStep(step) {
        $in('.paxdesign-booking-content').removeClass('paxdesign-is-active');
        $in('.paxdesign-booking-content[data-step="' + step + '"]').addClass('paxdesign-is-active');
        bookingData.currentStep = step;
        updateStepIndicator();
        updateSelectedServiceBanner();
    }

    function updateSelectedServiceBanner() {
        var $banner = $in('#paxdesignSelectedServiceBanner');
        if (!$banner.length) {
            return;
        }

        var details = bookingData.serviceDetails;
        if (!details && bookingData.service) {
            details = buildServiceDetailsFromKey(bookingData.service);
            bookingData.serviceDetails = details;
        }

        if (!details) {
            $banner.addClass('paxdesign-booking-is-hidden');
            $in('#paxdesignSelectedServiceName').text('');
            $in('#paxdesignSelectedServiceDescription').text('').addClass('paxdesign-booking-is-hidden');
            $in('#paxdesignSelectedServiceFeatures').empty();
            $in('#paxdesignSelectedServiceDetailsWrap').addClass('paxdesign-booking-is-hidden');
            $in('#paxdesignSelectedServiceCategory').text('').addClass('paxdesign-booking-is-hidden');
            return;
        }

        $in('#paxdesignSelectedServiceName').text(details.name);

        var $description = $in('#paxdesignSelectedServiceDescription');
        if (details.description) {
            $description.text(details.description).removeClass('paxdesign-booking-is-hidden');
        } else {
            $description.text('').addClass('paxdesign-booking-is-hidden');
        }

        var $features = $in('#paxdesignSelectedServiceFeatures');
        $features.empty();
        if (details.features && details.features.length) {
            details.features.forEach(function(feature) {
                $features.append($('<li></li>').text(feature));
            });
            $in('#paxdesignSelectedServiceDetailsWrap').removeClass('paxdesign-booking-is-hidden');
        } else {
            $in('#paxdesignSelectedServiceDetailsWrap').addClass('paxdesign-booking-is-hidden');
        }

        var $category = $in('#paxdesignSelectedServiceCategory');
        if (details.category) {
            $category.text('Kategorie: ' + details.category).removeClass('paxdesign-booking-is-hidden');
        } else if (details.cardId) {
            $category.text('Service-ID: ' + details.cardId).removeClass('paxdesign-booking-is-hidden');
        } else {
            $category.text('').addClass('paxdesign-booking-is-hidden');
        }

        $banner.removeClass('paxdesign-booking-is-hidden');
    }

    function applyPreselectedServiceFlow() {
        var details = loadPreselectedServiceDetails();
        if (!details) {
            return false;
        }

        var serviceKey = resolveServiceKey(details.cardId || details.name);
        if (!serviceKey) {
            return false;
        }

        var memberKey = findServiceMemberKey();
        if (!memberKey) {
            return false;
        }

        preselectedServiceKey = serviceKey;
        bookingData.member = memberKey;
        setBookingServiceDetails(details, serviceKey);

        var memberData = liveTeamMembers[memberKey] || paxdesignBooking.teamMembers[memberKey];
        if (memberData) {
            $('#paxdesignSelectedMemberName').text(memberData.name);
        }

        $in('.paxdesign-booking-team-card').removeClass('paxdesign-is-selected');
        $in('.paxdesign-booking-team-card[data-member="' + memberKey + '"]').addClass('paxdesign-is-selected');
        $in('.paxdesign-booking-service-card').removeClass('paxdesign-is-selected');
        $in('.paxdesign-booking-service-card[data-service="' + serviceKey + '"]').addClass('paxdesign-is-selected');

        clearPreselectedServiceName();
        activateStep(2);
        renderCalendar();
        return true;
    }

    function storePreselectedServiceFromTrigger($btn) {
        var details = extractServiceDetailsFromTrigger($btn);
        if (!details) {
            return;
        }

        try {
            sessionStorage.setItem(PRESELECT_STORAGE_KEY, details.name);
            sessionStorage.setItem(PRESELECT_DETAILS_STORAGE_KEY, JSON.stringify(details));
        } catch (e) {}
    }
    
    $(document).ready(function() {
        if (!root().length) {
            return;
        }
        initBookingSystem();
    });
    
    function initBookingSystem() {
        var $container = root();

        // Capture service selection from pricing/service cards before the widget opens.
        $(document).on('click', '.paxdesign-booking-trigger', function() {
            storePreselectedServiceFromTrigger($(this));

            // Fallback when external code opens the widget directly (without our button handler).
            setTimeout(function() {
                var $widget = $in('.paxdesign-booking-widget');
                if (!$widget.hasClass('paxdesign-is-active')) {
                    return;
                }
                refreshTeamMembers(function() {
                    renderTeamCards();
                    setTimeout(function() {
                        applyPreselectedServiceFlow();
                    }, 50);
                });
            }, 120);
        });

        // Toggle chat widget — refresh team data on every open for live availability
        $container.on('click', '.paxdesign-booking-button', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $widget = $in('.paxdesign-booking-widget');
            var opening = !$widget.hasClass('paxdesign-is-active');

            if (opening) {
                openWidget();
            } else {
                closeDialog();
            }
        });
        
        // Close widget
        $container.on('click', '.paxdesign-booking-close', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeDialog();
        });
        
        // Team selection
        $container.on('click', '.paxdesign-booking-team-card', function(e) {
            e.preventDefault();
            
            // Check if member is selectable
            if ($(this).hasClass('paxdesign-not-selectable') || $(this).data('disabled')) {
                // Show message that member is not available
                const availability = $(this).data('availability');
                let message = 'Dieser Mitarbeiter ist derzeit nicht verfügbar.';
                
                if (availability === 'busy') {
                    message = 'Dieser Mitarbeiter ist derzeit beschäftigt. Bitte wählen Sie einen anderen Ansprechpartner.';
                } else if (availability === 'vacation') {
                    message = 'Dieser Mitarbeiter ist im Urlaub. Bitte wählen Sie einen anderen Ansprechpartner.';
                } else if (availability === 'unavailable') {
                    message = 'Dieser Mitarbeiter ist nicht verfügbar. Bitte wählen Sie einen anderen Ansprechpartner.';
                }
                
                alert(message);
                return false;
            }
            
            const member = $(this).data('member');
            const hasServices = $(this).data('has-services');
            selectTeamMember(member, hasServices);
        });
        
        // Service selection
        $container.on('click', '.paxdesign-booking-service-card', function(e) {
            e.preventDefault();
            const service = $(this).data('service');
            selectService(service);
        });
        
        // Navigation
        $container.on('click', '.paxdesign-booking-btn-next', function(e) {
            e.preventDefault();
            nextStep();
        });
        
        $container.on('click', '.paxdesign-booking-btn-back', function(e) {
            e.preventDefault();
            previousStep();
        });
        
        $container.on('click', '.paxdesign-booking-btn-submit', function(e) {
            e.preventDefault();
            submitBooking();
        });
        
        // Calendar navigation
        $container.on('click', '.paxdesign-booking-calendar-nav.paxdesign-nav-prev', function(e) {
            e.preventDefault();
            previousMonth();
        });
        
        $container.on('click', '.paxdesign-booking-calendar-nav.paxdesign-nav-next', function(e) {
            e.preventDefault();
            nextMonth();
        });
        
        $container.on('change', '#paxdesignBookingPrivacy', function() {
            clearFormError();
        });

        // Keep widget interactions inside the plugin root
        $container.on('click', '.paxdesign-booking-widget', function(e) {
            e.stopPropagation();
        });

        // Mode switch: Booking ↔ KI-Assistent
        $container.on('click', '.paxdesign-booking-mode-btn', function(e) {
            e.preventDefault();
            switchWidgetMode($(this).data('mode'));
        });
    }

    function switchWidgetMode(mode) {
        if (!mode || mode === currentWidgetMode) return;
        currentWidgetMode = mode;

        $in('.paxdesign-booking-mode-btn').removeClass('paxdesign-is-active').attr('aria-selected', 'false');
        $in('.paxdesign-booking-mode-btn[data-mode="' + mode + '"]').addClass('paxdesign-is-active').attr('aria-selected', 'true');

        $in('.paxdesign-booking-mode-panel').removeClass('paxdesign-is-active').attr('aria-hidden', 'true');
        $in('.paxdesign-booking-mode-panel[data-mode="' + mode + '"]').addClass('paxdesign-is-active').attr('aria-hidden', 'false');

        root().toggleClass('paxdesign-mobile-chat-mode', mode === 'chat' && isMobileViewport());
        root().toggleClass('paxdesign-chat-mode-active', mode === 'chat');
        adjustMobileLayout();

        var titles = WIDGET_MODE_TITLES[mode];
        if (titles && $in('#paxdesignWidgetTitle').length) {
            $in('#paxdesignWidgetTitle').text(titles.title);
            $in('#paxdesignWidgetSubtitle').text(titles.subtitle);
        }

        if (mode === 'chat') {
            if (window.PAXdesignChat && typeof window.PAXdesignChat.init === 'function') {
                window.PAXdesignChat.init();
            }
            if (window.PAXdesignChat && typeof window.PAXdesignChat.onOpen === 'function') {
                window.PAXdesignChat.onOpen();
            }
            if (!isMobileViewport()) {
                setTimeout(function() {
                    var $input = $in('.paxdesign-booking-chat-input');
                    if (!$input.closest('#paxdesign-booking-root').hasClass('paxdesign-chat-entry-active')) {
                        $input.focus();
                    }
                }, 120);
            }
        }

        if (mode === 'booking' && window.PAXdesignChat && typeof window.PAXdesignChat.abort === 'function') {
            window.PAXdesignChat.abort();
        }
    }

    function resetWidgetMode() {
        var hasChat = $in('.paxdesign-booking-mode-switch').length > 0;
        currentWidgetMode = hasChat ? 'chat' : 'booking';
        root().removeClass('paxdesign-mobile-chat-mode paxdesign-chat-mode-active');

        if (!$in('.paxdesign-booking-mode-switch').length) return;

        $in('.paxdesign-booking-mode-btn').removeClass('paxdesign-is-active').attr('aria-selected', 'false');
        $in('.paxdesign-booking-mode-btn[data-mode="' + currentWidgetMode + '"]').addClass('paxdesign-is-active').attr('aria-selected', 'true');

        $in('.paxdesign-booking-mode-panel').removeClass('paxdesign-is-active').attr('aria-hidden', 'true');
        $in('.paxdesign-booking-mode-panel[data-mode="' + currentWidgetMode + '"]').addClass('paxdesign-is-active').attr('aria-hidden', 'false');

        var titles = WIDGET_MODE_TITLES[currentWidgetMode] || WIDGET_MODE_TITLES.chat;
        if ($in('#paxdesignWidgetTitle').length) {
            $in('#paxdesignWidgetTitle').text(titles.title);
            $in('#paxdesignWidgetSubtitle').text(titles.subtitle);
        }

        if (window.PAXdesignChat && typeof window.PAXdesignChat.abort === 'function') {
            window.PAXdesignChat.abort();
        }
    }
    
    function isMobileViewport() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function lockPageScroll() {
        if (!isMobileViewport()) return;
        scrollLockY = window.scrollY || window.pageYOffset || 0;
        $('html').addClass('paxdesign-scroll-lock');
    }

    function unlockPageScroll() {
        if (!$('html').hasClass('paxdesign-scroll-lock')) return;
        $('html').removeClass('paxdesign-scroll-lock');
        window.scrollTo(0, scrollLockY);
    }

    function bindMobileViewportGuard() {
        if (mobileViewportBound) return;
        mobileViewportBound = true;

        var onViewportChange = function() {
            adjustMobileLayout();
        };

        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', onViewportChange);
            window.visualViewport.addEventListener('scroll', onViewportChange);
        }
        window.addEventListener('orientationchange', function() {
            setTimeout(adjustMobileLayout, 100);
        });
    }

    function adjustMobileLayout() {
        var $widget = $in('.paxdesign-booking-widget');
        if (!$widget.hasClass('paxdesign-is-active') || !isMobileViewport()) {
            $widget.css({ top: '', bottom: '', maxHeight: '' });
            root().removeClass('paxdesign-keyboard-open');
            return;
        }

        var rootEl = root()[0];
        var styles = rootEl ? getComputedStyle(rootEl) : null;
        var headerClear = styles ? parseFloat(styles.getPropertyValue('--pax-mobile-header-clearance')) || 96 : 96;
        var launcherClear = styles ? parseFloat(styles.getPropertyValue('--pax-mobile-launcher-clearance')) || 92 : 92;
        var isChat = root().hasClass('paxdesign-mobile-chat-mode');
        var capVar = isChat ? '--pax-mobile-widget-max-chat' : '--pax-mobile-widget-max-booking';
        var absoluteCap = styles ? parseFloat(styles.getPropertyValue(capVar)) || (isChat ? 280 : 420) : (isChat ? 280 : 420);

        if (window.visualViewport) {
            var vv = window.visualViewport;
            var keyboardOpen = isChat && (vv.height < window.innerHeight * 0.75);

            if (keyboardOpen) {
                root().addClass('paxdesign-keyboard-open');
                var topPos = vv.offsetTop + 8;
                var availHeight = vv.height - 16;
                var maxH = Math.min(absoluteCap, availHeight);

                $widget.css({
                    top: topPos + 'px',
                    bottom: 'auto',
                    maxHeight: Math.max(160, maxH) + 'px'
                });
                return;
            }
        }

        root().removeClass('paxdesign-keyboard-open');
        var viewportH = window.visualViewport ? window.visualViewport.height : window.innerHeight;
        var computed = Math.min(absoluteCap, viewportH - headerClear - launcherClear - 16);

        $widget.css({
            top: 'auto',
            bottom: '',
            maxHeight: Math.max(200, computed) + 'px'
        });
    }

    window.PAXdesignBookingMobile = {
        adjustLayout: adjustMobileLayout
    };

    var CHAT_NOTE_STORAGE_KEY = 'paxdesign-chat-booking-note';

    function prefillChatBookingNote() {
        var note = '';
        try {
            note = sessionStorage.getItem(CHAT_NOTE_STORAGE_KEY) || '';
        } catch (e) {}
        if (!note) {
            return;
        }
        var $field = $in('#paxdesignBookingMessage');
        if ($field.length && !$field.val()) {
            $field.val(note);
        }
        try {
            sessionStorage.removeItem(CHAT_NOTE_STORAGE_KEY);
        } catch (e2) {}
    }

    function openBookingFromChat(options) {
        options = options || {};
        var $widget = $in('.paxdesign-booking-widget');

        if (!$widget.hasClass('paxdesign-is-active')) {
            openWidget();
        }

        if (window.PAXdesignChat && typeof window.PAXdesignChat.abort === 'function') {
            window.PAXdesignChat.abort();
        }

        switchWidgetMode('booking');

        var serviceName = $.trim(options.service || '');
        var message = $.trim(options.message || '');

        if (message) {
            try {
                sessionStorage.setItem(CHAT_NOTE_STORAGE_KEY, message);
            } catch (e) {}
        }

        if (serviceName) {
            var serviceKey = resolveServiceKey(serviceName);
            var details = serviceKey ? buildServiceDetailsFromKey(serviceKey) : null;
            if (!details && serviceName) {
                details = {
                    name: serviceName,
                    cardId: serviceKey || '',
                    description: '',
                    features: [],
                    category: ''
                };
            }
            if (details) {
                try {
                    sessionStorage.setItem(PRESELECT_STORAGE_KEY, details.name);
                    sessionStorage.setItem(PRESELECT_DETAILS_STORAGE_KEY, JSON.stringify(details));
                } catch (e2) {}
            }
        }

        refreshTeamMembers(function() {
            renderTeamCards();
            setTimeout(function() {
                if (!applyPreselectedServiceFlow()) {
                    activateStep(1);
                }
                prefillChatBookingNote();
            }, 80);
        });
    }

    window.PAXdesignBooking = {
        openFromChat: openBookingFromChat,
        switchMode: switchWidgetMode
    };

    function openWidget() {
        var $widget = $in('.paxdesign-booking-widget');
        $widget.addClass('paxdesign-is-active').attr('aria-hidden', 'false');
        root().addClass('paxdesign-widget-open');

        if ($in('.paxdesign-booking-mode-switch').length) {
            openChatOrganizer();
        } else {
            root().toggleClass('paxdesign-mobile-chat-mode', currentWidgetMode === 'chat' && isMobileViewport());
        }

        lockPageScroll();
        bindMobileViewportGuard();
        adjustMobileLayout();

        refreshTeamMembers(function() {
            renderTeamCards();
            if ($in('#paxdesignCalendarDays').children().length === 0) {
                renderCalendar();
            }
            setTimeout(function() {
                applyPreselectedServiceFlow();
            }, 50);
        });
    }

    /** Open Live Chat panel and run entry organizer (Live Agent vs KI). */
    function openChatOrganizer() {
        currentWidgetMode = 'chat';

        $in('.paxdesign-booking-mode-btn').removeClass('paxdesign-is-active').attr('aria-selected', 'false');
        $in('.paxdesign-booking-mode-btn[data-mode="chat"]').addClass('paxdesign-is-active').attr('aria-selected', 'true');

        $in('.paxdesign-booking-mode-panel').removeClass('paxdesign-is-active').attr('aria-hidden', 'true');
        $in('.paxdesign-booking-mode-panel[data-mode="chat"]').addClass('paxdesign-is-active').attr('aria-hidden', 'false');

        root().toggleClass('paxdesign-mobile-chat-mode', isMobileViewport());
        root().addClass('paxdesign-chat-mode-active');
        adjustMobileLayout();

        var titles = WIDGET_MODE_TITLES.chat;
        if ($in('#paxdesignWidgetTitle').length) {
            $in('#paxdesignWidgetTitle').text(titles.title);
            $in('#paxdesignWidgetSubtitle').text(titles.subtitle);
        }

        if (window.PAXdesignChat) {
            if (typeof window.PAXdesignChat.init === 'function') {
                window.PAXdesignChat.init();
            }
            if (typeof window.PAXdesignChat.onOpen === 'function') {
                window.PAXdesignChat.onOpen();
            }
        }
    }

    function closeDialog() {
        if (root().hasClass('paxdesign-chat-mode-active') && window.PAXdesignChat && typeof window.PAXdesignChat.onClose === 'function') {
            window.PAXdesignChat.onClose();
        }
        $in('.paxdesign-booking-widget').removeClass('paxdesign-is-active').attr('aria-hidden', 'true');
        $in('.paxdesign-booking-widget').css({ top: '', bottom: '', maxHeight: '' });
        root().removeClass('paxdesign-widget-open paxdesign-mobile-chat-mode paxdesign-keyboard-open');
        unlockPageScroll();
        resetWidgetMode();
        
        setTimeout(function() {
            bookingData = {
                member: null,
                service: null,
                serviceDetails: null,
                date: null,
                time: null,
                currentStep: 1
            };
            selectedDate = null;
            preselectedServiceKey = null;
            
            $in('.paxdesign-booking-content').removeClass('paxdesign-is-active');
            $in('.paxdesign-booking-content[data-step="1"]').addClass('paxdesign-is-active');
            $in('.paxdesign-booking-success').removeClass('paxdesign-is-active');
            $in('.paxdesign-booking-team-card').removeClass('paxdesign-is-selected');
            $in('.paxdesign-booking-service-card').removeClass('paxdesign-is-selected');
            updateSelectedServiceBanner();
            
            updateStepIndicator();
        }, 100);
    }
    
    function selectTeamMember(member, hasServices) {
        bookingData.member = member;
        
        $in('.paxdesign-booking-team-card').removeClass('paxdesign-is-selected');
        $in('.paxdesign-booking-team-card[data-member="' + member + '"]').addClass('paxdesign-is-selected');
        
        const memberData = liveTeamMembers[member] || paxdesignBooking.teamMembers[member];
        if (memberData) $('#paxdesignSelectedMemberName').text(memberData.name);
        
        setTimeout(function() {
            if (hasServices === true || hasServices === 'true') {
                // Show service selection for members with services
                renderServices();
                $in('.paxdesign-booking-content[data-step="1"]').removeClass('paxdesign-is-active');
                $in('.paxdesign-booking-content[data-step="1.5"]').addClass('paxdesign-is-active');
            } else {
                // Skip to date selection for others
                nextStep();
            }
        }, 200);
    }
    
    function getServiceIconHtml(iconKey) {
        const icons = paxdesignBooking.serviceIcons || {};
        const key = iconKey || 'website';
        return icons[key] || icons.website || '';
    }

    function renderServices() {
        const $servicesGrid = $in('#paxdesignServicesGrid');
        $servicesGrid.empty();
        
        const services = paxdesignBooking.services;
        
        $.each(services, function(key, service) {
            const badge = service.popular ? '<span class="paxdesign-booking-service-badge paxdesign-badge-popular">Beliebt</span>' : 
                         service.premium ? '<span class="paxdesign-booking-service-badge paxdesign-badge-premium">Premium</span>' : '';
            const iconHtml = getServiceIconHtml(service.icon || key);
            
            const $card = $('<div class="paxdesign-booking-service-card" data-service="' + key + '">' +
                '<div class="paxdesign-booking-service-icon" aria-hidden="true">' + iconHtml + '</div>' +
                '<h4 class="paxdesign-booking-service-name">' + service.name + '</h4>' +
                '<p class="service-description">' + service.description + '</p>' +
                badge +
                '</div>');
            
            $servicesGrid.append($card);
        });
    }
    
    function selectService(service) {
        setBookingServiceDetails(buildServiceDetailsFromKey(service), service);
        
        $in('.paxdesign-booking-service-card').removeClass('paxdesign-is-selected');
        $in('.paxdesign-booking-service-card[data-service="' + service + '"]').addClass('paxdesign-is-selected');
        
        setTimeout(function() {
            $in('.paxdesign-booking-content[data-step="1.5"]').removeClass('paxdesign-is-active');
            bookingData.currentStep = 2;
            $in('.paxdesign-booking-content[data-step="2"]').addClass('paxdesign-is-active');
            updateStepIndicator();
            updateSelectedServiceBanner();
            renderCalendar();
        }, 200);
    }
    
    function updateStepIndicator() {
        $in('.paxdesign-booking-step-dot').each(function(index) {
            const dotStep = index + 1;
            $(this).removeClass('paxdesign-is-active paxdesign-is-completed');
            if (dotStep === bookingData.currentStep) {
                $(this).addClass('paxdesign-is-active');
            } else if (dotStep < bookingData.currentStep) {
                $(this).addClass('paxdesign-is-completed');
            }
        });

        var tagStep = bookingData.currentStep;
        if (tagStep === 1.5) tagStep = 1;
        $in('.paxdesign-tag-step').removeClass('paxdesign-is-active');
        $in('.paxdesign-tag-step[data-step-tag="' + Math.min(tagStep, 3) + '"]').addClass('paxdesign-is-active');
    }
    
    function nextStep() {
        if (bookingData.currentStep < 3) {
            $in('.paxdesign-booking-content[data-step="' + bookingData.currentStep + '"]').removeClass('paxdesign-is-active');
            bookingData.currentStep++;
            $in('.paxdesign-booking-content[data-step="' + bookingData.currentStep + '"]').addClass('paxdesign-is-active');
            updateStepIndicator();
            
            if (bookingData.currentStep === 2) {
                renderCalendar();
            }
            
            if (bookingData.currentStep === 3) {
                updateSummary();
                prefillChatBookingNote();
            }
        }
    }
    
    function previousStep() {
        if (bookingData.currentStep <= 1) {
            return;
        }

        $in('.paxdesign-booking-content[data-step="' + bookingData.currentStep + '"]').removeClass('paxdesign-is-active');

        if (bookingData.currentStep === 2 && preselectedServiceKey) {
            bookingData.currentStep = 1;
        } else if (bookingData.currentStep === 2 && bookingData.service && !preselectedServiceKey) {
            bookingData.currentStep = 1.5;
        } else {
            bookingData.currentStep--;
        }

        $in('.paxdesign-booking-content[data-step="' + bookingData.currentStep + '"]').addClass('paxdesign-is-active');
        updateStepIndicator();
    }
    
    // Calendar functions
    function renderCalendar() {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        
        const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                           'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        
        $in('#paxdesignCalendarTitle').text(monthNames[month] + ' ' + year);
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const prevLastDay = new Date(year, month, 0);
        
        const firstDayIndex = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
        const lastDayDate = lastDay.getDate();
        const prevLastDayDate = prevLastDay.getDate();
        
        const $daysContainer = $in('#paxdesignCalendarDays');
        $daysContainer.empty();
        
        // Previous month days
        for (let i = firstDayIndex; i > 0; i--) {
            $daysContainer.append('<div class="paxdesign-booking-day paxdesign-is-other-month">' + (prevLastDayDate - i + 1) + '</div>');
        }
        
        // Current month days
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (let i = 1; i <= lastDayDate; i++) {
            const dayDate = new Date(year, month, i);
            dayDate.setHours(0, 0, 0, 0);
            
            const $day = $('<div class="paxdesign-booking-day">' + i + '</div>');
            
            // Disable past dates and weekends
            if (dayDate < today || dayDate.getDay() === 0 || dayDate.getDay() === 6) {
                $day.addClass('paxdesign-is-disabled');
            } else {
                $day.on('click', function() {
                    selectDate(dayDate);
                });
            }
            
            if (selectedDate && 
                dayDate.getDate() === selectedDate.getDate() &&
                dayDate.getMonth() === selectedDate.getMonth() &&
                dayDate.getFullYear() === selectedDate.getFullYear()) {
                $day.addClass('paxdesign-is-selected');
            }
            
            $daysContainer.append($day);
        }
        
        // Next month days
        const totalDays = $daysContainer.children().length;
        const remainingDays = 42 - totalDays;
        for (let i = 1; i <= remainingDays; i++) {
            $daysContainer.append('<div class="paxdesign-booking-day paxdesign-is-other-month">' + i + '</div>');
        }
    }
    
    function previousMonth() {
        currentMonth.setMonth(currentMonth.getMonth() - 1);
        renderCalendar();
    }
    
    function nextMonth() {
        currentMonth.setMonth(currentMonth.getMonth() + 1);
        renderCalendar();
    }
    
    function selectDate(date) {
        try {
            selectedDate = date;
            bookingData.date = formatDateISO(date);
            
            renderCalendar();
            renderTimeslots();
            
            const weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
            const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                           'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
            
            const dateStr = weekdays[date.getDay()] + ', ' + date.getDate() + '. ' + 
                           months[date.getMonth()] + ' ' + date.getFullYear();
            
            $in('#paxdesignSelectedDateDisplay').text(dateStr);
        } catch (error) {
            console.error('Error selecting date:', error);
        }
    }
    
    function renderTimeslots() {
        const $timeslotsGrid = $in('#paxdesignTimeslotsGrid');
        $timeslotsGrid.empty();
        
        const slots = [
            '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
            '13:00', '13:30', '14:00', '14:30', '15:00', '15:30',
            '16:00', '16:30', '17:00'
        ];
        
        slots.forEach(function(time) {
            const $slot = $('<div class="paxdesign-booking-timeslot">' + time + '</div>');
            
            $slot.on('click', function() {
                selectTime(time);
            });
            
            if (bookingData.time === time) {
                $slot.addClass('paxdesign-is-selected');
            }
            
            $timeslotsGrid.append($slot);
        });
    }
    
    function selectTime(time) {
        bookingData.time = time;
        renderTimeslots();
        $in('#paxdesignNextToDetailsBtn').prop('disabled', false);
    }
    
    function renderMemberSummary(member) {
        var $target = $in('#paxdesignSummaryMember');
        $target.empty();

        if (!member) {
            return;
        }

        $target.append($('<strong class="paxdesign-booking-summary-member-name"></strong>').text(member.name));

        if (member.is_founder) {
            $target.append($('<span class="paxdesign-booking-founder-badge"></span>').text('Gründer & Inhaber'));
        }
        if (member.role) {
            $target.append($('<span class="paxdesign-booking-summary-member-role"></span>').text(member.role));
        }
        if (member.role_en) {
            $target.append($('<span class="paxdesign-booking-summary-member-role-en"></span>').text(member.role_en));
        }
    }

    function updateSummary() {
        const member = liveTeamMembers[bookingData.member] || paxdesignBooking.teamMembers[bookingData.member];
        renderMemberSummary(member);
        
        // Show service if selected
        if (bookingData.service || bookingData.serviceDetails) {
            if (!bookingData.serviceDetails && bookingData.service) {
                bookingData.serviceDetails = buildServiceDetailsFromKey(bookingData.service);
            }
            renderServiceDetailsPanel($in('#paxdesignSummaryService'), bookingData.serviceDetails);
            $in('#paxdesignSummaryServiceItem').removeClass('paxdesign-booking-is-hidden');
        } else {
            renderServiceDetailsPanel($in('#paxdesignSummaryService'), null);
            $in('#paxdesignSummaryServiceItem').addClass('paxdesign-booking-is-hidden');
        }
        
        // Append time component to avoid UTC-midnight timezone shift
        const date = new Date(bookingData.date + 'T12:00:00');
        const weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                       'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        
        const dateStr = weekdays[date.getDay()] + ', ' + date.getDate() + '. ' + 
                       months[date.getMonth()] + ' ' + date.getFullYear() + ' um ' + bookingData.time + ' Uhr';
        
        $in('#paxdesignSummaryDateTime').text(dateStr);
    }
    
    function showFormError(message) {
        $in('#paxdesignFormError').remove();
        const $err = $('<div id="paxdesignFormError" class="paxdesign-form-error">' + message + '</div>');
        $in('#paxdesignBookingDetailsForm').before($err);
    }

    function clearFormError() {
        $in('#paxdesignFormError').remove();
    }

    function submitBooking() {
        try {
            clearFormError();

            // Guard: booking state must be complete before submitting
            if (!bookingData.member || !bookingData.date || !bookingData.time) {
                showFormError('Bitte wählen Sie Ansprechpartner, Datum und Uhrzeit aus.');
                return;
            }

            // Validate required form fields manually so errors show inside the widget
            const name    = $in('#paxdesignBookingName').val().trim();
            const email   = $in('#paxdesignBookingEmail').val().trim();
            const purpose = $in('#paxdesignBookingPurpose').val();
            const privacy = $in('#paxdesignBookingPrivacy').is(':checked');

            if (!name) {
                showFormError('Bitte geben Sie Ihren Namen ein.');
                $in('#paxdesignBookingName').focus();
                return;
            }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFormError('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
                $in('#paxdesignBookingEmail').focus();
                return;
            }
            if (!purpose) {
                showFormError('Bitte wählen Sie den Zweck des Termins aus.');
                $in('#paxdesignBookingPurpose').focus();
                return;
            }
            if (!privacy) {
                showFormError('Bitte akzeptieren Sie die Datenschutzerklärung.');
                return;
            }

            const formData = {
                member:  bookingData.member,
                service: bookingData.service || '',
                serviceDetails: bookingData.serviceDetails,
                date:    bookingData.date,
                time:    bookingData.time,
                name:    name,
                email:   email,
                phone:   $in('#paxdesignBookingPhone').val().trim(),
                purpose: purpose,
                message: $in('#paxdesignBookingMessage').val().trim(),
                nonce:   paxdesignBooking.nonce
            };
            
            const $submitBtn = $in('.paxdesign-booking-btn-submit');
            $submitBtn.text('Wird gesendet...').prop('disabled', true);
            
            $.ajax({
                url: paxdesignBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action:  'paxdesign_submit_booking',
                    member:  formData.member,
                    service: formData.service,
                    service_details: formData.serviceDetails ? JSON.stringify(formData.serviceDetails) : '',
                    date:    formData.date,
                    time:    formData.time,
                    name:    formData.name,
                    email:   formData.email,
                    phone:   formData.phone,
                    purpose: formData.purpose,
                    message: formData.message,
                    nonce:   formData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $in('.paxdesign-booking-content[data-step="3"]').removeClass('paxdesign-is-active');
                        
                        const member = response.data.member_info;
                        const date = new Date(formData.date + 'T12:00:00');
                        const weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
                        const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                                       'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                        
                        const dateStr = weekdays[date.getDay()] + ', ' + date.getDate() + '. ' + 
                                       months[date.getMonth()] + ' ' + date.getFullYear() + ' um ' + formData.time + ' Uhr';
                        
                        const successHTML = '<div class="paxdesign-booking-summary-item">' +
                            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                            '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>' +
                            '<circle cx="12" cy="7" r="4"/>' +
                            '</svg>' +
                            '<div>' +
                            '<span class="paxdesign-booking-summary-label">Ansprechpartner</span>' +
                            '<span class="paxdesign-booking-summary-value">' + member.name + '</span>' +
                            '</div>' +
                            '</div>' +
                            '<div class="paxdesign-booking-summary-item">' +
                            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                            '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>' +
                            '<line x1="16" y1="2" x2="16" y2="6"/>' +
                            '<line x1="8" y1="2" x2="8" y2="6"/>' +
                            '<line x1="3" y1="10" x2="21" y2="10"/>' +
                            '</svg>' +
                            '<div>' +
                            '<span class="paxdesign-booking-summary-label">Termin</span>' +
                            '<span class="paxdesign-booking-summary-value">' + dateStr + '</span>' +
                            '</div>' +
                            '</div>';
                        
                        $in('#paxdesignSuccessDetails').html(successHTML);
                        $in('.paxdesign-booking-success').addClass('paxdesign-is-active');
                    } else {
                        showFormError('Fehler: ' + (response.data && response.data.message ? response.data.message : 'Unbekannter Fehler.'));
                        $submitBtn.text('Termin buchen').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error, xhr.responseText);
                    showFormError('Fehler beim Senden. Bitte versuchen Sie es erneut.');
                    $submitBtn.text('Termin buchen').prop('disabled', false);
                }
            });
        } catch (error) {
            console.error('Error submitting booking:', error);
            showFormError('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
        }
    }
    
    function formatDateISO(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    // Initialize clock animation — scoped to plugin root only (never documentElement)
    (function initClock() {
        var rootEl = document.getElementById('paxdesign-booking-root');
        if (!rootEl) {
            return;
        }

        const d = new Date();
        const convertedSeconds = ((d.getSeconds() + d.getMilliseconds() / 1000) / 60) * 360;
        const convertedMinutes = (d.getMinutes() / 60) * 360;
        const convertedHours = ((d.getHours() + d.getMinutes() / 60) / 12) * 360;
        
        rootEl.style.setProperty('--pax-s-rotate-from', convertedSeconds + 'deg');
        rootEl.style.setProperty('--pax-m-rotate-from', convertedMinutes + 'deg');
        rootEl.style.setProperty('--pax-h-rotate-from', convertedHours + 'deg');
        rootEl.style.setProperty('--pax-s-rotate-to', (convertedSeconds + 360) + 'deg');
        rootEl.style.setProperty('--pax-m-rotate-to', (convertedMinutes + 360) + 'deg');
        rootEl.style.setProperty('--pax-h-rotate-to', (convertedHours + 360) + 'deg');
    })();
    
})(jQuery);
