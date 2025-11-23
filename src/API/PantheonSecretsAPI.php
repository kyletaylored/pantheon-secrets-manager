<?php
/**
 * Pantheon Secrets API Service.
 *
 * @package PantheonSecretsManager\API
 */

namespace PantheonSecretsManager\API;

use Pantheon\Secrets\CustomerSecrets;

/**
 * Class PantheonSecretsAPI
 */
class PantheonSecretsAPI
{

    /**
     * The CustomerSecrets client.
     *
     * @var CustomerSecrets
     */
    private CustomerSecrets $client;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->client = CustomerSecrets::create();
    }

    /**
     * Get all secrets.
     *
     * @return array
     */
    public function get_secrets(): array
    {
        try {
            return $this->client->getSecrets();
        } catch (\Exception $e) {
            // Log error.
            error_log('Pantheon Secrets Manager Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single secret.
     *
     * @param string $name The secret name.
     * @return mixed|null The secret value or null if not found.
     */
    public function get_secret(string $name)
    {
        try {
            return $this->client->getSecret($name);
        } catch (\Exception $e) {
            $secret = $this->client->getSecret($name);
            return $secret ? $secret->getValue() : null;
        } catch (\Exception $e) {
            error_log('Pantheon Secrets Manager Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set a secret.
     *
     * @param string $name The secret name.
     * @param string $value The secret value.
     * @param string $type The secret type (default: user).
     * @param string $scope The secret scope (default: web).
     * @return bool True on success, false on failure.
     */
    public function set_secret(string $name, string $value, string $type = 'user', string $scope = 'web'): bool
    {
        try {
            $this->client->setSecret($name, $value, $type, $scope);
            return true;
        } catch (\Exception $e) {
            error_log('Pantheon Secrets Manager Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a secret.
     *
     * @param string $name The secret name.
     * @return bool True on success, false on failure.
     */
    public function delete_secret(string $name): bool
    {
        try {
            $this->client->deleteSecret($name);
            return true;
        } catch (\Exception $e) {
            error_log('Pantheon Secrets Manager Error: ' . $e->getMessage());
            return false;
        }
    }
}
