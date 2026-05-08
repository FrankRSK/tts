# Qwen3-TTS Web UI 🎤

> Text-to-Speech Web UI für Qwen3-TTS Modelle mit lokalem KI-Backend.

## Features
- **Vollständige Web UI** für TTS-Generierung
- **Qwen3-TTS Modelle** (lokal, kein Internet nötig)
- **Custom Voice Support** (eigene Stimmen trainieren/einbinden)
- **Dual-Backend:** Lokal (Ollama) oder Cloud (DeepSeek API)
- **Export-Funktion** für generierte Audio-Dateien

## Voraussetzungen
- **Qwen3-TTS Modell** (lokal installiert)
- **Ollama** (optional, für lokales LLM-Backend)
- **Node.js** (für den Server)

## Schnellstart
```bash
# Repository klonen
git clone https://github.com/FrankRSK/tts.git
cd tts

# Server starten
node app.js
# → Browser öffnen: http://localhost:XXXX
```

## Technik
| Komponente | Technologie |
|-----------|-------------|
| Frontend | HTML/CSS/JavaScript |
| Backend | Node.js (app.js) |
| TTS Engine | Qwen3-TTS Python |
| LLM-Integration | Ollama / DeepSeek API |

## Konfiguration
Siehe `Einstellungen` in der Web UI für:
- Modellpfade (Custom, Base, Tokenizer)
- API-Keys (optional)
- TTS-Parameter

## Lizenz
MIT © 2026 Frank Kemper
