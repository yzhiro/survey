<?php
session_start();

// 管理者(admin)または中間管理者(editor)としてログインしているかチェック
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'editor'])) {
    $_SESSION['error'] = 'このページにアクセスする権限がありません。';
    header('Location: analysis.php'); // 権限がない場合は分析ページへ戻す
    exit();
}

// データベース接続とデータ取得
require_once 'db_connect.php';
$all_data = [];
try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->query("SELECT * FROM survey_db ORDER BY id DESC"); // 新しい順に表示
    $all_data = $stmt->fetchAll();
} catch (PDOException $e) {
    // エラーの場合はメッセージを表示
    $error_message = "データベースの読み込みに失敗しました: " . $e->getMessage();
}

// 質問項目とカラム名の対応
$questions_map = [
    'id' => 'ID', 'age' => '年齢', 'gender' => '性別', 'income' => '年収(万)', 'disability' => '障害有無', 'q1' => 'Q1', 'q2' => 'Q2', 'q3' => 'Q3', 'q4' => 'Q4', 'q5' => 'Q5', 'q6' => 'Q6', 'q7' => 'Q7', 'q8' => 'Q8', 'q9' => 'Q9', 'q10' => 'Q10', 'submitted_at' => '回答日時'
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース内容表示</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-4 md:p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">データベース内容表示</h1>
            <div>
                <a href="analysis.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm">分析レポートに戻る</a>
                <a href="logout.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg text-sm ml-2">ログアウト</a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">エラー:</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php elseif (empty($all_data)): ?>
            <p class="text-center text-gray-500">データはまだありません。</p>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 text-xs text-gray-700 uppercase">
                        <tr>
                            <?php foreach (array_values($questions_map) as $label): ?>
                                <th class="px-4 py-3 whitespace-nowrap"><?php echo $label; ?></th>
                            <?php endforeach; ?>
                            <th class="px-4 py-3 whitespace-nowrap">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_data as $row): ?>
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <?php foreach (array_keys($questions_map) as $key): ?>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php echo htmlspecialchars($row[$key]); ?>
                            </td>
                            <?php endforeach; ?>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="edit_form.php?id=<?php echo $row['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded text-xs">編集</a>
                                <a href="delete_data.php?id=<?php echo $row['id']; ?>" onclick="return confirm('本当にこのデータを削除しますか？(ID: <?php echo $row['id']; ?>)');" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-xs ml-1">削除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>