<?php
// build_phar.php - PHAR アーカイブを作成
// 使い方: php -d phar.readonly=0 build_phar.php
//
// 生成された app.phar は以下のいずれかの方法で実行できる:
//   1) php app.phar                           (PHP がインストール済みの環境)
//   2) copy /b micro.sfx + app.phar rmis.exe  (Windows 単一バイナリ)
//   3) cat micro.sfx app.phar > rmis && chmod +x rmis  (macOS/Linux 単一バイナリ)

$distDir  = __DIR__ . '/dist';
$pharFile = $distDir . '/app.phar';

@mkdir($distDir, 0755, true);
@unlink($pharFile);

$phar = new Phar($pharFile);
$phar->startBuffering();

// ソースファイルを追加（PHPのみ）
$phar->addFile(__DIR__ . '/src/index.php', 'src/index.php');

// SQLiteデータベースを埋め込み（初期データ入り）
if (file_exists(__DIR__ . '/data/rmis.sqlite')) {
    $phar->addFile(__DIR__ . '/data/rmis.sqlite', 'data/rmis.sqlite');
} else {
    // データベースがなければ空ファイルを追加（index.php が自動作成する）
    $phar->addFromString('data/.gitkeep', '');
}

// エントリポイント
$phar->addFile(__DIR__ . '/server.php', 'server.php');
$phar->setDefaultStub('server.php');

$phar->stopBuffering();

$size = round(filesize($pharFile) / 1024);
echo "✅ {$pharFile} 作成完了 ({$size} KB)\n";
echo "\n";
echo "== 実行方法 ==\n";
echo "  php dist/app.phar\n";
echo "\n";
echo "== Windows exe 作成 (micro.sfx が必要) ==\n";
echo "  copy /b micro.sfx + dist\\app.phar dist\\rmis.exe\n";
echo "\n";
echo "== macOS/Linux バイナリ作成 (micro.sfx が必要) ==\n";
echo "  cat micro.sfx dist/app.phar > dist/rmis && chmod +x dist/rmis\n";
