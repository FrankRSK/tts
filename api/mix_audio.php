<?php
/**
 * api/mix_audio.php
 * Montiert alle TTS-Segmente + optionales Intro/Outro/Atmosphäre via ffmpeg.
 *
 * Erwartet POST JSON:
 * {
 *   "segment_files": ["/tmp/.../seg_001.wav", ...],
 *   "session_id": "...",
 *   "use_intro": true, "intro_file": "/audio/intros/intro.mp3",
 *   "use_atmosphere": true, "atmosphere_file": "/audio/atm.wav", "atmosphere_volume": 20,
 *   "use_outro": false, "outro_file": "",
 *   "output_dir": "/audio/output",
 *   "tmp_dir": "/tmp/qwen-tts-ui"
 * }
 *
 * Gibt zurück:
 * { "output_file": "/audio/output/podcast_YYYYMMDD_HHMMSS.mp3" }
 *
 * Audio-Pipeline:
 *   1. Segmente mit zufälligen Pausen (0.3–0.6s) konkatenieren → combined.wav
 *   2. Intro (mit Fade-In) voranstellen
 *   3. Outro (mit Fade-Out) anhängen
 *   4. Atmosphäre leise einmischen (normalize=0, Pegel sehr niedrig)
 *   5. WAV → MP3
 */

header('Content-Type: application/json; charset=utf-8');

set_time_limit(0);

// Damit erzeugte Dateien (MP3, WAV) von allen Benutzern gelesen werden können
// (z.B. frank im Dateimanager, Nemo-Vorschau)
umask(0022);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Nur POST erlaubt']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['error' => 'Ungültiges JSON']); exit;
}

$segFiles       = $body['segment_files']    ?? [];
$sessionId      = $body['session_id']       ?? ('qtts_' . time());
$useIntro       = (bool)($body['use_intro']      ?? false);
$introFile      = $body['intro_file']       ?? '';
$useAtmosphere  = (bool)($body['use_atmosphere'] ?? false);
$atmosphereFile = $body['atmosphere_file']  ?? '';
$atmosphereVol  = (int)($body['atmosphere_volume'] ?? 20);
$useOutro       = (bool)($body['use_outro']      ?? false);
$outroFile      = $body['outro_file']       ?? '';
$outputDir      = rtrim($body['output_dir'] ?? '/tmp', '/');
$tmpDir         = rtrim($body['tmp_dir']    ?? '/tmp/qwen-tts-ui', '/');

if (empty($segFiles)) {
    echo json_encode(['error' => 'Keine Segment-Dateien übergeben']); exit;
}

if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

$sessionDir = $tmpDir . '/' . preg_replace('/[^a-z0-9_]/', '', strtolower($sessionId));
if (!is_dir($sessionDir)) mkdir($sessionDir, 0777, true);

$timestamp  = date('Ymd_His');
$outputFile = $outputDir . "/podcast_{$timestamp}.mp3";

// ─── HILFSFUNKTION: Shell-Kommando ausführen ─────────────────────────────────
function run(string $cmd): string {
    return (string)shell_exec($cmd . ' 2>&1');
}

// ─── SCHRITT 1: Segmente mit zufälligen Pausen konkatenieren ─────────────────
// Zwischen je zwei Segmenten wird eine Stille-WAV mit zufälliger Länge (0.3–0.6s)
// eingefügt, um künstliche gleichmäßige Abstände zu vermeiden.

$combinedWav = $sessionDir . '/combined.wav';

if (count($segFiles) === 1) {
    $combinedWav = $segFiles[0];
} else {
    // Stille-Dateien für jeden Übergang erzeugen
    $listContent  = '';
    $silenceIndex = 0;

    foreach ($segFiles as $idx => $segFile) {
        $listContent .= "file '" . str_replace("'", "'\\''", $segFile) . "'\n";

        // Nach jedem Segment außer dem letzten eine Pause einfügen
        if ($idx < count($segFiles) - 1) {
            // Zufällige Pause: 0.3–0.6 Sekunden
            $pauseDuration = round(0.3 + lcg_value() * 0.3, 3);
            $silenceFile   = $sessionDir . "/silence_{$silenceIndex}.wav";

            $silCmd = sprintf(
                'ffmpeg -y -f lavfi -i anullsrc=r=24000:cl=mono -t %.3f %s',
                $pauseDuration,
                escapeshellarg($silenceFile)
            );
            run($silCmd);

            if (file_exists($silenceFile)) {
                $listContent .= "file '" . str_replace("'", "'\\''", $silenceFile) . "'\n";
            }
            $silenceIndex++;
        }
    }

    $listFile = $sessionDir . '/concat_list.txt';
    file_put_contents($listFile, $listContent);

    $cmd = sprintf(
        'ffmpeg -y -f concat -safe 0 -i %s -ar 24000 -ac 1 %s',
        escapeshellarg($listFile),
        escapeshellarg($combinedWav)
    );
    $out = run($cmd);

    if (!file_exists($combinedWav)) {
        echo json_encode(['error' => 'Segmente konnten nicht zusammengefügt werden', 'ffmpeg' => $out]); exit;
    }
}

