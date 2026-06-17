<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20260618 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Nextcloud caps table identifiers at 30 chars including the `oc_`
        // prefix, so `superadminpage_geocode_cache` (31 with prefix) is out.
        // Shorten "geocode_cache" → "geocache" to fit: final table name is
        // `oc_superadminpage_geocache` (26 chars).
        if (!$schema->hasTable('superadminpage_geocache')) {
            $table = $schema->createTable('superadminpage_geocache');

            $table->addColumn('addr_hash', Types::STRING, [
                'notnull' => true,
                'length'  => 64,
            ]);
            $table->addColumn('lat', Types::DECIMAL, [
                'notnull'   => false,
                'precision' => 10,
                'scale'     => 7,
            ]);
            $table->addColumn('lng', Types::DECIMAL, [
                'notnull'   => false,
                'precision' => 10,
                'scale'     => 7,
            ]);
            $table->addColumn('display_name', Types::STRING, [
                'notnull' => false,
                'length'  => 255,
                'default' => null,
            ]);
            $table->addColumn('source', Types::STRING, [
                'notnull' => true,
                'length'  => 32,
                'default' => 'nominatim',
            ]);
            $table->addColumn('created_at', Types::BIGINT, [
                'notnull'  => true,
                'unsigned' => true,
            ]);

            // Explicit short PK name — Nextcloud enforces a 30-char limit
            // on index identifiers.
            $table->setPrimaryKey(['addr_hash'], 'sap_geocache_pk');
        }

        return $schema;
    }
}
