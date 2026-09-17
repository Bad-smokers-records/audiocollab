<template>
    <div class="audiocollab-player">
        <div v-if="loading" class="ac-loading">
            <span class="ac-spinner"></span>
            {{ loadingMessage }}
        </div>

        <template v-else>
            <header class="ac-header">
                <div class="ac-cover">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                </div>
                <div v-if="!editingMetadata" class="ac-header-display">
                    <div class="ac-header-text">
                        <div class="ac-title-row">
                            <h2 class="ac-title">{{ fileName }}</h2>
                            <div class="ac-version-switcher">
                                <button class="ac-version-pill ac-version-pill-btn" @click="showVersionMenu = !showVersionMenu">
                                    v{{ version.number || 1 }}<span v-if="!version.isLatest"> (storica)</span>
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div v-if="showVersionMenu" class="ac-version-menu">
                                    <button
                                        v-for="v in versionsDesc"
                                        :key="v.id"
                                        class="ac-version-menu-item"
                                        :class="{ 'ac-version-menu-item-active': v.id === version.id }"
                                        @click="switchVersion(v.id)"
                                    >
                                        <span class="ac-version-menu-number">v{{ v.number }}</span>
                                        <span class="ac-version-menu-meta">{{ v.uploadedBy }} · {{ formatDate(v.uploadedAt) }}</span>
                                        <span class="ac-version-menu-meta" v-if="v.nativeRevisionId">rev. Nextcloud: {{ formatUnixDate(v.nativeRevisionId) }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="ac-subtitle" v-if="artist">{{ artist }}</p>
                        <p class="ac-file-meta">
                            {{ format }}<span v-if="sizeLabel"> · {{ sizeLabel }}</span><span v-if="version.uploadedAt"> · caricato il {{ formatDate(version.uploadedAt) }}</span>
                        </p>
                    </div>
                    <div class="ac-header-side">
                        <div class="ac-status-switcher" v-if="features.trackStatus && version.status">
                            <button
                                class="ac-status-pill"
                                :class="'ac-status-pill-' + version.status"
                                @click="canManageStatus && (showStatusMenu = !showStatusMenu)"
                            >
                                {{ statusLabel(version.status) }}
                                <svg v-if="canManageStatus" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div v-if="showStatusMenu" class="ac-status-menu">
                                <button
                                    v-for="s in statusOptions"
                                    :key="s"
                                    class="ac-status-menu-item"
                                    :class="{ 'ac-status-menu-item-active': s === version.status }"
                                    @click="changeStatus(s)"
                                >
                                    <span class="ac-status-dot" :class="'ac-status-pill-' + s"></span>
                                    {{ statusLabel(s) }}
                                </button>
                            </div>
                        </div>
                        <span class="ac-quality-badge" :class="{ 'ac-quality-badge-lossless': isLossless }">
                            {{ qualityLabel }}
                        </span>
                        <button v-if="isOwner" class="ac-icon-btn" @click="startEditingMetadata" title="Modifica artista e titolo">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                        </button>
                    </div>
                </div>
                <div v-else class="ac-header-edit">
                    <input v-model="editArtist" class="ac-input" placeholder="Artista" />
                    <input v-model="editTitle" class="ac-input" placeholder="Titolo" />
                    <div class="ac-header-edit-actions">
                        <button class="ac-btn ac-btn-ghost" @click="cancelEditingMetadata">Annulla</button>
                        <button class="ac-btn ac-btn-primary" @click="saveMetadata">Salva</button>
                    </div>
                </div>
            </header>

            <nav class="ac-tabbar">
                <button class="ac-tab" :class="{ 'ac-tab-active': activeTab === 'overview' }" @click="activeTab = 'overview'">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 12h3l2-7 4 14 3-10 2 3h4"/></svg>
                    Panoramica
                </button>
                <button class="ac-tab" :class="{ 'ac-tab-active': activeTab === 'comments' }" @click="activeTab = 'comments'">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.4A8.5 8.5 0 1 1 21 11.5Z"/></svg>
                    Commenti
                    <span v-if="comments.length" class="ac-tab-count">{{ comments.length }}</span>
                </button>
                <button class="ac-tab" :class="{ 'ac-tab-active': activeTab === 'versions' }" @click="activeTab = 'versions'">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 3"/></svg>
                    Versioni
                </button>
            </nav>

            <div class="ac-tab-content" :class="{ 'ac-tab-content-row': activeTab === 'overview' }">
            <div class="ac-waveform-card" v-if="activeTab === 'overview'">
                <div class="ac-waveform-container" @click="onWaveformClick" ref="waveform">
                    <svg :viewBox="`0 0 ${displayPeaks.length * 3} 100`" preserveAspectRatio="none" class="ac-waveform-svg">
                        <line x1="0" y1="50" :x2="displayPeaks.length * 3" y2="50" class="ac-center-line" />
                        <template v-for="(peak, i) in displayPeaks">
                            <rect
                                :key="'t' + i"
                                :x="i * 3"
                                :y="50 - peak.top * 46"
                                width="2"
                                rx="1"
                                :height="Math.max(peak.top * 46, 1.5)"
                                :class="i / displayPeaks.length <= playheadRatio ? 'ac-bar-played' : 'ac-bar'"
                            />
                            <rect
                                :key="'b' + i"
                                :x="i * 3"
                                y="50"
                                width="2"
                                rx="1"
                                :height="Math.max(peak.bottom * 46, 1.5)"
                                :class="i / displayPeaks.length <= playheadRatio ? 'ac-bar-played' : 'ac-bar'"
                            />
                        </template>
                    </svg>
                    <div class="ac-playhead" :style="{ left: playheadPercent + '%' }"></div>
                    <div
                        v-for="comment in comments"
                        :key="'marker-' + comment.id"
                        class="ac-comment-tick"
                        :style="{ left: (comment.timestamp_seconds / duration * 100) + '%', background: colorFor(comment.author_uid) }"
                        :title="comment.author_uid + ': ' + comment.body"
                        @click.stop="seekTo(comment.timestamp_seconds)"
                    ></div>
                </div>

                <div class="ac-transport">
                    <button class="ac-play-btn" @click="togglePlay" :title="isPlaying ? 'Pausa' : 'Riproduci'">
                        <svg v-if="!isPlaying" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
                    </button>
                    <button class="ac-skip-btn" @click="skip(-10)" title="Indietro 10s">
                        <svg width="23" height="23" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.5 6L6 12l6.5 6z"/>
                            <path d="M19 6l-6.5 6 6.5 6z"/>
                        </svg>
                        <span class="ac-skip-label">10s</span>
                    </button>
                    <button class="ac-skip-btn" @click="skip(10)" title="Avanti 10s">
                        <svg width="23" height="23" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.5 6L18 12l-6.5 6z"/>
                            <path d="M5 6l6.5 6L5 18z"/>
                        </svg>
                        <span class="ac-skip-label">10s</span>
                    </button>
                    <span class="ac-time-display">{{ formatTime(currentTime) }} / {{ formatTime(duration) }}</span>
                    <input
                        type="range"
                        class="ac-seek-range"
                        min="0"
                        :max="duration || 0"
                        step="0.01"
                        :value="seekDisplayValue"
                        @mousedown="onSeekStart"
                        @touchstart.stop="onSeekStart"
                        @touchmove.stop
                        @input="onSeekInput"
                        @mouseup="onSeekEnd"
                        @touchend.stop="onSeekEnd"
                        @change="onSeekEnd"
                    />
                    <div class="ac-volume">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 10v4h4l5 5V5L7 10H3z"/></svg>
                        <input
                            type="range"
                            class="ac-volume-range"
                            min="0"
                            max="1"
                            step="0.01"
                            v-model.number="volume"
                            @touchstart.stop
                            @touchmove.stop
                            @touchend.stop
                        />
                    </div>
                </div>

                <div class="ac-loudness-row" v-if="features.loudnessMatching && loudnessMatch">
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
                    <div class="ac-loudness-text">
                        <span class="ac-loudness-label">Bilancia il volume con le altre tracce</span>
                        <span class="ac-loudness-meta">
                            {{ version.integratedLoudness.toFixed(1) }} LUFS
                            <span v-if="loudnessMatchEnabled">→ {{ loudnessMatch.targetLoudness.toFixed(1) }} LUFS ({{ loudnessMatch.gainDb >= 0 ? '+' : '' }}{{ loudnessMatch.gainDb.toFixed(1) }} dB)</span>
                            <span> · confronto con {{ loudnessMatch.comparedTracks }} tracce</span>
                        </span>
                    </div>
                </div>

                <div class="ac-platform-row" v-if="features.loudnessMatching && version.integratedLoudness !== null && version.integratedLoudness !== undefined">
                    <span class="ac-platform-label">Simula su</span>
                    <div class="ac-platform-pills">
                        <button
                            v-for="p in platformOptions"
                            :key="p.id"
                            class="ac-platform-pill"
                            :class="{ 'ac-platform-pill-active': platformSimulation === p.id }"
                            @click="platformSimulation = p.id"
                        >{{ p.label }}</button>
                    </div>
                    <span class="ac-platform-meta" v-if="platformSimulation !== 'original'">
                        {{ version.integratedLoudness.toFixed(1) }} → {{ platformTargets[platformSimulation] }} LUFS
                        ({{ platformGainDb >= 0 ? '+' : '' }}{{ platformGainDb.toFixed(1) }} dB)
                    </span>
                </div>

                <audio
                    ref="audioEl"
                    :src="streamUrl"
                    @timeupdate="onTimeUpdate"
                    @loadedmetadata="onLoadedMetadata"
                    @play="isPlaying = true"
                    @pause="isPlaying = false"
                    @ended="isPlaying = false"
                ></audio>
            </div>

            <section class="ac-comments-section" v-if="activeTab === 'overview' || activeTab === 'comments'">
                <div class="ac-comments-header">
                    <span>Commenti</span>
                    <span class="ac-comments-count" v-if="comments.length">{{ comments.length }}</span>
                    <button
                        v-if="hasThreadsWithReplies"
                        type="button"
                        class="ac-collapse-all-btn"
                        :title="allThreadsExpanded ? 'Comprimi tutte le risposte' : 'Espandi tutte le risposte'"
                        @click="toggleAllThreads"
                    >{{ allThreadsExpanded ? '−' : '+' }}</button>
                    <div class="ac-sort-toggle">
                        <button :class="{ 'ac-sort-active': sortMode === 'timestamp' }" @click="sortMode = 'timestamp'">Per timestamp</button>
                        <button :class="{ 'ac-sort-active': sortMode === 'recent' }" @click="sortMode = 'recent'">Più recenti</button>
                    </div>
                </div>

                <div v-if="comments.length === 0" class="ac-no-comments">
                    Nessun commento ancora. Clicca sulla waveform per aggiungerne uno.
                </div>

                <div class="ac-comment-rail">
                    <article
                        v-for="comment in sortedComments"
                        :key="comment.id"
                        :id="'ac-comment-' + comment.id"
                        class="ac-comment-card"
                        :class="{ 'ac-comment-highlighted': highlightedCommentId === comment.id }"
                    >
                        <span class="ac-rail-dot" :style="{ background: colorFor(comment.author_uid) }"></span>
                        <button class="ac-time-badge" @click="seekTo(comment.timestamp_seconds)">{{ formatTime(comment.timestamp_seconds) }}</button>
                        <div class="ac-comment-body">
                            <div class="ac-comment-top">
                                <img
                                    class="ac-avatar"
                                    :src="avatarUrl(comment.author_uid)"
                                    @error="onAvatarError($event, comment.author_uid)"
                                    :alt="comment.author_uid"
                                />
                                <div class="ac-comment-who">
                                    <span class="ac-comment-author">{{ comment.author_uid }}</span>
                                    <span class="ac-comment-date">{{ formatDate(comment.created_at) }}</span>
                                </div>
                                <span class="ac-status-pill" :class="comment.status === 'resolved' ? 'ac-status-resolved' : 'ac-status-open'">
                                    {{ comment.status === 'resolved' ? 'Risolto' : 'Aperto' }}
                                </span>
                            </div>

                            <template v-if="editingCommentId === comment.id">
                                <textarea v-model="editText" rows="2" class="ac-textarea"></textarea>
                                <div class="ac-composer-row">
                                    <button class="ac-btn ac-btn-ghost ac-btn-sm" @click="cancelEdit">Annulla</button>
                                    <button class="ac-btn ac-btn-primary ac-btn-sm" @click="saveEdit(comment)">Salva</button>
                                </div>
                            </template>
                            <p v-else class="ac-comment-text">{{ comment.body }}</p>

                            <div class="ac-comment-actions" v-if="editingCommentId !== comment.id">
                                <button class="ac-action-link" @click="startReply(comment)">Rispondi</button>
                                <button v-if="isMine(comment.author_uid)" class="ac-action-link" @click="startEdit(comment)">Modifica</button>
                                <button v-if="isMine(comment.author_uid)" class="ac-action-link" @click="deleteComment(comment)">Elimina</button>
                                <button class="ac-action-link" @click="toggleResolved(comment)">
                                    {{ comment.status === 'resolved' ? 'Riapri' : 'Risolto' }}
                                </button>
                                <button v-if="repliesFor(comment.id).length" class="ac-action-link ac-thread-toggle" @click="toggleThread(comment.id)">
                                    <span :class="{ 'ac-chevron-open': isThreadExpanded(comment.id) }">▸</span>
                                    {{ repliesFor(comment.id).length }} risposte
                                </button>
                            </div>

                            <div v-if="replyingTo === comment.id" class="ac-composer ac-reply-composer">
                                <textarea v-model="replyText" rows="2" placeholder="Scrivi una risposta..." class="ac-textarea"></textarea>
                                <div class="ac-composer-row">
                                    <button class="ac-btn ac-btn-ghost ac-btn-sm" @click="cancelReply">Annulla</button>
                                    <button class="ac-btn ac-btn-primary ac-btn-sm" @click="submitReply(comment)">Rispondi</button>
                                </div>
                            </div>

                            <div v-if="repliesFor(comment.id).length && isThreadExpanded(comment.id)" class="ac-replies">
                                <div
                                    v-for="reply in repliesFor(comment.id)"
                                    :key="reply.id"
                                    :id="'ac-comment-' + reply.id"
                                    class="ac-reply-card"
                                    :class="{ 'ac-comment-highlighted': highlightedCommentId === reply.id }"
                                >
                                    <img class="ac-avatar ac-avatar-sm" :src="avatarUrl(reply.author_uid)" @error="onAvatarError($event, reply.author_uid)" :alt="reply.author_uid" />
                                    <div class="ac-reply-body">
                                        <div class="ac-comment-who">
                                            <span class="ac-comment-author">{{ reply.author_uid }}</span>
                                            <span class="ac-comment-date">{{ formatDate(reply.created_at) }}</span>
                                        </div>
                                        <template v-if="editingCommentId === reply.id">
                                            <textarea v-model="editText" rows="2" class="ac-textarea"></textarea>
                                            <div class="ac-composer-row">
                                                <button class="ac-btn ac-btn-ghost ac-btn-sm" @click="cancelEdit">Annulla</button>
                                                <button class="ac-btn ac-btn-primary ac-btn-sm" @click="saveEdit(reply)">Salva</button>
                                            </div>
                                        </template>
                                        <p v-else class="ac-comment-text">{{ reply.body }}</p>
                                        <div class="ac-comment-actions" v-if="editingCommentId !== reply.id">
                                            <button v-if="isMine(reply.author_uid)" class="ac-action-link" @click="startEdit(reply)">Modifica</button>
                                            <button v-if="isMine(reply.author_uid)" class="ac-action-link" @click="deleteComment(reply)">Elimina</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="ac-composer">
                    <textarea
                        v-model="newCommentText"
                        rows="2"
                        placeholder="Scrivi un commento al punto in cui ti trovi..."
                        class="ac-textarea"
                    ></textarea>
                    <div class="ac-composer-row">
                        <span class="ac-composer-time">a {{ formatTime(currentTime) }}</span>
                        <button class="ac-btn ac-btn-primary ac-btn-sm" @click="submitComment">Invia</button>
                    </div>
                </div>
            </section>

            <section class="ac-versions-section" v-if="activeTab === 'versions'">
                <div
                    v-for="v in versionsDesc"
                    :key="v.id"
                    class="ac-version-row"
                    :class="{ 'ac-version-row-active': v.id === version.id }"
                >
                    <span class="ac-version-pill">v{{ v.number }}</span>
                    <div class="ac-version-info">
                        <span class="ac-version-current" v-if="v.id === version.id">In visualizzazione</span>
                        <span class="ac-version-meta">
                            Caricata da {{ v.uploadedBy }}<span v-if="v.uploadedAt"> · {{ formatDate(v.uploadedAt) }}</span>
                        </span>
                        <span class="ac-version-meta" v-if="v.nativeRevisionId">rev. Nextcloud: {{ formatUnixDate(v.nativeRevisionId) }}</span>
                    </div>
                    <button v-if="v.id !== version.id" class="ac-btn ac-btn-ghost ac-btn-sm" @click="switchVersion(v.id)">Visualizza</button>
                </div>
            </section>
            </div>
        </template>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const AVATAR_COLORS = ['#1f6fbf', '#8b5fd1', '#2f9e6e', '#e08a3c', '#d1435c', '#0f9aa6']

export default {
    name: 'AudioCollabPlayer',
    props: {
        loaded: { type: Boolean, default: false },
        fileid: { type: Number, required: true },
        filename: { type: String, required: true },
    },
    data() {
        return {
            loading: true,
            features: {
                loudnessMatching: true,
                commentNotifications: true,
                trackStatus: true,
            },
            loadingMessage: 'Caricamento anteprima audio...',
            peaks: [],
            streamUrl: '',
            duration: 0,
            currentTime: 0,
            isPlaying: false,
            volume: 1,
            comments: [],
            newCommentText: '',
            artist: null,
            title: null,
            isOwner: false,
            canManageStatus: false,
            showStatusMenu: false,
            statusOptions: ['draft', 'in_review', 'approved'],
            format: '',
            sizeBytes: 0,
            version: {},
            versions: [],
            showVersionMenu: false,
            editingMetadata: false,
            editArtist: '',
            editTitle: '',
            activeTab: 'overview',
            sortMode: 'timestamp',
            isSeeking: false,
            seekPreview: 0,
            avatarFallback: {},
            replyingTo: null,
            replyText: '',
            editingCommentId: null,
            editText: '',
            collapsedThreads: {},
            loudnessMatch: null,
            loudnessMatchEnabled: false,
            platformSimulation: 'original',
            platformOptions: [
                { id: 'original', label: 'Originale' },
                { id: 'spotify', label: 'Spotify' },
                { id: 'apple_music', label: 'Apple Music' },
                { id: 'youtube_music', label: 'YouTube Music' },
            ],
            // Target LUFS pubblici di normalizzazione delle piattaforme
            // (verificati: Spotify/YouTube Music ~-14 LUFS, Apple Music ~-16 LUFS)
            platformTargets: {
                spotify: -14,
                apple_music: -16,
                youtube_music: -14,
            },
            audioContext: null,
            gainNode: null,
            highlightedCommentId: null,
            pendingSeekSeconds: null,
        }
    },
    computed: {
        displayPeaks() {
            const maxBars = 150
            const toPair = (p) => ({ top: Math.abs(p[1] ?? p[0] ?? 0), bottom: Math.abs(p[0] ?? p[1] ?? 0) })
            if (this.peaks.length <= maxBars) {
                return this.peaks.map(toPair)
            }
            const bucketSize = Math.ceil(this.peaks.length / maxBars)
            const result = []
            for (let i = 0; i < this.peaks.length; i += bucketSize) {
                const bucket = this.peaks.slice(i, i + bucketSize).map(toPair)
                result.push({
                    top: Math.max(...bucket.map(b => b.top)),
                    bottom: Math.max(...bucket.map(b => b.bottom)),
                })
            }
            return result
        },
        playheadRatio() {
            if (!this.duration) return 0
            return this.currentTime / this.duration
        },
        playheadPercent() {
            return this.playheadRatio * 100
        },
        seekDisplayValue() {
            return this.isSeeking ? this.seekPreview : this.currentTime
        },
        fileName() {
            return this.title || this.filename
        },
        isLossless() {
            return this.format === 'WAV' || this.format === 'FLAC'
        },
        bitrateKbps() {
            if (this.isLossless || !this.duration || !this.sizeBytes) return null
            return Math.round((this.sizeBytes * 8) / this.duration / 1000)
        },
        qualityLabel() {
            if (this.isLossless) return 'LOSSLESS'
            return this.bitrateKbps ? `${this.bitrateKbps} kbps` : this.format
        },
        sizeLabel() {
            if (!this.sizeBytes) return ''
            const mb = this.sizeBytes / (1024 * 1024)
            return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.round(this.sizeBytes / 1024)} KB`
        },
        versionsDesc() {
            return [...this.versions].sort((a, b) => b.number - a.number)
        },
        platformGainDb() {
            if (this.platformSimulation === 'original' || this.version.integratedLoudness == null) {
                return 0
            }
            return this.platformTargets[this.platformSimulation] - this.version.integratedLoudness
        },
        sortedComments() {
            const list = this.comments.filter(c => !c.parent_id)
            if (this.sortMode === 'recent') {
                return list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
            }
            return list.sort((a, b) => a.timestamp_seconds - b.timestamp_seconds)
        },
        threadsWithReplies() {
            return this.sortedComments.filter(c => this.repliesFor(c.id).length > 0)
        },
        hasThreadsWithReplies() {
            return this.threadsWithReplies.length > 0
        },
        allThreadsExpanded() {
            return this.threadsWithReplies.every(c => this.isThreadExpanded(c.id))
        },
    },
    watch: {
        volume(value) {
            if (this.$refs.audioEl) {
                this.$refs.audioEl.volume = value
            }
            // Su iOS Safari HTMLMediaElement.volume viene ignorato (WebKit
            // non permette di cambiare il volume via JS, solo con i tasti
            // fisici): applichiamo il volume anche via GainNode, l'unico
            // modo che funziona davvero su iPhone.
            this.applyGain()
        },
        loudnessMatchEnabled(value) {
            if (value) {
                this.platformSimulation = 'original'
            }
            this.applyGain()
        },
        platformSimulation(value) {
            if (value !== 'original') {
                this.loudnessMatchEnabled = false
            }
            this.applyGain()
        },
    },
    async mounted() {
        document.body.classList.add('audiocollab-viewer-active')
        // Abbiamo una nostra UI di caricamento interna: diciamo subito al
        // Viewer nativo di nascondere la propria rotellina, altrimenti resta
        // visibile sovrapposta alla nostra finché l'audio non è pronto.
        this.$emit('update:loaded', true)
        this.fetchFeatures()

        const params = new URLSearchParams(window.location.search)
        const acVersion = params.get('ac_version')
        const acT = params.get('ac_t')
        const acComment = params.get('ac_comment')
        const hasDeepLink = acVersion !== null || acT !== null || acComment !== null

        await this.loadTrackData(acVersion !== null ? Number(acVersion) : null)

        if (acT !== null) {
            this.pendingSeekSeconds = Number(acT)
        }
        if (acComment !== null) {
            this.highlightedCommentId = Number(acComment)
            this.activeTab = 'comments'
            this.$nextTick(() => this.scrollToHighlightedComment())
        }
        if (hasDeepLink) {
            this.stripDeepLinkParams()
        }
    },
    beforeDestroy() {
        document.body.classList.remove('audiocollab-viewer-active')
    },
    methods: {
        async fetchFeatures() {
            try {
                const response = await axios.get(generateUrl('/apps/audiocollab/api/settings'))
                this.features = { ...this.features, ...response.data }
            } catch (e) {
                console.error('AudioCollab: errore caricamento impostazioni', e)
            }
        },
        async loadTrackData(versionId = null) {
            this.loadingMessage = 'Caricamento anteprima audio...'
            const slowLoadTimer = setTimeout(() => {
                this.loadingMessage = 'Generazione anteprima in corso, un momento...'
            }, 1500)
            try {
                const params = { fileid: this.fileid }
                if (versionId) {
                    params.version = versionId
                }
                const response = await axios.get(generateUrl('/apps/audiocollab/api/track'), { params })
                this.peaks = response.data.waveform.peaks
                this.streamUrl = response.data.streamUrl
                this.comments = response.data.comments
                this.artist = response.data.artist
                this.title = response.data.title
                this.isOwner = response.data.isOwner
                this.canManageStatus = response.data.canManageStatus || false
                this.format = response.data.format || ''
                this.sizeBytes = response.data.sizeBytes || 0
                this.version = response.data.version || {}
                this.versions = response.data.versions || []
                this.loudnessMatch = response.data.loudnessMatch || null
                this.applyGain()
            } catch (e) {
                console.error('AudioCollab: errore caricamento traccia', e)
            } finally {
                clearTimeout(slowLoadTimer)
                this.loading = false
            }
        },
        statusLabel(status) {
            return { draft: 'Bozza', in_review: 'In revisione', approved: 'Approvato' }[status] || 'Bozza'
        },
        async changeStatus(status) {
            this.showStatusMenu = false
            if (!this.canManageStatus || status === this.version.status) return
            try {
                await axios.post(generateUrl('/apps/audiocollab/api/track/status'), {
                    fileid: this.fileid,
                    version_id: this.version.id,
                    status,
                })
                this.version = { ...this.version, status }
            } catch (e) {
                console.error('AudioCollab: errore cambio stato', e)
            }
        },
        async switchVersion(versionId) {
            if (versionId === this.version.id) {
                this.showVersionMenu = false
                return
            }
            if (this.$refs.audioEl) {
                this.$refs.audioEl.pause()
            }
            this.showVersionMenu = false
            this.currentTime = 0
            this.duration = 0
            this.loading = true
            await this.loadTrackData(versionId)
        },
        onLoadedMetadata() {
            this.$emit('update:loaded', true)
            this.duration = this.$refs.audioEl.duration
            this.$refs.audioEl.volume = this.volume
            if (this.pendingSeekSeconds !== null) {
                this.seekTo(this.pendingSeekSeconds)
                this.pendingSeekSeconds = null
            }
        },
        stripDeepLinkParams() {
            const url = new URL(window.location.href)
            ;['ac_t', 'ac_comment', 'ac_version'].forEach(key => url.searchParams.delete(key))
            const query = url.searchParams.toString()
            window.history.replaceState(null, '', url.pathname + (query ? '?' + query : '') + url.hash)
        },
        scrollToHighlightedComment() {
            const el = document.getElementById('ac-comment-' + this.highlightedCommentId)
            if (!el) return
            el.scrollIntoView({ behavior: 'smooth', block: 'center' })
            setTimeout(() => {
                this.highlightedCommentId = null
            }, 4000)
        },
        onTimeUpdate() {
            if (this.isSeeking) return
            this.currentTime = this.$refs.audioEl.currentTime
        },
        togglePlay() {
            this.ensureAudioGraph()
            if (this.isPlaying) {
                this.$refs.audioEl.pause()
            } else {
                this.$refs.audioEl.play()
            }
        },
        ensureAudioGraph() {
            if (this.audioContext) {
                if (this.audioContext.state === 'suspended') {
                    this.audioContext.resume()
                }
                return
            }
            const AudioContextClass = window.AudioContext || window.webkitAudioContext
            if (!AudioContextClass) return
            this.audioContext = new AudioContextClass()
            const source = this.audioContext.createMediaElementSource(this.$refs.audioEl)
            this.gainNode = this.audioContext.createGain()
            source.connect(this.gainNode)
            this.gainNode.connect(this.audioContext.destination)
            this.applyGain()
        },
        applyGain() {
            if (!this.gainNode) return
            let gainDb = 0
            if (this.platformSimulation !== 'original') {
                gainDb = this.platformGainDb
            } else if (this.loudnessMatchEnabled && this.loudnessMatch) {
                gainDb = this.loudnessMatch.gainDb
            }
            // this.volume (0-1, dal cursore volume) moltiplica il gain di
            // correzione invece di passare solo per audioEl.volume: su iOS
            // quest'ultimo viene ignorato da WebKit, quindi senza questo il
            // cursore del volume non avrebbe alcun effetto su iPhone.
            this.gainNode.gain.value = this.volume * Math.pow(10, gainDb / 20)
        },
        skip(seconds) {
            const el = this.$refs.audioEl
            const next = Math.min(Math.max(el.currentTime + seconds, 0), this.duration || 0)
            el.currentTime = next
            this.currentTime = next
        },
        onSeekStart() {
            this.isSeeking = true
            this.seekPreview = this.currentTime
        },
        onSeekInput(event) {
            const value = parseFloat(event.target.value)
            this.seekPreview = value
            this.currentTime = value
            this.$refs.audioEl.currentTime = value
        },
        onSeekEnd() {
            this.isSeeking = false
        },
        seekTo(seconds) {
            this.ensureAudioGraph()
            this.$refs.audioEl.currentTime = seconds
            this.currentTime = seconds
            this.$refs.audioEl.play()
        },
        onWaveformClick(event) {
            const rect = this.$refs.waveform.getBoundingClientRect()
            const ratio = (event.clientX - rect.left) / rect.width
            const seconds = ratio * this.duration
            this.seekTo(seconds)
        },
        async submitComment() {
            if (!this.newCommentText.trim()) return
            try {
                const response = await axios.post(generateUrl('/apps/audiocollab/api/comment'), {
                    fileid: this.fileid,
                    timestamp_seconds: this.currentTime,
                    body: this.newCommentText,
                    version_id: this.version.id,
                })
                this.comments.push(response.data)
                this.newCommentText = ''
            } catch (e) {
                console.error('AudioCollab: errore invio commento', e)
            }
        },
        repliesFor(commentId) {
            return this.comments
                .filter(c => c.parent_id === commentId)
                .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
        },
        isThreadExpanded(commentId) {
            return !this.collapsedThreads[commentId]
        },
        toggleThread(commentId) {
            this.$set(this.collapsedThreads, commentId, !this.collapsedThreads[commentId])
        },
        toggleAllThreads() {
            // Se sono già tutti espansi comprime tutto, altrimenti espande
            // tutto: un solo bottone che riflette lo stato aggregato invece
            // di dover aprire/chiudere ogni thread singolarmente.
            const collapse = this.allThreadsExpanded
            this.threadsWithReplies.forEach(c => {
                this.$set(this.collapsedThreads, c.id, collapse)
            })
        },
        startReply(comment) {
            this.replyingTo = comment.id
            this.replyText = ''
        },
        cancelReply() {
            this.replyingTo = null
        },
        async submitReply(comment) {
            if (!this.replyText.trim()) return
            try {
                const response = await axios.post(generateUrl('/apps/audiocollab/api/comment'), {
                    fileid: this.fileid,
                    timestamp_seconds: comment.timestamp_seconds,
                    body: this.replyText,
                    parent_id: comment.id,
                    version_id: this.version.id,
                })
                this.comments.push(response.data)
                this.replyingTo = null
                this.replyText = ''
                this.$delete(this.collapsedThreads, comment.id)
            } catch (e) {
                console.error('AudioCollab: errore invio risposta', e)
            }
        },
        startEdit(comment) {
            this.editingCommentId = comment.id
            this.editText = comment.body
        },
        cancelEdit() {
            this.editingCommentId = null
        },
        async saveEdit(comment) {
            if (!this.editText.trim()) return
            try {
                await axios.put(generateUrl('/apps/audiocollab/api/comment/{id}', { id: comment.id }), {
                    body: this.editText,
                })
                comment.body = this.editText
                this.editingCommentId = null
            } catch (e) {
                console.error('AudioCollab: errore modifica commento', e)
            }
        },
        async deleteComment(comment) {
            if (!confirm('Eliminare questo commento?')) return
            try {
                await axios.delete(generateUrl('/apps/audiocollab/api/comment/{id}', { id: comment.id }))
                this.comments = this.comments.filter(c => c.id !== comment.id && c.parent_id !== comment.id)
            } catch (e) {
                console.error('AudioCollab: errore eliminazione commento', e)
            }
        },
        async toggleResolved(comment) {
            const newStatus = comment.status === 'resolved' ? 'open' : 'resolved'
            try {
                await axios.post(generateUrl('/apps/audiocollab/api/comment/{id}/status', { id: comment.id }), {
                    status: newStatus,
                })
                comment.status = newStatus
            } catch (e) {
                console.error('AudioCollab: errore aggiornamento stato commento', e)
            }
        },
        startEditingMetadata() {
            this.editArtist = this.artist || ''
            this.editTitle = this.title || ''
            this.editingMetadata = true
        },
        cancelEditingMetadata() {
            this.editingMetadata = false
        },
        async saveMetadata() {
            try {
                const response = await axios.post(generateUrl('/apps/audiocollab/api/metadata'), {
                    fileid: this.fileid,
                    artist: this.editArtist,
                    title: this.editTitle,
                })
                this.artist = response.data.artist
                this.title = response.data.title
                this.editingMetadata = false
            } catch (e) {
                console.error('AudioCollab: errore salvataggio metadati', e)
            }
        },
        formatTime(seconds) {
            if (!seconds || isNaN(seconds)) return '0:00'
            const m = Math.floor(seconds / 60)
            const s = Math.floor(seconds % 60).toString().padStart(2, '0')
            return `${m}:${s}`
        },
        formatDate(value) {
            if (!value) return ''
            const d = new Date(value.replace(' ', 'T') + 'Z')
            if (isNaN(d.getTime())) return ''
            return d.toLocaleString('it-IT', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        },
        formatUnixDate(seconds) {
            const d = new Date(seconds * 1000)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleString('it-IT', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        },
        initials(name) {
            if (!name) return '?'
            return name.charAt(0).toUpperCase()
        },
        isMine(authorUid) {
            const currentUser = OC.getCurrentUser()
            return currentUser && currentUser.uid === authorUid
        },
        colorFor(uid) {
            let hash = 0
            for (let i = 0; i < (uid || '').length; i++) {
                hash = uid.charCodeAt(i) + ((hash << 5) - hash)
            }
            return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length]
        },
        avatarUrl(uid) {
            if (this.avatarFallback[uid]) {
                return this.avatarFallback[uid]
            }
            return generateUrl('/avatar/{uid}/64', { uid })
        },
        onAvatarError(event, uid) {
            const color = this.colorFor(uid).replace('#', '%23')
            const letter = this.initials(uid)
            const svg = `data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64'><rect width='64' height='64' rx='32' fill='${color}'/><text x='32' y='40' font-family='sans-serif' font-size='26' fill='white' text-anchor='middle'>${letter}</text></svg>`
            this.$set(this.avatarFallback, uid, svg)
        },
    },
}
</script>

