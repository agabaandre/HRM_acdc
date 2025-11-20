/**
 * Google Translate utility functions
 */

/**
 * Fire a custom event on an element (for Google Translate)
 * @param {HTMLElement} element - Element to fire event on
 * @param {string} event - Event name
 */
export const GTranslateFireEvent = (element, event) => {
  try {
    if (document.createEventObject) {
      const evt = document.createEventObject();
      element.fireEvent('on' + event, evt);
    } else {
      const evt = document.createEvent('HTMLEvents');
      evt.initEvent(event, true, true);
      element.dispatchEvent(evt);
    }
  } catch (e) {
    // Ignore errors
  }
};

/**
 * Apply Google Translate to a specific language
 * @param {string} langCode - Language code (e.g., 'en', 'fr', 'sw')
 */
export const doGTranslate = (langCode) => {
  const lang = langCode || 'en';
  const interval = setInterval(() => {
    const teCombo = document.querySelector('select.goog-te-combo');
    if (teCombo && teCombo.options.length > 0) {
      const langIndex = Array.from(teCombo.options).findIndex(option => option.value === lang);
      if (langIndex !== -1) {
        teCombo.selectedIndex = langIndex;
        GTranslateFireEvent(teCombo, 'change');
        GTranslateFireEvent(teCombo, 'change');
        clearInterval(interval);
      }
    }
  }, 500);
};

/**
 * Initialize Google Translate element
 * This function is made globally available for the Google Translate script callback
 */
export const initializeGoogleTranslateElement = () => {
  if (typeof window.google !== 'undefined' && window.google.translate) {
    new window.google.translate.TranslateElement({
      pageLanguage: 'en',
      autoDisplay: false,
      disableAutoHover: true,
      showBanner: false
    }, 'google_translate_element');
  }
};

/**
 * Map user language codes to Google Translate codes
 */
export const LANGUAGE_MAP = {
  'en': 'en',
  'fr': 'fr',
  'sw': 'sw',
  'ar': 'ar',
  'pt': 'pt',
  'es': 'es'
};

/**
 * Get Google Translate language code from user language
 * @param {string} userLanguage - User's preferred language
 * @returns {string} Google Translate language code
 */
export const getGoogleTranslateLang = (userLanguage) => {
  return LANGUAGE_MAP[userLanguage] || 'en';
};

