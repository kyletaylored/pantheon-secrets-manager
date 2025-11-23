<?php
/**
 * Admin Menu Handler.
 *
 * @package PantheonSecretsManager\Admin
 */

namespace PantheonSecretsManager\Admin;

/**
 * Class AdminMenu
 */
class AdminMenu
{

    /**
     * Initialize the admin menu.
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_menu_pages']);
    }

    /**
     * Register menu pages.
     */
    public function register_menu_pages(): void
    {
        $capability = 'manage_pantheon_secrets';

        add_menu_page(
            __('Pantheon Secrets', 'pantheon-secrets-manager'),
            __('Pantheon Secrets', 'pantheon-secrets-manager'),
            $capability,
            'pantheon-secrets-manager',
            [$this, 'render_secrets_page'],
            'dashicons-lock',
            65
        );

        add_submenu_page(
            'pantheon-secrets-manager',
            __('Secrets', 'pantheon-secrets-manager'),
            __('Secrets', 'pantheon-secrets-manager'),
            $capability,
            'pantheon-secrets-manager',
            [$this, 'render_secrets_page']
        );

        add_submenu_page(
            'pantheon-secrets-manager',
            __('Settings', 'pantheon-secrets-manager'),
            __('Settings', 'pantheon-secrets-manager'),
            $capability,
            'pantheon-secrets-manager-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render the secrets page.
     */
    public function render_secrets_page(): void
    {
        if (!current_user_can('manage_pantheon_secrets')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'pantheon-secrets-manager'));
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        if ('add' === $action || 'edit' === $action) {
            $this->render_secret_form($action);
        } else {
            $this->render_secrets_list();
        }
    }

    /**
     * Render the secrets list.
     */
    private function render_secrets_list(): void
    {
        // Handle Sync Action.
        if (isset($_POST['sync_secrets'])) {
            check_admin_referer('sync_secrets', 'sync_nonce');
            $service = new \PantheonSecretsManager\Service\SecretsSyncService();
            $stats = $service->sync();
            echo '<div class="notice notice-success"><p>' . sprintf(
                esc_html__('Sync completed. Created: %d, Updated: %d, Deleted: %d', 'pantheon-secrets-manager'),
                $stats['created'],
                $stats['updated'],
                $stats['deleted']
            ) . '</p></div>';
        }

        $repository = new \PantheonSecretsManager\Service\SecretsRepository();
        $table = new SecretsListTable($repository);
        $table->prepare_items();

        echo '<div class="wrap"><h1>' . esc_html__('Pantheon Secrets', 'pantheon-secrets-manager');
        echo ' <a href="' . esc_url(admin_url('admin.php?page=pantheon-secrets-manager&action=add')) . '" class="page-title-action">' . esc_html__('Add New', 'pantheon-secrets-manager') . '</a>';

        // Sync Button.
        echo '<form method="post" style="display:inline-block; margin-left: 10px;">';
        wp_nonce_field('sync_secrets', 'sync_nonce');
        submit_button(__('Sync from Pantheon', 'pantheon-secrets-manager'), 'secondary', 'sync_secrets', false);
        echo '</form>';

        echo '</h1>';
        echo '<form method="post">';
        $table->display();
        echo '</form></div>';
    }

    /**
     * Render the secret form.
     *
     * @param string $action The action (add or edit).
     */
    private function render_secret_form(string $action): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $secret = null;
        $repository = new \PantheonSecretsManager\Service\SecretsRepository();

        if ('edit' === $action && $id) {
            $secret = $repository->get($id);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_secret'])) {
            check_admin_referer('save_secret', 'secret_nonce');

            $label = sanitize_text_field($_POST['label']);
            $pantheon_secret_name = sanitize_text_field($_POST['pantheon_secret_name']);
            $secret_value = sanitize_text_field($_POST['secret_value']);
            $php_constant_name = sanitize_text_field($_POST['php_constant_name']);
            $is_constant_enabled = isset($_POST['is_constant_enabled']);
            $load_context = sanitize_text_field($_POST['load_context']);

            // Default constant name to secret name if empty.
            if (empty($php_constant_name)) {
                $php_constant_name = $pantheon_secret_name;
            }

            // Validate names.
            if (!$this->validate_secret_name($pantheon_secret_name)) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid Pantheon Secret Name. Must contain only uppercase letters, numbers, and underscores.', 'pantheon-secrets-manager') . '</p></div>';
            } elseif (!empty($php_constant_name) && !$this->validate_secret_name($php_constant_name)) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid PHP Constant Name. Must contain only uppercase letters, numbers, and underscores.', 'pantheon-secrets-manager') . '</p></div>';
            } else {
                // Save to Pantheon.
                $api = new \PantheonSecretsManager\API\PantheonSecretsAPI();
                $api_success = true;
                if (!empty($secret_value)) {
                    if (!$api->set_secret($pantheon_secret_name, $secret_value)) {
                        $api_success = false;
                        echo '<div class="notice notice-error"><p>' . esc_html__('Failed to save secret to Pantheon.', 'pantheon-secrets-manager') . '</p></div>';
                    }
                }

                if ($api_success) {
                    // Save to DB.
                    $data = [
                        'id' => $id,
                        'pantheon_secret_name' => $pantheon_secret_name,
                        'label' => $label,
                        'php_constant_name' => $php_constant_name,
                        'is_constant_enabled' => $is_constant_enabled,
                        'load_context' => $load_context,
                        'environment' => 'dev', // TODO: Get actual environment.
                        'is_plugin_owned' => true,
                        'status' => 'active',
                    ];

                    $new_secret = new \PantheonSecretsManager\Model\Secret($data);
                    if ($repository->save($new_secret)) {
                        echo '<div class="notice notice-success"><p>' . esc_html__('Secret saved.', 'pantheon-secrets-manager') . '</p></div>';
                        if ('add' === $action) {
                            // Redirect to edit page or list page.
                            // For now just clear POST to avoid resubmission? Or maybe redirect.
                        }
                    } else {
                        echo '<div class="notice notice-error"><p>' . esc_html__('Failed to save secret metadata.', 'pantheon-secrets-manager') . '</p></div>';
                    }
                }
            }

