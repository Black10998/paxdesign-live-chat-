/**
 * Apple-style mega menu for the primary header nav.
 *
 * Owns Superfish for the desktop primary menu (superfish.js must NOT auto-init).
 * CSS controls panel visibility; hover swaps the feature preview image + copy.
 */
(function ($) {
  'use strict';

  var DESKTOP_MQ = window.matchMedia('(min-width: 993px)');
  var previewCache = Object.create(null);

  function isDesktop() {
    return DESKTOP_MQ.matches;
  }

  function setMegaOpen(open) {
    document.body.classList.toggle('dtr-mega-open', !!open && isDesktop());
  }

  function anyMegaOpen($menu) {
    return $menu.find('> li.dtr-has-mega.sfHover').length > 0;
  }

  function clearPanelInline($panel) {
    if (!$panel.length) {
      return;
    }
    // Let CSS own display/opacity so theme + jQuery do not flash a second layout.
    $panel.css({
      display: '',
      opacity: '',
      visibility: '',
      height: '',
      overflow: '',
      top: '',
      right: ''
    });
  }

  function clampPanel($panel) {
    if (!$panel.length || !isDesktop()) {
      return;
    }

    clearPanelInline($panel);
    $panel.css({ left: '50%', marginLeft: 0 });

    var rect = $panel.get(0).getBoundingClientRect();
    var pad = 24;
    var shift = 0;

    if (rect.left < pad) {
      shift = pad - rect.left;
    } else if (rect.right > window.innerWidth - pad) {
      shift = window.innerWidth - pad - rect.right;
    }

    if (shift !== 0) {
      $panel.css({
        left: '50%',
        marginLeft: shift + 'px'
      });
    }
  }

  function syncExpanded($li, open) {
    $li.children('a').attr('aria-expanded', open ? 'true' : 'false');
  }

  function preloadSrc(src) {
    if (!src || previewCache[src]) {
      return;
    }
    var img = new Image();
    img.decoding = 'async';
    img.src = src;
    previewCache[src] = img;
  }

  function preloadPanelPreviews($panel) {
    if (!$panel || !$panel.length) {
      return;
    }
    var $feature = $panel.children('li.dtr-mega-feature').first();
    preloadSrc($feature.attr('data-default-src'));
    $panel.find('> li.dtr-mega-item > a[data-preview-src]').each(function () {
      preloadSrc(this.getAttribute('data-preview-src'));
    });
  }

  function applyFeatureCopy($feature, title, desc, href, cta) {
    var $card = $feature.find('.dtr-mega-feature__card').first();
    var $title = $feature.find('.dtr-mega-feature__title').first();
    var $desc = $feature.find('.dtr-mega-feature__desc').first();
    var $cta = $feature.find('.dtr-mega-feature__cta').first();

    if (title) {
      $title.text(title);
    }
    if ($desc.length) {
      if (desc) {
        $desc.text(desc).css('display', '');
      } else {
        $desc.text('').hide();
      }
    }
    if (href) {
      $card.attr('href', href);
    }
    if (typeof cta === 'string' && cta && $cta.length) {
      $cta.text(cta);
    }
  }

  function swapFeatureImage($feature, src) {
    if (!$feature.length || !src) {
      return;
    }

    var $img = $feature.find('.dtr-mega-feature__img').first();
    if (!$img.length) {
      $img = $feature.find('.dtr-mega-feature__media img').first();
    }
    if (!$img.length) {
      return;
    }

    if ($img.attr('src') === src) {
      $feature.attr('data-current-src', src);
      return;
    }

    var token = String(Date.now()) + Math.random();
    $feature.data('previewToken', token);

    function activate() {
      if ($feature.data('previewToken') !== token) {
        return;
      }
      $img.attr('src', src);
      $feature.attr('data-current-src', src);
    }

    var cached = previewCache[src];
    if (cached && cached.complete) {
      activate();
      return;
    }

    var loader = new Image();
    loader.onload = function () {
      previewCache[src] = loader;
      activate();
    };
    loader.onerror = activate;
    loader.src = src;
    if (loader.complete) {
      previewCache[src] = loader;
      activate();
    }
  }

  function setItemPreview($panel, $link) {
    var $feature = $panel.children('li.dtr-mega-feature').first();
    if (!$feature.length || !$link || !$link.length) {
      return;
    }

    var src = $link.attr('data-preview-src');
    var title = $link.attr('data-preview-title') || '';
    var desc = $link.attr('data-preview-desc') || '';
    var href = $link.attr('href') || '';

    $panel.find('> li.dtr-mega-item').removeClass('is-preview-active');
    $link.closest('li.dtr-mega-item').addClass('is-preview-active');

    swapFeatureImage($feature, src);
    applyFeatureCopy($feature, title, desc, href, 'Mehr erfahren');
  }

  function resetPanelPreview($panel) {
    var $feature = $panel.children('li.dtr-mega-feature').first();
    if (!$feature.length) {
      return;
    }

    $panel.find('> li.dtr-mega-item').removeClass('is-preview-active');

    swapFeatureImage($feature, $feature.attr('data-default-src'));
    applyFeatureCopy(
      $feature,
      $feature.attr('data-default-title') || '',
      $feature.attr('data-default-desc') || '',
      $feature.attr('data-default-href') || '',
      $feature.attr('data-default-cta') || ''
    );
  }

  function retuneSuperfish($menu) {
    if (!$menu.length || typeof $.fn.superfish !== 'function') {
      return;
    }

    try {
      $menu.superfish('destroy');
    } catch (e) {
      // Ignore if Superfish was not yet initialized.
    }

    $menu.superfish({
      delay: 220,
      // Instant show/hide: CSS owns the panel transition. Avoids jQuery opacity
      // fighting theme display:block and re-animating a "second" panel.
      animation: {},
      animationOut: {},
      speed: 1,
      speedOut: 1,
      cssArrows: true,
      disableHI: true,
      onBeforeShow: function () {
        if (!this.hasClass('dtr-mega-panel')) {
          return;
        }
        clearPanelInline(this);
        clampPanel(this);
        setMegaOpen(true);
        preloadPanelPreviews(this);
        // Cancel jQuery.animate — sfHover + CSS display:grid !important shows the panel.
        return false;
      },
      onBeforeHide: function () {
        if (!this.hasClass('dtr-mega-panel')) {
          return;
        }
        resetPanelPreview(this);
        clearPanelInline(this);
        window.requestAnimationFrame(function () {
          setMegaOpen(anyMegaOpen($menu));
        });
        return false;
      }
    });

    // Superfish binds focusin on ALL li; nested mega items re-trigger show/hide.
    // Restrict keyboard focus handling to top-level mega parents only.
    $menu.off('focusin.superfish focusout.superfish');
    $menu.on('focusin.dtrMegaFocus', '> li.dtr-has-mega', function () {
      if (!isDesktop()) {
        return;
      }
      var $li = $(this);
      $li.siblings('li.dtr-has-mega.sfHover').removeClass('sfHover')
        .children('ul.dtr-mega-panel').each(function () {
          resetPanelPreview($(this));
          clearPanelInline($(this));
        });
      if (!$li.hasClass('sfHover')) {
        $li.addClass('sfHover');
      }
      syncExpanded($li, true);
      var $panel = $li.children('ul.dtr-mega-panel');
      clearPanelInline($panel);
      clampPanel($panel);
      setMegaOpen(true);
      preloadPanelPreviews($panel);
    });
    $menu.on('focusout.dtrMegaFocus', '> li.dtr-has-mega', function (e) {
      var $li = $(this);
      var next = e.relatedTarget;
      if (next && $li.get(0).contains(next)) {
        return;
      }
      window.setTimeout(function () {
        if ($li.get(0).contains(document.activeElement)) {
          return;
        }
        $li.removeClass('sfHover');
        syncExpanded($li, false);
        var $panel = $li.children('ul.dtr-mega-panel');
        resetPanelPreview($panel);
        clearPanelInline($panel);
        setMegaOpen(anyMegaOpen($menu));
      }, 0);
    });
  }

  function bindMegaMenu() {
    var $menu = $('#dtr-main-header .sf-menu.dtr-main-nav');
    if (!$menu.length) {
      return;
    }

    retuneSuperfish($menu);

    $menu.on('mouseenter', '> li.dtr-has-mega', function () {
      if (!isDesktop()) {
        return;
      }
      var $li = $(this);
      var $panel = $li.children('ul.dtr-mega-panel');
      syncExpanded($li, true);
      setMegaOpen(true);
      clearPanelInline($panel);
      preloadPanelPreviews($panel);
      window.requestAnimationFrame(function () {
        clampPanel($panel);
      });
    });

    $menu.on('mouseleave', '> li.dtr-has-mega', function () {
      var $li = $(this);
      syncExpanded($li, false);
      var $panel = $li.children('ul.dtr-mega-panel');
      resetPanelPreview($panel);
      window.setTimeout(function () {
        setMegaOpen(anyMegaOpen($menu));
      }, 40);
    });

    $menu.on(
      'mouseenter focusin',
      '> li.dtr-has-mega > ul.dtr-mega-panel > li.dtr-mega-item > a[data-preview-src]',
      function () {
        if (!isDesktop()) {
          return;
        }
        var $link = $(this);
        setItemPreview($link.closest('ul.dtr-mega-panel'), $link);
      }
    );

    $(window).on('resize.dtrMegaMenu', function () {
      if (!isDesktop()) {
        setMegaOpen(false);
        $menu.find('> li.dtr-has-mega > ul.dtr-mega-panel').css({
          left: '',
          marginLeft: '',
          right: '',
          display: '',
          opacity: ''
        });
        return;
      }
      $menu.find('> li.dtr-has-mega.sfHover').each(function () {
        clampPanel($(this).children('ul.dtr-mega-panel'));
      });
    });

    $(document).on('keydown.dtrMegaMenu', function (e) {
      if (e.key === 'Escape') {
        $menu.find('> li.dtr-has-mega.sfHover').removeClass('sfHover')
          .children('a').attr('aria-expanded', 'false');
        $menu.find('> li.dtr-has-mega > ul.dtr-mega-panel').each(function () {
          resetPanelPreview($(this));
          clearPanelInline($(this));
        });
        setMegaOpen(false);
      }
    });

    window.requestAnimationFrame(function () {
      $menu.find('> li.dtr-has-mega > ul.dtr-mega-panel').each(function () {
        preloadPanelPreviews($(this));
      });
    });
  }

  $(function () {
    // Run after other ready handlers so we own the only Superfish instance.
    window.setTimeout(bindMegaMenu, 0);
  });
})(jQuery);
