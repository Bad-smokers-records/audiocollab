<?php
namespace OCA\Audiocollab\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class ProjectMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'audiocollab_projects', Project::class);
    }

    public function findByFolderFileId(int $folderFileId): ?Project {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('audiocollab_projects')
            ->where($qb->expr()->eq('folder_file_id', $qb->createNamedParameter($folderFileId, IQueryBuilder::PARAM_INT)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }
}
