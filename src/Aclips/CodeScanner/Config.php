<?php

namespace Aclips\CodeScanner;

use MongoDB\Client;

/**
 * Класс конфигурации для настройки параметров сканера кода.
 */
class Config
{
    /**
     * @var string Путь к директории для сканирования
     */
    private string $directory;

    /**
     * @var array Список исключённых директорий
     */
    private array $excludedDirectories;

    /**
     * @var string Хост базы данных MongoDB
     */
    private string $dbHost;

    /**
     * @var string Имя базы данных MongoDB
     */
    private string $dbName;

    /**
     * @var string Базовый путь, который нужно обрезать из путей файлов
     */
    private string $baseDirectory;

    /**
     * Конструктор класса Config.
     *
     * @param string $directory Путь к директории для сканирования.
     * @param array $excludedDirectories Массив директорий для исключения.
     * @param string $dbHost Хост базы данных.
     * @param string $dbName Имя базы данных.
     * @param string $baseDirectory Путь, который будет обрезаться из полного пути файла.
     */
    public function __construct(
        string $directory,
        array  $excludedDirectories,
        string $dbHost,
        string $dbName,
        string $baseDirectory
    )
    {
        $this->directory = $directory;
        $this->excludedDirectories = $excludedDirectories;
        $this->dbHost = $dbHost;
        $this->dbName = $dbName;
        $this->baseDirectory = $baseDirectory;
    }

    /**
     * Получить подключение к базе данных MongoDB.
     *
     * @return Client Клиент MongoDB.
     */
    public function getMongoClient(): Client
    {
        return new Client("mongodb://{$this->dbHost}");
    }

    /**
     * Получить путь к директории для сканирования.
     *
     * @return string Путь к директории.
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Получить список исключённых директорий.
     *
     * @return array Массив исключённых директорий.
     */
    public function getExcludedDirectories(): array
    {
        return $this->excludedDirectories;
    }

    /**
     * Получить имя базы данных.
     *
     * @return string Имя базы данных.
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * Получить путь к файлу относительно базового каталога.
     *
     * @param string $filePath Путь к файлу.
     *
     * @return string Путь без базового каталога.
     */
    public function getRelativeFilePath(string $filePath): string
    {
        // Проверяем, начинается ли путь с базового каталога
        if (strpos($filePath, $this->baseDirectory) === 0) {
            // Убираем базовый каталог из пути
            return substr($filePath, strlen($this->baseDirectory));
        }

        // Если не начинается с базового каталога, возвращаем путь как есть
        return $filePath;
    }
}
