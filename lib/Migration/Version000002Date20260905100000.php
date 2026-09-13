<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000002Date20260905100000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('audiocollab_versions');

        if (!$table->hasColumn('artist_override')) {
            $table->addColumn('artist_override', 'string', ['notnull' => false, 'length' => 255]);
        }
        if (!$table->hasColumn('title_override')) {
            $table->addColumn('title_override', 'string', ['notnull' => false, 'length' => 255]);
        }

        return $schema;
    }
}
