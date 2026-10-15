/**
 * Token decoding utilities for session transfer
 * Matches Laravel APM implementation: token is urlencoded(base64(json_encode($session)))
 */

/**
 * Decode a base64 token from URL parameter
 * @param {string} base64Token - Base64 encoded token from URL
 * @returns {object} Decoded session data
 * @throws {Error} If token is invalid or cannot be decoded
 */
export const decodeToken = (base64Token) => {
  if (!base64Token) {
    throw new Error('Token is required');
  }

  try {
    // Handle URL decoding if needed (token might be double-encoded)
    // URLSearchParams.get() automatically URL decodes (like Laravel's $request->query())
    let tokenToDecode = base64Token;
    try {
      // Try decoding URL encoding first (in case it's double-encoded)
      tokenToDecode = decodeURIComponent(base64Token);
    } catch (e) {
      // If already decoded, use as is
      tokenToDecode = base64Token;
    }

    // Fix base64 padding if needed (atob requires proper padding)
    // Base64 strings should have length multiple of 4
    while (tokenToDecode.length % 4) {
      tokenToDecode += '=';
    }

    // Base64 decode (Laravel: base64_decode($base64Token))
    const decodedToken = atob(tokenToDecode);
    
    // Parse JSON (Laravel: json_decode($decodedToken, true))
    const json = JSON.parse(decodedToken);

    if (!json) {
      throw new Error('Invalid token format: empty data');
    }

    return json;
  } catch (error) {
    console.error('Token decode error:', error);
    console.error('Token value (first 50 chars):', base64Token.substring(0, 50));
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

