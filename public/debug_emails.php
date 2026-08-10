<?php
require_once __DIR__ . '/../app/EnvLoader.php';
require_once __DIR__ . '/../app/InventoryApp.php';

EnvLoader::load(__DIR__ . '/../.env');
$app = new InventoryApp();
$itemId = isset($_GET['item']) ? (int)$_GET['item'] : 13;
$emails = $app->getItemNotificationEmails($itemId);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($emails, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