<style scoped>
@import './shared-theme.css';

/* Senza questo reset, gli elementi con padding e box-sizing:content-box
   (default del browser) che ricevono "width: 100%" nei breakpoint qui sotto
   (es. .ac-waveform-card, .ac-comments-section) si rendono più larghi del
   contenitore della loro stessa padding, e l'eccedenza viene tagliata in
   silenzio dall'overflow-x:hidden qui sotto invece di andare a capo. */
.audiocollab-player,
.audiocollab-player *,
.audiocollab-player *::before,
.audiocollab-player *::after {
    box-sizing: border-box;
}

.audiocollab-player {
    width: 100%;
    max-width: 980px;
    margin: 0 auto;
    padding: 32px 14px 14px;
    background: var(--ac-bg);
    color: var(--ac-text);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    overflow-x: hidden;
    /* Il Viewer nativo di Nextcloud mette il file in un contenitore flex
       con align-items:center: va bene per immagini/video che stanno dentro
       il riquadro, ma il nostro player è più alto del riquadro disponibile
       su mobile, e centrarlo verticalmente lo taglia a metà sopra e sotto
       in modo non recuperabile con lo scroll. Ci ancoriamo in alto. */
    align-self: flex-start;
}

.audiocollab-player audio {
    /* Il tag <audio> è pilotato solo via JS (play/pause/seek dal nostro
       transport custom): senza questa regola, iOS Safari può renderizzare
       comunque una propria UI nativa al posto dei nostri controlli. */
    display: none;
}

