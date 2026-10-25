<?php

declare(strict_types=1);

namespace App;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $file = APP_BASE_PATH . '/templates/' . $template . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException('Vista no encontrada: ' . $template);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutFile = APP_BASE_PATH . '/templates/' . $layout . '.php';

        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Layout no encontrado: ' . $layout);
        }

        ob_start();
        require $layoutFile;

        return (string) ob_get_clean();
    }
}
