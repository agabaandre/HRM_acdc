import React from 'react';
import Layout from './Layout';
import './Dashboard.css';

function MyMissions({ user, onLogout }) {
  return (
    <Layout user={user} onLogout={onLogout} title="My Missions">
      <div className="dashboard-container">
        <div className="dashboard-header mb-4">
          <h1 className="dashboard-title">My Missions</h1>
          <p className="text-muted mb-0">
            View and manage your mission requests
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

export default MyMissions;

