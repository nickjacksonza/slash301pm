<?php
declare(strict_types=1);

namespace App\Logs;

use App\Clock\Clock;
use Throwable;

/**
 * Daily log files in data/logs (app-YYYY-MM-DD.log, SAST dates), denied to the
 * web by data/.htaccess and data/logs/.htaccess. Full error detail goes here and
 * never into a response: users see only the request id. At most once a day
 * (marker file .pruned holding the date) files older than KEEP_DAYS are
 * deleted. Logging never throws: a full disk must not turn an error page into
 * a blank one. Go: package logs, a *log.Logger over a dated file.
 */
final class AppLog
{
    public const KEEP_DAYS = 30;

    public function __construct(private readonly string $dir, private readonly Clock $clock) {}

    /** 16 hex characters, shown to the user and written next to the detail. */
    public static function newRequestId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function path(): string
    {
        return $this->dir . '/app-' . $this->clock->now()->format('Y-m-d') . '.log';
    }

    /** One entry for an uncaught exception: the request line, the user and the full trace. */
    public function exception(string $requestId, Throwable $e, string $method, string $path, ?string $userId): void
    {
        $one = static fn (string $v): string => (string) preg_replace('/[\x00-\x1f\x7f]/', '?', $v);
        $lines = [sprintf('[%s] %s %s %s user=%s', $one($requestId), get_class($e), $one($method), $one($path), $one($userId ?? '-'))];
        $cur = $e;
        while ($cur !== null) {
            $lines[] = '  ' . get_class($cur) . ': ' . str_replace("\n", "\n    ", $cur->getMessage()) . ' at ' . $cur->getFile() . ':' . $cur->getLine();
            $lines[] = '  ' . str_replace("\n", "\n  ", $cur->getTraceAsString());
            $cur = $cur->getPrevious();
        }
        $this->write('error', implode("\n", $lines));
    }

    public function write(string $level, string $message): void
    {
        try {
            if (!is_dir($this->dir) && !@mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
                return;
            }
            self::ensureDeny($this->dir);
            $stamp = $this->clock->now()->format('Y-m-d H:i:s');
            // Control characters become '?' and every continuation line is indented, so only a real
            // entry starts with a timestamp and nothing in a request or message can forge one.
            $clean = str_replace("\n", "\n  ", (string) preg_replace('/[\x00-\x09\x0b-\x1f\x7f]/', '?', $message));
            @file_put_contents($this->path(), "$stamp $level $clean\n", FILE_APPEND | LOCK_EX);
            $this->pruneOncePerDay();
        } catch (Throwable) {
            // nothing sensible left to do
        }
    }

    /** Deletes app-*.log and php-*.log older than KEEP_DAYS; runs at most once per day. */
    public function pruneOncePerDay(): int
    {
        $today = $this->clock->now()->format('Y-m-d');
        $marker = $this->dir . '/.pruned';
        if (is_file($marker) && trim((string) @file_get_contents($marker)) === $today) {
            return 0;
        }
        @file_put_contents($marker, $today . "\n", LOCK_EX);
        $cutoff = $this->clock->now()->modify('-' . self::KEEP_DAYS . ' days')->format('Y-m-d');
        $deleted = 0;
        foreach (glob($this->dir . '/*.log') ?: [] as $file) {
            if (preg_match('/^(app|php)-(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $m) === 1 && $m[2] < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }
        return $deleted;
    }

    /** The folder's own deny file, in case data/.htaccess is ever missing. */
    public static function ensureDeny(string $dir): void
    {
        $f = $dir . '/.htaccess';
        if (!is_file($f)) {
            @file_put_contents($f, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
        }
    }
}
