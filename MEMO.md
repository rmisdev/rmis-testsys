# MEMO — exe 生成時の留意点

## micro.sfx のバージョン選択

| ビルド | 含まれる拡張 | 備考 |
|--------|-------------|------|
| **spc-max** | pdo, pdo_sqlite, sqlite3, mbstring, phar 等全拡張 | **こちらを使う** |
| spc-min | ctype, fileinfo, filter, iconv, mbstring, tokenizer, phar のみ | PDO/SQLite 未収録のため動作しない |

- ダウンロード元: https://dl.static-php.dev/static-php-cli/windows/spc-max/
- ファイル名の例: `php-8.3.30-micro-win.zip`（zip の中に `micro.sfx` が入っている）
- **spc-min を使うと `PDO not found` エラーでアプリが起動しない**

## ビルド手順（概要）

```bash
# 1. micro.sfx をプロジェクトルートに配置

# 2. Windows: build.bat を実行 → dist\rmis.exe が生成される
# 3. macOS/Linux: bash build.sh → dist/rmis が生成される

# または手動:
php -d phar.readonly=0 build_phar.php
cat micro.sfx dist/app.phar > dist/rmis.exe    # macOS/Linux から Windows exe を生成
copy /b micro.sfx + dist\app.phar dist\rmis.exe  # Windows の場合
```

## ソケットサーバーモードの制約（exe 実行時）

micro.sfx の SAPI は `micro` であり、`php -S`（組み込みサーバー）が使えない。
そのため exe 実行時は `stream_socket_server` による独自ソケットサーバーで動作する。

### exit の扱い

- ソケットサーバーモードでは `index.php` が `include` で読み込まれるため、`exit` を呼ぶとサーバープロセス全体が終了する（DOSプロンプトが閉じる）
- **POST 後のリダイレクト処理で `exit` ではなく `return` を使う必要がある**
- `RMIS_SOCKET_SERVER` 定数で分岐し、`$GLOBALS['_RMIS_REDIRECT']` にリダイレクト先をセットして `return` する方式を採用

```php
// ソケットサーバーモード時の処理
if (defined('RMIS_SOCKET_SERVER') && RMIS_SOCKET_SERVER) {
    $GLOBALS['_RMIS_REDIRECT'] = "index.php?{$qs}";
    return;
}
header("Location: index.php?{$qs}");
exit;
```

## Windows 実行時の注意

### SmartScreen / スマートアプリコントロール

- `rmis.exe` はコード署名されていないため、Windows Defender SmartScreen やスマートアプリコントロールがブロックする場合がある
- 対処: 「詳細情報」→「実行」で許可、または `Unblock-File` を実行:
  ```powershell
  Unblock-File -Path rmis.exe
  ```
- micro.sfx 自体にも MOTW (Mark of the Web) が付いている場合は、ビルド前に解除しておく:
  ```powershell
  Unblock-File -Path micro.sfx
  ```

### ファイアウォール

- ローカルサーバー (`127.0.0.1:8080`) を起動するため、Windows Defender ファイアウォールの警告が表示される場合がある
- ローカル通信のみのため「プライベートネットワーク」で許可すれば問題ない

### PHAR ファイルの展開先

- exe 実行時、PHAR 内のファイルはシステム一時ディレクトリ（`%TEMP%\rmis_<hash>`）に自動展開される
- SQLite データベースもこの一時ディレクトリ内に作成される
- exe を削除しても一時ディレクトリは残るため、完全に消す場合は手動削除が必要

## GitHub リリース手順

```bash
# 1. タグ作成 & プッシュ
git tag v0.2.0
git push origin main --tags

# 2. リリース作成（gh CLI）
gh release create v0.2.0 dist/rmis.exe \
  --title "v0.2.0 - 改訂版リリース" \
  --notes "変更点..."

# リリースやり直し（タグ・リリース削除→再作成）
gh release delete v0.2.0 --yes
git tag -d v0.2.0
git push origin :refs/tags/v0.2.0
# → 上記の作成手順を再実行
```
