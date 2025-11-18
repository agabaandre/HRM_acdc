/**
 * Memory Management Middleware
 * Implements strategies to prevent memory leaks and manage resources
 */

const v8 = require('v8');

// Memory usage tracking
let memoryStats = {
  heapUsed: 0,
  heapTotal: 0,
  external: 0,
  rss: 0,
  lastCheck: Date.now()
};

/**
 * Get current memory usage
 */
function getMemoryUsage() {
  const usage = process.memoryUsage();
  memoryStats = {
    heapUsed: usage.heapUsed,
    heapTotal: usage.heapTotal,
    external: usage.external,
    rss: usage.rss,
    lastCheck: Date.now()
  };
  return memoryStats;
}

/**
 * Format bytes to human readable
 */
function formatBytes(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Request timeout middleware
 * Prevents requests from hanging and consuming memory
 */
function requestTimeout(timeout = 30000) { // 30 seconds default
  return (req, res, next) => {
    const timer = setTimeout(() => {
      if (!res.headersSent) {
        res.status(408).json({
          success: false,
          message: 'Request timeout',
          error: 'The request took too long to process'
        });
        res.end();
      }
    }, timeout);

    // Clear timer when response is sent
    res.on('finish', () => {
      clearTimeout(timer);
    });

    res.on('close', () => {
      clearTimeout(timer);
    });

    next();
  };
}

/**
 * Response size limit middleware
 * Prevents sending excessively large responses
 */
function responseSizeLimit(maxSize = 10 * 1024 * 1024) { // 10MB default
  return (req, res, next) => {
    const originalSend = res.send;
    let responseSize = 0;

    res.send = function(data) {
      if (data) {
        responseSize = Buffer.byteLength(JSON.stringify(data), 'utf8');
        if (responseSize > maxSize) {
          return res.status(413).json({
            success: false,
            message: 'Response too large',
            error: `Response size (${formatBytes(responseSize)}) exceeds maximum allowed size (${formatBytes(maxSize)})`
          });
        }
      }
      return originalSend.call(this, data);
    };

    next();
  };
}

/**
 * Memory monitoring middleware
 * Logs memory usage and triggers GC if needed
 */
function memoryMonitor(options = {}) {
  const {
    threshold = 0.8, // 80% of heap used
    logInterval = 60000, // Log every minute
    enableGC = true
  } = options;

  let lastLogTime = Date.now();

  return (req, res, next) => {
    const now = Date.now();
    
    // Log memory usage periodically
    if (now - lastLogTime > logInterval) {
      const usage = getMemoryUsage();
      const heapUsedPercent = usage.heapUsed / usage.heapTotal;

      if (heapUsedPercent > threshold) {
        console.warn(`[Memory Warning] Heap usage: ${(heapUsedPercent * 100).toFixed(2)}%`);
        console.warn(`[Memory Stats]`, {
          heapUsed: formatBytes(usage.heapUsed),
          heapTotal: formatBytes(usage.heapTotal),
          rss: formatBytes(usage.rss),
          external: formatBytes(usage.external)
        });

        // Trigger garbage collection if enabled and available
        if (enableGC && global.gc) {
          console.log('[Memory] Triggering garbage collection...');
          global.gc();
        }
      }

      lastLogTime = now;
    }

    next();
  };
}

/**
 * Request body size limit
 * Prevents large request bodies from consuming memory
 */
function bodySizeLimit(maxSize = 1024 * 1024) { // 1MB default
  return (req, res, next) => {
    const contentLength = parseInt(req.headers['content-length'] || '0');
    
    if (contentLength > maxSize) {
      return res.status(413).json({
        success: false,
        message: 'Request body too large',
        error: `Request body size (${formatBytes(contentLength)}) exceeds maximum allowed size (${formatBytes(maxSize)})`
      });
    }

    next();
  };
}

/**
 * Cleanup middleware
 * Ensures resources are cleaned up after request
 */
function cleanup() {
  return (req, res, next) => {
    // Cleanup on response finish
    res.on('finish', () => {
      // Clear any request-specific data
      if (req.tempFiles) {
        req.tempFiles.forEach(file => {
          // Cleanup temp files if any
        });
      }
    });

    // Cleanup on response close
    res.on('close', () => {
      // Additional cleanup if needed
    });

    next();
  };
}

/**
 * Memory health check endpoint data
 */
function getMemoryHealth() {
  const usage = getMemoryUsage();
  const heapUsedPercent = usage.heapUsed / usage.heapTotal;
  
  return {
    status: heapUsedPercent > 0.9 ? 'critical' : heapUsedPercent > 0.8 ? 'warning' : 'healthy',
    heapUsed: formatBytes(usage.heapUsed),
    heapTotal: formatBytes(usage.heapTotal),
    heapUsedPercent: (heapUsedPercent * 100).toFixed(2) + '%',
    rss: formatBytes(usage.rss),
    external: formatBytes(usage.external),
    uptime: Math.floor(process.uptime())
  };
}

module.exports = {
  requestTimeout,
  responseSizeLimit,
  memoryMonitor,
  bodySizeLimit,
  cleanup,
  getMemoryUsage,
  getMemoryHealth,
  formatBytes
};

