/**
 * Error Handler Utility
 * Provides consistent error handling and memory-efficient error responses
 */
const logger = require('./logger');

/**
 * Standard error response format
 */
class AppError extends Error {
  constructor(message, statusCode = 500, code = null, details = null) {
    super(message);
    this.statusCode = statusCode;
    this.code = code;
    this.details = details;
    this.isOperational = true;
    Error.captureStackTrace(this, this.constructor);
  }
}

/**
 * Handle async errors in route handlers
 * Wraps async route handlers to catch errors automatically
 */
const asyncHandler = (fn) => {
  return (req, res, next) => {
    Promise.resolve(fn(req, res, next)).catch(next);
  };
};

/**
 * Global error handler middleware
 * Should be used as the last middleware
 */
const errorHandler = (err, req, res, next) => {
  // Default error
  let error = {
    statusCode: err.statusCode || 500,
    message: err.message || 'Internal server error',
    code: err.code || 'INTERNAL_ERROR'
  };

  // Development error details
  if (process.env.NODE_ENV === 'development') {
    error.stack = err.stack;
    error.details = err;
  }

  // Log error
  logger.error('Request error', {
    message: error.message,
    statusCode: error.statusCode,
    code: error.code,
    path: req.path,
    method: req.method,
    stack: process.env.NODE_ENV === 'development' ? err.stack : undefined
  });

  // Send error response
  const errorResponse = {
    success: false,
    error: {
      message: error.message,
      code: error.code || 'INTERNAL_ERROR'
    }
  };

  // Add details if available
  if (error.details) {
    errorResponse.error.details = error.details;
  }

  // Add stack trace in development
  if (process.env.NODE_ENV === 'development') {
    errorResponse.error.stack = error.stack;
    if (!error.details) {
      errorResponse.error.details = err;
    }
  }

  res.status(error.statusCode).json(errorResponse);
};

/**
 * 404 Not Found handler
 */
const notFoundHandler = (req, res, next) => {
  const error = new AppError(`Route ${req.originalUrl} not found`, 404, 'NOT_FOUND');
  next(error);
};

/**
 * Validation error handler
 */
const validationError = (message, field = null) => {
  const error = new AppError(message, 400, 'VALIDATION_ERROR');
  if (field) {
    error.field = field;
  }
  return error;
};

module.exports = {
  AppError,
  asyncHandler,
  errorHandler,
  notFoundHandler,
  validationError
};

