<?php

/*
 | Generates docs/diagrams/*.svg (flowcharts / state diagrams / architecture) with a simple grid layout so nothing
 | is clipped. Usage: php scripts/build_diagrams.php
 */

const STYLES = [
    'start' => ['#e8f0ff', '#2f5bd3'], 'step' => ['#ffffff', '#6649d1'], 'manual' => ['#fff3d9', '#b9770e'],
    'auto' => ['#e3f4ea', '#1c7a43'], 'bad' => ['#fde8e8', '#a12a2a'], 'end' => ['#ece9f8', '#4b32ad'], 'gate' => ['#f3f3f3', '#666666'],
];

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_XML1);
}

/** @param array<string, array{0:int,1:int,2:string,3?:string,4?:int}> $nodes col,row,label,style,widthCols */
function diagram(string $file, string $title, array $nodes, array $edges, int $cols, int $rows, array $legend = []): void
{
    $cw = 250;
    $ch = 110;
    $bw = 210;
    $bh = 64;
    $pad = 30;
    $top = 60;
    $W = $cols * $cw + $pad * 2;
    $H = $rows * $ch + $top + $pad + (count($legend) ? 36 : 0);
    $pos = [];
    foreach ($nodes as $id => $n) {
        $w = ($n[4] ?? 1) === 2 ? $bw + $cw : $bw;
        $cx = $pad + $n[0] * $cw + $cw / 2 + (($n[4] ?? 1) === 2 ? $cw / 2 : 0);
        $cy = $top + $n[1] * $ch + $ch / 2;
        $pos[$id] = [$cx, $cy, $w, $bh];
    }
    $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 $W $H\" width=\"$W\" height=\"$H\" font-family=\"Segoe UI, Arial, sans-serif\">\n";
    $svg .= "<defs><marker id=\"a\" markerWidth=\"10\" markerHeight=\"8\" refX=\"9\" refY=\"4\" orient=\"auto\"><path d=\"M0,0 L10,4 L0,8 z\" fill=\"#555\"/></marker></defs>\n";
    $svg .= "<rect width=\"$W\" height=\"$H\" fill=\"#ffffff\"/>\n<text x=\"$pad\" y=\"34\" font-size=\"20\" font-weight=\"700\" fill=\"#1d2032\">".esc($title)."</text>\n";

    $edgeSvg = '';
    foreach ($edges as $e) {
        [$from, $to] = $e;
        $label = $e[2] ?? '';
        $via = $e[3] ?? [];
        [$fx, $fy, $fw, $fh] = $pos[$from];
        [$tx, $ty, $tw, $th] = $pos[$to];
        $pts = [];
        $anchor = function ($x, $y, $w, $h, $ox, $oy) use ($via) {
            $dx = $ox - $x;
            $dy = $oy - $y;
            if (abs($dx) * $h > abs($dy) * $w) {
                return [$x + ($dx > 0 ? $w / 2 : -$w / 2), $y];
            }
            $ax = $via ? max($x - $w / 2 + 25, min($x + $w / 2 - 25, $ox)) : $x;

            return [$ax, $y + ($dy > 0 ? $h / 2 : -$h / 2)];
        };
        if ($via) {
            $vp = array_map(fn ($v) => [$pad + $v[0] * $cw + $cw / 2, $top + $v[1] * $ch + $ch / 2], $via);
            $pts[] = $anchor($fx, $fy, $fw, $fh, $vp[0][0], $vp[0][1]);
            foreach ($vp as $p) {
                $pts[] = $p;
            }
            $pts[] = $anchor($tx, $ty, $tw, $th, end($vp)[0], end($vp)[1]);
        } else {
            $pts[] = $anchor($fx, $fy, $fw, $fh, $tx, $ty);
            $pts[] = $anchor($tx, $ty, $tw, $th, $fx, $fy);
        }
        $d = 'M'.implode(' L', array_map(fn ($p) => round($p[0]).','.round($p[1]), $pts));
        $edgeSvg .= "<path d=\"$d\" fill=\"none\" stroke=\"#555\" stroke-width=\"1.8\" marker-end=\"url(#a)\"/>\n";
        if ($label !== '') {
            $m = $pts[intdiv(count($pts), 2)];
            $m2 = $pts[intdiv(count($pts), 2) - 1] ?? $m;
            $lx = count($pts) === 2 ? ($pts[0][0] + $pts[1][0]) / 2 : $m[0];
            $ly = count($pts) === 2 ? ($pts[0][1] + $pts[1][1]) / 2 : $m[1];
            $tw2 = strlen($label) * 6.6 + 10;
            $edgeSvg .= '<rect x="'.round($lx - $tw2 / 2).'" y="'.round($ly - 18).'" width="'.round($tw2).'" height="17" rx="4" fill="#fff"/><text x="'.round($lx).'" y="'.round($ly - 5).'" font-size="12" text-anchor="middle" fill="#333">'.esc($label)."</text>\n";
        }
    }
    $svg .= $edgeSvg;

    foreach ($nodes as $id => $n) {
        [$cx, $cy, $w, $h] = $pos[$id];
        [$fill, $stroke] = STYLES[$n[3] ?? 'step'];
        $lines = explode("\n", wordwrap($n[2], intdiv((int) $w, 8), "\n", true));
        $svg .= '<rect x="'.round($cx - $w / 2).'" y="'.round($cy - $h / 2).'" width="'.round($w)."\" height=\"$h\" rx=\"12\" fill=\"$fill\" stroke=\"$stroke\" stroke-width=\"2\"/>\n";
        $y0 = $cy - (count($lines) - 1) * 8 + 4;
        foreach ($lines as $i => $line) {
            $svg .= '<text x="'.round($cx).'" y="'.round($y0 + $i * 16).'" font-size="13.5" text-anchor="middle" fill="#1d2032">'.esc($line)."</text>\n";
        }
    }
    if ($legend) {
        $x = $pad;
        $y = $H - 18;
        foreach ($legend as $style => $text) {
            [$fill, $stroke] = STYLES[$style];
            $svg .= "<rect x=\"$x\" y=\"".($y - 12)."\" width=\"16\" height=\"16\" rx=\"4\" fill=\"$fill\" stroke=\"$stroke\" stroke-width=\"2\"/><text x=\"".($x + 22).'" y="'.($y + 1).'" font-size="12.5" fill="#333">'.esc($text)."</text>\n";
            $x += 40 + strlen($text) * 7;
        }
    }
    file_put_contents(__DIR__.'/../docs/diagrams/'.$file, $svg."</svg>\n");
    echo "wrote $file\n";
}

