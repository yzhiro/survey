<?php
// --- ロジック ---
require_once __DIR__ . '/init.php';

// POSTリクエストでなければ登録ページにリダイレクト
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

// CSRFトークンを検証
if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = '不正なリクエストです。';
    redirect('register.php');
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// バリデーション
if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'ユーザー名とパスワードを入力してください。';
    redirect('register.php');
}

// パスワードをハッシュ化
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = get_pdo_connection();

    // ユーザー名が既に存在するかチェック
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'このユーザー名は既に使用されています。';
        redirect('register.php');
    }

    // ユーザーを登録 (デフォルトロールは 'viewer')
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'viewer')");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->bindValue(':password', $hashed_password, PDO::PARAM_STR);
    $stmt->execute();

    // 登録後、そのままログインさせる
    // セッションIDを再生成してセキュリティを向上させる（セッション固定化攻撃対策）
    session_regenerate_id(true);
    $_SESSION['user'] = $username;
    $_SESSION['role'] = 'viewer';
    
    // 分析ページへリダイレクト
    redirect('analysis.php');

} catch (PDOException $e) {
    // 開発者向けにエラーをログに記録
    error_log('Registration Error: ' . $e->getMessage());
    // ユーザーには汎用的なエラーメッセージを表示
    $_SESSION['error'] = 'データベースエラーが発生しました。しばらくしてから再度お試しください。';
    redirect('register.php');
}
?>