/**
 * Session Service
 * Handles session-related business logic
 */
const { AppError } = require('../utils/errorHandler');
const logger = require('../utils/logger');

class SessionService {
  /**
   * Transfer session from CI app
   * @param {object} sessionData - Session data from CI app
   * @param {object} session - Express session object
   * @returns {Promise<object>} User data
   */
  async transferSession(sessionData, session) {
    if (!sessionData) {
      throw new AppError('Session data is required', 400, 'MISSING_SESSION_DATA');
    }

    logger.info('Session transfer initiated', {
      hasBaseUrl: !!sessionData.base_url,
      hasPermissions: !!sessionData.permissions,
      staffId: sessionData.staff_id || sessionData.id || 'N/A'
    });

    // Store session data exactly like Laravel APM does
    session.user = sessionData;
    session.base_url = sessionData.base_url || '';
    session.permissions = sessionData.permissions || [];
    session.authenticated = true;
    session.transferredAt = new Date().toISOString();

    // Save session
    return new Promise((resolve, reject) => {
      session.save((err) => {
        if (err) {
          logger.error('Failed to save session', { error: err.message });
          reject(new AppError('Failed to save session', 500, 'SESSION_SAVE_ERROR'));
          return;
        }

        logger.info('Session transferred successfully', {
          sessionId: session.id
        });

        resolve({
          user: sessionData,
          base_url: session.base_url,
          permissions: session.permissions
        });
      });
    });
  }

  /**
   * Get current session
   * @param {object} session - Express session object
   * @returns {object|null} Session data or null
   */
  getSession(session) {
    if (!session || !session.authenticated || !session.user) {
      return null;
    }

    return {
      authenticated: true,
      user: session.user,
      base_url: session.base_url || '',
      permissions: session.permissions || [],
      transferredAt: session.transferredAt
    };
  }

  /**
   * Check if user has Finance access (permission 92)
   * @param {object} session - Express session object
   * @returns {boolean}
   */
  hasFinanceAccess(session) {
    if (!session || !session.authenticated) {
      return false;
    }

    const permissions = session.permissions || [];
    return permissions.includes('92') || permissions.includes(92);
  }

  /**
   * Destroy session
   * @param {object} session - Express session object
   * @returns {Promise<void>}
   */
  async destroySession(session) {
    return new Promise((resolve, reject) => {
      if (!session) {
        resolve();
        return;
      }

      session.destroy((err) => {
        if (err) {
          logger.error('Failed to destroy session', { error: err.message });
          reject(new AppError('Failed to destroy session', 500, 'SESSION_DESTROY_ERROR'));
          return;
        }

        logger.info('Session destroyed successfully');
        resolve();
      });
    });
  }
}

module.exports = new SessionService();

