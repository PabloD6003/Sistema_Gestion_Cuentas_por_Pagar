<?php

declare(strict_types=1);

namespace App\Support;

final class Utf8
{
    public static function clean(string $value): string
    {
        if ($value === '' || preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('iconv')) {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return $value;
    }

    public static function recursive(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::clean($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::recursive($item);
            }
        }

        return $value;
    }
}
