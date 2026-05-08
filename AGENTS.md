# AGENTS.md — Qwen TTS Studio

## Überblick

PHP/JavaScript-Web-UI für Qwen3-TTS. Läuft unter Apache (`/var/www/html/tts/`). **Immer über `http://localhost/tts/` öffnen** — `file://`-Aufruf bricht wegen CORS und fehlendem PHP ab. Kein Build-Schritt, kein Node, kein Bundler.

## Architektur

```
index.html              Frontend (Single Page)
style.css               CSS
app.js                  Gesamte Frontend-Logik (Vanilla JS, localStorage für State)
tts_generate.py         Python-Wrapper um qwen-tts, via shell_exec aufgerufen
audio/
  voices/               Custom-Voice-Samples (WAV/MP3) + gleichnamige .txt-Transkripte
  atmosphere/           Hintergrundatmosphären
  intros/               Intro-Jingles
  outros/               Outro-Jingles
  output/               Generierte Podcasts (MP3)
api/
  run_llm.php           Text → LLM → Sprechersegmente (DeepSeek oder Ollama)
  run_tts.php           Segmente → tts_generate.py → WAV-Dateien
  mix_audio.php         WAV-Segmente + Pausen + Intro/Outro/Atmosphäre → MP3
  progress.php          Polling-Endpunkt (liest /tmp/qwen-tts-ui/<session>/progress.json)
  list_files.php        Audiodateien aus konfigurierten Verzeichnissen listen
  list_voices.php       Custom Voices + Transkripte aus voicesDir liefern
  list_ollama_models.php  Verfügbare Ollama-Modelle via /api/tags abfragen
  preview_voice.php     TTS-Vorschau: generiert "Dies ist ein Testsatz." → WAV-Stream
  upload_pdf.php        PDF/TXT Upload → Text (pdftotext)
  download.php          Ausgabedatei ausliefern
  open_folder.php       Öffnet outputDir in Nemo via systemd-run
```

**Ausführungsfluss:** Browser → (optional) `run_llm.php` → `run_tts.php` (ruft `tts_generate.py` pro Segment via `shell_exec`) → `mix_audio.php` (ffmpeg). Fortschritt per Polling (`progress.php`).

## Systemabhängigkeiten

```bash
sudo apt install apache2 php libapache2-mod-php poppler-utils ffmpeg
pip install qwen-tts soundfile torch
# Optional:
pip install flash-attn --no-build-isolation
```

- `shell_exec()` muss in `php.ini` aktiv sein
- Apache-User `www-data` braucht Lesezugriff auf Modell- und Audiodateien
- `pdftotext` (poppler-utils) für PDF-Upload erforderlich

## Berechtigungen

`www-data` muss in die Gruppe `frank` (oder Gruppen-Schreibzugriff auf `audio/`):

```bash
# Saubere Dauerlösung:
sudo usermod -aG frank www-data
sudo systemctl restart apache2
chmod 775 /var/www/html/tts/audio/output \
          /var/www/html/tts/audio/voices \
          /var/www/html/tts/audio/atmosphere \
          /var/www/html/tts/audio/intros \
          /var/www/html/tts/audio/outros
chmod 1777 /tmp/qwen-tts-ui

# Workaround ohne sudo (aktuell aktiv):
chmod 777 /var/www/html/tts/audio/output  # usw.
```

Alle PHP-Skripte setzen `umask(0022)` → erzeugte Dateien sind `644` (für alle lesbar). Bestehende Dateien mit falschen Rechten: `chmod 644 /var/www/html/tts/audio/output/*.mp3`

## Konfiguration

Alle Pfade im Browser unter **⚙ Einstellungen** (localStorage key `qtts_settings`). Defaults aus `app.js`:

