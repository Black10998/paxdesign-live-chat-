/**
 * Apple-style mega menu helpers for the primary header nav.
 * Premium floating panels, viewport clamping, open-state backdrop,
 * and instant hover preview image swapping.
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

  function clampPanel($panel) {
    if (!$panel.length || !isDesktop()) {
      return;
    }

    $panel.css({ left: '50%', right: 'auto', marginLeft: 0 });

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
        $desc.text(desc).show();
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

  function cssUrl(src) {
    return src ? 'url("' + String(src).replace(/"/g, '\\"') + '")' : '';
  }

  function swapFeatureImage($feature, src) {
    if (!$feature.length || !src) {
      return;
    }

    var $media = $feature.find('.dtr-mega-feature__media').first();
    if (!$media.length) {
      return;
    }

    var current = $feature.attr('data-current-src') || $feature.attr('data-default-src') || '';
    if (current === src && $media.find('.dtr-mega-feature__layer.is-active').length) {
      return;
    }

    var $active = $media.find('.dtr-mega-feature__layer.is-active').first();
    var $next = $media.find('.dtr-mega-feature__layer').not('.is-active').first();

    // Fallback for older markup with <img> tags.
    if (!$active.length && !$next.length) {
      var $imgs = $media.find('img');
      if ($imgs.length) {
        $imgs.first().attr('src', src).addClass('is-active').css({
          opacity: '1',
          visibility: 'visible',
          display: 'block'
        });
        $imgs.slice(1).removeClass('is-active').css('opacity', '0');
      }
      $media.css('background-image', cssUrl(src));
      $feature.attr('data-current-src', src);
      return;
    }

    if (!$next.length) {
      $active.css('background-image', cssUrl(src)).addClass('is-active');
      $media.css('background-image', cssUrl(src));
      $feature.attr('data-current-src', src);
      return;
    }

    var token = String(Date.now()) + Math.random();
    $feature.data('previewToken', token);

    function activate() {
      if ($feature.data('previewToken') !== token) {
        return;
      }
      $next.css('background-image', cssUrl(src));
      $next.addClass('is-active');
      $active.removeClass('is-active');
      $media.css('background-image', cssUrl(src));
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
    loader.onerror = function () {
      // Still apply the URL so a broken state is visible/debuggable.
      activate();
    };
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
      delay: 240,
      animation: { opacity: 'show' },
      animationOut: { opacity: 'hide' },
      speed: 200,
      speedOut: 160,
      cssArrows: true,
      disableHI: true,
      onBeforeShow: function () {
        if (this.hasClass('dtr-mega-panel')) {
          this.css('display', 'grid');
        }
      },
      onShow: function () {
        if (this.hasClass('dtr-mega-panel')) {
          this.css('display', 'grid');
          clampPanel(this);
          setMegaOpen(true);
          preloadPanelPreviews(this);
        }
      },
      onHide: function () {
        if (this.hasClass('dtr-mega-panel')) {
          resetPanelPreview(this);
        }
        window.requestAnimationFrame(function () {
          setMegaOpen(anyMegaOpen($menu));
        });
      }
    });
  }

  function bindMegaMenu() {
    var $menu = $('#dtr-main-header .sf-menu.dtr-main-nav');
    if (!$menu.length) {
      return;
    }

    retuneSuperfish($menu);

    $menu.on('mouseenter.focusin', '> li.dtr-has-mega', function () {
      if (!isDesktop()) {
        return;
      }
      var $li = $(this);
      var $panel = $li.children('ul.dtr-mega-panel');
      syncExpanded($li, true);
      setMegaOpen(true);
      preloadPanelPreviews($panel);
      window.requestAnimationFrame(function () {
        clampPanel($panel);
      });
    });

    $menu.on('mouseleave.focusout', '> li.dtr-has-mega', function (e) {
      var $li = $(this);
      if (e.type === 'focusout') {
        var next = e.relatedTarget;
        if (next && $li.get(0).contains(next)) {
          return;
        }
      }
      syncExpanded($li, false);
      resetPanelPreview($li.children('ul.dtr-mega-panel'));
      window.setTimeout(function () {
        setMegaOpen(anyMegaOpen($menu));
      }, 40);
    });

    $menu.on(
      'mouseenter.focusin',
      '> li.dtr-has-mega > ul.dtr-mega-panel > li.dtr-mega-item > a[data-preview-src]',
      function () {
        if (!isDesktop()) {
          return;
        }
        var $link = $(this);
        var $panel = $link.closest('ul.dtr-mega-panel');
        setItemPreview($panel, $link);
      }
    );

    $menu.on('mouseleave', '> li.dtr-has-mega > ul.dtr-mega-panel', function (e) {
      if (!isDesktop()) {
        return;
      }
      var related = e.relatedTarget;
      var panel = this;
      if (related && panel.contains(related)) {
        return;
      }
      // Leaving the panel entirely restores the section default.
      // Item-to-item moves stay handled by mouseenter on the next item.
    });

    $(window).on('resize.dtrMegaMenu', function () {
      if (!isDesktop()) {
        setMegaOpen(false);
        $menu.find('> li.dtr-has-mega > ul.dtr-mega-panel').css({
          left: '',
          marginLeft: '',
          right: '',
          display: ''
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
        }).hide();
        setMegaOpen(false);
      }
    });

    // Warm the default section previews after first paint.
    window.requestAnimationFrame(function () {
      $menu.find('> li.dtr-has-mega > ul.dtr-mega-panel').each(function () {
        preloadPanelPreviews($(this));
      });
    });
  }

  $(function () {
    window.setTimeout(bindMegaMenu, 0);
  });
})(jQuery);
