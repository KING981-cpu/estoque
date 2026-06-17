<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '/var/www/html/app/EnvLoader.php';
require '/var/www/html/app/InventoryApp.php';
EnvLoader::load('/var/www/html/.env');
$app = new InventoryApp();
$ref = new ReflectionMethod(InventoryApp::class, 'sendEmail');
$ref->setAccessible(true);
$result = $ref->invoke($app, 'kaua.ferri@olimpia.sp.gov.br', 'Teste SMTP corporativo', "Corpo do teste enviado com o SMTP corporativo.
");
var_dump($result);
