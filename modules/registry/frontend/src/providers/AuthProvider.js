/**
 * Authentication Context Provider
 * Manages user authentication state, permissions, and session
 */
import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { 
  processTokenFromUrl, 
  checkSession, 
  logout as logoutService,
  hasFinanceAccess,
  redirectToCiLogin 
} from '../services/sessionService';

const AuthContext = createContext(null);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  /**
   * Check if user has a specific permission
   * @param {string|number} permissionId - Permission ID to check
   * @returns {boolean} True if user has the permission
   */
  const hasPermission = useCallback((permissionId) => {
    if (!permissions || !Array.isArray(permissions)) {
      return false;
    }
    // Check both string and number formats (permissions can be stored as either)
    return permissions.includes(String(permissionId)) || permissions.includes(Number(permissionId));
  }, [permissions]);

  /**
   * Initialize session - check for token or existing session
   */
  const initializeSession = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      // First, check if there's a token in the URL
      const tokenData = await processTokenFromUrl();
      
      if (tokenData) {
        // Token was processed successfully
        const { user: userData, permissions: userPermissions } = tokenData;
        
        // Check Finance access permission
        if (!hasFinanceAccess(userPermissions)) {
          console.error('User does not have Finance access permission (92)');
          alert('You do not have permission to access the Finance module. Please contact your administrator.');
          redirectToCiLogin();
          return;
        }
        
        setUser(userData);
        setPermissions(userPermissions);
        setLoading(false);
        return;
      }

      // No token, check existing session
      const sessionData = await checkSession();
      
      if (sessionData.authenticated && sessionData.user) {
        const userData = sessionData.user;
        const userPermissions = sessionData.permissions || userData.permissions || [];
        
        // Check Finance access permission
        if (!hasFinanceAccess(userPermissions)) {
          console.error('User does not have Finance access permission (92)');
          console.error('User permissions:', userPermissions);
          alert('You do not have permission to access the Finance module. Please contact your administrator.');
          redirectToCiLogin();
          return;
        }
        
        setUser(userData);
        setPermissions(userPermissions);
        setLoading(false);
      } else {
        // No session found
        console.log('No session found, redirecting to CI login');
        redirectToCiLogin();
      }
    } catch (error) {
      console.error('Session initialization error:', error);
      
      // Handle specific error cases
      if (error.response?.status === 401) {
        console.log('401 Unauthorized - redirecting to CI login');
        redirectToCiLogin();
        return;
      }
      
      // Network errors
      if (error.code === 'ECONNREFUSED' || error.message.includes('Network Error')) {
        console.error('Cannot connect to backend server. Is it running on port 3003?');
        setError('Cannot connect to the Finance server. Please ensure the backend server is running.');
        setLoading(false);
        return;
      }
      
      // Other errors
      setError(error.message || 'An error occurred while initializing session');
      setLoading(false);
    }
  }, []);

  /**
   * Logout user and redirect to CI login
   */
  const logout = useCallback(async () => {
    try {
      await logoutService();
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Always redirect to CI login on logout
      redirectToCiLogin();
    }
  }, []);

  // Initialize session on mount
  useEffect(() => {
    initializeSession();
  }, [initializeSession]);

  const value = {
    user,
    permissions,
    loading,
    error,
    hasPermission,
    logout,
    isAuthenticated: !!user
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

