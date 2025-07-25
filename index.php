<?php
// --- ロジック ---
require_once __DIR__ . '/init.php';

// CSRFトークンを生成
$csrf_token = generate_csrf_token();

// 質問項目データ
$questions = [
    'q1' => '世界遺産のドキュメンタリー動画を観ることに、どのくらい興味がありますか？', 'q2' => 'VRゴーグルを使って、世界遺産を仮想旅行体験することに、どのくらい興味がありますか？', 'q3' => '歴史的背景や専門家の解説付きの動画コンテンツに、どのくらい魅力を感じますか？', 'q4' => '4K/8Kなどの高画質で撮影された世界遺産の映像に、どのくらい価値を感じますか？', 'q5' => '高品質な世界遺産動画が見放題のサービスがあれば、月々500円程度を支払うことに抵抗はありませんか？', 'q6' => 'VRでのリアルな旅行体験ができるサービスがあれば、月々1,500円程度を支払うことに抵抗はありませんか？', 'q7' => '新しい世界遺産コンテンツが毎週追加されることに、どのくらい魅力を感じますか？', 'q8' => 'スマートフォンやタブレットで、手軽に世界遺産コンテンツを楽しめることは、どのくらい重要ですか？', 'q9' => '子ども向けの教育コンテンツとして、世界遺産動画やVRを利用することに、どのくらい関心がありますか？', 'q10' => '旅行先の候補として、動画やVRで観た世界遺産を選ぶ可能性はどのくらいありますか？',
];
$labels = [
    'q1' => ['全く興味がない', '非常に興味がある'], 'q2' => ['全く興味がない', '非常に興味がある'], 'q3' => ['全く魅力がない', '非常に魅力的'], 'q4' => ['全く価値を感じない', '非常に価値を感じる'], 'q5' => ['非常に抵抗がある', '全く抵抗がない'], 'q6' => ['非常に抵抗がある', '全く抵抗がない'], 'q7' => ['全く魅力がない', '非常に魅力的'], 'q8' => ['全く重要でない', '非常に重要'], 'q9' => ['全く関心がない', '非常に関心がある'], 'q10' => ['全くない', '非常にある']
];

// --- ビュー ---
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>世界遺産コンテンツに関するアンケート</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-800">

    <header class="bg-white shadow-sm">
        <div class="container mx-auto p-4 flex justify-end items-center">
            <a href="login.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg text-sm">
                管理者用
            </a>
        </div>
    </header>

    <div class="container mx-auto p-4 md:p-8">
        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-10">

            <div class="text-center mb-10">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">世界遺産コンテンツに関するアンケート</h1>
                <p class="text-gray-600 mt-2">新しい映像体験へのご意見をお聞かせください。</p>
            </div>

            <form action="submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

                <div class="mb-12">
                    <h2 class="text-2xl font-semibold mb-6 border-l-4 border-blue-500 pl-4">あなたについて教えてください</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="age" class="block text-sm font-medium text-gray-700 mb-1">年齢</label>
                            <input type="number" name="age" id="age" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="例: 35">
                        </div>
                        <div>
                            <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">性別</label>
                            <select name="gender" id="gender" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="男性">男性</option>
                                <option value="女性">女性</option>
                                <option value="その他">その他</option>
                            </select>
                        </div>
                        <div>
                            <label for="income" class="block text-sm font-medium text-gray-700 mb-1">世帯年収（万円）</label>
                            <input type="number" name="income" id="income" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="例: 600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">障害の有無</label>
                            <div class="flex items-center space-x-6 mt-2 p-3">
                                <label class="flex items-center cursor-pointer"><input type="radio" name="disability" value="あり" required class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500 mr-2"> あり</label>
                                <label class="flex items-center cursor-pointer"><input type="radio" name="disability" value="なし" required class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500 mr-2"> なし</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-2xl font-semibold mb-6 border-l-4 border-blue-500 pl-4">サービスへの興味について</h2>
                    <div class="space-y-8">
                        <?php foreach ($questions as $key => $text) : ?>
                            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                                <p class="font-semibold text-gray-800 mb-4"><?php echo "Q" . substr($key, 1) . ". " . h($text); ?></p>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600 text-center w-1/5"><?php echo h($labels[$key][0]); ?></span>
                                    <div class="flex-grow grid grid-cols-5 gap-2 mx-4">
                                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                                            <label class="flex flex-col items-center justify-center p-2 rounded-lg border-2 border-gray-200 cursor-pointer hover:bg-blue-100 hover:border-blue-400 has-[:checked]:bg-blue-100 has-[:checked]:border-blue-500 transition-colors">
                                                <input type="radio" name="answers[<?php echo h($key); ?>]" value="<?php echo $i; ?>" required class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500 mb-1">
                                                <span class="text-base font-bold"><?php echo $i; ?></span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-sm text-gray-600 text-center w-1/5"><?php echo h($labels[$key][1]); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-10 text-center">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg text-lg transition-transform transform hover:scale-105 shadow-lg">
                        送　信
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>