<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Config;

final class StoragePathResolver
{
    public static function resolve(string $rootPath, string $configuredPath, string $subdirectory): string
    {
        $configuredPath = trim($configuredPath);
        if ($configuredPath === '') {
            $configuredPath = 'var/storage';
        }

        $isAbsolute = str_starts_with($configuredPath, '/')
            || str_starts_with($configuredPath, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;

        $basePath = $isAbsolute
            ? $configuredPath
            : rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . ltrim($configuredPath, '/\\');

        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . trim($subdirectory, '/\\');
    }
}