.ac-header {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 12px;
    padding: 14px 16px;
    border-radius: var(--ac-radius);
    background:
        radial-gradient(circle at 85% 0%, rgba(255, 255, 255, 0.12), transparent 55%),
        linear-gradient(135deg, #0b1f4d 0%, #123a8a 55%, #1c5fd6 100%);
    color: #fff;
}

.ac-header .ac-title,
.ac-header .ac-subtitle,
.ac-header .ac-file-meta {
    color: #fff;
}

.ac-header .ac-subtitle {
    opacity: 0.85;
}

.ac-header .ac-file-meta {
    opacity: 0.7;
}

.ac-header .ac-version-pill-btn {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
}

.ac-cover {
    flex-shrink: 0;
    width: 52px;
    height: 52px;
    border-radius: var(--ac-radius-sm);
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ac-header-display {
    flex: 1;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
}

.ac-header-text {
    flex: 1;
    min-width: 0;
}

.ac-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ac-title {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: var(--ac-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ac-version-pill {
    flex-shrink: 0;
    background: var(--ac-accent-soft);
    color: var(--ac-accent-dark);
    font-size: 12px;
    font-weight: 600;
    padding: 2px 9px;
    border-radius: 10px;
}

.ac-version-switcher {
    position: relative;
}

.ac-version-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: none;
    cursor: pointer;
}

.ac-version-menu {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    z-index: 10;
    min-width: 220px;
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius-sm);
    box-shadow: 0 4px 16px rgba(20, 30, 40, 0.15);
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ac-version-menu-item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 1px;
    border: none;
    background: transparent;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
    text-align: left;
}

.ac-version-menu-item:hover {
    background: var(--ac-bg);
}

.ac-version-menu-item-active {
    background: var(--ac-accent-soft);
}

.ac-version-menu-number {
    font-size: 12px;
    font-weight: 700;
    color: var(--ac-text);
}

.ac-version-menu-meta {
    font-size: 11px;
    color: var(--ac-text-faint);
}

.ac-subtitle {
    margin: 4px 0 0 0;
    color: var(--ac-text-dim);
    font-size: 14px;
}

.ac-file-meta {
    margin: 4px 0 0 0;
    color: var(--ac-text-faint);
    font-size: 12px;
}

.ac-header-side {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.ac-quality-badge {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.4px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.5);
}

.ac-status-switcher {
    position: relative;
}

.ac-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.4px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    cursor: default;
}

.ac-status-switcher button.ac-status-pill {
    cursor: pointer;
}

.ac-status-pill-draft {
    background: rgba(255, 255, 255, 0.16);
}

.ac-status-pill-in_review {
    background: rgba(224, 138, 60, 0.85);
}

.ac-status-pill-approved {
    background: rgba(47, 158, 110, 0.85);
}

.ac-status-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 10;
    min-width: 160px;
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius-sm);
    box-shadow: 0 4px 16px rgba(20, 30, 40, 0.15);
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ac-status-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
    background: transparent;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
    text-align: left;
    font-size: 13px;
    color: var(--ac-text);
}

