import React, { useState, useEffect } from 'react';
import axios from 'axios';
import Layout from './Layout';
import './Dashboard.css';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:3003/api';

function Dashboard({ user, onLogout }) {
  const [financeData, setFinanceData] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchFinanceData();
  }, []);

  const fetchFinanceData = async () => {
    setLoading(true);
    try {
      const response = await axios.get(`${API_BASE_URL}/finance/data`);
      setFinanceData(response.data);
    } catch (error) {
      console.error('Error fetching finance data:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Layout user={user} onLogout={onLogout} title="Finance Dashboard">
      <div className="dashboard-container">
        {/* Header */}
        <div className="dashboard-header mb-4">
          <h1 className="dashboard-title">Welcome to Finance Management</h1>
          <p className="text-muted mb-0">
            Africa CDC Central Business Platform
          </p>
        </div>

        {/* Main Content */}
        <div className="row g-4">
          {/* Finance Cards */}
          <div className="col-12 col-md-6 col-lg-4">
            <div className="finance-card card-hover">
              <div className="card-icon">
                <i className="fas fa-chart-line"></i>
              </div>
              <h5 className="card-title">Financial Reports</h5>
              <p className="card-description">
                View and generate comprehensive financial reports and analytics.
              </p>
            </div>
          </div>

          <div className="col-12 col-md-6 col-lg-4">
            <div className="finance-card card-hover">
              <div className="card-icon">
                <i className="fas fa-file-invoice-dollar"></i>
              </div>
              <h5 className="card-title">Invoices</h5>
              <p className="card-description">
                Manage invoices, payments, and billing information.
              </p>
            </div>
          </div>

          <div className="col-12 col-md-6 col-lg-4">
            <div className="finance-card card-hover">
              <div className="card-icon">
                <i className="fas fa-wallet"></i>
              </div>
              <h5 className="card-title">Budget Management</h5>
              <p className="card-description">
                Track budgets, expenses, and financial allocations.
              </p>
            </div>
          </div>

          <div className="col-12 col-md-6 col-lg-4">
            <div className="finance-card card-hover">
              <div className="card-icon">
                <i className="fas fa-exchange-alt"></i>
              </div>
              <h5 className="card-title">Transactions</h5>
              <p className="card-description">
                View and manage all financial transactions and records.
              </p>
            </div>
          </div>

          <div className="col-12 col-md-6 col-lg-4">
            <div className="finance-card card-hover">
              <div className="card-icon">
                <i className="fas fa-users"></i>
              </div>
              <h5 className="card-title">Vendors</h5>
              <p className="card-description">
                Manage vendor information and payment details.
              </p>
            </div>
          </div>

          <div className="col-12 col-md-6 col-lg-4">
            <div className="finance-card card-hover">
              <div className="card-icon">
                <i className="fas fa-cog"></i>
              </div>
              <h5 className="card-title">Settings</h5>
              <p className="card-description">
                Configure finance system settings and preferences.
              </p>
            </div>
          </div>
        </div>

        {/* Session Info */}
        {financeData && (
          <div className="mt-4">
            <div className="alert alert-info">
              <strong>Session Status:</strong> {financeData.data?.message || 'Connected'}
              <br />
              <small>User: {financeData.data?.user?.name || user?.name}</small>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
}

export default Dashboard;

