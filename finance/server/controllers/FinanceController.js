/**
 * Finance Controller
 * Handles all finance-related HTTP requests
 * Uses service layer for business logic
 */
const financeService = require('../services/financeService');
const { asyncHandler } = require('../utils/errorHandler');

class FinanceController {

  /**
   * Get finance data
   * GET /api/finance/data
   */
  getData = asyncHandler(async (req, res) => {
    const user = req.session.user;
    const data = await financeService.getFinanceData(user);
    
    res.json({
      success: true,
      data: {
        ...data,
        base_url: req.session.base_url || '',
        permissions: req.session.permissions || []
      }
    });
  });

  /**
   * Get user advances
   * GET /api/finance/advances
   */
  getAdvances = asyncHandler(async (req, res) => {
    const userId = req.session.user?.staff_id || req.session.user?.id;
    const { page, limit, status } = req.query;
    
    const result = await financeService.getUserAdvances(userId, {
      page,
      limit,
      status
    });

    res.json({
      success: true,
      ...result
    });
  });

  /**
   * Get user missions
   * GET /api/finance/missions
   */
  getMissions = asyncHandler(async (req, res) => {
    const userId = req.session.user?.staff_id || req.session.user?.id;
    const { page, limit, status } = req.query;
    
    const result = await financeService.getUserMissions(userId, {
      page,
      limit,
      status
    });

    res.json({
      success: true,
      ...result
    });
  });

  /**
   * Get budgets
   * GET /api/finance/budgets
   */
  getBudgets = asyncHandler(async (req, res) => {
    const { page, limit, divisionId, year } = req.query;
    
    const result = await financeService.getBudgets({
      page,
      limit,
      divisionId,
      year
    });

    res.json({
      success: true,
      ...result
    });
  });

  /**
   * Get finance statistics
   * GET /api/finance/stats
   */
  getStats = asyncHandler(async (req, res) => {
    const userId = req.session.user?.staff_id || req.session.user?.id;
    const stats = await financeService.getFinanceStats(userId);

    res.json({
      success: true,
      data: stats
    });
  });

  /**
   * Get user permissions
   * GET /api/finance/permissions
   */
  getPermissions = asyncHandler(async (req, res) => {
    const permissions = req.session.permissions || [];
    
    res.json({
      success: true,
      data: {
        hasFinanceAccess: permissions.includes('92') || permissions.includes(92),
        hasFinanceSettings: permissions.includes('93') || permissions.includes(93),
        allPermissions: permissions
      }
    });
  });

  /**
   * Test database connection
   * GET /api/finance/test-db
   */
  testDb = asyncHandler(async (req, res) => {
    const result = await financeService.testDatabase();
    res.json(result);
  });
}

module.exports = FinanceController;

