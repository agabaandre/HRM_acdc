import React from 'react';
import { useLocation } from 'react-router-dom';
import './Breadcrumbs.css';

function Breadcrumbs({ title }) {
  const location = useLocation();

  return (
    <div className="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
      <div className="ps-3">
        <nav aria-label="breadcrumb">
          <ol className="breadcrumb mb-0 p-0">
            <li className="breadcrumb-item">
              <a href="/">
                <i className="fas fa-home"></i> Home
              </a>
            </li>
            {location.pathname !== '/' && (
              <li className="breadcrumb-item active" aria-current="page">
                {title}
              </li>
            )}
          </ol>
        </nav>
      </div>
    </div>
  );
}

export default Breadcrumbs;

