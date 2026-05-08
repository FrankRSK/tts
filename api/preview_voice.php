<?php
/**
 * api/preview_voice.php
 * Generiert einen kurzen TTS-Testsatz für einen Sprecher und liefert die WAV-Datei zurück.
 *
 * POST JSON:
 * {
 *   "mode": "custom"|"clone"|"base",
 *   "speaker": "chelsie",           // nur bei mode=custom
 *   "ref_audio": "/pfad/stimme.wav", // nur bei mode=clone
 *   "ref_text":  "Transkript ...",   // nur bei mode=clone
 *   "settings": { python_bin, tts_script, model_custom, model_base, tokenizer, language }
 * }
 *
 * Gibt WAV-Audiodaten zurück (Content-Type: audio/wav)
 * Bei Fehler: JSON { "error": "..." } mit HTTP 500
 */

// TTS-Generierung kann mehrere Minuten dauern (Modell laden + Inferenz)
set_time_limit(0);
ignore_user_abort(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Nur POST erlaubt']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ungültiges JSON']); exit;
}

$mode      = $body['mode']      ?? 'custom';
$speaker   = $body['speaker']   ?? 'aiden';
$refAudio  = $body['ref_audio'] ?? '';
$refText   = $body['ref_text']  ?? '';
$instruct  = $body['instruct']  ?? '';
$cfg       = $body['settings']  ?? [];

$pythonBin   = $cfg['python_bin']   ?? '/usr/bin/python3';
$ttsScript   = $cfg['tts_script']   ?? '/var/www/html/tts/tts_generate.py';
$modelCustom = $cfg['model_custom'] ?? '';
$modelBase   = $cfg['model_base']   ?? '';
$tokenizer   = $cfg['tokenizer']    ?? '';
$language    = $cfg['language']     ?? 'German';

$previewText = 'Dies ist ein Testsatz.';

// Ausgabedatei im Temp-Verzeichnis
$tmpFile = sys_get_temp_dir() . '/qtts_preview_' . uniqid() . '.wav';

// Argumente bauen
$args = [];
if ($mode === 'clone' && $refAudio && file_exists($refAudio)) {
    $args['--mode']      = 'clone';
    $args['--text']      = $previewText;
    $args['--language']  = $language;
    $args['--ref-audio'] = $refAudio;
    $args['--ref-text']  = $refText;
    $args['--output']    = $tmpFile;
    $args['--model']     = $modelBase ?: $modelCustom;
    $args['--tokenizer'] = $tokenizer;
} else {
    $args['--mode']      = 'custom';
    $args['--text']      = $previewText;
    $args['--language']  = $language;
    $args['--speaker']   = $speaker ?: 'aiden';
    $args['--output']    = $tmpFile;
    $args['--model']     = $modelCustom;
    $args['--tokenizer'] = $tokenizer;
    if ($instruct) $args['--instruct'] = $instruct;
}

$parts = [];
foreach ($args as $flag => $v) {
    if ($v === '') continue;
    $parts[] = $flag . ' ' . escapeshellarg($v);
}

// NUMBA_CACHE_DIR: verhindert RuntimeError wenn www-data kein Schreibzugriff auf venv hat
$cmd = 'NUMBA_CACHE_DIR=' . escapeshellarg(sys_get_temp_dir() . '/numba_cache') .
       ' ' . escapeshellarg($pythonBin) .
       ' ' . escapeshellarg($ttsScript) .
       ' ' . implode(' ', $parts) . ' 2>&1';
$output = shell_exec($cmd);

if (!file_exists($tmpFile) || filesize($tmpFile) < 100) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'TTS-Vorschau fehlgeschlagen', 'detail' => trim($output ?? '')]); exit;
}

// WAV ausliefern
header('Content-Type: audio/wav');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-store');
readfile($tmpFile);
unlink($tmpFile);