.ac-status-menu-item:hover {
    background: var(--ac-bg);
}

.ac-status-menu-item-active {
    background: var(--ac-accent-soft);
}

.ac-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    border: none;
}

.ac-icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #fff;
    opacity: 0.85;
    cursor: pointer;
    transition: background 0.15s ease, opacity 0.15s ease;
}

.ac-icon-btn:hover {
    background: rgba(255, 255, 255, 0.16);
    opacity: 1;
}

.ac-header-edit {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ac-input {
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius-sm);
    padding: 8px 12px;
    font-size: 14px;
    background: var(--ac-surface);
    color: var(--ac-text);
    font-family: inherit;
}

.ac-input:focus {
    outline: none;
    border-color: var(--ac-accent);
}

.ac-header-edit-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.ac-tabbar {
    display: flex;
    gap: 22px;
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius);
    padding: 0 16px;
    margin-bottom: 12px;
}

.ac-tab {
    display: flex;
    align-items: center;
    gap: 7px;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    padding: 10px 2px 9px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ac-text-dim);
    cursor: pointer;
}

.ac-tab:hover {
    color: var(--ac-text);
}

.ac-tab-active {
    color: var(--ac-accent);
    border-bottom-color: var(--ac-accent);
}

.ac-tab-count {
    background: var(--ac-accent);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 8px;
}

