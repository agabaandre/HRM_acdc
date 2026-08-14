const express = require('express');
const router = express.Router();
const { requireFinanceAccess, requireFinanceSettings } = require('../middleware/permissions');
const FinanceController = require('../controllers/FinanceController');
const SettingsController = require('../controllers/SettingsController');

// Initialize controllers
const financeController = new FinanceController();
const settingsController = new SettingsController();

// All finance routes require permission 92 (Finance access)
// Using controller methods for better separation of concerns

// Finance data routes
router.get('/data', requireFinanceAccess, (req, res) => financeController.getData(req, res));
router.get('/test-db', requireFinanceAccess, (req, res) => financeController.testDb(req, res));
router.get('/permissions', requireFinanceAccess, (req, res) => financeController.getPermissions(req, res));

// Finance feature routes
router.get('/advances', requireFinanceAccess, (req, res) => financeController.getAdvances(req, res));
router.get('/missions', requireFinanceAccess, (req, res) => financeController.getMissions(req, res));
router.get('/budgets', requireFinanceAccess, (req, res) => financeController.getBudgets(req, res));
router.get('/stats', requireFinanceAccess, (req, res) => financeController.getStats(req, res));

// Finance settings routes - require permission 93
router.get('/settings', requireFinanceSettings, (req, res) => settingsController.getSettings(req, res));

module.exports = router;
