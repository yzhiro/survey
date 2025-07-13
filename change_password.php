<?php
session_start();

// ログインしていなければ、ログインページにリダイレクト
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}
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

            <div class="flex items-center justify-between mt-8">
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