$legend = ['auto' => 'Automatic (system)', 'manual' => 'Manual (admin / human)', 'step' => 'Customer action', 'gate' => 'Gated / not live'];

diagram('customer-journey.svg', 'Customer journey (order to renewal)', [
    'a' => [0, 0, '1. Choose product', 'step'], 'b' => [1, 0, '2-3. Select plan, configuration, add-ons', 'step'], 'c' => [2, 0, '4. Add to cart', 'step'], 'd' => [3, 0, '5. Checkout', 'step'],
    'e' => [3, 1, '6. Log in or register', 'step'], 'f' => [2, 1, '7. Email OTP verification (new accounts)', 'auto'], 'g' => [1, 1, '8. Review order, resources, tax, limits', 'step'], 'h' => [0, 1, '9. Choose payment: Razorpay / wallet (PTSure gated)', 'step'],
    'i' => [0, 2, '10. Server-side payment verification', 'auto'], 'j' => [1, 2, '11. Order paid + notifications', 'auto'], 'k' => [2, 2, '12. Awaiting VPS assignment', 'manual'], 'l' => [3, 2, '13-14. Admin buys / assigns VPS', 'manual'],
    'm' => [3, 3, '15-17. Auto-install + health checks', 'auto'], 'n' => [2, 3, '18. Access delivered in dashboard', 'auto'], 'o' => [1, 3, '19. Customer manages service', 'step'], 'p' => [0, 3, '20. Renewal + support', 'step'],
], [['a', 'b'], ['b', 'c'], ['c', 'd'], ['d', 'e'], ['e', 'f'], ['f', 'g'], ['g', 'h'], ['h', 'i'], ['i', 'j'], ['j', 'k'], ['k', 'l'], ['l', 'm'], ['m', 'n'], ['n', 'o'], ['o', 'p']], 4, 4, $legend);

diagram('admin-provisioning.svg', 'Admin provisioning flow (new paid hosting order)', [
    'a' => [0, 0, 'Alert: VPS assignment required', 'auto'], 'b' => [1, 0, 'Open order: confirm payment verified', 'manual'], 'c' => [2, 0, 'Check plan: resources, managed / unmanaged', 'manual'],
    'd' => [2, 1, 'Existing unallocated server fits?', 'gate'], 'e' => [1, 1, 'Yes: assign server', 'manual'], 'f' => [3, 1, 'No: buy VPS from approved supplier', 'manual'],
    'g' => [3, 2, 'Record server in inventory (cost, renewal, host key)', 'manual'], 'h' => [1, 2, 'System validates fit, access model, cost limits', 'auto'], 'i' => [0, 2, 'Rejected: fix and retry', 'bad'],
    'j' => [1, 3, 'Dry run (simulation)', 'manual'], 'k' => [2, 3, 'Start deployment (live: gated)', 'manual'], 'l' => [3, 3, 'Watch progress and logs', 'manual'],
    'm' => [3, 4, 'Success: service active, customer notified', 'auto'], 'n' => [2, 4, 'Failure: service stays inactive', 'bad'], 'o' => [1, 4, 'Fix cause, retry (resumes at failed step)', 'manual'], 'p' => [0, 4, 'Escalate per runbook', 'manual'],
], [['a', 'b'], ['b', 'c'], ['c', 'd'], ['d', 'e', 'yes'], ['d', 'f', 'no'], ['f', 'g'], ['g', 'h'], ['e', 'h'], ['h', 'i', 'fails'], ['h', 'j', 'passes'], ['j', 'k'], ['k', 'l'], ['l', 'm', 'ok'], ['l', 'n', 'error'], ['n', 'o'], ['o', 'k', 'retry', [[1, 3.5], [2, 3.5]]], ['o', 'p']], 4, 5, $legend);

