<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Modul „Community“: Profile, Pinnwand, Foren, persönliche Nachrichten, Benachrichtigungen, Meldungen */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('handle', 30)->unique();
            $t->string('headline', 120)->nullable();
            $t->text('bio')->nullable();
            $t->string('avatar')->nullable();
            $t->string('cover')->nullable();
            $t->string('location', 80)->nullable();
            $t->string('website')->nullable();
            $t->string('page_title', 120)->nullable();
            $t->string('page_color', 20)->nullable();
            $t->json('page_sections')->nullable();
            $t->boolean('page_published')->default(false);
            $t->boolean('email_notifications')->default(true);
            $t->boolean('messages_open')->default(true);
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('banned_at')->nullable();
            $t->timestamps();
        });

        Schema::create('community_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->string('image')->nullable();
            $t->unsignedInteger('likes_count')->default(0);
            $t->unsignedInteger('comments_count')->default(0);
            $t->boolean('is_pinned')->default(false);
            $t->timestamps();
            $t->index('created_at');
        });

        Schema::create('community_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('community_likes', function (Blueprint $t) {
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->primary(['post_id', 'user_id']);
        });

        Schema::create('community_follows', function (Blueprint $t) {
            $t->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->primary(['follower_id', 'followed_id']);
        });

        Schema::create('community_blocks', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->primary(['user_id', 'blocked_id']);
        });

        Schema::create('community_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('one_read_at')->nullable();
            $t->timestamp('two_read_at')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
            $t->unique(['user_one_id', 'user_two_id']);
        });

        Schema::create('community_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('community_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('forum_boards', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 140)->unique();
            $t->string('description')->nullable();
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_locked')->default(false);
            $t->unsignedInteger('topics_count')->default(0);
            $t->unsignedInteger('replies_count')->default(0);
            $t->timestamps();
        });

        Schema::create('forum_topics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('board_id')->constrained('forum_boards')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 160);
            $t->string('slug', 180);
            $t->text('body');
            $t->boolean('is_pinned')->default(false);
            $t->boolean('is_locked')->default(false);
            $t->unsignedInteger('replies_count')->default(0);
            $t->unsignedInteger('views_count')->default(0);
            $t->timestamp('last_reply_at')->nullable();
            $t->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['board_id', 'is_pinned', 'last_reply_at']);
        });

        Schema::create('forum_replies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('community_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnDelete();
            $t->string('type', 30);
            $t->string('text');
            $t->string('url');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });

        Schema::create('community_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20);
            $t->unsignedBigInteger('target_id');
            $t->string('reason', 500);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['community_reports', 'community_notifications', 'forum_replies', 'forum_topics', 'forum_boards', 'community_messages',
            'community_conversations', 'community_blocks', 'community_follows', 'community_likes', 'community_comments', 'community_posts', 'community_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
