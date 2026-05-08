/* ════════════════════════════════════════════════════════════
   QWEN TTS STUDIO — app.js
   Speicherung: localStorage
   ════════════════════════════════════════════════════════════ */

'use strict';

// ─── PROMPT PRESETS ──────────────────────────────────────────────────────────
const PROMPT_PRESETS = {
  podcast: {
    label: 'Podcast',
    hint:  'Zwei Moderatoren diskutieren den Inhalt locker und informativ.',
    prompt: `Du bist ein professioneller Podcast-Produzent. Wandle den folgenden Text in ein lebendiges Podcast-Gespräch zwischen den angegebenen Sprechern um. Die Sprecher sollen den Inhalt locker, informativ und unterhaltsam besprechen, sich gegenseitig ergänzen und gelegentlich persönliche Einschätzungen einbringen. Behalte alle wesentlichen Informationen bei.
Antworte NUR mit einem validen JSON-Array: [{"sprecher": 1, "emotion_instruction": "...", "text": "..."}]`,
  },
  discussion: {
    label: 'Diskussion',
    hint:  'Sprecher vertreten verschiedene Standpunkte und debattieren kontrovers.',
    prompt: `Du bist ein Hörfunk-Regisseur. Verwandle den folgenden Text in eine lebhafte Diskussion zwischen den angegebenen Sprechern. Die Sprecher sollen unterschiedliche Perspektiven, Meinungen oder Interpretationen des Inhalts vertreten und miteinander debattieren. Es darf auch Widerspruch und Nachfragen geben.
Antworte NUR mit einem validen JSON-Array: [{"sprecher": 1, "emotion_instruction": "...", "text": "..."}]`,
  },
  hoerspiel: {
    label: 'Hörspiel',
    hint:  'Erzähler führt durch die Geschichte, Charaktere sprechen direkte Rede.',
    prompt: `Du bist ein Hörspiel-Autor. Wandle den folgenden Text in ein Hörspiel um. Sprecher 1 ist der Erzähler, der die Handlung und Beschreibungen vorträgt. Weitere Sprecher übernehmen die Rollen von Charakteren und sprechen direkte Rede. Formuliere fehlende direkte Rede aus dem Text heraus. Die Atmosphäre soll dramatisch und packend sein.
Antworte NUR mit einem validen JSON-Array: [{"sprecher": 1, "emotion_instruction": "...", "text": "..."}]`,
  },
  erklaerung: {
    label: 'Erklärung',
    hint:  'Inhalte werden vereinfacht und verständlich erklärt.',
    prompt: `Du bist ein erfahrener Wissensvermittler. Erkläre den Inhalt des folgenden Textes klar, einfach und verständlich — als würdest du es jemandem ohne Vorkenntnisse erklären. Verwende Analogien und Beispiele. Behalte alle wichtigen Informationen, vereinfache aber die Sprache und Struktur erheblich.
Antworte NUR mit einem validen JSON-Array: [{"sprecher": 1, "emotion_instruction": "...", "text": "..."}]`,
  },
  zusammenfassung: {
    label: 'Zusammenfassung',
    hint:  'Der Text wird auf die wesentlichen Punkte verdichtet.',
    prompt: `Du bist ein professioneller Redakteur. Fasse den folgenden Text prägnant zusammen. Extrahiere die wichtigsten Informationen, Kernaussagen und Schlussfolgerungen. Die Zusammenfassung soll etwa 20–30 % der Länge des Originaltexts haben und für eine Audioausgabe flüssig klingen.
Antworte NUR mit einem validen JSON-Array: [{"sprecher": 1, "emotion_instruction": "...", "text": "..."}]`,
  },
  nachrichten: {
    label: 'Nachrichtensendung',
    hint:  'Professionelle Nachrichtensendung mit Moderation und Berichten.',
    prompt: `Du bist ein Nachrichtenredakteur. Forme den folgenden Text in eine professionelle Nachrichtensendung um. Sprecher 1 ist der Nachrichtensprecher/die Moderatorin, der/die sachlich und klar die Hauptmeldungen präsentiert. Weitere Sprecher können als Reporter oder Experten auftreten. Verwende nachrichtentypische Sprache: kurze Sätze, aktive Formulierungen, präzise Aussagen.
Antworte NUR mit einem validen JSON-Array: [{"sprecher": 1, "emotion_instruction": "...", "text": "..."}]`,
  },
  emotionen: {
    label: 'Emotionen hinzufügen',
    hint:  'Text bleibt inhaltlich gleich, erhält aber emotionale Regieanweisungen (nur Preset-Sprecher).',
    prompt: `Du bist ein Hörfunk-Regisseur. Teile den folgenden Text in natürliche Sprechabschnitte auf und weise jedem Abschnitt eine passende emotionale Regieanweisung zu (z.B. "Sprich fröhlich und energetisch", "Sprich nachdenklich und bedächtig", "Sprich warm und erzählerisch"). Verändere den Text selbst NICHT — nur Aufteilung und Emotion-Instructions. Verteile die Abschnitte sinnvoll auf die verfügbaren Sprecher.
Antworte NUR mit einem validen JSON-Array: [{"sprecher": 1, "emotion_instruction": "...", "text": "..."}]`,
  },
};

