<?php
// ============================================================
// TIXORA — Lightweight .env Loader
// Membaca variabel lingkungan dari file .env di root project.
// ============================================================

if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $pos));
            $val = trim(substr($trimmed, $pos + 1));

            if ($key === '') {
                continue;
            }

            // Hapus kutip pembungkus jika ada
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
                $_ENV[$key] = $val;
                putenv("{$key}={$val}");
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        $val = $_ENV[$key] ?? getenv($key);
        if ($val === false || $val === null || $val === '') {
            return $default;
        }
        return (string) $val;
    }
}

loadEnvFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
