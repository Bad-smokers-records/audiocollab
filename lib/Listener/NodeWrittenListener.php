<?php
namespace OCA\Audiocollab\Listener;

use OCA\Audiocollab\BackgroundJob\GenerateTrackCacheJob;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\FileInfo;

/**
 * Quando un file audio viene caricato/sovrascritto, mette in coda la
 * generazione della cache (transcodifica/waveform/loudness) come job in
 * background, così è già pronta quando l'utente apre il player. Non fa
 * nessun lavoro pesante qui: questo listener gira in modo sincrono nella
 * stessa richiesta dell'upload e non deve rallentarla.
 */
class NodeWrittenListener implements IEventListener {

    private $jobList;

    public function __construct(IJobList $jobList) {
        $this->jobList = $jobList;
    }

    public function handle(Event $event): void {
        if (!($event instanceof NodeWrittenEvent)) {
            return;
        }

        $node = $event->getNode();
        if ($node->getType() !== FileInfo::TYPE_FILE) {
            return;
        }
        if ($node->getMimePart() !== 'audio') {
            return;
        }

        $owner = $node->getOwner();
        if ($owner === null) {
            return;
        }

        $argument = [
            'fileId' => $node->getId(),
            'uid' => $owner->getUID(),
        ];

        if (!$this->jobList->has(GenerateTrackCacheJob::class, $argument)) {
            $this->jobList->add(GenerateTrackCacheJob::class, $argument);
        }
    }
}