// ─── PRESET SPEAKERS ─────────────────────────────────────────────────────────
const PRESET_SPEAKERS = [
  'aiden', 'dylan', 'eric', 'ono_anna', 'ryan', 'serena', 'sohee', 'uncle_fu', 'vivian',
];

// ─── EMOTION PRESETS (Natural-Language Instructions für Qwen TTS) ───────────
const EMOTION_OPTIONS = [
  { value: '',                              label: 'Automatisch (kein Override)' },
  { value: 'Sprich klar und sachlich',      label: 'Sachlich / Neutral' },
  { value: 'Sprich fröhlich und energetisch', label: 'Fröhlich / Energetisch' },
  { value: 'Sprich nachdenklich und bedächtig', label: 'Nachdenklich / Bedächtig' },
  { value: 'Sprich aufgeregt und begeistert', label: 'Aufgeregt / Begeistert' },
  { value: 'Sprich warm und erzählerisch',  label: 'Warm / Erzählerisch' },
  { value: 'Sprich dramatisch und eindringlich', label: 'Dramatisch / Eindringlich' },
  { value: 'Sprich leise und flüsternd',    label: 'Leise / Flüsternd' },
  { value: 'Sprich mit einem traurigen Unterton', label: 'Traurig / Melancholisch' },
  { value: 'Sprich ironisch und trocken',   label: 'Ironisch / Trocken' },
  { value: 'Sprich jovial und humorvoll',   label: 'Humorvoll / Locker' },
];

// ─── DEFAULT SETTINGS ────────────────────────────────────────────────────────
const DEFAULT_SETTINGS = {
  pythonBin:      '/mnt/Daten/KI/qwen3-tts/venv/bin/python',
  ttsScript:      '/var/www/html/tts/tts_generate.py',
  modelCustom:    '/mnt/Daten/KI/huggingface-cache/hub/models--Qwen--Qwen3-TTS-12Hz-1.7B-CustomVoice/snapshots/0c0e3051f131929182e2c023b9537f8b1c68adfe',
  modelBase:      '/mnt/Daten/KI/huggingface-cache/hub/models--Qwen--Qwen3-TTS-12Hz-0.6B-Base/snapshots/5d83992436eae1d760afd27aff78a71d676296fc',
  tokenizer:      '/mnt/Daten/KI/huggingface-cache/hub/models--Qwen--Qwen3-TTS-12Hz-1.7B-CustomVoice/snapshots/0c0e3051f131929182e2c023b9537f8b1c68adfe',
  voicesDir:      '/var/www/html/tts/audio/voices',
  atmosphereDir:  '/var/www/html/tts/audio/atmosphere',
  introDir:       '/var/www/html/tts/audio/intros',
  outroDir:       '/var/www/html/tts/audio/outros',
  outputDir:      '/var/www/html/tts/audio/output',
  tmpDir:         '/tmp/qwen-tts-ui',
  language:       'German',
};

// ─── STATE ───────────────────────────────────────────────────────────────────
let settings         = {};
let speakers         = [];       // Array of speaker config objects
let pollTimer        = null;
let sessionId        = null;
let customVoices     = [];       // [{ name, path, transcript }] — geladen via list_voices.php
let ollamaModels     = [];       // ['modelname:tag', ...] — geladen via list_ollama_models.php
let activePreset     = 'podcast'; // aktuell gewählter Prompt-Preset-Key

// ─── INIT ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  loadSettings();
  loadSpeakerState();
  bindEvents();
  restoreFormState();
  // Voices und Ollama-Modelle laden, dann Speaker-Slots rendern
  await Promise.allSettled([
    loadCustomVoices(),
    loadOllamaModels(),
  ]);
  updateSpeakerSlots();
  updateSpeakerCountFromStyle();
  renderActivePreset();
});

// ─── SETTINGS ────────────────────────────────────────────────────────────────
function loadSettings() {
  const saved = localStorage.getItem('qtts_settings');
  settings = saved ? { ...DEFAULT_SETTINGS, ...JSON.parse(saved) } : { ...DEFAULT_SETTINGS };
}

function saveSettings() {
  const s = {
    pythonBin:     val('settingPythonBin'),
    ttsScript:     val('settingTtsScript'),
    modelCustom:   val('settingModelCustom'),
    modelBase:     val('settingModelBase'),
    tokenizer:     val('settingTokenizer'),
    voicesDir:     val('settingVoicesDir'),
    atmosphereDir: val('settingAtmosphereDir'),
    introDir:      val('settingIntroDir'),
    outroDir:      val('settingOutroDir'),
    outputDir:     val('settingOutputDir'),
    tmpDir:        val('settingTmpDir'),
    language:      val('settingLanguage'),
  };
  settings = s;
  localStorage.setItem('qtts_settings', JSON.stringify(s));
  showModal('modalSettings', false);
}

