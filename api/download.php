<?php
/**
 * api/download.php
 * Gibt eine generierte Audiodatei als Download aus.
 * GET ?file=/absolute/path/to/podcast.mp3
 *
 * Sicherheitshinweis: Nur Dateien im konfigurierten output_dir werden erlaubt.
 * TODO: output_dir aus einer serverseitigen Konfigurationsdatei laden.
 */

$requestedFile = $_GET['file'] ?? '';
if (!$requestedFile) {
    http_response_code(400); echo 'Keine Datei angegeben'; exit;
}

$realRequested = realpath($requestedFile);

// Sicherheitscheck: Datei muss unter /var/www/html/tts/audio/output
// oder /tmp/qwen-tts-ui liegen (kein Path-Traversal außerhalb)
$allowedBases = [
    realpath('/var/www/html/tts/audio/output'),
    realpath('/tmp/qwen-tts-ui'),
];

$allowed = false;
foreach ($allowedBases as $base) {
    if ($base && $realRequested && strpos($realRequested, $base . '/') === 0) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403); echo 'Zugriff verweigert'; exit;
}

if (!file_exists($realRequested)) {
    http_response_code(404); echo 'Datei nicht gefunden'; exit;
}

$ext      = strtolower(pathinfo($realRequested, PATHINFO_EXTENSION));
$mimeMap  = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'flac' => 'audio/flac'];
$mimeType = $mimeMap[$ext] ?? 'application/octet-stream';
$filename = basename($realRequested);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($realRequested));
header('Cache-Control: no-cache');

readfile($realRequested);
