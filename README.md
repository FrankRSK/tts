# Qwen TTS Studio — Setup

## Voraussetzungen

```bash
# System
sudo apt install apache2 php libapache2-mod-php poppler-utils ffmpeg

# Python (im bestehenden Qwen-TTS venv oder neu anlegen)
pip install qwen-tts soundfile torch
# Optional, für Geschwindigkeit:
pip install flash-attn --no-build-isolation
```

## Installation

1. Ordner ins Apache-Webroot kopieren:
   ```bash
   cp -r qwen-tts-ui /var/www/html/
   ```

2. Im Browser öffnen: `http://localhost/qwen-tts-ui/`

3. Auf das **⚙ Einstellungen**-Symbol klicken und ausfüllen:
   - Python-Executable (venv): z.B. `/opt/qwen-tts/venv/bin/python`
   - TTS-Script: Pfad zu `tts_generate.py` auf diesem System
   - Modellpfade (Custom, Base, Tokenizer)
   - Verzeichnisse für Stimmen, Atmosphäre, Intros, Outros, Ausgabe

## Modellpfade (TODO – bitte eintragen)

```
CustomVoice-Modell:  [DEIN PFAD zu Qwen3-TTS-12Hz-1.7B-CustomVoice]
Base-Modell:         [DEIN PFAD zu Qwen3-TTS-12Hz-1.7B-Base]
Tokenizer:           [DEIN PFAD zu Qwen3-TTS-Tokenizer-12Hz]
Python-Executable:   [DEIN PFAD zum Python im venv]
```

## Verzeichnisstruktur (Beispiel)

```
/audio/
├── voices/        ← eigene Referenzstimmen (WAV/MP3)
├── atmosphere/    ← Hintergrundgeräusche
├── intros/        ← Intro-Jingles
├── outros/        ← Outro-Jingles
└── output/        ← generierte Podcasts
/tmp/qwen-tts-ui/  ← temporäre Segmentdateien (automatisch bereinigen)
```

## Emotionssteuerung

Qwen3-TTS unterstützt **Natural Language Instructions** – keine Tags.
Beispiele die in der UI auswählbar sind:
- „Sprich klar und sachlich"
- „Sprich fröhlich und energetisch"
- „Sprich nachdenklich und bedächtig"
- „Sprich warm und erzählerisch"
- etc.

Der gewählte Text wird dem Sprechertext als Präfix übergeben:
`"Sprich warm und erzählerisch: Heute sprechen wir über…"`

Bei **Voice Clone** Sprechern sind Emotionen deaktiviert (Qwen TTS Einschränkung).

## Bekannte Preset-Sprecher (CustomVoice-Modell)

`chelsie`, `aiden`, `ethan`, `william`, `olivia`, `emma`, `harper`, `zoe`
*(Vollständige Liste: `faster-qwen3-tts custom --list-speakers`)*

## Hinweise

- PHP muss `shell_exec()` ausführen dürfen (in `php.ini` prüfen)
- Apache-Nutzer (`www-data`) braucht Lesezugriff auf Modell- und Audiodateien
- Temp-Verzeichnis wird nicht automatisch bereinigt – ggf. Cronjob anlegen:
  `find /tmp/qwen-tts-ui -mtime +1 -delete`
