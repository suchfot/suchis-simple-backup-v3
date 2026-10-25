<?php

if (PHP_SAPI === 'cli-server') {
    define('ABSPATH', __DIR__ . '/');
    define('WP_CONTENT_DIR', getenv('SSBHF_TEST_CONTENT'));
    function ssbhf_test_slash($value)
    {
        return is_array($value) ? array_map('ssbhf_test_slash', $value) : addslashes($value);
    }
    $_GET = array_map('ssbhf_test_slash', $_GET);
    $hooks = [];
    function add_action($hook, $callback)
    {
        global $hooks; $hooks[$hook] = $callback;
    }
    function current_user_can($capability)
    {
        return $capability === 'manage_options' && ($_GET['role'] ?? 'admin') === 'admin';
    }
    function wp_unslash($value)
    {
        return stripslashes($value);
    }
    function wp_verify_nonce($nonce, $action)
    {
        return hash_equals(hash('sha256', $action), $nonce);
    }
    function admin_url($path)
    {
        return '/' . $path;
    }
    function wp_nonce_url($url, $action)
    {
        return esc_html($url . '&_wpnonce=' . hash('sha256', $action));
    }
    function esc_html($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
    function esc_attr($value)
    {
        return esc_html($value);
    }
    function esc_url($value)
    {
        return esc_html(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
    }
    function sanitize_text_field($value)
    {
        return strip_tags($value);
    }
    function sanitize_file_name($value)
    {
        return basename($value);
    }
    function size_format($value)
    {
        return (string) $value;
    }
    function get_option($name)
    {
        return $name === 'date_format' ? 'Y-m-d' : 'H:i:s';
    }
    function date_i18n($format, $time)
    {
        return date($format, $time);
    }
    function status_header($status)
    {
        http_response_code($status);
    }
    function nocache_headers()
    {
        header('Cache-Control: no-store');
    }
    function wp_die($message, $title = '', $args = [])
    {
        http_response_code($args['response'] ?? 500);
        header('Content-Type: text/html; charset=UTF-8');
        exit('<!DOCTYPE html><title>' . esc_html($title) . '</title><p>' . $message . '</p>');
    }
    include dirname(__DIR__) . '/suchis-simple-backup.php';
    if (($_GET['action'] ?? '') === 'ssbhf_download') {
        if (isset($_GET['buffer'])) {
            ob_start();
            echo 'Unexpected buffered HTML';
        }
        $hooks['admin_post_ssbhf_download']();
        exit;
    }
    Ssbhf_Render_page();
    exit;
}
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

function ssbhf_test_request(string $url): array
{
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 15]]);
    $body = file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException('HTTP request failed.');
    }
    $response_headers = http_get_last_response_headers();
    $headers = [];
    foreach ($response_headers as $line) {
        if (strpos($line, ':') !== false) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower($name)] = trim($value);
        }
    }
    preg_match('/\s(\d{3})\s/', $response_headers[0], $match);
    return [(int) $match[1], $headers, $body];
}
function ssbhf_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . "\n";
}