function openSettings() {
  set('settingPythonBin',     settings.pythonBin);
  set('settingTtsScript',     settings.ttsScript);
  set('settingModelCustom',   settings.modelCustom);
  set('settingModelBase',     settings.modelBase);
  set('settingTokenizer',     settings.tokenizer);
  set('settingVoicesDir',     settings.voicesDir);
  set('settingAtmosphereDir', settings.atmosphereDir);
  set('settingIntroDir',      settings.introDir);
  set('settingOutroDir',      settings.outroDir);
  set('settingOutputDir',     settings.outputDir);
  set('settingTmpDir',        settings.tmpDir);
  set('settingLanguage',      settings.language);
  showModal('modalSettings', true);
}

// ─── CUSTOM VOICES LADEN ─────────────────────────────────────────────────────
async function loadCustomVoices() {
  try {
    const res  = await fetch(`api/list_voices.php?dir=${encodeURIComponent(settings.voicesDir)}`);
    const data = await res.json();
    if (Array.isArray(data.voices)) customVoices = data.voices;
  } catch(e) {
    customVoices = [];
  }
}

// ─── OLLAMA MODELLE LADEN ─────────────────────────────────────────────────────
async function loadOllamaModels() {
  // DOM-Wert bevorzugen (falls User gerade editiert), sonst gespeicherte Einstellung
  const endpoint = val('ollamaEndpoint') || settings.ollamaEndpoint || 'http://localhost:11434';
  try {
    const res  = await fetch(`api/list_ollama_models.php?endpoint=${encodeURIComponent(endpoint)}`);
    const data = await res.json();
    if (Array.isArray(data.models)) ollamaModels = data.models;
  } catch(e) {
    ollamaModels = [];
  }
  renderOllamaModelSelect();
}

function renderOllamaModelSelect() {
  const sel = el('ollamaModel');
  if (!sel) return;
  const current = sel.value;
  sel.innerHTML = '<option value="">— Modell wählen —</option>';
  ollamaModels.forEach(m => {
    const opt = document.createElement('option');
    opt.value = m;
    opt.textContent = m;
    if (m === current) opt.selected = true;
    sel.appendChild(opt);
  });
  // Gespeicherten Wert wiederherstellen
  const saved = localStorage.getItem('qtts_form');
  if (saved) {
    try {
      const s = JSON.parse(saved);
      if (s.ollamaModel) sel.value = s.ollamaModel;
    } catch(e) {}
  }
}

// ─── TEXTVERARBEITUNGS-MODUS ─────────────────────────────────────────────────
function toggleProcessingMode() {
  const mode = getRadioValue('processingMode');
  const opts = el('llmProcessingOptions');
  if (opts) opts.style.display = mode === 'llm' ? '' : 'none';
  // Im Direkt-Modus: LLM-Panel ausblenden
  const llmPanel = el('panelLLM');
  if (llmPanel) llmPanel.style.display = mode === 'llm' ? '' : 'none';
  // Im Direkt-Modus: Stil-Panel auf single-only hinweisen (optional — wir lassen es sichtbar)
  if (mode === 'llm') renderActivePreset();
}

function renderActivePreset() {
  // Preset-Buttons: aktiven hervorheben
  document.querySelectorAll('.prompt-preset-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.preset === activePreset);
  });
  // Custom-Bereich
  const isCustom = activePreset === 'custom';
  const customArea = el('customPromptArea');
  if (customArea) customArea.style.display = isCustom ? '' : 'none';
  // Vorschau-Box mit Prompt-Kurztext
  const preview = el('promptPreview');
  if (preview) {
    if (!isCustom && PROMPT_PRESETS[activePreset]) {
      preview.textContent = PROMPT_PRESETS[activePreset].hint;
      preview.style.display = '';
    } else {
      preview.style.display = 'none';
    }
  }
}

// Aktiven Prompt-Text für die Generierung ermitteln
function getActivePromptText() {
  if (activePreset === 'custom') return val('customPromptText').trim();
  return PROMPT_PRESETS[activePreset]?.prompt || '';
}

// ─── FORM STATE (localStorage) ───────────────────────────────────────────────
function saveFormState() {
  const state = {
    text:              val('inputText'),
    processingMode:    getRadioValue('processingMode'),
    activePreset,
    customPromptText:  val('customPromptText'),
    outputStyle:       getRadioValue('outputStyle'),
    useIntro:          checked('useIntro'),
    introFile:         val('introFile'),
    useAtmosphere:     checked('useAtmosphere'),
    atmosphereFile:    val('atmosphereFile'),
    atmosphereVolume:  val('atmosphereVolume'),
    useOutro:          checked('useOutro'),
    outroFile:         val('outroFile'),
    llmBackend:        getRadioValue('llmBackend'),
    deepseekKey:       val('deepseekKey'),
    deepseekModel:     val('deepseekModel'),
    ollamaEndpoint:    val('ollamaEndpoint'),
    ollamaModel:       val('ollamaModel'),
    speakers,
  };
  localStorage.setItem('qtts_form', JSON.stringify(state));
}

