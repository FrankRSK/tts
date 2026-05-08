<?php
/**
 * api/run_tts.php
 * Generiert Audio für jedes Sprachsegment via tts_generate.py
 *
 * Erwartet POST JSON:
 * {
 *   "segments": [{"sprecher":1,"emotion_instruction":"...","text":"..."}],
 *   "speakers": [{id, name, mode, presetSpeaker, refAudioPath, refText, ...}],
 *   "session_id": "qtts_1234567890",
 *   "settings": { python_bin, tts_script, model_custom, model_base, tokenizer, tmp_dir, language }
 * }
 *
 * Gibt zurück:
 * { "files": ["/tmp/.../seg_001.wav", ...] }
 */

// TTS-Generierung kann viele Minuten dauern (Segmente × Modell-Inferenz)
set_time_limit(0);
ignore_user_abort(true);
umask(0022); // Erzeugte WAVs sollen von allen Benutzern lesbar sein

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Nur POST erlaubt']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['error' => 'Ungültiges JSON']); exit;
}

$segments  = $body['segments']   ?? [];
$speakers  = $body['speakers']   ?? [];
$sessionId = $body['session_id'] ?? ('qtts_' . time());
$cfg       = $body['settings']   ?? [];

$pythonBin   = $cfg['python_bin']   ?? '/usr/bin/python3';
$ttsScript   = $cfg['tts_script']   ?? '/opt/qwen-tts/tts_generate.py';
$modelCustom = $cfg['model_custom'] ?? '';
$modelBase   = $cfg['model_base']   ?? '';
$tokenizer   = $cfg['tokenizer']    ?? '';
$tmpDir      = rtrim($cfg['tmp_dir'] ?? '/tmp/qwen-tts-ui', '/');
$language    = $cfg['language']     ?? 'German';

if (!$segments) {
    echo json_encode(['error' => 'Keine Segmente']); exit;
}

// Arbeitsverzeichnis anlegen
$sessionDir = $tmpDir . '/' . preg_replace('/[^a-z0-9_]/', '', strtolower($sessionId));
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0755, true);
}

// Sprecher-Map (id → config)
$speakerMap = [];
foreach ($speakers as $sp) {
    $speakerMap[(int)$sp['id']] = $sp;
}

// Progress-Datei initialisieren
$progressFile = $sessionDir . '/progress.json';
file_put_contents($progressFile, json_encode(['current' => 0, 'total' => count($segments), 'detail' => '']));

$outputFiles = [];
$errors      = [];

foreach ($segments as $i => $seg) {
    $segNum   = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
    $outFile  = $sessionDir . "/seg_{$segNum}.wav";
    $spId     = (int)($seg['sprecher'] ?? 1);
    $sp       = $speakerMap[$spId] ?? $speakerMap[array_key_first($speakerMap)] ?? null;
    $text     = trim($seg['text'] ?? '');
    $emotion  = trim($seg['emotion_instruction'] ?? '');

    if (!$text) {
        file_put_contents($progressFile, json_encode(['current' => $i+1, 'total' => count($segments), 'detail' => "Seg $segNum übersprungen (leer)"]));
        continue;
    }

    // Args bauen — Emotion als --instruct übergeben (nativer Parameter von generate_custom_voice)
    $args = buildTTSArgs($sp, $text, $emotion, $language, $modelCustom, $modelBase, $tokenizer, $outFile);

    $cmd = 'NUMBA_CACHE_DIR=' . escapeshellarg(sys_get_temp_dir() . '/numba_cache') .
           ' ' . escapeshellarg($pythonBin) .
           ' ' . escapeshellarg($ttsScript) .
           ' ' . $args . ' 2>&1';

    // Progress aktualisieren
    file_put_contents($progressFile, json_encode([
        'current' => $i,
        'total'   => count($segments),
        'detail'  => "Segment $segNum / " . count($segments),
    ]));

    $output = shell_exec($cmd);
    $exitOk = file_exists($outFile) && filesize($outFile) > 0;

    if (!$exitOk) {
        $errors[] = "Segment $segNum fehlgeschlagen: " . trim($output ?? 'Keine Ausgabe');
        // Trotzdem weitermachen, Segment überspringen
        file_put_contents($progressFile, json_encode([
            'current' => $i+1, 'total' => count($segments),
            'detail'  => "⚠ Seg $segNum fehlgeschlagen",
        ]));
        continue;
    }

    $outputFiles[] = $outFile;

    file_put_contents($progressFile, json_encode([
        'current' => $i+1, 'total' => count($segments),
        'detail'  => "✓ Segment $segNum fertig",
    ]));
}

if (empty($outputFiles)) {
    echo json_encode(['error' => 'Kein Segment erfolgreich generiert. ' . implode(' | ', $errors)]); exit;
}

$result = ['files' => $outputFiles];
if ($errors) $result['warnings'] = $errors;

echo json_encode($result);

// ─── TTS ARGS BUILDER ────────────────────────────────────────────────────────
function buildTTSArgs($sp, $text, $instruct, $language, $modelCustom, $modelBase, $tokenizer, $outFile) {
    $args = [];

    if (!$sp) {
        // Fallback: kein Sprecher-Config → custom mit Default-Sprecher
        $args['--mode']      = 'custom';
        $args['--text']      = $text;
        $args['--language']  = $language;
        $args['--speaker']   = 'aiden';
        $args['--output']    = $outFile;
        $args['--model']     = $modelCustom ?: $modelBase;
        $args['--tokenizer'] = $tokenizer;
    } elseif ($sp['mode'] === 'clone') {
        $args['--mode']      = 'clone';
        $args['--text']      = $text;
        $args['--language']  = $language;
        $args['--ref-audio'] = $sp['refAudioPath'] ?? '';
        $args['--ref-text']  = $sp['refText'] ?? '';
        $args['--output']    = $outFile;
        $args['--model']     = $modelBase ?: $modelCustom;
        $args['--tokenizer'] = $tokenizer;
        // Kein --instruct bei clone (Modelleinschränkung)
    } else {
        // custom preset speaker
        $args['--mode']      = 'custom';
        $args['--text']      = $text;
        $args['--language']  = $language;
        $args['--speaker']   = $sp['presetSpeaker'] ?? 'aiden';
        $args['--output']    = $outFile;
        $args['--model']     = $modelCustom;
        $args['--tokenizer'] = $tokenizer;
        if ($instruct) $args['--instruct'] = $instruct;
    }

    $parts = [];
    foreach ($args as $flag => $val) {
        if ($val === '') continue;
        $parts[] = $flag . ' ' . escapeshellarg($val);
    }
    return implode(' ', $parts);
}
