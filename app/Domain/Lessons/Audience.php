<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

enum Audience: string
{
    case Teacher = 'teacher';
    case Projector = 'projector';
    case Student = 'student';
}
