<?php

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function request(string $path, array $requestHeaders = [], string $method = 'GET', string $host = '127.0.0.1'): array
{
    $headers = [];
    $curl = curl_init('http://' . $host . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower($name)] = trim($value);
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($curl);
    check($body !== false, 'HTTP request failed: ' . curl_error($curl));
    return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body];
}

file_put_contents('/var/www/html/security-fixture.php', <<<'PHP'
<?php
switch ($_GET['mode'] ?? '') {
    case 'runtime':
        echo json_encode([
            'memory_limit' => ini_get('memory_limit'),
            'opcache' => opcache_get_status(false),
            'jit_buffer' => ini_get('opcache.jit_buffer_size'),
            'fix_pathinfo' => ini_get('cgi.fix_pathinfo'),
        ]);
        break;
    case 'ip':
        echo json_encode([$_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_PROXY'] ?? '']);
        break;
    case 'public':
        header('Cache-Control: public, max-age=60');
        echo bin2hex(random_bytes(8));
        break;
    case 'private':
        header('Cache-Control: private, max-age=60');
        echo bin2hex(random_bytes(8));
        break;
    case 'cookie':
        header('Cache-Control: public, max-age=60');
        setcookie('test', 'private', ['httponly' => true]);
        echo bin2hex(random_bytes(8));
        break;
    case 'vary':
        header('Cache-Control: public, max-age=60');
        header('Vary: Accept-Language');
        echo $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'none';
        break;
    case 'session':
        session_start();
        echo session_id();
        break;
    case 'error':
        ini_set('display_errors', '1');
        trigger_error('php-security-log-marker', E_USER_WARNING);
        echo 'warning logged';
        break;
    case 'memory':
        $marker = '/tmp/memory-active-' . getmypid();
        touch($marker);
        $overlap = count(glob('/tmp/memory-active-*'));
        $buffer = str_repeat('x', 96 * 1024 * 1024);
        usleep(2000000);
        $overlap = max($overlap, count(glob('/tmp/memory-active-*')));
        unlink($marker);
        echo json_encode(['peak' => memory_get_peak_usage(true), 'overlap' => $overlap]);
        break;
    case 'slow':
        usleep(6500000);
        echo 'slow request completed';
        break;
    default:
        echo bin2hex(random_bytes(8));
}
PHP);

$runtime = json_decode(request('/security-fixture.php?mode=runtime')['body'], true, 512, JSON_THROW_ON_ERROR);
check($runtime['memory_limit'] === '128M', 'Unexpected FPM memory limit');
check($runtime['opcache']['opcache_enabled'], 'OPcache is disabled in FPM');
check(array_sum($runtime['opcache']['memory_usage']) === 64 * 1024 * 1024, 'OPcache shared memory is not 64 MiB');
check($runtime['jit_buffer'] === '0' && $runtime['fix_pathinfo'] === '0', 'Unexpected JIT or PATH_INFO setting');

$ip = json_decode(request('/security-fixture.php?mode=ip', ['X-Forwarded-For: 203.0.113.9', 'X-Real-IP: 203.0.113.9', 'Proxy: attacker.invalid'])['body'], true, 512, JSON_THROW_ON_ERROR);
check($ip === ['127.0.0.1', '127.0.0.1', '127.0.0.1', ''], 'Untrusted IP or Proxy header reached PHP');

foreach (['', 'private', 'cookie'] as $mode) {
    $first = request('/security-fixture.php?mode=' . $mode);
    $second = request('/security-fixture.php?mode=' . $mode);
    check($first['body'] !== $second['body'], 'An implicit or private response was cached: ' . $mode);
}

$public = '/security-fixture.php?mode=public';
$first = request($public);
$second = request($public);
check($first['body'] === $second['body'] && ($second['headers']['x-fastcgi-cache'] ?? '') === 'HIT', 'Explicit public caching failed');
check((request($public, [], 'HEAD')['headers']['x-fastcgi-cache'] ?? '') === 'HIT', 'HEAD did not share the GET cache');
foreach ([['Cookie: user=alice'], ['Authorization: Bearer smoke-token']] as $headers) {
    $response = request($public, $headers);
    check($response['body'] !== $first['body'] && ($response['headers']['x-fastcgi-cache'] ?? '') === 'BYPASS', 'Authenticated response did not bypass the public cache');
}
foreach (['en', 'fr', 'en', 'fr'] as $language) {
    check(request('/security-fixture.php?mode=vary', ['Accept-Language: ' . $language])['body'] === $language, 'Vary mixed language variants');
}

check(request('/purge/security-fixture.php?mode=public')['status'] === 405, 'GET could purge the cache');
$peerIp = gethostbyname(gethostname());
check(filter_var($peerIp, FILTER_VALIDATE_IP) !== false && $peerIp !== '127.0.0.1', 'Cannot test non-loopback access');
check(request('/purge/security-fixture.php?mode=public', ['X-Forwarded-For: 127.0.0.1', 'X-Real-IP: 127.0.0.1'], 'PURGE', $peerIp)['status'] === 403, 'Public cache purge was allowed');
check(request('/fpm-status', [], 'GET', $peerIp)['status'] === 403, 'FPM status was exposed');
check(request('/fpm-ping', [], 'GET', $peerIp)['status'] === 403, 'FPM ping was exposed');
check(request('/purge/security-fixture.php?mode=public', [], 'PURGE')['status'] === 200, 'Local cache purge failed');
check(request($public)['body'] !== $first['body'], 'Purge did not invalidate the query-string cache key');

mkdir('/var/www/html/.git');
foreach (['/.git/config.js', '/.env.css', '/Dockerfile', '/composer.json'] as $path) {
    file_put_contents('/var/www/html' . $path, 'secret-file-marker');
    check(request($path)['status'] === 403, 'Sensitive file was exposed: ' . $path);
}
foreach (['/source.PHP', '/source.php.bak', '/source.php.jpg', '/source.phar'] as $path) {
    file_put_contents('/var/www/html' . $path, '<?php echo "source-marker";');
    check(request($path)['status'] === 404, 'PHP source suffix was exposed: ' . $path);
}
$notFound = request('/missing.php');
check($notFound['status'] === 404 && ($notFound['headers']['x-content-type-options'] ?? '') === 'nosniff', 'Missing PHP file or error headers were unsafe');
$oversized = request('/security-fixture.php', ['Content-Length: 36700161'], 'POST');
check($oversized['status'] === 413, 'Nginx did not enforce the 35 MiB POST limit');

$static = str_repeat('Static content for compression and sendfile verification. ', 500);
file_put_contents('/var/www/html/compression.txt', $static);
$gzip = request('/compression.txt', ['Accept-Encoding: gzip']);
check(($gzip['headers']['content-encoding'] ?? '') === 'gzip' && gzdecode($gzip['body']) === $static, 'Gzip corrupted static content');
$brotli = request('/compression.txt', ['Accept-Encoding: br']);
check(($brotli['headers']['content-encoding'] ?? '') === 'br' && strlen($brotli['body']) < strlen($static), 'Brotli compression failed');

$session = request('/security-fixture.php?mode=session', ['Cookie: PHPSESSID=attacker-chosen-session']);
check($session['body'] !== 'attacker-chosen-session' && str_contains(strtolower($session['headers']['set-cookie'] ?? ''), 'httponly'), 'Session fixation or HttpOnly protection failed');
check(request('/security-fixture.php?mode=error')['body'] === 'warning logged', 'PHP displayed an error in the HTTP response');
check(request('/security-fixture.php?mode=slow')['body'] === 'slow request completed', 'Slow request failed without tracing privileges');

$multi = curl_multi_init();
$handles = [];
for ($i = 0; $i < (int) getenv('PHP_FPM_MAX_CHILDREN'); $i++) {
    $handle = curl_init('http://127.0.0.1/security-fixture.php?mode=memory');
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    curl_multi_add_handle($multi, $handle);
    $handles[] = $handle;
}
do {
    check(curl_multi_exec($multi, $running) === CURLM_OK, 'Concurrent memory test failed');
    if ($running) { curl_multi_select($multi, 1); }
} while ($running);
$maxOverlap = 0;
foreach ($handles as $handle) {
    $memory = json_decode(curl_multi_getcontent($handle), true, 512, JSON_THROW_ON_ERROR);
    check(curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 200 && $memory['peak'] >= 96 * 1024 * 1024, 'Memory-limited FPM worker failed');
    $maxOverlap = max($maxOverlap, $memory['overlap']);
    curl_multi_remove_handle($multi, $handle);
}
check($maxOverlap === (int) getenv('PHP_FPM_MAX_CHILDREN'), 'Uncached requests were serialized instead of using the available FPM workers');
echo "Headers, cache, purge ACL, protected files, upload limit, compression, sessions, OPcache and concurrent memory allocation OK", PHP_EOL;