// ─── SCHRITT 2: Intro mit Fade-In voranstellen ───────────────────────────────
$withIntroWav = $combinedWav;
if ($useIntro && $introFile && file_exists($introFile)) {
    // Intro: Fade-In über 1.5s
    $introFaded = $sessionDir . '/intro_faded.wav';
    $cmd = sprintf(
        'ffmpeg -y -i %s -af "afade=t=in:st=0:d=1.5" -ar 24000 -ac 1 %s',
        escapeshellarg($introFile),
        escapeshellarg($introFaded)
    );
    run($cmd);

    $introSrc     = file_exists($introFaded) ? $introFaded : $introFile;
    $withIntroWav = $sessionDir . '/with_intro.wav';
    $listFile2    = $sessionDir . '/intro_list.txt';
    file_put_contents($listFile2,
        "file '" . str_replace("'", "'\\''", $introSrc)    . "'\n" .
        "file '" . str_replace("'", "'\\''", $combinedWav) . "'\n"
    );
    $cmd = sprintf(
        'ffmpeg -y -f concat -safe 0 -i %s -ar 24000 -ac 1 %s',
        escapeshellarg($listFile2),
        escapeshellarg($withIntroWav)
    );
    run($cmd);
    if (!file_exists($withIntroWav)) $withIntroWav = $combinedWav;
}

// ─── SCHRITT 3: Outro mit Fade-Out anhängen ──────────────────────────────────
$withOutroWav = $withIntroWav;
if ($useOutro && $outroFile && file_exists($outroFile)) {
    // Outro-Länge ermitteln, Fade-Out über letzte 2s
    $probeOut = run(sprintf(
        'ffprobe -v error -show_entries format=duration -of csv=p=0 %s',
        escapeshellarg($outroFile)
    ));
    $outroDuration = (float)trim($probeOut);
    $fadeStart     = max(0.0, $outroDuration - 2.0);

    $outroFaded = $sessionDir . '/outro_faded.wav';
    $cmd = sprintf(
        'ffmpeg -y -i %s -af "afade=t=out:st=%.3f:d=2.0" -ar 24000 -ac 1 %s',
        escapeshellarg($outroFile),
        $fadeStart,
        escapeshellarg($outroFaded)
    );
    run($cmd);

    $outroSrc     = file_exists($outroFaded) ? $outroFaded : $outroFile;
    $withOutroWav = $sessionDir . '/with_outro.wav';
    $listFile3    = $sessionDir . '/outro_list.txt';
    file_put_contents($listFile3,
        "file '" . str_replace("'", "'\\''", $withIntroWav) . "'\n" .
        "file '" . str_replace("'", "'\\''", $outroSrc)     . "'\n"
    );
    $cmd = sprintf(
        'ffmpeg -y -f concat -safe 0 -i %s -ar 24000 -ac 1 %s',
        escapeshellarg($listFile3),
        escapeshellarg($withOutroWav)
    );
    run($cmd);
    if (!file_exists($withOutroWav)) $withOutroWav = $withIntroWav;
}

// ─── SCHRITT 4: Atmosphäre leise einmischen ──────────────────────────────────
// normalize=0 verhindert dass amix die Pegel angleicht.
// Der User-Wert (0–100) wird auf einen sehr niedrigen Bereich (0–0.15) gemappt,
// damit die Atmosphäre wirklich im Hintergrund bleibt.
$finalWav = $withOutroWav;
if ($useAtmosphere && $atmosphereFile && file_exists($atmosphereFile)) {
    $finalWav = $sessionDir . '/with_atmosphere.wav';

    // Mapping: 0–100 → 0.0–0.15 (Atmosphäre bleibt deutlich leiser als Sprache)
    $atmVolume = ($atmosphereVol / 100.0) * 0.15;

    $cmd = sprintf(
        'ffmpeg -y -i %s -stream_loop -1 -i %s ' .
        '-filter_complex "[1:a]volume=%.4f[atm];[0:a][atm]amix=inputs=2:duration=first:normalize=0:dropout_transition=3[mix]" ' .
        '-map "[mix]" -ar 24000 -ac 1 %s',
        escapeshellarg($withOutroWav),
        escapeshellarg($atmosphereFile),
        $atmVolume,
        escapeshellarg($finalWav)
    );
    $out = run($cmd);
    if (!file_exists($finalWav) || filesize($finalWav) < 1000) {
        $finalWav = $withOutroWav;
    }
}

// ─── SCHRITT 5: WAV → MP3 konvertieren ───────────────────────────────────────
$cmd = sprintf(
    'ffmpeg -y -i %s -codec:a libmp3lame -q:a 2 -ar 44100 -ac 2 %s',
    escapeshellarg($finalWav),
    escapeshellarg($outputFile)
);
$out = run($cmd);

if (!file_exists($outputFile)) {
    $outputFile = str_replace('.mp3', '.wav', $outputFile);
    copy($finalWav, $outputFile);
}

if (!file_exists($outputFile)) {
    echo json_encode(['error' => 'Ausgabedatei konnte nicht erstellt werden', 'ffmpeg' => $out]); exit;
}

echo json_encode([
    'output_file' => $outputFile,
    'size'        => filesize($outputFile),
    'filename'    => basename($outputFile),
]);
