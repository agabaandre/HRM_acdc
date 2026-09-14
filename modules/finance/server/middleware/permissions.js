/**
 * Permission middleware for Finance app
 * Matches Laravel APM pattern: in_array(permission_id, permissions)
 * 
 * Permission 92: Access to Finance module
 * Permission 93: Access to Finance settings
 */

/**
 * Check if user has a specific permission
 * @param {number|string} permissionId - The permission ID to check
 * @param {array} permissions - Array of permission IDs
 * @returns {boolean}
 */
const hasPermission = (permissionId, permissions = []) => {
  if (!permissions || !Array.isArray(permissions)) {
    return false;
  }
  // Convert permissionId to string for comparison (permissions are stored as strings in CI)
  const permId = String(permissionId);
  return permissions.includes(permId) || permissions.includes(Number(permissionId));
};

/**
 * Middleware to require authentication
 */
const requireAuth = (req, res, next) => {
  if (!req.session || !req.session.authenticated) {
    return res.status(401).json({
      success: false,
      message: 'Unauthorized - Please log in'
    });
  }
  next();
};

/**
 * Middleware to require permission 92 (Finance access)
 */
const requireFinanceAccess = (req, res, next) => {
  if (!req.session || !req.session.authenticated) {
    return res.status(401).json({
      success: false,
      message: 'Unauthorized - Please log in'
    });
  }

  const permissions = req.session.permissions || [];
  if (!hasPermission(92, permissions)) {
    return res.status(403).json({
      success: false,
      message: 'Forbidden - You do not have permission to access Finance module',
      requiredPermission: 92
    });
  }

  next();
};

/**
 * Middleware to require permission 93 (Finance settings)
 */
const requireFinanceSettings = (req, res, next) => {
  if (!req.session || !req.session.authenticated) {
    return res.status(401).json({
      success: false,
      message: 'Unauthorized - Please log in'
    });
  }

  const permissions = req.session.permissions || [];
  if (!hasPermission(93, permissions)) {
    return res.status(403).json({
      success: false,
      message: 'Forbidden - You do not have permission to access Finance settings',
      requiredPermission: 93
    });
  }

  next();
};

/**
 * Helper function to check if user has permission (for use in routes)
 */
const checkPermission = (permissionId, permissions = []) => {
  return hasPermission(permissionId, permissions);
};

module.exports = {
  requireAuth,
  requireFinanceAccess,
  requireFinanceSettings,
  hasPermission,
  checkPermission
};

