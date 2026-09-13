<?php
namespace OCA\Audiocollab\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class CommentMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'audiocollab_comments', Comment::class);
    }

    public function findByVersionId(int $versionId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_comments')
            ->where($qb->expr()->eq('version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)))
            ->orderBy('timestamp_seconds', 'ASC');
        return $this->findEntities($qb);
    }

    public function findById(int $id): ?Comment {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_comments')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        try {
            return $this->findEntity($qb);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return null;
        }
    }

    public function deleteByParentId(int $parentId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('audiocollab_comments')
            ->where($qb->expr()->eq('parent_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
