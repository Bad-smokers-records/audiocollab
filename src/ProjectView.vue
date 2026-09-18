<template>
    <div class="ac-project-overlay" @click.self="close">
        <div class="ac-project-panel">
            <header class="ac-project-header">
                <div>
                    <h2 class="ac-project-title">{{ project.name || t('audiocollab', 'Project') }}</h2>
                    <p class="ac-project-subtitle">{{ t('audiocollab', 'Track loudness comparison') }}</p>
                </div>
                <button class="ac-project-close" @click="close" :title="t('audiocollab', 'Close')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </header>

            <div v-if="loading" class="ac-project-loading">{{ t('audiocollab', 'Loading tracks...') }}</div>

            <template v-else>
                <div class="ac-project-toolbar" v-if="features.loudnessMatching && hasAnyLoudness">
                    <button
                        type="button"
                        class="ac-toggle-switch"
                        :class="{ 'ac-toggle-switch-on': loudnessMatchEnabled }"
                        role="switch"
                        :aria-checked="loudnessMatchEnabled"
                        @click="loudnessMatchEnabled = !loudnessMatchEnabled"
                    >
                        <span class="ac-toggle-knob"></span>
                    </button>
                    <span class="ac-project-toolbar-label">
                        {{ t('audiocollab', 'Balance playback volume to the project average ({lufs} LUFS)', { lufs: averageLoudness.toFixed(1) }) }}
                    </span>
                </div>

                <div class="ac-track-list">
                    <div
                        v-for="(track, index) in tracks"
                        :key="track.fileId"
                        class="ac-track-row"
                        :class="{ 'ac-track-row-dragging': draggedIndex === index, 'ac-track-row-active': currentTrackFileId === track.fileId }"
                        draggable="true"
                        @dragstart="onDragStart(index)"
                        @dragover.prevent
                        @dragenter.prevent="onDragEnter(index)"
                        @drop="onDrop(index)"
                        @dragend="onDragEnd"
                    >
                        <span class="ac-track-handle" :title="t('audiocollab', 'Drag to reorder')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                        </span>
                        <div class="ac-track-reorder-buttons">
                            <button
                                type="button"
                                class="ac-track-reorder-btn"
                                :disabled="index === 0"
                                :title="t('audiocollab', 'Move up')"
                                @click="moveTrack(index, -1)"
                            >▲</button>
                            <button
                                type="button"
                                class="ac-track-reorder-btn"
                                :disabled="index === tracks.length - 1"
                                :title="t('audiocollab', 'Move down')"
                                @click="moveTrack(index, 1)"
                            >▼</button>
                        </div>
                        <span class="ac-track-number">{{ index + 1 }}</span>
                        <button
                            class="ac-track-play"
                            :disabled="!track.streamUrl"
                            :title="track.streamUrl ? t('audiocollab', 'Play') : t('audiocollab', 'Open the file in AudioCollab to generate the preview')"
                            @click="togglePlayTrack(track)"
                        >
                            <svg v-if="currentTrackFileId !== track.fileId || !isPlaying" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
                        </button>
                        <span class="ac-track-name">{{ track.name }}</span>
                        <div class="ac-track-meta">
                            <span v-if="features.trackStatus && track.status" class="ac-status-badge" :class="'ac-status-badge-' + track.status">
                                {{ statusLabel(track.status) }}
                            </span>
                            <template v-if="features.loudnessMatching">
                                <span v-if="track.integratedLoudness !== null" class="ac-loudness-badge" :class="loudnessBadgeClass(track)">
                                    {{ track.integratedLoudness.toFixed(1) }} LUFS
                                </span>
                                <span v-else class="ac-loudness-badge ac-loudness-badge-unknown">{{ t('audiocollab', 'N/A') }}</span>
                            </template>
                        </div>
                    </div>
                </div>

                <audio
                    ref="audioEl"
                    @ended="isPlaying = false"
                    @play="isPlaying = true"
                    @pause="isPlaying = false"
                ></audio>
            </template>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

