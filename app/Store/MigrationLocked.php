<?php
declare(strict_types=1);

namespace App\Store;

use RuntimeException;

/** Another request holds data/migrate.lock; answer 503 with Retry-After. */
final class MigrationLocked extends RuntimeException {}
