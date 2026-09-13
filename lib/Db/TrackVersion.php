<?php
namespace OCA\Audiocollab\Db;

use OCP\AppFramework\Db\Entity;

class TrackVersion extends Entity {
    protected $trackId;
    protected $fileId;
    protected $mp3CachePath;
    protected $waveformJson;
    protected $versionNumber;
    protected $uploadedBy;
    protected $uploadedAt;
    protected $artistOverride;
    protected $titleOverride;
    protected $etag;
    protected $nativeRevisionId;
    protected $integratedLoudness;
    protected $loudnessRange;
    protected $truePeak;
    protected $status;
    protected $extractedArtist;
    protected $extractedTitle;
    protected $sourceCodec;
    protected $sourceBitrateKbps;

    public function __construct() {
        $this->addType('trackId', 'integer');
        $this->addType('fileId', 'integer');
        $this->addType('versionNumber', 'integer');
        $this->addType('nativeRevisionId', 'integer');
        $this->addType('sourceBitrateKbps', 'integer');
    }
}
