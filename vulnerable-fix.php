<?php
// vulnerable-fix.php
// ⚠️ WARNING: このコードはSQLインジェクションの脆弱性を含む学習用です
// 絶対に本番環境では使用しないでください！

declare(strict_types=1);
mb_internal_encoding('UTF-8');

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=UTF-8');

$DB_DIR  = __DIR__ . '/data';
$DB_PATH = $DB_DIR . '/app.db';

// データディレクトリ作成
if (!is_dir($DB_DIR)) {
    mkdir($DB_DIR, 0770, true);
}

function db(): PDO {
    global $DB_PATH;
    $isNew = !file_exists($DB_PATH);

    $pdo = new PDO('sqlite:' . $DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($isNew) {
        initDb($pdo);
    }
    return $pdo;
}

function initDb(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT
        );
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT NOT NULL,
            price INTEGER NOT NULL
        );
    ");

    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount === 0) {
        $pdo->exec("INSERT INTO users (username, password, email) VALUES ('alice', 'password123', 'alice@example.com')");
        $pdo->exec("INSERT INTO users (username, password, email) VALUES ('bob', 'hunter2', 'bob@example.com')");
        $pdo->exec("INSERT INTO users (username, password, email) VALUES ('admin', 'admin', 'admin@example.com')");

        $pdo->exec("INSERT INTO products (name, description, price) VALUES ('Red Apple', 'Fresh and crispy red apples', 100)");
        $pdo->exec("INSERT INTO products (name, description, price) VALUES ('Green Tea', 'Premium sencha green tea', 500)");
        $pdo->exec("INSERT INTO products (name, description, price) VALUES ('Coffee Beans', 'Single-origin medium roast', 800)");
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function layout(string $title, string $body): void {
    echo <<<HTML
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>{$title}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      font-family: system-ui, -apple-system, sans-serif;
      margin: 2rem;
      line-height: 1.6;
      background: #f5f5f5;
    }
    .container {
      max-width: 900px;
      margin: 0 auto;
      background: white;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .warning {
      background: #fff3cd;
      border: 2px solid #ffc107;
      padding: 1rem;
      margin: 1rem 0;
      border-radius: 4px;
    }
    form { margin: 1.5rem 0; }
    input[type=text], input[type=password] {
      width: 320px;
      max-width: 100%;
      padding: .5rem;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    button {
      padding: .5rem 1.5rem;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    button:hover { background: #0056b3; }
    .card {
      border: 1px solid #ddd;
      padding: 1rem;
      margin: .8rem 0;
      border-radius: 4px;
      background: #fafafa;
    }
    .query {
      background: #f8f9fa;
      border-left: 3px solid #dc3545;
      padding: 1rem;
      margin: 1rem 0;
      font-family: monospace;
      white-space: pre-wrap;
    }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .nav { margin-bottom: 2rem; }
    .example {
      background: #e7f3ff;
      padding: 1rem;
      margin: 1rem 0;
      border-radius: 4px;
    }
    .example code {
      background: #fff;
      padding: 2px 6px;
      border-radius: 3px;
    }
  </style>
</head>
<body>
  <div class="container">
    {$body}
  </div>
</body>
</html>
HTML;
}

function pageIndex(): void {
    $body = <<<HTML
<div class="warning">
  <strong>⚠️ 警告:</strong> このサイトは<strong>SQLインジェクションの脆弱性</strong>を含む学習用デモです。<br>
  絶対に本番環境では使用しないでください！
</div>

<h1>SQLインジェクション学習サイト（脆弱版）</h1>

<div class="nav">
  <a href="/index.php">安全版</a> |
  <a href="/vulnerable.php">脆弱版はこちら</a> |
  <a href="/vulnerable-fix.php">脆弱版Labはこちら</a>
</div>

<h2>🔍 商品検索（脆弱）</h2>
<form action="/vulnerable-fix.php?page=search" method="get">
  <input type="hidden" name="page" value="search">
  <label>キーワード: <input type="text" name="q" placeholder="tea"></label>
  <button type="submit">検索</button>
</form>

<div class="example">
  <strong>💡 攻撃例を試してみよう:</strong><br>
  <code>' OR '1'='1</code> - 全商品を表示<br>
  <code>' UNION SELECT id, username, password, email FROM users --</code> - ユーザー情報を取得（4カラム）<br>
  <code>' UNION SELECT id, username, password, 0 FROM users --</code> - ユーザー情報を取得（NULL埋め）
</div>

<hr>

<h2>🔐 ログイン（脆弱）</h2>
<form action="/vulnerable-fix.php?page=login" method="post">
  <div><label>ユーザー名: <input type="text" name="username" value="alice"></label></div>
  <div><label>パスワード: <input type="password" name="password" value="password123"></label></div>
  <button type="submit">ログイン</button>
</form>

<div class="example">
  <strong>💡 攻撃例を試してみよう:</strong><br>
  <strong>ユーザー名:</strong> <code>admin' --</code> （パスワード不要でログイン）<br>
  <strong>ユーザー名:</strong> <code>' OR '1'='1' --</code> （最初のユーザーでログイン）
</div>

<hr>

<h3>📚 学習のポイント</h3>
<ul>
  <li>このサイトは入力値をSQL文に直接連結しています（危険！）</li>
  <li>実行されるSQLクエリが画面に表示されます</li>
  <li>安全版（index.php）と比較して、どう対策するか学びましょう</li>
</ul>
HTML;

    layout('SQLインジェクション学習（脆弱版）', $body);
}

function pageSearch(PDO $pdo): void {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '') {
        layout('検索', '<p>検索キーワードを入力してください。</p><p><a href="/vulnerable-fix.php">← 戻る</a></p>');
        return;
    }

    // ========== 脆弱版（現在有効） ==========
    // ⚠️ VULNERABLE: ユーザー入力を直接SQL文に連結
    $sql = "SELECT id, name, description, price FROM products WHERE name LIKE '%$q%'";
    $queryDisplay = '<div class="query"><strong>実行されたSQL:</strong><br>' . h($sql) . '</div>';
    try {
       $rows = $pdo->query($sql)->fetchAll();
    // ========================================

    // ========== 安全版（コメントアウト中） ==========
    // ✅ 安全な実装に切り替えるには、上の「脆弱版」をコメントアウトし、下の「安全版」のコメントを外す
    // $like = '%' . $q . '%';
    // $stmt = $pdo->prepare("SELECT id, name, description, price FROM products WHERE name LIKE ? OR description LIKE ?");
    // $stmt->execute([$like, $like]);
    // $rows = $stmt->fetchAll();
    // $queryDisplay = '<div class="query"><strong>実行されたコード:</strong><br>' . h('$stmt->execute(["' . $like . '", "' . $like . '"]);') . '</div>';
    // try {
         // 既に $rows は取得済み
    // =============================================

        $count = count($rows);
        $items = '';
        foreach ($rows as $r) {
            $items .= '<div class="card">'
                    . '<div><strong>' . h((string)($r['name'] ?? '')) . '</strong></div>'
                    . '<div>' . h((string)($r['description'] ?? '')) . '</div>'
                    . '<div>価格: ¥' . h((string)($r['price'] ?? '')) . '</div>'
                    . '</div>';
        }
        $items = $items !== '' ? $items : '<p>該当なし</p>';

        $body = '<h1>検索結果（' . $count . '件）</h1>'
              . $queryDisplay
              . $items
              . '<p><a href="/vulnerable-fix.php">← 戻る</a></p>';
        layout('検索結果', $body);
    } catch (Exception $e) {
        $body = '<h1>エラーが発生しました</h1>'
              . $queryDisplay
              . '<div class="warning">' . h($e->getMessage()) . '</div>'
              . '<p><a href="/vulnerable-fix.php">← 戻る</a></p>';
        layout('エラー', $body);
    }
}

function pageLogin(PDO $pdo): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        layout('Method Not Allowed', '<p>POST メソッドを使用してください。</p><p><a href="/vulnerable-fix.php">← 戻る</a></p>');
        return;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '') {
        layout('ログイン', '<p>ユーザー名を入力してください。</p><p><a href="/vulnerable-fix.php">← 戻る</a></p>');
        return;
    }

    // ========== 脆弱版（現在有効） ==========
    // ⚠️ VULNERABLE: ユーザー入力を直接SQL文に連結 + 平文パスワード比較
    $sql = "SELECT id, username, email FROM users WHERE username = '$username' AND password = '$password'";
    $queryDisplay = '<div class="query"><strong>実行されたSQL:</strong><br>' . h($sql) . '</div>';
    try {
        $row = $pdo->query($sql)->fetch();
    // ========================================

    // ========== 安全版（コメントアウト中） ==========
    // ✅ 安全な実装に切り替えるには、上の「脆弱版」をコメントアウトし、下の「安全版」のコメントを外す
    // $stmt = $pdo->prepare("SELECT id, username, email, password FROM users WHERE username = ?");
    // $stmt->execute([$username]);
    // $row = $stmt->fetch();
    // $queryDisplay = '<div class="query"><strong>実行されたコード:</strong><br>' . h('$stmt->execute(["' . $username . '"]);') . '</div>';
    // try {
    //     // 平文パスワードで直接比較（ハッシュ化なし）
    //     if (!$row || (string)$row['password'] !== $password) {
    //        $row = false; // 認証失敗
    //     }
    // =============================================

        if ($row) {
            $body = '<h1>✅ ログイン成功</h1>'
                  . $queryDisplay
                  . '<div class="card">'
                  . '<p><strong>ユーザー名:</strong> ' . h((string)$row['username']) . '</p>'
                  . '<p><strong>Email:</strong> ' . h((string)($row['email'] ?? '')) . '</p>'
                  . '</div>'
                  . '<p><a href="/vulnerable-fix.php">← 戻る</a></p>';
            layout('ログイン成功', $body);
        } else {
            $body = '<h1>❌ ログイン失敗</h1>'
                  . $queryDisplay
                  . '<p>ユーザー名またはパスワードが不正です。</p>'
                  . '<p><a href="/vulnerable-fix.php">← 戻る</a></p>';
            layout('ログイン失敗', $body);
        }
    } catch (Exception $e) {
        $body = '<h1>エラーが発生しました</h1>'
              . $queryDisplay
              . '<div class="warning">' . h($e->getMessage()) . '</div>'
              . '<p><a href="/vulnerable-fix.php">← 戻る</a></p>';
        layout('エラー', $body);
    }
}

// ルーティング
$page = $_GET['page'] ?? 'index';

try {
    $pdo = db();
    switch ($page) {
        case 'index':
            pageIndex();
            break;
        case 'search':
            pageSearch($pdo);
            break;
        case 'login':
            pageLogin($pdo);
            break;
        default:
            pageIndex();
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    layout('エラー', '<div class="warning">' . h($e->getMessage()) . '</div><p><a href="/vulnerable-fix.php">← 戻る</a></p>');
}