.ac-tab-content {
    height: min(66vh, 640px);
    min-height: 460px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    overflow: hidden;
}

.ac-tab-content-row {
    flex-direction: row;
    align-items: flex-start;
}

.ac-tab-content-row .ac-waveform-card {
    flex: 0 0 50%;
    align-self: flex-start;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ac-tab-content-row .ac-waveform-container {
    height: 180px;
}

.ac-tab-content-row .ac-comments-section {
    flex: 1;
    min-width: 0;
    align-self: stretch;
}

.ac-waveform-card {
    flex-shrink: 0;
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius);
    padding: 14px;
    box-shadow: 0 1px 3px rgba(20, 30, 40, 0.06);
}

.ac-waveform-container {
    position: relative;
    height: 110px;
    background: var(--ac-bg);
    border-radius: var(--ac-radius-sm);
    cursor: pointer;
    overflow: hidden;
}

.ac-waveform-svg {
    width: 100%;
    height: 100%;
}

.ac-center-line {
    stroke: var(--ac-border);
    stroke-width: 1;
}

.ac-bar {
    fill: #c3cdd6;
}

.ac-bar-played {
    fill: var(--ac-accent);
}

.ac-comment-tick {
    position: absolute;
    top: 4px;
    width: 6px;
    height: 6px;
    margin-left: -3px;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 0 0 2px var(--ac-surface);
}