export default {
    name: 'ProjectView',
    props: {
        folderId: { type: Number, required: true },
    },
    data() {
        return {
            loading: true,
            features: {
                loudnessMatching: true,
                commentNotifications: true,
                trackStatus: true,
            },
            project: {},
            tracks: [],
            currentTrackFileId: null,
            isPlaying: false,
            loudnessMatchEnabled: false,
            draggedIndex: null,
            dragStartOrder: null,
            dropHandled: false,
            audioContext: null,
            gainNode: null,
        }
    },
    computed: {
        tracksWithLoudness() {
            return this.tracks.filter(t => t.integratedLoudness !== null)
        },
        hasAnyLoudness() {
            return this.tracksWithLoudness.length > 0
        },
        averageLoudness() {
            const list = this.tracksWithLoudness
            if (!list.length) return 0
            return list.reduce((sum, t) => sum + t.integratedLoudness, 0) / list.length
        },
    },
    watch: {
        loudnessMatchEnabled() {
            this.applyGain()
        },
    },
    async mounted() {
        document.addEventListener('keydown', this.onKeydown)
        this.fetchFeatures()
        await this.loadProject()
    },
    beforeDestroy() {
        document.removeEventListener('keydown', this.onKeydown)
        if (this.audioContext) {
            this.audioContext.close()
        }
    },
    methods: {
        t,
        async fetchFeatures() {
            try {
                const response = await axios.get(generateUrl('/apps/audiocollab/api/settings'))
                this.features = { ...this.features, ...response.data }
            } catch (e) {
                console.error('AudioCollab: errore caricamento impostazioni', e)
            }
        },
        async loadProject() {
            try {
                const response = await axios.get(generateUrl('/apps/audiocollab/api/project'), {
                    params: { folderId: this.folderId },
                })
                this.project = response.data.project
                this.tracks = response.data.tracks
            } catch (e) {
                console.error('AudioCollab: errore caricamento progetto', e)
            } finally {
                this.loading = false
            }
        },
        loudnessBadgeClass(track) {
            if (!this.hasAnyLoudness || track.integratedLoudness === null) return ''
            const diff = Math.abs(track.integratedLoudness - this.averageLoudness)
            if (diff <= 1) return 'ac-loudness-badge-ok'
            if (diff <= 3) return 'ac-loudness-badge-warn'
            return 'ac-loudness-badge-off'
        },
        statusLabel(status) {
            return {
                draft: t('audiocollab', 'Draft'),
                in_review: t('audiocollab', 'In review'),
                approved: t('audiocollab', 'Approved'),
            }[status] || t('audiocollab', 'Draft')
        },
        ensureAudioGraph() {
            if (this.audioContext) {
                if (this.audioContext.state === 'suspended') this.audioContext.resume()
                return
            }
            const AudioContextClass = window.AudioContext || window.webkitAudioContext
            if (!AudioContextClass) return
            this.audioContext = new AudioContextClass()
            const source = this.audioContext.createMediaElementSource(this.$refs.audioEl)
            this.gainNode = this.audioContext.createGain()
            source.connect(this.gainNode)
            this.gainNode.connect(this.audioContext.destination)
        },
        // Media di riferimento per il gain: esclude la traccia passata (di
        // solito quella in riproduzione), come fa il player singolo
        // (computeSuggestedGain lato server confronta contro le "sorelle",
        // mai contro se stessa). Includerla nella propria media di
        // riferimento dimezzerebbe la correzione in progetti con poche tracce.
        averageLoudnessExcluding(fileId) {
            const list = this.tracksWithLoudness.filter(t => t.fileId !== fileId)
            if (!list.length) return null
            return list.reduce((sum, t) => sum + t.integratedLoudness, 0) / list.length
        },
        applyGain() {
            if (!this.gainNode) return
            const track = this.tracks.find(t => t.fileId === this.currentTrackFileId)
            const target = track ? this.averageLoudnessExcluding(track.fileId) : null
            const canApply = this.loudnessMatchEnabled && track && track.integratedLoudness !== null && target !== null
            const gainDb = canApply ? (target - track.integratedLoudness) : 0
            this.gainNode.gain.value = Math.pow(10, gainDb / 20)
        },
        togglePlayTrack(track) {
            if (!track.streamUrl) return
            const el = this.$refs.audioEl
            this.ensureAudioGraph()
            if (this.currentTrackFileId === track.fileId) {
                if (this.isPlaying) {
                    el.pause()
                } else {
                    el.play()
                }
                return
            }
            this.currentTrackFileId = track.fileId
            el.src = track.streamUrl
            this.applyGain()
            el.play()
        },
        onDragStart(index) {
            this.draggedIndex = index
            this.dragStartOrder = this.tracks.map(t => t.fileId)
            this.dropHandled = false
        },
        onDragEnter(index) {
            if (this.draggedIndex === null || this.draggedIndex === index) return
            const moved = this.tracks.splice(this.draggedIndex, 1)[0]
            this.tracks.splice(index, 0, moved)
            this.draggedIndex = index
        },
        async onDrop() {
            this.dropHandled = true
            this.draggedIndex = null
            await this.persistOrder()
        },
        onDragEnd() {
            // Se il drag finisce senza un drop su una riga valida (es.
            // rilasciato fuori dalla lista), l'ordine locale è già stato
            // cambiato in anteprima da onDragEnter ma mai salvato: lo
            // ripristiniamo com'era prima di iniziare a trascinare, invece
            // di lasciare la vista disallineata rispetto al server.
            if (!this.dropHandled && this.dragStartOrder) {
                const byId = new Map(this.tracks.map(t => [t.fileId, t]))
                this.tracks = this.dragStartOrder.map(fileId => byId.get(fileId)).filter(Boolean)
            }
            this.draggedIndex = null
            this.dragStartOrder = null
        },
        onKeydown(e) {
            if (e.key === 'Escape') this.close()
        },
        close() {
            this.$emit('close')
        },
        // Alternativa al trascinamento per il riordino, pensata per il
        // tocco: il drag-and-drop nativo HTML5 (draggable/dragstart/drop)
        // non genera eventi da input touch su iOS Safari, quindi su
        // iPhone/iPad il trascinamento della riga non ha alcun effetto.
        // Le frecce chiamano la stessa identica logica di persistenza.
        moveTrack(index, direction) {
            const target = index + direction
            if (target < 0 || target >= this.tracks.length) return
            const moved = this.tracks.splice(index, 1)[0]
            this.tracks.splice(target, 0, moved)
            this.persistOrder()
        },
        async persistOrder() {
            try {
                await axios.post(generateUrl('/apps/audiocollab/api/project/reorder'), {
                    folderId: this.folderId,
                    order: this.tracks.map(t => t.fileId),
                })
            } catch (e) {
                console.error('AudioCollab: errore salvataggio ordine', e)
            }
        },
    },
}
</script>

