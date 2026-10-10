<?php
declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

/** One parsed ServeMux pattern. Segment kinds: lit, wild ({x}), rest ({x...} or trailing /), end ({$}). */
final class Route
{
    private const RANK = ['lit' => 4, 'end' => 3, 'wild' => 2, 'rest' => 1];

    /** @param list<array{0:string,1:string}> $parts [kind, literal or name] */
    private function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly array $parts,
        public readonly mixed $handler,
    ) {}

    public static function parse(string $pattern, callable $handler): self
    {
        $method = '';
        $path = $pattern;
        $space = strpos($pattern, ' ');
        if ($space !== false) {
            $method = strtoupper(substr($pattern, 0, $space));
            $path = ltrim(substr($pattern, $space + 1));
            if (preg_match('/^[A-Z]+$/', $method) !== 1) {
                throw new InvalidArgumentException("Bad method in pattern: $pattern");
            }
        }
        if (!str_starts_with($path, '/')) {
            throw new InvalidArgumentException("Pattern path must start with /: $pattern");
        }
        $segments = Router::split($path);
        $parts = [];
        $last = count($segments) - 1;
        foreach ($segments as $i => $seg) {
            if ($seg === '{$}') {
                if ($i !== $last) {
                    throw new InvalidArgumentException("{\$} must be last: $pattern");
                }
                $parts[] = ['end', ''];
            } elseif (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\.\.\.\}$/', $seg, $m) === 1) {
                if ($i !== $last) {
                    throw new InvalidArgumentException("{name...} must be last: $pattern");
                }
                $parts[] = ['rest', $m[1]];
            } elseif (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $seg, $m) === 1) {
                $parts[] = ['wild', $m[1]];
            } elseif (str_contains($seg, '{') || str_contains($seg, '}')) {
                throw new InvalidArgumentException("Bad wildcard segment '$seg' in $pattern");
            } else {
                $parts[] = ['lit', rawurldecode($seg)];
            }
        }
        $lastKind = $parts === [] ? '' : $parts[count($parts) - 1][0];
        if (str_ends_with($path, '/') && $lastKind !== 'end' && $lastKind !== 'rest') {
            $parts[] = ['rest', ''];   // trailing slash: prefix match, like Go
        }
        return new self($method, $pattern, $parts, $handler);
    }

    /**
     * @param list<string> $segments raw request segments
     * @return array<string,string>|null
     */
    public function matchPath(array $segments, bool $trailingSlash): ?array
    {
        $values = [];
        $i = 0;
        $n = count($segments);
        foreach ($this->parts as $part) {
            $kind = $part[0];
            $name = $part[1];
            if ($kind === 'lit') {
                if ($i >= $n || rawurldecode($segments[$i]) !== $name) {
                    return null;
                }
                $i++;
            } elseif ($kind === 'wild') {
                if ($i >= $n || $segments[$i] === '') {
                    return null;
                }
                $values[$name] = rawurldecode($segments[$i]);
                $i++;
            } elseif ($kind === 'rest') {
                $rest = array_slice($segments, $i);
                if ($rest === [] && !$trailingSlash) {
                    return null;
                }
                if ($name !== '') {
                    $joined = implode('/', array_map('rawurldecode', $rest));
                    $values[$name] = $rest !== [] && $trailingSlash ? $joined . '/' : $joined;
                }
                return $values;
            } else { // end
                return ($i === $n && $trailingSlash) ? $values : null;
            }
        }
        if ($i !== $n || ($trailingSlash && $n > 0)) {
            return null;
        }
        return $values;
    }

    /** Most specific wins: compare kinds left to right, then length, then method. */
    public function moreSpecificThan(self $other): bool
    {
        $len = max(count($this->parts), count($other->parts));
        for ($k = 0; $k < $len; $k++) {
            $a = isset($this->parts[$k]) ? self::RANK[$this->parts[$k][0]] : 0;
            $b = isset($other->parts[$k]) ? self::RANK[$other->parts[$k][0]] : 0;
            if ($a !== $b) {
                return $a > $b;
            }
        }
        return $this->method !== '' && $other->method === '';
    }

    /** Pattern shape without wildcard names, for duplicate detection. */
    public function key(): string
    {
        $out = [];
        foreach ($this->parts as $p) {
            $out[] = $p[0] === 'lit' ? 'L:' . $p[1] : $p[0];
        }
        return implode('/', $out);
    }
}
