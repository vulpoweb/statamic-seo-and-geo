<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for sites running statamic/eloquent-driver. Only loaded when the
 * addon's storage driver resolves to eloquent, so a flat-file site never sees
 * them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('redirects'), function (Blueprint $table) {
            $table->id();
            $table->string('from_path');
            $table->string('to_path');
            $table->unsignedSmallInteger('status')->default(301);
            $table->string('match_type')->default('exact');
            $table->boolean('active')->default(true);
            $table->string('source')->default('manual');
            $table->timestamp('created_at')->nullable();

            $table->index('from_path');
            $table->index('active');
        });

        Schema::create($this->table('not_found'), function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->unsignedInteger('hits')->default(0);
            $table->string('referer')->nullable();
            $table->timestamp('last_seen')->nullable();

            $table->unique('path');
            $table->index('last_seen');
        });

        Schema::create($this->table('ai_crawlers'), function (Blueprint $table) {
            $table->id();
            $table->string('date');
            $table->string('bot');
            $table->unsignedInteger('hits')->default(0);
            $table->string('last_path')->nullable();
            $table->timestamp('last_seen')->nullable();

            $table->unique(['date', 'bot']);
            $table->index('last_seen');
        });

        Schema::create($this->table('uris'), function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('uri');

            $table->unique('key');
        });
    }

    public function down(): void
    {
        foreach (['uris', 'ai_crawlers', 'not_found', 'redirects'] as $set) {
            Schema::dropIfExists($this->table($set));
        }
    }

    private function table(string $set): string
    {
        return (string) config("seo.storage.tables.{$set}", 'vulpo_seo_'.$set);
    }
};
