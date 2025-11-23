<?php
/**
 * Secret Model.
 *
 * @package PantheonSecretsManager\Model
 */

namespace PantheonSecretsManager\Model;

/**
 * Class Secret
 */
class Secret
{

    /**
     * The secret ID.
     *
     * @var int|null
     */
    private ?int $id;

    /**
     * The Pantheon secret name.
     *
     * @var string
     */
    private string $pantheon_secret_name;

    /**
     * The human-readable label.
     *
     * @var string
     */
    private string $label;

    /**
     * The PHP constant name.
     *
     * @var string|null
     */
    private ?string $php_constant_name;

    /**
     * Whether the constant is enabled.
     *
     * @var bool
     */
    private bool $is_constant_enabled;

    /**
     * The load context (plugin, mu_plugin, manual).
     *
     * @var string
     */
    private string $load_context;

    /**
     * The environment context.
     *
     * @var string
     */
    private string $environment;

    /**
     * Whether the secret is owned by the plugin.
     *
     * @var bool
     */
    private bool $is_plugin_owned;

    /**
     * Whether the secret is locally deleted.
     *
     * @var bool
     */
    private bool $is_deleted_locally;

    /**
     * The status of the secret.
     *
     * @var string
     */
    private string $status;

    /**
     * Last synced timestamp.
     *
     * @var string|null
     */
    private ?string $last_synced_at;

    /**
     * Created timestamp.
     *
     * @var string
     */
    private string $created_at;

    /**
     * Updated timestamp.
     *
     * @var string
     */
    private string $updated_at;

    /**
     * Constructor.
     *
     * @param array $data Data to populate the model.
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    /**
     * Hydrate the model with data.
     *
     * @param array $data Data to hydrate.
     */
    public function hydrate(array $data): void
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : null;
        $this->pantheon_secret_name = $data['pantheon_secret_name'] ?? '';
        $this->label = $data['label'] ?? '';
        $this->php_constant_name = $data['php_constant_name'] ?? null;
        $this->is_constant_enabled = !empty($data['is_constant_enabled']);
        $this->load_context = $data['load_context'] ?? 'manual';
        $this->environment = $data['environment'] ?? '';
        $this->is_plugin_owned = !empty($data['is_plugin_owned']);
        $this->is_deleted_locally = !empty($data['is_deleted_locally']);
        $this->status = $data['status'] ?? 'active';
        $this->last_synced_at = $data['last_synced_at'] ?? null;
        $this->created_at = $data['created_at'] ?? '';
        $this->updated_at = $data['updated_at'] ?? '';
    }

    /**
     * Get the ID.
     *
     * @return int|null
     */
    public function get_id(): ?int
    {
        return $this->id;
    }

    /**
     * Get the Pantheon secret name.
     *
     * @return string
     */
    public function get_pantheon_secret_name(): string
    {
        return $this->pantheon_secret_name;
    }

    /**
     * Set the Pantheon secret name.
     *
     * @param string $pantheon_secret_name The Pantheon secret name.
     */
    public function set_pantheon_secret_name(string $pantheon_secret_name): void
    {
        $this->pantheon_secret_name = $pantheon_secret_name;
    }

    /**
     * Get the label.
     *
     * @return string
     */
    public function get_label(): string
    {
        return $this->label;
    }

    /**
     * Set the label.
     *
     * @param string $label The label.
     */
    public function set_label(string $label): void
    {
        $this->label = $label;
    }

    /**
     * Get the PHP constant name.
     *
     * @return string|null
     */
    public function get_php_constant_name(): ?string
    {
        return $this->php_constant_name;
    }

    /**
     * Set the PHP constant name.
     *
     * @param string|null $php_constant_name The PHP constant name.
     */
    public function set_php_constant_name(?string $php_constant_name): void
    {
        $this->php_constant_name = $php_constant_name;
    }

    /**
     * Check if the constant is enabled.
     *
     * @return bool
     */
    public function is_constant_enabled(): bool
    {
        return $this->is_constant_enabled;
    }

    /**
     * Set whether the constant is enabled.
     *
     * @param bool $is_constant_enabled Whether the constant is enabled.
     */
    public function set_is_constant_enabled(bool $is_constant_enabled): void
    {
        $this->is_constant_enabled = $is_constant_enabled;
    }

    /**
     * Get the load context.
     *
     * @return string
     */
    public function get_load_context(): string
    {
        return $this->load_context;
    }

    /**
     * Set the load context.
     *
     * @param string $load_context The load context.
     */
    public function set_load_context(string $load_context): void
    {
        $this->load_context = $load_context;
    }

    /**
     * Get the environment.
     *
     * @return string
     */
    public function get_environment(): string
    {
        return $this->environment;
    }

    /**
     * Set the environment.
     *
     * @param string $environment The environment.
     */
    public function set_environment(string $environment): void
    {
        $this->environment = $environment;
    }

    /**
     * Check if the secret is plugin owned.
     *
     * @return bool
     */
    public function is_plugin_owned(): bool
    {
        return $this->is_plugin_owned;
    }

    /**
     * Set whether the secret is plugin owned.
     *
     * @param bool $is_plugin_owned Whether the secret is plugin owned.
     */
    public function set_is_plugin_owned(bool $is_plugin_owned): void
    {
        $this->is_plugin_owned = $is_plugin_owned;
    }

    /**
     * Check if the secret is locally deleted.
     *
     * @return bool
     */
    public function is_deleted_locally(): bool
    {
        return $this->is_deleted_locally;
    }

    /**
     * Set whether the secret is locally deleted.
     *
     * @param bool $is_deleted_locally Whether the secret is locally deleted.
     */
    public function set_is_deleted_locally(bool $is_deleted_locally): void
    {
        $this->is_deleted_locally = $is_deleted_locally;
    }

    /**
     * Get the status.
     *
     * @return string
     */
    public function get_status(): string
    {
        return $this->status;
    }

    /**
     * Set the status.
     *
     * @param string $status The status.
     */
    public function set_status(string $status): void
    {
        $this->status = $status;
    }

    /**
     * Get the last synced timestamp.
     *
     * @return string|null
     */
    public function get_last_synced_at(): ?string
    {
        return $this->last_synced_at;
    }

    /**
     * Set the last synced timestamp.
     *
     * @param string|null $last_synced_at The last synced timestamp.
     */
    public function set_last_synced_at(?string $last_synced_at): void
    {
        $this->last_synced_at = $last_synced_at;
    }

    /**
     * Get the created timestamp.
     *
     * @return string
     */
    public function get_created_at(): string
    {
        return $this->created_at;
    }

    /**
     * Set the created timestamp.
     *
     * @param string $created_at The created timestamp.
     */
    public function set_created_at(string $created_at): void
    {
        $this->created_at = $created_at;
    }

    /**
     * Get the updated timestamp.
     *
     * @return string
     */
    public function get_updated_at(): string
    {
        return $this->updated_at;
    }

    /**
     * Set the updated timestamp.
     *
     * @param string $updated_at The updated timestamp.
     */
    public function set_updated_at(string $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    /**
     * Convert the object to an array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return [
            'id' => $this->id,
            'pantheon_secret_name' => $this->pantheon_secret_name,
            'label' => $this->label,
            'php_constant_name' => $this->php_constant_name,
            'is_constant_enabled' => $this->is_constant_enabled,
            'load_context' => $this->load_context,
            'environment' => $this->environment,
            'is_plugin_owned' => $this->is_plugin_owned,
            'is_deleted_locally' => $this->is_deleted_locally,
            'status' => $this->status,
            'last_synced_at' => $this->last_synced_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
