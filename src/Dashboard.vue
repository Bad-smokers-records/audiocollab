<template>
    <div class="audiocollab-dashboard">
        <header class="ac-header">
            <div class="ac-cover">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
            </div>
            <div class="ac-header-text">
                <h2 class="ac-title">AudioCollab</h2>
                <p class="ac-subtitle">Le tue tracce e i commenti più recenti</p>
            </div>
            <a v-if="isAdmin" class="ac-settings-link" :href="settingsUrl">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Impostazioni
            </a>
        </header>

        <div v-if="loading" class="ac-loading">
            <span class="ac-spinner"></span>
            Caricamento...
        </div>

        <template v-else>
            <div class="ac-stats-row">
                <div class="ac-stat-tile">
                    <span class="ac-stat-value">{{ stats.totalTracks }}</span>
                    <span class="ac-stat-label">Tracce totali</span>
                </div>
                <template v-if="stats.statusBreakdown">
                    <div class="ac-stat-tile">
                        <span class="ac-stat-value">{{ stats.statusBreakdown.draft }}</span>
                        <span class="ac-stat-label">Bozza</span>
                    </div>
                    <div class="ac-stat-tile">
                        <span class="ac-stat-value">{{ stats.statusBreakdown.in_review }}</span>
                        <span class="ac-stat-label">In revisione</span>
                    </div>
                    <div class="ac-stat-tile">
                        <span class="ac-stat-value">{{ stats.statusBreakdown.approved }}</span>
                        <span class="ac-stat-label">Approvate</span>
                    </div>
                </template>
            </div>

            <div class="ac-dashboard-columns">
                <section class="ac-card">
                    <h3 class="ac-card-title">Tracce recenti</h3>
                    <div v-if="recentTracks.length === 0" class="ac-empty">
                        Nessuna traccia con attività recente.
                    </div>
                    <a
                        v-for="track in recentTracks"
                        :key="track.fileId"
                        class="ac-track-row"
                        :href="track.link"
                    >
                        <div class="ac-track-info">
                            <span class="ac-track-name">{{ track.name }}</span>
                            <span v-if="track.artist" class="ac-track-artist">{{ track.artist }}</span>
                        </div>
                        <span v-if="track.status" class="ac-status-pill" :class="'ac-status-pill-' + track.status">
                            {{ statusLabel(track.status) }}
                        </span>
                        <span class="ac-track-date">{{ formatDate(track.lastActivity) }}</span>
                    </a>
                </section>

                <section class="ac-card">
                    <h3 class="ac-card-title">Commenti recenti</h3>
                    <div v-if="recentComments.length === 0" class="ac-empty">
                        Nessun commento recente.
                    </div>
                    <a
                        v-for="comment in recentComments"
                        :key="comment.commentId"
                        class="ac-comment-row"
                        :href="comment.link"
                    >
                        <div class="ac-comment-top">
                            <span class="ac-comment-track">{{ comment.trackName }}</span>
                            <span class="ac-time-badge">{{ formatTime(comment.timestampSeconds) }}</span>
                        </div>
                        <p class="ac-comment-excerpt">
                            <strong>{{ comment.authorUid }}</strong>: {{ comment.excerpt }}
                        </p>
                        <span class="ac-comment-date">{{ formatDate(comment.createdAt) }}</span>
                    </a>
                </section>
            </div>
        </template>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'Dashboard',
    data() {
        return {
            loading: true,
            recentTracks: [],
            recentComments: [],
            stats: { totalTracks: 0, statusBreakdown: null },
            isAdmin: false,
        }
    },
    computed: {
        settingsUrl() {
            return generateUrl('/settings/admin/audiocollab')
        },
    },
    async mounted() {
        try {
            const response = await axios.get(generateUrl('/apps/audiocollab/api/dashboard'))
            this.recentTracks = response.data.recentTracks || []
            this.recentComments = response.data.recentComments || []
            this.stats = response.data.stats || { totalTracks: 0, statusBreakdown: null }
            this.isAdmin = !!response.data.isAdmin
        } catch (e) {
            console.error('AudioCollab: errore caricamento dashboard', e)
        } finally {
            this.loading = false
        }
    },
    methods: {
        statusLabel(status) {
            return { draft: 'Bozza', in_review: 'In revisione', approved: 'Approvato' }[status] || 'Bozza'
        },
        formatTime(seconds) {
            const s = Math.max(0, Math.round(seconds || 0))
            return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
        },
        formatDate(value) {
            if (!value) return ''
            const date = new Date(value.replace(' ', 'T') + 'Z')
            return date.toLocaleDateString('it-IT', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
        },
    },
}
</script>