.ac-playhead {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--ac-marker);
    pointer-events: none;
}

.ac-transport {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}

.ac-play-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    background: var(--ac-accent);
    color: #fff;
    cursor: pointer;
    flex-shrink: 0;
}

.ac-play-btn:hover {
    background: var(--ac-accent-dark);
}

.ac-skip-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 1px solid var(--ac-border);
    background: var(--ac-surface);
    color: var(--ac-text);
    cursor: pointer;
    flex-shrink: 0;
}

.ac-skip-label {
    font-size: 8px;
    font-weight: 700;
    line-height: 1;
}

.ac-skip-btn:hover {
    color: var(--ac-accent);
    border-color: var(--ac-accent);
}

.ac-time-display {
    font-size: 12px;
    color: var(--ac-text-dim);
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.ac-seek-range {
    flex: 1;
    /* Senza min-width:0 un <input type="range"> in un contenitore flex non
       si restringe sotto la sua larghezza intrinseca (~UA default): a
       finestra stretta spinge il controllo volume fuori dalla riga invece
       di lasciargli spazio. */
    min-width: 0;
    accent-color: var(--ac-accent);
    /* Il player gira dentro il Viewer nativo di Nextcloud, che su iOS
       Safari intercetta i trascinamenti orizzontali per lo swipe tra
       file: senza touch-action:none quel gesto può "rubare" il drag
       dello slider invece di farlo muovere. */
    touch-action: none;
}

.ac-volume {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--ac-text-dim);
    flex-shrink: 0;
}

