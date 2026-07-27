/**
 * Apple-inspired full-screen mobile navigation.
 * Reuses desktop mega-menu walker markup (icons + descriptions).
 */
(function ($) {
  'use strict';

  var MQ = window.matchMedia('(max-width: 992px)');
  var CHEVRON =
    '<svg class="dtr-apple-mnav__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">' +
    '<path fill="currentColor" d="M5.2 7.4a1 1 0 0 1 1.4 0L10 10.8l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.8a1 1 0 0 1 0-1.4z"/></svg>';

  var state = {
    open: false,
    built: false,
    $root: null,
    $list: null,
    $btn: null,
    lastFocus: null,
    scrollY: 0
  };

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function isMobile() {
    return MQ.matches;
  }

  function sourceMenu() {
    return $('#dtr-main-header .dtr-main-nav').first();
  }

  function ensureRoot() {
    if (state.$root && state.$root.length) {
      return state.$root;
    }

    var $existing = $('#dtr-apple-mobile-nav');
    if ($existing.length) {
      state.$root = $existing;
      state.$list = $existing.find('.dtr-apple-mnav__list');
      return state.$root;
    }

    var html =
      '<div id="dtr-apple-mobile-nav" class="dtr-apple-mnav" hidden aria-hidden="true">' +
      '<div class="dtr-apple-mnav__backdrop" data-amnav-close tabindex="-1"></div>' +
      '<div class="dtr-apple-mnav__sheet" role="dialog" aria-modal="true" aria-label="Menü">' +
      '<div class="dtr-apple-mnav__scroll">' +
      '<p class="dtr-apple-mnav__brand">PAXdesign</p>' +
      '<ul class="dtr-apple-mnav__list"></ul>' +
      '<div class="dtr-apple-mnav__footer">' +
      '<a href="/kontakt/">Kontakt aufnehmen</a>' +
      '</div>' +
      '</div></div></div>';

    $('body').append(html);
    state.$root = $('#dtr-apple-mobile-nav');
    state.$list = state.$root.find('.dtr-apple-mnav__list');
    return state.$root;
  }

  function cleanClone($node) {
    var $clone = $node.clone(false, false);
    // Superfish / theme may leave inline display:none — strip so accordion can open.
    $clone.removeAttr('style');
    $clone.find('[style]').removeAttr('style');
    $clone.find('.sf-sub-indicator, .sf-with-ul > .sf-sub-indicator').remove();
    $clone.find('[id]').removeAttr('id');
    $clone.find('a').removeAttr('aria-haspopup aria-expanded');
    $clone.prop('hidden', false).removeAttr('hidden');
    return $clone;
  }

  function buildPanel() {
    var $menu = sourceMenu();
    if (!$menu.length) {
      return false;
    }

    ensureRoot();
    state.$list.empty();

    $menu.children('li').each(function () {
      var $li = $(this);
      var $link = $li.children('a').first();
      if (!$link.length) {
        return;
      }

      var href = $link.attr('href') || '#';
      // Prefer only the top-level label, not nested mega copy text.
      var label = $.trim($link.clone().children().remove().end().text()) || $.trim($link.text());
      var $panel = $li.children('ul.dtr-mega-panel, ul.sub-menu').first();
      var hasChildren = $panel.length > 0;
      var $item = $('<li class="dtr-apple-mnav__item"></li>');

      if (hasChildren) {
        var panelId = 'dtr-amnav-panel-' + String($li.attr('id') || Math.random()).replace(/[^\w-]/g, '');
        var $toggle = $(
          '<button type="button" class="dtr-apple-mnav__toggle" aria-expanded="false" aria-controls="' +
            panelId +
            '"></button>'
        );
        $toggle.append($('<span class="dtr-apple-mnav__label"></span>').text(label));
        $toggle.append(CHEVRON);

        var $children = cleanClone($panel);
        $children
          .attr('id', panelId)
          .attr('class', 'dtr-apple-mnav__panel')
          .attr('aria-hidden', 'true');

        // Keep mega item markup (icon/title/desc); strip nested unused menus + desktop feature cards.
        $children.find('ul.sub-menu').remove();
        $children.children('li.dtr-mega-feature').remove();
        $children.find('.dtr-mega-go').remove();

        // If feature stripping left nothing usable, skip empty accordion.
        if (!$children.children('li').length) {
          var $aEmpty = $('<a class="dtr-apple-mnav__link"></a>').attr('href', href).text(label);
          $item.append($aEmpty);
        } else {
          $item.append($toggle).append($children);
        }
      } else {
        var $a = $('<a class="dtr-apple-mnav__link"></a>').attr('href', href).text(label);
        $item.append($a);
      }

      state.$list.append($item);
    });

    state.built = true;
    bindAccordion();
    return true;
  }

  function setExpanded($item, open) {
    if (!$item || !$item.length) {
      return;
    }
    var $toggle = $item.children('.dtr-apple-mnav__toggle');
    var $panel = $item.children('.dtr-apple-mnav__panel');
    $item.toggleClass('is-open', !!open);
    $toggle.attr('aria-expanded', open ? 'true' : 'false');
    if ($panel.length) {
      // Never rely on [hidden] / leftover inline display from Superfish.
      $panel.prop('hidden', false).removeAttr('hidden').removeAttr('style');
      $panel.attr('aria-hidden', open ? 'false' : 'true');
      if (open) {
        $panel.css('display', 'block');
      } else {
        $panel.css('display', 'none');
      }
    }
  }

  function closeAllSections(except) {
    if (!state.$list) {
      return;
    }
    state.$list.children('.dtr-apple-mnav__item.is-open').each(function () {
      if (except && this === except.get(0)) {
        return;
      }
      setExpanded($(this), false);
    });
  }

  function onToggleActivate(e) {
    e.preventDefault();
    e.stopPropagation();
    var $toggle = $(e.currentTarget);
    var $item = $toggle.closest('.dtr-apple-mnav__item');
    if (!$item.length) {
      return;
    }
    var willOpen = !$item.hasClass('is-open');
    closeAllSections($item);
    setExpanded($item, willOpen);
  }

  function bindAccordion() {
    if (!state.$list || !state.$list.length) {
      return;
    }
    // Bind on the list (closer than document) for reliable mobile taps.
    state.$list
      .off('click.amnavAcc touchend.amnavAcc')
      .on('click.amnavAcc', '.dtr-apple-mnav__toggle', onToggleActivate);
  }

  function lockScroll(lock) {
    var $body = $(document.body);
    if (lock) {
      state.scrollY = window.scrollY || window.pageYOffset || 0;
      $body.css({
        position: 'fixed',
        top: '-' + state.scrollY + 'px',
        left: 0,
        right: 0,
        width: '100%'
      });
    } else {
      $body.css({ position: '', top: '', left: '', right: '', width: '' });
      window.scrollTo(0, state.scrollY || 0);
    }
  }

  function openNav() {
    if (!isMobile()) {
      return;
    }
    // Rebuild each open so accordion handlers/markup stay fresh after desktop clones.
    if (!buildPanel()) {
      return;
    }

    ensureRoot();
    state.open = true;
    state.lastFocus = document.activeElement;
    $(document.body).addClass('dtr-apple-mnav-open');
    if (state.$btn) {
      state.$btn.addClass('is-active').attr('aria-expanded', 'true');
    }
    state.$root
      .prop('hidden', false)
      .removeAttr('hidden')
      .attr('aria-hidden', 'false')
      .addClass('is-open');
    lockScroll(true);
    closeAllSections();

    window.setTimeout(function () {
      var $first = state.$root.find('.dtr-apple-mnav__toggle, .dtr-apple-mnav__link').first();
      if ($first.length) {
        $first.trigger('focus');
      }
    }, prefersReducedMotion() ? 0 : 180);
  }

  function closeNav() {
    if (!state.open) {
      $(document.body).removeClass('dtr-apple-mnav-open');
      if (state.$btn) {
        state.$btn.removeClass('is-active').attr('aria-expanded', 'false');
      }
      return;
    }

    state.open = false;
    closeAllSections();
    $(document.body).removeClass('dtr-apple-mnav-open');
    if (state.$btn) {
      state.$btn.removeClass('is-active').attr('aria-expanded', 'false');
    }
    if (state.$root) {
      state.$root.removeClass('is-open').attr('aria-hidden', 'true');
      window.setTimeout(function () {
        if (!state.open && state.$root) {
          state.$root.prop('hidden', true).attr('hidden', true);
        }
      }, prefersReducedMotion() ? 0 : 360);
    }
    lockScroll(false);
    if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
      try {
        state.lastFocus.focus();
      } catch (e) {
        // ignore
      }
    }
  }

  function toggleNav(e) {
    if (e) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
    if (!isMobile()) {
      return;
    }
    if (state.open) {
      closeNav();
    } else {
      openNav();
    }
  }

  function bind() {
    document.body.classList.add('dtr-apple-mobile-nav');
    state.$btn = $('#dtr-menu-button');
    if (!state.$btn.length) {
      return;
    }

    // Take ownership of the hamburger from SlickNav / custom.js.
    state.$btn.off('click');
    $(document).off('click', '#dtr-menu-button');
    state.$btn.attr({
      'aria-controls': 'dtr-apple-mobile-nav',
      'aria-expanded': 'false'
    });

    state.$btn.on('click.appleMobileNav', toggleNav);

    $(document)
      .off('click.appleMobileNav')
      .on('click.appleMobileNav', '[data-amnav-close]', function (e) {
        e.preventDefault();
        closeNav();
      })
      .on('click.appleMobileNav', '.dtr-apple-mnav__panel a, .dtr-apple-mnav__link, .dtr-apple-mnav__footer a', function () {
        closeNav();
      });

    $(document).on('keydown.appleMobileNav', function (e) {
      if (e.key === 'Escape' && state.open) {
        closeNav();
      }
    });

    function onViewportChange() {
      if (!isMobile() && state.open) {
        closeNav();
      }
      if (isMobile()) {
        state.built = false;
      }
    }

    if (typeof MQ.addEventListener === 'function') {
      MQ.addEventListener('change', onViewportChange);
    } else if (typeof MQ.addListener === 'function') {
      MQ.addListener(onViewportChange);
    }

    if (isMobile()) {
      buildPanel();
    }
  }

  function boot() {
    window.setTimeout(bind, 0);
    window.setTimeout(function () {
      if (state.$btn && state.$btn.length) {
        state.$btn.off('click').on('click.appleMobileNav', toggleNav);
      }
    }, 120);
  }

  $(boot);
})(jQuery);
