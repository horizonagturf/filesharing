<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Reviewer = 'reviewer';
    case Admin = 'admin';
}
