<?php
session_start();

// 管理者でなければアクセス不可
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'db_connect.php';
$users = [];
try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "データベースの読み込みに失敗しました: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー管理</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-4 md:p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">ユーザー管理</h1>
            <div>
                 <a href="analysis.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm">分析レポート</a>
                 <a href="logout.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg text-sm ml-2">ログアウト</a>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 text-xs text-gray-700 uppercase">
                    <tr>
                        <th class="px-4 py-3">ユーザー名</th>
                        <th class="px-4 py-3">権限</th>
                        <th class="px-4 py-3">登録日時</th>
                        <th class="px-4 py-3">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr class="bg-white border-b hover:bg-gray-50">
                        <td class="px-4 py-3"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="px-4 py-3">
                            <form action="update_user_role.php" method="POST" class="inline-flex items-center">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="role" class="border rounded p-1">
                                    <option value="viewer" <?php if($user['role'] == 'viewer') echo 'selected'; ?>>一般</option>
                                    <option value="editor" <?php if($user['role'] == 'editor') echo 'selected'; ?>>中間管理者</option>
                                    <option value="admin" <?php if($user['role'] == 'admin') echo 'selected'; ?>>管理者</option>
                                </select>
                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-2 rounded text-xs ml-2">更新</button>
                            </form>
                        </td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($user['created_at']); ?></td>
                        <td class="px-4 py-3">
                            <?php if ($_SESSION['user'] !== $user['username']): // 自分自身は削除できない ?>
                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" onclick="return confirm('本当にこのユーザーを削除しますか？');" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-xs ml-1">削除</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>