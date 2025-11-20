/**
 * Validation middleware
 * Validates request data using simple validation rules
 */

const { AppError } = require('../utils/errorHandler');

/**
 * Validate request body against schema
 * @param {object} schema - Validation schema
 * @returns {Function} Express middleware
 */
const validate = (schema) => {
  return (req, res, next) => {
    const { error, value } = schema.validate(req.body, {
      abortEarly: false,
      stripUnknown: true
    });

    if (error) {
      const errors = error.details.map(detail => ({
        field: detail.path.join('.'),
        message: detail.message
      }));

      return next(new AppError('Validation failed', 400, 'VALIDATION_ERROR', errors));
    }

    // Replace req.body with validated and sanitized value
    req.body = value;
    next();
  };
};

/**
 * Validate query parameters
 * @param {object} schema - Validation schema
 * @returns {Function} Express middleware
 */
const validateQuery = (schema) => {
  return (req, res, next) => {
    const { error, value } = schema.validate(req.query, {
      abortEarly: false,
      stripUnknown: true
    });

    if (error) {
      const errors = error.details.map(detail => ({
        field: detail.path.join('.'),
        message: detail.message
      }));

      return next(new AppError('Query validation failed', 400, 'VALIDATION_ERROR', errors));
    }

    req.query = value;
    next();
  };
};

/**
 * Simple validation helpers (can be replaced with Joi or Yup)
 */
const validators = {
  required: (value, fieldName) => {
    if (value === undefined || value === null || value === '') {
      throw new AppError(`${fieldName} is required`, 400, 'VALIDATION_ERROR');
    }
    return value;
  },

  number: (value, fieldName) => {
    const num = parseInt(value, 10);
    if (isNaN(num)) {
      throw new AppError(`${fieldName} must be a number`, 400, 'VALIDATION_ERROR');
    }
    return num;
  },

  min: (value, min, fieldName) => {
    if (value < min) {
      throw new AppError(`${fieldName} must be at least ${min}`, 400, 'VALIDATION_ERROR');
    }
    return value;
  },

  max: (value, max, fieldName) => {
    if (value > max) {
      throw new AppError(`${fieldName} must be at most ${max}`, 400, 'VALIDATION_ERROR');
    }
    return value;
  }
};

module.exports = {
  validate,
  validateQuery,
  validators
};

