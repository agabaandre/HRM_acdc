const { createProxyMiddleware } = require('http-proxy-middleware');

module.exports = function(app) {
  // Only proxy API requests to the backend server
  // Static assets (favicon, images, etc.) will be served by React dev server
  
  // Handle /finance/api requests (when accessing via /finance path)
  app.use(
    '/finance/api',
    createProxyMiddleware({
      target: 'http://localhost:3003',
      changeOrigin: true,
      pathRewrite: {
        '^/finance/api': '/api', // Rewrite /finance/api to /api
      },
      logLevel: 'silent', // Reduce noise in console
      onProxyReq: (proxyReq, req, res) => {
        // Log API requests for debugging
        console.log(`[PROXY] ${req.method} ${req.url} -> http://localhost:3003${proxyReq.path}`);
      },
      onError: (err, req, res) => {
        console.error('[PROXY ERROR]', err.message);
        console.error('[PROXY ERROR] Is the backend server running on port 3003?');
      }
    })
  );

  // Also handle direct /api requests (for development without /finance prefix)
  app.use(
    '/api',
    createProxyMiddleware({
      target: 'http://localhost:3003',
      changeOrigin: true,
      logLevel: 'silent',
      onProxyReq: (proxyReq, req, res) => {
        console.log(`[PROXY] ${req.method} ${req.url} -> http://localhost:3003${proxyReq.path}`);
      },
      onError: (err, req, res) => {
        console.error('[PROXY ERROR]', err.message);
        console.error('[PROXY ERROR] Is the backend server running on port 3003?');
      }
    })
  );
};

