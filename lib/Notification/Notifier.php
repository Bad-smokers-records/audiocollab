<?php
namespace OCA\Audiocollab\Notification;

use OCA\Audiocollab\AppInfo\Application;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {

    private $rootFolder;
    private $userManager;
    private $urlGenerator;

    public function __construct(
        IRootFolder $rootFolder,
        IUserManager $userManager,
        IURLGenerator $urlGenerator
    ) {
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->urlGenerator = $urlGenerator;
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return 'AudioCollab';
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== Application::APP_ID) {
            throw new UnknownNotificationException();
        }

        $subject = $notification->getSubject();
        if ($subject !== 'new-comment' && $subject !== 'new-reply') {
            throw new UnknownNotificationException();
        }

        $params = $notification->getSubjectParameters();
        $fileId = (int)($params['fileId'] ?? 0);

        $node = $this->rootFolder->getUserFolder($notification->getUser())->getFirstNodeById($fileId);
        if ($node === null) {
            // L'utente non ha (più) accesso al file: non ha senso mostrare la notifica
            throw new AlreadyProcessedException();
        }

        $authorUid = (string)($params['authorUid'] ?? '');
        $authorName = $this->userManager->getDisplayName($authorUid) ?? $authorUid;
        $timestampSeconds = (float)($params['timestampSeconds'] ?? 0);
        $timeLabel = $this->formatTime($timestampSeconds);

        $link = $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', [
            'fileid' => $fileId,
            'openfile' => 'true',
        ]);
        $link .= '&ac_t=' . rawurlencode((string)$timestampSeconds);
        if (!empty($params['commentId'])) {
            $link .= '&ac_comment=' . rawurlencode((string)$params['commentId']);
        }
        if (!empty($params['versionId'])) {
            $link .= '&ac_version=' . rawurlencode((string)$params['versionId']);
        }

        $text = $subject === 'new-reply'
            ? $authorName . ' ha risposto a un commento su "' . $node->getName() . '" a ' . $timeLabel
            : $authorName . ' ha commentato "' . $node->getName() . '" a ' . $timeLabel;

        $notification
            ->setParsedSubject($text)
            ->setLink($link);

        try {
            $notification->setIcon($this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
            ));
        } catch (\Throwable $e) {
            // icona opzionale, non blocca la notifica
        }

        if (!empty($params['body'])) {
            $notification->setParsedMessage((string)$params['body']);
        }

        $action = $notification->createAction();
        $action->setLabel('listen')
            ->setParsedLabel('Ascolta il commento')
            ->setLink($link, IAction::TYPE_WEB)
            ->setPrimary(true);
        $notification->addParsedAction($action);

        return $notification;
    }

    private function formatTime(float $seconds): string {
        $seconds = max(0, (int)round($seconds));
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
