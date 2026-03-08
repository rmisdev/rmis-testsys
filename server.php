<?php
// server.php - RMIS サーバー
// 使い方: php server.php  または  test.exe (micro.sfx + phar)
//
// PHAR/micro.sfx 実行時はソケットベースの組み込みHTTPサーバーで動作する。
// 通常のPHP CLIから実行した場合は php -S (組み込みサーバー) を使う。

$host = '127.0.0.1';
$port = 8080;

// ポートが使用中なら空きポートを探す
for ($try = 0; $try < 10; $try++) {
    $sock = @stream_socket_client("tcp://{$host}:" . ($port + $try), $errno, $errstr, 1);
    if ($sock === false) {
        $port = $port + $try;
        break;
    }
    fclose($sock);
    if ($try === 9) {
        $port = $port + 10;
    }
}

// ドキュメントルート決定
$pharPath = Phar::running(false);
if ($pharPath !== '') {
    // PHAR 内から実行されている場合
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rmis_' . md5($pharPath);
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
    // ソースファイルを展開
    $phar = new Phar($pharPath);
    foreach (new RecursiveIteratorIterator($phar) as $file) {
        $relative = str_replace('phar://' . $pharPath . '/', '', $file->getPathname());
        $dest = $tmpDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        copy($file->getPathname(), $dest);
    }
    $docroot = $tmpDir . DIRECTORY_SEPARATOR . 'src';
    $dbPath  = $tmpDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'rmis.sqlite';
    putenv("SQLITE_DB_PATH={$dbPath}");
} else {
    // 通常ファイルから実行
    $docroot = __DIR__ . DIRECTORY_SEPARATOR . 'src';
}

$url = "http://{$host}:{$port}/index.php";

// ブラウザを自動で開く
if (PHP_OS_FAMILY === 'Windows') {
    pclose(popen("start {$url}", 'r'));
} elseif (PHP_OS_FAMILY === 'Darwin') {
    pclose(popen("open {$url} &", 'r'));
} else {
    if (shell_exec('which xdg-open 2>/dev/null')) {
        pclose(popen("xdg-open {$url} &", 'r'));
    }
}

echo "==============================\n";
echo " RMIS サーバー\n";
echo "==============================\n";
echo "\n";
echo "{$url} で起動中...\n";
echo "停止するには Ctrl+C を押してください\n\n";

// ── micro.sfx か通常 CLI かで起動方法を分岐 ──
$isMicro = ($pharPath !== '' && !canRunBuiltinServer());

if ($isMicro) {
    // micro.sfx: ソケットベースの簡易 HTTP サーバー
    runSocketServer($host, $port, $docroot);
} else {
    // 通常 PHP CLI: php -S を使用
    $php = PHP_BINARY;
    $cmd = "\"{$php}\" -S {$host}:{$port} -t \"{$docroot}\"";
    if (PHP_OS_FAMILY === 'Windows') {
        $proc = proc_open($cmd, [STDIN, STDOUT, STDERR], $pipes);
        if (is_resource($proc)) {
            proc_close($proc);
        }
    } else {
        passthru($cmd);
    }
}

// ────────────────────────────────────────────
// micro.sfx で php -S が使えるかチェック
// ────────────────────────────────────────────
function canRunBuiltinServer(): bool
{
    $php = PHP_BINARY;
    // micro.sfx は通常 -S オプションをサポートしない
    // PHP_SAPI が 'micro' の場合は組み込みサーバー不可
    if (PHP_SAPI === 'micro') {
        return false;
    }
    // CLI SAPI であれば php -S が使える
    if (PHP_SAPI === 'cli') {
        return true;
    }
    return false;
}

// ────────────────────────────────────────────
// ソケットベース簡易 HTTP サーバー
// ────────────────────────────────────────────
function runSocketServer(string $host, int $port, string $docroot): void
{
    $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
    if ($server === false) {
        echo "❌ サーバー起動失敗: {$errstr} ({$errno})\n";
        echo "\nEnter キーを押して終了...\n";
        fgets(STDIN);
        return;
    }

    echo "ソケットサーバーモードで起動しました\n\n";

    while (true) {
        $client = @stream_socket_accept($server, -1);
        if ($client === false) {
            continue;
        }

        try {
            handleRequest($client, $docroot);
        } catch (\Throwable $e) {
            $errorBody = "500 Internal Server Error: " . $e->getMessage();
            @fwrite($client, "HTTP/1.1 500 Internal Server Error\r\nContent-Length: " . strlen($errorBody) . "\r\n\r\n" . $errorBody);
        }

        @fclose($client);
    }
}

