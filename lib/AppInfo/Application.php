<?php
namespace OCA\Audiocollab\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Audiocollab\Listener\LoadAdditionalScriptsListener;
use OCA\Audiocollab\Listener\NodeWrittenListener;
use OCA\Audiocollab\Notification\Notifier;
use OCP\Files\Events\Node\NodeWrittenEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'audiocollab';

    // Chiavi AppConfig per abilitare/disabilitare le feature dal pannello
    // di amministrazione. Tutte abilitate di default (comportamento attuale).
    public const CONFIG_LOUDNESS_MATCHING = 'feature_loudness_matching';
    public const CONFIG_COMMENT_NOTIFICATIONS = 'feature_comment_notifications';
    public const CONFIG_TRACK_STATUS = 'feature_track_status';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(
            LoadAdditionalScriptsEvent::class,
            LoadAdditionalScriptsListener::class
        );
        $context->registerEventListener(
            NodeWrittenEvent::class,
            NodeWrittenListener::class
        );
        $context->registerNotifierService(Notifier::class);
    }

    public function boot(IBootContext $context): void {
    }
}
