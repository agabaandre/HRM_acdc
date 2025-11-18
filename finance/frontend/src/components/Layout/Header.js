import React from 'react';
import './Header.css';

function Header({ user, onLogout }) {
  const baseUrl = process.env.REACT_APP_BASE_URL || 'http://localhost/staff';

  return (
    <header>
      <div className="topbar d-flex">
        <nav className="navbar navbar-expand">
          <div className="topbar-logo-header">
            <div>
              <img 
                src={`${baseUrl}/assets/images/AU_CDC_Logo-800.png`} 
                width="200"
                style={{ filter: 'brightness(0) invert(1)' }}
                alt="Africa CDC Logo"
              />
            </div>
          </div>
          <div className="mobile-toggle-menu">
            <i className='bx bx-menu'></i>
          </div>
          <div className="search-bar flex-grow-1" style={{ display: 'none' }}>
            <div className="position-relative search-bar-box">
              <input type="text" className="form-control search-control" placeholder="Type to search..." />
              <span className="position-absolute top-50 search-show translate-middle-y">
                <i className='bx bx-search'></i>
              </span>
              <span className="position-absolute top-50 search-close translate-middle-y">
                <i className='bx bx-x'></i>
              </span>
            </div>
          </div>
          <div className="top-menu ms-auto">
            <ul className="navbar-nav align-items-center">
              {/* Add menu items here if needed */}
            </ul>
          </div>
          <div className="user-box dropdown">
            <a 
              className="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret"
              href="#" 
              role="button" 
              data-bs-toggle="dropdown" 
              aria-expanded="false"
            >
              <img 
                src={`${baseUrl}/assets/images/user.png`} 
                className="user-img" 
                alt="User"
              />
              <div className="user-info ps-3">
                <p className="user-name mb-0">{user?.name || 'User'}</p>
                <p className="designattion mb-0">{user?.email || ''}</p>
              </div>
            </a>
            <ul className="dropdown-menu dropdown-menu-end">
              <li>
                <a className="dropdown-item" href="#" onClick={(e) => { e.preventDefault(); onLogout(); }}>
                  <i className="bx bx-log-out-circle"></i>
                  <span>Logout</span>
                </a>
              </li>
            </ul>
          </div>
        </nav>
      </div>
    </header>
  );
}

export default Header;

