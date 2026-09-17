<?php
namespace OCA\Audiocollab\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * audiocollab_versions aveva un indice su track_id (sempre 0, mai usato: la
 * gestione progetti/tracce usa file_id) ma nessuno su file_id, che è invece
 * la colonna filtrata da ogni singola query di questa tabella
 * (findLatestByFileId, findAllByFileId, findByEtag, findByIdForFile...).
 */
class Version000010Date20260914000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $versions = $schema->getTable('audiocollab_versions');
        if (!$versions->hasIndex('ac_versions_fileid_idx')) {
            $versions->addIndex(['file_id'], 'ac_versions_fileid_idx');
        }

        return $schema;
    }
}
