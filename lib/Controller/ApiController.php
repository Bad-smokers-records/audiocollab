<?php
namespace OCA\Audiocollab\Controller;

use OCA\Audiocollab\Db\CommentMapper;
use OCA\Audiocollab\Db\Comment;
use OCA\Audiocollab\Db\TrackVersionMapper;
use OCA\Audiocollab\Db\TrackVersion;
use OCA\Audiocollab\Http\RangeFileResponse;
use OCA\Audiocollab\AppInfo\Application;
use OCA\Audiocollab\Service\TrackCacheService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;

class ApiController extends Controller {

    private $rootFolder;
    private $userSession;
    private $urlGenerator;
    private $commentMapper;
    private $versionMapper;
    private $notificationManager;
    private $cacheService;
    private $appConfig;

    public function __construct(
        string $appName,
        IRequest $request,
        IRootFolder $rootFolder,
        IUserSession $userSession,
        IURLGenerator $urlGenerator,
        CommentMapper $commentMapper,
        TrackVersionMapper $versionMapper,
        INotificationManager $notificationManager,
        TrackCacheService $cacheService,
        IAppConfig $appConfig
    ) {
        parent::__construct($appName, $request);
        $this->rootFolder = $rootFolder;
        $this->userSession = $userSession;
        $this->urlGenerator = $urlGenerator;
        $this->commentMapper = $commentMapper;
        $this->versionMapper = $versionMapper;
        $this->notificationManager = $notificationManager;
        $this->cacheService = $cacheService;
        $this->appConfig = $appConfig;
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getTrack(int $fileid, ?int $version = null): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }

        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        $files = $userFolder->getById($fileid);
        if (empty($files)) {
            return new JSONResponse(['error' => 'file not found'], 404);
        }
        $file = $files[0];
        $realPath = $file->getStorage()->getLocalFile($file->getInternalPath());

        $latest = $this->cacheService->ensureVersionForCurrentContent($fileid, $file, $user);

        if ($version !== null) {
            $displayed = $this->versionMapper->findByIdForFile($version, $fileid);
            if ($displayed === null) {
                return new JSONResponse(['error' => 'version not found'], 404);
            }
        } else {
            $displayed = $latest;
        }

        $comments = $this->commentMapper->findByVersionId($displayed->getId());
        $commentsArray = array_map(function ($c) {
            return [
                'id' => $c->getId(),
                'timestamp_seconds' => $c->getTimestampSeconds(),
                'body' => $c->getBody(),
                'author_uid' => $c->getAuthorUid(),
                'created_at' => $c->getCreatedAt(),
                'parent_id' => $c->getParentId(),
                'status' => $c->getStatus(),
            ];
        }, $comments);


        $waveform = json_decode($displayed->getWaveformJson(), true);

        $artist = $displayed->getArtistOverride() ?: $displayed->getExtractedArtist();
        $title = $displayed->getTitleOverride() ?: $displayed->getExtractedTitle();

        if (!$artist || !$title) {
            // Versioni create prima dell'introduzione della cache dei metadati
            // (extracted_artist/extracted_title): fallback una tantum, le
            // versioni nuove non passano più di qui.
            $metadata = $this->cacheService->fetchLegacyMetadata($realPath);
            if (!$artist) {
                $artist = $metadata['artist'] ?? null;
            }
            if (!$title) {
                $title = $metadata['title'] ?? null;
                if ($title && preg_match('/\.(mp3|wav|flac|m4a)$/i', $title)) {
                    $title = pathinfo($title, PATHINFO_FILENAME);
                }
            }
        }
        if (!$title) {
            $title = pathinfo($file->getName(), PATHINFO_FILENAME);
        }

        $isOwner = $file->getOwner() && $file->getOwner()->getUID() === $user->getUID();
        $canManageStatus = $file->isUpdateable();

        $loudnessMatch = $this->computeSuggestedGain($file, $displayed);

        $allVersions = $this->versionMapper->findAllByFileId($fileid);
        $versionsArray = array_map(function (TrackVersion $v) {
            return [
                'id' => $v->getId(),
                'number' => $v->getVersionNumber(),
                'uploadedBy' => $v->getUploadedBy(),
                'uploadedAt' => $v->getUploadedAt(),
                'nativeRevisionId' => $v->getNativeRevisionId(),
            ];
        }, $allVersions);

