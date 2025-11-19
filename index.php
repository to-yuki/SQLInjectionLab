<?php
// index.php
// 学習用：PHP + PDO(SQLite) 安全実装版
// - SQLインジェクション対策：パラメータ化クエリを徹底
// - パスワードハッシュ化：password_hash / password_verify 使用
//
// vulnerable.php と比較して、安全な実装方法を学びましょう

declare(strict_types=1);
mb_internal_encoding('UTF-8');

const DEV = true; // 開発中のみ true。公開時は false。
if (DEV) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}
header('Content-Type: text/html; charset=UTF-8');

$DB_DIR  = __DIR__ . '/data';
$DB_PATH = $DB_DIR . '/app_safe.db'; // 安全版は別のDBファイル

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
        PDO::ATTR_EMULATE_PREPARES => false,
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
            password_hash TEXT NOT NULL,
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
        $pdo->beginTransaction();
        try {
            // ✅ 安全: パスワードをハッシュ化して保存
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email) VALUES (?, ?, ?)");
            $stmt->execute(['alice', password_hash('password123', PASSWORD_DEFAULT), 'alice@example.com']);
            $stmt->execute(['bob', password_hash('hunter2', PASSWORD_DEFAULT), 'bob@example.com']);
            $stmt->execute(['admin', password_hash('admin', PASSWORD_DEFAULT), 'admin@example.com']);
            
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price) VALUES (?, ?, ?)");
            $stmt->execute(['Red Apple', 'Fresh and crispy red apples', 100]);
            $stmt->execute(['Green Tea', 'Premium sencha green tea', 500]);
            $stmt->execute(['Coffee Beans', 'Single-origin medium roast', 800]);
            
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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
    .success {
      background: #d4edda;
      border: 2px solid #28a745;
      padding: 1rem;
      margin: 1rem 0;
      border-radius: 4px;
      color: #155724;
    }
    .info {
      background: #d1ecf1;
      border: 2px solid #17a2b8;
      padding: 1rem;
      margin: 1rem 0;
      border-radius: 4px;
      color: #0c5460;
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
      background: #28a745;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    button:hover { background: #218838; }
    .card { 
      border: 1px solid #ddd; 
      padding: 1rem; 
      margin: .8rem 0;
      border-radius: 4px;
      background: #fafafa;
    }
    .code { 
      background: #f8f9fa; 
      border-left: 3px solid #28a745;
      padding: 1rem; 
      margin: 1rem 0;
      font-family: monospace;
      white-space: pre-wrap;
    }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .nav { margin-bottom: 2rem; }
    .comparison { 
      background: #e7f3ff; 
      padding: 1rem; 
      margin: 1rem 0;
      border-radius: 4px;
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
    $devNote = DEV ? '<p class="info">DEVモード: エラー詳細を画面表示します。</p>' : '';
    $body = <<<HTML
<div class="success">
  <strong>✅ 安全版:</strong> このサイトは<strong>SQLインジェクション対策済み</strong>の実装例です。
</div>

<h1>SQLインジェクション学習サイト（安全版）</h1>
{$devNote}

<div class="nav">
  <a href="/index.php">安全版</a> | 
  <a href="/vulnerable.php">脆弱版はこちら</a> | 
  <a href="/vulnerable-fix.php">脆弱版Labはこちら</a>
</div>

<h2>🔍 商品検索（安全）</h2>
<form action="/index.php?page=search" method="get">
  <input type="hidden" name="page" value="search">
  <label>キーワード: <input type="text" name="q" placeholder="tea"></label>
  <button type="submit">検索</button>
</form>

<div class="comparison">
  <strong>💡 試してみよう:</strong><br>
  以下の攻撃文字列を入力しても、安全に処理されます：<br>
  <code>' OR '1'='1</code><br>
  <code>' UNION SELECT id, username, password_hash, email FROM users --</code>
</div>

<div class="code"><strong>✅ 安全な実装コード:</strong>
\$stmt = \$pdo->prepare("SELECT * FROM products WHERE name LIKE ?");
\$stmt->execute(['%' . \$keyword . '%']);
\$rows = \$stmt->fetchAll();
</div>

<hr>

<h2>🔐 ログイン（安全）</h2>
<form action="/index.php?page=login" method="post">
  <div><label>ユーザー名: <input type="text" name="username" value="alice"></label></div>
  <div><label>パスワード: <input type="password" name="password" value="password123"></label></div>
  <button type="submit">ログイン</button>
</form>

<div class="comparison">
  <strong>💡 試してみよう:</strong><br>
  攻撃文字列を入力しても、ログインは失敗します：<br>
  <strong>ユーザー名:</strong> <code>admin' --</code>
</div>

<div class="code"><strong>✅ 安全な実装コード:</strong>
// パスワードハッシュ化
\$hash = password_hash(\$password, PASSWORD_DEFAULT);

// プリペアドステートメント
\$stmt = \$pdo->prepare("SELECT * FROM users WHERE username = ?");
\$stmt->execute([\$username]);
\$user = \$stmt->fetch();

// パスワード検証
if (\$user && password_verify(\$password, \$user['password_hash'])) {
    // ログイン成功
}
</div>

<hr>

<h3>📚 安全な実装のポイント</h3>
<ul>
  <li><strong>プリペアドステートメント</strong>を使用（<code>prepare()</code> + <code>execute()</code>）</li>
  <li>ユーザー入力をSQL文に<strong>直接連結しない</strong></li>
  <li>パスワードは<strong>ハッシュ化</strong>して保存（<code>password_hash()</code>）</li>
  <li>ログイン時は<code>password_verify()</code>で検証</li>
  <li>入力値の<strong>バリデーション</strong>（長さ制限、型チェック）</li>
</ul>
HTML;

    layout('SQLインジェクション学習（安全版）', $body);
}

function pageSearch(PDO $pdo): void {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '') {
        layout('検索', '<p>検索キーワードを入力してください。</p><p><a href="/index.php">← 戻る</a></p>');
        return;
    }

    // 入力値のバリデーション
    if (mb_strlen($q) > 100) {
        layout('検索', '<p>検索キーワードが長すぎます（最大100文字）。</p><p><a href="/index.php">← 戻る</a></p>');
        return;
    }

    // ✅ 安全: プリペアドステートメントを使用
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT id, name, description, price
        FROM products
        WHERE name LIKE ? OR description LIKE ?
        ORDER BY id ASC
    ");
    $stmt->execute([$like, $like]);
    $rows = $stmt->fetchAll();

    $codeDisplay = '<div class="code"><strong>実行されたコード:</strong><br>'
        . h('$stmt = $pdo->prepare("SELECT ... WHERE name LIKE ? OR description LIKE ?");') . '<br>'
        . h('$stmt->execute(["' . $like . '", "' . $like . '"]);')
        . '</div>';

    $count = count($rows);
    $items = '';
    foreach ($rows as $r) {
        $items .= '<div class="card">'
                . '<div><strong>' . h((string)$r['name']) . '</strong></div>'
                . '<div>' . h((string)$r['description']) . '</div>'
                . '<div>価格: ¥' . h((string)$r['price']) . '</div>'
                . '</div>';
    }
    $items = $items !== '' ? $items : '<p>該当なし</p>';

    $body = '<h1>検索結果（' . $count . '件）</h1>' 
          . $codeDisplay 
          . $items 
          . '<p><a href="/index.php">← 戻る</a></p>';
    layout('検索結果', $body);
}

