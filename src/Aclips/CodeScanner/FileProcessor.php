<?php

namespace Aclips\CodeScanner;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Psr\Log\LoggerInterface;

/**
 * Класс для обработки PHP файлов и извлечения информации о классах, функциях и их документации.
 */
class FileProcessor
{
    /**
     * @var Parser Парсер для обработки PHP кода.
     */
    private Parser $parser;

    /**
     * @var LoggerInterface Логгер для логирования ошибок и информации.
     */
    private LoggerInterface $logger;

    private string $fileContent = '';
    /**
     * Конструктор для инициализации парсера и логгера.
     */
    public function __construct(LoggerInterface $logger)
    {
        // Создаем парсер для последней поддерживаемой версии PHP
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->logger = $logger;
    }

    /**
     * Обрабатывает PHP файл, извлекает информацию о классах, функциях и их документации.
     *
     * @param string $fileContent Содержимое PHP файла.
     * @param string $fileName Имя файла.
     * @return array Массив с данными о классах, функциях и их документации.
     */
    public function processFile(string $fileContent, string $fileName): array
    {
        $this->fileContent = $fileContent;

        $fileData = [];

        try {
            // Парсим PHP код в абстрактное синтаксическое дерево (AST)
            $ast = $this->parser->parse($fileContent);
            $fileData = $this->extractData($ast);
        } catch (\PhpParser\Error $e) {
            // Логируем ошибку парсинга
            $this->logger->error("Parse error in file {$fileName}: " . $e->getMessage());
        }

        return $fileData;
    }

    /**
     * Извлекает данные о классах, функциях и других элементах из AST.
     *
     * @param array $ast Абстрактное синтаксическое дерево (AST).
     * @return array Массив с данными о классах, функциях и документации.
     */
    private function extractData(array $ast): array
    {
        $fileData = [];

        foreach ($ast as $node) {
            // Рекурсивно обрабатываем каждый узел
            $fileData = array_merge($fileData, $this->processNode($node));
        }

        return $fileData;
    }

    /**
     * Рекурсивно обрабатывает узлы AST.
     *
     * @param Node $node Узел AST.
     * @param string $fileName Имя файла.
     * @return array Массив данных.
     */
    private function processNode(Node $node): array
    {
        $data = [];

        if ($node instanceof Namespace_) {
            $data[] = $this->processNamespace($node);
        } elseif ($node instanceof Class_) {
            $data[] = $this->processClass($node);
        } elseif ($node instanceof Function_) {
            $data[] = $this->processFunction($node);
        } elseif ($node instanceof Use_) {
            $data[] = $this->processUse($node);
        } elseif (is_array($node)) {
            foreach ($node as $childNode) {
                $data = array_merge($data, $this->processNode($childNode));
            }
        }

        if (isset($node->stmts)) {
            foreach ($node->stmts as $stmt) {
                $data = array_merge($data, $this->processNode($stmt));
            }
        }

        return $data;
    }

    private function processClassConst(\PhpParser\Node\Stmt\ClassConst $constNode): array
    {
        $constants = [];
        foreach ($constNode->consts as $const) {
            $constants = [
                'type' => 'constant',
                'name' => (string)$const->name,
                'value' => $const->value instanceof Node ? $this->getNodeValue($const->value) : null,
            ];
        }
        return $constants;
    }

    private function processProperty(\PhpParser\Node\Stmt\Property $propertyNode): array
    {
        $properties = [];
        foreach ($propertyNode->props as $prop) {
            $type = null;

            // Проверяем наличие типа для свойства
            if ($propertyNode->type instanceof Node\Name) {
                // Если тип свойства представлен объектом Name (например, для сложных типов)
                $type = (string)$propertyNode->type;
            } elseif ($propertyNode->type instanceof Node\Identifier) {
                // Если тип свойства это простой идентификатор (например, LoggerInterface)
                $type = (string)$propertyNode->type;
            }

            $properties = [
                'name' => (string)$prop->name,
                'visibility' => $this->getVisibility($propertyNode),
                'default' => $prop->default instanceof Node ? $this->getNodeValue($prop->default) : null,
                'type' => $type, // Добавляем тип свойства
            ];
        }
        return $properties;
    }

