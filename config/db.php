<?php
/**
 * PDO database connection (PHP 8.2).
 *
 * Returns a shared PDO instance via getDB(). Throws a PDOException that is
 * converted to a clean fatal message so credentials are never leaked.
 */

declare(strict_types=1);

// The clinic runs on Philippine time. PHP's own clock must match the DB session
// (time_zone '+08:00', set below) or anything stamped with PHP's date() (e.g.
// payments.paid_at, which Revenue Summary buckets by) lands on the wrong day:
// Hostinger's PHP defaults to UTC and local XAMPP to Europe/Berlin.
date_default_timezone_set('Asia/Manila');

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'dgtone_db';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        // Clinic runs on Philippine time. Pin the MySQL session to UTC+8 so
        // CURDATE()/NOW() roll over at Philippine midnight even when the
        // hosting server's MySQL clock is UTC.
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Database connection error. Please try again later.');
    }

    return $pdo;
}
