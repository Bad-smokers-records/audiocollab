function loadInitialState(app, key, fallback) {
    const el = document.getElementById('initial-state-' + app + '-' + key)
    if (!el) return fallback
    try {
        return JSON.parse(atob(el.value))
    } catch (e) {
        return fallback
    }
}

function saveSettings(state) {
    return fetch(OC.generateUrl('/apps/audiocollab/api/settings'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            requesttoken: OC.requestToken,
        },
        body: JSON.stringify(state),
    })
}

document.addEventListener('DOMContentLoaded', function () {
    const mount = document.getElementById('audiocollab-admin-settings')
    if (!mount) return

    const state = loadInitialState('audiocollab', 'config', {
        loudnessMatching: true,
        commentNotifications: true,
        trackStatus: true,
    })

    const features = [
        { key: 'loudnessMatching', label: 'Bilanciamento del volume (loudness matching) tra le tracce di un progetto' },
        { key: 'commentNotifications', label: 'Notifiche per i commenti a timestamp' },
        { key: 'trackStatus', label: 'Stato di revisione della traccia (bozza / in revisione / approvato)' },
    ]

    const section = document.createElement('div')
    section.classList.add('section')

    const heading = document.createElement('h2')
    heading.textContent = 'AudioCollab'
    section.appendChild(heading)

    const hint = document.createElement('p')
    hint.classList.add('settings-hint')
    hint.textContent = 'Abilita o disabilita le funzionalità dell\'app per tutti gli utenti.'
    section.appendChild(hint)

    const status = document.createElement('span')
    status.style.marginLeft = '8px'
    status.style.opacity = '0.7'

    features.forEach(function (feature) {
        const row = document.createElement('div')
        row.style.margin = '12px 0'

        const checkboxId = 'audiocollab-setting-' + feature.key
        const checkbox = document.createElement('input')
        checkbox.type = 'checkbox'
        checkbox.id = checkboxId
        checkbox.className = 'checkbox'
        checkbox.checked = !!state[feature.key]

        const label = document.createElement('label')
        label.setAttribute('for', checkboxId)
        label.textContent = ' ' + feature.label

        checkbox.addEventListener('change', function () {
            state[feature.key] = checkbox.checked
            status.textContent = 'Salvataggio...'
            saveSettings(state)
                .then(function (res) {
                    status.textContent = res.ok ? 'Salvato' : 'Errore nel salvataggio'
                    setTimeout(function () { status.textContent = '' }, 2000)
                })
                .catch(function () {
                    status.textContent = 'Errore nel salvataggio'
                })
        })

        row.appendChild(checkbox)
        row.appendChild(label)
        section.appendChild(row)
    })

    section.appendChild(status)
    mount.appendChild(section)
})