function restoreFormState() {
  const saved = localStorage.getItem('qtts_form');
  if (!saved) return;
  try {
    const s = JSON.parse(saved);
    if (s.text)             set('inputText', s.text);
    if (s.processingMode)   setRadio('processingMode', s.processingMode);
    if (s.activePreset)   { activePreset = s.activePreset; }
    if (s.customPromptText) set('customPromptText', s.customPromptText);
    if (s.outputStyle)      setRadio('outputStyle', s.outputStyle);
    if (s.useIntro)         setChecked('useIntro', s.useIntro);
    if (s.useAtmosphere)  setChecked('useAtmosphere', s.useAtmosphere);
    if (s.atmosphereFile) { /* loaded after file list */ }
    if (s.atmosphereVolume) {
      set('atmosphereVolume', s.atmosphereVolume);
      el('atmosphereVolumeVal').textContent = s.atmosphereVolume + '%';
    }
    if (s.useOutro)       setChecked('useOutro', s.useOutro);
    if (s.llmBackend)     setRadio('llmBackend', s.llmBackend);
    if (s.deepseekKey)    set('deepseekKey', s.deepseekKey);
    if (s.deepseekModel)  set('deepseekModel', s.deepseekModel);
    if (s.ollamaEndpoint) set('ollamaEndpoint', s.ollamaEndpoint);
    // ollamaModel wird in renderOllamaModelSelect() gesetzt (nach Laden der Modellliste)
    if (Array.isArray(s.speakers)) speakers = s.speakers;

    toggleEnvSelectors();
    toggleLLMConfig();
    toggleProcessingMode();
    updateCharCount();
  } catch(e) { /* ignore corrupt state */ }
}

function loadSpeakerState() {
  const saved = localStorage.getItem('qtts_speakers');
  if (saved) {
    try { speakers = JSON.parse(saved); } catch(e) {}
  }
}

function saveSpeakerState() {
  localStorage.setItem('qtts_speakers', JSON.stringify(speakers));
}

// ─── SPEAKER SLOTS ───────────────────────────────────────────────────────────
const STYLE_SPEAKER_COUNT = { single: 1, duo: 2, discussion: 3, audiobook: 2 };

function updateSpeakerCountFromStyle() {
  const style = getRadioValue('outputStyle');
  const count = STYLE_SPEAKER_COUNT[style] || 1;

  // Pad speakers array
  while (speakers.length < count) {
    speakers.push(defaultSpeaker(speakers.length + 1));
  }
  // Don't shrink — user might switch back
  updateSpeakerSlots();
}

function defaultSpeaker(n) {
  return {
    id: n,
    name: n === 1 ? 'Sprecher 1' : n === 2 ? 'Sprecher 2' : `Sprecher ${n}`,
    mode: 'custom',      // 'custom' (preset) | 'clone' (ref audio)
    presetSpeaker: '',
    refAudioPath: '',
    refText: '',
    emotionInstruction: '',
    emotionsEnabled: true,
  };
}

function updateSpeakerSlots() {
  const style = getRadioValue('outputStyle');
  const count = STYLE_SPEAKER_COUNT[style] || 1;
  const container = el('speakerSlots');
  container.innerHTML = '';

  for (let i = 0; i < count; i++) {
    if (!speakers[i]) speakers[i] = defaultSpeaker(i + 1);
    container.appendChild(buildSpeakerSlot(i));
  }
}

