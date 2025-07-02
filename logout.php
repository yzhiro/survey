<?php
session_start();

// セッション変数をすべて解除する
$_SESSION = array();

// セッションクッキーを削除する
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破棄する
session_destroy();

// ログインページにリダイレクト
header('Location: login.php');
exit();