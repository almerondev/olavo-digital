<?php

namespace App\Modules\Materials\Enums;

enum MaterialVisibility: string
{
    case All = 'all';
    case Restricted = 'restricted';
    case Staff = 'staff';
}