.ac-volume-range {
    width: 70px;
    accent-color: var(--ac-accent);
    touch-action: none;
}

.ac-loudness-row {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--ac-border);
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.ac-toggle-switch {
    flex-shrink: 0;
    position: relative;
    width: 34px;
    height: 20px;
    min-width: 0;
    min-height: 0;
    padding: 0;
    margin-top: 1px;
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

.ac-loudness-text {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.ac-loudness-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--ac-text);
}

.ac-loudness-meta {
    font-size: 11px;
    color: var(--ac-text-faint);
    padding-left: 21px;
}

.ac-platform-row {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--ac-border);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.ac-platform-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--ac-text);
}

.ac-platform-pills {
    display: flex;
    gap: 4px;
    background: var(--ac-bg);
    border-radius: 999px;
    padding: 3px;
}

.ac-platform-pill {
    border: none;
    background: transparent;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: var(--ac-text-dim);
    cursor: pointer;
    white-space: nowrap;
}

.ac-platform-pill-active {
    background: var(--ac-accent);
    color: #fff;
    box-shadow: 0 1px 2px rgba(20, 30, 40, 0.15);
}

.ac-platform-meta {
    font-size: 11px;
    color: var(--ac-text-faint);
}

.ac-comments-section {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius);
    box-shadow: 0 1px 3px rgba(20, 30, 40, 0.06);
    padding: 16px;
}

.ac-comments-header {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
    font-weight: 600;
    font-size: 15px;
}

.ac-comments-count {
    background: var(--ac-accent-soft);
    color: var(--ac-accent-dark);
    font-size: 11px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 10px;
}

.ac-collapse-all-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: 1px solid var(--ac-border);
    border-radius: 50%;
    background: var(--ac-surface);
    color: var(--ac-text-dim);
    font-size: 14px;
    line-height: 1;
    font-weight: 700;
    padding: 0;
    cursor: pointer;
}

.ac-collapse-all-btn:hover {
    color: var(--ac-accent-dark);
    border-color: var(--ac-accent);
}

.ac-sort-toggle {
    margin-left: auto;
    display: flex;
    gap: 4px;
    background: var(--ac-bg);
    border-radius: 999px;
    padding: 3px;
}

.ac-sort-toggle button {
    border: none;
    background: transparent;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: var(--ac-text-dim);
    cursor: pointer;
    white-space: nowrap;
}

.ac-sort-active {
    background: var(--ac-surface);
    color: var(--ac-accent-dark);
    box-shadow: 0 1px 2px rgba(20, 30, 40, 0.1);
}

.ac-no-comments {
    color: var(--ac-text-faint);
    font-style: italic;
    font-size: 13px;
    padding: 8px 4px 16px;
}

