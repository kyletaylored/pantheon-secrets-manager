# Pantheon Secrets Manager – Requirements Specification

## 1. Project Overview

**Name:** Pantheon Secrets Manager
**Author:** Kyle Taylor
**Email:** [kyletaylored@gmail.com](mailto:kyletaylored@gmail.com)
**GitHub:** `kyletaylored`
**Repository:** [https://github.com/kyletaylored/pantheon-secrets-manager](https://github.com/kyletaylored/pantheon-secrets-manager)

### 1.1 Purpose

The Pantheon Secrets Manager WordPress plugin provides a secure, Pantheon-native way to manage secrets that are typically stored as:

* Hardcoded values in `wp-config.php`
* Plaintext options in the database
* Hardcoded values in plugin code or MU-plugins

Instead of this, the plugin integrates with Pantheon’s Customer Secrets API (via their PHP SDK) to:

* Create, update, delete, and list secrets stored in Pantheon’s encrypted secret service.
* Map Pantheon secrets to PHP constants (environment-style variables) used by WordPress and its plugins.
* Control **when** and **how** these constants are defined, including support for early loading for MU-plugins.
* Offer a companion WP-CLI interface to manage secrets via terminal.

### 1.2 Scope

The plugin will:

* Provide an admin UI in the WordPress dashboard for managing secrets.
* Use Pantheon’s Customer Secrets PHP SDK to interact with the API.
* Maintain a local metadata table for mapping secrets to PHP constants and tracking plugin-managed vs externally-created secrets.
* Define PHP constants on demand or at specific points in the WordPress lifecycle.
* Handle Pantheon environment context (dev/test/live/etc.) and local “fake client” behavior.
* Provide WP-CLI commands mirroring the main management capabilities.
* Be installable both as a standard WordPress plugin and as a Composer-installed plugin.
* Include CI workflows (GitHub Actions) for testing, coding standards, building, and releasing (WordPress.org + Packagist).

Out of scope:

* Rotation scheduling / automatic rotation policy (can be future enhancement).
* UI for arbitrary non-Pantheon secret stores.

---

## 2. Platform & Compatibility Requirements

### 2.1 WordPress

* Minimum WordPress version: latest LTS minus one major (TBD, but treat as a constant to configure in code).
* Plugin must:

  * Follow WordPress Coding Standards (PHPCS, `WordPress` / `WordPress-Extra` rulesets).
  * Use standard plugin header format compatible with the WordPress.org plugin directory.
  * Use appropriate actions/filters to integrate into the admin and load constants.

### 2.2 PHP

* Minimum PHP version: **8.2**
* CI test matrix:

  * PHP 8.2
  * PHP 8.3 (and newer stable versions as they are released)
* Plugin must be fully type-safe where feasible (typed properties, return types, strict types when compatible with WP standards).

### 2.3 Composer Compatibility

* Provide a `composer.json` so the plugin can be installed via Composer.

  * `"type": "wordpress-plugin"`
  * PSR-4 autoload for plugin namespace.
  * Require Pantheon Customer Secrets PHP SDK:

    * `pantheon-systems/customer-secrets-php-sdk` (version constraint TBD, pinned to a compatible minor).
* No breaking assumptions about directory paths; use paths relative to plugin base dir.

---

## 3. External Dependencies

### 3.1 Pantheon Customer Secrets API & SDK

* SDK: [https://github.com/pantheon-systems/customer-secrets-php-sdk](https://github.com/pantheon-systems/customer-secrets-php-sdk)
* Plugin must:

  * Use the SDK’s main client for interacting with the live environment.
  * Use the SDK’s “fake client” when:

    * Running locally (non-Pantheon environment), or
    * Pantheon environment variables are not set.
  * Provide robust error handling around API calls:

    * Network errors
    * Authentication/authorization failures
    * Missing secrets in a given environment

### 3.2 Environment Context

* Pantheon environments (dev, test, live, multidev) have their **own** secrets context.
* Plugin must:

  * Detect current Pantheon environment using standard Pantheon-specific env vars (e.g. `PANTHEON_ENVIRONMENT`, etc.).
  * Store environment context in the local mapping table so the plugin knows which environment a mapping applies to.
  * Handle cases where:

    * A secret mapping exists locally but the secret does **not** exist in the secrets API for that environment (e.g., DB cloned from another environment).

      * Provide “Sync” / “Recreate” capabilities in the UI and CLI.

---

## 4. High-Level Feature Summary

1. **Admin UI for Secret Management**

   * List secrets retrieved from Pantheon (via SDK).
   * Show plugin-managed secrets vs externally created secrets.
   * Create, update, and delete plugin-managed secrets.
   * Associate secrets with PHP constants and configure load behavior.
   * Sync/recreate secrets across environments when local metadata exists but remote secret does not.
   * Allow “Secrets Creator Only” mode, where plugin only manages secrets but does not define constants.

2. **Secret → PHP Constant Mapping**

   * Ability to map a secret to a PHP constant name (e.g. `WP_GOOGLE_LOGIN_CLIENT_ID`).
   * Control defining the constant:

     * Enabled/disabled toggle.
     * Load timing: normal plugin load vs early MU-plugin loader.
   * Avoid loading all secrets on every page; define only those configured for automatic loading.

3. **External Secret Awareness**

   * When listing secrets from the API, highlight which are:

     * Managed by this plugin (ownership flag).
     * External (created outside the plugin).
   * For external secrets:

     * Allow mapping to a PHP constant.
     * If “deleted” in our UI, **only** remove mapping/metadata; do **not** delete the remote secret.

4. **Environment Handling**

   * Mark mappings with environment context.
   * When a secret doesn’t exist in a given environment:

     * Show status: “missing in this environment.”
     * Provide:

       * “Recreate secret in this environment” option.
       * “Sync” option to pull current metadata again from API (where supported).

5. **WP-CLI Integration**

   * CLI equivalents for:

     * Listing secrets.
     * Creating/updating/deleting plugin-managed secrets.
     * Mapping/unmapping secrets to constants.
     * Syncing/recreating missing secrets.
   * Commands must be namespaced, e.g. `wp pantheon-secrets ...`.

6. **CI/CD & Distribution**

   * GitHub Actions workflows for:

     * Testing (PHPUnit, PHPCS).
     * Building and packaging the plugin into a distributable zip.
     * Deploying to:

       * WordPress.org plugin repo.
       * Packagist (or ensure Packagist auto-refresh on Git tags).
   * Matrix testing across supported PHP versions and latest WordPress.

---

## 5. Architecture & Implementation Requirements

### 5.1 Plugin Structure

Suggested structure (implementation can vary if consistent and standards-compliant):

```text
pantheon-secrets-manager/
  pantheon-secrets-manager.php        # Main plugin bootstrap file
  src/
    Admin/
    API/
    CLI/
    Model/
    Service/
    Integration/
  tests/
  vendor/                             # Composer dependencies (excluded in dev, included in release zip)
  assets/
    css/
    js/
  languages/
  readme.txt                          # WordPress.org
  README.md                           # GitHub
  composer.json
  phpcs.xml
  phpunit.xml
  .github/workflows/
```

* Use a root namespace like `PantheonSecretsManager\`.
* Main plugin file:

  * Registers autoloader (Composer).
  * Bootstraps services (admin UI, CLI, constant loader).
  * Defines plugin version constant.
  * Registers activation/deactivation hooks.

### 5.2 Data Model & Storage

**Goal:** Never store the secrets’ plaintext values in the database.

Introduce a custom DB table (with `{$wpdb->prefix}`):

**Table name:** `{$wpdb->prefix}pantheon_secrets`

**Fields (at minimum):**

* `id` (INT, PK, auto_increment)
* `pantheon_secret_name` (VARCHAR)

  * The key/name used in the Pantheon secrets API.
* `label` (VARCHAR)

  * Human-readable label for the secret in the UI.
* `php_constant_name` (VARCHAR, nullable)

  * Corresponding PHP constant to define (e.g. `WP_GOOGLE_LOGIN_CLIENT_ID`).
* `is_constant_enabled` (TINYINT(1))

  * Whether to define this constant automatically.
* `load_context` (ENUM / VARCHAR)

  * E.g. `plugin`, `mu_plugin`, `manual`:

    * `plugin`: defined during plugin bootstrap.
    * `mu_plugin`: defined via mu-plugin loader.
    * `manual`: available via an internal API/helper only.
* `environment` (VARCHAR)

  * Current environment context (e.g. `dev`, `test`, `live`, `local`).
* `is_plugin_owned` (TINYINT(1))

  * `1` when created through this plugin; `0` when discovered from the API.
* `is_deleted_locally` (TINYINT(1))

  * For representing that the local mapping is disabled or logically deleted without touching the API.
* `status` (VARCHAR)

  * e.g. `active`, `missing_remote`, `disabled`, `error`.
* `last_synced_at` (DATETIME, nullable)
* `created_at` (DATETIME)
* `updated_at` (DATETIME)

Optional field(s):

* `value_hash` (VARCHAR, nullable)

  * If needed to detect changes without storing plaintext. This should be optional and never reversible.

**Constraints:**

* Ensure unique constraint on (`pantheon_secret_name`, `environment`) where appropriate.
* Avoid storing the actual secret value.

### 5.3 Secret Lifecycle Logic

#### 5.3.1 Create Secret (Plugin-Owned)

* User enters:

  * Secret label.
  * Pantheon secret name (can default to value derived from PHP constant name).
  * PHP constant name (optional).
  * Whether constant is enabled.
  * Load context (plugin / mu_plugin / manual).
* Plugin performs:

  * Call to Pantheon Secrets SDK to create/set the secret.
  * Store metadata in `pantheon_secrets` table with `is_plugin_owned = 1`.
* UI feedback:

  * Success message or detailed error.

#### 5.3.2 Read/List Secrets

* Use Pantheon SDK `getSecrets` (or equivalent) to list secrets from API.
* Merge with local metadata table:

  * For each API secret:

    * If mapping exists locally: mark as plugin-managed or external, show mapping details.
    * If not: show as external with ability to “Associate to PHP Constant.”
* List view includes:

  * Secret name
  * Label
  * Status (active, external, missing in environment, etc.)
  * PHP constant name (if mapped)
  * Load context
  * Ownership (plugin/external)

#### 5.3.3 Update Secret

* For plugin-owned secrets:

  * Allow updating:

    * Label
    * Secret value
    * PHP constant name
    * Flags (enabled, load context)
  * Update both:

    * Pantheon secrets API (for new value).
    * Local metadata table.

* For external secrets:

  * Allow updating mapping fields only:

    * PHP constant name
    * Flags (enabled, load context)
  * Do **not** update secret value in API unless explicitly allowed.

#### 5.3.4 Delete Secret

* For plugin-owned secrets:

  * Option 1 (default behavior): Delete secret in Pantheon via API **and** remove local metadata.
  * Option 2 (configurable?): Soft delete locally (mark disabled) but keep remote secret.
* For external secrets:

  * Deletion in UI **only** removes local mapping / disables constant.
  * Remote secret remains untouched.

#### 5.3.5 Missing Secrets / Sync

* When a secret mapping exists locally but `getSecret` (or list) doesn’t find it:

  * Mark status as `missing_remote`.
  * Provide actions:

    * **Recreate Secret**: Recreate secret in current environment (requires new value from user).
    * **Remove Mapping**: Delete local metadata, keep remote state as-is (if any).
* Provide a bulk “Sync with Pantheon” action:

  * Re-fetch secrets list from API.
  * Update statuses and potentially re-link known mappings (by secret name).

---

## 6. Constant Definition & Load Behavior

### 6.1 General Approach

* Constants must not be defined globally for every secret unless explicitly enabled.
* Only secrets with `is_constant_enabled = 1` are auto-defined.
* Provide an internal service class, e.g. `SecretResolver`:

  * `get_secret_value( $php_constant_name )`: ensures secret value is fetched and constant defined if needed.
  * This enables on-demand loading for plugins that integrate directly.

### 6.2 Plugin Load vs MU-Plugin Load

Problem: Some plugins or MU-plugins need constants defined **before** normal plugins load.

Implementation requirements:

1. **Plugin Load Mode (`load_context = plugin`):**

   * Hook into a reasonably early action, e.g. `plugins_loaded` or earlier if needed.
   * For all enabled mappings with `load_context = plugin`:

     * Fetch secret from Pantheon if constant not already defined.
     * Call `define( $php_constant_name, $value )`.

2. **MU-Plugin Load Mode (`load_context = mu_plugin`):**

   * Provide a generated MU-plugin loader file, e.g. `wp-content/mu-plugins/pantheon-secrets-loader.php`.
   * This loader:

     * Loads minimal bootstrap or a dedicated loader from the main plugin.
     * Calls a function/service in the main plugin to define all constants marked `mu_plugin`.
     * Handles environment where main plugin may not be active (should degrade gracefully).

3. **Manual Mode (`load_context = manual`):**

   * Do not auto-define the constant.
   * Provide public helper:

     * `pantheon_secrets_get( string $php_constant_name )` or similar.
   * Developers can explicitly request secrets when needed.

### 6.3 “Secrets Creator Only” Mode

* In plugin settings, provide a global toggle:

  * When enabled:

    * Plugin continues to allow full secret CRUD and mapping in UI.
    * No constants are auto-defined, and MU-plugin loader does nothing.
    * WP-CLI operations still allowed for secret management.

---

## 7. Admin UI Requirements

### 7.1 Menu & Permissions

* Add a top-level admin menu or a submenu under “Settings” (to be decided).
* Default capability: `manage_options`, but define a custom capability like `manage_pantheon_secrets` for future extensibility.
* Only users with that capability can view/manage secrets.

### 7.2 Screens

1. **Secrets List Screen**

   * Columns:

     * Label
     * Pantheon secret name
     * PHP constant name
     * Ownership (Plugin / External)
     * Status (Active / Missing remote / Disabled / Error)
     * Environment
     * Load context (Plugin / MU / Manual)
   * Row actions:

     * Edit
     * Sync
     * Recreate (if missing)
     * Delete mapping (and optionally delete remote for plugin-owned)
   * Bulk actions:

     * Enable / disable constants
     * Sync
     * Remove mappings

2. **Secret Edit / Add Screen**

   * Fields:

     * Label
     * Pantheon secret name (with validation)
     * Secret value (masked input; never re-display the existing value)
     * PHP constant name
     * Enable constant (checkbox)
     * Load context (dropdown: plugin / mu_plugin / manual)
     * Ownership indicator (read-only)
     * Environment (read-only)
   * Behavior:

     * For external secrets:

       * Pantheon secret name is read-only.
       * Secret value field optional or disabled (depending on allowed updates).
     * For plugin-owned secrets:

       * Full edit allowed.
   * Security:

     * Nonce validation.
     * Capability check.

3. **Settings Screen**

   * Global options:

     * “Secrets Creator Only” mode (toggle).
     * Default load context for new mappings (plugin / mu_plugin / manual).
     * Logging level (minimal, errors only, verbose metadata; never secrets).
   * MU-plugin loader:

     * Button to create/update MU-plugin loader file.
     * Show status (exists / writable / last updated).

---

## 8. WP-CLI Commands

Namespace: `pantheon-secrets`

Examples:

1. `wp pantheon-secrets list [--environment=<env>] [--status=<status>]`

   * Outputs a table of secrets + mappings.

2. `wp pantheon-secrets create --name=<pantheon_name> --label=<label> --value=<value> [--constant=<PHP_CONST>] [--load-context=<plugin|mu_plugin|manual>] [--enable-constant]`

   * Creates a new plugin-owned secret and mapping.

3. `wp pantheon-secrets update --name=<pantheon_name> [--label=<label>] [--value=<value>] [--constant=<PHP_CONST>] [--load-context=<...>] [--enable-constant=<0|1>]`

   * Updates value and/or mapping.

4. `wp pantheon-secrets delete --name=<pantheon_name> [--keep-remote]`

   * Delete mapping and optionally remote secret (default behavior for plugin-owned can be defined in spec; `--keep-remote` overrides).

5. `wp pantheon-secrets sync [--environment=<env>]`

   * Refresh secrets list from Pantheon and update statuses.

6. `wp pantheon-secrets recreate --name=<pantheon_name> --value=<value> [--environment=<env>]`

   * Recreate a missing secret in the current environment.

7. `wp pantheon-secrets define-constants [--context=<plugin|mu_plugin|all>]`

   * Manually trigger constant definition.

Each command must:

* Validate environment and connectivity.
* Respect “Secrets Creator Only” mode.
* Return sensible exit codes and clear error messages.

---

## 9. Security Requirements

* **Never** store plaintext secrets in the database.
* **Never** log secret values.
* Use nonces and capability checks for all write operations in the admin UI.
* Escape and sanitize all user input and output.
* Stick to prepared statements / `$wpdb->prepare()` for DB access.
* Ensure MU-plugin loader does not expose details if main plugin is inactive.
* Consider basic rate limiting or backoff for repeated failing API calls.

---

## 10. Performance Requirements

* Avoid fetching all secret values on every request.
* Only load:

  * Secrets marked as auto-defined for the current context.
  * Secrets explicitly requested via API helpers.
* Implement lightweight caching where appropriate:

  * In-memory per-request caching for secret values.
  * Avoid aggressive persistent caching that could cause stale secrets unless under explicit control.

---

## 11. Internationalization & Accessibility

* All user-facing text must be:

  * Wrapped in translation functions (`__()`, `_e()`, `esc_html__()`, etc.).
  * Collected into a `.pot` file (place in `languages/` directory).
* Admin UI should:

  * Use proper ARIA attributes where relevant.
  * Use standard WordPress UI components for better accessibility.

---

## 12. Documentation Requirements

### 12.1 WordPress.org `readme.txt`

Include standard sections:

* Plugin Name: Pantheon Secrets Manager
* Contributors: `kyletaylored` (and others as applicable)
* Tags: `pantheon`, `secrets`, `security`, `environment`, `configuration`
* Requires at least: [WP version]
* Tested up to: [WP version]
* Requires PHP: 8.2
* Stable tag: trunk (or version)
* License: GPLv2 or later
* License URI: [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

Sections:

* Description
* Installation
* Frequently Asked Questions
* Screenshots
* Changelog
* Upgrade Notice

### 12.2 GitHub `README.md`

Include:

* Project overview & purpose.
* Requirements.
* Installation:

  * Via WordPress.org.
  * Via Composer.
* Configuration & usage:

  * Admin UI walk-through.
  * MU-plugin loader explanation.
  * WP-CLI examples.
* Development:

  * How to run tests.
  * Coding standards.
  * Contribution guidelines.

---

## 13. CI/CD Requirements (GitHub Actions)

Create `.github/workflows`:

1. **Tests & Standards Workflow**

   * Trigger: `push`, `pull_request`.
   * Matrix:

     * `php: [8.2, 8.3]`
   * Steps:

     * Checkout.
     * Install dependencies (`composer install`).
     * Run `phpunit`.
     * Run `phpcs` with WP ruleset.
     * Optionally spin up a minimal WordPress test environment to ensure plugin activates cleanly.

2. **Build & Release Workflow**

   * Trigger: `push` on tags matching `v*`.
   * Steps:

     * Run tests.
     * Build distributable zip:

       * Exclude dev files (`tests/`, `.github/`, etc.).
     * Publish:

       * To WordPress.org plugin repo (using SVN and secure credentials).
       * Packagist is typically updated via webhook or via API trigger; document the approach.

3. **Optional: Nightly / Scheduled Compatibility Check**

   * Trigger: `schedule` (e.g. daily/weekly).
   * Run plugin tests against latest WordPress and PHP versions.

---

## 14. Future Enhancements (Non-blocking)

* Secret rotation helper UI.
* Integration hints/snippets for popular plugins (e.g. “Use this secret as `XYZ` for FooPlugin”).
* More granular environment mapping (e.g. map to multiple environments).
* REST API endpoints for external tooling integration.
