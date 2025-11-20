# Assets Setup Guide

The finance app uses assets from the Laravel application. Here's how it's configured:

## Asset Serving

The Node.js server proxies asset requests to the Laravel public directory:

- `/assets/*` → Served from `/opt/homebrew/var/www/staff/assets/`
- `/apm/assets/*` → Served from `/opt/homebrew/var/www/staff/apm/public/assets/`

## Configuration

Set the `ASSETS_BASE_PATH` environment variable in your `.env` file:

```env
ASSETS_BASE_PATH=/opt/homebrew/var/www/staff
```

Or update it in `server.js` if your path is different.

## Using Assets in React Components

In React components, reference assets using the base URL:

```javascript
const baseUrl = process.env.REACT_APP_BASE_URL || 'http://localhost/staff';
<img src={`${baseUrl}/assets/images/logo.png`} />
```

## Available Assets

The following assets are available from the Laravel app:

- **CSS**: Bootstrap, custom styles, plugins
- **JS**: jQuery, Bootstrap, DataTables, Select2, etc.
- **Images**: Logos, icons, user avatars
- **Fonts**: Custom fonts and icon fonts

## Development vs Production

- **Development**: Assets are served directly from the Laravel public directory via the Node.js proxy
- **Production**: You may want to copy assets to the React build directory or use a CDN

## Notes

- The React app uses Bootstrap 5 and Font Awesome from CDN
- Additional Laravel-specific assets are loaded from the proxied directory
- Make sure the Laravel assets directory is accessible from the Node.js server

