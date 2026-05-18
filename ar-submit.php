<?php
// Accept JSON POST { session, length, width, productId } and store to ar_sessions/{session}.json

header('Content-Type: application/json');

// Read and decode incoming JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'msg' => 'Invalid JSON']);
    exit;
}

// Extract and validate fields
$session    = preg_replace('/[^a-f0-9]/', '', ($input['session'] ?? ''));
$length     = isset($input['length']) ? floatval($input['length']) : null;
$width      = isset($input['width'])  ? floatval($input['width'])  : null;
$productId  = isset($input['productId']) ? intval($input['productId']) : null;

// Required fields check
if (!$session || $length === null || $width === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'msg' => 'Missing parameters']);
    exit;
}

// Validate session format (24 hex chars)
if (!preg_match('/^[a-f0-9]{24}$/', $session)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'msg' => 'Invalid session format']);
    exit;
}

// Create folder if not present
$dir = __DIR__ . '/ar_sessions';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

// Build payload
$payload = [
    'session'   => $session,
    'length'    => $length,
    'width'     => $width,
    'productId' => $productId,
    'timestamp' => time()
];

// Write JSON file
$file = $dir . '/' . $session . '.json';
file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Response
echo json_encode(['status' => 'ok', 'received' => $payload]);

error_log('Session received: ' . $session);
?>