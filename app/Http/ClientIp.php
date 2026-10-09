<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Port of legacy getClientIp(): trust CF-Connecting-IP only when the peer is a
 * Cloudflare address, so a spoofed header cannot dodge the login rate limit.
 * Ranges from https://www.cloudflare.com/ips/ (re-check occasionally).
 */
final class ClientIp
{
    private const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public static function resolve(string $remoteAddr, string $cfConnectingIp): string
    {
        $remote = $remoteAddr !== '' ? $remoteAddr : '0.0.0.0';
        if ($cfConnectingIp !== '' && filter_var($cfConnectingIp, FILTER_VALIDATE_IP) !== false) {
            foreach (self::CLOUDFLARE as $range) {
                if (self::inCidr($remote, $range)) {
                    return $cfConnectingIp;
                }
            }
        }
        return $remote;
    }

    public static function inCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) {
            return false;
        }
        $ipBin = filter_var($ip, FILTER_VALIDATE_IP) !== false ? inet_pton($ip) : false;
        $subBin = filter_var($parts[0], FILTER_VALIDATE_IP) !== false ? inet_pton($parts[0]) : false;
        if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
            return false;
        }
        $bits = (int) $parts[1];
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) {
            return false;
        }
        $rem = $bits % 8;
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($subBin[$bytes]) & $mask);
    }
}
