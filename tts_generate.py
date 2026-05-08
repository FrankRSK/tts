#!/usr/bin/env python3
"""
tts_generate.py — Qwen3-TTS Wrapper für die Web-UI

Verwendung:
  python tts_generate.py --mode custom   --speaker chelsie   --text "Hallo Welt" \
                         --language German --output /tmp/out.wav \
                         --model /models/Qwen3-TTS-12Hz-1.7B-CustomVoice \
                         --tokenizer /models/Qwen3-TTS-Tokenizer-12Hz

  python tts_generate.py --mode clone    --ref-audio /voices/ref.wav \
                         --ref-text "Referenztext hier" \
                         --text "Zu sprechender Text" \
                         --language German --output /tmp/out.wav \
                         --model /models/Qwen3-TTS-12Hz-1.7B-Base \
                         --tokenizer /models/Qwen3-TTS-Tokenizer-12Hz

  python tts_generate.py --mode base     --text "Text ohne Stimmprofil" \
                         --language German --output /tmp/out.wav \
                         --model /models/Qwen3-TTS-12Hz-1.7B-Base \
                         --tokenizer /models/Qwen3-TTS-Tokenizer-12Hz

Modes:
  custom   Preset-Sprecher aus CustomVoice-Modell (--speaker erforderlich)
  clone    Voice Cloning mit Referenzaudio (--ref-audio + --ref-text erforderlich)
  base     Basisgenerierung ohne Stimmprofil

Emotion:
  Emotionen werden als Natural-Language-Instruction dem Text vorangestellt,
  z.B. "Sprich fröhlich: Der heutige Tag war…"
  Das Qwen3-TTS Modell interpretiert diese Anweisung automatisch.

Abhängigkeiten:
  pip install qwen-tts soundfile torch
  Optionale Beschleunigung: pip install flash-attn --no-build-isolation
"""

import argparse
import os
import sys
import torch
import soundfile as sf

# ─── TODO: Pfade ggf. anpassen ────────────────────────────────────────────────
# Falls die Modelle nicht automatisch via HuggingFace geladen werden sollen,
# werden sie durch --model und --tokenizer übergeben.
# Standard-Modellnamen (werden als Fallback verwendet wenn --model nicht angegeben):
DEFAULT_MODEL_CUSTOM   = 'Qwen/Qwen3-TTS-12Hz-1.7B-CustomVoice'
DEFAULT_MODEL_BASE     = 'Qwen/Qwen3-TTS-12Hz-1.7B-Base'
DEFAULT_TOKENIZER      = 'Qwen/Qwen3-TTS-Tokenizer-12Hz'
# ──────────────────────────────────────────────────────────────────────────────

def parse_args():
    p = argparse.ArgumentParser(description='Qwen3-TTS Wrapper')
    p.add_argument('--mode',      required=True, choices=['custom', 'clone', 'base'],
                   help='Generierungsmodus: custom (Preset), clone (Voice Cloning), base')
    p.add_argument('--text',      required=True,
                   help='Zu sprechender Text')
    p.add_argument('--instruct',  default='',
                   help='[custom] Natürlichsprachliche Emotion-Instruction (z.B. "Sprich fröhlich")')
    p.add_argument('--language',  default='German',
                   help='Sprache des Textes (German, English, …)')
    p.add_argument('--output',    required=True,
                   help='Ausgabepfad für die WAV-Datei')
    p.add_argument('--model',     default='',
                   help='Lokaler Modellpfad oder HuggingFace-Modellname')
    p.add_argument('--tokenizer', default='',
                   help='Lokaler Tokenizer-Pfad oder HuggingFace-Name')
    p.add_argument('--speaker',   default='aiden',
                   help='[custom] Name des Preset-Sprechers')
    p.add_argument('--ref-audio', default='',
                   help='[clone] Pfad zur Referenz-Audiodatei (WAV/MP3)')
    p.add_argument('--ref-text',  default='',
                   help='[clone] Transkript der Referenz-Audiodatei')
    return p.parse_args()