function pageLogin(PDO $pdo): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        layout('Method Not Allowed', '<p>POST メソッドを使用してください。</p><p><a href="/index.php">← 戻る</a></p>');
        return;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        layout('ログイン', '<p>ユーザー名とパスワードを入力してください。</p><p><a href="/index.php">← 戻る</a></p>');
        return;
    }

    // 入力値のバリデーション
    if (mb_strlen($username) > 150 || mb_strlen($password) > 255) {
        layout('ログイン', '<p>入力値が長すぎます。</p><p><a href="/index.php">← 戻る</a></p>');
        return;
    }

    // ✅ 安全: プリペアドステートメントを使用
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    $codeDisplay = '<div class="code"><strong>実行されたコード:</strong><br>'
        . h('$stmt = $pdo->prepare("SELECT ... FROM users WHERE username = ?");') . '<br>'
        . h('$stmt->execute(["' . $username . '"]);') . '<br>'
        . h('password_verify($password, $user["password_hash"]);')
        . '</div>';

    // ✅ 安全: password_verify でパスワード検証
    if ($row && password_verify($password, (string)$row['password_hash'])) {
        $body = '<h1>✅ ログイン成功</h1>'
              . $codeDisplay
              . '<div class="card">'
              . '<p><strong>ユーザー名:</strong> ' . h((string)$row['username']) . '</p>'
              . '<p><strong>Email:</strong> ' . h((string)($row['email'] ?? '')) . '</p>'
              . '</div>'
              . '<p><a href="/index.php">← 戻る</a></p>';
        layout('ログイン成功', $body);
    } else {
        $body = '<h1>❌ ログイン失敗</h1>'
              . $codeDisplay
              . '<p>ユーザー名またはパスワードが不正です。</p>'
              . '<p><a href="/index.php">← 戻る</a></p>';
        layout('ログイン失敗', $body);
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
    if (DEV) {
        $detail = '<pre>' . h($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
        layout('エラー', '<p>内部エラーが発生しました（開発モード）。</p>' . $detail . '<p><a href="/index.php">← 戻る</a></p>');
    } else {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        layout('エラー', '<p>内部エラーが発生しました。</p><p><a href="/index.php">← 戻る</a></p>');
    }
}
