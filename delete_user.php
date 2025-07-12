<?php
session_start();

// 管理者でなければアクセス不可
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'db_connect.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// IDが無効ならリダイレクト
if (!$id) {
    header('Location: manage_users.php');
    exit();
}

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
} catch (PDOException $e) {
    exit('データベース処理中にエラーが発生しました: ' . $e->getMessage());
}

header('Location: manage_users.php');
exit();
?>