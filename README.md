# UIPress Analytics Bridge

Enhanced Google Analytics authentication and data retrieval for UIPress Pro. This plugin provides a more reliable connection with better error handling and diagnostics.

## Description

UIPress Analytics Bridge serves as middleware between UIPress Pro and Google Analytics, providing an enhanced authentication and data retrieval system. This plugin resolves critical issues with UIPress Pro's native Google Analytics integration, ensuring a more reliable connection with improved error handling and diagnostics.

### Key Features

- **Reliable OAuth Authentication**: Replaces UIPress Pro's native Google Analytics authentication with a more robust implementation
- **Complete Compatibility**: Maintains 100% compatibility with UIPress Pro's expected data structures and UI
- **Enhanced Error Handling**: Provides detailed error messages and diagnostics for troubleshooting
- **Intelligent Caching**: Implements smart caching for better performance
- **Dual Analytics Support**: Works with both Universal Analytics and Google Analytics 4 (GA4) properties
- **Proper WordPress Integration**: Follows WordPress best practices to avoid critical errors

## Installation

1. Upload the `uipress-analytics-bridge` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Settings → UIPress Analytics' to configure the plugin

## Requirements

- WordPress 5.0 or higher
- UIPress Pro 3.0 or higher
- PHP 7.2 or higher

## Frequently Asked Questions

### Does this plugin replace UIPress Pro?

No, this plugin enhances UIPress Pro's Google Analytics integration. You still need UIPress Pro installed and activated.

### Will this plugin interfere with other Google Analytics plugins?

No, this plugin specifically targets UIPress Pro's Google Analytics integration and won't interfere with other Google Analytics plugins.

### Do I need to create my own Google API project?

No, this plugin handles the Google API authentication for you without requiring you to create your own Google API project.

## Changelog

### 1.0.0
* Initial release

## Credits

This plugin was developed to enhance the UIPress Pro experience by providing a more reliable Google Analytics integration.

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
```