<?php
declare(strict_types=1);

namespace App\Http;

use App\Config\Transport;
use LogicException;
use starfederation\datastar\events\EventInterface;
use starfederation\datastar\events\PatchElements as SdkPatchElements;
use starfederation\datastar\events\PatchSignals as SdkPatchSignals;
use starfederation\datastar\events\RemoveElements as SdkRemoveElements;
use starfederation\datastar\ServerSentEventGenerator;

/**
 * A full page, a redirect, JSON, or a list of Datastar events. This is the only
 * file that touches headers or the Datastar SDK. Go: a func writing to
 * http.ResponseWriter via datastar-go.
 *
 * Datastar ignores the body of any non-200 response, so events() is always 200:
 * validation and CSRF failures are an error Toast with status 200.
 */
final class Response
{
    /**
     * @param list<PatchElements|PatchSignals|Toast|Redirect> $events
     * @param array<string,string> $headers
     */
    private function __construct(
        private readonly string $kind,
        private readonly int $status,
        private readonly string $body,
        private readonly array $events,
        private readonly array $headers,
        private readonly ?Transport $transport,
        private readonly int $pauseMs,
    ) {}

    public static function page(string $html, int $status = 200): self
    {
        return new self('page', $status, $html, [], [], null, 0);
    }

    public static function events(PatchElements|PatchSignals|Toast|Redirect ...$events): self
    {
        return new self('events', 200, '', array_values($events), [], null, 0);
    }

