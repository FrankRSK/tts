<?php
/**
 * api/run_llm.php
 * Sendet Text an DeepSeek oder Ollama, erhält strukturierte Sprecherabschnitte zurück.
 *
 * Erwartet POST JSON:
 * {
 *   "text": "...",
 *   "style": "single|duo|discussion|audiobook",
 *   "speakers": [{id, name, mode, emotionsEnabled, emotionInstruction, ...}],
 *   "backend": "deepseek|ollama",
 *   "deepseek_key": "sk-...",
 *   "deepseek_model": "deepseek-chat",
 *   "ollama_endpoint": "http://localhost:11434",
 *   "ollama_model": "llama3"
 * }
 *
 * Gibt zurück:
 * { "segments": [{"sprecher": 1, "emotion_instruction": "...", "text": "..."}] }
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Nur POST erlaubt']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['error' => 'Ungültiges JSON']); exit;
}

$text               = trim($body['text'] ?? '');
$style              = $body['style'] ?? 'single';
$speakers           = $body['speakers'] ?? [];
$backend            = $body['backend'] ?? 'deepseek';
$deepseekKey        = $body['deepseek_key'] ?? '';
$deepseekModel      = $body['deepseek_model'] ?? 'deepseek-chat';
$ollamaEndpoint     = rtrim($body['ollama_endpoint'] ?? 'http://localhost:11434', '/');
$ollamaModel        = $body['ollama_model'] ?? '';
$customSystemPrompt = trim($body['custom_system_prompt'] ?? '');

if (!$text) {
    echo json_encode(['error' => 'Kein Text angegeben']); exit;
}

// ─── PROMPT BAUEN ────────────────────────────────────────────────────────────
$styleLabels = [
    'single'     => 'EINZELSPRECHER – Ein Sprecher liest den Text linear vor.',
    'duo'        => 'DUO – Zwei Sprecher wechseln sich ab, als wäre es ein Podcast-Gespräch.',
    'discussion' => 'DISKUSSION – Mehrere Sprecher diskutieren kontrovers über die Themen im Text.',
    'audiobook'  => 'HÖRBUCH/HÖRSPIEL – Erzähler und Charakterstimmen. Erzähler leitet ein, Charaktere sprechen direkte Rede.',
];
$styleLabel = $styleLabels[$style] ?? $styleLabels['single'];

$speakerList = '';
foreach ($speakers as $sp) {
    $emotions = ($sp['emotionsEnabled'] ?? true) && $sp['mode'] !== 'clone' ? 'ja' : 'nein';
    $speakerList .= "  - Sprecher {$sp['id']}: \"{$sp['name']}\" | Emotionen: $emotions\n";
}

$emotionNote = '';
foreach ($speakers as $sp) {
    if (($sp['emotionsEnabled'] ?? true) && $sp['mode'] !== 'clone') {
        $emotionNote = 'Das Feld "emotion_instruction" soll eine natürlichsprachliche Regieanweisung für Qwen TTS enthalten, z.B. "Sprich fröhlich und energetisch" oder "Sprich nachdenklich und bedächtig". Leer lassen ("") wenn keine spezifische Emotion gewünscht.';
        break;
    }
}
if (!$emotionNote) {
    $emotionNote = 'Das Feld "emotion_instruction" bleibt immer leer (""), da keine Sprecher Emotionen nutzen.';
}

$systemPrompt = <<<PROMPT
Du bist ein professioneller Podcast- und Hörspiel-Produzent.
Deine Aufgabe: Den folgenden Text in Sprecherabschnitte aufteilen.

AUSGABESTIL: $styleLabel

SPRECHER:
$speakerList

REGELN:
1. Teile den Text sinnvoll in Abschnitte auf. Jeder Abschnitt wird von EINEM Sprecher gesprochen.
2. Behalte den Inhalt des Originaltexts bei. Füge kein neues Wissen hinzu.
3. Bei Duo/Diskussion: verteile die Inhalte natürlich auf beide Sprecher. Sprecher 1 eröffnet meist.
4. Bei Hörbuch: Sprecher 1 ist der Erzähler. Direkte Rede wird anderen Sprechern zugeordnet.
5. $emotionNote
6. Antworte NUR mit einem validen JSON-Array. Kein Markdown, keine Erklärungen, keine Backticks.

JSON-FORMAT:
[
  {"sprecher": 1, "emotion_instruction": "Sprich sachlich und informativ", "text": "Der Sprecher-Text hier."},
  {"sprecher": 2, "emotion_instruction": "", "text": "Antwort des zweiten Sprechers."}
]

WICHTIG: "sprecher" ist die Sprecher-ID (Zahl), "emotion_instruction" ein String, "text" der zu sprechende Text.
PROMPT;

// Wenn ein custom_system_prompt übergeben wurde, ersetzt er den generierten Prompt.
// Der custom Prompt enthält die Sprecher-Info als Anhang, damit die IDs korrekt sind.
if ($customSystemPrompt) {
    $systemPrompt = $customSystemPrompt . "\n\nVERFÜGBARE SPRECHER:\n" . $speakerList .
        "\nWICHTIG: Antworte NUR mit einem validen JSON-Array: " .
        "[{\"sprecher\": 1, \"emotion_instruction\": \"...\", \"text\": \"...\"}]" .
        "\n\"sprecher\" ist die Sprecher-ID (Zahl), \"emotion_instruction\" ein String, \"text\" der zu sprechende Text.";
}

$userPrompt = "TEXT:\n\n" . $text;

// ─── API-CALL ────────────────────────────────────────────────────────────────
if ($backend === 'deepseek') {
    $result = callDeepSeek($systemPrompt, $userPrompt, $deepseekKey, $deepseekModel);
} else {
    $result = callOllama($systemPrompt, $userPrompt, $ollamaEndpoint, $ollamaModel);
}

if (isset($result['error'])) {
    echo json_encode(['error' => $result['error']]); exit;
}

// ─── JSON PARSEN ─────────────────────────────────────────────────────────────
$rawText = $result['text'];
// Backticks entfernen falls vorhanden
$rawText = preg_replace('/```json\s*/', '', $rawText);
$rawText = preg_replace('/```\s*/', '', $rawText);
$rawText = trim($rawText);

