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
        { key: 'loudnessMatching', label: t('audiocollab', 'Volume balancing (loudness matching) between the tracks of a project') },
        { key: 'commentNotifications', label: t('audiocollab', 'Notifications for timestamped comments') },
        { key: 'trackStatus', label: t('audiocollab', 'Track review status (draft / in review / approved)') },
    ]

    const section = document.createElement('div')
    section.classList.add('section')

    const heading = document.createElement('h2')
    heading.textContent = 'AudioCollab'
    section.appendChild(heading)

    const hint = document.createElement('p')
    hint.classList.add('settings-hint')
    hint.textContent = t('audiocollab', 'Enable or disable the app\'s features for all users.')
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
            status.textContent = t('audiocollab', 'Saving...')
            saveSettings(state)
                .then(function (res) {
                    status.textContent = res.ok ? t('audiocollab', 'Saved') : t('audiocollab', 'Error while saving')
                    setTimeout(function () { status.textContent = '' }, 2000)
                })
                .catch(function () {
                    status.textContent = t('audiocollab', 'Error while saving')
                })
        })

        row.appendChild(checkbox)
        row.appendChild(label)
        section.appendChild(row)
    })

    section.appendChild(status)
    mount.appendChild(section)
})
