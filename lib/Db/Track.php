<?php
namespace OCA\Audiocollab\Db;

use OCP\AppFramework\Db\Entity;

class Track extends Entity {
    protected $projectId;
    protected $name;
    protected $fileId;
    protected $sortOrder;
    protected $createdAt;

    public function __construct() {
        $this->addType('projectId', 'integer');
        $this->addType('fileId', 'integer');
        $this->addType('sortOrder', 'integer');
    }
}
