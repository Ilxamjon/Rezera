<?php

namespace App\Domain\Resources\Enums;

enum ResourceType: string
{
    case Pc = 'pc';
    case Console = 'console';
    case Room = 'room';
    case Table = 'table';
    case Desk = 'desk';
    case Court = 'court';
    case Other = 'other';
}
