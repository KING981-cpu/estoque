<?php
require_once __DIR__ . '/../app/EnvLoader.php';
require_once __DIR__ . '/../app/InventoryApp.php';

EnvLoader::load(__DIR__ . '/../.env');
$app = new InventoryApp();
$emails = $app->getItemNotificationEmails(13);
echo json_encode($emails, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
