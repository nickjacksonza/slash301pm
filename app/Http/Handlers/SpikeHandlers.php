<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Config\Transport;
use App\Domain\Policy;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\PatchMode;
use App\Http\PatchSignals;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;

/**
 * /system/spike (COO and ECD): proves on live, through Cloudflare, that every
 * Datastar mechanism we rely on works. Each patched fragment checks its own
 * placement in data-init and writes pass or fail into $spike.<test>.
 */
final class SpikeHandlers
{
    /** Signal sent with the method tests; the server checks it arrived intact. */
    public const ECHO_S = 'a&b <c> "q" \'s\' é';

    public static function page(Request $r, Deps $d): Response
    {
        $deny = self::guard($r);
        if ($deny !== null) {
            return $deny;
        }
        return Shell::page($r, $d, 'Datastar spike', 'spike', page_spike(self::ECHO_S));
    }

    public static function patch(Request $r, Deps $d): Response
    {
        $deny = self::guard($r);
        if ($deny !== null) {
            return $deny;
        }
        $mode = $r->pathValue('mode');
        $html = spike_fragment($mode);
        return match ($mode) {
            'outer' => Response::events(new PatchElements($html)),
            'replace' => Response::events(new PatchElements($html, null, PatchMode::Replace)),
            'inner' => Response::events(PatchElements::into('#t-inner', $html, PatchMode::Inner)),
            'prepend' => Response::events(PatchElements::into('#t-prepend', $html, PatchMode::Prepend)),
            'append' => Response::events(PatchElements::into('#t-append', $html, PatchMode::Append)),
            'before' => Response::events(PatchElements::into('#t-before', $html, PatchMode::Before)),
            'after' => Response::events(PatchElements::into('#t-after', $html, PatchMode::After)),
            // remove, then a checker element that reports whether #t-remove is gone
            'remove' => Response::events(PatchElements::remove('#t-remove'), PatchElements::into('#t-remove-box', $html, PatchMode::Append)),
            default => Response::events(Toast::error('Unknown patch mode.')),
        };
    }

    public static function signals(Request $r, Deps $d): Response
    {
        $deny = self::guard($r);
        if ($deny !== null) {
            return $deny;
        }
        return Response::events(new PatchSignals(['spike' => ['signals' => 'pass']]));
    }

    public static function toast(Request $r, Deps $d): Response
    {
        $deny = self::guard($r);
        if ($deny !== null) {
            return $deny;
        }
        return Response::events(
            Toast::ok('Spike toast: if you can read this, toasts work. It disappears after 4 seconds.'),
            new PatchSignals(['spike' => ['toast' => 'pass if a toast appeared']]),
        );
    }

    /** GET POST PUT PATCH DELETE all land here; checks the method and the echoed signals. */
    public static function method(Request $r, Deps $d): Response
    {
        $deny = self::guard($r);
        if ($deny !== null) {
            return $deny;
        }
        $s = $r->signals();
        $echo = isset($s['spike_echo']) && is_array($s['spike_echo']) ? $s['spike_echo'] : [];
        $n = $echo['n'] ?? null;
        $str = $echo['s'] ?? null;
        $ok = ($n === 41 || $n === '41') && $str === self::ECHO_S;
        $key = 'm_' . strtolower($r->method());
        $result = $ok ? 'pass' : 'fail: signals did not arrive intact';
        return Response::events(new PatchSignals(['spike' => [$key => $result]]));
    }

    /** About 3 seconds: four events one second apart, each flushed. */
    public static function slow(Request $r, Deps $d): Response
    {
        $deny = self::guard($r);
        if ($deny !== null) {
            return $deny;
        }
        return Response::events(
            PatchElements::html(spike_slow_step(1)),
            PatchElements::html(spike_slow_step(2)),
            PatchElements::html(spike_slow_step(3)),
            PatchElements::html(spike_slow_step(4)),
        )->withPause(1000)->withTransport(Transport::Sse);
    }

    /** transport=html, whatever the config says. {kind}: elements | inner | signals | toast */
    public static function html(Request $r, Deps $d): Response
    {
        $deny = self::guard($r);
        if ($deny !== null) {
            return $deny;
        }
        $kind = $r->pathValue('kind');
        $resp = match ($kind) {
            'elements' => Response::events(PatchElements::html(spike_html_fragment('elements'))),
            'inner' => Response::events(PatchElements::into('#t-html-inner', spike_html_fragment('inner'), PatchMode::Inner)),
            'signals' => Response::events(new PatchSignals(['spike' => ['html_signals' => 'pass']])),
            'toast' => Response::events(Toast::info('Toast via transport=html (replaces the toast region).')),
            default => Response::events(Toast::error('Unknown html test')),
        };
        return $resp->withTransport(Transport::Html);
    }

    private static function guard(Request $r): ?Response
    {
        $user = $r->user();
        if ($user === null) {
            return Shell::deny($r, 'Sign in first.');
        }
        $decision = Policy::canViewSystem($user);
        return $decision->allowed ? null : Shell::deny($r, $decision->reason);
    }
}
