<?php
namespace OCA\Audiocollab\BackgroundJob;

use OCA\Audiocollab\Service\TrackCacheService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Genera in background la cache (mp3/waveform/loudness/metadati) di una
 * traccia appena caricata o sovrascritta, così è già pronta quando l'utente
 * apre il player invece di generarla al volo alla prima apertura.
 */
class GenerateTrackCacheJob extends QueuedJob {

    private $rootFolder;
    private $userManager;
    private $cacheService;
    private $logger;

    public function __construct(
        ITimeFactory $time,
        IRootFolder $rootFolder,
        IUserManager $userManager,
        TrackCacheService $cacheService,
        LoggerInterface $logger
    ) {
        parent::__construct($time);
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->cacheService = $cacheService;
        $this->logger = $logger;
    }

    protected function run($argument): void {
        $fileId = (int)($argument['fileId'] ?? 0);
        $uid = (string)($argument['uid'] ?? '');
        if (!$fileId || !$uid) {
            return;
        }

        try {
            $user = $this->userManager->get($uid);
            if ($user === null) {
                return;
            }

            $userFolder = $this->rootFolder->getUserFolder($uid);
            $files = $userFolder->getById($fileId);
            if (empty($files)) {
                return;
            }
            $file = $files[0];

            $this->cacheService->ensureVersionForCurrentContent($fileId, $file, $user);
        } catch (\Throwable $e) {
            $this->logger->warning('AudioCollab: generazione cache in background fallita per il file ' . $fileId, [
                'app' => 'audiocollab',
                'exception' => $e,
            ]);
        }
    }
}
