<?php
declare(strict_types=1);

namespace Edexcel\Http;

/**
 * Measured host exposure for the admin Protection page.
 * A port is never reported as protected unless a readable firewalld policy limits it.
 */
final class HostPortStatus
{
    /** @return list<array{group:string,label:string,state:string,detail:string}> */
    public static function items(): array
    {
        $fw = self::firewalld();
        $policy = self::policyReadable();
        return [
            self::adminSsh(),
            ['group' => 'Host firewall', 'label' => 'Firewall', 'state' => $fw['state'], 'detail' => $fw['detail']],
            self::policyItem($policy),
            self::portItem('8888', [8888], 'tcp'),
            self::portItem('7080', [7080], 'tcp'),
            self::portItem('8090', [8090], 'tcp'),
            self::portItem('53', [53], 'any'),
            self::portItem('SSH', [22], 'tcp'),
            self::familyItem('IPv4', 'ipv4', $policy),
            self::familyItem('IPv6', 'ipv6', $policy),
        ];
    }

    /** @return array{state:string,detail:string} */
    private static function firewalld(): array
    {
        $text = self::command('systemctl is-active firewalld');
        if ($text === 'active') {
            return [
                'state' => 'ACTIVE',
                'detail' => 'firewalld is running. Zone rules are not treated as a port restriction unless the policy file is readable.',
            ];
        }
        if ($text === 'inactive' || $text === 'failed') {
            return [
                'state' => 'UNKNOWN',
                'detail' => 'firewalld was queried and is not active, so it is not marked active.',
            ];
        }
        return [
            'state' => 'UNKNOWN',
            'detail' => 'firewalld could not be queried from this process.',
        ];
    }

    /** @param list<int> $ports */
    private static function portItem(string $label, array $ports, string $proto): array
    {
        $listen = self::listeners();
        if ($listen === null) {
            return [
                'group' => 'Host firewall',
                'label' => $label,
                'state' => 'UNKNOWN',
                'detail' => 'Listening sockets could not be read, so this port is not marked protected.',
            ];
        }
        $public = false;
        $local = false;
        foreach ($listen as $row) {
            if (!in_array($row['port'], $ports, true)) {
                continue;
            }
            if ($proto !== 'any' && $row['proto'] !== $proto) {
                continue;
            }
            if ($row['public']) {
                $public = true;
            } else {
                $local = true;
            }
        }
        if ($public) {
            $limited = self::firewallLimitsPort($ports, $proto);
            if ($limited === true) {
                return [
                    'group' => 'Host firewall',
                    'label' => $label,
                    'state' => 'RESTRICTED',
                    'detail' => 'firewalld has a source-limited accept rule and no world-open port entry for this service.',
                ];
            }
            return [
                'group' => 'Host firewall',
                'label' => $label,
                'state' => 'PUBLIC',
                'detail' => 'A process is listening on a public address. No readable firewalld rule limits this port, so it is not marked restricted.',
            ];
        }
        if ($label === '8888' && self::browserSshUnitStopped()) {
            return [
                'group' => 'Host firewall',
                'label' => $label,
                'state' => 'DISABLED',
                'detail' => 'fastapi_ssh_server is not active and port 8888 is not listening on a public address.',
            ];
        }
        if ($local) {
            return [
                'group' => 'Host firewall',
                'label' => $label,
                'state' => 'LOCAL',
                'detail' => 'Listening on loopback only.',
            ];
        }
        if ($label === '8888') {
            return [
                'group' => 'Host firewall',
                'label' => $label,
                'state' => 'UNKNOWN',
                'detail' => 'Port 8888 was not found on a public address, and the service was not confirmed stopped.',
            ];
        }
        return [
            'group' => 'Host firewall',
            'label' => $label,
            'state' => 'UNKNOWN',
            'detail' => 'No public listener was found, and a firewall restriction was not confirmed.',
        ];
    }

