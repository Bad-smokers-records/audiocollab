<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000005Date20260912130000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('audiocollab_versions');

        if (!$table->hasColumn('native_revision_id')) {
            $table->addColumn('native_revision_id', 'bigint', ['notnull' => false]);
        }

        return $schema;
    }
}
