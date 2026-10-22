#!/usr/bin/env bash
set -euo pipefail

image=${1:?Usage: smoke-test-php.sh IMAGE PHP_SERIES}
series=${2:?Usage: smoke-test-php.sh IMAGE PHP_SERIES}
memory=${3:-256m}
children=${4:-1}
case "$series" in
  8.2) minimum=8.2.35 ;;
  8.3) minimum=8.3.36 ;;
  8.4) minimum=8.4.27 ;;
  8.5) minimum=8.5.12 ;;
  *) echo "Unsupported PHP series: $series" >&2; exit 1 ;;
esac

if [ "$series" = 8.2 ]; then
  docker run --rm --entrypoint sh "$image" -ec '! apk info --exists php82'
else
  docker run --rm --entrypoint sh "$image" -ec '
    apk info --exists "php$1" "php$1-fpm"
    test "$(cut -d . -f 1,2 /etc/alpine-release)" = 3.24
    test "$(readlink -f /usr/local/bin/php)" = "/usr/bin/php$1"
    test "$(readlink -f /usr/local/sbin/php-fpm)" = "/usr/sbin/php-fpm$1"
    test "$(readlink -f /usr/local/etc/php)" = "/etc/php$1"
    test "$(readlink -f /usr/local/etc/php-fpm.d)" = "/etc/php$1/php-fpm.d"
    test "$(readlink -f /usr/local/etc/php-fpm.conf)" = "/etc/php$1/php-fpm.conf"
  ' -- "${series//./}"
fi

docker run --rm --entrypoint php "$image" -r '
    if (PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION !== $argv[1]
        || version_compare(PHP_VERSION, $argv[2], "<")) {
        fwrite(STDERR, "Unexpected or vulnerable PHP version: " . PHP_VERSION . PHP_EOL);
        exit(1);
    }
    $extensions = ["bcmath", "ctype", "curl", "dom", "fileinfo", "gd", "iconv",
        "intl", "json", "mbstring", "mysqli", "openssl", "PDO", "pdo_mysql",
        "pdo_sqlite", "Phar", "posix", "redis", "session", "SimpleXML", "sodium", "sqlite3", "xmlreader",
        "tokenizer", "xml", "xmlwriter", "zip", "zlib", "Zend OPcache"];
    if (in_array($argv[1], ["8.3", "8.4"], true)) { $extensions[] = "imagick"; }
    if ($argv[1] !== "8.2") { $extensions[] = "brotli"; }
    if ($argv[1] === "8.5") { $extensions[] = "ftp"; }
    foreach ($extensions as $extension) {
        if (!extension_loaded($extension)) {
            fwrite(STDERR, "Missing extension: " . $extension . PHP_EOL);
            exit(1);
        }
    }
    if (ini_get("expose_php")) { exit(1); }
    echo "PHP ", PHP_VERSION, ": extensions OK", PHP_EOL;
' -- "$series" "$minimum"

docker run --rm --entrypoint php "$image" -n -r '
    $_SERVER["HTTP_ACCEPT"] = "application/json";
    ob_start();
    require "/var/www/html/index.php";
    $data = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
    if (http_response_code() !== 503 || $data["status"] !== "error"
        || $data["checks"]["fpm"] !== false || $data["checks"]["redis"] !== false) {
        fwrite(STDERR, "Index accepted an invalid runtime" . PHP_EOL);
        exit(1);
    }
    unset($_SERVER["HTTP_ACCEPT"]);
    ob_start();
    require "/var/www/html/index.php";
    $html = ob_get_clean();
    if (http_response_code() !== 503 || !str_contains($html, "Environment needs attention")
        || !str_contains($html, ">Failed<")) {
        fwrite(STDERR, "Index hid a failed runtime check" . PHP_EOL);
        exit(1);
    }
'

container=$(docker run --detach --memory "$memory" --memory-swap "$memory" \
  --env PHP_FPM_MAX_CHILDREN="$children" --env PHP_INDEX_TEST_SECRET=must-not-appear-in-http "$image")
cleanup() {
  result=$?
  if [ "$result" -ne 0 ]; then docker logs "$container" >&2 || true; fi
  docker rm --force "$container" >/dev/null
}
trap cleanup EXIT

docker exec "$container" php-fpm --test
docker exec "$container" nginx -t

