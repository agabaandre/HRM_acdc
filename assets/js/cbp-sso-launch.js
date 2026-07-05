/**
 * Secure CBP module launch: POST to Staff portal (never puts JWT in the URL).
 * Requires an active Staff portal session cookie on the same site.
 */
(function (global) {
  'use strict';

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

  function csrfTokenName() {
    return global.CBP_CSRF_TOKEN_NAME || 'africacdc_csrf_token';
  }

  function fetchCsrfToken(base) {
    return fetch(base + '/auth/refreshCSRF', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) { return data && data.csrf_token ? data.csrf_token : null; });
  }

  function postLaunch(moduleKey, targetWindow) {
    var base = staffBaseUrl();
    fetchCsrfToken(base).then(function (token) {
      if (!token) {
        window.alert('Could not obtain a security token. Open CBP Home and try again.');
        return;
      }
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = base + '/home/launch_module';
      form.style.display = 'none';
      if (targetWindow === '_blank') {
        form.target = '_blank';
      }
      var mk = document.createElement('input');
      mk.type = 'hidden';
      mk.name = 'module_key';
      mk.value = moduleKey;
      form.appendChild(mk);
      var csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = csrfTokenName();
      csrf.value = token;
      form.appendChild(csrf);
      document.body.appendChild(form);
      form.submit();
      setTimeout(function () { form.remove(); }, 1000);
    }).catch(function () {
      window.alert('Secure launch failed. Open CBP Home and try again.');
    });
  }

  global.cbpLaunchModule = function (moduleKey, openInNewTab) {
    if (!moduleKey) {
      return;
    }
    postLaunch(String(moduleKey), openInNewTab ? '_blank' : undefined);
  };
})(window);
