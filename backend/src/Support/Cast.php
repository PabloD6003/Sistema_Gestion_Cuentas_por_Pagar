<?php

declare(strict_types=1);

namespace App\Support;

final class Cast
{
    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $ints
     * @param array<int, string> $floats
     * @param array<int, string> $bools
     * @return array<string, mixed>
     */
    public static function row(array $row, array $ints = [], array $floats = [], array $bools = []): array
    {
        foreach ($ints as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $row[$key] === null ? null : (int) $row[$key];
            }
        }

        foreach ($floats as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $row[$key] === null ? null : (float) $row[$key];
            }
        }

        foreach ($bools as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $row[$key] === null ? null : (bool) (int) $row[$key];
            }
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $ints
     * @param array<int, string> $floats
     * @param array<int, string> $bools
     * @return array<int, array<string, mixed>>
     */
    public static function rows(array $rows, array $ints = [], array $floats = [], array $bools = []): array
    {
        return array_map(
            static fn (array $row): array => self::row($row, $ints, $floats, $bools),
            $rows
        );
    }
}
