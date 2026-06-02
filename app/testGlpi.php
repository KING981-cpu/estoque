<?php

require_once __DIR__ . '/EnvLoader.php';
require_once __DIR__ . '/ApiClient.php';

EnvLoader::load(__DIR__ . '/../.env');

try {
    $client = new ApiClient();
    $locations = $client->fetchLocations();

    echo 'Localidades carregadas: ' . count($locations) . PHP_EOL;
    echo 'Primeiras 10 localidades:' . PHP_EOL;

    foreach (array_slice($locations, 0, 10) as $location) {
        echo sprintf("- [%s] %s\n", $location['id'] ?? '[sem id]', $location['name'] ?? '[sem nome]');
    }
} catch (Throwable $error) {
    echo 'Erro: ' . $error->getMessage() . PHP_EOL;
    exit(1);
}
