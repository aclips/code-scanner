
# PHP Code Scanner

PHP Code Scanner — это инструмент для сканирования PHP файлов с целью извлечения информации о классах, методах, свойствах, функциях и других элементах кода. Результаты сканирования сохраняются в базе данных MongoDB. Также предусмотрены механизмы логирования и вывода прогресса в консоль.

## Описание

Проект предназначен для автоматического анализа PHP файлов в указанном каталоге, извлечения информации о структурах кода и сохранения этих данных в MongoDB. Процесс сканирования включает:
- Обработку PHP файлов (классов, методов, свойств, функций).
- Сохранение результатов в MongoDB.
- Логирование действий и прогресса в консоль.

## Установка

Для установки и настройки проекта, выполните следующие шаги:

1. Клонируйте репозиторий:

```bash
git clone https://github.com/aclips/code-scanner.git
cd code-scanner
```

2. Установите зависимости с помощью Composer:

```bash
composer install
```

3. Установите MongoDB и настройте его в соответствии с документацией MongoDB.

4. Настройте конфигурацию в классе `Config` для вашего проекта. Убедитесь, что указаны правильные пути к каталогам и параметры подключения к MongoDB.

## Пример использования

Пример использования сканера:

```php
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

// Настройка Monolog логгера
$logger = new Logger('file_processor');
$logger->pushHandler(new StreamHandler('./log.log', Logger::ERROR));

$mongoClient = $config->getMongoClient();

// Создание объекта CodeScannerService
$scanner = new CodeScannerService(
    $logger,
    $mongoClient,
    new FileUtils(),
    new FileProcessor($logger),
    $config,
    new ConsoleOutput()
);

// Запуск сканирования
$scanner->scan();
```

# Описание структуры данных, сохраняемой в базе данных MongoDB

## Структура документа

```json
{
  "_id": {
    "$oid": "673de3aba43c898cacbc231e"
  },
  "file_name": "path/to/your/file/SomeFile.php",
  "classes": [
    {
      "name": "SomeClass",
      "methods": [
        {
          "name": "someMethod",
          "parameters": [
            {
              "name": "param1",
              "type": "string"
            }
          ],
          "phpdoc": "/** Docstring for method */",
          "source_code": "public function someMethod($param1) { ... }"
        }
      ],
      "constants": [
        {
          "name": "SOME_CONSTANT",
          "value": "constant value"
        }
      ],
      "properties": [
        {
          "name": "propertyName",
          "visibility": "private",
          "type": "string",
          "default": "default value"
        }
      ]
    }
  ],
  "functions": [
    {
      "name": "someFunction",
      "parameters": [
        {
          "name": "param1",
          "type": "int"
        }
      ],
      "phpdoc": "/** Docstring for function */",
      "source_code": "function someFunction($param1) { ... }"
    }
  ],
  "last_updated": {
    "$date": "2024-11-20T13:43:58.094Z"
  },
  "namespace": "Namespace\For\File",
  "uses": [
    "Psr\Log\LoggerInterface",
    "MongoDB\Client",
    "Symfony\Component\Console\Output\OutputInterface",
    "Symfony\Component\Console\Helper\ProgressBar"
  ]
}
```

## Описание полей:

- **_id** — уникальный идентификатор документа в MongoDB.
- **file_name** — полный путь к файлу, который был проанализирован.
- **classes** — массив объектов, представляющих классы, определенные в файле.
    - **name** — имя класса.
    - **methods** — массив объектов, представляющих методы класса.
        - **name** — имя метода.
        - **parameters** — массив параметров метода.
            - **name** — имя параметра.
            - **type** — тип параметра.
        - **phpdoc** — PHPDoc описание метода (если есть).
        - **source_code** — исходный код метода.
    - **constants** — массив объектов, представляющих константы класса.
        - **name** — имя константы.
        - **value** — значение константы.
    - **properties** — массив объектов, представляющих свойства класса.
        - **name** — имя свойства.
        - **visibility** — видимость свойства (например, private, public, protected).
        - **type** — тип свойства.
        - **default** — значение по умолчанию (если есть).
- **functions** — массив объектов, представляющих функции, определенные в файле (которые не принадлежат классу).
    - **name** — имя функции.
    - **parameters** — массив параметров функции.
    - **phpdoc** — PHPDoc описание функции (если есть).
    - **source_code** — исходный код функции.
- **last_updated** — дата и время последнего обновления записи.
- **namespace** — пространство имен, используемое в файле.
- **uses** — массив используемых классов или пространств имен.

Эта структура сохраняет всю необходимую информацию о коде, включая классы, их методы, свойства и константы, а также функции и их параметры, что позволяет эффективно управлять и анализировать код на уровне базы данных.



## Конфигурация

Конфигурация осуществляется через класс `Config`, где можно настроить:
- Путь к основному каталогу для сканирования.
- Исключаемые директории.
- Параметры подключения к MongoDB (хост, база данных).
- Путь к корневому каталогу проекта.

Пример конфигурации:

```php
$config = new Config(
    '/home/bitrix/www/local/',
    ['vendor', 'migrations'],
    'localhost',
    'codebase',
    '/home/bitrix/www/'
);
```

## Подключение к MongoDB

MongoDB используется для сохранения данных о сканируемых файлах. Убедитесь, что MongoDB настроен и доступен для вашего проекта.

## Структура проекта

- `src/`: Исходный код проекта.
    - `CodeScannerService.php`: Главный сервис для сканирования PHP файлов.
    - `FileProcessor.php`: Класс для обработки содержимого файлов.
    - `FileUtils.php`: Утилиты для работы с файловой системой.
    - `Config.php`: Класс для конфигурации.
- `composer.json`: Файл зависимостей проекта.

## Контрибьюция

Если вы хотите внести изменения в проект, пожалуйста, создайте пулл-запрос с подробным описанием вашего изменения.

## Лицензия

Проект распространяется под лицензией MIT.