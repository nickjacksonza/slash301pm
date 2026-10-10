<?php
declare(strict_types=1);

namespace App\Http;

enum ToastKind: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Error = 'error';
    case Info = 'info';
}
