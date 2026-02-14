<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add mediafire_url column if it doesn't exist
        if (!Schema::hasColumn('videos', 'mediafire_url')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->string('mediafire_url', 500)->nullable()->after('thumbnail_path');
            });
        }

        // Make file_path nullable using raw SQL (avoids doctrine/dbal requirement)
        DB::statement('ALTER TABLE videos MODIFY file_path VARCHAR(255) NULL');
    }

    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('mediafire_url');
        });

        DB::statement('ALTER TABLE videos MODIFY file_path VARCHAR(255) NOT NULL');
    }
};
