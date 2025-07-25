<?php
require_once __DIR__ . '/init.php';

// ログイン必須 (init.php経由で読み込まれたfunctions.phpの関数を利用)
require_login();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワード変更</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md">
        <form action="change_password_process.php" method="POST" class="bg-white shadow-lg rounded-xl px-8 pt-6 pb-8 mb-4">
            <h1 class="text-2xl font-bold text-center mb-6 text-gray-800">パスワード変更</h1>

            <?php
            // エラーメッセージや成功メッセージの表示
            if (isset($_SESSION['success_message'])) {
                echo '<p class="text-green-500 text-center text-sm font-bold mb-4">' . htmlspecialchars($_SESSION['success_message']) . '</p>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<p class="text-red-500 text-center text-sm font-bold mb-4">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
                unset($_SESSION['error_message']);
            }
            ?>

            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">現在のパスワード</label>
                <input type="password" name="current_password" id="current_password" required class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-6">
                <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">新しいパスワード</label>
                <input type="password" name="new_password" id="new_password" required class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">新しいパスワード（確認用）</label>
                <input type="password" name="confirm_password" id="confirm_password" required class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex items-center justify-between">
                <a href="analysis.php" class="inline-block align-baseline font-bold text-sm text-gray-600 hover:text-gray-800">
                    &larr; 分析レポートに戻る
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                    変更する
                </button>
            </div>
        </form>
    </div>
</body>
</html>