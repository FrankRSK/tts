<?php
/**
 * api/list_ollama_models.php
 * Fragt den lokalen Ollama-Server nach verfügbaren Modellen ab.
 *
 * GET ?endpoint=http://localhost:11434   (optional, Default: http://localhost:11434)
 *
 * Gibt zurück:
 * { "models": ["llama3.2:3b", "mistral:latest", ...] }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$endpoint = rtrim($_GET['endpoint'] ?? 'http://localhost:11434', '/');

// Sicherheitscheck: nur http/https erlauben
if (!preg_match('#^https?://#', $endpoint)) {
    echo json_encode(['error' => 'Ungültiger Endpoint', 'models' => []]);
    exit;
}

$ch = curl_init($endpoint . '/api/tags');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'Verbindungsfehler: ' . $err, 'models' => []]);
    exit;
}

if ($code !== 200) {
    echo json_encode(['error' => "Ollama antwortete mit HTTP $code", 'models' => []]);
    exit;
}

$data = json_decode($resp, true);
if (!$data || !isset($data['models'])) {
    echo json_encode(['error' => 'Ungültige Antwort von Ollama', 'models' => []]);
    exit;
}

$names = array_map(fn($m) => $m['name'], $data['models']);
sort($names);

echo json_encode(['models' => $names]);
