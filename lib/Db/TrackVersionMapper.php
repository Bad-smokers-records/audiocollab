<?php
namespace OCA\Audiocollab\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class TrackVersionMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'audiocollab_versions', TrackVersion::class);
    }

    public function findLatestByFileId(int $fileId): ?TrackVersion {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_versions')
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
            ->orderBy('version_number', 'DESC')
            ->setMaxResults(1);
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function findAllByFileId(int $fileId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_versions')
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
            ->orderBy('version_number', 'ASC');
        return $this->findEntities($qb);
    }

    public function findByEtag(int $fileId, string $etag): ?TrackVersion {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_versions')
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('etag', $qb->createNamedParameter($etag)))
            ->orderBy('version_number', 'DESC')
            ->setMaxResults(1);
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    public function findByIdForFile(int $id, int $fileId): ?TrackVersion {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_versions')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }
}
