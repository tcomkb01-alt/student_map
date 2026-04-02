<?php
/**
 * config.php – Central configuration
 * Loads all settings from .env (one directory above /includes/)
 *
 * Student Home Visit Map System
 */

// ─── Timezone & Encoding ────────────────────────────────────────────────────
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

// ─── .env Loader ────────────────────────────────────────────────────────────
/**
 * loadEnv()
 * Parses a .env file and populates $_ENV, $_SERVER, and putenv().
 * Supports:
 *   KEY=value
 *   KEY="quoted value"
 *   KEY='single quoted'
 *   # comments and blank lines are ignored
 *   export KEY=value  (optional 'export' prefix)
 */
function loadEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        // Fall through silently — constants will use hard-coded defaults below
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Strip optional 'export ' prefix
        if (strncasecmp($line, 'export ', 7) === 0) {
            $line = ltrim(substr($line, 7));
        }

        // Must contain '='
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Strip inline comments (value not quoted)
        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            if (($pos = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $pos));
            }
        }

        // Strip surrounding quotes and unescape
        $len = strlen($value);
        if ($len >= 2) {
            if (
                ($value[0] === '"' && $value[$len - 1] === '"') ||
                ($value[0] === "'" && $value[$len - 1] === "'")
            ) {
                $value = substr($value, 1, $len - 2);
                // Unescape \n \r \t only inside double-quotes
                if ($value[0] ?? '' !== "'") {
                    $value = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $value);
                }
            }
        }

        // Set in all three superglobals so getenv() works too
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// ─── Load .env file ─────────────────────────────────────────────────────────
// The .env lives at the project root (one level up from /includes/)
loadEnv(dirname(__DIR__) . '/.env');

// ─── Helper: read env with fallback ─────────────────────────────────────────
function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ─── Database ────────────────────────────────────────────────────────────────
define('DB_HOST', env('DB_HOST'));
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// ─── Application ─────────────────────────────────────────────────────────────
define('APP_NAME', env('APP_NAME', 'ระบบแผนที่เยี่ยมบ้านนักเรียน'));
define('APP_VERSION', env('APP_VERSION', '1.0.0'));

// ─── Paths ───────────────────────────────────────────────────────────────────
define('BASE_URL', rtrim(env('BASE_URL'), '/'));
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads/students/');
define('UPLOAD_URL', BASE_URL . '/uploads/students/');

// ─── Upload limits ────────────────────────────────────────────────────────────
$_maxMb = (int) env('MAX_FILE_SIZE_MB', '5');
define('MAX_FILE_SIZE', max(1, $_maxMb) * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ─── Security ────────────────────────────────────────────────────────────────
define('MAP_PASSWORD', env('MAP_PASSWORD'));

// ─── Session ─────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('student_map_sess');
    session_start();
}
