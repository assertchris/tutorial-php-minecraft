<?php

require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/source/game.php";
require __DIR__ . "/source/shopware.php";

$builder = new Theory\Builder\Client("127.0.0.1", 25575, "password123");
$shopware = new Shopware("http://127.0.0.1:8080/api/", "admin", "PSIJxLXTpmItsWVeAP6czBvOwNFqUUISa23JJIlu");
$filesystem = Amp\File\filesystem();

$game = new Game($builder, $shopware, $filesystem);
$game->run();