            // Refresh secret object.
            if ($id) {
                $secret = $repository->get($id);
            }
        }

        $title = 'add' === $action ? __('Add New Secret', 'pantheon-secrets-manager') : __('Edit Secret', 'pantheon-secrets-manager');

        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
        echo '<form method="post" action="">';
        wp_nonce_field('save_secret', 'secret_nonce');

        echo '<table class="form-table">';

        // Label.
        echo '<tr><th scope="row"><label for="label">' . esc_html__('Label', 'pantheon-secrets-manager') . '</label></th>';
        echo '<td><input name="label" type="text" id="label" value="' . esc_attr($secret ? $secret->get_label() : '') . '" class="regular-text"></td></tr>';

        // Pantheon Secret Name.
        echo '<tr><th scope="row"><label for="pantheon_secret_name">' . esc_html__('Pantheon Secret Name', 'pantheon-secrets-manager') . '</label></th>';
        echo '<td><input name="pantheon_secret_name" type="text" id="pantheon_secret_name" value="' . esc_attr($secret ? $secret->get_pantheon_secret_name() : '') . '" class="regular-text" ' . ('edit' === $action ? 'readonly' : '') . '></td></tr>';

        // Secret Value.
        echo '<tr><th scope="row"><label for="secret_value">' . esc_html__('Secret Value', 'pantheon-secrets-manager') . '</label></th>';
        echo '<td><input name="secret_value" type="password" id="secret_value" value="" class="regular-text" placeholder="' . esc_attr__('Enter new value to update', 'pantheon-secrets-manager') . '"></td></tr>';

        // PHP Constant Name.
        echo '<tr><th scope="row"><label for="php_constant_name">' . esc_html__('PHP Constant Name', 'pantheon-secrets-manager') . '</label></th>';
        echo '<td><input name="php_constant_name" type="text" id="php_constant_name" value="' . esc_attr($secret ? $secret->get_php_constant_name() : '') . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Leave blank to use the Pantheon Secret Name.', 'pantheon-secrets-manager') . '</p></td></tr>';

        // Is Constant Enabled.
        echo '<tr><th scope="row"><label for="is_constant_enabled">' . esc_html__('Enable Constant', 'pantheon-secrets-manager') . '</label></th>';
        echo '<td><input name="is_constant_enabled" type="checkbox" id="is_constant_enabled" value="1" ' . checked($secret ? $secret->is_constant_enabled() : false, true, false) . '></td></tr>';

        // Load Context.
        $context = $secret ? $secret->get_load_context() : 'manual';
        echo '<tr><th scope="row"><label for="load_context">' . esc_html__('Load Context', 'pantheon-secrets-manager') . '</label></th>';
        echo '<td><select name="load_context" id="load_context">';
        echo '<option value="manual" ' . selected($context, 'manual', false) . '>Manual</option>';
        echo '<option value="plugin" ' . selected($context, 'plugin', false) . '>Plugin</option>';
        echo '<option value="mu_plugin" ' . selected($context, 'mu_plugin', false) . '>MU Plugin</option>';
        echo '</select></td></tr>';

        echo '</table>';

        submit_button('Save Secret', 'primary', 'submit_secret');

        echo '</form></div>';
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_pantheon_secrets')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'pantheon-secrets-manager'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['submit_settings'])) {
                check_admin_referer('save_settings', 'settings_nonce');

                $creator_only = isset($_POST['creator_only']);
                update_option('pantheon_secrets_creator_only', $creator_only);

                // Save Roles.
                if (isset($_POST['allowed_roles']) && is_array($_POST['allowed_roles'])) {
                    $allowed_roles = array_map('sanitize_text_field', $_POST['allowed_roles']);
                    $this->update_role_capabilities($allowed_roles);
                } else {
                    // If no roles checked (empty array), remove cap from all except admin maybe?
                    // Or just handle empty array.
                    $this->update_role_capabilities([]);
                }

                echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'pantheon-secrets-manager') . '</p></div>';
            } elseif (isset($_POST['generate_loader'])) {
                check_admin_referer('generate_loader', 'loader_nonce');

                $resolver = new \PantheonSecretsManager\Service\SecretResolver();
                if ($resolver->generate_mu_plugin_loader()) {
                    echo '<div class="notice notice-success"><p>' . esc_html__('MU-Plugin loader generated.', 'pantheon-secrets-manager') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to generate MU-Plugin loader.', 'pantheon-secrets-manager') . '</p></div>';
                }
            }
        }

        $creator_only = get_option('pantheon_secrets_creator_only', false);

        echo '<div class="wrap"><h1>' . esc_html__('Settings', 'pantheon-secrets-manager') . '</h1>';

        // Settings Form.
        echo '<form method="post" action="">';
        wp_nonce_field('save_settings', 'settings_nonce');

        echo '<table class="form-table">';

        // Secrets Creator Only Mode.
        echo '<tr><th scope="row"><label for="creator_only">' . esc_html__('Secrets Creator Only Mode', 'pantheon-secrets-manager') . '</label></th>';
        echo '<td><input name="creator_only" type="checkbox" id="creator_only" value="1" ' . checked($creator_only, true, false) . '>';
        echo '<p class="description">' . esc_html__('If enabled, the plugin will only manage secrets but not define constants.', 'pantheon-secrets-manager') . '</p></td></tr>';

        // Role Permissions.
        echo '<tr><th scope="row">' . esc_html__('Allowed Roles', 'pantheon-secrets-manager') . '</th>';
        echo '<td>';
        echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html__('Allowed Roles', 'pantheon-secrets-manager') . '</span></legend>';

        global $wp_roles;
        $all_roles = $wp_roles->roles;
        foreach ($all_roles as $role_slug => $role_details) {
            if ('administrator' === $role_slug) {
                continue; // Admin always has access.
            }
            $role_obj = get_role($role_slug);
            $has_cap = $role_obj->has_cap('manage_pantheon_secrets');

            echo '<label><input type="checkbox" name="allowed_roles[]" value="' . esc_attr($role_slug) . '" ' . checked($has_cap, true, false) . '> ' . esc_html($role_details['name']) . '</label><br>';
        }
        echo '<p class="description">' . esc_html__('Administrators always have access. Select other roles that can manage secrets.', 'pantheon-secrets-manager') . '</p>';
        echo '</fieldset>';
        echo '</td></tr>';

        echo '</table>';

        submit_button('Save Settings', 'primary', 'submit_settings');
        echo '</form>';

        echo '<hr>';

        // MU-Plugin Loader Generator.
        echo '<h2>' . esc_html__('MU-Plugin Loader', 'pantheon-secrets-manager') . '</h2>';
        echo '<p>' . esc_html__('Generate a loader file in mu-plugins to load secrets early.', 'pantheon-secrets-manager') . '</p>';

        echo '<form method="post" action="">';
        wp_nonce_field('generate_loader', 'loader_nonce');
        submit_button('Generate Loader', 'secondary', 'generate_loader');
        echo '</form>';

        echo '</div>';
    }

    /**
     * Update role capabilities.
     *
     * @param array $allowed_roles List of role slugs.
     */
    private function update_role_capabilities(array $allowed_roles): void
    {
        global $wp_roles;
        $all_roles = $wp_roles->roles;

        foreach ($all_roles as $role_slug => $role_details) {
            if ('administrator' === $role_slug) {
                continue;
            }

            $role = get_role($role_slug);
            if (in_array($role_slug, $allowed_roles, true)) {
                $role->add_cap('manage_pantheon_secrets');
            } else {
                $role->remove_cap('manage_pantheon_secrets');
            }
        }
    }

    /**
     * Validate secret name.
     *
     * @param string $name Secret name.
     * @return bool True if valid.
     */
    private function validate_secret_name(string $name): bool
    {
        return (bool) preg_match('/^[A-Z0-9_]+$/', $name);
    }
}
