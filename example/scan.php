<?php

require '../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Aclips\CodeScanner\CodeScannerService;
use Aclips\CodeScanner\FileProcessor;
use Aclips\CodeScanner\FileUtils;
use Aclips\CodeScanner\Config;
use Symfony\Component\Console\Output\ConsoleOutput;

// Получаем конфигурацию

$config = new Config(
    '/home/bitrix/www/local/',
    ['vendor', 'migrations'],
    'localhost',
    'codebase',
    '/home/bitrix/www/'
);

// Set up Monolog logger
$logger = new Logger('file_processor');
$logger->pushHandler(new StreamHandler('./log.log', Logger::ERROR));

$mongoClient = $config->getMongoClient();

$scanner = new CodeScannerService(
    $logger,
    $mongoClient,
    new FileUtils(),
    new FileProcessor($logger),
    $config,
    new ConsoleOutput()
);

$scanner->scan();