ready=false
for _ in {1..30}; do
  if response=$(docker exec "$container" curl --silent --fail --max-time 2 http://127.0.0.1/fpm-ping); then
    if [ "$response" = pong ]; then
      ready=true
      break
    fi
  fi
  sleep 1
done
if [ "$ready" != true ]; then
  echo "Nginx/FPM did not become ready" >&2
  exit 1
fi

# Exercise PHP through Nginx, including private responses that must bypass cache.
docker exec "$container" sh -c 'printf '\''<?php header("Cache-Control: private, no-store"); echo PHP_VERSION; '\'' > /var/www/html/smoke.php'
response=$(docker exec "$container" curl --silent --show-error --fail --max-time 5 --dump-header /tmp/smoke-headers http://127.0.0.1/smoke.php)
test "$response" = "$(docker exec "$container" php -r 'echo PHP_VERSION;')"
docker exec "$container" sh -ec 'test -s /tmp/smoke-headers; if grep -qiE "^(X-Powered-By:|X-FastCGI-Cache: HIT)" /tmp/smoke-headers; then exit 1; fi'
docker exec "$container" curl --silent --show-error --fail --max-time 5 --dump-header /tmp/smoke-headers --output /dev/null http://127.0.0.1/smoke.php
docker exec "$container" sh -ec '! grep -qi "^X-FastCGI-Cache: HIT" /tmp/smoke-headers'

docker exec "$container" sh -c 'printf '\''<?php echo $_COOKIE["user"] ?? $_SERVER["HTTP_AUTHORIZATION"] ?? "anonymous"; '\'' > /var/www/html/cache.php'
for _ in {1..3}; do
  test "$(docker exec "$container" curl --silent --show-error --fail --max-time 5 http://127.0.0.1/cache.php)" = anonymous
done
test "$(docker exec "$container" curl --silent --show-error --fail --max-time 5 --header 'Cookie: user=alice' http://127.0.0.1/cache.php)" = alice
test "$(docker exec "$container" curl --silent --show-error --fail --max-time 5 --header 'Authorization: Bearer smoke-token' http://127.0.0.1/cache.php)" = 'Bearer smoke-token'
test "$(docker exec "$container" curl --silent --show-error --fail --max-time 5 http://127.0.0.1/cache.php)" = anonymous
docker exec "$container" curl --silent --show-error --fail --max-time 5 --header 'Accept: application/json' --dump-header /tmp/index-headers --output /tmp/index-response http://127.0.0.1/
docker exec "$container" php -r '
    $body = file_get_contents("/tmp/index-response");
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if ($data["status"] !== "ok" || $data["php_version"] !== PHP_VERSION
        || $data["sapi"] !== "fpm-fcgi" || in_array(false, $data["checks"], true)
        || array_keys($data) !== ["status", "php_version", "sapi", "environment", "checks", "extensions", "generated_at"]
        || array_keys($data["environment"]) !== ["os", "architecture", "timezone"]
        || abs(time() - (new DateTimeImmutable($data["generated_at"]))->getTimestamp()) > 10
        || str_contains($body, getenv("PHP_INDEX_TEST_SECRET"))
        || !preg_match("/^Cache-Control: no-store\r?$/mi", file_get_contents("/tmp/index-headers"))) {
        fwrite(STDERR, "Invalid or unsafe default index response" . PHP_EOL);
        exit(1);
    }
'

docker exec "$container" curl --silent --show-error --fail --max-time 5 --dump-header /tmp/index-html-headers --output /tmp/index-page http://127.0.0.1/
docker exec "$container" curl --silent --show-error --fail --max-time 5 --output /tmp/welcome-cats.png http://127.0.0.1/welcome-cats.png
docker exec "$container" php -r '
    $body = file_get_contents("/tmp/index-page");
    $data = json_decode(file_get_contents("/tmp/index-response"), true, 512, JSON_THROW_ON_ERROR);
    libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $document->loadHTML($body);
    $xpath = new DOMXPath($document);
    $generatedAt = $xpath->evaluate("string(//time/@datetime)");
    if (!str_contains($body, PHP_VERSION) || str_contains($body, "<?php")
        || str_contains($body, getenv("PHP_INDEX_TEST_SECRET"))
        || $xpath->query("//img[@src=\"/welcome-cats.png\"]")->length !== 1
        || $xpath->query("//details")->length !== 3
        || $xpath->query("//details[@open]")->length !== 0
        || $xpath->evaluate("string(//details[@id=\"configuration\"]/summary)") !== "Configuration basics"
        || $xpath->evaluate("string(//details[@id=\"configuration\"]//tbody/tr[1]/td)") !== ini_get("memory_limit")
        || $xpath->evaluate("string(//details[@id=\"load-test\"]/summary)") !== "Load test reference"
        || $xpath->query("//details[@id=\"load-test\"]//tbody/tr")->length !== 8
        || !preg_match("/\\d{4}-\\d{2}-\\d{2}/", $xpath->evaluate("string(//details[@id=\"load-test\"]//caption)"))
        || !str_contains($body, "saved reference, not a live test")
        || $generatedAt === "" || $generatedAt === $data["generated_at"]
        || abs(time() - (new DateTimeImmutable($generatedAt))->getTimestamp()) > 10
        || !preg_match("/^Content-Type: text\/html; charset=utf-8\r?$/mi", file_get_contents("/tmp/index-html-headers"))
        || !preg_match("/^Cache-Control: no-store\r?$/mi", file_get_contents("/tmp/index-html-headers"))
        || file_get_contents("/tmp/welcome-cats.png") !== file_get_contents("/var/www/html/welcome-cats.png")) {
        fwrite(STDERR, "Welcome page, configuration, benchmark, dynamic timestamp or local image failed" . PHP_EOL);
        exit(1);
    }
'

docker cp "$(dirname "${BASH_SOURCE[0]}")/test-php-security.php" "$container:/tmp/test-php-security.php"
docker exec "$container" php /tmp/test-php-security.php
docker logs "$container" 2>&1 | grep -F 'php-security-log-marker' >/dev/null
if docker logs "$container" 2>&1 | grep -F 'failed to ptrace' >/dev/null; then
  echo "FPM attempted a backtrace without tracing privileges" >&2
  exit 1
fi
test "$(docker inspect --format '{{.State.OOMKilled}}' "$container")" = false
docker stats --no-stream --format '{{.MemUsage}}' "$container"

docker stop --time 140 "$container" >/dev/null
if [ "$(docker inspect --format '{{.State.ExitCode}}' "$container")" != 0 ]; then
  echo "Container did not shut down cleanly" >&2
  exit 1
fi
echo "Nginx/FPM startup, HTTP, security, cache privacy and shutdown OK ($series, $memory, $children worker limit)"
