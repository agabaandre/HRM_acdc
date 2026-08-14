/**
 * Main App component
 * Refactored to use providers, services, and hooks
 */
import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { SnackbarProvider } from './components/Layout/SnackbarProvider';
import { AuthProvider, useAuth } from './providers/AuthProvider';
import { useTranslation } from './hooks/useTranslation';
import ProtectedRoute from './components/ProtectedRoute';
import Dashboard from './components/Dashboard';
import MyAdvances from './components/MyAdvances';
import MyMissions from './components/MyMissions';
import Budgets from './components/Budgets';
import { getBasePath } from './utils/config';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import './App.css';

/**
 * App Routes component - handles routing logic
 */
const AppRoutes = () => {
  const { user, permissions, hasPermission, logout } = useAuth();
  
  // Initialize translation based on user's preferred language
  useTranslation(user);

  // Determine base path for React Router
  const basename = getBasePath();

  return (
    <Router basename={basename}>
      <Routes>
        <Route
          path="/"
          element={
            <Dashboard 
              user={user} 
              permissions={permissions} 
              hasPermission={hasPermission} 
              onLogout={logout} 
            />
          }
        />
        <Route
          path="/my-advances"
          element={
            <MyAdvances 
              user={user} 
              permissions={permissions} 
              hasPermission={hasPermission} 
              onLogout={logout} 
            />
          }
        />
        <Route
          path="/my-missions"
          element={
            <MyMissions 
              user={user} 
              permissions={permissions} 
              hasPermission={hasPermission} 
              onLogout={logout} 
            />
          }
        />
        <Route
          path="/budgets"
          element={
            <Budgets 
              user={user} 
              permissions={permissions} 
              hasPermission={hasPermission} 
              onLogout={logout} 
            />
          }
        />
        {/* Catch all other routes and redirect to home */}
        <Route
          path="*"
          element={
            <Dashboard 
              user={user} 
              permissions={permissions} 
              hasPermission={hasPermission} 
              onLogout={logout} 
            />
          }
        />
      </Routes>
    </Router>
  );
};

/**
 * Main App component
 */
function App() {
  return (
    <SnackbarProvider>
      <AuthProvider>
        <ProtectedRoute>
          <AppRoutes />
        </ProtectedRoute>
      </AuthProvider>
    </SnackbarProvider>
  );
}

export default App;
