<?php
require_once __DIR__ . '/init.php';

// ログイン必須
require_login();

// --- 変数の初期化 ---
$type = $_GET['type'] ?? '';
$filename = 'download.csv';
$data = [];
$header = [];

try {
    // --- 権限チェックとデータ取得 ---
    if ($type === 'survey' && in_array($_SESSION['role'], ['admin', 'editor'])) {
        // 【アンケート結果】管理者 or 編集者権限
        $filename = 'survey_data_' . date("Ymd") . '.csv';
        $pdo = get_pdo_connection();
        $stmt = $pdo->query("SELECT * FROM survey_db ORDER BY id ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($data)) {
            $header = array_keys($data[0]);
        }

    } elseif ($type === 'users' && $_SESSION['role'] === 'admin') {
        // 【ユーザーリスト】管理者権限のみ
        $filename = 'users_list_' . date("Ymd") . '.csv';
        $pdo = get_pdo_connection();
        // パスワードハッシュは除外する
        $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($data)) {
            $header = array_keys($data[0]);
        }
    } else {
        // 権限がない、または不正なタイプの場合はリダイレクト
        $_SESSION['error'] = 'このデータにアクセスする権限がありません。';
        redirect('analysis.php');
    }

    // --- CSV出力処理 ---
    
    // HTTPヘッダーを設定
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // BOMを付与してExcelでの文字化けを防ぐ
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // ヘッダー行を書き込み
    if (!empty($header)) {
        fputcsv($output, $header);
    }

    // データ行を書き込み
    if (!empty($data)) {
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    error_log("Download Error: " . $e->getMessage());
    $_SESSION['error'] = 'データのダウンロード中にエラーが発生しました。';
    redirect('analysis.php');
}