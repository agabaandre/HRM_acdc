# Layouts Integration Summary

## Overview

The finance React app now uses layout components that match the Laravel application's design. The layouts have been adapted from the Laravel Blade templates to React components.

## Components Created

### 1. Layout Components (`frontend/src/components/Layout/`)

- **Layout.js** - Main layout wrapper that combines all layout pieces
- **Header.js** - Top navigation bar with logo, search, and user menu
- **Nav.js** - Side navigation menu with finance-specific menu items
- **Footer.js** - Page footer with copyright and back-to-top button
- **Breadcrumbs.js** - Breadcrumb navigation component

### 2. Styling

Each component has its own CSS file:
- `Layout.css` - Main layout styles
- `Header.css` - Header and topbar styles
- `Nav.css` - Navigation menu styles
- `Footer.css` - Footer styles
- `Breadcrumbs.css` - Breadcrumb styles

## Asset Integration

### Server Configuration

The Node.js server now proxies asset requests to the Laravel public directory:

```javascript
// In server.js
app.use('/assets', express.static(path.join(ASSETS_BASE_PATH, 'assets')));
app.use('/apm/assets', express.static(path.join(ASSETS_BASE_PATH, 'apm/public/assets')));
```

### Using Assets in React

Assets are referenced using the base URL:

```javascript
const baseUrl = process.env.REACT_APP_BASE_URL || 'http://localhost/staff';
<img src={`${baseUrl}/assets/images/logo.png`} />
```

## Features

1. **Consistent UI**: Matches Laravel app design with same colors, fonts, and styling
2. **Responsive**: Works on mobile and desktop
3. **Asset Integration**: Uses Laravel assets (images, CSS, JS) via proxy
4. **Navigation**: Finance-specific menu items
5. **User Menu**: Dropdown with user info and logout

## Dependencies Added

- `boxicons` - For icon fonts used in Laravel layouts
- Bootstrap 5 - Already included
- Font Awesome - Already included

## Usage

The Layout component wraps page content:

```javascript
import Layout from './components/Layout';

function MyPage({ user, onLogout }) {
  return (
    <Layout user={user} onLogout={onLogout} title="My Page">
      <div>Page content here</div>
    </Layout>
  );
}
```

## Differences from Laravel

1. **No Blade Syntax**: Converted to React JSX
2. **No PHP Functions**: User data passed as props
3. **No Laravel Routes**: Uses React Router
4. **Simplified Navigation**: Finance-specific menu items only

## Next Steps

1. Add more finance-specific menu items to Nav.js
2. Customize header dropdowns for finance features
3. Add breadcrumb logic for nested routes
4. Integrate Laravel JavaScript libraries (DataTables, Select2, etc.)

