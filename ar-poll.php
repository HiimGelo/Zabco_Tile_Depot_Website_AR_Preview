<?php
header('Content-Type: application/json');

$session = isset($_GET['session']) ? preg_replace('/[^a-f0-9]/', '', $_GET['session']) : '';
if (!$session) {
    echo json_encode(['status' => 'error', 'msg' => 'no session']);
    exit;
}

$file = __DIR__ . '/ar_sessions/' . $session . '.json';

if (!file_exists($file)) {
    echo json_encode(['status' => 'pending']);
    exit;
}

// lock to avoid race conditions
$fp = fopen($file, 'r');
if (!$fp) {
    echo json_encode(['status' => 'error', 'msg' => 'unable to read']);
    exit;
}

flock($fp, LOCK_SH);
$content = stream_get_contents($fp);
flock($fp, LOCK_UN);
fclose($fp);

$data = json_decode($content, true);
if (!$data) {
    echo json_encode(['status' => 'error', 'msg' => 'bad data']);
    exit;
}

// delete only after successfully reading
@unlink($file);

echo json_encode([
    'status' => 'ok',
    'session' => $data['session'],
    'length' => $data['length'],
    'width' => $data['width'],
    'productId' => $data['productId'],
    'ts' => $data['timestamp'] ?? $data['ts'] ?? null
]);
?>