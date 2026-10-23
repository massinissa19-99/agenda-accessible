<?php
declare(strict_types=1);

/**
 * Retourne une connexion PDO unique (réutilisée à chaque appel).
 *
 * PDO + requêtes préparées = protection contre les injections SQL.
 */
function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $fichierConfig = __DIR__ . '/../config/config.php';
    if (!is_file($fichierConfig)) {
        http_response_code(500);
        exit('Configuration manquante : copiez config/config.example.php vers config/config.php.');
    }

    $c = require $fichierConfig;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $c['db_host'],
        $c['db_name'],
        $c['db_charset']
    );

    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lance des exceptions en cas d'erreur
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // résultats sous forme de tableaux associatifs
        PDO::ATTR_EMULATE_PREPARES   => false,                    // vraies requêtes préparées côté MySQL
    ]);

    return $pdo;
}
