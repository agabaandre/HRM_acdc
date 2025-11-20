# Memory Management Guide

This guide explains memory management in the Finance server and how to configure heap size.

## Understanding Memory Metrics

### Heap Memory
The heap is where Node.js stores JavaScript objects, strings, arrays, and other dynamic data. It's the main memory area for your application's runtime data.

### Memory Metrics Explained

- **heapUsed**: Memory actively used by your JavaScript objects
- **heapTotal**: Total heap memory allocated by the V8 engine
- **rss** (Resident Set Size): Total physical memory used by the Node.js process
- **external**: Memory used by C++ objects bound to JavaScript objects

## Setting Max Heap Size

### Option 1: In package.json (Recommended)

Edit `package.json` scripts:

```json
{
  "scripts": {
    "start": "node --max-old-space-size=512 server/index.js",
    "dev": "nodemon --exec \"node --max-old-space-size=512\" server/index.js"
  }
}
```

**Heap Size Options:**
- `256` = 256MB (small apps)
- `512` = 512MB (recommended for most apps)
- `1024` = 1GB (larger applications)
- `2048` = 2GB (very large applications)

### Option 2: Via Environment Variable

Add to `finance/.env`:

```env
NODE_OPTIONS=--max-old-space-size=512
```

Then your scripts can remain simple:
```json
{
  "scripts": {
    "start": "node server/index.js",
    "dev": "nodemon server/index.js"
  }
}
```

### Option 3: Direct Command Line

```bash
node --max-old-space-size=512 server/index.js
```

## Memory Monitoring

The server includes built-in memory monitoring that logs warnings when heap usage exceeds 80%.

### Current Configuration

Located in `server/middleware/memory.js`:

```javascript
memoryMonitor({
  threshold: 0.8, // Warn at 80% heap usage
  logInterval: 60000, // Log every minute
  enableGC: config.nodeEnv === 'production'
})
```

### Adjusting Warning Threshold

Edit `server/middleware/memory.js`:

```javascript
memoryMonitor({
  threshold: 0.9, // Change to 90% if you want fewer warnings
  // ...
})
```

## Memory Warning Interpretation

### What 89% Heap Usage Means

```
Heap Usage: 89.16% = (heapUsed / heapTotal) × 100
```

- **< 80%**: Normal operation
- **80-90%**: High but manageable (warning logged)
- **> 90%**: Very high, consider optimization
- **> 95%**: Critical, immediate action needed

### Is This a Problem?

**For small applications:**
- Not immediately critical
- Node.js will request more heap if needed
- V8 can expand the heap automatically

**For production applications:**
- Monitor for trends
- If consistently above 90%, investigate
- Watch for memory leaks

## Memory Optimization Tips

### 1. Use Streaming for Large Data

```javascript
// Instead of loading all data into memory
const data = await db.query('SELECT * FROM large_table');

// Use streaming
const stream = db.query('SELECT * FROM large_table').stream();
stream.on('data', (row) => {
  // Process one row at a time
});
```

### 2. Clear Caches Periodically

```javascript
// Clear large caches periodically
setInterval(() => {
  if (cache.size > 1000) {
    cache.clear();
  }
}, 3600000); // Every hour
```

### 3. Limit Response Sizes

```javascript
// In your controllers
const limit = Math.min(parseInt(req.query.limit) || 50, 100);
```

### 4. Use Connection Pooling

Already configured in `server/database/index.js`:

```javascript
connectionLimit: 10, // Limit concurrent connections
```

### 5. Monitor Memory Trends

Check logs for memory patterns:

```bash
# Watch memory usage
tail -f logs/server.log | grep "Memory"
```

## Troubleshooting

### Memory Keeps Growing

1. **Check for memory leaks:**
   ```bash
   node --inspect server/index.js
   # Open chrome://inspect in Chrome
   ```

2. **Use heap snapshots:**
   ```javascript
   const v8 = require('v8');
   const snapshot = v8.writeHeapSnapshot();
   console.log('Heap snapshot:', snapshot);
   ```

3. **Monitor specific routes:**
   Add memory logging to specific endpoints

### Out of Memory Errors

1. **Increase heap size:**
   ```json
   "start": "node --max-old-space-size=1024 server/index.js"
   ```

2. **Check for infinite loops**
3. **Review large data processing**
4. **Optimize database queries**

## Production Recommendations

### Recommended Heap Sizes

| Application Size | Heap Size | Use Case |
|-----------------|-----------|----------|
| Small | 256-512 MB | Simple APIs, few users |
| Medium | 512-1024 MB | Standard web apps |
| Large | 1024-2048 MB | Complex apps, many users |
| Very Large | 2048+ MB | Enterprise applications |

### Monitoring in Production

1. **Set up alerts** for memory usage > 90%
2. **Log memory stats** regularly
3. **Monitor trends** over time
4. **Set up auto-restart** if memory exceeds limits

### Example: PM2 Configuration

If using PM2, add to `ecosystem.config.js`:

```javascript
module.exports = {
  apps: [{
    name: 'finance-server',
    script: 'server/index.js',
    node_args: '--max-old-space-size=512',
    max_memory_restart: '600M',
    // ...
  }]
};
```

## Best Practices

1. **Start small**: Begin with 512MB and increase if needed
2. **Monitor regularly**: Check memory usage patterns
3. **Optimize code**: Fix memory leaks before increasing heap
4. **Test changes**: Verify memory improvements
5. **Document settings**: Keep track of heap size decisions

## Related Documentation

- [Server Architecture](./SERVER_ARCHITECTURE.md) - Overall server structure
- [Error Handling](../server/utils/errorHandler.js) - Error management
- [Middleware](../server/middleware/memory.js) - Memory monitoring middleware

---

**Last Updated**: 2024