    private function getVisibility(\PhpParser\Node\Stmt\Property $propertyNode): string
    {
        if ($propertyNode->isPublic()) {
            return 'public';
        } elseif ($propertyNode->isProtected()) {
            return 'protected';
        } elseif ($propertyNode->isPrivate()) {
            return 'private';
        }
        return 'unknown';
    }

    private function getNodeValue(Node $node)
    {
        if ($node instanceof \PhpParser\Node\Scalar\String_) {
            return $node->value;
        } elseif ($node instanceof \PhpParser\Node\Scalar\LNumber) {
            return $node->value;
        } elseif ($node instanceof \PhpParser\Node\Scalar\DNumber) {
            return $node->value;
        }
        return null;
    }

    /**
     * Обрабатывает пространство имен.
     *
     * @param Namespace_ $namespace Узел пространства имен.
     * @return array Массив с данными о пространстве имен.
     */
    private function processNamespace(Namespace_ $namespace): array
    {
        return [
            'type' => 'namespace',
            'name' => (string)$namespace->name,
        ];
    }

    /**
     * Обрабатывает класс.
     *
     * @param Class_ $class Узел класса.
     * @return array Массив с данными о классе.
     */
    private function processClass(Class_ $class): array
    {
        // Инициализируем структуру для хранения данных класса
        $classData = [
            'type' => 'class',
            'name' => $class->name,
            'methods' => [],
            'properties' => [],
            'constants' => [],
        ];

        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod) {
                $classData['methods'][] = $this->processMethod($stmt);
            } elseif ($stmt instanceof \PhpParser\Node\Stmt\Property) {
                $classData['properties'][] = $this->processProperty($stmt);
            } elseif ($stmt instanceof \PhpParser\Node\Stmt\ClassConst) {
                $classData['constants'][] = $this->processClassConst($stmt);
            }
        }
        return $classData;
    }

    /**
     * Обрабатывает метод класса.
     *
     * @param \PhpParser\Node\Stmt\ClassMethod $method Узел метода.
     * @return array Массив с данными о методе.
     */
    private function processMethod(\PhpParser\Node\Stmt\ClassMethod $method): array
    {
        $phpDoc = $method->getDocComment() ? $method->getDocComment()->getText() : null;
        $sourceCode = $this->extractSourceCode($this->fileContent, $method->getStartLine(), $method->getEndLine());

        return [
            'type' => 'method',
            'name' => $method->name,
            'parameters' => $this->processParameters($method->params),
            'phpdoc' => $phpDoc,
            'source_code' => $sourceCode,
        ];
    }

    private function extractSourceCode(string $fileContent, int $startLine, int $endLine): string
    {
        $lines = explode("\n", $fileContent);
        $sourceCodeLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        return implode("\n", $sourceCodeLines);
    }

    /**
     * Обрабатывает параметры функции или метода.
     *
     * @param array $params Параметры функции или метода.
     * @return array Массив с данными о параметрах.
     */
    private function processParameters(array $params): array
    {
        $parameters = [];

        foreach ($params as $param) {
            $type = null;

            // Проверяем тип параметра
            if ($param->type instanceof Node\Identifier) {
                // Простой тип (например, int, string, LoggerInterface)
                $type = (string)$param->type;
            } elseif ($param->type instanceof Node\Name) {
                // Сложный тип (например, Some\Namespace\ClassName)
                $type = (string)$param->type;
            }

            // Добавляем параметр в массив
            $parameters[] = [
                'name' => $param->var->name,
                'type' => $type,
            ];
        }

        return $parameters;
    }

    /**
     * Обрабатывает функцию.
     *
     * @param Function_ $function Узел функции.
     * @param string $fileName Имя файла.
     * @return array Массив с данными о функции.
     */
    private function processFunction(Function_ $function, string $fileName): array
    {
        return [
            'file' => $fileName,
            'type' => 'function',
            'name' => $function->name,
            'parameters' => $this->processParameters($function->params),
        ];
    }

    /**
     * Обрабатывает директивы use.
     *
     * @param Use_ $use Узел директивы use.
     * @return array Массив с данными о директиве use.
     */
    private function processUse(Use_ $use): array
    {
        $useData = [];

        foreach ($use->uses as $useItem) {
            $useData = [
                'type' => 'use',
                'name' => (string)$useItem->name,
            ];
        }

        return $useData;
    }
}
