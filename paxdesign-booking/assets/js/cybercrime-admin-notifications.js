(function () {
  'use strict';

  var cfg = window.paxCybercrimeAdminNotify || {};
  if (!cfg.ajaxUrl || !cfg.nonce) {
    return;
  }

  var pollTimer = null;
  var defaultPortalUrl = cfg.defaultPortalUrl || '';
  var storedPortalHref = defaultPortalUrl;
  var storedParentHref = 'admin.php?page=' + (cfg.parentMenuSlug || 'paxdesign-booking');

  function formatCount(total) {
    var count = parseInt(total, 10) || 0;
    if (count <= 0) {
      return '';
    }
    return count > 99 ? '99+' : String(count);
  }

  function menuLinks() {
    var slug = cfg.portalMenuSlug || 'paxdesign-customer-portal';
    var parent = cfg.parentMenuSlug || 'paxdesign-booking';
    var selectors = [
      '#toplevel_page_' + parent + ' > a.menu-top',
      '#toplevel_page_' + parent + ' .wp-submenu a[href*="page=' + slug + '"]',
      '#toplevel_page_' + parent + ' .wp-submenu a[href*="page=' + slug + '&tab=cybercrime"]'
    ];
    var links = [];
    selectors.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (link) {
        if (links.indexOf(link) === -1) {
          links.push(link);
        }
      });
    });
    return links;
  }

  function ensureMenuBadge(link) {
    if (!link) {
      return null;
    }
    var badge = link.querySelector('.pax-cc-menu-unread-badge');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'awaiting-mod pax-cc-menu-unread-badge';
      badge.innerHTML = '<span class="pax-cc-menu-unread-count"></span>';
      link.appendChild(badge);
    }
    return badge.querySelector('.pax-cc-menu-unread-count') || badge;
  }

  function updateMenuBadges(summary) {
    var total = parseInt(summary && summary.total, 10) || 0;
    var label = formatCount(total);
    var links = menuLinks();

    links.forEach(function (link) {
      if (!link.dataset.paxCcDefaultHref) {
        link.dataset.paxCcDefaultHref = link.getAttribute('href') || defaultPortalUrl;
      }
      var countEl = ensureMenuBadge(link);
      if (!countEl) {
        return;
      }
      if (label === '') {
        countEl.textContent = '';
        var badgeWrap = countEl.closest('.pax-cc-menu-unread-badge');
        if (badgeWrap) {
          badgeWrap.style.display = 'none';
        }
        link.setAttribute('href', link.dataset.paxCcDefaultHref);
        return;
      }
      countEl.textContent = label;
      var wrap = countEl.closest('.pax-cc-menu-unread-badge');
      if (wrap) {
        wrap.style.display = '';
      }
      if (summary.target_url) {
        link.setAttribute('href', summary.target_url);
      }
    });

    var parentTop = document.querySelector('#toplevel_page_' + (cfg.parentMenuSlug || 'paxdesign-booking') + ' > a.menu-top');
    if (parentTop) {
      parentTop.classList.toggle('pax-cc-has-unread', total > 0);
    }
  }

  function updateTabBadge(summary) {
    var tabBadge = document.getElementById('pax-cc-tab-unread-badge');
    if (!tabBadge) {
      return;
    }
    var label = formatCount(summary && summary.total);
    if (label === '') {
      tabBadge.hidden = true;
      tabBadge.textContent = '';
      return;
    }
    tabBadge.hidden = false;
    tabBadge.textContent = label;
  }

  function updateRowBadges(summary) {
    var reports = summary && Array.isArray(summary.reports) ? summary.reports : [];
    var unreadMap = {};
    reports.forEach(function (item) {
      if (item && item.reference_id) {
        unreadMap[item.reference_id] = parseInt(item.unread_count, 10) || 0;
      }
    });
    document.querySelectorAll('.pax-cc-unread-badge--row').forEach(function (badge) {
      var ref = badge.getAttribute('data-unread-for') || '';
      var count = unreadMap[ref] || 0;
      if (count > 0) {
        badge.hidden = false;
        badge.textContent = count > 99 ? '99+' : String(count);
      } else {
        badge.hidden = true;
        badge.textContent = '';
      }
    });
  }

  function applySummary(summary) {
    if (!summary || typeof summary !== 'object') {
      return;
    }
    updateMenuBadges(summary);
    updateTabBadge(summary);
    updateRowBadges(summary);
  }

  function fetchSummary() {
    var body = new URLSearchParams();
    body.set('action', 'paxdesign_cybercrime_admin_unread');
    body.set('nonce', cfg.nonce);

    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString()
    })
      .then(function (response) {
        return response.text();
      })
      .then(function (text) {
        var data = null;
        try {
          data = text ? JSON.parse(text) : null;
        } catch (error) {
          return null;
        }
        if (!data || data.success !== true) {
          return null;
        }
        return data.data && data.data.summary ? data.data.summary : data.data;
      })
      .then(function (summary) {
        if (summary) {
          applySummary(summary);
        }
        return summary;
      })
      .catch(function () {
        return null;
      });
  }

  applySummary(cfg.initialSummary || { total: 0, reports: [] });
  fetchSummary();

  pollTimer = window.setInterval(fetchSummary, parseInt(cfg.pollIntervalMs, 10) || 30000);

  window.paxCybercrimeAdminApplyUnread = applySummary;
})();
