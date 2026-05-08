<?php
/**
 * api/list_files.php
 * Gibt JSON-Liste der Audio-Dateien in einem Verzeichnis zurück.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$allowedTypes = ['intro', 'outro', 'atmosphere', 'voices'];
$audioExts    = ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac'];

$type = isset($_GET['type']) ? preg_replace('/[^a-z]/', '', strtolower($_GET['type'])) : '';
$dir  = isset($_GET['dir'])  ? trim($_GET['dir']) : '';

if (!$dir) {
    echo json_encode(['error' => 'Kein Verzeichnis angegeben', 'files' => []]);
    exit;
}

// Sicherheitscheck: keine path traversal
$realDir = realpath($dir);
if (!$realDir || !is_dir($realDir)) {
    echo json_encode(['error' => "Verzeichnis nicht gefunden: $dir", 'files' => []]);
    exit;
}

$files  = [];
$handle = opendir($realDir);
if ($handle) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry === '.' || $entry === '..') continue;
        $fullPath = $realDir . '/' . $entry;
        if (!is_file($fullPath)) continue;
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $audioExts)) continue;
        $files[] = [
            'name' => $entry,
            'path' => $fullPath,
            'size' => filesize($fullPath),
        ];
    }
    closedir($handle);
}

usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

echo json_encode(['files' => $files, 'dir' => $realDir]);