function buildSpeakerSlot(idx) {
  const sp = speakers[idx];
  const div = document.createElement('div');
  div.className = 'speaker-slot';
  div.dataset.idx = idx;

  // Emotion dropdown HTML
  const emotionOptions = EMOTION_OPTIONS.map(o =>
    `<option value="${esc(o.value)}" ${sp.emotionInstruction === o.value ? 'selected' : ''}>${esc(o.label)}</option>`
  ).join('');

  // Preset-Speaker dropdown
  const presetOptions = PRESET_SPEAKERS.map(s =>
    `<option value="${esc(s)}" ${sp.presetSpeaker === s ? 'selected' : ''}>${esc(s)}</option>`
  ).join('');

  // Custom-Voice dropdown
  const voiceOptions = customVoices.map(v =>
    `<option value="${esc(v.path)}" ${sp.refAudioPath === v.path ? 'selected' : ''}>${esc(v.name)}</option>`
  ).join('');

  div.innerHTML = `
    <div class="speaker-header">
      <span class="speaker-num">SP${idx + 1}</span>
      <input type="text" class="speaker-name-input" value="${esc(sp.name)}" placeholder="Name / Rolle" data-field="name">
      <button class="btn-preview" title="Testsatz sprechen lassen">▶ Vorschau</button>
    </div>
    <div class="voice-mode-tabs">
      <button class="voice-mode-tab ${sp.mode === 'custom' ? 'active' : ''}" data-mode="custom">Preset-Stimme</button>
      <button class="voice-mode-tab ${sp.mode === 'clone' ? 'active' : ''}" data-mode="clone">Voice Clone</button>
    </div>

    <!-- Preset-Stimme -->
    <div class="preset-voice-section ${sp.mode === 'custom' ? '' : 'hidden'}">
      <div class="speaker-body">
        <div class="field-group">
          <label class="field-label">Preset-Speaker</label>
          <select class="select-field" data-field="presetSpeaker">
            <option value="">— Sprecher wählen —</option>
            ${presetOptions}
          </select>
        </div>
        <div class="field-group">
          <label class="field-label">Emotion</label>
          <select class="select-field" data-field="emotionInstruction">${emotionOptions}</select>
        </div>
      </div>
    </div>

    <!-- Voice Clone -->
    <div class="clone-voice-section ${sp.mode === 'clone' ? '' : 'hidden'}">
      <div class="speaker-body">
        <div class="field-group" style="grid-column:1/-1">
          <label class="field-label">Referenz-Stimme</label>
          <select class="select-field voice-select" data-field="refAudioPath">
            <option value="">— Stimme wählen —</option>
            ${voiceOptions}
          </select>
        </div>
        <div class="field-group" style="grid-column:1/-1">
          <label class="field-label">Transkript der Referenzdatei</label>
          <textarea class="text-field" rows="2" data-field="refText"
                    placeholder="Was wird in der Referenzdatei gesagt?">${esc(sp.refText)}</textarea>
        </div>
      </div>
      <div class="field-group" style="margin-top:8px; color: var(--text-dim); font-size:0.75rem;">
        ℹ Emotionen sind bei Voice Clone nicht verfügbar (Qwen TTS Einschränkung)
      </div>
    </div>
  `;

  // Bind events
  div.querySelector('.speaker-name-input').addEventListener('input', e => {
    speakers[idx].name = e.target.value;
    saveSpeakerState(); saveFormState();
  });

  div.querySelectorAll('.voice-mode-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      speakers[idx].mode = btn.dataset.mode;
      div.querySelectorAll('.voice-mode-tab').forEach(b => b.classList.toggle('active', b === btn));
      div.querySelector('.preset-voice-section').classList.toggle('hidden', btn.dataset.mode !== 'custom');
      div.querySelector('.clone-voice-section').classList.toggle('hidden', btn.dataset.mode !== 'clone');
      saveSpeakerState(); saveFormState();
    });
  });

  // Vorschau-Button
  div.querySelector('.btn-preview').addEventListener('click', function() {
    previewSpeaker(idx, this);
  });

  div.querySelectorAll('[data-field]').forEach(field => {
    // SELECT → 'change', TEXTAREA → 'input', INPUT → 'input'
    const evt = field.tagName === 'SELECT' ? 'change' : 'input';
    field.addEventListener(evt, e => {
      speakers[idx][field.dataset.field] = e.target.value;

      // Transkript automatisch befüllen wenn Voice-Dropdown geändert und Feld noch leer
      if (field.dataset.field === 'refAudioPath') {
        const voice = customVoices.find(v => v.path === e.target.value);
        if (voice && voice.transcript) {
          const refTextArea = div.querySelector('[data-field="refText"]');
          if (refTextArea && !speakers[idx].refText) {
            refTextArea.value = voice.transcript;
            speakers[idx].refText = voice.transcript;
          }
        }
      }

      saveSpeakerState(); saveFormState();
    });
  });

  return div;
}

// ─── FILE LISTS ──────────────────────────────────────────────────────────────
async function loadFileList(type) {
  const selectId = { intro: 'introFile', atmosphere: 'atmosphereFile', outro: 'outroFile' }[type];
  const dirSetting = { intro: settings.introDir, atmosphere: settings.atmosphereDir, outro: settings.outroDir }[type];
  const select = el(selectId);

  try {
    const res = await fetch(`api/list_files.php?dir=${encodeURIComponent(dirSetting)}&type=${type}`);
    const data = await res.json();
    const saved = localStorage.getItem(`qtts_file_${type}`);

    select.innerHTML = '<option value="">— Datei wählen —</option>';
    if (data.files && data.files.length) {
      data.files.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.path;
        opt.textContent = f.name;
        if (saved && saved === f.path) opt.selected = true;
        select.appendChild(opt);
      });
    } else {
      select.innerHTML = '<option value="">Keine Dateien gefunden</option>';
    }
  } catch(e) {
    select.innerHTML = '<option value="">Fehler beim Laden</option>';
  }
}

// ─── ENV TOGGLE ──────────────────────────────────────────────────────────────
function toggleEnvSelectors() {
  toggleEnv('useIntro',       'introSelector');
  toggleEnv('useAtmosphere',  'atmosphereSelector');
  toggleEnv('useOutro',       'outroSelector');
}
function toggleEnv(checkboxId, selectorId) {
  const on = checked(checkboxId);
  el(selectorId).classList.toggle('visible', on);
  if (on) {
    const type = checkboxId.replace('use','').toLowerCase();
    loadFileList(type);
  }
}

// ─── LLM CONFIG TOGGLE ───────────────────────────────────────────────────────
function toggleLLMConfig() {
  const backend = getRadioValue('llmBackend');
  el('deepseekConfig').style.display = backend === 'deepseek' ? 'flex' : 'none';
  el('ollamaConfig').style.display   = backend === 'ollama'   ? 'flex' : 'none';
}

