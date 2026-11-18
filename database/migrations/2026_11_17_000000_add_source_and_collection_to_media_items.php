<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->string('source', 16)->default('library')->after('path_hash');
            // The first directory of a RPG/Maps file, which need not be a system.
            $table->string('collection')->nullable()->after('system');
            $table->string('system')->nullable()->change();
        });

    }// end up()

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn(['source', 'collection']);
        });

    }// end down()
};
