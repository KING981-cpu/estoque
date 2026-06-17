<?php
require '/var/www/html/app/EnvLoader.php';
require '/var/www/html/app/InventoryApp.php';
EnvLoader::load('/var/www/html/.env');
$app = new InventoryApp();
$ref = new ReflectionMethod(InventoryApp::class, 'sendEmail');
$ref->setAccessible(true);
$result = $ref->invoke($app, 'kaua.ferri@olimpia.sp.gov.br', 'Teste SMTP direto', "Corpo do teste enviado agora para o endereço correto.
");
var_dump($result);