// ────────────────────────────────────────────
// HTTP リクエストを処理
// ────────────────────────────────────────────
function handleRequest($client, string $docroot): void
{
    // リクエストヘッダーを読む
    $rawHeader = '';
    while (($line = fgets($client)) !== false) {
        $rawHeader .= $line;
        if (rtrim($line) === '') {
            break;
        }
    }

    if ($rawHeader === '') {
        return;
    }

    // リクエスト行をパース
    $lines = explode("\n", $rawHeader);
    $requestLine = trim($lines[0]);
    $parts = explode(' ', $requestLine);
    if (count($parts) < 2) {
        return;
    }

    $method = strtoupper($parts[0]);
    $uri    = $parts[1];

    // ヘッダーをパース
    $headers = [];
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if ($line === '') break;
        $colonPos = strpos($line, ':');
        if ($colonPos !== false) {
            $key = strtolower(trim(substr($line, 0, $colonPos)));
            $val = trim(substr($line, $colonPos + 1));
            $headers[$key] = $val;
        }
    }

    // POST ボディを読む
    $body = '';
    if ($method === 'POST' && isset($headers['content-length'])) {
        $len = (int)$headers['content-length'];
        if ($len > 0) {
            $body = fread($client, $len);
        }
    }

    // URI を分解
    $parsed = parse_url($uri);
    $path   = $parsed['path'] ?? '/';
    $query  = $parsed['query'] ?? '';

    // /index.php か / にルーティング
    if ($path === '/' || $path === '/index.php') {
        // index.php を実行
        $response = executeIndexPhp($docroot, $method, $query, $body, $headers);
        fwrite($client, $response);
    } else {
        // 静的ファイル or 404
        $filePath = $docroot . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (file_exists($filePath) && is_file($filePath)) {
            $content = file_get_contents($filePath);
            $mime = getMimeType($filePath);
            $resp  = "HTTP/1.1 200 OK\r\n";
            $resp .= "Content-Type: {$mime}\r\n";
            $resp .= "Content-Length: " . strlen($content) . "\r\n";
            $resp .= "\r\n";
            $resp .= $content;
            fwrite($client, $resp);
        } else {
            $resp = "HTTP/1.1 404 Not Found\r\nContent-Length: 9\r\n\r\nNot Found";
            fwrite($client, $resp);
        }
    }

    $logTime = date('H:i:s');
    echo "[{$logTime}] {$method} {$uri}\n";
}

// ────────────────────────────────────────────
// index.php をサブプロセスなしで実行
// ────────────────────────────────────────────
function executeIndexPhp(string $docroot, string $method, string $query, string $body, array $headers): string
{
    // スーパーグローバルを設定
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['QUERY_STRING']   = $query;
    $_SERVER['REQUEST_URI']    = '/index.php' . ($query !== '' ? '?' . $query : '');
    $_SERVER['SCRIPT_NAME']    = '/index.php';
    $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    $_SERVER['HTTP_HOST']      = $headers['host'] ?? 'localhost';

    // GET パラメータ
    $_GET = [];
    if ($query !== '') {
        parse_str($query, $_GET);
    }

    // POST パラメータ
    $_POST = [];
    if ($method === 'POST' && $body !== '') {
        parse_str($body, $_POST);
    }

    // header() の出力をキャプチャ
    $capturedHeaders = [];
    $headerCallback = function (string $header) use (&$capturedHeaders) {
        $capturedHeaders[] = $header;
    };

    // 出力バッファリングで index.php の出力をキャプチャ
    ob_start();

    // header() をオーバーライドできないので、index.php を require する前に
    // 独自の header 処理が必要 → index.php 内の header() / exit を処理する
    $indexFile = $docroot . DIRECTORY_SEPARATOR . 'index.php';

    $statusCode = 200;
    $responseHeaders = ['Content-Type: text/html; charset=UTF-8'];

    try {
        // index.php を include (header + exit による POST リダイレクトも処理)
        include $indexFile;
    } catch (\Throwable $e) {
        ob_end_clean();
        $errMsg = "Error: " . $e->getMessage();
        return "HTTP/1.1 500 Internal Server Error\r\nContent-Length: " . strlen($errMsg) . "\r\n\r\n" . $errMsg;
    }

    $output = ob_get_clean();

    // header() で送られたヘッダーを確認
    $sentHeaders = headers_list();
    $redirectLocation = null;
    foreach ($sentHeaders as $h) {
        if (stripos($h, 'Location:') === 0) {
            $redirectLocation = trim(substr($h, 9));
        }
    }

    // リダイレクトレスポンス
    if ($redirectLocation !== null) {
        $resp  = "HTTP/1.1 302 Found\r\n";
        $resp .= "Location: {$redirectLocation}\r\n";
        $resp .= "Content-Length: 0\r\n";
        $resp .= "\r\n";

        // 送信済みヘッダーをクリア
        if (function_exists('header_remove')) {
            header_remove();
        }
        return $resp;
    }

    // 通常レスポンス
    $resp  = "HTTP/1.1 {$statusCode} OK\r\n";
    $resp .= "Content-Type: text/html; charset=UTF-8\r\n";
    $resp .= "Content-Length: " . strlen($output) . "\r\n";
    $resp .= "\r\n";
    $resp .= $output;

    if (function_exists('header_remove')) {
        header_remove();
    }

    return $resp;
}

// ────────────────────────────────────────────
// MIME タイプ判定
// ────────────────────────────────────────────
function getMimeType(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $types = [
        'html' => 'text/html',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
    ];
    return $types[$ext] ?? 'application/octet-stream';
}
