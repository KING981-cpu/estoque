<?php

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/LocalidadeModel.php';

class GlpiService
{
    private ApiClient $apiClient;
    private LocalidadeModel $localidadeModel;

    public function __construct(ApiClient $apiClient, LocalidadeModel $localidadeModel)
    {
        $this->apiClient = $apiClient;
        $this->localidadeModel = $localidadeModel;
    }

    public function syncLocations(): array
    {
        $locations = $this->apiClient->fetchLocations();

        foreach ($locations as $location) {
            $name = $location['completename'] ?? $location['name'] ?? null;
            if ($name === null || trim($name) === '') {
                continue;
            }

            $this->localidadeModel->ensureExists($name);
        }

        return $this->localidadeModel->all();
    }
}
