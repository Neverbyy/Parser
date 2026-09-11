<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Регистрации в приложении нет — единственный пользователь заводится здесь.
     *
     * Логин: demo@example.com
     * Пароль: password
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Демо-пользователь',
                // Модель приводит password к hashed-касту, хешируется само.
                'password' => 'password',
            ],
        );
    }
}
