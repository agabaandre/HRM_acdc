/**
 * Session service for handling authentication and session management
 */
import apiClient from './apiService';
import { decodeToken, getTokenFromUrl, removeTokenFromUrl } from '../utils/tokenDecoder';
import { CI_BASE_URL } from '../utils/config';

/**
 * Transfer session from CI app to Finance app
 * @param {object} sessionData - Decoded session data from token
 * @returns {Promise<object>} Response with user data
 */
export const transferSession = async (sessionData) => {
  try {
    const response = await apiClient.post('/session/transfer', { sessionData });
    return response.data;
  } catch (error) {
    console.error('Session transfer error:', error);
    throw error;
  }
};

/**
 * Check current session
 * @returns {Promise<object>} Session data including user and permissions
 */
export const checkSession = async () => {
  try {
    const response = await apiClient.get('/session');
    return response.data;
  } catch (error) {
    console.error('Session check error:', error);
    throw error;
  }
};

/**
 * Logout user
 * @returns {Promise<void>}
 */
export const logout = async () => {
  try {
    await apiClient.post('/auth/logout');
  } catch (error) {
    console.error('Logout error:', error);
    // Continue with redirect even if logout fails
  }
};

/**
 * Process token from URL and transfer session
 * @returns {Promise<object>} User data and permissions
 */
export const processTokenFromUrl = async () => {
  const base64Token = getTokenFromUrl();
  
  if (!base64Token) {
    return null;
  }

  try {
    // Decode token
    const sessionData = decodeToken(base64Token);
    
    console.log('Token decoded successfully, transferring session...');
    console.log('Decoded session data keys:', Object.keys(sessionData));
    
    // Transfer session to server
    const response = await transferSession(sessionData);
    
    if (response.success) {
      // Remove token from URL
      removeTokenFromUrl();
      return {
        user: response.user,
        permissions: response.user?.permissions || []
      };
    } else {
      throw new Error('Session transfer failed');
    }
  } catch (error) {
    console.error('Error processing token:', error);
    throw error;
  }
};

/**
 * Check if user has Finance access permission (92)
 * @param {Array} permissions - User permissions array
 * @returns {boolean} True if user has permission 92
 */
export const hasFinanceAccess = (permissions) => {
  if (!permissions || !Array.isArray(permissions)) {
    return false;
  }
  return permissions.includes('92') || permissions.includes(92);
};

/**
 * Redirect to CI login page
 */
export const redirectToCiLogin = () => {
  const loginUrl = `${CI_BASE_URL}/auth`;
  window.location.href = loginUrl;
};