    /** @return array{group:string,label:string,state:string,detail:string} */
    private static function adminSsh(): array
    {
        $file = '/home/edexcel.college/private_backups/firewall/admin-ssh-status.json';
        $missing = [
            'group' => 'Host firewall',
            'label' => 'Admin SSH access',
            'state' => 'NOT VERIFIED',
            'detail' => 'No SSH session check is recorded. A visit to this website is not treated as an administrator SSH session.',
        ];
        if (!is_file($file)) {
            return $missing;
        }
        $json = json_decode((string)file_get_contents($file), true);
        $at = is_array($json) ? strtotime((string)($json['checked_at'] ?? '')) : false;
        $fresh = $at !== false && (time() - $at) <= 1800;
        if (!is_array($json) || empty($json['verified']) || ($json['source'] ?? '') !== 'ssh' || !$fresh) {
            return $missing;
        }
        return [
            'group' => 'Host firewall',
            'label' => 'Admin SSH access',
            'state' => 'VERIFIED',
            'detail' => 'A shell with an SSH connection recorded a passing check in the last 30 minutes. The client address is not shown.',
        ];
    }

    /** @return array{group:string,label:string,state:string,detail:string} */
    private static function policyItem(bool $readable): array
    {
        if ($readable) {
            return [
                'group' => 'Host firewall',
                'label' => 'Firewall policy',
                'state' => 'CONFIRMED',
                'detail' => 'The firewalld zone files were readable. A readable policy is not itself a restriction.',
            ];
        }
        return [
            'group' => 'Host firewall',
            'label' => 'Firewall policy',
            'state' => 'NOT CONFIRMED',
            'detail' => 'The firewalld zone in /etc/firewalld was not readable, so no restriction is confirmed.',
        ];
    }

    private static function browserSshUnitStopped(): bool
    {
        $text = self::command('systemctl is-active fastapi_ssh_server.service');
        return $text === 'inactive' || $text === 'failed' || $text === 'unknown';
    }

    /** @return array{group:string,label:string,state:string,detail:string} */
    private static function familyItem(string $label, string $family, bool $policyReadable): array
    {
        $route = '';
        if ($family === 'ipv6' && self::ipv6DefaultRoute()) {
            $route = ' A global IPv6 address and default route are visible. Missing AAAA records are not protection.';
        }
        $limited = $policyReadable
            && self::firewallLimitsPort([7080], 'tcp')
            && self::firewallLimitsPort([8090], 'tcp');
        if ($limited && $family === 'ipv4') {
            return [
                'group' => 'Host firewall',
                'label' => $label,
                'state' => 'PROTECTED',
                'detail' => 'firewalld source limits for 7080 and 8090 were readable.',
            ];
        }
        return [
            'group' => 'Host firewall',
            'label' => $label,
            'state' => 'UNKNOWN',
            'detail' => 'Not marked protected. firewalld has not confirmed the same limits for ' . strtoupper($family) . '.' . $route,
        ];
    }

    private static function policyReadable(): bool
    {
        return is_readable('/etc/firewalld/firewalld.conf') && is_dir('/etc/firewalld/zones');
    }

