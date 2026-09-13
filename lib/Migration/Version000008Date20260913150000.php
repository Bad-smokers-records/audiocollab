<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000008Date20260913150000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $versions = $schema->getTable('audiocollab_versions');
        if (!$versions->hasColumn('status')) {
            $versions->addColumn('status', 'string', [
                'notnull' => true,
                'length' => 20,
                'default' => 'draft',
            ]);
        }

        return $schema;
    }
}
