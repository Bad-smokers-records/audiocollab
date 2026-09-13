<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000009Date20260913170000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $versions = $schema->getTable('audiocollab_versions');
        if (!$versions->hasColumn('extracted_artist')) {
            $versions->addColumn('extracted_artist', 'string', [
                'notnull' => false,
                'length' => 255,
            ]);
        }
        if (!$versions->hasColumn('extracted_title')) {
            $versions->addColumn('extracted_title', 'string', [
                'notnull' => false,
                'length' => 255,
            ]);
        }
        if (!$versions->hasColumn('source_codec')) {
            $versions->addColumn('source_codec', 'string', [
                'notnull' => false,
                'length' => 32,
            ]);
        }
        if (!$versions->hasColumn('source_bitrate_kbps')) {
            $versions->addColumn('source_bitrate_kbps', 'integer', [
                'notnull' => false,
            ]);
        }

        return $schema;
    }
}
