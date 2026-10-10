<?php
declare(strict_types=1);

// Returns the Config for this request. The owner can change the default
// transport here by SFTP if SSE misbehaves behind Cloudflare ('html'), and the
// review round limit: the AM is warned when a post reaches the round after it
// (default 3, so the warning comes at round 4; 0 turns the warning off).
// Env overrides (local and tests only): S301_DB, S301_DATA_DIR, S301_TRANSPORT.

use App\Config\Config;
use App\Config\Transport;

$s301DefaultTransport = Transport::Sse;
$s301ReviewRoundLimit = 3;

$s301Env = getenv();
return Config::fromEnvironment($_SERVER, is_array($s301Env) ? $s301Env : [], PHP_SAPI, dirname(__DIR__), $s301DefaultTransport, $s301ReviewRoundLimit);
