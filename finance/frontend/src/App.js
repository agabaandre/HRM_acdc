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

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

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
        // Base64 decode (Laravel: base64_decode($base64Token))
        const decodedToken = atob(base64Token);
        // Parse JSON (Laravel: json_decode($decodedToken, true))
        const json = JSON.parse(decodedToken);
        
        // Transfer session to Node.js server
        // The server will store it exactly like Laravel: user, base_url, permissions
        axios.post(`${API_BASE_URL}/session/transfer`, { sessionData: json })
          .then(response => {
            if (response.data.success) {
              // Store full user object like Laravel does
              setUser(response.data.user);
              // Remove token from URL
              window.history.replaceState({}, document.title, window.location.pathname);
            } else {
              // Redirect to CI login if session transfer fails
              redirectToCiLogin();
            }
            setLoading(false);
          })
          .catch(error => {
            console.error('Session transfer error:', error);
            redirectToCiLogin();
          });
      } catch (error) {
        console.error('Token decode error:', error);
        redirectToCiLogin();
      }
    } else {
      // Check existing session
      checkSession();
    }
  }, []);

  const checkSession = async () => {
    try {
      const response = await axios.get(`${API_BASE_URL}/session`);
      if (response.data.authenticated && response.data.user) {
        // Store full user object with all properties
        setUser(response.data.user);
      } else {
        // No session found, redirect to CI login
        redirectToCiLogin();
      }
    } catch (error) {
      console.error('Session check error:', error);
      // On error, redirect to CI login
      redirectToCiLogin();
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

  return (
    <Router>
      <Routes>
        <Route
          path="/"
          element={<Dashboard user={user} onLogout={handleLogout} />}
        />
        <Route
          path="/my-advances"
          element={<MyAdvances user={user} onLogout={handleLogout} />}
        />
        <Route
          path="/my-missions"
          element={<MyMissions user={user} onLogout={handleLogout} />}
        />
        <Route
          path="/budgets"
          element={<Budgets user={user} onLogout={handleLogout} />}
        />
        {/* Catch all other routes and redirect to home */}
        <Route
          path="*"
          element={<Dashboard user={user} onLogout={handleLogout} />}
        />
      </Routes>
    </Router>
  );
}

export default App;

