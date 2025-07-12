<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

$username = $_POST['username'];
$password = $_POST['password'];

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'ユーザー名とパスワードを入力してください。';
    header('Location: register.php');
    exit();
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
        header('Location: register.php');
        exit();
    }

    // ユーザーを登録 (デフォルトロールは 'viewer')
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'viewer')");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->bindValue(':password', $hashed_password, PDO::PARAM_STR);
    $stmt->execute();

    // 登録後、そのままログインさせる
    $_SESSION['user'] = $username;
    $_SESSION['role'] = 'viewer';
    header('Location: analysis.php');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = 'データベースエラーが発生しました。';
    header('Location: register.php');
    exit();
}
?>