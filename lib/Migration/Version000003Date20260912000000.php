<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000003Date20260912000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('audiocollab_comments');

        if (!$table->hasColumn('parent_id')) {
            $table->addColumn('parent_id', 'bigint', ['notnull' => false]);
            $table->addIndex(['parent_id'], 'ac_comments_parent_idx');
        }
        if (!$table->hasColumn('status')) {
            $table->addColumn('status', 'string', ['notnull' => true, 'length' => 16, 'default' => 'open']);
        }

        return $schema;
    }
}
