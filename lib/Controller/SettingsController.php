<?php
namespace OCA\Audiocollab\Controller;

use OCA\Audiocollab\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;

class SettingsController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private IAppConfig $appConfig,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Stato delle feature attivabili/disattivabili, letto da qualunque utente
     * loggato: serve al frontend (player, vista progetto) per adattare
     * l'interfaccia in base a cosa l'amministratore ha abilitato.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function get(): JSONResponse {
        return new JSONResponse($this->readFeatures());
    }

    /**
     * Salva le feature: nessuna annotazione @NoAdminRequired, quindi per
     * default riservato agli amministratori (stesso comportamento di
     * qualunque altro endpoint di impostazioni in Nextcloud). CSRF non
     * bypassato: la chiamata arriva dalla pagina admin stessa via browser.
     */
    public function save(
        bool $loudnessMatching,
        bool $commentNotifications,
        bool $trackStatus,
    ): JSONResponse {
        $this->appConfig->setAppValueBool(Application::CONFIG_LOUDNESS_MATCHING, $loudnessMatching);
        $this->appConfig->setAppValueBool(Application::CONFIG_COMMENT_NOTIFICATIONS, $commentNotifications);
        $this->appConfig->setAppValueBool(Application::CONFIG_TRACK_STATUS, $trackStatus);

        return new JSONResponse($this->readFeatures());
    }

    private function readFeatures(): array {
        return [
            'loudnessMatching' => $this->appConfig->getAppValueBool(Application::CONFIG_LOUDNESS_MATCHING, true),
            'commentNotifications' => $this->appConfig->getAppValueBool(Application::CONFIG_COMMENT_NOTIFICATIONS, true),
            'trackStatus' => $this->appConfig->getAppValueBool(Application::CONFIG_TRACK_STATUS, true),
        ];
    }
}
