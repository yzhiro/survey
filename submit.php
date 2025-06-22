<?php
// 文字化け対策
header('Content-Type: text/html; charset=UTF-8');

// POSTリクエストでなければ何もせず終了
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// データのサニタイズ（簡易版）
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$gender = htmlspecialchars($_POST['gender'], ENT_QUOTES, 'UTF-8');
$income = filter_input(INPUT_POST, 'income', FILTER_VALIDATE_INT);
$disability = htmlspecialchars($_POST['disability'], ENT_QUOTES, 'UTF-8');
$answers = $_POST['answers'];

// バリデーション（簡易版）
if ($age === false || $income === false || empty($gender) || empty($disability) || !is_array($answers)) {
    exit('入力データに誤りがあります。');
}

// 回答データを一つの配列にまとめる
$submission_data = [
    'timestamp' => date('c'),
    'age' => $age,
    'gender' => $gender,
    'income' => $income,
    'disability' => $disability,
    'answers' => $answers
];

$json_data = json_encode($submission_data, JSON_UNESCAPED_UNICODE);
$file_name = 'data.txt';
file_put_contents($file_name, $json_data . PHP_EOL, FILE_APPEND | LOCK_EX);

// --- ★★★★★ 変更点 ★★★★★ ---
session_start();
$_SESSION['message'] = 'ご回答ありがとうございました！あなたの回答と全体の傾向を比較します。';
// 回答データをセッションに保存して、analysis.phpで使えるようにする
$_SESSION['my_score'] = $submission_data;
// --- ★★★★★ 変更ここまで ★★★★★ ---

// analysis.phpにリダイレクト
header('Location: analysis.php');
exit();
?>