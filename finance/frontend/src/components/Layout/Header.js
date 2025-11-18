import React from 'react';
import './Header.css';

function Header({ user, onLogout }) {
  const baseUrl = user?.base_url || process.env.REACT_APP_CI_BASE_URL || 'http://localhost/staff';
  // APM is located at baseUrl + '/apm' (e.g., http://localhost/staff/apm)
  const apmUrl = `${baseUrl}/apm`;
  
  // Generate token for APM (matches CI format: urlencode(base64_encode(json_encode($session))))
  const generateApmToken = () => {
    if (!user) return '';
    try {
      const sessionData = {
        ...user,
        base_url: user.base_url || baseUrl,
        permissions: user.permissions || []
      };
      const jsonString = JSON.stringify(sessionData);
      const base64String = btoa(jsonString);
      return encodeURIComponent(base64String);
    } catch (error) {
      console.error('Error generating APM token:', error);
      return '';
    }
  };
  
  // Get user photo or generate avatar
  const getUserAvatar = () => {
    if (user?.photo) {
      const photoUrl = user.photo_data 
        ? `data:image/jpeg;base64,${user.photo_data}`
        : `${baseUrl}/uploads/staff/${user.photo}`;
      
      return (
        <img 
          src={photoUrl} 
          className="user-img" 
          alt="User"
          onError={(e) => {
            // Fallback to initials if image fails to load
            e.target.style.display = 'none';
            e.target.nextSibling.style.display = 'flex';
          }}
        />
      );
    }
    
    // Generate initials avatar
    const firstName = user?.fname || user?.name?.split(' ')[0] || 'U';
    const lastName = user?.lname || user?.name?.split(' ')[1] || '';
    const initials = (firstName[0] + (lastName ? lastName[0] : '')).toUpperCase();
    
    // Generate color based on name
    const colors = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];
    const colorIndex = (firstName.charCodeAt(0) - 65) % colors.length;
    const bgColor = colors[colorIndex >= 0 ? colorIndex : 0];
    
    return (
      <div 
        className="user-avatar text-white d-flex align-items-center justify-content-center" 
        style={{
          fontWeight: 600,
          fontSize: '1.1rem',
          width: '40px',
          height: '40px',
          borderRadius: '50%',
          backgroundColor: bgColor
        }}
      >
        {initials}
      </div>
    );
  };

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
          <div className="top-menu ms-auto me-3">
            <ul className="navbar-nav align-items-center">
              {/* APM Menu Item */}
              <li className="nav-item">
                <a 
                  className="nav-link" 
                  href={`${apmUrl}?token=${generateApmToken()}`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <i className='fa fa-sitemap' style={{ color: '#FFF' }}></i>
                  <span className="ms-2 d-none d-md-inline" style={{ color: '#FFF' }}>APM</span>
                </a>
              </li>
              
              {/* Staff Portal Menu Item */}
              <li className="nav-item">
                <a 
                  className="nav-link" 
                  href={`${baseUrl}/auth/profile`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <i className='bx bx-user' style={{ color: '#FFF' }}></i>
                  <span className="ms-2 d-none d-md-inline" style={{ color: '#FFF' }}>Staff Portal</span>
                </a>
              </li>
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
              {getUserAvatar()}
              <div className="user-info ps-3">
                <p className="user-name mb-0">{user?.name || 'User'}</p>
                <p className="designattion mb-0"></p>
              </div>
            </a>
            <ul className="dropdown-menu dropdown-menu-end">
              <li>
                <a className="dropdown-item" href={`${baseUrl}/auth/profile`} target="_blank" rel="noopener noreferrer">
                  <i className="bx bx-user"></i>
                  <span>Profile</span>
                </a>
              </li>
              <li>
                <a className="dropdown-item" href={`${apmUrl}?token=${generateApmToken()}`} target="_blank" rel="noopener noreferrer">
                  <i className="fa fa-sitemap"></i>
                  <span>Approvals Management</span>
                </a>
              </li>
              <li>
                <a className="dropdown-item" href={`${baseUrl}/auth/profile`} target="_blank" rel="noopener noreferrer">
                  <i className="bx bx-home"></i>
                  <span>Staff Portal</span>
                </a>
              </li>
              <li>
                <div className="dropdown-divider mb-0"></div>
              </li>
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

