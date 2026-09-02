<?php

namespace App\Domain\Businesses\Enums;

enum OnboardingStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
