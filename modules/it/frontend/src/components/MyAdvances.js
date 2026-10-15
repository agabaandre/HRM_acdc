import React from 'react';
import Layout from './Layout';
import './Dashboard.css';

function MyAdvances({ user, onLogout }) {
  return (
    <Layout user={user} onLogout={onLogout} title="My Advances">
      <div className="dashboard-container">
        <div className="dashboard-header mb-4">
          <h1 className="dashboard-title">My Advances</h1>
          <p className="text-muted mb-0">
            View and manage your advance requests
          </p>
        </div>
        <div className="alert alert-info">
          <i className="fas fa-info-circle me-2"></i>
          This page is under development.
        </div>
      </div>
    </Layout>
  );
}

export default MyAdvances;

