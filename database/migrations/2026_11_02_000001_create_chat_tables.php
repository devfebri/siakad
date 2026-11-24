<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->unique();
                $table->string('nama', 100);
                $table->string('email', 150);
                $table->string('whatsapp', 25)->nullable();
                $table->string('topik', 60);
                $table->string('status', 15)->default('menunggu')->index(); // menunggu | dilayani | selesai
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->boolean('persetujuan')->default(false);
                $table->timestamp('visitor_typing_at')->nullable();
                $table->timestamp('agent_typing_at')->nullable();
                $table->timestamp('last_visitor_at')->nullable();
                $table->timestamp('taken_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedTinyInteger('rating')->nullable();
                $table->string('rating_komentar', 300)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('session_id');
                $table->string('sender', 10); // visitor | agent | bot | system
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['session_id', 'id']);
            });
        }

        if (! Schema::hasTable('chat_attachments')) {
            Schema::create('chat_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('message_id')->index();
                $table->string('path', 200);
                $table->string('nama_asli', 200);
                $table->string('mime', 80);
                $table->unsignedInteger('ukuran');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_faqs')) {
            Schema::create('chat_faqs', function (Blueprint $table) {
                $table->id();
                $table->string('pertanyaan', 200);
                $table->string('kata_kunci', 300);
                $table->text('jawaban');
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_settings')) {
            Schema::create('chat_settings', function (Blueprint $table) {
                $table->string('key', 60)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_agent_status')) {
            Schema::create('chat_agent_status', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('status', 10)->default('offline'); // online | istirahat | offline
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['chat_agent_status', 'chat_settings', 'chat_faqs', 'chat_attachments', 'chat_messages', 'chat_sessions'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
