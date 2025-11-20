/**
 * Custom hook for Google Translate integration
 */
import { useEffect } from 'react';
import {
  initializeGoogleTranslateElement,
  doGTranslate,
  getGoogleTranslateLang
} from '../utils/translation';

/**
 * Hook to initialize and apply Google Translate based on user's preferred language
 * @param {object} user - User object with language preference
 */
export const useTranslation = (user) => {
  useEffect(() => {
    // Make Google Translate initialization function globally available
    window.googleTranslateElementInit = initializeGoogleTranslateElement;
  }, []);

  useEffect(() => {
    if (!user) return;

    // Get user's preferred language from session
    const userLanguage = user.langauge || user.language || 'en';
    const preferredLang = getGoogleTranslateLang(userLanguage);

    // Initialize Google Translate element if not already initialized
    const initTranslation = () => {
      const translateElement = document.getElementById('google_translate_element');
      if (!translateElement) return;

      if (!translateElement.hasChildNodes()) {
        if (typeof window.googleTranslateElementInit === 'function') {
          window.googleTranslateElementInit();
        }
      }

      // Apply translation after a delay to let Google Translate load
      setTimeout(() => {
        doGTranslate(preferredLang);
      }, 1500);
    };

    // Check if Google Translate is already loaded
    if (typeof window.google !== 'undefined' && window.google.translate) {
      initTranslation();
    } else {
      // Wait for Google Translate to load
      const checkGoogleTranslate = setInterval(() => {
        if (typeof window.google !== 'undefined' && window.google.translate) {
          clearInterval(checkGoogleTranslate);
          initTranslation();
        }
      }, 100);

      // Cleanup after 10 seconds if Google Translate doesn't load
      setTimeout(() => {
        clearInterval(checkGoogleTranslate);
      }, 10000);
    }
  }, [user]);
};

