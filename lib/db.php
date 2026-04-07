<?php
/**
 * QuickMLS — MySQLi Database Connection
 */

function getDb(): mysqli {
    static $db = null;
    if ($db && $db->ping()) return $db;

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_errno) {
        throw new RuntimeException('Database connection failed: ' . $db->connect_error);
    }
    $db->set_charset('utf8mb4');
    return $db;
}
