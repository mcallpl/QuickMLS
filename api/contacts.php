<?php
/**
 * QuickMLS — Contact Search API
 * Searches the PropertyPulse contacts table for autocomplete
 * GET: q=search_term
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/session.php';

requireLogin();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['contacts' => []]);
    exit;
}

// Connect to PropertyPulse database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, 'propertypulse');
if ($db->connect_errno) {
    echo json_encode(['contacts' => [], 'error' => 'Database error']);
    exit;
}
$db->set_charset('utf8mb4');

$search = '%' . $q . '%';
$stmt = $db->prepare("
    SELECT first_name, last_name, phone, email
    FROM contacts
    WHERE status = 'active'
      AND phone IS NOT NULL AND phone != ''
      AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
    ORDER BY first_name, last_name
    LIMIT 10
");
$stmt->bind_param('sss', $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$contacts = [];
while ($row = $result->fetch_assoc()) {
    $contacts[] = [
        'name'  => trim($row['first_name'] . ' ' . $row['last_name']),
        'phone' => $row['phone'],
        'email' => $row['email'],
    ];
}
$stmt->close();
$db->close();

echo json_encode(['contacts' => $contacts]);
