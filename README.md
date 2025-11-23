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

### Development

### Pre-commit Hooks

This project uses `brainmaestro/composer-git-hooks` to automatically run checks before committing.
- **Linting:** `phpcs` checks for coding standard violations.
- **Testing:** `phpunit` runs the test suite.

If you need to bypass these checks (not recommended), you can use the `--no-verify` flag:
```bash
git commit -m "Your message" --no-verify
```

### EditorConfig

This project includes an `.editorconfig` file to ensure consistent formatting across different editors:
- **PHP files:** Use tabs for indentation (WordPress standard)
- **JSON/YAML files:** Use 2 spaces
- **Markdown files:** Use 2 spaces

Most modern editors (VSCode, PHPStorm, Sublime Text, etc.) support EditorConfig automatically. If your editor doesn't, install the EditorConfig plugin.

**IMPORTANT:** Disable "format on save" for PHP files in your editor to prevent conflicts with WordPress Coding Standards:

**VSCode:** Add to your user or workspace `settings.json`:
```json
{
  "[php]": {
    "editor.formatOnSave": false
  }
}
```

**PHPStorm:** Go to Settings → Editor → Code Style → PHP → Set from... → Predefined Style → WordPress

Use `composer fix` to format PHP files according to WordPress standards.

### Manual Commands

You can also run checks manually:
- `composer lint`: Run PHPCS.
- `composer fix`: Run PHPCBF to fix formatting issues.
- `composer test`: Run PHPUnit.
```

### Testing

Run PHPUnit tests:

```bash
composer test
```

Run PHPCS:

```bash
composer lint
```
