/**
 * Cross-app Staff portal SSO session refresh for CBP modules (APM, Helpdesk, Finance).
 * Requires an active Staff portal session cookie on the same site.
 */
(function (global) {
  'use strict';

  var REFRESH_INTERVAL_MS = 15 * 60 * 1000;
  var REFRESH_BEFORE_EXP_SEC = 20 * 60;
  var started = false;

  function staffBaseUrl() {
    if (global.CBP_STAFF_BASE_URL) {
      return String(global.CBP_STAFF_BASE_URL).replace(/\/$/, '');
    }
    var path = window.location.pathname || '';
    var idx = path.indexOf('/staff/');
    if (idx >= 0) {
      return window.location.origin + path.substring(0, idx + 6);
    }
    return window.location.origin + '/staff';
  }

  function parseJwtExp(token) {
    if (!token || typeof token !== 'string') {
      return null;
    }
    var parts = token.split('.');
    if (parts.length !== 3) {
      return null;
    }
    try {
      var payload = parts[1].replace(/-/g, '+').replace(/_/g, '/');
      var pad = payload.length % 4;
      if (pad) {
        payload += '===='.substring(pad);
      }
      var json = JSON.parse(atob(payload));
      return typeof json.exp === 'number' ? json.exp : null;
    } catch (e) {
      return null;
    }
  }

  function fetchFreshSsoToken() {
    var base = staffBaseUrl();
    return fetch(base + '/auth/refresh_sso_session', {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }).then(function (r) {
      if (!r.ok) {
        throw new Error('refresh_sso_session HTTP ' + r.status);
      }
      return r.json();
    });
  }

  function dispatchRefresh(detail) {
    try {
      global.dispatchEvent(new CustomEvent('cbp:sso-refreshed', { detail: detail }));
    } catch (e) {
      /* IE fallback not required */
    }
    var handlers = global.CBP_SSO_REFRESH_HANDLERS || [];
    handlers.forEach(function (fn) {
      try {
        if (typeof fn === 'function') {
          fn(detail);
        }
      } catch (err) {
        console.warn('[cbp-session-refresh] handler failed', err);
      }
    });
  }

  function shouldRefreshNow(lastToken, lastExp) {
    if (!lastToken) {
      return true;
    }
    var exp = lastExp || parseJwtExp(lastToken);
    if (!exp) {
      return true;
    }
    return exp - Math.floor(Date.now() / 1000) <= REFRESH_BEFORE_EXP_SEC;
  }

  function refreshTick() {
    var state = global.CBP_SSO_REFRESH_STATE || (global.CBP_SSO_REFRESH_STATE = {});
    if (state.inFlight) {
      return;
    }
    if (!shouldRefreshNow(state.lastToken, state.lastExp)) {
      return;
    }
    state.inFlight = true;
    fetchFreshSsoToken()
      .then(function (data) {
        if (!data || !data.ok || !data.sso_token) {
          return;
        }
        state.lastToken = data.sso_token;
        state.lastExp = parseJwtExp(data.sso_token);
        dispatchRefresh({
          sso_token: data.sso_token,
          expires_at: data.expires_at || null,
          expires_in: data.expires_in || null,
          staff_id: data.staff_id || null,
        });
      })
      .catch(function (err) {
        console.warn('[cbp-session-refresh] Staff portal refresh failed', err);
      })
      .finally(function () {
        state.inFlight = false;
      });
  }

  global.cbpRegisterSsoRefreshHandler = function (fn) {
    if (typeof fn !== 'function') {
      return;
    }
    global.CBP_SSO_REFRESH_HANDLERS = global.CBP_SSO_REFRESH_HANDLERS || [];
    global.CBP_SSO_REFRESH_HANDLERS.push(fn);
  };

  global.cbpRefreshSsoSession = refreshTick;

  global.cbpStartSsoSessionRefresh = function () {
    if (started) {
      return;
    }
    started = true;
    setTimeout(refreshTick, 5000);
    setInterval(refreshTick, REFRESH_INTERVAL_MS);
  };

  if (global.CBP_AUTO_START_SSO_REFRESH !== false) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', global.cbpStartSsoSessionRefresh);
    } else {
      global.cbpStartSsoSessionRefresh();
    }
  }
})(window);