// ─── GENERATE ────────────────────────────────────────────────────────────────
async function startGeneration() {
  const text = val('inputText').trim();
  if (!text) { showError('Bitte zuerst einen Text eingeben.'); return; }

  const processingMode = getRadioValue('processingMode');
  const style          = getRadioValue('outputStyle');
  const count          = STYLE_SPEAKER_COUNT[style] || 1;
  const activeSpeakers = speakers.slice(0, count);
  const llmBackend     = getRadioValue('llmBackend');
  const deepseekKey    = val('deepseekKey');
  const deepseekModel  = val('deepseekModel');
  const ollamaEndpoint = val('ollamaEndpoint');
  const ollamaModel    = val('ollamaModel');

  // Im LLM-Modus: Backend-Validierung
  if (processingMode === 'llm') {
    if (llmBackend === 'deepseek' && !deepseekKey) {
      showError('DeepSeek API-Key fehlt (Einstellungen → LLM-Backend).'); return;
    }
    if (llmBackend === 'ollama' && !ollamaModel) {
      showError('Ollama Modell nicht angegeben.'); return;
    }
    if (activePreset === 'custom' && !val('customPromptText').trim()) {
      showError('Eigener Prompt ist leer.'); return;
    }
  }

  sessionId = 'qtts_' + Date.now();
  setGenerating(true);
  hideError();
  el('outputArea').style.display = 'none';
  el('progressArea').style.display = '';

  let segments;

  if (processingMode === 'direct') {
    // ── Direkt-Modus: Text 1:1 als ein Segment, kein LLM ──────────────────
    setProgress(10, 'Text wird direkt gesprochen …', '');
    // Nur Sprecher 1 verwenden, Text als einzelnes Segment
    const sp = activeSpeakers[0] || speakers[0];
    segments = [{
      sprecher:            sp ? sp.id : 1,
      emotion_instruction: sp ? (sp.emotionInstruction || '') : '',
      text,
    }];
  } else {
    // ── LLM-Modus: Text transformieren ────────────────────────────────────
    setProgress(5, 'LLM verarbeitet Text …', '');
    const customSystemPrompt = getActivePromptText();
    try {
      segments = await callLLM({
        text, style, speakers: activeSpeakers, llmBackend,
        deepseekKey, deepseekModel, ollamaEndpoint, ollamaModel,
        customSystemPrompt,
      });
    } catch(e) {
      showError('LLM-Fehler: ' + e.message);
      setGenerating(false);
      return;
    }
  }

  // Step 2: TTS pro Segment
  setProgress(20, `TTS: 0 / ${segments.length} Segmente …`, '');
  let segmentFiles;
  try {
    segmentFiles = await runTTS(segments, activeSpeakers);
  } catch(e) {
    showError('TTS-Fehler: ' + e.message);
    setGenerating(false);
    return;
  }

  // Step 3: Audiomontage
  setProgress(90, 'Audiomontage (ffmpeg) …', '');
  let outputFile;
  try {
    outputFile = await mixAudio(segmentFiles);
  } catch(e) {
    showError('Montage-Fehler: ' + e.message);
    setGenerating(false);
    return;
  }

  // Fertig
  setProgress(100, 'Fertig!', `${segmentFiles.length} Segmente → ${outputFile}`);
  setGenerating(false);

  el('downloadLink').href = `api/download.php?file=${encodeURIComponent(outputFile)}`;
  el('downloadLink').download = outputFile.split('/').pop();
  updateOutputDirLink();
  el('outputArea').style.display = '';
}

// ─── LLM CALL ────────────────────────────────────────────────────────────────
async function callLLM({ text, style, speakers, llmBackend, deepseekKey, deepseekModel, ollamaEndpoint, ollamaModel, customSystemPrompt }) {
  const res = await fetch('api/run_llm.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      text, style, speakers,
      backend: llmBackend,
      deepseek_key: deepseekKey,
      deepseek_model: deepseekModel || 'deepseek-chat',
      ollama_endpoint: ollamaEndpoint,
      ollama_model: ollamaModel,
      custom_system_prompt: customSystemPrompt || '',
    }),
  });

  const data = await res.json();
  if (data.error) throw new Error(data.error);
  if (!Array.isArray(data.segments)) throw new Error('LLM lieferte kein gültiges Segment-Array');
  return data.segments;
}

// ─── TTS CALL ────────────────────────────────────────────────────────────────
async function runTTS(segments, activeSpeakers) {
  const res = await fetch('api/run_tts.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      segments,
      speakers: activeSpeakers,
      session_id: sessionId,
      settings: {
        python_bin:   settings.pythonBin,
        tts_script:   settings.ttsScript,
        model_custom: settings.modelCustom,
        model_base:   settings.modelBase,
        tokenizer:    settings.tokenizer,
        tmp_dir:      settings.tmpDir,
        language:     settings.language,
      },
    }),
  });

  // Poll progress
  pollTimer = setInterval(async () => {
    try {
      const prog = await fetch(`api/progress.php?session=${sessionId}&tmp_dir=${encodeURIComponent(settings.tmpDir)}`);
      const p    = await prog.json();
      if (p.current !== undefined) {
        const pct = 20 + Math.round((p.current / p.total) * 65);
        setProgress(pct, `TTS: ${p.current} / ${p.total} Segmente …`, p.detail || '');
      }
    } catch(e) {}
  }, 1500);

  const data = await res.json();
  clearInterval(pollTimer);
  pollTimer = null;

  if (data.error) throw new Error(data.error);
  return data.files;
}