<style scoped>
@import './shared-theme.css';

.ac-project-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10, 16, 30, 0.6);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ac-project-panel {
    width: min(560px, 92vw);
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    background: var(--ac-surface);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.ac-project-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    background: linear-gradient(135deg, #0b1f4d 0%, #123a8a 55%, #1c5fd6 100%);
    color: #fff;
}

.ac-project-title {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
}

.ac-project-subtitle {
    margin: 2px 0 0 0;
    font-size: 12px;
    opacity: 0.8;
}

.ac-project-close {
    border: none;
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    width: 32px;
    height: 32px;
    min-width: 0;
    min-height: 0;
    padding: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.ac-project-loading {
    padding: 40px;
    text-align: center;
    color: var(--ac-text-dim);
}

.ac-project-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    border-bottom: 1px solid var(--ac-border);
}

.ac-project-toolbar-label {
    font-size: 12px;
    color: var(--ac-text);
    font-weight: 600;
}

.ac-toggle-switch {
    flex-shrink: 0;
    position: relative;
    width: 34px;
    height: 20px;
    min-width: 0;
    min-height: 0;
    padding: 0;
    border: none;
    border-radius: 999px;
    background: var(--ac-border);
    cursor: pointer;
    transition: background 0.15s ease;
}

.ac-toggle-switch.ac-toggle-switch-on {
    background: var(--ac-accent) !important;
}

.ac-toggle-knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 2px rgba(20, 30, 40, 0.3);
    transition: transform 0.15s ease;
}

.ac-toggle-switch-on .ac-toggle-knob {
    transform: translateX(14px);
}

.ac-track-list {
    overflow-y: auto;
    padding: 8px 12px 16px;
}

.ac-track-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 8px;
    border-radius: 10px;
    cursor: grab;
}

.ac-track-row:hover {
    background: var(--ac-bg);
}

.ac-track-row-dragging {
    opacity: 0.4;
}

.ac-track-row-active {
    background: var(--ac-accent-soft);
}

.ac-track-handle {
    flex-shrink: 0;
    color: var(--ac-text-faint);
    display: flex;
}

.ac-track-reorder-buttons {
    display: none;
    flex-direction: column;
    flex-shrink: 0;
}

.ac-track-reorder-btn {
    width: 22px;
    height: 18px;
    min-width: 0;
    min-height: 0;
    padding: 0;
    border: none;
    background: transparent;
    color: var(--ac-text-faint);
    font-size: 9px;
    line-height: 1;
    cursor: pointer;
}

.ac-track-reorder-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.ac-track-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.ac-track-number {
    flex-shrink: 0;
    width: 20px;
    font-size: 12px;
    font-weight: 700;
    color: var(--ac-text-faint);
    text-align: right;
}

.ac-track-play {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    min-width: 0;
    min-height: 0;
    padding: 0;
    border-radius: 50%;
    border: none;
    background: var(--ac-accent);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.ac-track-play:disabled {
    background: #d9e3e8;
    cursor: not-allowed;
}

.ac-track-name {
    flex: 1;
    min-width: 0;
    font-size: 13px;
    color: var(--ac-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ac-loudness-badge {
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
}

.ac-loudness-badge-ok {
    background: #e5f7ec;
    color: #2ea364;
}

.ac-loudness-badge-warn {
    background: #fef3e0;
    color: #c9820f;
}

.ac-loudness-badge-off {
    background: #fdeaec;
    color: #d1435c;
}

.ac-loudness-badge-unknown {
    background: #f0f2f4;
    color: #9aa3ad;
}

.ac-status-badge {
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
}

.ac-status-badge-draft {
    background: #f0f2f4;
    color: #6b7480;
}

.ac-status-badge-in_review {
    background: #fef3e0;
    color: #c9820f;
}

.ac-status-badge-approved {
    background: #e5f7ec;
    color: #2ea364;
}

@media (max-width: 480px) {
    /* Il nome traccia si riduceva a pochi caratteri visibili: stato e
       loudness vanno su una riga propria sotto, lasciando al nome tutta
       la larghezza rimasta dopo maniglia/numero/play. */
    .ac-track-handle {
        display: none;
    }

    .ac-track-reorder-buttons {
        display: flex;
    }

    .ac-track-row {
        flex-wrap: wrap;
        cursor: default;
    }

    .ac-track-meta {
        order: 1;
        flex-basis: 100%;
        justify-content: flex-end;
        margin-top: 2px;
    }
}
</style>
