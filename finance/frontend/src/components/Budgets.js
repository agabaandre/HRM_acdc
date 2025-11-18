import React from 'react';
import Layout from './Layout';
import './Dashboard.css';

function Budgets({ user, onLogout }) {
  return (
    <Layout user={user} onLogout={onLogout} title="Budgets">
      <div className="dashboard-container">
        <div className="dashboard-header mb-4">
          <h1 className="dashboard-title">Budgets</h1>
          <p className="text-muted mb-0">
            View and manage budget allocations and expenditures
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

export default Budgets;

