# AudioCollab

App per Nextcloud per la revisione collaborativa di mix audio in studio: player con waveform, commenti a timestamp, versioning automatico, confronto e bilanciamento del volume tra le tracce di un progetto, simulazione dell'ascolto su Spotify/Apple Music/YouTube Music, stato di revisione per traccia e notifiche per i nuovi commenti.

## Funzionalità

- **Player con waveform e commenti a timestamp**, con risposte, modifica ed eliminazione.
- **Versioning automatico**, sincronizzato con la cronologia file nativa di Nextcloud: ogni sovrascrittura di una traccia genera una nuova versione tracciata, senza duplicare lavoro.
- **Confronto e bilanciamento del volume (loudness matching)** tra le tracce di uno stesso progetto (cartella), sia nel player singolo sia nella vista di confronto dedicata.
- **Simulazione dell'ascolto** su Spotify / Apple Music / YouTube Music, basata sui target LUFS reali di ciascuna piattaforma.
- **Stato di revisione per traccia** (bozza / in revisione / approvato), gestibile da proprietario e collaboratori con permesso di scrittura.
- **Notifiche native Nextcloud** per nuovi commenti e risposte, con link diretto al punto esatto del commento nel player.
- **Dashboard** con tracce e commenti recenti, statistiche rapide e accesso alle impostazioni per gli amministratori.
- **Pannello di amministrazione** per abilitare/disabilitare singolarmente loudness matching, notifiche commenti e stato di revisione.

## Requisiti

- Nextcloud 27–34.
- PHP 8.1+.
- **Un microservizio esterno (`audiotools`) per la transcodifica e l'analisi audio** — vedi sotto. Senza questo componente l'app si installa e si avvia, ma le funzionalità principali (anteprima audio, waveform, loudness matching, simulazione piattaforme) non funzionano.

## Il microservizio `audiotools`

AudioCollab delega a un piccolo servizio Node.js/ffmpeg esterno (non incluso nel pacchetto dell'app) tutto il lavoro pesante: transcodifica, estrazione waveform, analisi loudness (LUFS/LRA/True Peak) e metadati. Gira come container separato, tipicamente su Docker, e comunica con l'app via HTTP su una rete interna/locale — **non deve essere esposto pubblicamente**.

### Perché un servizio separato

ffmpeg e l'elaborazione audio sono operazioni pesanti (CPU, memoria, tempo) che non è sensato eseguire dentro il processo PHP di Nextcloud. Isolarle in un container dedicato permette di scalarle o spostarle indipendentemente dal resto dell'istanza Nextcloud.

### Setup

1. Nella cartella del microservizio (fuori dalla cartella `apps/` di Nextcloud) servono `Dockerfile`, `package.json` e `src/server.js` — quest'ultimo è incluso in questo repository sotto `src/server.js` come riferimento/sorgente di verità: copialo nel contesto di build del container prima di ogni aggiornamento.
2. Build e avvio:
   ```bash
   docker build -t audiotools .
   docker run -d --name audiotools --restart unless-stopped -p 127.0.0.1:3100:3100 audiotools
   ```
   La porta va esposta solo su `127.0.0.1` (o su una rete interna raggiungibile dal server Nextcloud), mai pubblicamente: il servizio non fa autenticazione.
3. Verifica che risponda:
   ```bash
   curl http://localhost:3100/health
   ```
4. In **Impostazioni → Amministrazione → AudioCollab** (o via `occ config:app:set`), configura se necessario:
   - `ffmpeg_service_url` — URL del servizio (default `http://localhost:3100`, va cambiato se il container gira su un altro host/porta).
   - `cache_base_path` — percorso su disco dove AudioCollab tiene la cache di mp3/waveform generati.

   ```bash
   occ config:app:set audiocollab ffmpeg_service_url --value=http://host:porta
   occ config:app:set audiocollab cache_base_path --value=/percorso/cache
   ```

### Endpoint esposti dal servizio

- `POST /analyze-all` — endpoint principale: un solo upload del file, il container fa transcodifica (se serve), estrazione waveform, analisi loudness e metadati in un'unica chiamata.
- `POST /metadata` — estrazione rapida di artista/titolo (usato come fallback per versioni create prima dell'introduzione della cache metadati).
- `GET /health` — controllo di stato.

## Licenza

AGPL-3.0-or-later, coerente con Nextcloud stesso.
