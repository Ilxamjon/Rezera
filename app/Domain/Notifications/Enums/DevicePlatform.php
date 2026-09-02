<?php

namespace App\Domain\Notifications\Enums;

enum DevicePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
    case Web = 'web';
}
