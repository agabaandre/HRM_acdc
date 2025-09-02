# Shared Page Header Component Usage Guide

## Overview
The shared page header component provides a consistent, beautiful header design across all pages in the application. It maintains the same styling you love while allowing customization for different pages.

## Basic Usage

```php
<?php
// Prepare header data
$header_data = [
    'title' => 'Your Page Title',
    'subtitle' => 'Optional subtitle or description',
    'icon' => 'fa-dashboard', // FontAwesome icon class
    'actions' => [
        [
            'text' => 'Button Text',
            'icon' => 'fa-icon-name',
            'class' => 'btn-primary',
            'onclick' => 'onclick="yourFunction()"'
        ]
    ]
];

// Load the shared header
$this->load->view('templates/partials/shared_page_header', $header_data);
?>
```

## Parameters

### Required
- `title`: Main page title (string)

### Optional
- `subtitle`: Page description (string)
- `icon`: FontAwesome icon class (string, defaults to 'fa-dashboard')
- `actions`: Array of action buttons (array)
- `bg_color`: Custom background color (string, defaults to theme color)

### Action Button Properties
- `text`: Button text (required)
- `icon`: FontAwesome icon class (optional)
- `class`: CSS classes (optional, defaults to 'btn-light')
- `onclick`: JavaScript function or data attributes (optional)
- `data-bs-toggle`: Bootstrap modal toggle (optional)
- `data-bs-target`: Bootstrap modal target (optional)
- `id`: Button ID (optional)

## Examples

### Simple Header
```php
$header_data = [
    'title' => 'User Management',
    'subtitle' => 'Manage system users and permissions',
    'icon' => 'fa-users'
];
```

### Header with Single Action
```php
$header_data = [
    'title' => 'Reports',
    'subtitle' => 'Generate and view system reports',
    'icon' => 'fa-chart-bar',
    'actions' => [
        [
            'text' => 'Export',
            'icon' => 'fa-download',
            'class' => 'btn-success',
            'onclick' => 'onclick="exportData()"'
        ]
    ]
];
```

### Header with Multiple Actions
```php
$header_data = [
    'title' => 'Project Management',
    'subtitle' => 'Track and manage project progress',
    'icon' => 'fa-project-diagram',
    'actions' => [
        [
            'text' => 'Add Project',
            'icon' => 'fa-plus',
            'class' => 'btn-primary',
            'data-bs-toggle' => 'modal',
            'data-bs-target' => '#addProjectModal'
        ],
        [
            'text' => 'Export',
            'icon' => 'fa-file-export',
            'class' => 'btn-outline-success',
            'onclick' => 'onclick="exportProjects()"'
        ],
        [
            'text' => 'Refresh',
            'icon' => 'fa-sync',
            'class' => 'btn-light',
            'onclick' => 'onclick="refreshData()"'
        ]
    ]
];
```

### Custom Background Color
```php
$header_data = [
    'title' => 'Special Page',
    'subtitle' => 'This page has a custom background',
    'icon' => 'fa-star',
    'bg_color' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    'actions' => [
        [
            'text' => 'Action',
            'class' => 'btn-warning'
        ]
    ]
];
```