<style scoped>
@import './shared-theme.css';

/* Stesso bug già corretto nel player: elementi con padding sotto il
   box-sizing di default (content-box) che ricevono una larghezza dal
   layout del genitore si rendono più larghi del previsto, e l'eccedenza
   viene tagliata in silenzio invece di andare a capo o restringersi. */
.audiocollab-dashboard,
.audiocollab-dashboard *,
.audiocollab-dashboard *::before,
.audiocollab-dashboard *::after {
    box-sizing: border-box;
}

.audiocollab-dashboard {
    width: 100%;
    max-width: 980px;
    margin: 0 auto;
    padding: 32px 14px 60px;
    box-sizing: border-box;
    color: var(--ac-text);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    /* Il contenitore che Nextcloud usa per le pagine standalone delle app
       (".app-audiocollab") è a altezza fissa con overflow-y:clip, non
       scroll: si aspetta che sia il contenuto dell'app stesso a scorrere.
       Senza questo, su schermi piccoli tutto sotto la prima schermata
       (inclusa l'intera colonna "Commenti recenti") è irraggiungibile. */
    height: 100%;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.ac-header {
    position: relative;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
    padding: 18px 20px;
    border-radius: var(--ac-radius);
    background:
        radial-gradient(circle at 85% 0%, rgba(255, 255, 255, 0.12), transparent 55%),
        linear-gradient(135deg, #0b1f4d 0%, #123a8a 55%, #1c5fd6 100%);
    color: #fff;
}

.ac-cover {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: var(--ac-radius-sm);
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ac-header-text {
    flex: 1;
    min-width: 0;
}

.ac-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
}

.ac-subtitle {
    margin: 2px 0 0 0;
    font-size: 13px;
    opacity: 0.8;
}

.ac-settings-link {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
}

.ac-settings-link:hover {
    background: rgba(255, 255, 255, 0.28);
}

.ac-stats-row {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.ac-stat-tile {
    flex: 1;
    min-width: 110px;
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius);
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    box-shadow: 0 1px 3px rgba(20, 30, 40, 0.06);
}

.ac-stat-value {
    font-size: 26px;
    font-weight: 700;
    color: var(--ac-text);
}

.ac-stat-label {
    font-size: 12px;
    color: var(--ac-text-dim);
}

.ac-dashboard-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    align-items: start;
}

.ac-card {
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius);
    padding: 16px;
    box-shadow: 0 1px 3px rgba(20, 30, 40, 0.06);
}

.ac-card-title {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--ac-text);
}

.ac-empty {
    color: var(--ac-text-faint);
    font-style: italic;
    font-size: 13px;
    padding: 8px 2px;
}

.ac-track-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 4px;
    border-bottom: 1px solid var(--ac-border);
    text-decoration: none;
    color: inherit;
}

.ac-track-row:last-child {
    border-bottom: none;
}

.ac-track-row:hover {
    background: var(--ac-bg);
}

.ac-track-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.ac-track-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--ac-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ac-track-artist {
    font-size: 11px;
    color: var(--ac-text-faint);
}

.ac-track-date {
    flex-shrink: 0;
    font-size: 11px;
    color: var(--ac-text-faint);
    white-space: nowrap;
}

.ac-status-pill {
    flex-shrink: 0;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    white-space: nowrap;
}

.ac-status-pill-draft {
    background: #f0f2f4;
    color: var(--ac-text-dim);
}

.ac-status-pill-in_review {
    background: #fef3e0;
    color: #c9820f;
}

.ac-status-pill-approved {
    background: var(--ac-status-resolved-bg);
    color: var(--ac-status-resolved-text);
}

.ac-comment-row {
    display: block;
    padding: 9px 4px;
    border-bottom: 1px solid var(--ac-border);
    text-decoration: none;
    color: inherit;
}

.ac-comment-row:last-child {
    border-bottom: none;
}

.ac-comment-row:hover {
    background: var(--ac-bg);
}

.ac-comment-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.ac-comment-track {
    font-size: 12px;
    font-weight: 600;
    color: var(--ac-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ac-time-badge {
    flex-shrink: 0;
    background: var(--ac-accent-soft);
    color: var(--ac-accent-dark);
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}

.ac-comment-excerpt {
    margin: 4px 0 2px 0;
    font-size: 12px;
    line-height: 1.4;
    color: var(--ac-text-dim);
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.ac-comment-date {
    font-size: 11px;
    color: var(--ac-text-faint);
}

@media (max-width: 700px) {
    .ac-dashboard-columns {
        grid-template-columns: 1fr;
    }
}
</style>
