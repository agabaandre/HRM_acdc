# Session Implementation

This document describes how session transfer from CodeIgniter is implemented, matching the Laravel APM pattern.

## Token Generation (CodeIgniter)

In the CI app (`application/modules/home/views/home.php`):

```php
$token = urlencode(base64_encode(json_encode($session)));
```

The token is created by:
1. `json_encode($session)` - Convert session array to JSON string
2. `base64_encode(...)` - Base64 encode the JSON string
3. `urlencode(...)` - URL encode for safe transmission in URL

## Token Processing (Laravel APM Pattern)

### Laravel Implementation (`apm/routes/web.php`)

```php
Route::get('/', function (Request $request) {
    $base64Token = $request->query('token');
    
    if ($base64Token) {
        // Decode the base64 token
        $decodedToken = base64_decode($base64Token);
        
        // Parse the JSON data
        $json = json_decode($decodedToken, true);
        
        // Save to session
        session([
            'user' => $json, 
            'base_url' => $json['base_url'] ?? '', 
            'permissions' => $json['permissions'] ?? []
        ]);
    }
    
    return redirect('/home');
});
```

**Key Points:**
- Laravel automatically URL decodes query parameters
- Uses `base64_decode()` directly (no URL decode needed)
- Stores entire JSON object as `user`
- Stores `base_url` and `permissions` separately

## Node.js Implementation

### Server-Side (`server/routes/session.js`)

```javascript
router.post('/transfer', (req, res) => {
    const { sessionData } = req.body;
    
    // Store exactly like Laravel:
    req.session.user = sessionData; // Entire JSON object
    req.session.base_url = sessionData.base_url || '';
    req.session.permissions = sessionData.permissions || [];
    req.session.authenticated = true;
});
```

### Client-Side (`frontend/src/App.js`)

```javascript
// Get token from URL
const base64Token = urlParams.get('token');

// Decode (Express/React needs manual URL decode)
const decodedToken = decodeURIComponent(base64Token);
const jsonString = atob(decodedToken); // Base64 decode
const json = JSON.parse(jsonString); // Parse JSON

// Send to server
axios.post('/api/session/transfer', { sessionData: json });
```

## Session Structure

The session object contains:

```javascript
{
  id: number,
  name: string,
  email: string,
  role: number,
  staff_id: string,
  permissions: array,
  base_url: string,
  // ... other user properties
}
```

## Storage Pattern

**Laravel:**
```php
session([
    'user' => $json,                    // Full object
    'base_url' => $json['base_url'],    // Extracted
    'permissions' => $json['permissions'] // Extracted
]);
```

**Node.js (matches Laravel):**
```javascript
req.session.user = sessionData;              // Full object
req.session.base_url = sessionData.base_url || '';
req.session.permissions = sessionData.permissions || [];
```

## Differences

1. **URL Decoding:**
   - Laravel: Automatic via `$request->query()`
   - Node.js: Manual via `decodeURIComponent()` in React

2. **Base64 Decoding:**
   - Laravel: `base64_decode()`
   - Node.js: `atob()` (browser) or `Buffer.from(..., 'base64')` (server)

3. **Session Storage:**
   - Laravel: Uses Laravel session driver
   - Node.js: Uses `express-session` with cookie storage

## Accessing Session Data

**Laravel:**
```php
$user = session('user');
$baseUrl = session('base_url');
$permissions = session('permissions');
```

**Node.js:**
```javascript
const user = req.session.user;
const baseUrl = req.session.base_url;
const permissions = req.session.permissions;
```

## Error Handling

Both implementations redirect to CI login on:
- Invalid token format
- JSON parse errors
- Missing session data
- Session expiration

