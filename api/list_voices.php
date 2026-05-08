<?php
/**
 * api/list_voices.php
 * Gibt JSON-Liste der Custom-Voice-Dateien (WAV/MP3) aus dem voicesDir zurück.
 * Zu jeder Stimmdatei wird das Transkript mitgeliefert, wenn eine gleichnamige
 * .txt-Datei im selben Verzeichnis liegt.
 *
 * GET ?dir=/absoluter/pfad/zu/voices
 *
 * Gibt zurück:
 * { "voices": [{ "name": "FrankKemper", "path": "/abs/path/FrankKemper.wav", "transcript": "..." }] }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$dir = trim($_GET['dir'] ?? '');

if (!$dir) {
    echo json_encode(['error' => 'Kein Verzeichnis angegeben', 'voices' => []]);
    exit;
}

$realDir = realpath($dir);
if (!$realDir || !is_dir($realDir)) {
    echo json_encode(['error' => "Verzeichnis nicht gefunden: $dir", 'voices' => []]);
    exit;
}

$audioExts = ['wav', 'mp3', 'ogg', 'flac', 'm4a'];
$voices    = [];

$handle = opendir($realDir);
if ($handle) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry === '.' || $entry === '..') continue;
        $fullPath = $realDir . '/' . $entry;
        if (!is_file($fullPath)) continue;

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $audioExts)) continue;

        $basename   = pathinfo($entry, PATHINFO_FILENAME);
        $txtPath    = $realDir . '/' . $basename . '.txt';
        $transcript = '';
        if (file_exists($txtPath)) {
            $transcript = trim(file_get_contents($txtPath));
        }

        $voices[] = [
            'name'       => $basename,
            'path'       => $fullPath,
            'transcript' => $transcript,
        ];
    }
    closedir($handle);
}

usort($voices, fn($a, $b) => strcasecmp($a['name'], $b['name']));

echo json_encode(['voices' => $voices, 'dir' => $realDir]);
