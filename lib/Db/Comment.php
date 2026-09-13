<?php
namespace OCA\Audiocollab\Db;

use OCP\AppFramework\Db\Entity;

class Comment extends Entity {
    protected $versionId;
    protected $authorUid;
    protected $timestampSeconds;
    protected $body;
    protected $createdAt;
    protected $parentId;
    protected $status;

    public function __construct() {
        $this->addType('versionId', 'integer');
        $this->addType('timestampSeconds', 'float');
        $this->addType('parentId', 'integer');
    }
}
