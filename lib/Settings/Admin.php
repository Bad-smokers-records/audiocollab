<?php
namespace OCA\Audiocollab\Settings;

use OCA\Audiocollab\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {

    public function __construct(
        private IAppConfig $appConfig,
        private IInitialState $initialState,
    ) {
    }

    public function getForm(): TemplateResponse {
        Util::addScript('audiocollab', 'audiocollab-admin-settings');

        $this->initialState->provideInitialState('config', [
            'loudnessMatching' => $this->appConfig->getAppValueBool(Application::CONFIG_LOUDNESS_MATCHING, true),
            'commentNotifications' => $this->appConfig->getAppValueBool(Application::CONFIG_COMMENT_NOTIFICATIONS, true),
            'trackStatus' => $this->appConfig->getAppValueBool(Application::CONFIG_TRACK_STATUS, true),
        ]);

        return new TemplateResponse('audiocollab', 'settings/admin');
    }

    public function getSection(): string {
        return 'audiocollab';
    }

    public function getPriority(): int {
        return 50;
    }
}