    /** Full-page navigation (303 after a form POST, so the browser follows with GET). */
    public static function redirect(string $url, int $status = 303): self
    {
        return new self('redirect', $status, '', [], ['Location' => $url], null, 0);
    }

    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) . "\n";
        return new self('json', $status, $body, [], [], null, 0);
    }

    public static function notFound(): self
    {
        return self::page(page_error(404, 'Not found', 'There is nothing at this address.'), 404);
    }

    public static function forbidden(string $reason = 'You do not have access to this page.'): self
    {
        return self::page(page_error(403, 'Not allowed', $reason), 403);
    }

    /** A redirect that works for both plain requests and Datastar actions. */
    public static function navigate(bool $datastar, string $url): self
    {
        return $datastar ? self::events(new Redirect($url)) : self::redirect($url);
    }

    public function withHeader(string $name, string $value): self
    {
        return $this->withHeaders([$name => $value]);
    }

    /** Add or replace headers (the security headers come from Middleware\SecurityHeaders). @param array<string,string> $set */
    public function withHeaders(array $set): self
    {
        $headers = $this->headers;
        foreach ($set as $name => $value) {
            $headers[$name] = $value;
        }
        return new self($this->kind, $this->status, $this->body, $this->events, $headers, $this->transport, $this->pauseMs);
    }

    /** Force a transport for this response (the spike page tests both). */
    public function withTransport(Transport $t): self
    {
        return new self($this->kind, $this->status, $this->body, $this->events, $this->headers, $t, $this->pauseMs);
    }

    /** SSE only: sleep between events, each flushed, to prove nothing buffers. */
    public function withPause(int $ms): self
    {
        return new self($this->kind, $this->status, $this->body, $this->events, $this->headers, $this->transport, $ms);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return list<PatchElements|PatchSignals|Toast|Redirect> */
    public function eventList(): array
    {
        return $this->events;
    }

    public function render(Transport $default): Rendered
    {
        // Security headers are added by Middleware\SecurityHeaders (withHeaders), not here.
        $base = [];
        if ($this->kind === 'page') {
            return new Rendered($this->status, $base + ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store'] + $this->headers, [$this->body], 0);
        }
        if ($this->kind === 'json') {
            return new Rendered($this->status, $base + ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'] + $this->headers, [$this->body], 0);
        }
        if ($this->kind === 'redirect') {
            return new Rendered($this->status, $base + ['Cache-Control' => 'no-store'] + $this->headers, [''], 0);
        }
        $transport = $this->transport ?? $default;
        return $transport === Transport::Html ? $this->renderHtml($base) : $this->renderSse($base);
    }

    /** @param array<string,string> $base */
    private function renderSse(array $base): Rendered
    {
        $headers = $base + ServerSentEventGenerator::headers() + $this->headers;
        $chunks = [];
        foreach ($this->events as $ev) {
            $chunks[] = self::sdkEvent($ev)->getOutput();
        }
        return new Rendered(200, $headers, $chunks, $this->pauseMs);
    }

    private static function sdkEvent(PatchElements|PatchSignals|Toast|Redirect $ev): EventInterface
    {
        if ($ev instanceof PatchElements) {
            if ($ev->mode === PatchMode::Remove) {
                return new SdkRemoveElements((string) $ev->selector);
            }
            $opts = ['mode' => $ev->mode->value];
            if ($ev->selector !== null) {
                $opts['selector'] = $ev->selector;
            }
            return new SdkPatchElements($ev->normalizedHtml(), $opts);
        }
        if ($ev instanceof PatchSignals) {
            return new SdkPatchSignals($ev->json(), ['onlyIfMissing' => $ev->onlyIfMissing]);
        }
        if ($ev instanceof Toast) {
            return new SdkPatchElements(str_replace(["\r\n", "\r"], "\n", $ev->html()), ['selector' => '#toasts', 'mode' => 'append']);
        }
        // Never a script: the CSP blocks inline scripts. The layout's body watches _redirect.
        return new SdkPatchSignals($ev->signalsJson(), []);
    }

    /**
     * Datastar's text/html transport carries ONE patch per response:
     * - elements sharing one selector and mode (a Toast counts as an outer
     *   patch of the whole #toasts region, matched by id), or
     * - a signals-only JSON body, or
     * - a Redirect, which is a signals-only JSON body ({"_redirect": url}).
     * Anything else cannot be represented and is a programmer error.
     * @param array<string,string> $base
     */
    private function renderHtml(array $base): Rendered
    {
        if ($this->events === []) {
            return new Rendered(204, $base, [''], 0);
        }
        $signals = [];
        $redirects = [];
        $elements = [];
        foreach ($this->events as $ev) {
            if ($ev instanceof PatchSignals) {
                $signals[] = $ev;
            } elseif ($ev instanceof Redirect) {
                $redirects[] = $ev;
            } elseif ($ev instanceof Toast) {
                $elements[] = new PatchElements($ev->regionHtml());
            } else {
                $elements[] = $ev;
            }
        }
        $kinds = ($signals !== [] ? 1 : 0) + ($redirects !== [] ? 1 : 0) + ($elements !== [] ? 1 : 0);
        if ($kinds > 1 || count($signals) > 1 || count($redirects) > 1) {
            throw new LogicException('transport=html carries one patch per response; this one mixes event kinds');
        }
        if ($signals !== []) {
            $headers = $base + ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-cache'];
            if ($signals[0]->onlyIfMissing) {
                $headers['Datastar-Only-If-Missing'] = 'true';
            }
            return new Rendered(200, $headers + $this->headers, [$signals[0]->json()], 0);
        }
        if ($redirects !== []) {
            return new Rendered(200, $base + ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-cache'] + $this->headers, [$redirects[0]->signalsJson()], 0);
        }
        $first = $elements[0];
        $html = '';
        foreach ($elements as $el) {
            if ($el->selector !== $first->selector || $el->mode !== $first->mode) {
                throw new LogicException('transport=html carries one patch per response; these elements use different selectors or modes');
            }
            $html .= $el->normalizedHtml();
        }
        $headers = $base + ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache'];
        if ($first->selector !== null) {
            $headers['Datastar-Selector'] = $first->selector;
        }
        if ($first->mode !== PatchMode::Outer) {
            $headers['Datastar-Mode'] = $first->mode->value;
        }
        return new Rendered(200, $headers + $this->headers, [$html], 0);
    }

    /** Write the response. Nothing may have been echoed before this. */
    public function send(Transport $default): void
    {
        $r = $this->render($default);
        $streaming = str_starts_with($r->header('Content-Type'), 'text/event-stream');
        if ($streaming) {
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
            ini_set('zlib.output_compression', '0');
            // What ServerSentEventGenerator's constructor does: stop when the client goes away.
            ignore_user_abort(false);
        }
        http_response_code($r->status);
        header_remove('X-Powered-By');
        foreach ($r->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        $last = count($r->chunks) - 1;
        foreach ($r->chunks as $i => $chunk) {
            echo $chunk;
            if ($streaming) {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
                flush();
                if ($r->pauseMs > 0 && $i < $last) {
                    usleep($r->pauseMs * 1000);
                }
            }
        }
    }
}
