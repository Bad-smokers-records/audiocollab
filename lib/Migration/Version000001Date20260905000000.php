<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000001Date20260905000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('audiocollab_projects')) {
            $table = $schema->createTable('audiocollab_projects');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
            $table->addColumn('owner_uid', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('root_folder_path', 'string', ['notnull' => true, 'length' => 1024]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['owner_uid'], 'ac_projects_owner_idx');
        }

        if (!$schema->hasTable('audiocollab_tracks')) {
            $table = $schema->createTable('audiocollab_tracks');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('project_id', 'bigint', ['notnull' => true]);
            $table->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
            $table->addColumn('file_id', 'bigint', ['notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['project_id'], 'ac_tracks_project_idx');
            $table->addIndex(['file_id'], 'ac_tracks_fileid_idx');
        }

        if (!$schema->hasTable('audiocollab_versions')) {
            $table = $schema->createTable('audiocollab_versions');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('track_id', 'bigint', ['notnull' => true]);
            $table->addColumn('file_id', 'bigint', ['notnull' => true]);
            $table->addColumn('mp3_cache_path', 'string', ['notnull' => false, 'length' => 1024]);
            $table->addColumn('waveform_json', 'text', ['notnull' => false]);
            $table->addColumn('version_number', 'integer', ['notnull' => true, 'default' => 1]);
            $table->addColumn('uploaded_by', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('uploaded_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['track_id'], 'ac_versions_track_idx');
        }

        if (!$schema->hasTable('audiocollab_comments')) {
            $table = $schema->createTable('audiocollab_comments');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('version_id', 'bigint', ['notnull' => true]);
            $table->addColumn('author_uid', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('timestamp_seconds', 'float', ['notnull' => true]);
            $table->addColumn('body', 'text', ['notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['version_id'], 'ac_comments_version_idx');
        }

        return $schema;
    }
}
