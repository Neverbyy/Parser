<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            // Идентификатор карточки в Яндекс.Картах (oid из ссылки). Он же
            // ключ, по которому организация не дублируется между пользователями.
            $table->string('yandex_id')->unique();
            $table->text('url');

            $table->string('name')->nullable();
            $table->string('address')->nullable();

            // Средний балл и два РАЗНЫХ счётчика, как их отдаёт Яндекс:
            // ratings_count — сколько людей поставили оценку,
            // reviews_count — сколько из них оставили текстовый отзыв.
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('ratings_count')->nullable();
            $table->unsignedInteger('reviews_count')->nullable();

            // Сколько отзывов реально удалось выгрузить: Яндекс обрезает
            // выдачу на 600 даже если reviews_count кратно больше.
            $table->unsignedInteger('fetched_reviews_count')->default(0);

            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('parsed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
