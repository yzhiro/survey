<?php
require_once __DIR__ . '/init.php';

// 権限チェック (管理者のみ)
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_users.php');
    exit();
}

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$role = $_POST['role'];

// 有効な権限かチェック
if (!$user_id || !in_array($role, ['viewer', 'editor', 'admin'])) {
    header('Location: manage_users.php');
    exit();
}

try {
    $pdo = get_pdo_connection();
    $sql = "UPDATE users SET role = :role WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':role', $role, PDO::PARAM_STR);
    $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
} catch (PDOException $e) {
    // エラー処理
    exit('データベースの更新に失敗しました: ' . $e->getMessage());
}

header('Location: manage_users.php');
exit();
?>