        return new JSONResponse([
            'streamUrl' => $this->urlGenerator->linkToRoute('audiocollab.api.stream', ['fileid' => $fileid]) . '?version=' . $displayed->getId(),
            'waveform' => $waveform,
            'comments' => $commentsArray,
            'artist' => $artist,
            'title' => $title,
            'isOwner' => $isOwner,
            'canManageStatus' => $canManageStatus,
            'format' => strtoupper(pathinfo($file->getName(), PATHINFO_EXTENSION)),
            'sizeBytes' => $file->getSize(),
            'version' => [
                'id' => $displayed->getId(),
                'number' => $displayed->getVersionNumber(),
                'uploadedBy' => $displayed->getUploadedBy(),
                'uploadedAt' => $displayed->getUploadedAt(),
                'isLatest' => $displayed->getId() === $latest->getId(),
                'nativeRevisionId' => $displayed->getNativeRevisionId(),
                'integratedLoudness' => $displayed->getIntegratedLoudness(),
                'loudnessRange' => $displayed->getLoudnessRange(),
                'truePeak' => $displayed->getTruePeak(),
                'status' => $displayed->getStatus(),
            ],
            'versions' => $versionsArray,
            'loudnessMatch' => $loudnessMatch,
        ]);
    }


    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function stream(int $fileid, ?int $version = null): Response {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }
        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        if (empty($userFolder->getById($fileid))) {
            // Senza questo controllo, qualunque utente autenticato potrebbe
            // ascoltare l'audio di un file indovinando/iterando il fileid,
            // aggirando completamente la condivisione di Nextcloud.
            return new JSONResponse(['error' => 'file not found'], 404);
        }

        $trackVersion = $version !== null
            ? $this->versionMapper->findByIdForFile($version, $fileid)
            : $this->versionMapper->findLatestByFileId($fileid);
        if ($trackVersion === null) {
            return new JSONResponse(['error' => 'track not initialized'], 404);
        }

        $path = $trackVersion->getMp3CachePath();
        if (!is_string($path) || !file_exists($path)) {
            return new JSONResponse(['error' => 'file not found'], 404);
        }

        $size = filesize($path);
        $start = 0;
        $end = $size - 1;
        $status = Http::STATUS_OK;

        $rangeHeader = $this->request->getHeader('Range');
        if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches)) {
            if ($matches[1] === '' && $matches[2] !== '') {
                // Forma "suffisso" (es. "bytes=-500"): gli ultimi N byte del
                // file, non un offset di fine assoluto.
                $suffixLength = min((int)$matches[2], $size);
                $start = $size - $suffixLength;
            } else {
                if ($matches[1] !== '') {
                    $start = (int)$matches[1];
                }
                if ($matches[2] !== '') {
                    $end = min((int)$matches[2], $size - 1);
                }
            }
            if ($start > $end || $start >= $size) {
                $invalid = new JSONResponse([], Http::STATUS_REQUEST_RANGE_NOT_SATISFIABLE);
                $invalid->addHeader('Content-Range', 'bytes */' . $size);
                return $invalid;
            }
            $status = Http::STATUS_PARTIAL_CONTENT;
        }

        $length = $end - $start + 1;

        $response = new RangeFileResponse($path, $start, $length, $status);
        $response->addHeader('Content-Type', 'audio/mpeg');
        $response->addHeader('Accept-Ranges', 'bytes');
        $response->addHeader('Content-Length', (string)$length);
        if ($status === Http::STATUS_PARTIAL_CONTENT) {
            $response->addHeader('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $size);
        }
        return $response;
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function addComment(int $fileid, float $timestamp_seconds, string $body, ?int $parent_id = null, ?int $version_id = null): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }
        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        if (empty($userFolder->getById($fileid))) {
            // Senza questo controllo, qualunque utente autenticato potrebbe
            // scrivere commenti su un file indovinando/iterando il fileid,
            // aggirando completamente la condivisione di Nextcloud.
            return new JSONResponse(['error' => 'file not found'], 404);
        }

        $version = $version_id !== null
            ? $this->versionMapper->findByIdForFile($version_id, $fileid)
            : $this->versionMapper->findLatestByFileId($fileid);
        if ($version === null) {
            return new JSONResponse(['error' => 'track not initialized'], 400);
        }

        $comment = new Comment();
        $comment->setVersionId($version->getId());
        $comment->setAuthorUid($user->getUID());
        $comment->setTimestampSeconds($timestamp_seconds);
        $comment->setBody($body);
        $comment->setCreatedAt(gmdate('Y-m-d H:i:s'));
        $comment->setParentId($parent_id);
        $comment->setStatus('open');
        $comment = $this->commentMapper->insert($comment);

        $this->notifyCommentParticipants($fileid, $comment, $user->getUID());

        return new JSONResponse([
            'id' => $comment->getId(),
            'timestamp_seconds' => $comment->getTimestampSeconds(),
            'body' => $comment->getBody(),
            'author_uid' => $comment->getAuthorUid(),
            'created_at' => $comment->getCreatedAt(),
            'parent_id' => $comment->getParentId(),
            'status' => $comment->getStatus(),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function updateComment(int $id, string $body): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }

        $comment = $this->commentMapper->findById($id);
        if ($comment === null) {
            return new JSONResponse(['error' => 'comment not found'], 404);
        }
        if ($comment->getAuthorUid() !== $user->getUID()) {
            return new JSONResponse(['error' => 'only the author can edit this comment'], 403);
        }

        $comment->setBody($body);
        $comment = $this->commentMapper->update($comment);

        return new JSONResponse([
            'id' => $comment->getId(),
            'body' => $comment->getBody(),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function deleteComment(int $id): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }

        $comment = $this->commentMapper->findById($id);
        if ($comment === null) {
            return new JSONResponse(['error' => 'comment not found'], 404);
        }
        if ($comment->getAuthorUid() !== $user->getUID()) {
            return new JSONResponse(['error' => 'only the author can delete this comment'], 403);
        }

        $this->commentMapper->deleteByParentId($comment->getId());
        $this->commentMapper->delete($comment);

        return new JSONResponse(['id' => $id]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function setCommentStatus(int $id, string $status): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }
        if (!in_array($status, ['open', 'resolved'], true)) {
            return new JSONResponse(['error' => 'invalid status'], 400);
        }

        $comment = $this->commentMapper->findById($id);
        if ($comment === null) {
            return new JSONResponse(['error' => 'comment not found'], 404);
        }
        if (!$this->userCanWriteCommentTarget($comment, $user)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }

        $comment->setStatus($status);
        $comment = $this->commentMapper->update($comment);

        return new JSONResponse([
            'id' => $comment->getId(),
            'status' => $comment->getStatus(),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function setTrackStatus(int $fileid, string $status, ?int $version_id = null): JSONResponse {
        if (!$this->appConfig->getAppValueBool(Application::CONFIG_TRACK_STATUS, true)) {
            return new JSONResponse(['error' => 'track status feature disabled'], 403);
        }

        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }
        if (!in_array($status, ['draft', 'in_review', 'approved'], true)) {
            return new JSONResponse(['error' => 'invalid status'], 400);
        }

        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        $files = $userFolder->getById($fileid);
        if (empty($files)) {
            return new JSONResponse(['error' => 'file not found'], 404);
        }
        $file = $files[0];
        if (!$file->isUpdateable()) {
            return new JSONResponse(['error' => 'only the owner or a collaborator with write access can change the status'], 403);
        }

        $version = $version_id !== null
            ? $this->versionMapper->findByIdForFile($version_id, $fileid)
            : $this->versionMapper->findLatestByFileId($fileid);
        if ($version === null) {
            return new JSONResponse(['error' => 'track not initialized'], 400);
        }

        $version->setStatus($status);
        $version = $this->versionMapper->update($version);

        return new JSONResponse([
            'id' => $version->getId(),
            'status' => $version->getStatus(),
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function updateMetadata(int $fileid, ?string $artist = null, ?string $title = null): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }

        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        $files = $userFolder->getById($fileid);
        if (empty($files)) {
            return new JSONResponse(['error' => 'file not found'], 404);
        }
        $file = $files[0];

        $isOwner = $file->getOwner() && $file->getOwner()->getUID() === $user->getUID();
        if (!$isOwner) {
            return new JSONResponse(['error' => 'only the owner can edit metadata'], 403);
        }

        $version = $this->versionMapper->findLatestByFileId($fileid);
        if ($version === null) {
            return new JSONResponse(['error' => 'track not initialized'], 400);
        }

        $version->setArtistOverride($artist);
        $version->setTitleOverride($title);
        $this->versionMapper->update($version);

        return new JSONResponse([
            'artist' => $artist,
            'title' => $title,
        ]);
    }

    /**
     * Verifica che l'utente possa scrivere sul file a cui appartiene il
     * commento (proprietario o collaboratore con permesso di scrittura),
     * usando lo stesso meccanismo di condivisione nativo di Nextcloud invece
     * di una lista di permessi nostra.
     */
    private function userCanWriteCommentTarget(Comment $comment, \OCP\IUser $user): bool {
        $versions = $this->versionMapper->findByIds([$comment->getVersionId()]);
        if (empty($versions)) {
            return false;
        }
        $version = $versions[0];
        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        $files = $userFolder->getById($version->getFileId());
        if (empty($files)) {
            return false;
        }
        return $files[0]->isUpdateable();
    }

    /**
     * Notifica gli altri partecipanti quando viene aggiunto un commento o una
     * risposta: per una risposta avvisa tutti gli altri autori del thread,
     * per un commento nuovo (di primo livello) avvisa il proprietario del file.
     */
    private function notifyCommentParticipants(int $fileid, Comment $comment, string $authorUid): void {
        if (!$this->appConfig->getAppValueBool(Application::CONFIG_COMMENT_NOTIFICATIONS, true)) {
            return;
        }

        $userFolder = $this->rootFolder->getUserFolder($authorUid);
        $files = $userFolder->getById($fileid);
        if (empty($files)) {
            return;
        }
        $file = $files[0];

        $recipients = [];
        $isReply = $comment->getParentId() !== null;

        if ($isReply) {
            $allComments = $this->commentMapper->findByVersionId($comment->getVersionId());
            $byId = [];
            foreach ($allComments as $c) {
                $byId[$c->getId()] = $c;
            }

            $rootId = $comment->getParentId();
            while (isset($byId[$rootId]) && $byId[$rootId]->getParentId() !== null) {
                $rootId = $byId[$rootId]->getParentId();
            }

            foreach ($allComments as $c) {
                if ($c->getId() === $rootId || $c->getParentId() === $rootId) {
                    $recipients[$c->getAuthorUid()] = true;
                }
            }
        } else {
            $owner = $file->getOwner();
            if ($owner !== null) {
                $recipients[$owner->getUID()] = true;
            }
        }

        unset($recipients[$authorUid]);
        if (empty($recipients)) {
            return;
        }

        $subject = $isReply ? 'new-reply' : 'new-comment';
        $params = [
            'fileId' => $fileid,
            'versionId' => $comment->getVersionId(),
            'timestampSeconds' => $comment->getTimestampSeconds(),
            'commentId' => $comment->getId(),
            'authorUid' => $authorUid,
            'body' => mb_substr($comment->getBody(), 0, 200),
        ];

        foreach (array_keys($recipients) as $uid) {
            $notification = $this->notificationManager->createNotification();
            $notification->setApp('audiocollab')
                ->setUser($uid)
                ->setDateTime(new \DateTime())
                ->setObject('comment', (string)$comment->getId())
                ->setSubject($subject, $params);
            try {
                $this->notificationManager->notify($notification);
            } catch (\Throwable $e) {
                // non bloccare l'inserimento del commento se la notifica fallisce
            }
        }
    }

    /**
     * Calcola il gain (dB) da applicare in riproduzione per allineare la
     * sonorità di questa traccia alla media delle altre tracce audio nella
     * stessa cartella (il "progetto"). Non modifica alcun file: il gain
     * viene applicato lato client con un GainNode della Web Audio API.
     */
    private function computeSuggestedGain(Node $file, TrackVersion $displayed): ?array {
        if ($displayed->getIntegratedLoudness() === null) {
            return null;
        }

        $parent = $file->getParent();
        $siblingValues = [];
        foreach ($parent->getDirectoryListing() as $sibling) {
            if ($sibling->getId() === $file->getId()) {
                continue;
            }
            if ($sibling->getMimePart() !== 'audio') {
                continue;
            }
            $siblingVersion = $this->versionMapper->findLatestByFileId($sibling->getId());
            if ($siblingVersion !== null && $siblingVersion->getIntegratedLoudness() !== null) {
                $siblingValues[] = $siblingVersion->getIntegratedLoudness();
            }
        }

        if (empty($siblingValues)) {
            return null;
        }

        $targetLoudness = array_sum($siblingValues) / count($siblingValues);
        return [
            'targetLoudness' => $targetLoudness,
            'gainDb' => $targetLoudness - $displayed->getIntegratedLoudness(),
            'comparedTracks' => count($siblingValues),
        ];
    }

}
