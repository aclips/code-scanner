<?php

namespace Aclips\CodeScanner;

class FileUtils
{
    public static function getPhpFiles(string $directory, array $excludedDirectories = []): array
    {
        // Если передан файл, а не директория, просто вернем его в массив
        if (is_file($directory)) {
            if (pathinfo($directory, PATHINFO_EXTENSION) === 'php' && !self::isExcluded($directory, $excludedDirectories)) {
                return [$directory];
            } else {
                return [];
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                if (!self::isExcluded($file->getRealPath(), $excludedDirectories)) {
                    $files[] = $file->getRealPath();
                }
            }
        }

        return $files;
    }


    private static function isExcluded(string $filePath, array $excludedDirectories): bool
    {
        foreach ($excludedDirectories as $excludedDir) {
            if (strpos($filePath, $excludedDir) !== false) {
                return true;
            }
        }
        return false;
    }
}
