import React from 'react';
import Header from './Layout/Header';
import Nav from './Layout/Nav';
import Footer from './Layout/Footer';
import Breadcrumbs from './Layout/Breadcrumbs';
import './Layout/Layout.css';

function Layout({ children, user, onLogout, title = 'Finance Management' }) {
  return (
    <div className="wrapper">
      <Header user={user} onLogout={onLogout} />
      <Nav />
      <div className="page-wrapper">
        <div className="page-content">
          <Breadcrumbs title={title} />
          <div className="card">
            {children}
          </div>
        </div>
      </div>
      <Footer />
    </div>
  );
}

export default Layout;

