import Vue from 'vue'
import AudioCollabPlayer from './AudioCollabPlayer.vue'
import ProjectView from './ProjectView.vue'

// Il pulsante "AudioCollab" nella toolbar dei File è un bottone nativo di
// Nextcloud con flex-shrink:0 e min-width:fit-content: sotto una certa
// larghezza il testo non va mai a capo né si riduce, e finisce per
// sovrapporsi al breadcrumb del percorso. Altri pulsanti nativi della
// stessa toolbar diventano icona-sola su schermi stretti; replichiamo lo
// stesso comportamento solo per il nostro, mirato tramite il suo
// data-cy-files-list-action (stabile, non cambia tra le build).
const mobileToolbarStyle = document.createElement('style')
mobileToolbarStyle.textContent = `
@media (max-width: 520px) {
    [data-cy-files-list-action="audiocollab-open-project-toolbar"] .button-vue__text {
        display: none;
    }
}
`
document.head.appendChild(mobileToolbarStyle)

const handler = {
    id: 'audiocollab',
    group: 'media',
    mimes: [
        'audio/mpeg',
        'audio/flac',
        'audio/x-flac',
        'audio/wav',
        'audio/x-wav',
        // .m4a: già gestiti da NodeWrittenListener (mimePart === 'audio') e
        // da ProjectController per la cache/il confronto tracce, ma senza
        // questi due mimetype il Viewer non apriva mai AudioCollab per
        // questi file quando ci si clicca sopra nei File.
        'audio/mp4',
        'audio/x-m4a',
    ],
    component: AudioCollabPlayer,
}

function registerAudioCollabHandler() {
    if (!window.OCA || !window.OCA.Viewer) {
        return false
    }

    const handlers = window.OCA.Viewer.availableHandlers
    const nativeIndex = handlers.findIndex(h => h.id === 'audios')
    if (nativeIndex > -1) {
        handlers.splice(nativeIndex, 1)
    }

    if (!handlers.find(h => h.id === 'audiocollab')) {
        window.OCA.Viewer.registerHandler(handler)
    }

    return true
}

function waitForViewerAndRegister(attemptsLeft) {
    if (registerAudioCollabHandler()) {
        return
    }
    if (attemptsLeft <= 0) {
        console.warn('AudioCollab: OCA.Viewer non trovato dopo diversi tentativi')
        return
    }
    setTimeout(() => waitForViewerAndRegister(attemptsLeft - 1), 150)
}

waitForViewerAndRegister(60)

let openProjectViewInstance = null

function closeProjectView() {
    if (!openProjectViewInstance) return
    const instance = openProjectViewInstance
    openProjectViewInstance = null
    instance.$destroy()
    if (instance.$el && instance.$el.parentNode) {
        instance.$el.parentNode.removeChild(instance.$el)
    }
}

function openProjectView(folderId) {
    // Senza questo, cliccare due volte velocemente sull'azione "AudioCollab"
    // di una cartella (o su due cartelle diverse in sequenza) montava più
    // modali sovrapposte contemporaneamente.
    closeProjectView()

    const mountEl = document.createElement('div')
    document.body.appendChild(mountEl)
    openProjectViewInstance = new Vue({
        render: (h) => h(ProjectView, {
            props: { folderId },
            on: {
                close: closeProjectView,
            },
        }),
    }).$mount(mountEl)
}

const projectFolderAction = {
    id: 'audiocollab-open-project',
    displayName: () => 'AudioCollab',
    iconSvgInline: () => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 15v-6a1 1 0 0 1 2 0v6a1 1 0 0 1-2 0z"/><path d="M7.5 18v-12a1 1 0 0 1 2 0v12a1 1 0 0 1-2 0z"/><path d="M11 20v-16a1 1 0 0 1 2 0v16a1 1 0 0 1-2 0z"/><path d="M14.5 17v-10a1 1 0 0 1 2 0v10a1 1 0 0 1-2 0z"/><path d="M18 13.5v-3a1 1 0 0 1 2 0v3a1 1 0 0 1-2 0z"/></svg>',
    enabled: (context) => {
        const nodes = context && context.nodes ? context.nodes : []
        return nodes.length === 1 && nodes[0] && nodes[0].type === 'folder'
    },
    exec: async (context) => {
        const nodes = context && context.nodes ? context.nodes : []
        if (nodes.length === 1 && nodes[0]) {
            openProjectView(nodes[0].fileid)
        }
        return null
    },
    order: 15,
}

function registerProjectFolderAction() {
    const scope = window._nc_files_scope && window._nc_files_scope.v4_0
    if (!scope) {
        return false
    }
    scope.fileActions = scope.fileActions || new Map()
    if (!scope.fileActions.has(projectFolderAction.id)) {
        scope.fileActions.set(projectFolderAction.id, projectFolderAction)
        if (scope.registry && scope.registry.dispatchEvent) {
            scope.registry.dispatchEvent(new CustomEvent('register:action', { detail: projectFolderAction }))
        }
    }
    return true
}

function waitForFilesAndRegister(attemptsLeft) {
    if (registerProjectFolderAction()) {
        return
    }
    if (attemptsLeft <= 0) {
        console.warn('AudioCollab: registro azioni Files non trovato dopo diversi tentativi')
        return
    }
    setTimeout(() => waitForFilesAndRegister(attemptsLeft - 1), 150)
}

waitForFilesAndRegister(60)

const projectListAction = {
    id: 'audiocollab-open-project-toolbar',
    displayName: () => 'AudioCollab',
    iconSvgInline: () => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 15v-6a1 1 0 0 1 2 0v6a1 1 0 0 1-2 0z"/><path d="M7.5 18v-12a1 1 0 0 1 2 0v12a1 1 0 0 1-2 0z"/><path d="M11 20v-16a1 1 0 0 1 2 0v16a1 1 0 0 1-2 0z"/><path d="M14.5 17v-10a1 1 0 0 1 2 0v10a1 1 0 0 1-2 0z"/><path d="M18 13.5v-3a1 1 0 0 1 2 0v3a1 1 0 0 1-2 0z"/></svg>',
    enabled: (context) => {
        const contents = (context && context.contents) || []
        return contents.some((n) => n.mime && n.mime.startsWith('audio/'))
    },
    exec: async (context) => {
        const folder = context && context.folder
        if (folder && folder.fileid) {
            openProjectView(folder.fileid)
        }
    },
    order: 15,
}

function registerProjectListAction() {
    const scope = window._nc_files_scope && window._nc_files_scope.v4_0
    if (!scope) {
        return false
    }
    scope.fileListActions = scope.fileListActions || new Map()
    if (!scope.fileListActions.has(projectListAction.id)) {
        scope.fileListActions.set(projectListAction.id, projectListAction)
        if (scope.registry && scope.registry.dispatchEvent) {
            scope.registry.dispatchEvent(new CustomEvent('register:listAction', { detail: projectListAction }))
        }
    }
    return true
}

function waitForFilesAndRegisterListAction(attemptsLeft) {
    if (registerProjectListAction()) {
        return
    }
    if (attemptsLeft <= 0) {
        console.warn('AudioCollab: registro fileListActions non trovato dopo diversi tentativi')
        return
    }
    setTimeout(() => waitForFilesAndRegisterListAction(attemptsLeft - 1), 150)
}

waitForFilesAndRegisterListAction(60)
