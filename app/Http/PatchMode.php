<?php
declare(strict_types=1);

namespace App\Http;

/** Datastar patch-elements modes (1.0.4). Go: datastar.ElementPatchMode. */
enum PatchMode: string
{
    case Outer = 'outer';
    case Inner = 'inner';
    case Replace = 'replace';
    case Prepend = 'prepend';
    case Append = 'append';
    case Before = 'before';
    case After = 'after';
    case Remove = 'remove';
}
