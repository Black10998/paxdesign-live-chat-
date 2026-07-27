/**
 * Apple-style mega menu helpers for the primary header nav.
 * Premium floating panels, viewport clamping, open-state backdrop.
 */
(function ($) {
  'use strict';

  var DESKTOP_MQ = window.matchMedia('(min-width: 993px)');

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
        }
      },
      onHide: function () {
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
      syncExpanded($li, true);
      setMegaOpen(true);
      window.requestAnimationFrame(function () {
        clampPanel($li.children('ul.dtr-mega-panel'));
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
      window.setTimeout(function () {
        setMegaOpen(anyMegaOpen($menu));
      }, 40);
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
        $menu.find('> li.dtr-has-mega > ul.dtr-mega-panel').hide();
        setMegaOpen(false);
      }
    });
  }

  $(function () {
    window.setTimeout(bindMegaMenu, 0);
  });
})(jQuery);
