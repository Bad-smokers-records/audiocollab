<?php
namespace OCA\Audiocollab\Controller;

use OCA\Audiocollab\AppInfo\Application;
use OCA\Audiocollab\Db\CommentMapper;
use OCA\Audiocollab\Db\TrackVersionMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class DashboardController extends Controller {

    private const MAX_TRACKS = 10;
    private const MAX_COMMENTS = 15;
    // Quante righe recenti considerare prima di filtrare per accesso: più
    // larga della lista finale perché alcune verranno scartate (file non
    // più accessibili all'utente corrente, es. condivisione revocata).
    private const CANDIDATE_POOL = 60;

    public function __construct(
        string $appName,
        IRequest $request,
        private IRootFolder $rootFolder,
        private IUserSession $userSession,
        private IURLGenerator $urlGenerator,
        private IGroupManager $groupManager,
        private TrackVersionMapper $versionMapper,
        private CommentMapper $commentMapper,
        private IAppConfig $appConfig,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function get(): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }
        $userFolder = $this->rootFolder->getUserFolder($user->getUID());

        $features = [
            'loudnessMatching' => $this->appConfig->getAppValueBool(Application::CONFIG_LOUDNESS_MATCHING, true),
            'commentNotifications' => $this->appConfig->getAppValueBool(Application::CONFIG_COMMENT_NOTIFICATIONS, true),
            'trackStatus' => $this->appConfig->getAppValueBool(Application::CONFIG_TRACK_STATUS, true),
        ];

        return new JSONResponse([
            'recentTracks' => $this->buildRecentTracks($userFolder, $features['trackStatus']),
            'recentComments' => $this->buildRecentComments($userFolder),
            'stats' => $this->buildStats($features['trackStatus']),
            'isAdmin' => $this->groupManager->isAdmin($user->getUID()),
            'features' => $features,
        ]);
    }

    private function buildRecentTracks($userFolder, bool $trackStatusEnabled): array {
        $candidates = $this->versionMapper->findRecentVersions(self::CANDIDATE_POOL);

        $seenFileIds = [];
        $tracks = [];
        foreach ($candidates as $version) {
            $fileId = $version->getFileId();
            if (isset($seenFileIds[$fileId])) {
                continue;
            }
            $seenFileIds[$fileId] = true;

            $nodes = $userFolder->getById($fileId);
            if (empty($nodes)) {
                // File non (più) accessibile a questo utente: salta.
                continue;
            }
            $node = $nodes[0];

            $tracks[] = [
                'fileId' => $fileId,
                'name' => $this->resolveTrackName($version, $node->getName()),
                'artist' => $version->getArtistOverride() ?: $version->getExtractedArtist(),
                'status' => $trackStatusEnabled ? $version->getStatus() : null,
                'lastActivity' => $version->getUploadedAt(),
                'link' => $this->buildFileLink($fileId),
            ];

            if (count($tracks) >= self::MAX_TRACKS) {
                break;
            }
        }

        return $tracks;
    }

    private function buildRecentComments($userFolder): array {
        $candidates = $this->commentMapper->findRecentComments(self::CANDIDATE_POOL);
        if (empty($candidates)) {
            return [];
        }

        $versionIds = array_values(array_unique(array_map(
            fn($c) => $c->getVersionId(),
            $candidates
        )));
        $versions = $this->versionMapper->findByIds($versionIds);
        $versionsById = [];
        foreach ($versions as $v) {
            $versionsById[$v->getId()] = $v;
        }

        $accessCache = [];
        $comments = [];
        foreach ($candidates as $comment) {
            $version = $versionsById[$comment->getVersionId()] ?? null;
            if ($version === null) {
                continue;
            }
            $fileId = $version->getFileId();

            if (!array_key_exists($fileId, $accessCache)) {
                $nodes = $userFolder->getById($fileId);
                $accessCache[$fileId] = empty($nodes) ? null : $nodes[0];
            }
            $node = $accessCache[$fileId];
            if ($node === null) {
                continue;
            }

            $comments[] = [
                'fileId' => $fileId,
                'commentId' => $comment->getId(),
                'trackName' => $this->resolveTrackName($version, $node->getName()),
                'authorUid' => $comment->getAuthorUid(),
                'excerpt' => mb_substr($comment->getBody(), 0, 140),
                'timestampSeconds' => $comment->getTimestampSeconds(),
                'createdAt' => $comment->getCreatedAt(),
                'link' => $this->buildFileLink($fileId, $comment->getId(), $version->getId(), $comment->getTimestampSeconds()),
            ];

            if (count($comments) >= self::MAX_COMMENTS) {
                break;
            }
        }

        return $comments;
    }

    /**
     * Conteggio globale (non filtrato per accesso utente): "tracce gestite
     * dall'app" è inteso come metrica dell'installazione nel suo complesso,
     * non della sola vista dell'utente corrente.
     */
    private function buildStats(bool $trackStatusEnabled): array {
        $rows = $this->versionMapper->findAllForStats();

        $latestStatusByFile = [];
        foreach ($rows as $row) {
            $fileId = (int)$row['file_id'];
            if (!isset($latestStatusByFile[$fileId])) {
                // Righe già ordinate per file_id, version_number DESC:
                // la prima occorrenza per file è la versione più recente.
                $latestStatusByFile[$fileId] = $row['status'];
            }
        }

        $statusBreakdown = null;
        if ($trackStatusEnabled) {
            $statusBreakdown = ['draft' => 0, 'in_review' => 0, 'approved' => 0];
            foreach ($latestStatusByFile as $status) {
                if (isset($statusBreakdown[$status])) {
                    $statusBreakdown[$status]++;
                }
            }
        }

        return [
            'totalTracks' => count($latestStatusByFile),
            'statusBreakdown' => $statusBreakdown,
        ];
    }

    private function resolveTrackName($version, string $fileName): string {
        $title = $version->getTitleOverride() ?: $version->getExtractedTitle();
        return $title ?: pathinfo($fileName, PATHINFO_FILENAME);
    }

    private function buildFileLink(int $fileId, ?int $commentId = null, ?int $versionId = null, ?float $timestampSeconds = null): string {
        $link = $this->urlGenerator->linkToRoute('files.viewcontroller.showFile', [
            'fileid' => $fileId,
            'openfile' => 'true',
        ]);
        if ($commentId !== null) {
            $link .= '&ac_comment=' . rawurlencode((string)$commentId);
        }
        if ($versionId !== null) {
            $link .= '&ac_version=' . rawurlencode((string)$versionId);
        }
        if ($timestampSeconds !== null) {
            $link .= '&ac_t=' . rawurlencode((string)$timestampSeconds);
        }
        return $link;
    }
}
