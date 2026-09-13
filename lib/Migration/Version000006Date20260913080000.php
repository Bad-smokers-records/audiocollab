<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000006Date20260913080000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('audiocollab_versions');

        if (!$table->hasColumn('integrated_loudness')) {
            $table->addColumn('integrated_loudness', 'float', ['notnull' => false]);
        }
        if (!$table->hasColumn('loudness_range')) {
            $table->addColumn('loudness_range', 'float', ['notnull' => false]);
        }
        if (!$table->hasColumn('true_peak')) {
            $table->addColumn('true_peak', 'float', ['notnull' => false]);
        }

        return $schema;
    }
}
