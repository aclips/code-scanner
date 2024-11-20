<?php

namespace Aclips\CodeScanner;

use Psr\Log\LoggerInterface;
use MongoDB\Client as MongoClient;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class CodeScannerService
{
    private LoggerInterface $logger;
    private MongoClient $mongoClient;
    private FileUtils $fileUtils;
    private FileProcessor $fileProcessor;
    private Config $config;
    private OutputInterface $output;

    public function __construct(
        LoggerInterface $logger,
        MongoClient     $mongoClient,
        FileUtils       $fileUtils,
        FileProcessor   $fileProcessor,
        Config          $config,
        OutputInterface $output
    )
    {
        $this->logger = $logger;
        $this->mongoClient = $mongoClient;
        $this->fileUtils = $fileUtils;
        $this->fileProcessor = $fileProcessor;
        $this->config = $config;
        $this->output = $output;
    }

    /**
     * Запускает сканирование кода
     * @return void
     */
    public function scan(): void
    {
        $directory = $this->config->getDirectory();
        $this->logger->info("Starting code scan: {$directory}");
        $this->output->writeln("Starting code scan...");

        $files = $this->fileUtils->getPhpFiles($directory, $this->config->getExcludedDirectories());
        $this->logger->info("Found " . count($files) . " PHP files.");
        $this->output->writeln("Found " . count($files) . " PHP files.");

        // Initialize the progress bar
        $progressBar = new ProgressBar($this->output, count($files));
        $progressBar->setFormat('verbose');
        $progressBar->start();

        foreach ($files as $file) {
            $this->processFile($file);
            // Advance the progress bar by 1 step
            $progressBar->advance();
        }

        // Finish the progress bar
        $progressBar->finish();

        $this->output->writeln("\nScan completed.");
        $this->logger->info("Code scan completed.");
    }

    /**
     * Обрабатывает файл
     * @param string $filePath
     * @return void
     */
    private function processFile(string $filePath): void
    {
        $fileContent = file_get_contents($filePath);
        $fileData = $this->fileProcessor->processFile($fileContent, $filePath);

        $filePath = $this->config->getRelativeFilePath($filePath);

        if (!empty($fileData)) {
            $this->saveFileData($fileData, $filePath);
        }
    }

    /**
     * Сохраняет данные о файле в MongoDB
     * @param array $fileData
     * @param string $fileName
     * @return void
     */
    private function saveFileData(array $fileData, string $fileName): void
    {
        $collection = $this->mongoClient->selectCollection($this->config->getDbName(), 'files');

        $transformedData = $this->transformFileData($fileData);

        $transformedData['last_updated'] = new \MongoDB\BSON\UTCDateTime();
        $transformedData['file_name'] = $fileName;

        $collection->updateOne(
            ['file_name' => $fileName],
            ['$set' => $transformedData],
            ['upsert' => true]
        );

        $this->logger->info("Saved data for file: {$fileName}");
    }

    /**
     * Трансформирует данные о файле
     * @param array $fileData
     * @return array
     */
    private function transformFileData(array $fileData): array
    {
        return [
            'namespace' => $this->getNamespace($fileData),
            'uses' => $this->processUses($fileData),
            'classes' => $this->processClasses($fileData),
            'functions' => $this->processFunctions($fileData),
        ];
    }

    /**
     * Получает пространство имён из данных файла
     * @param array $fileData
     * @return string|null
     */
    private function getNamespace(array $fileData): ?string
    {
        foreach ($fileData as $item) {
            if ($item['type'] === 'namespace') {
                return $item['name'] ?? null;
            }
        }
        return null;
    }

    /**
     * Обрабатывает использование (use) классов и пространств имён
     * @param array $fileData
     * @return array
     */
    private function processUses(array $fileData): array
    {
        return array_values(array_filter(
            array_map(fn($item) => isset($item['type']) && $item['type'] === 'use' ? $item['name'] : null, $fileData)
        ));
    }

    /**
     * Обрабатывает классы из данных файла
     * @param array $fileData
     * @return array
     */
    private function processClasses(array $fileData): array
    {
        $classes = [];
        foreach ($fileData as $item) {
            if (isset($item['type']) && $item['type'] === 'class') {
                $classes[] = [
                    'name' => $item['name']->name ?? '',
                    'methods' => $this->processMethods($item['methods'] ?? []),
                    'constants' => $this->processConstants($item['constants'] ?? []),
                    'properties' => $this->processProperties($item['properties'] ?? []),
                ];
            }
        }
        return $classes;
    }

    /**
     * Обрабатывает методы класса
     * @param array $methods
     * @return array
     */
    private function processMethods(array $methods): array
    {
        return array_map(fn($method) => [
            'name' => $method['name']->name ?? '',
            'parameters' => $this->processParameters($method['parameters'] ?? []),
            'phpdoc' => $method['phpdoc'],
            'source_code' => $method['source_code'],
        ], $methods);
    }

    /**
     * Обрабатывает константы класса
     * @param array $constants
     * @return array
     */
    private function processConstants(array $constants): array
    {
        return array_map(fn($constant) => [
            'name' => $constant['name'] ?? '',
            'value' => $constant['value'] ?? null
        ], $constants);
    }

    /**
     * Обрабатывает свойства класса
     * @param array $properties
     * @return array
     */
    private function processProperties(array $properties): array
    {
        return array_map(fn($property) => [
            'name' => $property['name'] ?? '',
            'visibility' => $property['visibility'] ?? 'private',
            'type' => $property['type'] ?? null,
            'default' => $property['default'] ?? null
        ], $properties);
    }

    /**
     * Обрабатывает функции из данных файла
     * @param array $fileData
     * @return array
     */
    private function processFunctions(array $fileData): array
    {
        return array_values(array_filter(
            array_map(fn($item) => $item['type'] === 'function' ? [
                'name' => $item['name']->name ?? '',
                'parameters' => $this->processParameters($item['parameters'] ?? [])
            ] : null, $fileData)
        ));
    }

    /**
     * Обрабатывает параметры функции или метода
     * @param array $parameters
     * @return array
     */
    private function processParameters(array $parameters): array
    {
        return array_map(fn($param) => [
            'name' => $param['name'] ?? '',
            'type' => $param['type'] ?? null
        ], $parameters);
    }
}
