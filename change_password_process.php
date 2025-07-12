<?php
session_start();
require_once 'db_connect.php';

// ログインチェック
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// POSTリクエストでなければリダイレクト
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: change_password.php');
    exit();
}

// フォームデータの受け取り
$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];
$username = $_SESSION['user'];

// バリデーション
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['error_message'] = 'すべての項目を入力してください。';
    header('Location: change_password.php');
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['error_message'] = '新しいパスワードと確認用のパスワードが一致しません。';
    header('Location: change_password.php');
    exit();
}

try {
    $pdo = get_pdo_connection();

    // 現在のパスワードが正しいか検証
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['password'])) {
        $_SESSION['error_message'] = '現在のパスワードが正しくありません。';
        header('Location: change_password.php');
        exit();
    }

    // 新しいパスワードをハッシュ化
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // データベースを更新
    $update_stmt = $pdo->prepare("UPDATE users SET password = :password WHERE username = :username");
    $update_stmt->bindValue(':password', $hashed_password, PDO::PARAM_STR);
    $update_stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $update_stmt->execute();

    $_SESSION['success_message'] = 'パスワードが正常に変更されました。';
    header('Location: change_password.php');
    exit();

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'データベースエラーが発生しました。';
    header('Location: change_password.php');
    exit();
}
?>