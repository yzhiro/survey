<?php
session_start();

// 管理者または中間管理者でなければアクセス不可
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'editor'])) {
    header('Location: analysis.php'); // or login.php
    exit();
}

require_once 'db_connect.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    exit('無効なIDです。');
}

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("SELECT * FROM survey_db WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    exit("データベースの読み込みに失敗しました: " . $e->getMessage());
}

if (!$data) {
    exit("該当するデータが見つかりません。");
}

// 質問項目 (index.phpから流用)
$questions = [
    'q1' => '世界遺産のドキュメンタリー動画を観ることに、どのくらい興味がありますか？', 'q2' => 'VRゴーグルを使って、世界遺産を仮想旅行体験することに、どのくらい興味がありますか？', 'q3' => '歴史的背景や専門家の解説付きの動画コンテンツに、どのくらい魅力を感じますか？', 'q4' => '4K/8Kなどの高画質で撮影された世界遺産の映像に、どのくらい価値を感じますか？', 'q5' => '高品質な世界遺産動画が見放題のサービスがあれば、月々500円程度を支払うことに抵抗はありませんか？', 'q6' => 'VRでのリアルな旅行体験ができるサービスがあれば、月々1,500円程度を支払うことに抵抗はありませんか？', 'q7' => '新しい世界遺産コンテンツが毎週追加されることに、どのくらい魅力を感じますか？', 'q8' => 'スマートフォンやタブレットで、手軽に世界遺産コンテンツを楽しめることは、どのくらい重要ですか？', 'q9' => '子ども向けの教育コンテンツとして、世界遺産動画やVRを利用することに、どのくらい関心がありますか？', 'q10' => '旅行先の候補として、動画やVRで観た世界遺産を選ぶ可能性はどのくらいありますか？',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データ編集 (ID: <?php echo htmlspecialchars($data['id']); ?>)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-4 md:p-8">
        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-10">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">データ編集 (ID: <?php echo htmlspecialchars($data['id']); ?>)</h1>
            <form action="edit_data.php" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($data['id']); ?>">
                <div class="mb-12">
                    <h2 class="text-2xl font-semibold mb-6 border-l-4 border-blue-500 pl-4">回答者情報</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="age" class="block text-sm font-medium text-gray-700 mb-1">年齢</label>
                            <input type="number" name="age" id="age" required class="w-full p-3 border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($data['age']); ?>">
                        </div>
                        <div>
                            <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">性別</label>
                            <select name="gender" id="gender" required class="w-full p-3 border border-gray-300 rounded-lg">
                                <option value="男性" <?php if ($data['gender'] == '男性') echo 'selected'; ?>>男性</option>
                                <option value="女性" <?php if ($data['gender'] == '女性') echo 'selected'; ?>>女性</option>
                                <option value="その他" <?php if ($data['gender'] == 'その他') echo 'selected'; ?>>その他</option>
                            </select>
                        </div>
                        <div>
                            <label for="income" class="block text-sm font-medium text-gray-700 mb-1">世帯年収（万円）</label>
                            <input type="number" name="income" id="income" required class="w-full p-3 border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($data['income']); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">障害の有無</label>
                            <div class="flex items-center space-x-6 mt-2 p-3">
                                <label><input type="radio" name="disability" value="あり" required class="mr-2" <?php if ($data['disability'] == 'あり') echo 'checked'; ?>>あり</label>
                                <label><input type="radio" name="disability" value="なし" required class="mr-2" <?php if ($data['disability'] == 'なし') echo 'checked'; ?>>なし</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-2xl font-semibold mb-6 border-l-4 border-blue-500 pl-4">アンケート回答</h2>
                    <div class="space-y-8">
                        <?php foreach ($questions as $key => $text): ?>
                        <div class="bg-gray-50 p-5 rounded-lg border">
                            <p class="font-semibold mb-4"><?php echo "Q" . substr($key, 1) . ". " . $text; ?></p>
                            <div class="flex items-center justify-center space-x-4">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="radio" name="answers[<?php echo $key; ?>]" value="<?php echo $i; ?>" required class="h-5 w-5" <?php if ($data[$key] == $i) echo 'checked'; ?>>
                                    <span><?php echo $i; ?></span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-10 flex justify-end gap-4">
                    <a href="view_data.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-lg">キャンセル</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">更新する</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>