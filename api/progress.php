<?php
/**
 * api/progress.php
 * Gibt aktuellen Fortschritt für eine Session zurück (Polling-Endpunkt).
 * GET ?session=qtts_1234567890
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$sessionId = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['session'] ?? ''));
if (!$sessionId) {
    echo json_encode(['error' => 'Keine Session-ID']); exit;
}

$tmpDir = rtrim($_GET['tmp_dir'] ?? '/tmp/qwen-tts-ui', '/');
// Sicherheitscheck: nur absolute Pfade erlauben
if (!str_starts_with($tmpDir, '/')) {
    $tmpDir = '/tmp/qwen-tts-ui';
}
$progressFile = $tmpDir . '/' . $sessionId . '/progress.json';

if (!file_exists($progressFile)) {
    echo json_encode(['current' => 0, 'total' => 0, 'detail' => 'Warte …']); exit;
}

$content = file_get_contents($progressFile);
$data    = json_decode($content, true);

echo json_encode($data ?: ['current' => 0, 'total' => 0, 'detail' => '']);
