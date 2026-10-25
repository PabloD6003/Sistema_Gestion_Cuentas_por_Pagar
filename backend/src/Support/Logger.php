<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

final class Logger
{
    /**
     * @param array<string, mixed> $context
     */
    public static function error(Throwable $exception, array $context = []): void
    {
        $base = defined('APP_BASE_PATH') ? APP_BASE_PATH : dirname(__DIR__, 2);
        $directory = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        $file = $directory . DIRECTORY_SEPARATOR . 'app.log';

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $entry = sprintf(
            "[%s] %s: %s%s%s\n",
            date('Y-m-d H:i:s'),
            $exception::class,
            $exception->getMessage(),
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "\n" . $exception->getTraceAsString()
        );

        @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }
}
