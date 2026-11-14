<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The original 2019 create_failed_jobs_table migration predates the `uuid`
 * column, so databases created from it can't record failed jobs
 * ("Unknown column 'uuid'"). Add it, backfill existing rows, then index it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('uuid')->nullable()->after('id');
        });

        DB::table('failed_jobs')
            ->whereNull('uuid')
            ->orderBy('id')
            ->each(function ($job) {
                DB::table('failed_jobs')
                    ->where('id', $job->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
