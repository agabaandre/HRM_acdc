/**
 * Logger utility
 * Simple logger with different log levels
 * Can be replaced with winston or pino in the future
 */

const config = require('../config');

const logLevels = {
  ERROR: 0,
  WARN: 1,
  INFO: 2,
  DEBUG: 3
};

const currentLogLevel = config.nodeEnv === 'production' ? logLevels.INFO : logLevels.DEBUG;

const formatMessage = (level, message, meta = {}) => {
  const timestamp = new Date().toISOString();
  const metaStr = Object.keys(meta).length > 0 ? ` ${JSON.stringify(meta)}` : '';
  return `[${timestamp}] [${level}] ${message}${metaStr}`;
};

const logger = {
  error: (message, meta = {}) => {
    if (logLevels.ERROR <= currentLogLevel) {
      console.error(formatMessage('ERROR', message, meta));
    }
  },

  warn: (message, meta = {}) => {
    if (logLevels.WARN <= currentLogLevel) {
      console.warn(formatMessage('WARN', message, meta));
    }
  },

  info: (message, meta = {}) => {
    if (logLevels.INFO <= currentLogLevel) {
      console.log(formatMessage('INFO', message, meta));
    }
  },

  debug: (message, meta = {}) => {
    if (logLevels.DEBUG <= currentLogLevel) {
      console.log(formatMessage('DEBUG', message, meta));
    }
  }
};

module.exports = logger;

