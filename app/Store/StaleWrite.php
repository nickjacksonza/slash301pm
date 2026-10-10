<?php
declare(strict_types=1);

namespace App\Store;

/**
 * Thrown inside a write transaction when a row changed since it was read, so
 * the whole transaction rolls back; the store answers "stale" to its caller.
 * Never escapes a Store. Go: a sentinel error returned from the tx func.
 */
final class StaleWrite extends \RuntimeException {}
