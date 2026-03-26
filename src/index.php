<?php
// micro.sfx ソケットサーバーモードでは header() が実際には送信されないため、
// グローバル変数でリダイレクト先を記録し、サーバー側でレスポンスを構築する。
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// ── データベース接続 ──
$dbPath = getenv('SQLITE_DB_PATH') ?: __DIR__ . '/../data/rmis.sqlite';
$dbDir  = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

$pdo = new PDO("sqlite:{$dbPath}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// テーブル作成（初回のみ）
$pdo->exec('CREATE TABLE IF NOT EXISTS organizations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime("now","localtime"))
)');

// 初期データ
$count = (int)$pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
if ($count === 0) {
    $pdo->exec("INSERT INTO organizations (name) VALUES ('サンプル国立大学')");
    $pdo->exec("INSERT INTO organizations (name) VALUES ('テスト研究会')");
    $pdo->exec("INSERT INTO organizations (name) VALUES ('デモNPO法人')");
}

// ── リクエスト処理 ──
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $message = '組織名を入力してください';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare('INSERT INTO organizations (name) VALUES (:name)');
            $stmt->execute([':name' => $name]);
            $message = "「{$name}」を追加しました";
            $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM organizations WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $message = '削除しました';
            $messageType = 'success';
        }
    } elseif ($action === 'reset') {
        $pdo->exec('DELETE FROM organizations');
        $pdo->exec("INSERT INTO organizations (name) VALUES ('サンプル国立大学')");
        $pdo->exec("INSERT INTO organizations (name) VALUES ('テスト研究会')");
        $pdo->exec("INSERT INTO organizations (name) VALUES ('デモNPO法人')");
        $message = '初期データにリセットしました';
        $messageType = 'success';
    }

    // POST後リダイレクト（二重送信防止）— メッセージはクエリパラメータで渡す
    $qs = http_build_query(['msg' => $message, 'type' => $messageType]);
    if (defined('RMIS_SOCKET_SERVER') && RMIS_SOCKET_SERVER) {
        // ソケットサーバーモード: exit するとサーバーが落ちるため return で抜ける
        $GLOBALS['_RMIS_REDIRECT'] = "index.php?{$qs}";
        return;
    }
    header("Location: index.php?{$qs}");
    exit;
}

// リダイレクト後のメッセージ取得
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? '';
}

// ── 組織一覧取得 ──
$organizations = $pdo->query('SELECT id, name, created_at FROM organizations ORDER BY id')
                     ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RMIS - 組織一覧</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .success { color: #28a745; font-weight: bold; }
        .error   { color: #dc3545; font-weight: bold; }
        .info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th { background-color: #007bff; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 5px;
        }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-danger  { background-color: #dc3545; color: white; }
        .btn-danger:hover  { background-color: #c82333; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #545b62; }
        .actions { margin-top: 20px; display: flex; gap: 8px; }
        .delete-btn {
            padding: 5px 10px; font-size: 12px;
            background-color: #dc3545; color: white;
            border: none; border-radius: 3px; cursor: pointer;
        }
        .delete-btn:hover { background-color: #c82333; }
    </style>
</head>
<body>
<div class="container">
    <h1>🏢 組織一覧</h1>

    <?php if ($message): ?>
        <div class="info"><span class="<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></span></div>
    <?php endif; ?>

    <!-- 組織追加フォーム -->
    <form method="post" action="index.php">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label for="orgName">新規組織名:</label>
            <input type="text" id="orgName" name="name" placeholder="組織名を入力してください" required>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">追加</button>
    </form>
    <form method="post" action="index.php" onsubmit="return confirm('すべてのデータを削除し、初期データにリセットしますか？');" style="display:inline">
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="btn btn-secondary">初期データにリセット</button>
    </form>
        </div>

    <!-- 組織一覧テーブル -->
    <?php if (count($organizations) > 0): ?>
        <table>
            <tr><th>ID</th><th>組織名</th><th>登録日時</th><th>操作</th></tr>
            <?php foreach ($organizations as $org): ?>
                <tr>
                    <td><?= htmlspecialchars($org['id']) ?></td>
                    <td><?= htmlspecialchars($org['name']) ?></td>
                    <td><?= htmlspecialchars($org['created_at']) ?></td>
                    <td>
                        <form method="post" action="index.php" style="display:inline"
                              onsubmit="return confirm('この組織を削除してもよろしいですか？');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$org['id'] ?>">
                            <button type="submit" class="delete-btn">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <div class="info" style="margin-top: 20px;">合計: <?= count($organizations) ?> 件</div>
    <?php else: ?>
        <div class="info">データがありません</div>
    <?php endif; ?>
</div>
</body>
</html>
