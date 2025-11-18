import React from 'react';
import './Footer.css';

function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <>
      <div className="overlay toggle-icon"></div>
      <a href="javaScript:;" className="back-to-top">
        <i className='bx bxs-up-arrow-alt'></i>
      </a>
      <footer className="page-footer">
        <p className="mb-0">Copyright © Africa CDC {currentYear}. All right reserved.</p>
      </footer>
    </>
  );
}

export default Footer;

