*[Leggi questo in italiano](README.it.md)*

# AudioCollab

Nextcloud app for collaborative studio mix review: a player with waveform and timestamped comments, automatic versioning, loudness comparison and matching between the tracks of a project, listening simulation on Spotify/Apple Music/YouTube Music, per-track review status, and notifications for new comments.

## Features

- **Player with waveform and timestamped comments**, with replies, editing and deletion.
- **Automatic versioning**, synced with Nextcloud's native file history: every overwrite of a track creates a new tracked version, without duplicating work.
- **Loudness comparison and matching** between the tracks of the same project (folder), both in the single-track player and in a dedicated comparison view.
- **Listening simulation** on Spotify / Apple Music / YouTube Music, based on each platform's real LUFS targets.
- **Per-track review status** (draft / in review / approved), manageable by the owner and by collaborators with write access.
- **Native Nextcloud notifications** for new comments and replies, with a direct link to the exact point of the comment in the player.
- **Dashboard** with recent tracks and comments, quick stats, and settings access for admins.
- **Admin panel** to individually enable/disable loudness matching, comment notifications, and review status.

## Requirements

- Nextcloud 27–35.
- PHP 8.1+ (8.3+ when running on Nextcloud 35, which requires it as a minimum).
- **An external microservice (`audiotools`) for audio transcoding and analysis** — see below. Without this component the app installs and starts, but its core features (audio preview, waveform, loudness matching, platform simulation) don't work.

## The `audiotools` microservice

AudioCollab delegates all the heavy lifting — transcoding, waveform extraction, loudness analysis (LUFS/LRA/True Peak) and metadata — to a small external Node.js/ffmpeg service (not bundled with the app package). It runs as a separate container, typically on Docker, and talks to the app over HTTP on an internal/local network — **it must never be exposed publicly**.

### Why a separate service

ffmpeg and audio processing are heavy operations (CPU, memory, time) that don't belong inside Nextcloud's PHP process. Isolating them in a dedicated container lets you scale or relocate them independently of the rest of the Nextcloud instance.

### Setup

The microservice's `Dockerfile` and `docker-compose.yml` are included in this repository under `docker/audiotools/`, and build directly from `src/server.js` (no manual copy to keep in sync).

1. Build and start, from the **repository root** (the build context needs to include `src/`):
   ```bash
   docker compose -f docker/audiotools/docker-compose.yml up -d --build
   ```
   or, without compose:
   ```bash
   docker build -f docker/audiotools/Dockerfile -t audiotools .
   docker run -d --name audiotools --restart unless-stopped -p 127.0.0.1:3100:3100 audiotools
   ```
   The port should only be exposed on `127.0.0.1` (or an internal network reachable by the Nextcloud server), never publicly: the service has no authentication.
2. Check that it responds:
   ```bash
   curl http://localhost:3100/health
   ```
3. In **Settings → Administration → AudioCollab** (or via `occ config:app:set`), configure if needed:
   - `ffmpeg_service_url` — the service's URL (default `http://localhost:3100`, change it if the container runs on a different host/port).
   - `cache_base_path` — the disk path where AudioCollab keeps its generated mp3/waveform cache. Optional: if unset, it defaults to a path under Nextcloud's own data directory (`<datadirectory>/appdata_<instanceid>/audiocollab_cache`), so it works with no configuration on any installation. Only set this if you want the cache on a different disk/volume than Nextcloud's own data.

   ```bash
   occ config:app:set audiocollab ffmpeg_service_url --value=http://host:port
   occ config:app:set audiocollab cache_base_path --value=/path/to/cache
   ```

### Endpoints exposed by the service

- `POST /analyze-all` — main endpoint: a single file upload, and the container runs transcoding (if needed), waveform extraction, loudness analysis and metadata extraction in one call.
- `POST /metadata` — quick artist/title extraction (used as a fallback for versions created before the metadata cache was introduced).
- `GET /health` — health check.

## License

AGPL-3.0-or-later, consistent with Nextcloud itself.
