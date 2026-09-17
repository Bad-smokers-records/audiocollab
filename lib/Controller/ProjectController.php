<?php
namespace OCA\Audiocollab\Controller;

use OCA\Audiocollab\Db\Project;
use OCA\Audiocollab\Db\ProjectMapper;
use OCA\Audiocollab\Db\Track;
use OCA\Audiocollab\Db\TrackMapper;
use OCA\Audiocollab\Db\TrackVersionMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class ProjectController extends Controller {

    private $rootFolder;
    private $userSession;
    private $projectMapper;
    private $trackMapper;
    private $versionMapper;
    private $urlGenerator;

    public function __construct(
        string $appName,
        IRequest $request,
        IRootFolder $rootFolder,
        IUserSession $userSession,
        ProjectMapper $projectMapper,
        TrackMapper $trackMapper,
        TrackVersionMapper $versionMapper,
        IURLGenerator $urlGenerator
    ) {
        parent::__construct($appName, $request);
        $this->rootFolder = $rootFolder;
        $this->userSession = $userSession;
        $this->projectMapper = $projectMapper;
        $this->trackMapper = $trackMapper;
        $this->versionMapper = $versionMapper;
        $this->urlGenerator = $urlGenerator;
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function get(int $folderId): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }

        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        $nodes = $userFolder->getById($folderId);
        if (empty($nodes) || $nodes[0]->getType() !== FileInfo::TYPE_FOLDER) {
            return new JSONResponse(['error' => 'folder not found'], 404);
        }
        /** @var \OCP\Files\Folder $folder */
        $folder = $nodes[0];

        $project = $this->projectMapper->findByFolderFileId($folderId);
        if ($project === null) {
            $project = new Project();
            $project->setName($folder->getName());
            $project->setOwnerUid($user->getUID());
            $project->setRootFolderPath($folder->getPath());
            $project->setFolderFileId($folderId);
            $project->setCreatedAt(gmdate('Y-m-d H:i:s'));
            $project = $this->projectMapper->insert($project);
        }

        $audioFiles = array_values(array_filter($folder->getDirectoryListing(), function ($node) {
            return $node->getType() === FileInfo::TYPE_FILE && $node->getMimePart() === 'audio';
        }));

        $existingTracks = $this->trackMapper->findByProjectId($project->getId());
        $trackByFileId = [];
        foreach ($existingTracks as $t) {
            $trackByFileId[$t->getFileId()] = $t;
        }

        $nextOrder = count($existingTracks) > 0
            ? max(array_map(fn($t) => $t->getSortOrder() ?? 0, $existingTracks)) + 1
            : 0;

        foreach ($audioFiles as $file) {
            if (!isset($trackByFileId[$file->getId()])) {
                $track = new Track();
                $track->setProjectId($project->getId());
                $track->setName($file->getName());
                $track->setFileId($file->getId());
                $track->setSortOrder($nextOrder);
                $track->setCreatedAt(gmdate('Y-m-d H:i:s'));
                $trackByFileId[$file->getId()] = $this->trackMapper->insert($track);
                $nextOrder++;
            }
        }

        $tracksArray = [];
        foreach ($audioFiles as $file) {
            $track = $trackByFileId[$file->getId()];
            $version = $this->versionMapper->findLatestByFileId($file->getId());
            $tracksArray[] = [
                'fileId' => $file->getId(),
                'trackId' => $track->getId(),
                'name' => ($version !== null && ($version->getTitleOverride() || $version->getExtractedTitle()))
                    ? ($version->getTitleOverride() ?: $version->getExtractedTitle())
                    : pathinfo($file->getName(), PATHINFO_FILENAME),
                'sortOrder' => $track->getSortOrder(),
                'sizeBytes' => $file->getSize(),
                'streamUrl' => $version !== null
                    ? $this->urlGenerator->linkToRoute('audiocollab.api.stream', ['fileid' => $file->getId()]) . '?version=' . $version->getId()
                    : null,
                'integratedLoudness' => $version !== null ? $version->getIntegratedLoudness() : null,
                'loudnessRange' => $version !== null ? $version->getLoudnessRange() : null,
                'truePeak' => $version !== null ? $version->getTruePeak() : null,
                'status' => $version !== null ? $version->getStatus() : null,
            ];
        }

        usort($tracksArray, fn($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return new JSONResponse([
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'folderFileId' => $project->getFolderFileId(),
            ],
            'tracks' => $tracksArray,
        ]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function reorder(int $folderId, array $order): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'not authenticated'], 401);
        }

        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        $folders = $userFolder->getById($folderId);
        if (empty($folders)) {
            // Senza questo controllo, qualunque utente autenticato potrebbe
            // riordinare le tracce di un progetto indovinando il folderId,
            // anche senza avervi accesso.
            return new JSONResponse(['error' => 'folder not found'], 404);
        }
        if (!$folders[0]->isUpdateable()) {
            // Solo visibile (es. condivisione in sola lettura) non basta:
            // riordinare le tracce è un'azione di scrittura.
            return new JSONResponse(['error' => 'only the owner or a collaborator with write access can reorder tracks'], 403);
        }

        $project = $this->projectMapper->findByFolderFileId($folderId);
        if ($project === null) {
            return new JSONResponse(['error' => 'project not found'], 404);
        }

        foreach ($order as $index => $fileId) {
            $track = $this->trackMapper->findByProjectAndFile($project->getId(), (int)$fileId);
            if ($track !== null && $track->getSortOrder() !== $index) {
                $track->setSortOrder($index);
                $this->trackMapper->update($track);
            }
        }

        return new JSONResponse(['ok' => true]);
    }
}
