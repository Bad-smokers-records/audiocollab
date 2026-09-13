<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000007Date20260913100000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tracks = $schema->getTable('audiocollab_tracks');
        if (!$tracks->hasColumn('sort_order')) {
            $tracks->addColumn('sort_order', 'integer', ['notnull' => false]);
        }
        if (!$tracks->hasIndex('ac_tracks_project_file_idx')) {
            $tracks->addUniqueIndex(['project_id', 'file_id'], 'ac_tracks_project_file_idx');
        }

        $projects = $schema->getTable('audiocollab_projects');
        if (!$projects->hasColumn('folder_file_id')) {
            $projects->addColumn('folder_file_id', 'bigint', ['notnull' => false]);
            $projects->addUniqueIndex(['folder_file_id'], 'ac_projects_folder_idx');
        }

        return $schema;
    }
}
