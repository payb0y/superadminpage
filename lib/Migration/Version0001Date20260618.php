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

        if (!$schema->hasTable('superadminpage_geocode_cache')) {
            $table = $schema->createTable('superadminpage_geocode_cache');

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
            // on index identifiers, and the auto-generated PK name for this
            // table ('superadminpage_geocode_cache_pkey') would exceed it.
            $table->setPrimaryKey(['addr_hash'], 'superadmpage_geocache_pk');
        }

        return $schema;
    }
}
