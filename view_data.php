<?php
// --- ロジック ---
require_once __DIR__ . '/init.php';

// 権限チェック (管理者 or 編集者)
require_role(['admin', 'editor']);

$all_data = [];
$error_message = null;

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->query("SELECT * FROM survey_db ORDER BY id DESC");
    $all_data = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('View Data Error: ' . $e->getMessage());
    $error_message = "データベースの読み込み中にエラーが発生しました。";
}

// 質問項目とカラム名の対応
$questions_map = [
    'age' => '年齢', 'gender' => '性別', 'income' => '年収(万)', 'disability' => '障害有無',
    'q1' => 'Q1', 'q2' => 'Q2', 'q3' => 'Q3', 'q4' => 'Q4', 'q5' => 'Q5',
    'q6' => 'Q6', 'q7' => 'Q7', 'q8' => 'Q8', 'q9' => 'Q9', 'q10' => 'Q10',
    'submitted_at' => '回答日時'
];

// CSRFトークンを生成 (削除処理用)
$csrf_token = generate_csrf_token();

// --- ビュー ---
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

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">エラー:</strong>
                <span class="block sm:inline"><?php echo h($error_message); ?></span>
            </div>
        <?php elseif (empty($all_data)): ?>
            <p class="text-center text-gray-500">データはまだありません。</p>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 text-xs text-gray-700 uppercase">
                        <tr>
                            <?php foreach (array_values($questions_map) as $label): ?>
                                <th class="px-4 py-3 whitespace-nowrap"><?php echo h($label); ?></th>
                            <?php endforeach; ?>
                            <th class="px-4 py-3 whitespace-nowrap">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_data as $row): ?>
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <?php foreach (array_keys($questions_map) as $key): ?>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php echo h($row[$key]); ?>
                            </td>
                            <?php endforeach; ?>
                            <td class="px-4 py-3 whitespace-nowrap flex items-center">
                                <a href="edit_form.php?id=<?php echo h($row['id']); ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded text-xs">編集</a>
                                
                                <form action="delete_data.php" method="POST" class="inline-block ml-1">
                                    <input type="hidden" name="id" value="<?php echo h($row['id']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <button type="submit" onclick="return confirm('本当にこのデータを削除しますか？(ID: <?php echo h($row['id']); ?>)');" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-xs">削除</button>
                                </form>
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