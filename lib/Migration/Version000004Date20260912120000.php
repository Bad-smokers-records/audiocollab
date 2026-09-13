<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000004Date20260912120000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('audiocollab_versions');

        if (!$table->hasColumn('etag')) {
            $table->addColumn('etag', 'string', ['notnull' => false, 'length' => 64]);
        }

        return $schema;
    }
}
