<?php
/**
 * api/upload_pdf.php
 * Nimmt eine PDF- oder TXT-Datei entgegen und gibt den extrahierten Text zurück.
 * Erfordert: pdftotext (poppler-utils) für PDF-Extraktion
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Nur POST erlaubt']); exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['error' => 'Keine Datei empfangen']); exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload-Fehler: Code ' . $file['error']]); exit;
}

$tmpPath  = $file['tmp_name'];
$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, ['pdf', 'txt'])) {
    echo json_encode(['error' => 'Nur PDF und TXT werden unterstützt']); exit;
}

$text = '';

if ($ext === 'txt') {
    $text = file_get_contents($tmpPath);
    if ($text === false) {
        echo json_encode(['error' => 'Datei konnte nicht gelesen werden']); exit;
    }
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
} elseif ($ext === 'pdf') {
    // Versuche pdftotext (Poppler)
    $pdfPath = escapeshellarg($tmpPath);
    $cmd     = "pdftotext -layout -enc UTF-8 $pdfPath - 2>&1";
    $output  = shell_exec($cmd);

    if ($output === null || str_contains($output, 'Error')) {
        echo json_encode(['error' => 'PDF-Extraktion fehlgeschlagen. Ist pdftotext installiert? (sudo apt install poppler-utils)']); exit;
    }
    $text = $output;
}

// Bereinigung
$text = preg_replace('/\r\n|\r/', "\n", $text);
$text = preg_replace('/\n{3,}/', "\n\n", $text);
$text = trim($text);

echo json_encode([
    'text'   => $text,
    'length' => mb_strlen($text),
    'source' => $origName,
]);