def load_model(model_name: str, tokenizer_name: str, mode: str):
    """Lädt das passende Qwen3-TTS Modell."""
    from qwen_tts import Qwen3TTSModel

    # Gerät wählen
    device = 'cuda:0' if torch.cuda.is_available() else 'cpu'
    dtype  = torch.bfloat16 if torch.cuda.is_available() else torch.float32

    # Tokenizer-Pfad: entweder explizit oder aus Modell-Pfad ableiten
    tokenizer_path = tokenizer_name or (
        DEFAULT_TOKENIZER if not tokenizer_name else tokenizer_name
    )

    try:
        model = Qwen3TTSModel.from_pretrained(
            model_name,
            device_map=device,
            dtype=dtype,
            # flash_attention_2 nur wenn verfügbar
            # attn_implementation='flash_attention_2',
        )
        return model, device
    except Exception as e:
        print(f'[tts_generate] Modellfehler: {e}', file=sys.stderr)
        sys.exit(1)


def generate_custom(model, text: str, language: str, speaker: str, output: str, instruct: str = ''):
    """Preset-Sprecher aus CustomVoice-Modell."""
    print(f'[tts_generate] custom mode | speaker={speaker} | lang={language} | instruct={instruct!r}')
    wavs, sr = model.generate_custom_voice(
        text=text,
        speaker=speaker,
        language=language,
        instruct=instruct if instruct else None,
    )
    os.makedirs(os.path.dirname(os.path.abspath(output)), exist_ok=True)
    sf.write(output, wavs[0], sr)
    print(f'[tts_generate] ✓ Ausgabe: {output} ({sr} Hz)')


def generate_clone(model, text: str, language: str, ref_audio: str, ref_text: str, output: str):
    """Voice Cloning mit Referenzaudio."""
    print(f'[tts_generate] clone mode | ref={ref_audio} | lang={language}')
    if not ref_audio or not os.path.exists(ref_audio):
        print(f'[tts_generate] Fehler: Referenz-Audiodatei nicht gefunden: {ref_audio}', file=sys.stderr)
        sys.exit(1)
    if not ref_text:
        print('[tts_generate] Warnung: Kein Referenz-Transkript angegeben', file=sys.stderr)

    wavs, sr = model.generate_voice_clone(
        text=text,
        language=language,
        ref_audio=ref_audio,
        ref_text=ref_text,
    )
    os.makedirs(os.path.dirname(os.path.abspath(output)), exist_ok=True)
    sf.write(output, wavs[0], sr)
    print(f'[tts_generate] ✓ Ausgabe: {output} ({sr} Hz)')


def generate_base(model, text: str, language: str, output: str):
    """Basisgenerierung ohne Stimmprofil — nutzt generate_custom_voice mit Default-Sprecher."""
    print(f'[tts_generate] base mode | lang={language}')
    wavs, sr = model.generate_custom_voice(
        text=text,
        speaker='aiden',
        language=language,
    )
    os.makedirs(os.path.dirname(os.path.abspath(output)), exist_ok=True)
    sf.write(output, wavs[0], sr)
    print(f'[tts_generate] ✓ Ausgabe: {output} ({sr} Hz)')


def main():
    args = parse_args()

    print(f'[tts_generate] Modus: {args.mode} | Text: {args.text[:60]}…' if len(args.text) > 60 else f'[tts_generate] Modus: {args.mode} | Text: {args.text}')

    # Modellnamen bestimmen
    if args.mode == 'custom':
        model_name = args.model or DEFAULT_MODEL_CUSTOM
    else:
        model_name = args.model or DEFAULT_MODEL_BASE

    tokenizer_name = args.tokenizer or DEFAULT_TOKENIZER

    model, device = load_model(model_name, tokenizer_name, args.mode)

    if args.mode == 'custom':
        generate_custom(
            model     = model,
            text      = args.text,
            language  = args.language,
            speaker   = args.speaker,
            output    = args.output,
            instruct  = args.instruct,
        )
    elif args.mode == 'clone':
        generate_clone(
            model     = model,
            text      = args.text,
            language  = args.language,
            ref_audio = getattr(args, 'ref_audio', ''),
            ref_text  = getattr(args, 'ref_text', ''),
            output    = args.output,
        )
    elif args.mode == 'base':
        generate_base(
            model    = model,
            text     = args.text,
            language = args.language,
            output   = args.output,
        )
    else:
        print(f'[tts_generate] Unbekannter Modus: {args.mode}', file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