$root = sys_get_temp_dir() . '/ssbhf-download-' . bin2hex(random_bytes(8));
mkdir($root);
mkdir($root . '/backups');
$name = 'wp-backup_files-db_example_2026-10-25_120000.zip';
$path = $root . '/backups/' . $name;
$zip = new ZipArchive();
$zip->open($path, ZipArchive::CREATE);
$zip->addFromString('test.bin', random_bytes(3 * 1024 * 1024));
$zip->close();
copy($path, $root . '/outside.zip');
symlink($root . '/outside.zip', $root . '/backups/linked.zip');
mkdir($root . '/backups/directory.zip');
touch($root . '/backups/empty.zip');
$hash = hash_file('sha256', $path);
$size = filesize($path);
$socket = stream_socket_server('tcp://127.0.0.1:0', $error_number, $error_message);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$environment = getenv();
$environment['SSBHF_TEST_CONTENT'] = $root;
$server = proc_open(
    [PHP_BINARY, '-d', 'error_reporting=32767', '-d', 'display_errors=1', '-S', $address, __FILE__],
    [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']],
    $pipes, __DIR__, $environment
);
$exit_code = 0;
try {
    $startup = fgets($pipes[2]);
    stream_set_blocking($pipes[2], false);
    ssbhf_test_assert($startup !== false && strpos($startup, 'started') !== false, 'Isolated PHP HTTP server started.');
    $base = 'http://' . $address;
    [$status, , $page] = ssbhf_test_request($base . '/?ssb_notice=created&file=' . rawurlencode($name));
    preg_match_all('/href="([^"]*action=ssbhf_download[^"]*)"/', $page, $matches);
    $links = array_map(static fn($link) => html_entity_decode($link, ENT_QUOTES, 'UTF-8'), $matches[1]);
    $links = array_values(array_filter($links, static fn($link) => strpos($link, rawurlencode($name)) !== false));
    ssbhf_test_assert($status === 200 && count($links) === 2, 'Both download buttons render matching links.');
    foreach ($links as $index => $link) {
        [$status, $headers, $body] = ssbhf_test_request($base . $link);
        ssbhf_test_assert($status === 200 && ($headers['content-type'] ?? '') === 'application/zip' && strpos($headers['content-disposition'] ?? '', $name) !== false, 'Button ' . ($index + 1) . ': ZIP headers and original filename.');
        ssbhf_test_assert((int) ($headers['content-length'] ?? 0) === $size && strlen($body) === $size && hash('sha256', $body) === $hash && substr($body, 0, 4) === "PK\x03\x04", 'Button ' . ($index + 1) . ': exact ZIP bytes, size, signature and SHA-256.');
    }
    [$status, , $body] = ssbhf_test_request($base . $links[0] . '&buffer=1');
    ssbhf_test_assert($status === 200 && hash('sha256', $body) === $hash, 'Buffered HTML does not contaminate the ZIP.');
    parse_str(parse_url($links[0], PHP_URL_QUERY), $valid);
    $cases = [
        'missing nonce' => [array_diff_key($valid, ['_wpnonce' => true]), 403],
        'expired nonce' => [array_merge($valid, ['_wpnonce' => 'expired']), 403],
        'array nonce' => [array_merge($valid, ['_wpnonce' => ['invalid']]), 403],
        'unauthorized user' => [array_merge($valid, ['role' => 'subscriber']), 403],
        'missing file' => [array_merge($valid, ['file' => 'missing.zip']), 404],
        'parent traversal' => [array_merge($valid, ['file' => '../outside.zip']), 400],
        'Windows traversal' => [array_merge($valid, ['file' => '..\\outside.zip']), 400],
        'array filename' => [array_merge($valid, ['file' => ['invalid']]), 400],
        'header injection' => [array_merge($valid, ['file' => "invalid\r\nheader.zip"]), 400],
        'external symlink' => [array_merge($valid, ['file' => 'linked.zip']), 404],
        'directory' => [array_merge($valid, ['file' => 'directory.zip']), 404],
        'empty file' => [array_merge($valid, ['file' => 'empty.zip']), 500],
    ];
    chmod($path, 0000);
    if (!is_readable($path)) {
        $cases['unreadable file'] = [$valid, 500];
    }
    foreach ($cases as $label => [$query, $expected]) {
        [$status, $headers, $body] = ssbhf_test_request($base . '/admin-post.php?' . http_build_query($query));
        ssbhf_test_assert($status === $expected && !isset($headers['content-disposition']) && strpos($body, 'Backup-Download fehlgeschlagen') !== false, $label . ': correct error status, no ZIP attachment.');
    }
    $log = $startup . stream_get_contents($pipes[2]);
    ssbhf_test_assert(!preg_match('/Deprecated|Warning|Fatal error|TypeError/', $log), 'No PHP 8.5 deprecations, warnings, fatal errors or TypeErrors.');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exit_code = 1;
} finally {
    proc_terminate($server);
    fclose($pipes[0]);
    fclose($pipes[2]);
    proc_close($server);
    chmod($path, 0600);
    foreach (glob($root . '/backups/*') as $item) {
        if (is_dir($item)) {
            rmdir($item);
        } else {
            unlink($item);
        }
    }
    unlink($root . '/outside.zip');
    rmdir($root . '/backups');
    rmdir($root);
}
exit($exit_code);