<?php

require '../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Aclips\CodeScanner\CodeScannerService;
use Aclips\CodeScanner\FileProcessor;
use Aclips\CodeScanner\FileUtils;
use Aclips\CodeScanner\Config;
use Symfony\Component\Console\Output\ConsoleOutput;

$config = new Config(
    '/home/aclips/project/veb/www/local/modules',
    ['vendor', 'migrations'],
    'localhost',
    'codebase',
    '/home/aclips/project/veb/'
);

$logger = new Logger('file_processor');
$logger->pushHandler(new StreamHandler('./log.log', Logger::ERROR));

$mongoClient = $config->getMongoClient();

$codeScannerService = new CodeScannerService(
    $logger,
    $mongoClient,
    new FileUtils(),
    new FileProcessor($logger),
    $config,
    new ConsoleOutput()
);

$callback = function (array $transformedData) {
    echo "\nSuccessfully saved the following data\n";
};

$codeScannerService->setSuccessCallback($callback);

$codeScannerService->scan();