    /**
     * True only when /etc/firewalld zone XML shows a source limit and no open port.
     * Unreadable policy returns false so the port stays unconfirmed rather than protected.
     *
     * @param list<int> $ports
     */
    private static function firewallLimitsPort(array $ports, string $proto): bool
    {
        if (!self::policyReadable()) {
            return false;
        }
        $conf = (string)file_get_contents('/etc/firewalld/firewalld.conf');
        $zone = 'public';
        if (preg_match('/^DefaultZone=(.+)$/m', $conf, $m)) {
            $zone = trim($m[1]);
        }
        $xmlPath = '/etc/firewalld/zones/' . $zone . '.xml';
        if (!is_readable($xmlPath)) {
            return false;
        }
        $xml = @simplexml_load_file($xmlPath);
        if ($xml === false) {
            return false;
        }
        $open = false;
        $limited = false;
        foreach ($ports as $port) {
            foreach ($xml->port as $node) {
                $nodePort = (string)$node['port'];
                $nodeProto = strtolower((string)$node['protocol']);
                if (self::portMatches($nodePort, $port) && self::protoMatches($nodeProto, $proto)) {
                    $open = true;
                }
            }
            foreach ($xml->rule as $rule) {
                $source = isset($rule->source['address']) ? trim((string)$rule->source['address']) : '';
                $rulePort = isset($rule->port['port']) ? (string)$rule->port['port'] : '';
                $ruleProto = strtolower((string)($rule->port['protocol'] ?? ''));
                $accepts = isset($rule->accept);
                if (!$accepts || $source === '' || !self::portMatches($rulePort, $port) || !self::protoMatches($ruleProto, $proto)) {
                    continue;
                }
                $limited = true;
            }
        }
        return $limited && !$open;
    }

    private static function portMatches(string $spec, int $port): bool
    {
        if ($spec === (string)$port) {
            return true;
        }
        if (preg_match('/^(\d+)-(\d+)$/', $spec, $m)) {
            return $port >= (int)$m[1] && $port <= (int)$m[2];
        }
        return false;
    }

    private static function protoMatches(string $nodeProto, string $want): bool
    {
        if ($want === 'any') {
            return $nodeProto === '' || $nodeProto === 'tcp' || $nodeProto === 'udp';
        }
        return $nodeProto === '' || $nodeProto === $want;
    }

    /** @return list<array{port:int,proto:string,public:bool}>|null */
    private static function listeners(): ?array
    {
        $files = [
            '/proc/net/tcp' => 'tcp',
            '/proc/net/tcp6' => 'tcp',
            '/proc/net/udp' => 'udp',
            '/proc/net/udp6' => 'udp',
        ];
        $any = false;
        $rows = [];
        foreach ($files as $path => $proto) {
            if (!is_readable($path)) {
                continue;
            }
            $any = true;
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }
            foreach (array_slice($lines, 1) as $line) {
                $cols = preg_split('/\s+/', trim($line));
                if (!$cols || count($cols) < 4 || !str_contains($cols[1], ':')) {
                    continue;
                }
                [$addr, $portHex] = explode(':', $cols[1], 2);
                $state = $cols[3];
                if ($proto === 'tcp' && $state !== '0A') {
                    continue;
                }
                $port = hexdec($portHex);
                if (!in_array($port, [21, 22, 25, 53, 80, 443, 465, 587, 7080, 8090, 8888, 7880, 7881, 8443], true)) {
                    continue;
                }
                $rows[] = [
                    'port' => $port,
                    'proto' => $proto,
                    'public' => !self::isLoopback($addr, str_contains($path, '6')),
                ];
            }
        }
        return $any ? $rows : null;
    }

    private static function isLoopback(string $hex, bool $v6): bool
    {
        if ($v6) {
            $bin = strlen($hex) === 32 ? hex2bin($hex) : false;
            if ($bin === false) {
                return false;
            }
            $ip = inet_ntop($bin);
            return $ip === '::1' || $ip === '::ffff:127.0.0.1';
        }
        if (strlen($hex) !== 8) {
            return false;
        }
        $n = hexdec($hex);
        $ip = long2ip((($n & 0xff) << 24) | (($n & 0xff00) << 8) | (($n >> 8) & 0xff00) | (($n >> 24) & 0xff));
        return $ip === '127.0.0.1';
    }

    private static function ipv6DefaultRoute(): bool
    {
        $text = self::command('ip -6 route show default');
        return is_string($text) && str_contains($text, 'default');
    }

    private static function command(string $cmd): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }
        $out = [];
        $code = 1;
        @exec($cmd . ' 2>&1', $out, $code);
        $text = trim(implode("\n", $out));
        if ($text === '') {
            return null;
        }
        return $text;
    }
}
