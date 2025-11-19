import React from 'react';
import './Footer.css';

function Footer() {
  const currentYear = new Date().getFullYear();

  const handleBackToTop = (e) => {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <>
      <div className="overlay toggle-icon"></div>
      <a href="#" className="back-to-top" onClick={handleBackToTop}>
        <i className='bx bxs-up-arrow-alt'></i>
      </a>
      <footer className="page-footer">
        <p className="mb-0">Copyright © Africa CDC {currentYear}. All right reserved.</p>
      </footer>
    </>
  );
}

export default Footer;

