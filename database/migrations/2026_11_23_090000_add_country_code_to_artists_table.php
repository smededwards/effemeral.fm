<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable()->after('region')->index();
            $table->boolean('country_manual')->default(false)->after('country_code');
        });

        $countryCodes = array_flip(array_map('mb_strtolower', config('countries')));
        $countryCodes['england'] = 'GB';

        DB::table('artists')->whereNotNull('region')->orderBy('id')->chunkById(100, function ($artists) use ($countryCodes): void {
            foreach ($artists as $artist) {
                $countryCode = $countryCodes[mb_strtolower(trim($artist->region))] ?? null;

                if ($countryCode !== null) {
                    DB::table('artists')->where('id', $artist->id)->update([
                        'country_code' => $countryCode,
                        'country_manual' => $artist->region_manual,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->dropIndex(['country_code']);
            $table->dropColumn(['country_code', 'country_manual']);
        });
    }
};
