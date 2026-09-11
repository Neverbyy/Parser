<?php

namespace App\Http\Requests;

use App\Rules\YandexOrganizationUrl;
use Illuminate\Foundation\Http\FormRequest;

class SaveOrganizationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', new YandexOrganizationUrl],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Укажите ссылку на организацию.',
            'url.string' => 'Ссылка должна быть строкой.',
            'url.max' => 'Ссылка слишком длинная.',
        ];
    }

    /**
     * Не url(): такой метод уже есть у Illuminate\Http\Request
     * и возвращает адрес самого запроса.
     */
    public function organizationUrl(): string
    {
        return trim((string) $this->input('url'));
    }
}
