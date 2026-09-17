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

    /**
     * Le versioni caricate più di recente (across tutti i file), per la
     * dashboard. Non deduplicate per file_id: chi chiama tiene solo la prima
     * occorrenza per file (già la più recente, essendo la query ordinata).
     */
    public function findRecentVersions(int $limit): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_versions')
            ->orderBy('uploaded_at', 'DESC')
            ->setMaxResults($limit);
        return $this->findEntities($qb);
    }

    /**
     * Solo le colonne necessarie per calcolare le statistiche della
     * dashboard (numero tracce, stato più recente per file), ordinate per
     * poter tenere in PHP solo la prima occorrenza (più recente) per file.
     *
     * @return array<int, array{file_id: int, version_number: int, status: string}>
     */
    public function findAllForStats(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('file_id', 'version_number', 'status')
            ->from('audiocollab_versions')
            ->orderBy('file_id', 'ASC')
            ->addOrderBy('version_number', 'DESC');
        return $qb->executeQuery()->fetchAll();
    }

    /**
     * @param int[] $ids
     * @return TrackVersion[]
     */
    public function findByIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_versions')
            ->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
        return $this->findEntities($qb);
    }
}
