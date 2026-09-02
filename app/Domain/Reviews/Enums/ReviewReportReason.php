<?php

namespace App\Domain\Reviews\Enums;

enum ReviewReportReason: string
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case Harassment = 'harassment';
    case Hate = 'hate';
    case Fake = 'fake';
    case Irrelevant = 'irrelevant';
    case PersonalInformation = 'personal_information';
    case Other = 'other';
}
