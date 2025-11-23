# Pantheon Secrets Manager

The Pantheon Secrets Manager WordPress plugin provides an interface to manage secrets that are typically stored as hardcoded values or plaintext options.

## Features

*   **Admin UI**: Manage secrets directly from the WordPress dashboard.
*   **Pantheon Integration**: Uses Pantheon’s Customer Secrets API.
*   **Secure Storage**: Never stores secrets in the database.
*   **PHP Constants**: Map secrets to PHP constants securely.
*   **WP-CLI Support**: Manage secrets via the command line.
*   **Environment Aware**: Handles different Pantheon environments (dev, test, live).

## Requirements

*   WordPress 6.0+
*   PHP 8.2+
*   Composer

## Installation

### Via Composer

```bash
composer require kyletaylored/pantheon-secrets-manager
```

### Manual Installation

1.  Download the latest release zip.
2.  Upload to your WordPress plugins directory.
3.  Activate the plugin.

## Usage

Navigate to the "Pantheon Secrets" menu in the WordPress admin dashboard to manage your secrets.

## Development

### Setup

1.  Clone the repository.
2.  Run `composer install`.

### Testing

Run PHPUnit tests:

```bash
composer test
```

Run PHPCS:

```bash
composer lint
```
