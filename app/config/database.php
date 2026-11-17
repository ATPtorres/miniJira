<?php
declare(strict_types=1);

/**
 * Small SQL compatibility shim for legacy query strings.
 * It normalizes common MySQL expressions still used by the application while
 * the persistence layer is moved to PostgreSQL. It deliberately does not
 * emulate MySQL-only DDL or silently rewrite complex joins/upserts.
 */
function minijira_pg_sql(string $sql): string {
    $sql = preg_replace('/\\bNOW\\s*\\(\\s*\\)/i', 'CURRENT_TIMESTAMP', $sql) ?? $sql;
    $sql = preg_replace('/\\bCURDATE\\s*\\(\\s*\\)/i', 'CURRENT_DATE', $sql) ?? $sql;
    $sql = preg_replace('/\\bDATABASE\\s*\\(\\s*\\)/i', 'current_database()', $sql) ?? $sql;
    $sql = preg_replace('/DATE_ADD\\s*\\(\\s*CURRENT_TIMESTAMP\\s*,\\s*INTERVAL\\s+\\?\\s+MINUTE\\s*\\)/i', "(CURRENT_TIMESTAMP + (? * INTERVAL '1 minute'))", $sql) ?? $sql;
    $sql = preg_replace('/GROUP_CONCAT\\(\\s*([^,]+)\\s+ORDER BY\\s+([^)]+?)\\s+SEPARATOR\\s+\'([^\']*)\'\\s*\\)/i', "STRING_AGG($1, '$3' ORDER BY $2)", $sql) ?? $sql;

    // Protect the one quoted PostgreSQL identifier used by the academic module.
    $sql = str_replace('"groups"', '__MINIJIRA_GROUPS_IDENTIFIER__', $sql);
    // The legacy SQL uses double-quoted string constants, not quoted identifiers.
    $sql = preg_replace_callback('/"((?:[^"\\\\]|\\\\.)*)"/', static function(array $m): string {
        return "'" . str_replace("'", "''", stripcslashes($m[1])) . "'";
    }, $sql) ?? $sql;
    $sql = str_replace('__MINIJIRA_GROUPS_IDENTIFIER__', '"groups"', $sql);
    // Translate MySQL backtick-quoted identifiers (notably the academic `groups` table).
    $sql = preg_replace('/`([a-zA-Z_][a-zA-Z0-9_]*)`/', '"$1"', $sql) ?? $sql;

    if (preg_match('/^\\s*INSERT\\s+IGNORE\\s+INTO\\b/i', $sql)) {
        $sql = preg_replace('/^\\s*INSERT\\s+IGNORE\\s+INTO/i', 'INSERT INTO', $sql) ?? $sql;
        $sql = rtrim($sql, " ;\\t\\n\\r") . ' ON CONFLICT DO NOTHING';
    }
    return $sql;
}

final class MiniJiraPostgresPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(minijira_pg_sql($query), $options);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $query = minijira_pg_sql($query);
        if ($fetchMode === null) return parent::query($query);
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
    public function exec(string $statement): int|false {
        return parent::exec(minijira_pg_sql($statement));
    }
    public function rawExec(string $statement): int|false {
        return parent::exec($statement);
    }
}

/** PostgreSQL connection for Render and local development. */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $url = getenv('DATABASE_URL') ?: '';
    $parts = $url !== '' ? parse_url($url) : false;
    $host = getenv('SISTEMA_DB_HOST') ?: ($parts['host'] ?? '127.0.0.1');
    $port = (int) (getenv('SISTEMA_DB_PORT') ?: ($parts['port'] ?? 5432));
    $name = getenv('SISTEMA_DB_DATABASE') ?: (isset($parts['path']) ? ltrim($parts['path'], '/') : 'sistema_universitario');
    $user = getenv('SISTEMA_DB_USERNAME') ?: (isset($parts['user']) ? rawurldecode($parts['user']) : 'postgres');
    $pass = getenv('SISTEMA_DB_PASSWORD') ?: (isset($parts['pass']) ? rawurldecode($parts['pass']) : '');
    $sslmode = getenv('SISTEMA_DB_SSLMODE') ?: (getenv('RENDER') ? 'require' : 'prefer');

    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $host, $port, $name, $sslmode);
    $pdo = new MiniJiraPostgresPDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec("SET TIME ZONE 'America/La_Paz'");
    return $pdo;
}
