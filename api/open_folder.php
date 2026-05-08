<?php
/**
 * api/open_folder.php
 * Öffnet einen Ordner im Dateimanager (Nemo) des Desktop-Users.
 *
 * GET ?dir=/absoluter/pfad
 *
 * Strategie: systemd-run --user --machine=frank@ nemo <dir>
 * Nur Unterverzeichnisse von /var/www/html/tts/ sind erlaubt.
 */

header('Content-Type: application/json; charset=utf-8');

$dir = $_GET['dir'] ?? '';

if (!$dir) {
    echo json_encode(['error' => 'Kein Verzeichnis angegeben']); exit;
}

// Sicherheitscheck: nur absolute Pfade unter /var/www/html/tts
$real = realpath($dir);
if (!$real || !is_dir($real)) {
    echo json_encode(['error' => 'Verzeichnis nicht gefunden']); exit;
}

$allowedRoot = realpath('/var/www/html/tts');
if (!$allowedRoot || strpos($real, $allowedRoot) !== 0) {
    echo json_encode(['error' => 'Zugriff verweigert']); exit;
}

// Desktop-User ermitteln (wer an :0 eingeloggt ist)
$desktopUser = trim((string)shell_exec(
    'loginctl list-sessions --no-legend 2>/dev/null | awk \'{print $3}\' | head -1'
));
if (!$desktopUser) {
    $desktopUser = 'frank'; // Fallback
}

// Nemo über systemd-run im User-Kontext des Desktop-Users öffnen
$cmd = sprintf(
    'systemd-run --user --machine=%s@ nemo %s > /dev/null 2>&1 &',
    escapeshellarg($desktopUser),
    escapeshellarg($real)
);
shell_exec($cmd);

echo json_encode(['ok' => true, 'dir' => $real, 'user' => $desktopUser]);
