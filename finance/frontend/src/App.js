import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import axios from 'axios';
import Dashboard from './components/Dashboard';
import MyAdvances from './components/MyAdvances';
import MyMissions from './components/MyMissions';
import Budgets from './components/Budgets';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import './App.css';

// Configure axios defaults
axios.defaults.withCredentials = true;
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:3003/api';
const CI_BASE_URL = process.env.REACT_APP_CI_BASE_URL || 'http://localhost/staff';

// Determine base path for React Router based on environment
// In production behind reverse proxy, use /finance, otherwise use /
const getBasePath = () => {
  if (process.env.NODE_ENV === 'production') {
    // Check if we're behind the reverse proxy
    const pathname = window.location.pathname;
    if (pathname.startsWith('/finance')) {
      return '/finance';
    }
  }
  return '/';
};

function App() {
  const [user, setUser] = useState(null);
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(true);

  // Helper function to check if user has a permission
  const hasPermission = (permissionId) => {
    if (!permissions || !Array.isArray(permissions)) {
      return false;
    }
    // Check both string and number formats (permissions can be stored as either)
    return permissions.includes(String(permissionId)) || permissions.includes(Number(permissionId));
  };

  useEffect(() => {
    // Check for session transfer from CI app
    // Matches Laravel APM implementation: token is urlencoded(base64(json_encode($session)))
    const urlParams = new URLSearchParams(window.location.search);
    const base64Token = urlParams.get('token');

    if (base64Token) {
      // Decode and transfer session from CI - matches Laravel pattern
      // Laravel: base64_decode($base64Token) then json_decode($decodedToken, true)
      // URLSearchParams.get() automatically URL decodes (like Laravel's $request->query())
      try {
        // Handle URL decoding if needed (token might be double-encoded)
        let tokenToDecode = base64Token;
        try {
          // Try decoding URL encoding first (in case it's double-encoded)
          // URLSearchParams.get() should already decode, but let's be safe
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
        
        console.log('Token decoded successfully, transferring session...');
        console.log('Decoded session data keys:', Object.keys(json));
        
        // Transfer session to Node.js server
        // The server will store it exactly like Laravel: user, base_url, permissions
        axios.post(`${API_BASE_URL}/session/transfer`, { sessionData: json }, {
          withCredentials: true, // Ensure cookies are sent and received
          headers: {
            'Content-Type': 'application/json'
          }
        })
          .then(response => {
            console.log('Session transfer response:', response.data);
            if (response.data.success) {
              // Store full user object like Laravel does
              const userData = response.data.user;
              setUser(userData);
              
              // Check permissions
              const userPermissions = userData.permissions || [];
              setPermissions(userPermissions);
              
              // Check if user has permission 92 (Finance access)
              const hasFinanceAccess = userPermissions.includes('92') || userPermissions.includes(92);
              if (!hasFinanceAccess) {
                console.error('User does not have Finance access permission (92)');
                alert('You do not have permission to access the Finance module. Please contact your administrator.');
                redirectToCiLogin();
                return;
              }
              
              // Remove token from URL
              window.history.replaceState({}, document.title, window.location.pathname);
              setLoading(false);
            } else {
              // Redirect to CI login if session transfer fails
              console.error('Session transfer failed:', response.data);
              redirectToCiLogin();
            }
          })
          .catch(error => {
            console.error('Session transfer error:', error);
            console.error('Error details:', error.response?.data || error.message);
            console.error('Error status:', error.response?.status);
            redirectToCiLogin();
          });
      } catch (error) {
        console.error('Token decode error:', error);
        console.error('Token value (first 50 chars):', base64Token.substring(0, 50));
        console.error('Error message:', error.message);
        redirectToCiLogin();
      }
    } else {
      // Check existing session
      checkSession();
    }
  }, []);

  const checkSession = async () => {
    try {
      const response = await axios.get(`${API_BASE_URL}/session`, {
        withCredentials: true // Ensure cookies are sent
      });
      if (response.data.authenticated && response.data.user) {
        // Store full user object with all properties
        const userData = response.data.user;
        setUser(userData);
        
        // Check permissions
        const userPermissions = response.data.permissions || userData.permissions || [];
        setPermissions(userPermissions);
        
        // Check if user has permission 92 (Finance access)
        const hasFinanceAccess = userPermissions.includes('92') || userPermissions.includes(92);
        if (!hasFinanceAccess) {
          console.error('User does not have Finance access permission (92)');
          alert('You do not have permission to access the Finance module. Please contact your administrator.');
          redirectToCiLogin();
          return; // Exit early to prevent setting loading to false
        }
      } else {
        // No session found, redirect to CI login
        redirectToCiLogin();
        return; // Exit early to prevent setting loading to false
      }
    } catch (error) {
      console.error('Session check error:', error);
      // Only redirect on 401 (unauthorized), not on network errors
      if (error.response && error.response.status === 401) {
        redirectToCiLogin();
        return; // Exit early to prevent setting loading to false
      }
      // For other errors, show error but don't redirect immediately
      console.error('Unexpected error checking session:', error);
    } finally {
      setLoading(false);
    }
  };

  const redirectToCiLogin = () => {
    const loginUrl = `${CI_BASE_URL}/auth`;
    window.location.href = loginUrl;
  };

  const handleLogout = async () => {
    try {
      await axios.post(`${API_BASE_URL}/auth/logout`);
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Always redirect to CI login on logout
      redirectToCiLogin();
    }
  };

  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center" style={{ minHeight: '100vh' }}>
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">Loading...</span>
        </div>
      </div>
    );
  }

  // If no user after loading, we're redirecting (component will unmount)
  if (!user) {
    return null;
  }

      // Determine base path for React Router based on environment
      // In production behind reverse proxy, use /finance, otherwise use /
      const basename = (() => {
        if (process.env.NODE_ENV === 'production') {
          // Check if we're behind the reverse proxy
          const pathname = window.location.pathname;
          if (pathname.startsWith('/finance')) {
            return '/finance';
          }
        }
        return '/';
      })();

      return (
        <Router basename={basename}>
          <Routes>
            <Route
              path="/"
              element={<Dashboard user={user} permissions={permissions} hasPermission={hasPermission} onLogout={handleLogout} />}
            />
            <Route
              path="/my-advances"
              element={<MyAdvances user={user} permissions={permissions} hasPermission={hasPermission} onLogout={handleLogout} />}
            />
            <Route
              path="/my-missions"
              element={<MyMissions user={user} permissions={permissions} hasPermission={hasPermission} onLogout={handleLogout} />}
            />
            <Route
              path="/budgets"
              element={<Budgets user={user} permissions={permissions} hasPermission={hasPermission} onLogout={handleLogout} />}
            />
            {/* Catch all other routes and redirect to home */}
            <Route
              path="*"
              element={<Dashboard user={user} permissions={permissions} hasPermission={hasPermission} onLogout={handleLogout} />}
            />
          </Routes>
        </Router>
      );
}

export default App;

