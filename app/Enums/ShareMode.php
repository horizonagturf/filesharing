<?php

namespace App\Enums;

enum ShareMode: string
{
    case Invitation = 'invitation';
    case StaticLink = 'static_link';
}
