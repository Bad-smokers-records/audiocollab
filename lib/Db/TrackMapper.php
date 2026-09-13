<?php
namespace OCA\Audiocollab\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class TrackMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'audiocollab_tracks', Track::class);
    }

    public function findByProjectId(int $projectId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_tracks')
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->orderBy('sort_order', 'ASC');
        return $this->findEntities($qb);
    }

    public function findByProjectAndFile(int $projectId, int $fileId): ?Track {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_tracks')
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }
}
