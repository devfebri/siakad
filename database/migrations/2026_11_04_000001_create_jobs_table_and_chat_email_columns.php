<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Antrian email (QUEUE_CONNECTION=database), dijalankan lewat cron schedule:run.
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (Schema::hasTable('chat_sessions') && ! Schema::hasColumn('chat_sessions', 'email_balasan_at')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                // Pencegah email beruntun: kapan terakhir email balasan ke pengunjung / pemberitahuan ke petugas dikirim.
                $table->timestamp('email_balasan_at')->nullable()->after('closed_at');
                $table->timestamp('email_admin_at')->nullable()->after('email_balasan_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_sessions', 'email_balasan_at')) {
            Schema::table('chat_sessions', fn (Blueprint $table) => $table->dropColumn(['email_balasan_at', 'email_admin_at']));
        }
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
    }
};
