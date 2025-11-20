const createApp = require('./app');
const config = require('./config');
const db = require('./database');
const logger = require('./utils/logger');

// Create Express app
const app = createApp();

// Server reference for graceful shutdown
let server;

// Initialize database connection
async function startServer() {
  // Test database connection (existing module)
  const dbConnected = await db.testConnection();
  if (!dbConnected) {
    console.warn('Warning: Database connection failed. Some features may not work.');
  }

  // Test Sequelize connection
  const sequelizeDb = require('./models');
  const sequelizeConnected = await sequelizeDb.testConnection();
  if (!sequelizeConnected) {
    console.warn('Warning: Sequelize connection failed. ORM features may not work.');
  }

  // Start server and store reference for graceful shutdown
  server = app.listen(config.port, () => {
    logger.info('Finance server started', {
      port: config.port,
      environment: config.nodeEnv,
      database: `${config.database.database}@${config.database.host}:${config.database.port}`
    });
    
    // Log initial memory usage
    const { getMemoryUsage, formatBytes } = require('./middleware/memory');
    const memUsage = getMemoryUsage();
    logger.info('Initial memory usage', {
      heapUsed: formatBytes(memUsage.heapUsed),
      heapTotal: formatBytes(memUsage.heapTotal)
    });
  });
  
  // Set server timeout to prevent hanging connections
  server.timeout = 30000; // 30 seconds
  server.keepAliveTimeout = 65000; // 65 seconds (slightly longer than load balancer)
  server.headersTimeout = 66000; // 66 seconds (slightly longer than keepAliveTimeout)
}

// Start the server
startServer().catch(error => {
  logger.error('Failed to start server', { error: error.message, stack: error.stack });
  process.exit(1);
});

// Memory management: Set max listeners to prevent memory leaks
process.setMaxListeners(20);

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
  logger.error('Uncaught Exception', { error: error.message, stack: error.stack });
  // Close database connections
  db.close().then(() => {
    process.exit(1);
  }).catch(() => {
    process.exit(1);
  });
});

// Handle unhandled promise rejections
process.on('unhandledRejection', (reason, promise) => {
  logger.error('Unhandled Rejection', { 
    reason: reason?.message || reason, 
    stack: reason?.stack 
  });
  // Close database connections
  db.close().then(() => {
    process.exit(1);
  }).catch(() => {
    process.exit(1);
  });
});

// Graceful shutdown
const gracefulShutdown = async (signal) => {
  logger.info('Graceful shutdown initiated', { signal });
  
  if (server) {
    server.close(() => {
      logger.info('HTTP server closed');
    });
  }
  
  try {
    await db.close();
    logger.info('Database connections closed');
    process.exit(0);
  } catch (error) {
    logger.error('Error during shutdown', { error: error.message });
    process.exit(1);
  }
};

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));

module.exports = app;