.ac-comment-rail {
    position: relative;
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ac-comment-rail::before {
    content: '';
    position: absolute;
    left: 51px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: var(--ac-border);
}

.ac-comment-card {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 9px 4px;
    border-bottom: 1px solid var(--ac-border);
}

.ac-comment-card:last-child {
    border-bottom: none;
}

.ac-comment-highlighted {
    animation: ac-comment-flash 2.4s ease-out;
    border-radius: 8px;
}

@keyframes ac-comment-flash {
    0% { background: var(--ac-accent-soft); }
    100% { background: transparent; }
}

.ac-rail-dot {
    position: absolute;
    left: 47px;
    top: 20px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    box-shadow: 0 0 0 3px var(--ac-surface);
    z-index: 1;
}

.ac-time-badge {
    flex-shrink: 0;
    width: 44px;
    text-align: center;
    border: none;
    background: var(--ac-accent-soft);
    color: var(--ac-accent-dark);
    font-size: 11px;
    font-weight: 700;
    padding: 4px 0;
    border-radius: 10px;
    cursor: pointer;
}

.ac-time-badge:hover {
    background: var(--ac-accent);
    color: #fff;
}

.ac-comment-body {
    flex: 1;
    min-width: 0;
}

.ac-comment-top {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ac-avatar {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--ac-bg);
}

.ac-comment-who {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.ac-comment-author {
    font-weight: 600;
    font-size: 13px;
}

.ac-comment-date {
    font-size: 11px;
    color: var(--ac-text-faint);
}

.ac-status-pill {
    flex-shrink: 0;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    white-space: nowrap;
}

.ac-status-open {
    background: var(--ac-status-open-bg);
    color: var(--ac-status-open-text);
}

.ac-status-resolved {
    background: var(--ac-status-resolved-bg);
    color: var(--ac-status-resolved-text);
}

.ac-comment-text {
    margin: 4px 0;
    font-size: 13px;
    line-height: 1.4;
    word-wrap: break-word;
}

.ac-comment-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.ac-action-link {
    border: none;
    background: none;
    padding: 0;
    font-size: 11px;
    font-weight: 600;
    color: var(--ac-text-faint);
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.ac-action-link:hover {
    color: var(--ac-accent);
}

.ac-thread-toggle {
    margin-left: auto;
}

.ac-chevron-open {
    display: inline-block;
    transform: rotate(90deg);
}

.ac-reply-composer {
    margin-top: 10px;
    padding-top: 0;
    border-top: none;
}

.ac-replies {
    margin-top: 12px;
    padding-left: 14px;
    border-left: 2px solid var(--ac-border);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.ac-reply-card {
    display: flex;
    gap: 10px;
}

.ac-avatar-sm {
    width: 22px;
    height: 22px;
}

.ac-reply-body {
    flex: 1;
    min-width: 0;
}

.ac-composer {
    flex-shrink: 0;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--ac-border);
}

.ac-textarea {
    width: 100%;
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius-sm);
    padding: 8px;
    font-family: inherit;
    font-size: 13px;
    resize: vertical;
    background: var(--ac-bg);
    color: var(--ac-text);
    box-sizing: border-box;
}

.ac-composer-row {
    margin-top: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ac-composer-time {
    font-size: 11px;
    color: var(--ac-text-faint);
}

.ac-btn {
    border: none;
    border-radius: 999px;
    padding: 8px 18px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s ease;
}

.ac-btn:hover {
    opacity: 0.9;
}

.ac-btn-primary {
    background: var(--ac-accent);
    color: #fff;
}

.ac-btn-ghost {
    background: var(--ac-bg);
    color: var(--ac-text);
}

.ac-btn-sm {
    padding: 6px 14px;
    font-size: 12px;
}

.ac-versions-section {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--ac-radius);
    padding: 20px;
}

.ac-version-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 8px;
    border-radius: var(--ac-radius-sm);
}

.ac-version-row-active {
    background: var(--ac-accent-soft);
}

.ac-version-row + .ac-version-row {
    border-top: 1px solid var(--ac-border);
}

.ac-version-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ac-version-current {
    font-weight: 600;
    font-size: 14px;
}

.ac-version-meta {
    font-size: 12px;
    color: var(--ac-text-dim);
}

@media (max-width: 840px) {
    .ac-tab-content {
        height: auto;
        min-height: 420px;
    }

    .ac-tab-content-row {
        flex-direction: column;
    }

    .ac-tab-content-row .ac-waveform-card {
        flex: none;
        width: 100%;
    }

    .ac-tab-content-row .ac-comments-section {
        flex: none;
        width: 100%;
    }
}

@media (max-width: 520px) {
    .audiocollab-player {
        padding: 32px 10px 10px;
    }

    .ac-header {
        padding: 14px;
        gap: 10px;
    }

    .ac-header-display {
        /* Con flex-wrap semplice, .ac-header-text può restringersi fino a
           quasi 0 (min-width:0 + titolo con ellipsis) e la riga "sta" tutta
           su una linea sola: il titolo si riduce a una sola lettera mentre
           i badge (versione/stato/qualità) restano larghi. Passando a
           colonna, testo e badge hanno sempre ciascuno la riga intera. */
        flex-direction: column;
        align-items: stretch;
    }

    .ac-header-side {
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .ac-cover {
        width: 40px;
        height: 40px;
    }

    .ac-title {
        font-size: 16px;
    }

    .ac-tabbar {
        gap: 14px;
        padding: 0 10px;
        overflow-x: auto;
    }

    .ac-tab {
        font-size: 12px;
        white-space: nowrap;
    }

    .ac-comments-section {
        flex: none;
        padding: 12px;
    }

    .ac-comment-rail {
        flex: none;
        overflow-y: visible;
    }

    .ac-versions-section {
        flex: none;
        overflow-y: visible;
    }

    .ac-waveform-card {
        padding: 12px;
    }

    .ac-waveform-container {
        height: 90px;
    }

    .ac-transport {
        flex-wrap: wrap;
        row-gap: 10px;
    }

    .ac-seek-range {
        order: 1;
        flex-basis: 100%;
        /* range input di sistema: alza l'area di tocco senza cambiare
           l'altezza visiva della traccia */
        min-height: 28px;
    }

    .ac-volume {
        /* Sotto i 520px non c'è spazio per stare sulla prima riga insieme
           a play/skip/tempo senza schiacciare la barra di avanzamento:
           la mettiamo su una riga propria, con lo slider allargato per
           un'area di tocco comoda invece dei 70px fissi da desktop. */
        order: 2;
        flex-basis: 100%;
        margin-top: 2px;
    }

    .ac-volume-range {
        flex: 1;
        width: auto;
        min-width: 0;
        min-height: 28px;
    }

    /* Aree di tocco più generose sotto i ~520px (min. 44x44 consigliato da iOS) */
    .ac-skip-btn {
        width: 44px;
        height: 44px;
    }

    .ac-icon-btn {
        width: 40px;
        height: 40px;
    }

    .ac-status-pill {
        padding: 8px 10px;
    }

    .ac-quality-badge {
        padding: 8px 10px;
    }

    .ac-time-badge {
        padding: 10px 0;
    }

    .ac-action-link {
        padding: 8px 2px;
    }

    .ac-toggle-switch {
        width: 40px;
        height: 24px;
    }

    .ac-toggle-knob {
        width: 20px;
        height: 20px;
    }

    .ac-toggle-switch-on .ac-toggle-knob {
        transform: translateX(16px);
    }
}
</style>

<style>
/* Non-scoped: nasconde il titolo file nella barra del Viewer nativo
   solo mentre il nostro player è attivo (vedi mounted/beforeDestroy) */
body.audiocollab-viewer-active .modal-header__name {
    visibility: hidden;
}
</style>