// Nur den JSON-Array-Teil extrahieren
if (preg_match('/\[.*\]/s', $rawText, $matches)) {
    $rawText = $matches[0];
}

$segments = json_decode($rawText, true);
if (!is_array($segments)) {
    echo json_encode(['error' => 'LLM-Antwort ist kein gültiges JSON', 'raw' => $rawText]); exit;
}

// Validierung und Normalisierung
$clean = [];
foreach ($segments as $seg) {
    if (!isset($seg['sprecher']) || !isset($seg['text'])) continue;
    $clean[] = [
        'sprecher'           => (int)$seg['sprecher'],
        'emotion_instruction'=> $seg['emotion_instruction'] ?? '',
        'text'               => trim($seg['text']),
    ];
}

if (empty($clean)) {
    echo json_encode(['error' => 'Keine verwertbaren Segmente', 'raw' => $rawText]); exit;
}

echo json_encode(['segments' => $clean, 'count' => count($clean)]);

// ─── DEEPSEEK ────────────────────────────────────────────────────────────────
function callDeepSeek($system, $user, $apiKey, $model) {
    $payload = json_encode([
        'model'    => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
        'temperature' => 0.3,
        'max_tokens'  => 8192,
    ]);

    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['error' => 'cURL-Fehler: ' . $err];

    $data = json_decode($resp, true);
    if (!$data) return ['error' => 'DeepSeek: Ungültige Antwort'];
    if (isset($data['error'])) return ['error' => 'DeepSeek API: ' . ($data['error']['message'] ?? $resp)];

    $text = $data['choices'][0]['message']['content'] ?? '';
    return ['text' => $text];
}

// ─── OLLAMA ──────────────────────────────────────────────────────────────────
function callOllama($system, $user, $endpoint, $model) {
    $payload = json_encode([
        'model'  => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
        'stream' => false,
        'options'=> ['temperature' => 0.3],
    ]);

    $ch = curl_init($endpoint . '/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['error' => 'cURL-Fehler: ' . $err];

    $data = json_decode($resp, true);
    if (!$data) return ['error' => 'Ollama: Ungültige Antwort'];
    if (isset($data['error'])) return ['error' => 'Ollama: ' . $data['error']];

    $text = $data['message']['content'] ?? '';
    return ['text' => $text];
}
