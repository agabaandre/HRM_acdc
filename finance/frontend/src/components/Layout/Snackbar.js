import React, { useState, useEffect } from 'react';
import './Snackbar.css';

function Snackbar({ message, type = 'info', duration = 4000, onClose }) {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    if (message) {
      setIsVisible(true);
      const timer = setTimeout(() => {
        setIsVisible(false);
        setTimeout(() => {
          if (onClose) onClose();
        }, 300); // Wait for fade out animation
      }, duration);

      return () => clearTimeout(timer);
    }
  }, [message, duration, onClose]);

  if (!message) return null;

  const getIcon = () => {
    switch (type) {
      case 'success':
        return 'fas fa-check-circle';
      case 'error':
        return 'fas fa-exclamation-circle';
      case 'warning':
        return 'fas fa-exclamation-triangle';
      default:
        return 'fas fa-info-circle';
    }
  };

  return (
    <div className={`snackbar snackbar-${type} ${isVisible ? 'show' : ''}`}>
      <div className="snackbar-content">
        <i className={getIcon()}></i>
        <span className="snackbar-message">{message}</span>
        <button 
          className="snackbar-close" 
          onClick={() => {
            setIsVisible(false);
            setTimeout(() => {
              if (onClose) onClose();
            }, 300);
          }}
        >
          <i className="fas fa-times"></i>
        </button>
      </div>
    </div>
  );
}

export default Snackbar;

