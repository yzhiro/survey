<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// db_connect.phpを読み込む
require_once 'db_connect.php';

// POSTリクエストでなければ何もせず終了
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// データのサニタイズ
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$gender = htmlspecialchars($_POST['gender'], ENT_QUOTES, 'UTF-8');
$income = filter_input(INPUT_POST, 'income', FILTER_VALIDATE_INT);
$disability = htmlspecialchars($_POST['disability'], ENT_QUOTES, 'UTF-8');
$answers = $_POST['answers'];

// バリデーション
if ($age === false || $income === false || empty($gender) || empty($disability) || !is_array($answers)) {
    // 実際には、より詳細なエラーページにリダイレクトすることが望ましい
    exit('入力データに誤りがあります。');
}

// データベースに保存するデータを用意
$submission_data = [
    'age' => $age,
    'gender' => $gender,
    'income' => $income,
    'disability' => $disability,
    'answers' => $answers
];

try {
    // データベースに接続
    $pdo = get_pdo_connection();

    // SQL文を準備 (プリペアドステートメント)
    $sql = "INSERT INTO survey_db (age, gender, income, disability, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10) 
            VALUES (:age, :gender, :income, :disability, :q1, :q2, :q3, :q4, :q5, :q6, :q7, :q8, :q9, :q10)";

    $stmt = $pdo->prepare($sql);

    // パラメータをバインド
    $stmt->bindValue(':age', $age, PDO::PARAM_INT);
    $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
    $stmt->bindValue(':income', $income, PDO::PARAM_INT);
    $stmt->bindValue(':disability', $disability, PDO::PARAM_STR);
    // 各質問の回答をバインド
    foreach ($answers as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
    }

    // SQLを実行
    $stmt->execute();

} catch (PDOException $e) {
    // エラーが発生した場合はメッセージを表示して終了
    // 本番環境では、エラーログに記録するなどの処理が望ましい
    exit('データベースへの書き込みに失敗しました: ' . $e->getMessage());
}

// セッションにメッセージと今回の回答を保存
$_SESSION['message'] = 'ご回答ありがとうございました！あなたの回答と全体の傾向を比較します。';
$_SESSION['my_score'] = $submission_data;

// analysis.phpにリダイレクト
header('Location: analysis.php');
exit();
?>