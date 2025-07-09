<?php
session_start();

// 管理者としてログインしているかチェック
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// データベース接続とIDの取得
require_once 'db_connect.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// IDが無効ならリダイレクト
if (!$id) {
    header('Location: view_data.php');
    exit();
}

try {
    // データベースに接続
    $pdo = get_pdo_connection();

    // SQL文を準備 (プリペアドステートメント)
    $sql = "DELETE FROM survey_db WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    // SQLを実行
    $stmt->execute();

} catch (PDOException $e) {
    // エラーが発生した場合はメッセージを表示して終了
    // 本番環境では、エラーログに記録するなどの処理が望ましい
    exit('データベース処理中にエラーが発生しました: ' . $e->getMessage());
}

// データ一覧ページにリダイレクト
header('Location: view_data.php');
exit();
?>