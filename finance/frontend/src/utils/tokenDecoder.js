/**
 * Token decoding utilities for session transfer.
 * Supports JWT (current) and base64 JSON (legacy).
 */

/**
 * Decode token from URL parameter.
 * @param {string} encodedToken - JWT or base64 encoded token from URL
 * @returns {object} Decoded session data
 * @throws {Error} If token is invalid or cannot be decoded
 */
const decodeBase64Url = (input) => {
  const normalized = input.replace(/-/g, '+').replace(/_/g, '/');
  const padding = '='.repeat((4 - (normalized.length % 4)) % 4);
  return atob(normalized + padding);
};

export const decodeToken = (encodedToken) => {
  if (!encodedToken) {
    throw new Error('Token is required');
  }

  try {
    // Handle URL decoding if needed (token might be double-encoded)
    // URLSearchParams.get() automatically URL decodes (like Laravel's $request->query())
    let tokenToDecode = encodedToken;
    try {
      // Try decoding URL encoding first (in case it's double-encoded)
      tokenToDecode = decodeURIComponent(encodedToken);
    } catch (e) {
      // If already decoded, use as is
      tokenToDecode = encodedToken;
    }

    // JWT path
    const parts = tokenToDecode.split('.');
    if (parts.length === 3) {
      const payload = JSON.parse(decodeBase64Url(parts[1]));
      if (!payload || typeof payload !== 'object') {
        throw new Error('Invalid JWT payload');
      }
      if (payload.exp && Number(payload.exp) < Math.floor(Date.now() / 1000)) {
        throw new Error('Token expired');
      }
      return payload;
    }

    // Legacy base64(json) path
    while (tokenToDecode.length % 4) {
      tokenToDecode += '=';
    }
    const decodedToken = atob(tokenToDecode);
    
    // Parse JSON (Laravel: json_decode($decodedToken, true))
    const json = JSON.parse(decodedToken);

    if (!json) {
      throw new Error('Invalid token format: empty data');
    }

    return json;
  } catch (error) {
    console.error('Token decode error:', error);
    console.error('Token value (first 50 chars):', encodedToken.substring(0, 50));
    throw new Error(`Failed to decode token: ${error.message}`);
  }
};

/**
 * Extract token from URL query parameters
 * @returns {string|null} Token from URL or null if not found
 */
export const getTokenFromUrl = () => {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get('token');
};

/**
 * Remove token from URL without page reload
 */
export const removeTokenFromUrl = () => {
  window.history.replaceState({}, document.title, window.location.pathname);
};

