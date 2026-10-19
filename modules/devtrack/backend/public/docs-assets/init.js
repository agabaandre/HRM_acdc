/* Swagger UI boot — kept external so CSP script-src 'self' allows it */
window.onload = function () {
  if (typeof SwaggerUIBundle === 'undefined') {
    document.body.innerHTML =
      '<pre style="padding:1rem;font-family:monospace">Swagger UI failed to load. ' +
      'Check that /docs-assets/swagger-ui-bundle.js is reachable.</pre>';
    return;
  }

  var token = null;
  try {
    token = window.sessionStorage.getItem('email_server.token');
  } catch (e) {
    token = null;
  }

  SwaggerUIBundle({
    url: '/api/docs.json',
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
    layout: 'BaseLayout',
    persistAuthorization: true,
    defaultModelsExpandDepth: 2,
    defaultModelExpandDepth: 3,
    tryItOutEnabled: true,
    requestInterceptor: function (req) {
      // Prefer sessionStorage bearer (same-tab SPA); cookie covers top-level navigation.
      if (token && req && req.headers) {
        req.headers.Authorization = 'Bearer ' + token;
      }
      return req;
    },
    responseInterceptor: function (res) {
      if (res && res.status === 401) {
        window.location.replace('/login?redirect=' + encodeURIComponent('/api/documentation'));
      }
      return res;
    },
  });
};
