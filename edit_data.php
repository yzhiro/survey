<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// 管理者ログインチェック
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once 'db_connect.php';

// POSTリクエストでなければ何もせず終了
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// データのサニタイズとバリデーション
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$gender = htmlspecialchars($_POST['gender'], ENT_QUOTES, 'UTF-8');
$income = filter_input(INPUT_POST, 'income', FILTER_VALIDATE_INT);
$disability = htmlspecialchars($_POST['disability'], ENT_QUOTES, 'UTF-8');
$answers = $_POST['answers'];

if ($id === false || $age === false || $income === false || empty($gender) || empty($disability) || !is_array($answers)) {
    exit('入力データに誤りがあります。');
}

try {
    $pdo = get_pdo_connection();

    // SQL文を準備 (プリペアドステートメント)
    $sql = "UPDATE survey_db SET 
                age = :age, 
                gender = :gender, 
                income = :income, 
                disability = :disability, 
                q1 = :q1, q2 = :q2, q3 = :q3, q4 = :q4, q5 = :q5, 
                q6 = :q6, q7 = :q7, q8 = :q8, q9 = :q9, q10 = :q10
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    // パラメータをバインド
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':age', $age, PDO::PARAM_INT);
    $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
    $stmt->bindValue(':income', $income, PDO::PARAM_INT);
    $stmt->bindValue(':disability', $disability, PDO::PARAM_STR);
    foreach ($answers as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
    }

    // SQLを実行
    $stmt->execute();

} catch (PDOException $e) {
    exit('データベースの更新に失敗しました: ' . $e->getMessage());
}

// データ一覧ページにリダイレクト
header('Location: view_data.php');
exit();
?>