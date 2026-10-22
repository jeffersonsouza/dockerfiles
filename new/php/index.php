<?php

header('Cache-Control: no-store');
header('Vary: Accept');

$checks = ['fpm' => PHP_SAPI === 'fpm-fcgi'];
foreach (['json', 'curl', 'mbstring', 'openssl', 'PDO', 'pdo_mysql', 'redis', 'sodium', 'zip', 'Zend OPcache'] as $extension) {
    $checks[$extension] = extension_loaded($extension);
}

$healthy = !in_array(false, $checks, true);
http_response_code($healthy ? 200 : 503);

$generatedAt = new DateTimeImmutable();
$data = [
    'status' => $healthy ? 'ok' : 'error',
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'environment' => [
        'os' => PHP_OS_FAMILY,
        'architecture' => php_uname('m'),
        'timezone' => date_default_timezone_get(),
    ],
    'checks' => $checks,
    'extensions' => get_loaded_extensions(),
    'generated_at' => $generatedAt->format('Y-m-d\TH:i:s.uP'),
];

if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    return;
}

header('Content-Type: text/html; charset=utf-8');
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$benchmarkFile = __DIR__ . '/benchmark.json';
$benchmark = is_file($benchmarkFile) ? json_decode(file_get_contents($benchmarkFile), true) : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $healthy ? 'PHP is ready' : 'Check your PHP environment' ?></title>
    <style>
        :root { color-scheme: light; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #403d56; background: #f3f4f6; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100svh; display: grid; place-items: center; padding: 48px 24px; }
        main { width: 100%; min-width: 0; max-width: 660px; text-align: center; }
        .cats { display: block; width: min(440px, 44vw); height: auto; margin: 0 auto 26px; mix-blend-mode: multiply; }
        .status { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border: 1px solid #c9e7d9; border-radius: 99px; background: #e8f5ed; color: #256344; font-size: 12px; font-weight: 600; }
        .status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .status.error { color: #913a32; background: #fceeea; border-color: #efc9c1; }
        h1 { margin: 17px 0 10px; font-size: clamp(26px, 4vw, 34px); letter-spacing: -1px; font-weight: 650; }
        .intro { margin: 0; color: #706c7e; font-size: 14px; line-height: 1.6; }
        .panel { margin-top: 28px; border: 1px solid #e3e3eb; border-radius: 18px; background: #fff; box-shadow: 0 8px 32px #403d5605; overflow: hidden; text-align: left; }
        dl { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px 20px; padding: 25px 28px; margin: 0; }
        dt { color: #706c7e; font-size: 10px; font-weight: 650; letter-spacing: 1.1px; text-transform: uppercase; }
        dd { margin: 7px 0 0; font-size: 14px; font-weight: 550; overflow-wrap: anywhere; }
        .verified { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding: 15px 28px; border-top: 1px solid #efeff4; background: #fbfbfd; font-size: 11px; color: #706c7e; }
        .verified strong { color: #605b80; font-weight: 550; }
        form { margin: 24px 0 0; }
        button { display: inline-flex; align-items: center; justify-content: center; gap: 9px; padding: 11px 18px; border: 1px solid #6657f0; border-radius: 9px; background: #6b5cff; color: #fff; font: inherit; font-size: 12px; font-weight: 550; cursor: pointer; box-shadow: 0 3px 8px #6b5cff18; }
        button:hover { background: #5c4de9; }
        button svg { width: 14px; height: 14px; }
        button:focus-visible, summary:focus-visible { outline: 3px solid #b5adff; outline-offset: 4px; }
        details { margin-top: 22px; font-size: 12px; color: #706c7e; }
        summary { width: fit-content; margin: auto; cursor: pointer; }
        .diagnostics { margin-top: 16px; padding: 20px 24px; border: 1px solid #e3e3eb; border-radius: 12px; background: #fff; text-align: left; }
        .checks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 24px; padding: 0; margin: 0; list-style: none; }
        .checks li { display: flex; justify-content: space-between; gap: 8px; }
        .pass { color: #256344; }
        .fail { color: #913a32; }
        .extensions { margin: 18px 0 0; padding-top: 16px; border-top: 1px solid #efeff4; line-height: 1.8; overflow-wrap: anywhere; }
        .diagnostics h2 { margin: 0 0 12px; color: #403d56; font-size: 14px; }
        .diagnostics h2:not(:first-child) { margin-top: 24px; }
        .diagnostics p, .guidance { line-height: 1.7; }
        .guidance { margin: 16px 0 0; padding-left: 18px; }
        .guidance li + li { margin-top: 12px; }
        code { color: #5146a7; font-size: 11px; overflow-wrap: anywhere; }
        pre { margin: 14px 0; padding: 12px; border-radius: 8px; background: #f5f4fa; white-space: pre-wrap; }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; line-height: 1.5; text-align: left; }
        caption { padding-bottom: 12px; text-align: left; line-height: 1.7; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #efeff4; vertical-align: top; }
        th { color: #403d56; font-weight: 600; }
        .benchmark-table th, .benchmark-table td { white-space: nowrap; }
        .table-hint { display: none; }
        .note { margin: 18px 0 0; font-size: 11px; color: #706c7e; line-height: 1.6; }
        @media (max-width: 600px) {
            body { padding: 32px 20px; }
            .cats { width: min(360px, 80vw); margin-bottom: 24px; }
            dl { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 22px; gap: 22px 16px; }
            .verified { padding: 14px 22px; }
            .checks { grid-template-columns: 1fr; }
            .table-hint { display: block; }
        }
    </style>
</head>
<body>
<main>
    <img class="cats" src="/welcome-cats.png" width="1778" height="918" alt="Two cats welcoming you above the word Welcome">
    <div class="status<?= $healthy ? '' : ' error' ?>"><?= $healthy ? 'PHP is running' : 'Environment needs attention' ?></div>
    <h1><?= $healthy ? 'All set and ready.' : 'Check your environment.' ?></h1>
    <p class="intro"><?= $healthy ? 'Nginx and PHP-FPM are working together.' : 'PHP responded, but one or more checks failed.' ?></p>

    <section class="panel" aria-label="PHP environment information">
        <dl>
            <div><dt>PHP</dt><dd><?= $escape(PHP_VERSION) ?></dd></div>
            <div><dt>Runtime</dt><dd><?= $escape(PHP_SAPI) ?></dd></div>
            <div><dt>System</dt><dd><?= $escape(PHP_OS_FAMILY) ?> · <?= $escape($data['environment']['architecture']) ?></dd></div>
            <div><dt>Timezone</dt><dd><?= $escape($data['environment']['timezone']) ?></dd></div>
            <div><dt>Extensions</dt><dd><?= count($data['extensions']) ?> loaded</dd></div>
            <div><dt>Checks</dt><dd><?= count(array_filter($checks)) ?> of <?= count($checks) ?> passed</dd></div>
        </dl>
        <div class="verified">
            <strong>Generated by PHP</strong>
            <time datetime="<?= $escape($data['generated_at']) ?>"><?= $escape($generatedAt->format('Y-m-d · H:i:s.v T')) ?></time>
        </div>
    </section>

    <form method="get">
        <button type="submit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 6.1a8 8 0 0 1 13.2 3.2M4.7 14.7a8 8 0 0 0 13.2 3.2"/></svg>
            Check again
        </button>
    </form>
    <details id="checks">
        <summary>View extensions and checks</summary>
        <div class="diagnostics">
            <ul class="checks">
                <?php foreach ($checks as $extension => $passed): ?>
                    <li><span><?= $escape($extension) ?></span><span class="<?= $passed ? 'pass' : 'fail' ?>"><?= $passed ? 'OK' : 'Failed' ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="extensions"><strong>Loaded extensions</strong><br><?= $escape(implode(' · ', $data['extensions'])) ?></p>
        </div>
    </details>
    <details id="configuration">
        <summary>Configuration basics</summary>
        <div class="diagnostics">
            <h2>Active PHP settings</h2>
            <div class="table-scroll" role="region" aria-label="Active PHP settings" tabindex="0">
                <table>
                    <thead><tr><th scope="col">Setting</th><th scope="col">Current value</th></tr></thead>
                    <tbody>
                    <?php foreach (['memory_limit', 'upload_max_filesize', 'post_max_size', 'opcache.memory_consumption', 'opcache.revalidate_freq'] as $setting): ?>
                        <tr><th scope="row"><code><?= $escape($setting) ?></code></th><td><?= $escape((string) ini_get($setting)) ?><?= $setting === 'opcache.memory_consumption' ? ' MiB' : ($setting === 'opcache.revalidate_freq' ? ' s' : '') ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h2>Container sizing</h2>
            <table>
                <thead><tr><th scope="col">Container RAM</th><th scope="col">FPM worker limit</th><th scope="col">OPcache</th></tr></thead>
                <tbody>
                    <tr><th scope="row">256 MiB</th><td>1 (image default)</td><td>64 MiB</td></tr>
                    <tr><th scope="row">512 MiB</th><td>2</td><td>64 MiB</td></tr>
                </tbody>
            </table>
            <p>Starting profiles, not automatic sizing. Set the actual RAM limit in Docker or your orchestrator. PHP's memory limit applies to each request; native allocations and other processes use additional memory.</p>
            <p>For a 512 MiB container, set this environment variable before starting FPM:</p>
            <pre><code>PHP_FPM_MAX_CHILDREN=2</code></pre>
            <ul class="guidance">
                <li><strong>PHP overrides:</strong> add an INI file at <code>/usr/local/etc/php/conf.d/zzz-app.ini</code>, then recreate the container. Pool settings in <code>/usr/local/etc/php-fpm.d/zz-custom.conf</code> take precedence over INI defaults. Keep upload limits aligned with Nginx's <code>client_max_body_size</code> (35 MiB by default).</li>
                <li><strong>HTTPS behind a proxy:</strong> trust only your proxy's actual IPs/CIDRs using Nginx <code>set_real_ip_from</code>. Configure trusted HTTPS forwarding in your application. For HTTPS sessions, enable <code>session.cookie_secure=On</code> and choose <code>session.cookie_samesite</code> for your sign-in flow.</li>
                <li><strong>Caching:</strong> responses need an explicit public TTL to enter FastCGI cache. Private/no-store responses, session cookies and Authorization bypass it. Keep cache locks limited to dedicated cacheable locations.</li>
                <li><strong>Health and tuning:</strong> <code>/fpm-ping</code> and <code>/fpm-status</code> are available only from inside the container via loopback. The ping shares the application pool. Measure your application's worker memory, queue and p95/p99 latency before increasing concurrency.</li>
            </ul>
        </div>
    </details>
    <details id="load-test">
        <summary>Load test reference</summary>
        <div class="diagnostics">
            <h2>Recorded benchmark</h2>
            <?php if (is_array($benchmark) && !empty($benchmark['profiles'])): ?>
                <div class="table-scroll" role="region" aria-label="Recorded load test results" tabindex="0">
                    <table class="benchmark-table">
                        <caption><?= $escape($benchmark['date']) ?> · <?= $escape($benchmark['architecture']) ?> · 1 vCPU · 32 concurrent clients<br>Dynamic JSON index, no cache · ApacheBench / HTTP 1.0 · 10 s per scenario</caption>
                        <thead><tr><th scope="col">PHP</th><th scope="col">RAM</th><th scope="col">Workers</th><th scope="col">Req/s</th><th scope="col">p99</th><th scope="col">Peak RAM*</th></tr></thead>
                        <tbody>
                        <?php foreach ($benchmark['profiles'] as $profile): ?>
                            <tr><th scope="row"><?= $escape($profile['php_version']) ?></th><td><?= (int) $profile['memory_mib'] ?> MiB</td><td><?= (int) $profile['workers'] ?></td><td><?= number_format($profile['index_rps'], 0, '.', ',') ?></td><td><?= (int) $profile['index_p99_ms'] ?> ms</td><td><?= number_format($profile['memory_peak_mib'], 1) ?> MiB</td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="table-hint">Scroll horizontally to see all columns.</p>
                <p>*Peak memory covers all five scenarios, including concurrent requests allocating 96 MiB each. All profiles completed without HTTP/client errors, OOM kills or service restarts.</p>
            <?php else: ?>
                <p>No benchmark has been recorded for this build.</p>
            <?php endif; ?>
            <p>This is a saved reference, not a live test or a production capacity guarantee. The load generator shared the Docker host. Database, Redis, TLS and application workloads were not included. Run your own tests with your application's traffic and resource limits.</p>
        </div>
    </details>
    <p class="note">Every request runs a fresh check. No cache.</p>
</main>
</body>
</html>
