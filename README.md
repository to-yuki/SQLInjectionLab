# SQLInjectionLab
SQL InjectionDemo Lab

# SQLインジェクション学習サイト

⚠️ **警告**: このプロジェクトは教育目的のみです。脆弱なコードを含んでいますので、絶対に本番環境では使用しないでください。

## 概要

SQLインジェクションの脆弱性を理解し、安全な実装方法を学ぶための学習用Webサイトです。

- **脆弱版**: `vulnerable.php` - SQLインジェクションが可能
- **安全版**: `index.php` - パラメータ化クエリで保護

## セットアップ

### 必要なもの
- PHP 7.4以上（SQLite拡張が有効）

### 起動方法

```bash
# プロジェクトディレクトリに移動
cd SQLInjectionLab

# PHPビルトインサーバーを起動
php -S localhost:8000
```

### アクセス

- **脆弱版**: http://localhost:8000/vulnerable.php
- **安全版**: http://localhost:8000/index.php

## データベース

SQLiteを使用しており、初回アクセス時に自動的に以下が作成されます：

- データベースファイル: `./data/app.db`
- テーブル: `users`, `products`

### 初期データ

**ユーザー (users)**
| username | password | email |
|----------|----------|-------|
| alice | password123 | alice@example.com |
| bob | hunter2 | bob@example.com |
| admin | admin | admin@example.com |

**商品 (products)**
- Red Apple - ¥100
- Green Tea - ¥500
- Coffee Beans - ¥800

## 攻撃例（脆弱版のみ）

### 🔍 商品検索での攻撃

#### 1. 全商品を表示
```
' OR '1'='1
```
**実行されるSQL:**
```sql
SELECT * FROM products WHERE name LIKE '%' OR '1'='1%'
```

#### 2. UNION攻撃でユーザー情報を取得
```
' UNION SELECT id, username, password FROM users --
```
**実行されるSQL:**
```sql
SELECT id, name, description, price FROM products 
WHERE name LIKE '%' UNION SELECT id, username, password FROM users --%'
```

### 🔐 ログインでの攻撃

#### 1. パスワードなしでログイン（SQLコメント）
**ユーザー名:**
```
admin' --
```
**パスワード:** （何でもOK）

**実行されるSQL:**
```sql
SELECT id, username, email FROM users 
WHERE username = 'admin' --' AND password = 'xxx'
```
`--` 以降がコメントアウトされ、パスワードチェックがスキップされます。

#### 2. 常に真となる条件でログイン
**ユーザー名:**
```
' OR '1'='1' --
```
**パスワード:** （何でもOK）

**実行されるSQL:**
```sql
SELECT id, username, email FROM users 
WHERE username = '' OR '1'='1' --' AND password = 'xxx'
```
常に真となる条件により、最初のユーザーでログインできます。

#### 3. 複数の結果を返す攻撃
**ユーザー名:**
```
' OR 1=1 UNION SELECT id, username, email FROM users --
```

## 安全な実装との比較

### 脆弱なコード (vulnerable.php)
```php
// ❌ 危険: 直接文字列連結
$sql = "SELECT * FROM users WHERE username = '$username'";
$result = $pdo->query($sql);
```

### 安全なコード (index.php)
```php
// ✅ 安全: パラメータ化クエリ（プリペアドステートメント）
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$result = $stmt->fetchAll();
```

## 学習ポイント

### なぜSQLインジェクションは危険か？

1. **データ漏洩**: 他のユーザーの個人情報を盗める
2. **認証回避**: パスワードなしでログインできる
3. **データ改ざん**: UPDATE/DELETE文を実行できる
4. **データ破壊**: DROP TABLEでテーブルを削除できる

### 対策方法

1. **パラメータ化クエリ（プリペアドステートメント）を使用**
   - `?` プレースホルダーを使用
   - `execute()` で値をバインド

2. **入力値の検証**
   - 型チェック
   - 長さ制限
   - ホワイトリスト検証

3. **エスケープ処理**
   - 最終手段として使用
   - パラメータ化クエリが推奨

4. **最小権限の原則**
   - データベースユーザーに必要最小限の権限のみ付与

5. **エラーメッセージの適切な処理**
   - 詳細なエラー情報を公開しない

## 実験してみよう

1. まず脆弱版で上記の攻撃を試してみる
2. 実行されるSQLクエリを確認する
3. 安全版で同じ入力を試し、防御されることを確認する
4. コードを比較して対策方法を理解する

## リセット方法

データベースをリセットしたい場合：

```bash
rm -f data/app.db
```

再度アクセスすると、初期データで再作成されます。

## 注意事項

- このコードは学習目的専用です
- 他人のシステムに対して攻撃を行わないでください
- 本番環境では必ず安全な実装を使用してください
