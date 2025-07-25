<?php
// --- ロジック ---
require_once __DIR__ . '/init.php';

// POSTリクエストかチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = '不正なアクセスです。';
    redirect('view_data.php');
}

// 権限チェック (管理者 or 編集者)
require_role(['admin', 'editor'], 'view_data.php');

// CSRFトークンを検証
if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = '不正なリクエストです。';
    redirect('view_data.php');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

// IDが無効ならリダイレクト
if (!$id) {
    $_SESSION['error'] = '無効なIDです。';
    redirect('view_data.php');
}

try {
    $pdo = get_pdo_connection();
    $sql = "DELETE FROM survey_db WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
} catch (PDOException $e) {
    error_log('Delete Data Error: ' . $e->getMessage());
    $_SESSION['error'] = 'データベース処理中にエラーが発生しました。';
    redirect('view_data.php');
}

// データ一覧ページにリダイレクト
$_SESSION['success'] = 'データを削除しました。';
redirect('view_data.php');
?>