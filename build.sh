#!/bin/bash
# RMIS ビルドスクリプト
# PHAR 作成 → micro.sfx と結合してネイティブバイナリを生成
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DIST_DIR="${SCRIPT_DIR}/dist"

echo "=== RMIS ビルド ==="

# --- Step 1: PHAR 作成 ---
echo "--- PHAR 作成 ---"
php -d phar.readonly=0 "${SCRIPT_DIR}/build_phar.php"

# --- Step 2: micro.sfx と結合 ---
# micro.sfx のパスを探す（カレントディレクトリ or buildroot）
MICRO_SFX=""
for candidate in \
    "${SCRIPT_DIR}/micro.sfx" \
    "${SCRIPT_DIR}/buildroot/bin/micro.sfx" \
    ; do
    if [[ -f "$candidate" ]]; then
        MICRO_SFX="$candidate"
        break
    fi
done

if [[ -z "$MICRO_SFX" ]]; then
    echo ""
    echo "⚠️  micro.sfx が見つかりません。PHAR のみ作成しました。"
    echo "   単一バイナリを作成するには micro.sfx をプロジェクトルートに配置してください。"
    echo ""
    echo "   Windows 用: https://dl.static-php.dev/static-php-cli/windows/"
    echo "   macOS 用:   https://dl.static-php.dev/static-php-cli/macOS/"
    echo "   Linux 用:   https://dl.static-php.dev/static-php-cli/linux/"
    echo ""
    echo "配布ファイル:"
    ls -lh "${DIST_DIR}/app.phar"
    exit 0
fi

echo "--- ネイティブバイナリ作成 ---"
echo "micro.sfx: ${MICRO_SFX}"

# macOS / Linux
if [[ "$(uname)" != MINGW* && "$(uname)" != CYGWIN* ]]; then
    OUTPUT="${DIST_DIR}/test"
    cat "${MICRO_SFX}" "${DIST_DIR}/app.phar" > "${OUTPUT}"
    chmod +x "${OUTPUT}"
    echo "✅ ${OUTPUT} 作成完了"
fi

echo ""
echo "=== 完了 ==="
echo "配布ファイル:"
ls -lh "${DIST_DIR}/"
