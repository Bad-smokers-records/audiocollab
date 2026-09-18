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

    // Configurabili per-installazione (es. `occ config:app:set audiocollab
    // ffmpeg_service_url --value=http://host:porta`). Il default della porta
    // locale va bene su qualunque installazione (il container audiotools è
    // sempre un sidecar sullo stesso host). Il cache path invece NON ha più
    // un default hardcoded: prima era un percorso assoluto Synology
    // (/volume1/...), che su qualunque altra installazione Nextcloud non
    // sarebbe mai esistito. Il default reale è calcolato a runtime in
    // TrackCacheService a partire dalla data directory configurata di
    // Nextcloud, portabile su qualunque host.
    public const CONFIG_FFMPEG_SERVICE_URL = 'ffmpeg_service_url';
    public const DEFAULT_FFMPEG_SERVICE_URL = 'http://localhost:3100';
    public const CONFIG_CACHE_BASE_PATH = 'cache_base_path';

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
