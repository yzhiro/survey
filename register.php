<?php
// --- ロジック ---
require_once __DIR__ . '/init.php';

// ログイン済みの場合は分析ページへリダイレクト
if (isset($_SESSION['user'])) {
    redirect('analysis.php');
}

// CSRFトークンを生成
$csrf_token = generate_csrf_token();

// セッションからエラーメッセージを取得し、表示後に削除
$error_message = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

// --- ビュー ---
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー登録</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-xs">
        <form action="register_process.php" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h1 class="text-2xl font-bold text-center mb-6">ユーザー登録</h1>
            
            <?php if ($error_message): ?>
                <p class="text-red-500 text-xs italic mb-4"><?php echo h($error_message); ?></p>
            <?php endif; ?>

            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">ユーザー名</label>
                <input type="text" name="username" id="username" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">パスワード</label>
                <input type="password" name="password" id="password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    登録する
                </button>
                <a href="login.php" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
                    ログイン画面へ
                </a>
            </div>
        </form>
    </div>
</body>
</html>