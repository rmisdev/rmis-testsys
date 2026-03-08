# RMIS - 組織管理アプリケーション

## 概要

組織一覧を管理するシンプルなWebアプリケーション（PHP + SQLite）。
Docker 環境での実行に加え、Windows / macOS / Linux 向けの単一バイナリとしても配布できます。

## 実行形態

| 実行形態 | 説明 |
|----------|------|
| Docker (Apache + PHP) | Docker Compose で LAMP 環境を構築 |
| PHP 組み込みサーバー | `php server.php` で即座に起動 |
| 単一バイナリ (exe) | micro.sfx + PHAR でランタイムごとパッケージ |

## 技術構成

- **PHP**: 8.4（micro.sfx）/ 8.2（Docker）
- **SQLite**: 3
- **必要な PHP 拡張**: pdo_sqlite, phar, mbstring
- **ビルドツール**: [static-php-cli (micro.sfx)](https://github.com/crazywhalecc/static-php-cli)

## ディレクトリ構成

```
.
├── docker-compose.yml      # Docker Compose 定義
├── server.php              # サーバーエントリポイント（ソケットサーバー / php -S 自動切替）
├── build_phar.php          # PHAR アーカイブ作成スクリプト
├── build.sh                # macOS/Linux ビルドスクリプト
├── build.bat               # Windows ビルドスクリプト
├── micro.sfx               # PHP ランタイム（自分でダウンロード・配置）
├── docker/
│   └── apache/
│       ├── Dockerfile      # PHP 8.2 + Apache イメージ
│       ├── php.ini         # PHP設定
│       └── sites-available/
│           └── 000-default.conf
├── src/
│   └── index.php           # アプリケーション本体（CRUD）
├── data/
│   └── rmis.sqlite         # SQLiteデータベースファイル
└── dist/
    ├── app.phar            # PHAR アーカイブ（ビルド生成物）
    └── rmis.exe            # Windows 単一実行ファイル（ビルド生成物）
```

---

## 1. Docker (Apache + PHP) で実行

### 起動

```bash
docker-compose up -d --build
```

### 停止

```bash
docker-compose down
# データを保持せず停止（ボリューム削除）
docker-compose down -v
```

### アクセス

http://localhost:8080/index.php

---

## 2. PHP 組み込みサーバーで実行

Docker不要。PHP がインストールされた環境であればすぐに起動できます。

```bash
php server.php
```

- ブラウザが自動で開きます
- http://127.0.0.1:8080 でアクセス
- Ctrl+C で停止

---

## 3. Windows exe を作成する

PHP ランタイムごと単一の `.exe` にパッケージングし、PHP 未インストールの Windows PC でも実行できるようにします。

### 前提条件

- PHP CLI（ビルド用の開発マシンにのみ必要）
- `micro.sfx`（PHP ランタイム）

### 手順

#### 1) micro.sfx をダウンロード

[static-php-cli ダウンロードページ](https://dl.static-php.dev/static-php-cli/windows/spc-max/) から
Windows 用の `micro` zip をダウンロードします。

> **spc-max** を選んでください（`pdo_sqlite` 拡張が含まれています）。
> spc-min には `pdo_sqlite` が含まれておらず、アプリが動作しません。

```bash
# 例: PHP 8.4.18
curl -L -o micro_win.zip "https://dl.static-php.dev/static-php-cli/windows/spc-max/php-8.4.18-micro-win.zip"
unzip micro_win.zip
# → micro.sfx が展開される
```

`micro.sfx` をプロジェクトルートに配置します。

#### 2) PHAR を作成

```bash
php -d phar.readonly=0 build_phar.php
```

#### 3) exe を作成

**macOS / Linux（開発マシン）:**
```bash
cat micro.sfx dist/app.phar > dist/rmis.exe
```

**Windows:**
```cmd
copy /b micro.sfx + dist\app.phar dist\rmis.exe
```

または **`build.bat`（Windows）/ `build.sh`（macOS/Linux）** を実行すると上記すべてを自動実行します。

#### 4) 実行

`dist\rmis.exe` をダブルクリックすると、ソケットベースの HTTP サーバーが起動しブラウザが自動で開きます。

> **仕組み**: `rmis.exe` = `micro.sfx`（PHP ランタイム）+ `app.phar`（アプリコード）。  
> micro.sfx の SAPI は `micro` であり `php -S` が使えないため、  
> ソケットサーバー（`stream_socket_server`）で HTTP リクエストを直接処理します。

### macOS / Linux バイナリの場合

```bash
./build.sh
# または手動で:
cat micro.sfx dist/app.phar > dist/rmis && chmod +x dist/rmis
```

---

## アプリケーション機能

- 組織一覧の表示（ID、組織名、登録日時）
- 組織の追加
- 組織の削除
- 初期データへのリセット

### 初期データ

| ID | 組織名 |
|----|--------|
| 1 | サンプル国立大学 |
| 2 | テスト研究会 |
| 3 | デモNPO法人 |

## データベース情報

| 項目 | 値 |
|------|-----|
| データベース種別 | SQLite 3 |
| ファイル | `data/rmis.sqlite` |
| Docker時パス | `/var/www/data/rmis.sqlite` |
| テーブル | `organizations` (id, name, created_at) |

## サーバーの動作モード

| モード | 条件 | 方式 |
|--------|------|------|
| CLI モード | `php server.php` / `php dist/app.phar` | `php -S`（PHP 組み込みサーバー） |
| micro.sfx モード | `rmis.exe` / `rmis` バイナリ | ソケットサーバー（`stream_socket_server`） |

## 注意事項

- `docker-compose down -v` を実行するとSQLiteデータがリセットされます
- exe / バイナリ実行時、PHAR 内のファイルはシステム一時ディレクトリに展開されます
- テーブルと初期データは初回アクセス時に自動作成されます
- exe 作成には **spc-max** ビルドの `micro.sfx` が必要です（`pdo_sqlite` 拡張が必要なため）
- Windows Defender ファイアウォールの警告が出る場合があります（ローカルサーバーのため）
