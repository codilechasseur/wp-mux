# WP Mux Plugin Development Guide

## Project Overview
This is a WordPress plugin for integrating MUX video streaming platform with WordPress media library. Built from the WordPress Plugin Boilerplate (wppb.me), it follows a structured, object-oriented architecture with clear separation of concerns.

## Architecture Pattern
The plugin uses a **Loader Pattern** architecture centered around `Wp_Mux_Loader` class:
- **`wp-mux.php`**: Bootstrap file, plugin entry point
- **`includes/class-wp-mux.php`**: Core orchestrator, loads dependencies and defines hooks
- **`includes/class-wp-mux-loader.php`**: Hook registry that collects and executes actions/filters
- **`admin/class-wp-mux-admin.php`**: Admin-specific functionality
- **`public/class-wp-mux-public.php`**: Public-facing functionality

### Key Pattern: Centralized Hook Registration
Instead of scattered `add_action()` calls, all hooks are registered through the loader:
```php
$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
$this->loader->add_filter( 'hook_name', $component, 'callback_method' );
```

## Development Environment
- **DDEV**: Local WordPress development environment
- **PHP 8.3**: Primary version (configured in `.vscode/settings.json`)
- **Xdebug**: Available via VS Code tasks (`DDEV: Enable Xdebug` / `DDEV: Disable Xdebug`)
- **Text Domain**: `wp-mux` for translations

## File Structure Conventions
- **`/includes/`**: Core plugin classes (activator, deactivator, i18n, loader)
- **`/admin/`**: Admin interface (CSS, JS, PHP classes, partials)
- **`/public/`**: Public-facing code (CSS, JS, PHP classes, partials)
- **`/languages/`**: Translation files (.pot template)
- **`index.php`**: Security files (`<?php // Silence is golden`) in every directory

## Naming Conventions
- **Classes**: `Wp_Mux_Component` (snake_case with Wp_Mux prefix)
- **Files**: `class-wp-mux-component.php` (kebab-case)
- **Constants**: `WP_MUX_VERSION` (uppercase)
- **Plugin slug**: `wp-mux` (used for text domain, asset handles)

## Adding New Functionality

### 1. Admin Features
Add hooks in `includes/class-wp-mux.php` → `define_admin_hooks()`:
```php
$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
```

### 2. Public Features
Add hooks in `includes/class-wp-mux.php` → `define_public_hooks()`:
```php
$this->loader->add_action( 'wp_footer', $plugin_public, 'render_video_player' );
```

### 3. New Components
Follow the established pattern:
- Create class file in appropriate directory
- Include in `load_dependencies()` method
- Pass plugin name and version to constructor

## Current State
The plugin is in **boilerplate stage** - core architecture is established but MUX-specific functionality is not yet implemented. The description indicates it will provide "media library integration for the MUX video hosting platform."

## Debugging
- Use VS Code launch configuration "Listen for Xdebug"
- Automatic Xdebug enable/disable via pre/post launch tasks
- Path mappings configured for DDEV container (`/var/www/html` → workspace)

## WordPress Integration Points
- **Activation/Deactivation**: `class-wp-mux-activator.php` / `class-wp-mux-deactivator.php`
- **Uninstall**: `uninstall.php`
- **i18n**: `class-wp-mux-i18n.php` handles text domain loading
- **Assets**: Enqueued via admin/public classes with version-based cache busting

When implementing MUX integration, focus on extending the admin class for media library integration and public class for video rendering functionality.
