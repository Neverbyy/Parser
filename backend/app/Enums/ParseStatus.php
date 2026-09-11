<?php

namespace App\Enums;

/**
 * Состояние парсинга организации.
 *
 * Фронт ориентируется именно на него: пока статус Parsing — показывает
 * индикатор загрузки, при Failed — error_message из ответа.
 */
enum ParseStatus: string
{
    case Pending = 'pending';
    case Parsing = 'parsing';
    case Ready = 'ready';
    case Failed = 'failed';
}
