import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import './Nav.css';

function Nav() {
  const location = useLocation();

  const financeMenuItems = [
    {
      path: '/',
      icon: 'fas fa-tachometer-alt',
      title: 'Dashboard',
    },
    {
      path: '/my-advances',
      icon: 'fas fa-money-bill-wave',
      title: 'My Advances',
    },
    {
      path: '/my-missions',
      icon: 'fas fa-plane',
      title: 'My Missions',
    },
    {
      path: '/budgets',
      icon: 'fas fa-wallet',
      title: 'Budgets',
    },
  ];

  return (
    <div className="nav-container primary-menu">
      <nav className="navbar navbar-expand-xl w-100">
        <ul className="navbar-nav justify-content-start">
          {financeMenuItems.map((item) => (
            <li key={item.path} className="nav-item">
              <Link
                to={item.path}
                className={`nav-link ${location.pathname === item.path ? 'active' : ''}`}
              >
                <div className="parent-icon">
                  <i className={item.icon}></i>
                </div>
                <div className="menu-title">{item.title}</div>
              </Link>
            </li>
          ))}
        </ul>
      </nav>
    </div>
  );
}

export default Nav;

