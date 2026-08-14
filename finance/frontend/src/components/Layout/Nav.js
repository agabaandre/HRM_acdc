import React, { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import './Nav.css';

function Nav() {
  const location = useLocation();
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  const financeMenuItems = [
    {
      path: '/',
      icon: 'fas fa-tachometer-alt',
      title: 'Dashboard',
    },
    {
      path: '/my-advances',
      icon: 'fas fa-hand-holding-usd',
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

  // Close menu when route changes
  useEffect(() => {
    setIsMenuOpen(false);
  }, [location.pathname]);

  // Close menu when clicking outside
  useEffect(() => {
    if (!isMenuOpen) return;

    const handleClick = (e) => {
      const nav = e.target.closest('.nav-container');
      if (!nav) {
        setIsMenuOpen(false);
      }
    };

    // Small delay to avoid immediate closure
    const timer = setTimeout(() => {
      document.addEventListener('click', handleClick);
    }, 100);

    return () => {
      clearTimeout(timer);
      document.removeEventListener('click', handleClick);
    };
  }, [isMenuOpen]);

  const toggleMenu = () => {
    setIsMenuOpen(!isMenuOpen);
  };

  return (
    <div className="nav-container primary-menu">
      <nav className="navbar navbar-expand-xl w-100">
        <button
          className="mobile-menu-toggle d-xl-none"
          onClick={toggleMenu}
          type="button"
          aria-label="Toggle menu"
        >
          <i className={`fas ${isMenuOpen ? 'fa-times' : 'fa-bars'}`}></i>
        </button>

        <ul className={`navbar-nav justify-content-start ${isMenuOpen ? 'menu-open' : ''}`}>
          {financeMenuItems.map((item) => (
            <li key={item.path} className="nav-item">
              <Link
                to={item.path}
                className={`nav-link ${location.pathname === item.path ? 'active' : ''}`}
                onClick={() => setIsMenuOpen(false)}
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
