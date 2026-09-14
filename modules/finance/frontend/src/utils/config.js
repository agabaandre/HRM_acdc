/**
 * Configuration utilities for API URLs and base paths
 */

/**
 * Determine API base URL based on environment
 * @returns {string} API base URL
 */
export const getApiBaseUrl = () => {
  if (process.env.NODE_ENV === 'production') {
    // In production, use relative path which will work with reverse proxy
    // If accessed at /finance, API will be at /finance/api
    const pathname = window.location.pathname;
    if (pathname.startsWith('/finance')) {
      return '/finance/api';
    }
    return '/api';
  }
  // Development: use relative path so proxy can handle it
  // The setupProxy.js will proxy /api to http://localhost:3003/api
  const pathname = window.location.pathname;
  if (pathname.startsWith('/finance')) {
    return '/finance/api'; // Proxy will rewrite this to /api
  }
  return '/api'; // Proxy will forward to http://localhost:3003/api
};

/**
 * Get CI base URL from environment or default
 * @returns {string} CI base URL
 */
export const getCiBaseUrl = () => {
  return process.env.REACT_APP_CI_BASE_URL || 'http://localhost/staff';
};

/**
 * Determine base path for React Router based on environment
 * @returns {string} Base path for React Router
 */
export const getBasePath = () => {
  if (process.env.NODE_ENV === 'production') {
    // Check if we're behind the reverse proxy
    const pathname = window.location.pathname;
    if (pathname.startsWith('/finance')) {
      return '/finance';
    }
  }
  return '/';
};

// Export constants
export const API_BASE_URL = getApiBaseUrl();
export const CI_BASE_URL = getCiBaseUrl();