// ─── MIX AUDIO ───────────────────────────────────────────────────────────────
async function mixAudio(segmentFiles) {
  const res = await fetch('api/mix_audio.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      segment_files: segmentFiles,
      session_id:    sessionId,
      use_intro:     checked('useIntro'),
      intro_file:    val('introFile'),
      use_atmosphere: checked('useAtmosphere'),
      atmosphere_file: val('atmosphereFile'),
      atmosphere_volume: parseInt(val('atmosphereVolume')) || 20,
      use_outro:     checked('useOutro'),
      outro_file:    val('outroFile'),
      output_dir:    settings.outputDir,
      tmp_dir:       settings.tmpDir,
    }),
  });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data.output_file;
}

// ─── PDF UPLOAD ───────────────────────────────────────────────────────────────
async function uploadPDF(file) {
  const formData = new FormData();
  formData.append('file', file);

  setProgress(0, 'Datei wird verarbeitet …', '');
  el('progressArea').style.display = '';
  el('btnGenerate').disabled = true;

  try {
    const res = await fetch('api/upload_pdf.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.error) { showError(data.error); return; }
    if (data.text)  {
      set('inputText', data.text);
      updateCharCount();
      saveFormState();
    }
  } catch(e) {
    showError('Upload-Fehler: ' + e.message);
  } finally {
    el('progressArea').style.display = 'none';
    el('btnGenerate').disabled = false;
  }
}

// ─── UI HELPERS ──────────────────────────────────────────────────────────────
function setGenerating(on) {
  const btn = el('btnGenerate');
  btn.disabled = on;
  btn.classList.toggle('running', on);
  btn.innerHTML = on
    ? '<span>■ LÄUFT …</span>'
    : '<span class="btn-icon-left">▶</span> GENERIERUNG STARTEN';
}

function setProgress(pct, status, detail) {
  el('progressBar').style.width = pct + '%';
  el('progressStatus').textContent = status;
  el('progressDetail').textContent = detail;
}

function showError(msg) {
  el('errorArea').style.display = '';
  el('errorMsg').textContent = msg;
}
function hideError() {
  el('errorArea').style.display = 'none';
}

function showModal(id, show) {
  el(id).style.display = show ? 'flex' : 'none';
}

function updateCharCount() {
  const n = val('inputText').length;
  el('charCount').textContent = n.toLocaleString('de') + ' Zeichen';
}

// ─── EVENT BINDING ───────────────────────────────────────────────────────────
function bindEvents() {
  // Header
  el('btnSettings').addEventListener('click', openSettings);
  el('btnCloseSettings').addEventListener('click', () => showModal('modalSettings', false));
  el('btnSaveSettings').addEventListener('click', saveSettings);
  el('modalSettings').addEventListener('click', e => {
    if (e.target === el('modalSettings')) showModal('modalSettings', false);
  });

  // Textverarbeitungs-Modus
  document.querySelectorAll('input[name="processingMode"]').forEach(r => {
    r.addEventListener('change', () => { toggleProcessingMode(); saveFormState(); });
  });

  // Prompt-Preset-Buttons
  document.querySelectorAll('.prompt-preset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activePreset = btn.dataset.preset;
      renderActivePreset();
      saveFormState();
    });
  });

  // Custom Prompt Text
  el('customPromptText').addEventListener('input', saveFormState);

  // Text
  el('inputText').addEventListener('input', () => { updateCharCount(); saveFormState(); });

  // PDF upload
  el('pdfUpload').addEventListener('change', e => {
    if (e.target.files[0]) uploadPDF(e.target.files[0]);
    e.target.value = '';
  });

  // Output style
  document.querySelectorAll('input[name="outputStyle"]').forEach(r => {
    r.addEventListener('change', () => {
      updateSpeakerCountFromStyle();
      saveFormState();
    });
  });

  // Audio env checkboxes
  ['useIntro', 'useAtmosphere', 'useOutro'].forEach(id => {
    el(id).addEventListener('change', () => { toggleEnvSelectors(); saveFormState(); });
  });

  // File selects
  ['introFile', 'atmosphereFile', 'outroFile'].forEach(id => {
    el(id).addEventListener('change', () => {
      const type = id.replace('File','').toLowerCase();
      localStorage.setItem(`qtts_file_${type}`, val(id));
      saveFormState();
    });
  });

  // Refresh buttons
  document.querySelectorAll('.btn-refresh').forEach(btn => {
    btn.addEventListener('click', () => loadFileList(btn.dataset.target));
  });

  // Volume
  el('atmosphereVolume').addEventListener('input', e => {
    el('atmosphereVolumeVal').textContent = e.target.value + '%';
    saveFormState();
  });

  // LLM backend
  document.querySelectorAll('input[name="llmBackend"]').forEach(r => {
    r.addEventListener('change', () => { toggleLLMConfig(); saveFormState(); });
  });

  // LLM fields
  ['deepseekKey','deepseekModel'].forEach(id => {
    el(id).addEventListener('input', saveFormState);
  });
  el('ollamaEndpoint').addEventListener('input', saveFormState);
  el('ollamaModel').addEventListener('change', saveFormState);

  // Ollama Modelle neu laden (auch bei Endpoint-Änderung nach kurzer Pause)
  el('btnRefreshOllamaModels').addEventListener('click', loadOllamaModels);
  let ollamaReloadTimer = null;
  el('ollamaEndpoint').addEventListener('input', () => {
    saveFormState();
    clearTimeout(ollamaReloadTimer);
    ollamaReloadTimer = setTimeout(loadOllamaModels, 800);
  });

  // Generate
  el('btnGenerate').addEventListener('click', startGeneration);

  // Hilfe
  el('btnHelp').addEventListener('click', () => { openHelp(); showModal('modalHelp', true); });
  el('btnCloseHelp').addEventListener('click',  () => showModal('modalHelp', false));
  el('btnCloseHelp2').addEventListener('click', () => showModal('modalHelp', false));
  el('modalHelp').addEventListener('click', e => {
    if (e.target === el('modalHelp')) showModal('modalHelp', false);
  });

  // Reset
  el('btnResetSettings').addEventListener('click', () => {
    if (!confirm('Alle Einstellungen und gespeicherten Formulardaten wirklich zurücksetzen?')) return;
    ['qtts_settings','qtts_form','qtts_speakers',
     'qtts_file_intro','qtts_file_atmosphere','qtts_file_outro'].forEach(k => localStorage.removeItem(k));
    location.reload();
  });

  // Ausgabeverzeichnis-Link
  updateOutputDirLink();
}

