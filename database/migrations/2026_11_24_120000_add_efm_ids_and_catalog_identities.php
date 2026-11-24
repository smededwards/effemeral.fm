<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add nullable columns first so existing records can be backfilled safely.
        foreach ([
            'artists' => 'ART',
            'albums' => 'ALB',
            'tracks' => 'TRK',
            'genres' => 'GEN',
            'album_playlists' => 'PLY',
        ] as $table => $type) {
            if (! Schema::hasColumn($table, 'efm_id')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->string('efm_id', 32)->nullable()->unique()->after('id');
                });
            }
            $this->backfillEfmIds($table, $type);
        }

        if (! Schema::hasTable('catalog_identities')) {
            Schema::create('catalog_identities', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 32);
                $table->string('entity_type', 32);
                $table->string('identifier_type', 64);
                $table->string('identifier', 191);
                $table->text('provider_url')->nullable();
                $table->string('provider_name')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(
                    ['provider', 'entity_type', 'identifier_type', 'identifier'],
                    'catalog_identities_provider_entity_identifier_unique',
                );
            });
        }

        if (! Schema::hasTable('catalog_identity_links')) {
            Schema::create('catalog_identity_links', function (Blueprint $table): void {
                $table->foreignId('catalog_identity_id')->constrained()->cascadeOnDelete();
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->string('match_status')->default('confirmed');
                $table->string('match_method');
                $table->unsignedTinyInteger('confidence')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->unique(
                    ['catalog_identity_id', 'entity_type', 'entity_id'],
                    'catalog_identity_links_identity_entity_unique',
                );
                $table->index(['entity_type', 'entity_id']);
            });
        }

        // Preserve existing provider columns while creating the new canonical identity links.
        $this->backfillIdentities();
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_identity_links');
        Schema::dropIfExists('catalog_identities');

        foreach (['artists', 'albums', 'tracks', 'genres', 'album_playlists'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropUnique($table->getTable().'_efm_id_unique');
                $table->dropColumn('efm_id');
            });
        }
    }

    private function backfillEfmIds(string $table, string $type): void
    {
        $nameColumn = in_array($table, ['albums', 'tracks'], true) ? 'title' : 'name';
        $quotedTable = '`'.str_replace('`', '``', $table).'`';
        $quotedNameColumn = '`'.str_replace('`', '``', $nameColumn).'`';

        // Use one set-based update per table so migrations do not issue one query per record.
        DB::statement(
            "UPDATE {$quotedTable} SET efm_id = CONCAT('EFM-', ?, '-', "
            ."LPAD(FLOOR((id - 1) / 999) + 1, 3, '0'), '-', "
            ."LPAD(CASE WHEN ASCII(UPPER(LEFT({$quotedNameColumn}, 1))) BETWEEN 65 AND 90 "
            ."THEN ASCII(UPPER(LEFT({$quotedNameColumn}, 1))) - 64 ELSE 0 END, 3, '0'), '-', "
            ."LPAD(MOD(id - 1, 999) + 1, 3, '0')) WHERE efm_id IS NULL",
            [$type],
        );
    }

    private function backfillIdentities(): void
    {
        // These mappings mirror the provider columns already present in the catalogue tables.
        $sources = [
            'artists' => [
                ['provider' => 'spotify', 'entity_type' => 'artist', 'identifier_type' => 'spotify_id', 'column' => 'spotify_id', 'url_prefix' => 'https://open.spotify.com/artist/'],
                ['provider' => 'lastfm', 'entity_type' => 'artist', 'identifier_type' => 'mbid', 'column' => 'lastfm_mbid'],
                ['provider' => 'musicbrainz', 'entity_type' => 'artist', 'identifier_type' => 'mbid', 'column' => 'musicbrainz_mbid', 'url_prefix' => 'https://musicbrainz.org/artist/'],
            ],
            'albums' => [
                ['provider' => 'spotify', 'entity_type' => 'album', 'identifier_type' => 'spotify_id', 'column' => 'spotify_id', 'url_prefix' => 'https://open.spotify.com/album/'],
                ['provider' => 'lastfm', 'entity_type' => 'album', 'identifier_type' => 'mbid', 'column' => 'lastfm_mbid'],
                ['provider' => 'musicbrainz', 'entity_type' => 'album', 'identifier_type' => 'release_group_mbid', 'column' => 'musicbrainz_release_group_mbid', 'url_prefix' => 'https://musicbrainz.org/release-group/'],
            ],
            'tracks' => [
                ['provider' => 'spotify', 'entity_type' => 'track', 'identifier_type' => 'spotify_id', 'column' => 'spotify_id', 'url_prefix' => 'https://open.spotify.com/track/'],
                ['provider' => 'lastfm', 'entity_type' => 'track', 'identifier_type' => 'mbid', 'column' => 'lastfm_mbid'],
            ],
            'album_playlists' => [
                ['provider' => 'spotify', 'entity_type' => 'playlist', 'identifier_type' => 'spotify_id', 'column' => 'spotify_playlist_id', 'url_prefix' => 'https://open.spotify.com/playlist/'],
            ],
        ];

        foreach ($sources as $table => $identifiers) {
            foreach ($identifiers as $source) {
                $tableName = '`'.str_replace('`', '``', $table).'`';
                $identifierColumn = '`'.str_replace('`', '``', $source['column']).'`';
                $nameColumn = in_array($table, ['albums', 'tracks'], true) ? '`title`' : '`name`';
                $providerUrl = isset($source['url_prefix']) ? 'CONCAT(?, '.$identifierColumn.')' : 'NULL';

                DB::statement(
                    'INSERT IGNORE INTO catalog_identities '
                    .'(provider, entity_type, identifier_type, identifier, provider_url, provider_name, created_at, updated_at) '
                    ."SELECT ?, ?, ?, {$identifierColumn}, {$providerUrl}, {$nameColumn}, NOW(), NOW() "
                    ."FROM {$tableName} WHERE {$identifierColumn} IS NOT NULL AND {$identifierColumn} != ''",
                    array_values(array_filter([
                        $source['provider'],
                        $source['entity_type'],
                        $source['identifier_type'],
                        $source['url_prefix'] ?? null,
                    ], fn (mixed $value): bool => $value !== null)),
                );

                DB::statement(
                    'INSERT IGNORE INTO catalog_identity_links '
                    .'(catalog_identity_id, entity_type, entity_id, match_status, match_method, confidence, is_primary, created_at, updated_at) '
                    ."SELECT identities.id, ?, records.id, 'confirmed', ?, 100, 1, NOW(), NOW() "
                    ."FROM {$tableName} records "
                    .'INNER JOIN catalog_identities identities ON identities.provider = ? '
                    .'AND identities.entity_type = ? AND identities.identifier_type = ? '
                    ."AND identities.identifier = records.{$identifierColumn} "
                    ."WHERE records.{$identifierColumn} IS NOT NULL AND records.{$identifierColumn} != ''",
                    [
                        $source['entity_type'],
                        $source['identifier_type'],
                        $source['provider'],
                        $source['entity_type'],
                        $source['identifier_type'],
                    ],
                );
            }
        }
    }
};
