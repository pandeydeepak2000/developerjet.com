<?php

namespace App\Services\Provisioning;

use App\Models\Service;
use Illuminate\Support\Facades\Cache;

/**
 * Live usage for the customer panel (CPU, RAM, disk, network, uptime), read over SSH with the reviewed read-only
 * script resources/provisioning/common/stats.sh. One SSH call per server per minute at most (cached), so a panel
 * left open — or many tabs — never hammers the server. Only real readings are returned; nothing is estimated.
 */
class ServerStats
{
    public const CACHE_SECONDS = 55;

    public const FAIL_CACHE_SECONDS = 30;

    /** Integer readings the script prints, and nothing else is accepted from its output. */
    private const INT_KEYS = ['cpu_permille', 'cpu_cores', 'mem_total_kb', 'mem_used_kb', 'swap_total_kb', 'swap_used_kb',
        'disk_total_kb', 'disk_used_kb', 'uptime_s', 'net_rx_bytes', 'net_tx_bytes', 'net_rx_rate', 'net_tx_rate'];

    public function __construct(private RemoteTransport $transport, private ServerActions $actions) {}

    public function available(Service $service): bool
    {
        return $this->actions->liveOn() && $service->server_id && in_array($service->status, ['active', 'suspended'], true);
    }

    /** @return array<string, mixed> {ok: true, at, cpu, mem, …} or {ok: false, message} */
    public function snapshot(Service $service): array
    {
        if (! $this->available($service)) {
            return ['ok' => false, 'message' => 'Live usage is not available for this service yet.'];
        }

        $key = 'server-stats:'.$service->server_id;
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $result = $this->read($service);
        Cache::put($key, $result, $result['ok'] ? self::CACHE_SECONDS : self::FAIL_CACHE_SECONDS);

        return $result;
    }

    public function forget(Service $service): void
    {
        Cache::forget('server-stats:'.$service->server_id);
    }

    /** @return array<string, mixed> */
    private function read(Service $service): array
    {
        $server = $service->server;
        try {
            $fingerprint = $this->transport->connect($server, $server->ssh_host_fingerprint ?: null);
            if (! $server->ssh_host_fingerprint) {
                $server->forceFill(['ssh_host_fingerprint' => $fingerprint])->save();
            }
            $result = $this->transport->exec(RecipeRegistry::script('common/stats'));
        } catch (TransportException) {
            return ['ok' => false, 'message' => 'We could not reach your server just now. It may be rebooting, or its root password was changed outside this panel.'];
        } finally {
            $this->transport->disconnect();
        }

        if ($result['exit'] !== 0) {
            return ['ok' => false, 'message' => 'The server answered but could not report its usage.'];
        }

        return ['ok' => true, 'at' => now()->toIso8601String()] + self::parse((string) $result['stdout']);
    }

    /** @return array<string, mixed> */
    public static function parse(string $output): array
    {
        $raw = [];
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/^([a-z_]+)=(.*)$/', trim($line), $m)) {
                $raw[$m[1]] = trim($m[2]);
            }
        }
        $int = fn (string $k) => in_array($k, self::INT_KEYS, true) && isset($raw[$k]) && ctype_digit($raw[$k]) ? (int) $raw[$k] : null;
        $load = array_map('floatval', array_slice(preg_split('/\s+/', $raw['load'] ?? '') ?: [], 0, 3));

        return [
            'cpu' => ['percent' => $int('cpu_permille') !== null ? round($int('cpu_permille') / 10, 1) : null, 'cores' => $int('cpu_cores'), 'load' => $load],
            'mem' => ['total' => self::kb($int('mem_total_kb')), 'used' => self::kb($int('mem_used_kb'))],
            'swap' => ['total' => self::kb($int('swap_total_kb')), 'used' => self::kb($int('swap_used_kb'))],
            'disk' => ['total' => self::kb($int('disk_total_kb')), 'used' => self::kb($int('disk_used_kb'))],
            'net' => ['rx' => $int('net_rx_bytes'), 'tx' => $int('net_tx_bytes'), 'rx_rate' => $int('net_rx_rate'), 'tx_rate' => $int('net_tx_rate')],
            'uptime' => $int('uptime_s'),
            'os' => isset($raw['os']) ? mb_substr(strip_tags($raw['os']), 0, 80) : null,
            'hostname' => isset($raw['hostname']) && preg_match('/^[A-Za-z0-9.-]{1,190}$/', $raw['hostname']) ? $raw['hostname'] : null,
        ];
    }

    private static function kb(?int $kb): ?int
    {
        return $kb === null ? null : $kb * 1024;
    }
}