| Setting | Default |
|---|---|
| `pythonBin` | `/mnt/Daten/KI/qwen3-tts/venv/bin/python` |
| `ttsScript` | `/var/www/html/tts/tts_generate.py` |
| `modelCustom` | `.../models--Qwen--Qwen3-TTS-12Hz-1.7B-CustomVoice/snapshots/0c0e3051...` |
| `modelBase` | `.../models--Qwen--Qwen3-TTS-12Hz-0.6B-Base/snapshots/5d839924...` |
| `tokenizer` | (identisch mit `modelCustom`, Tokenizer ist im Modell integriert) |
| `voicesDir` | `/var/www/html/tts/audio/voices` |
| `atmosphereDir` | `/var/www/html/tts/audio/atmosphere` |
| `introDir` | `/var/www/html/tts/audio/intros` |
| `outroDir` | `/var/www/html/tts/audio/outros` |
| `outputDir` | `/var/www/html/tts/audio/output` |
| `tmpDir` | `/tmp/qwen-tts-ui` |

**Wichtig:** Alle Audioverzeichnisse müssen **absolute Pfade** sein — `list_files.php` und `list_voices.php` rufen `realpath()` auf und lehnen relative Pfade ab.

Gespeicherte Einstellungen im Browser überschreiben Defaults. Bei Problemen: **↺ Zurücksetzen** in ⚙ löscht alle localStorage-Keys und lädt neu.

## Modelle

Volle Pfade unter `/mnt/Daten/KI/huggingface-cache/hub/`:

| Modell | Typ | Verwendung |
|---|---|---|
| `Qwen3-TTS-12Hz-1.7B-CustomVoice` | `custom_voice` | Preset-Sprecher (`--mode custom`) |
| `Qwen3-TTS-12Hz-1.7B-VoiceDesign` | `voice_design` | — |
| `Qwen3-TTS-12Hz-0.6B-Base` | `base` | Voice Clone (`--mode clone`) |
| `Qwen3-TTS-12Hz-0.6B-CustomVoice` | `custom_voice` | Preset-Sprecher, kleineres Modell |

**Kritisch:** `custom_voice`-Modelle unterstützen **kein** `generate_voice_clone()` — immer `modelBase` (0.6B-Base) für Voice Clone verwenden. Falsch konfiguriert → `ValueError: does not support generate_voice_clone`.

`tts_generate.py` nutzt als Fallback HuggingFace-Namen wenn kein lokaler Pfad angegeben.

## tts_generate.py — API

```bash
# Preset-Sprecher (CustomVoice-Modell) — nutzt generate_custom_voice()
python tts_generate.py --mode custom --speaker aiden \
  --instruct "Sprich fröhlich und energetisch" \
  --text "..." --language German --output /tmp/out.wav \
  --model /pfad/Qwen3-TTS-12Hz-1.7B-CustomVoice

# Voice Clone (Base-Modell) — nutzt generate_voice_clone()
python tts_generate.py --mode clone \
  --ref-audio /voices/ref.wav --ref-text "Transkript..." \
  --text "..." --language German --output /tmp/out.wav \
  --model /pfad/Qwen3-TTS-12Hz-0.6B-Base
```

- API-Methoden: `generate_custom_voice(text, speaker, language, instruct=None)` und `generate_voice_clone(...)`
- **Nicht** `model.generate()` — diese Methode existiert nicht in der installierten qwen-tts-Version
- Preset-Sprecher (1.7B-CustomVoice): `aiden`, `dylan`, `eric`, `ono_anna`, `ryan`, `serena`, `sohee`, `uncle_fu`, `vivian`
- Vollständige Liste zur Laufzeit: `model.get_supported_speakers()`
- `--instruct` nur bei `--mode custom` (nicht bei `clone`)

## PHP-Timeouts und NUMBA

- Alle TTS-aufrufenden PHP-Skripte setzen `set_time_limit(0)` — PHP-Default (30s) würde bei langen Generierungen NetworkError auslösen
- `NUMBA_CACHE_DIR=/tmp/numba_cache` wird dem `shell_exec`-Befehl vorangestellt — verhindert `RuntimeError: cannot cache function` wenn `www-data` kein Schreibzugriff auf das venv hat

