@echo off
chcp 65001 >nul
REM RMIS Windows exe ビルドスクリプト
REM 前提: micro.sfx (Windows用) をプロジェクトルートに配置済みであること
REM
REM micro.sfx のダウンロード:
REM   https://dl.static-php.dev/static-php-cli/windows/

setlocal

set SCRIPT_DIR=%~dp0
set DIST_DIR=%SCRIPT_DIR%dist

echo ==============================
echo  RMIS Windows exe ビルド
echo ==============================
echo.

REM --- Step 1: PHAR 作成 ---
echo --- PHAR 作成 ---
php -d phar.readonly=0 "%SCRIPT_DIR%build_phar.php"
if errorlevel 1 (
    echo PHAR 作成に失敗しました
    pause
    exit /b 1
)

REM --- Step 2: micro.sfx と結合 ---
if exist "%SCRIPT_DIR%micro.sfx" (
    set MICRO_SFX=%SCRIPT_DIR%micro.sfx
) else (
    echo.
    echo ⚠  micro.sfx が見つかりません。PHAR のみ作成しました。
    echo    単一 exe を作成するには micro.sfx をプロジェクトルートに配置してください。
    echo.
    echo    ダウンロード: https://dl.static-php.dev/static-php-cli/windows/
    echo.
    echo 配布ファイル:
    dir /b "%DIST_DIR%\app.phar"
    pause
    exit /b 0
)

echo --- exe 作成 ---
echo micro.sfx: %MICRO_SFX%
copy /b "%MICRO_SFX%" + "%DIST_DIR%\app.phar" "%DIST_DIR%\test.exe" >nul
if errorlevel 1 (
    echo exe 作成に失敗しました
    pause
    exit /b 1
)

REM --- Step 3: Mark of the Web (MOTW) 除去 ---
echo --- MOTW 除去 ---
powershell -Command "Unblock-File -Path '%DIST_DIR%\test.exe'" 2>nul
if errorlevel 0 (
    echo Zone.Identifier を除去しました
) else (
    echo ⚠  MOTW 除去をスキップしました（PowerShell が利用できません）
)

echo.
echo ✅ %DIST_DIR%\test.exe 作成完了
echo.
echo === 完了 ===
echo 作成されたファイル:
dir "%DIST_DIR%\test.exe"
echo.
echo test.exe をダブルクリックで実行できます。
echo.
echo ⚠  スマートアプリコントロールがブロックする場合は、
echo    micro.sfx にも Unblock-File を実行してから再ビルドしてください:
echo    powershell -Command "Unblock-File -Path 'micro.sfx'"
pause
