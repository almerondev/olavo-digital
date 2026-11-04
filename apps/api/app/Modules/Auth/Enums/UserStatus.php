<?php

namespace App\Modules\Auth\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Blocked = 'blocked';
}
