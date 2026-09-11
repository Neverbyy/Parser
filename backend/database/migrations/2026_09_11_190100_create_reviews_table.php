<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // reviewId из ответа Яндекса — по нему делается upsert, поэтому
            // повторный парсинг обновляет отзывы, а не плодит дубликаты.
            $table->string('yandex_review_id');

            $table->string('author_name')->nullable();
            $table->text('author_avatar_url')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('text')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'yandex_review_id']);

            // Постраничная выдача всегда идёт от свежих к старым.
            $table->index(['organization_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
