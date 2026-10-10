<?php
declare(strict_types=1);

namespace App\Config;

/** How Datastar responses travel: SSE events, or Datastar's plain text/html (one patch per response). */
enum Transport: string
{
    case Sse = 'sse';
    case Html = 'html';
}
