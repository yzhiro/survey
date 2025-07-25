<?php
// --- ロジック ---
require_once __DIR__ . '/init.php';

// POSTリクエストでなければ不正なアクセスとみなす
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 実際には、エラーページにリダイレクトするなどの処理が望ましい
    exit('不正なアクセスです。');
}

// CSRFトークンを検証
if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
    exit('不正なリクエストです。');
}

// データのサニタイズとバリデーション
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$gender = $_POST['gender'] ?? '';
$income = filter_input(INPUT_POST, 'income', FILTER_VALIDATE_INT);
$disability = $_POST['disability'] ?? '';
$answers = $_POST['answers'] ?? [];

$errors = [];
if ($age === false || $age < 0) {
    $errors[] = '年齢が正しくありません。';
}
if (!in_array($gender, ['男性', '女性', 'その他'], true)) {
    $errors[] = '性別が正しくありません。';
}
if ($income === false || $income < 0) {
    $errors[] = '年収が正しくありません。';
}
if (!in_array($disability, ['あり', 'なし'], true)) {
    $errors[] = '障害の有無が正しくありません。';
}
if (!is_array($answers) || count($answers) !== 10) {
    $errors[] = '回答データが不足しています。';
} else {
    foreach ($answers as $key => $value) {
        if (!preg_match('/^q([1-9]|10)$/', $key) || !in_array((int)$value, [1, 2, 3, 4, 5], true)) {
            $errors[] = '回答の内容が不正です。';
            break;
        }
    }
}

// バリデーションエラーがあればフォームに戻す (実際にはエラーメッセージをセッションに入れて戻すのが親切)
if (!empty($errors)) {
    // 本来はエラーメッセージをセッションに格納してindex.phpにリダイレクトするのが望ましい
    // 例: $_SESSION['errors'] = $errors; redirect('index.php');
    exit('入力データに誤りがあります。<br>' . implode('<br>', array_map('h', $errors)));
}

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
    for ($i = 1; $i <= 10; $i++) {
        $q_key = 'q' . $i;
        $stmt->bindValue(':' . $q_key, $answers[$q_key], PDO::PARAM_INT);
    }

    // SQLを実行
    $stmt->execute();

} catch (PDOException $e) {
    // エラーログに記録し、汎用的なエラーメッセージを表示
    error_log('Database Error: ' . $e->getMessage());
    exit('データベースへの書き込み中にエラーが発生しました。しばらくしてから再度お試しください。');
}

// 完了ページにリダイレクト
redirect('thanks.php');
?>