// ─── HILFE-MODAL ─────────────────────────────────────────────────────────────
function openHelp() {
  // Verzeichnispfade aus Settings in die Hilfetexte eintragen
  const fill = (id, val) => { const e = el(id); if (e) e.textContent = val; };
  fill('helpVoicesDir',  settings.voicesDir);
  fill('helpIntroDir',   settings.introDir);
  fill('helpOutroDir',   settings.outroDir);
  fill('helpAtmDir',     settings.atmosphereDir);
  fill('helpOutputDir',  settings.outputDir);
  const outLink = el('helpOpenOutputDir');
  if (outLink) {
    outLink.href = '#';
    outLink.onclick = (e) => { e.preventDefault(); openOutputFolder(); };
  }
}

// ─── AUSGABEVERZEICHNIS IN NEMO ÖFFNEN ───────────────────────────────────────
function updateOutputDirLink() {
  // Nichts zu setzen — der Button ruft openOutputFolder() on-click auf
}

async function openOutputFolder() {
  const dir = settings.outputDir;
  try {
    const res  = await fetch(`api/open_folder.php?dir=${encodeURIComponent(dir)}`);
    const data = await res.json();
    if (data.error) alert('Ordner konnte nicht geöffnet werden: ' + data.error);
  } catch(e) {
    alert('Fehler: ' + e.message);
  }
}

// ─── VOICE PREVIEW ───────────────────────────────────────────────────────────
async function previewSpeaker(idx, btnEl) {
  const sp = speakers[idx];
  if (!sp) return;

  const origText = btnEl.textContent;
  btnEl.textContent = '…';
  btnEl.disabled = true;

  const payload = {
    mode:      sp.mode === 'clone' ? 'clone' : 'custom',
    speaker:   sp.presetSpeaker || 'chelsie',
    ref_audio: sp.refAudioPath  || '',
    ref_text:  sp.refText       || '',
    settings: {
      python_bin:   settings.pythonBin,
      tts_script:   settings.ttsScript,
      model_custom: settings.modelCustom,
      model_base:   settings.modelBase,
      tokenizer:    settings.tokenizer,
      language:     settings.language,
    },
  };

  try {
    const res = await fetch('api/preview_voice.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({ error: 'Unbekannter Fehler' }));
      alert('Vorschau-Fehler: ' + (err.error || res.status));
      return;
    }

    const blob = await res.blob();
    const url  = URL.createObjectURL(blob);
    const audio = new Audio(url);
    audio.onended = () => URL.revokeObjectURL(url);
    audio.play();
  } catch(e) {
    alert('Vorschau-Fehler: ' + e.message);
  } finally {
    btnEl.textContent = origText;
    btnEl.disabled = false;
  }
}

// ─── UTILS ───────────────────────────────────────────────────────────────────
function el(id)          { return document.getElementById(id); }
function val(id)         { return el(id) ? el(id).value : ''; }
function set(id, v)      { if (el(id)) el(id).value = v; }
function checked(id)     { return el(id) ? el(id).checked : false; }
function setChecked(id, v){ if (el(id)) el(id).checked = v; }
function esc(s)          { return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function getRadioValue(name) {
  const r = document.querySelector(`input[name="${name}"]:checked`);
  return r ? r.value : '';
}
function setRadio(name, value) {
  const r = document.querySelector(`input[name="${name}"][value="${value}"]`);
  if (r) r.checked = true;
}