## Textverarbeitungs-Modi

| Modus | Beschreibung |
|---|---|
| `direct` | Text wird 1:1 als ein Segment an TTS übergeben — kein LLM-Schritt |
| `llm` | Text wird durch LLM mit Prompt transformiert, dann segmentiert an TTS |

Im `llm`-Modus: Prompt-Preset wählen oder eigenen System-Prompt eingeben. Presets in `PROMPT_PRESETS` in `app.js`. Eigener Prompt wird als `custom_system_prompt` an `run_llm.php` geschickt; Sprecher-Info wird automatisch angehängt. Im `direct`-Modus wird das LLM-Panel ausgeblendet.

## LLM-Integration

`run_llm.php` unterstützt zwei Backends:
- **DeepSeek**: `https://api.deepseek.com/v1/chat/completions`, Timeout 120s, Default `deepseek-chat`
- **Ollama**: `http://localhost:11434/api/chat`, Timeout 300s

LLM-Antworten werden auf JSON-Array bereinigt (Backtick-Entfernung, Regex-Extraktion `[.*]/s`).

`list_ollama_models.php` fragt `GET /api/tags` mit Timeout 5s ab. Dropdown wird beim Seitenstart befüllt und bei Endpoint-Änderung (800ms Debounce) oder ↻-Klick neu geladen. Der gespeicherte Modellname wird **nach** dem Befüllen wiederhergestellt (nicht in `restoreFormState`).

## Output-Stile und Sprecherzahlen

| Stil | Sprecher |
|---|---|
| `single` | 1 |
| `duo` | 2 |
| `discussion` | 3 |
| `audiobook` | 2 |

## Audio-Pipeline (mix_audio.php)

ffmpeg-Schritte in fixer Reihenfolge:
1. Segmente mit **zufälligen Pausen (0,3–0,6s)** zwischen je zwei Segmenten konkatenieren → `combined.wav` (24000 Hz, mono)
2. Intro mit **Fade-In (1,5s)** voranstellen
3. Outro mit **Fade-Out (2s)** anhängen — Dauer per `ffprobe` ermittelt
4. Atmosphäre einmischen: `normalize=0`, Pegel = `(vol/100) * 0.15` → bleibt deutlich im Hintergrund
5. WAV → MP3 (`libmp3lame -q:a 2`, 44100 Hz, stereo) → `podcast_YYYYMMDD_HHMMSS.mp3`

## Temp-Verzeichnis

Sessions in `/tmp/qwen-tts-ui/<session_id>/`. Kein automatisches Cleanup:
```bash
find /tmp/qwen-tts-ui -mtime +1 -delete
```

`progress.php` liest `tmpDir` aus GET-Parameter `tmp_dir`. Fallback: `/tmp/qwen-tts-ui`. Nur absolute Pfade erlaubt.

## Download und Ordner öffnen

`download.php` prüft ob die angeforderte Datei unter `/var/www/html/tts/audio/output` oder `/tmp/qwen-tts-ui` liegt — alles andere → HTTP 403.

`open_folder.php` öffnet ein Verzeichnis in Nemo via `systemd-run --user --machine=<desktopuser>@ nemo <dir>`. Nur Pfade unter `/var/www/html/tts/` erlaubt. Desktop-User wird per `loginctl` ermittelt. `file://`-Links funktionieren aus HTTP-Kontext nicht (Browser blockiert).

## Stimmen hinzufügen (Voice Clone)

Zwei Dateien in `audio/voices/` legen:
1. Audiodatei (WAV/MP3), z.B. `MeineStimme.wav` — 5–30s, sauber, kein Hintergrundgeräusch
2. Transkript: `MeineStimme.txt` — exakt gesprochener Inhalt

Danach erscheint die Stimme im Voice-Clone-Dropdown (↻ oder Seitenaufruf). Transkript wird automatisch vorausgefüllt.

## Kein Test-Framework

Verifikation manuell über Browser oder direkter PHP/Python-Aufruf.
