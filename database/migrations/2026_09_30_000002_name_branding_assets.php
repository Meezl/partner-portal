<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Branding assets were stored as bare URLs, so every screen listing them showed
 * the hashed storage filename. They are now {name, url}, carrying the name the
 * partner gave the file; existing rows fall back to the basename so the whole
 * column is one shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('branding_requirements')->select('id', 'assets')->get() as $branding) {
            $assets = json_decode($branding->assets ?? 'null', true);

            if (! is_array($assets) || $assets === []) {
                continue;
            }

            $converted = array_values(array_map(
                fn ($asset) => is_array($asset) ? $asset : [
                    'name' => urldecode(basename((string) $asset)),
                    'url' => (string) $asset,
                ],
                $assets,
            ));

            DB::table('branding_requirements')
                ->where('id', $branding->id)
                ->update(['assets' => json_encode($converted)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('branding_requirements')->select('id', 'assets')->get() as $branding) {
            $assets = json_decode($branding->assets ?? 'null', true);

            if (! is_array($assets) || $assets === []) {
                continue;
            }

            $urls = array_values(array_map(
                fn ($asset) => is_array($asset) ? ($asset['url'] ?? '') : (string) $asset,
                $assets,
            ));

            DB::table('branding_requirements')
                ->where('id', $branding->id)
                ->update(['assets' => json_encode($urls)]);
        }
    }
};
