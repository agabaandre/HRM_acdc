/**
 * Protected Route component - ensures user is authenticated
 */
import React from 'react';
import { useAuth } from '../providers/AuthProvider';

const ProtectedRoute = ({ children }) => {
  const { loading, isAuthenticated } = useAuth();

  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center" style={{ minHeight: '100vh' }}>
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">Loading...</span>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return null; // Will redirect via AuthProvider
  }

  return <>{children}</>;
};

export default ProtectedRoute;

