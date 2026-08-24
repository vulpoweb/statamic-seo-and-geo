<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scopes redirects and the 404 log to a site, so a rule for one site does not
 * fire on the others. Null means "every site", which is what a single-site
 * install keeps using.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table('redirects'), function (Blueprint $table) {
            $table->string('site')->nullable()->after('from_path');
            $table->index(['from_path', 'site']);
        });

        Schema::table($this->table('not_found'), function (Blueprint $table) {
            $table->string('site')->nullable()->after('path');
        });

        Schema::table($this->table('not_found'), function (Blueprint $table) {
            $table->dropUnique(['path']);
            $table->unique(['path', 'site']);
        });
    }

    public function down(): void
    {
        Schema::table($this->table('not_found'), function (Blueprint $table) {
            $table->dropUnique(['path', 'site']);
            $table->unique(['path']);
            $table->dropColumn('site');
        });

        Schema::table($this->table('redirects'), function (Blueprint $table) {
            $table->dropIndex(['from_path', 'site']);
            $table->dropColumn('site');
        });
    }

    private function table(string $set): string
    {
        return (string) config("seo.storage.tables.{$set}", 'vulpo_seo_'.$set);
    }
};
