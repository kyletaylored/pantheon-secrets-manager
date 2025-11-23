<?php
/**
 * Secrets Sync Service.
 *
 * @package PantheonSecretsManager\Service
 */

namespace PantheonSecretsManager\Service;

use PantheonSecretsManager\API\PantheonSecretsAPI;
use PantheonSecretsManager\Model\Secret;

/**
 * Class SecretsSyncService
 */
class SecretsSyncService
{

    /**
     * Secrets repository.
     *
     * @var SecretsRepository
     */
    private SecretsRepository $repository;

    /**
     * Pantheon Secrets API.
     *
     * @var PantheonSecretsAPI
     */
    private PantheonSecretsAPI $api;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->repository = new SecretsRepository();
        $this->api = new PantheonSecretsAPI();
    }

    /**
     * Sync secrets from Pantheon to local DB.
     *
     * @return array Result stats.
     */
    public function sync(): array
    {
        $pantheon_secrets = $this->api->get_secrets();
        $local_secrets = $this->repository->get_all_by_env('dev'); // TODO: Env.

        $stats = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
        ];

        $local_map = [];
        foreach ($local_secrets as $secret) {
            $local_map[$secret->get_pantheon_secret_name()] = $secret;
        }

        // Process Pantheon secrets.
        foreach ($pantheon_secrets as $p_secret) {
            $name = $p_secret['name'] ?? '';
            if (!$name) {
                continue;
            }

            if (isset($local_map[$name])) {
                // Update existing?
                // Currently we don't store much that changes on Pantheon side other than existence.
                // Maybe update status if it was 'deleted_locally'?
                $secret = $local_map[$name];
                if ('active' !== $secret->get_status()) {
                    $secret->set_status('active');
                    $this->repository->save($secret);
                    $stats['updated']++;
                }
                unset($local_map[$name]);
            } else {
                // Create new local record.
                $new_secret = new Secret([
                    'pantheon_secret_name' => $name,
                    'label' => $name,
                    'php_constant_name' => $name, // Default to name.
                    'is_constant_enabled' => false, // Default to disabled.
                    'load_context' => 'manual', // Default to manual.
                    'environment' => 'dev', // TODO: Env.
                    'is_plugin_owned' => false, // Discovered.
                    'status' => 'active',
                ]);
                $this->repository->save($new_secret);
                $stats['created']++;
            }
        }

        // Process remaining local secrets (not in Pantheon).
        foreach ($local_map as $secret) {
            // Mark as deleted or delete?
            // Let's mark as 'missing_remotely' or delete if it was plugin owned?
            // For now, let's just log it or maybe delete if we want full sync.
            // Requirement says "securely managing secrets", doesn't specify sync behavior.
            // Let's assume we keep them but maybe mark status?
            // Or just delete them if they are not in Pantheon?
            // If I created it locally but it failed to push to Pantheon, it might be here.
            // But if I synced, it means I trust Pantheon as source of truth.
            // Let's delete them from local DB if they are not in Pantheon.
            $this->repository->delete($secret->get_id());
            $stats['deleted']++;
        }

        return $stats;
    }
}
