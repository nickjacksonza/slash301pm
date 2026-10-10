<?php
declare(strict_types=1);

namespace App\Config;

enum Env: string
{
    case Local = 'local';
    case Live = 'live';
}
