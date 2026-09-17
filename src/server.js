const express = require('express');
const multer = require('multer');
const { execFile, spawn } = require('child_process');
const fs = require('fs');
const os = require('os');

const app = express();
const upload = multer({ dest: os.tmpdir() });

// Nuovo: estrae metadati (artista/titolo) con ffprobe
app.post('/metadata', upload.single('file'), (req, res) => {
  const inputPath = req.file.path;
  runFfprobe(inputPath)
    .then((probe) => res.json(probe.metadata))
    .catch(() => res.json({ artist: null, title: null }))
    .finally(() => fs.unlink(inputPath, () => {}));
});

// --- helper condivisi, tutti operano sullo stesso file locale gia' caricato una volta sola ---

function runFfprobe(inputPath) {
  return new Promise((resolve, reject) => {
    execFile('ffprobe', ['-v', 'quiet', '-print_format', 'json', '-show_format', '-show_streams', inputPath], (err, stdout) => {
      if (err) return reject(err);
      try {
        const data = JSON.parse(stdout);
        const audioStream = (data.streams || []).find((s) => s.codec_type === 'audio') || {};
        const tags = data.format?.tags || {};
        // i tag possono avere case diverso (Artist, ARTIST, artist)
        const findTag = (obj, name) => {
          const key = Object.keys(obj).find((k) => k.toLowerCase() === name);
          return key ? obj[key] : null;
        };
        const rawBitRate = audioStream.bit_rate || data.format?.bit_rate;
        const bitRate = parseInt(rawBitRate, 10);
        resolve({
          codecName: audioStream.codec_name || null,
          bitRate: Number.isFinite(bitRate) ? bitRate : null,
          metadata: {
            artist: findTag(tags, 'artist'),
            title: findTag(tags, 'title'),
          },
        });
      } catch (e) {
        reject(e);
      }
    });
  });
}

function runTranscode(inputPath, outputPath) {
  return new Promise((resolve, reject) => {
    execFile('ffmpeg', ['-y', '-i', inputPath, '-codec:a', 'libmp3lame', '-b:a', '320k', outputPath], (err) => {
      if (err) return reject(err);
      resolve();
    });
  });
}

function runWaveform(inputPath) {
  const targetPoints = 1000;
  return new Promise((resolve, reject) => {
    const ff = spawn('ffmpeg', ['-i', inputPath, '-f', 's16le', '-ac', '1', '-ar', '8000', '-']);
    const chunks = [];
    ff.stdout.on('data', (chunk) => chunks.push(chunk));
    ff.stderr.on('data', () => {});
    ff.on('close', (code) => {
      if (code !== 0) return reject(new Error('waveform extraction failed'));
      const buffer = Buffer.concat(chunks);
      const sampleCount = buffer.length / 2;
      const samplesPerPoint = Math.max(1, Math.floor(sampleCount / targetPoints));
      const peaks = [];
      for (let i = 0; i < sampleCount; i += samplesPerPoint) {
        let min = 0, max = 0;
        const end = Math.min(i + samplesPerPoint, sampleCount);
        for (let j = i; j < end; j++) {
          const sample = buffer.readInt16LE(j * 2);
          if (sample > max) max = sample;
          if (sample < min) min = sample;
        }
        peaks.push([min / 32768, max / 32768]);
      }
      resolve({ peaks, sampleRate: 8000, samplesPerPoint });
    });
  });
}

function runLoudness(inputPath) {
  return new Promise((resolve, reject) => {
    const ff = spawn('ffmpeg', ['-i', inputPath, '-af', 'loudnorm=print_format=json', '-f', 'null', '-']);
    let stderrData = '';
    ff.stderr.on('data', (chunk) => { stderrData += chunk.toString(); });
    ff.on('close', () => {
      const jsonMatch = stderrData.match(/\{[\s\S]*?\}/);
      if (!jsonMatch) return reject(new Error('loudness analysis failed'));
      try {
        const stats = JSON.parse(jsonMatch[0]);
        resolve({
          integratedLoudness: parseFloat(stats.input_i),
          loudnessRange: parseFloat(stats.input_lra),
          truePeak: parseFloat(stats.input_tp),
        });
      } catch (e) {
        reject(e);
      }
    });
  });
}

// Sotto questa soglia la traccia mp3 originale non e' abbastanza buona per lo
// streaming diretto e va ricodificata; sopra, viene riusata cosi' com'e'.
const MIN_BITRATE_FOR_SKIP_TRANSCODE = 256000; // 256 kbps

// Endpoint unico: un solo upload del file dal lato PHP, il container fa tutte
// le analisi (formato/metadati, transcodifica se serve, waveform, loudness)
// sullo stesso file gia' presente localmente, invece di richiedere 4-5
// richieste HTTP separate che ricaricherebbero il file ogni volta.
app.post('/analyze-all', upload.single('file'), async (req, res) => {
  const inputPath = req.file.path;
  const skipLoudness = req.body.skip_loudness === '1';
  let transcodedPath = null;

  try {
    const probe = await runFfprobe(inputPath);
    const needsTranscode = !(
      probe.codecName === 'mp3'
      && probe.bitRate
      && probe.bitRate >= MIN_BITRATE_FOR_SKIP_TRANSCODE
    );

    let mp3Base64 = null;
    if (needsTranscode) {
      transcodedPath = inputPath + '.analyzeall.mp3';
      await runTranscode(inputPath, transcodedPath);
      mp3Base64 = fs.readFileSync(transcodedPath).toString('base64');
    }

    const [waveform, loudness] = await Promise.all([
      runWaveform(inputPath),
      skipLoudness ? Promise.resolve(null) : runLoudness(inputPath),
    ]);

    res.json({
      transcoded: needsTranscode,
      sourceCodec: probe.codecName,
      sourceBitrateKbps: probe.bitRate ? Math.round(probe.bitRate / 1000) : null,
      mp3Base64,
      waveform,
      metadata: probe.metadata,
      loudness,
    });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: 'analyze-all failed' });
  } finally {
    fs.unlink(inputPath, () => {});
    if (transcodedPath) {
      fs.unlink(transcodedPath, () => {});
    }
  }
});

app.get('/health', (req, res) => res.json({ status: 'ok' }));

const PORT = 3100;
app.listen(PORT, () => console.log(`audiotools service listening on port ${PORT}`));
