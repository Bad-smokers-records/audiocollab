<?php
namespace OCA\Audiocollab\Db;

use OCP\AppFramework\Db\Entity;

class Project extends Entity {
    protected $name;
    protected $ownerUid;
    protected $rootFolderPath;
    protected $folderFileId;
    protected $createdAt;

    public function __construct() {
        $this->addType('folderFileId', 'integer');
    }
}
