<?php

namespace App\Domain\Subscriptions\Enums;

enum EntitlementValueType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case String = 'string';
    case Decimal = 'decimal';
    case Json = 'json';
}
