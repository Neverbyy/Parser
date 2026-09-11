<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * История агрегатов организации: по одной строке на каждое изменение.
     *
     * Таблица organizations хранит только текущее состояние и при повторном
     * разборе перезаписывается. Чтобы можно было ответить «а что изменилось
     * с прошлого раза», значения дополнительно складываются сюда. Снимок
     * неизменяем, поэтому updated_at не нужен.
     */
    public function up(): void
    {
        Schema::create('organization_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('ratings_count')->nullable();
            $table->unsignedInteger('reviews_count')->nullable();
            $table->unsignedInteger('fetched_reviews_count')->default(0);

            $table->timestamp('created_at')->nullable();

            // Выборка «история по организации, свежие сверху».
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_snapshots');
    }
};