diagram('hybrid-architecture.svg', 'Hybrid KVM infrastructure', [
    'web' => [0, 0, 'Customer website + dashboard (Laravel)', 'step'], 'adm' => [1, 0, 'Admin console (RBAC, 2FA foundation)', 'manual'], 'pay' => [2, 0, 'Razorpay (sandbox first); PTSure gated', 'gate'],
    'core' => [0, 1, 'DeveloperJet core: orders, wallet, invoices, inventory, recipes', 'auto', 2], 'ntf' => [2, 1, 'Telegram + Email + admin centre', 'auto'],
    'q' => [0, 2, 'Queue worker (separate from web requests)', 'auto'], 'w' => [1, 2, 'Deployment worker: fixed recipe commands only', 'auto'], 'adp' => [2, 2, 'Provider adapters (capability-gated)', 'auto'],
    'man' => [1, 3, 'NOW: manual suppliers (DesiVPS, EricHost, Contabo, Server Broker)', 'manual'], 'api' => [2, 3, 'LATER: supplier APIs (only if verified)', 'gate'], 'pve' => [3, 3, 'FUTURE: OVH dedicated + Proxmox KVM (disabled)', 'gate'],
], [['web', 'core'], ['adm', 'core'], ['pay', 'core'], ['core', 'ntf'], ['core', 'q'], ['q', 'w'], ['w', 'adp'], ['w', 'man', 'SSH after assign'], ['adp', 'api'], ['adp', 'pve']], 4, 4, $legend);

diagram('payment-states.svg', 'Order and payment states', [
    'd' => [0, 0, 'draft', 'step'], 'p' => [1, 0, 'pending_payment', 'step'], 'paid' => [2, 0, 'paid (only after verified payment)', 'auto'], 'aw' => [3, 0, 'awaiting_provisioning (Awaiting VPS assignment)', 'manual'],
    'pr' => [3, 1, 'provisioning', 'auto'], 'ac' => [2, 1, 'active', 'auto'], 'f' => [1, 1, 'failed (retryable)', 'bad'], 'c' => [0, 1, 'cancelled', 'bad'],
    'rp' => [2, 2, 'refund_pending', 'manual'], 'rf' => [3, 2, 'refunded', 'end'],
], [['d', 'p'], ['p', 'paid', 'verified'], ['paid', 'aw'], ['aw', 'pr'], ['pr', 'ac', 'success'], ['pr', 'f', 'failure'], ['f', 'aw', 'retry', [[1, 0.5], [3.35, 0.5]]], ['p', 'c'], ['ac', 'rp'], ['rp', 'rf']], 4, 3, ['auto' => 'System', 'manual' => 'Manual step', 'bad' => 'Failure / cancel']);

diagram('deployment-states.svg', 'Deployment states and safe retry', [
    'q' => [0, 0, 'queued', 'step'], 'pf' => [1, 0, 'preflight (resources, host key, OS, arch, disk)', 'auto'], 'r' => [2, 0, 'running recipe steps', 'auto'], 's' => [3, 0, 'succeeded: service active', 'end'],
    'dr' => [0, 1, 'dry_run_ok (simulation only)', 'gate'], 'f' => [2, 1, 'failed at step X (service inactive)', 'bad'], 'rt' => [1, 1, 'admin retry: new attempt resumes after confirmed steps', 'manual'],
], [['q', 'pf'], ['pf', 'r', 'pass'], ['r', 's', 'all steps ok'], ['q', 'dr', 'dry run'], ['pf', 'f', 'fail'], ['r', 'f', 'step fails'], ['f', 'rt'], ['rt', 'q', 'new attempt', [[1, 0.5], [0, 0.5]]]], 4, 2, ['auto' => 'System', 'manual' => 'Admin action', 'bad' => 'Failure', 'gate' => 'Not an install']);
