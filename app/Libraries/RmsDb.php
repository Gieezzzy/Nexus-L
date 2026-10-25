<?php

namespace App\Libraries;

use Config\Rms;
use PDO;

/** Lazy PDO (pdo_sqlsrv) connection to the shared RMS SQL Server database. */
class RmsDb
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config(Rms::class);
            if ($c->dbUser === '' || $c->dbPass === '') {
                throw new \RuntimeException('RMS database credentials are not configured (rms.dbUser / rms.dbPass in .env).');
            }
            self::$pdo = new PDO(
                "sqlsrv:Server={$c->dbServer};Database={$c->dbName};Encrypt=1;TrustServerCertificate=1;LoginTimeout=5",
                $c->dbUser,
                $c->dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        }

        return self::$pdo;
    }

    /** Run a query with positional params and return all rows. */
    public static function rows(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function exec(string $sql, array $params = []): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);

        return $st;
    }
}
