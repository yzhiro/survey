<?php
// =================================================================
//  1. 初期化・セキュリティチェック
// =================================================================
require_once __DIR__ . '/init.php';

// ログイン必須
require_login();

// --- 変数の初期化 ---
$error_message = null;
$total_count = 0;
$demographics = ['gender' => [], 'age' => [], 'income' => []];
$radar_avg_scores = [];
$survey_data = [];
$anova_result = null;
$tukey_result = null;
$anova2_result = null;
$anova2_error_message = null;
$chart_labels = [];
$chart_data = [];
$interaction_plot_data = [];

// =================================================================
//  2. データ取得・統計処理関数
// =================================================================

/**
 * 総回答者数を取得する
 * @return int|false
 */
function get_total_count()
{
    try {
        $pdo = get_pdo_connection();
        return (int)$pdo->query("SELECT COUNT(*) FROM survey_db")->fetchColumn();
    } catch (PDOException $e) {
        error_log("DB Error (get_total_count): " . $e->getMessage());
        return false;
    }
}

/**
 * 回答者の属性分布データを取得する
 * @return array|false
 */
function get_demographics_data()
{
    $data = ['gender' => [], 'age' => [], 'income' => []];
    try {
        $pdo = get_pdo_connection();
        // 性別
        $stmt_gender = $pdo->query("SELECT gender, COUNT(*) as count FROM survey_db GROUP BY gender");
        while ($row = $stmt_gender->fetch()) {
            $data['gender'][$row['gender']] = $row['count'];
        }
        // 年代
        $stmt_age = $pdo->query("SELECT CASE WHEN age < 30 THEN '20代以下' WHEN age BETWEEN 30 AND 39 THEN '30代' WHEN age BETWEEN 40 AND 49 THEN '40代' WHEN age BETWEEN 50 AND 59 THEN '50代' ELSE '60代以上' END as age_group, COUNT(*) as count FROM survey_db GROUP BY age_group ORDER BY age_group");
        while ($row = $stmt_age->fetch()) {
            $data['age'][$row['age_group']] = $row['count'];
        }
        // 年収層
        $stmt_income = $pdo->query("SELECT CASE WHEN income < 400 THEN '400万円未満' WHEN income BETWEEN 400 AND 799 THEN '400～800万円' ELSE '800万円以上' END as income_group, COUNT(*) as count FROM survey_db GROUP BY income_group ORDER BY income_group");
        while ($row = $stmt_income->fetch()) {
            $data['income'][$row['income_group']] = $row['count'];
        }
    } catch (PDOException $e) {
        error_log("DB Error (get_demographics_data): " . $e->getMessage());
        return false;
    }
    return $data;
}

/**
 * 全質問の全体の平均スコアを取得する
 * @return array|false
 */
function get_radar_avg_scores()
{
    try {
        $pdo = get_pdo_connection();
        $query = "SELECT AVG(q1), AVG(q2), AVG(q3), AVG(q4), AVG(q5), AVG(q6), AVG(q7), AVG(q8), AVG(q9), AVG(q10) FROM survey_db";
        return $pdo->query($query)->fetch(PDO::FETCH_NUM);
    } catch (PDOException $e) {
        error_log("DB Error (get_radar_avg_scores): " . $e->getMessage());
        return false;
    }
}

/**
 * 詳細な分析のために全データを取得する
 * @return array|false
 */
function get_all_survey_data()
{
    try {
        $pdo = get_pdo_connection();
        $sql = "
            SELECT
                age, income, gender, disability,
                q1, q2, q3, q4, q5, q6, q7, q8, q9, q10,
                CASE WHEN age < 30 THEN '20代以下' WHEN age BETWEEN 30 AND 39 THEN '30代' WHEN age BETWEEN 40 AND 49 THEN '40代' WHEN age BETWEEN 50 AND 59 THEN '50代' ELSE '60代以上' END as age_group,
                CASE WHEN income < 400 THEN '400万円未満' WHEN income BETWEEN 400 AND 799 THEN '400～800万円' ELSE '800万円以上' END as income_group
            FROM survey_db
        ";
        $results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // ネスト構造に変換
        return array_map(function ($row) {
            $answers = [];
            for ($i = 1; $i <= 10; $i++) {
                $q_key = 'q' . $i;
                $answers[$q_key] = $row[$q_key];
            }
            $row['answers'] = $answers;
            return $row;
        }, $results);
    } catch (PDOException $e) {
        error_log("DB Error (get_all_survey_data): " . $e->getMessage());
        return false;
    }
}


/**
 * 一元配置分散分析 (One-way ANOVA) を計算する
 */
function calculate_anova($data, $group_key, $value_key)
{
    $groups = [];
    $all_values = [];
    foreach ($data as $row) {
        if (!isset($row[$group_key]) || !isset($row['answers'][$value_key])) continue;
        $group_name = $row[$group_key];
        $value = $row['answers'][$value_key];
        $groups[$group_name][] = $value;
        $all_values[] = $value;
    }

    foreach ($groups as $name => $values) {
        if (count($values) < 2) {
            unset($groups[$name]);
        }
    }

    $k = count($groups); // 群の数
    $n_total = 0;
    foreach($groups as $values) $n_total += count($values);

    if ($k < 2 || $n_total < 3) return null;

    $grand_mean = array_sum($all_values) / count($all_values);

    $ss_between = 0;
    $ss_within = 0;
    foreach ($groups as $group_name => $values) {
        $n_group = count($values);
        if ($n_group == 0) continue;
        $group_mean = array_sum($values) / $n_group;
        $ss_between += $n_group * pow($group_mean - $grand_mean, 2);
        foreach ($values as $value) {
            $ss_within += pow($value - $group_mean, 2);
        }
    }

    $df_between = $k - 1;
    $df_within = $n_total - $k;

    if ($df_between <= 0 || $df_within <= 0) return null;

    $ms_between = $ss_between / $df_between;
    $ms_within = $ss_within / $df_within;

    $f_value = ($ms_within > 0) ? $ms_between / $ms_within : 0;
    
    $significance_level = 0;
    if ($f_value > 2.5) $significance_level = 0.05; // 簡易的な閾値
    if ($f_value > 4.0) $significance_level = 0.01; // 簡易的な閾値

    return [
        'df_between' => $df_between, 'ss_between' => $ss_between, 'ms_between' => $ms_between,
        'df_within' => $df_within, 'ss_within' => $ss_within, 'ms_within' => $ms_within,
        'f_value' => $f_value, 'significance_level' => $significance_level,
        'groups' => $groups,
    ];
}

/**
 * テューキーのHSD法による多重比較を計算する
 */
function calculate_tukey_hsd($groups, $ms_within, $df_within)
{
    $k = count($groups);
    if ($k < 2 || $ms_within <= 0) return null;
    
    $q_critical = 3.63; // α=0.05, k=3, df=∞ の場合に近い値（簡易）

    $means = [];
    foreach ($groups as $name => $values) {
        $means[$name] = ['mean' => array_sum($values) / count($values), 'n' => count($values)];
    }

    $group_names = array_keys($means);
    $comparisons = [];
    for ($i = 0; $i < $k; $i++) {
        for ($j = $i + 1; $j < $k; $j++) {
            $name1 = $group_names[$i];
            $name2 = $group_names[$j];
            $mean1 = $means[$name1]['mean'];
            $mean2 = $means[$name2]['mean'];
            $n1 = $means[$name1]['n'];
            $n2 = $means[$name2]['n'];
            
            if($n1 == 0 || $n2 == 0) continue;

            $hsd = $q_critical * sqrt($ms_within * (1/$n1 + 1/$n2) / 2);
            $diff = abs($mean1 - $mean2);

            $comparisons[] = [
                'group1' => $name1, 'group2' => $name2,
                'diff' => $diff, 'hsd' => $hsd,
                'significant' => $diff > $hsd,
            ];
        }
    }
    return $comparisons;
}

/**
 * 二元配置分散分析 (Two-way ANOVA) を計算する
 */
function calculate_two_way_anova($data, $factorA_key, $factorB_key, $value_key) {
    $cells = [];
    $all_values = [];
    foreach ($data as $row) {
        if (!isset($row[$factorA_key]) || !isset($row[$factorB_key]) || !isset($row['answers'][$value_key])) continue;
        $a_level = $row[$factorA_key];
        $b_level = $row[$factorB_key];
        $value = (float)$row['answers'][$value_key];
        $cells[$a_level][$b_level][] = $value;
        $all_values[] = $value;
    }

    $factorA_levels = array_keys($cells);
    $factorB_levels = [];
    foreach($cells as $a_level => $b_data){
        foreach($b_data as $b_level => $values){
            if(!in_array($b_level, $factorB_levels)) $factorB_levels[] = $b_level;
        }
    }
    sort($factorA_levels);
    sort($factorB_levels);

    foreach ($factorA_levels as $a_level) {
        foreach ($factorB_levels as $b_level) {
            if (!isset($cells[$a_level][$b_level]) || count($cells[$a_level][$b_level]) < 2) {
                return "各グループの組み合わせに最低2件以上のデータが必要です。";
            }
        }
    }

    $a = count($factorA_levels);
    $b = count($factorB_levels);
    $N = count($all_values);
    if($a < 2 || $b < 2) return "各要因に2つ以上の水準が必要です。";

    $grand_total = array_sum($all_values);
    $grand_mean = $grand_total / $N;
    
    $ss_total = 0;
    foreach($all_values as $val) $ss_total += pow($val - $grand_mean, 2);

    $row_totals = []; $row_counts = [];
    $col_totals = []; $col_counts = [];
    $cell_stats = [];

    foreach ($factorA_levels as $a_level) {
        $row_totals[$a_level] = 0; $row_counts[$a_level] = 0;
    }
    foreach ($factorB_levels as $b_level) {
        $col_totals[$b_level] = 0; $col_counts[$b_level] = 0;
    }

    $ss_error = 0;
    foreach ($factorA_levels as $a_level) {
        foreach ($factorB_levels as $b_level) {
            $values = $cells[$a_level][$b_level];
            $n = count($values);
            $sum = array_sum($values);
            $mean = $sum / $n;
            $ss = 0;
            foreach($values as $v) $ss += pow($v - $mean, 2);
            
            $cell_stats[$a_level][$b_level] = ['mean' => $mean, 'n' => $n, 'sum' => $sum];
            $row_totals[$a_level] += $sum; $row_counts[$a_level] += $n;
            $col_totals[$b_level] += $sum; $col_counts[$b_level] += $n;
            $ss_error += $ss;
        }
    }

    $ss_a = 0;
    foreach($row_totals as $a_level => $total) $ss_a += pow($total, 2) / $row_counts[$a_level];
    $ss_a -= pow($grand_total, 2) / $N;

    $ss_b = 0;
    foreach($col_totals as $b_level => $total) $ss_b += pow($total, 2) / $col_counts[$b_level];
    $ss_b -= pow($grand_total, 2) / $N;

    $ss_cells = 0;
    foreach($cell_stats as $a_level => $b_data){
        foreach($b_data as $b_level => $stats){
            $ss_cells += pow($stats['sum'], 2) / $stats['n'];
        }
    }
    $ss_cells -= pow($grand_total, 2) / $N;

    $ss_ab = $ss_cells - $ss_a - $ss_b;

    $df_a = $a - 1;
    $df_b = $b - 1;
    $df_ab = ($a - 1) * ($b - 1);
    $df_error = $N - ($a * $b);
    
    if ($df_a <= 0 || $df_b <= 0 || $df_ab < 0 || $df_error <= 0) return "自由度の計算に問題が発生しました。";
    
    $ms_a = $ss_a / $df_a;
    $ms_b = $ss_b / $df_b;
    $ms_ab = $df_ab > 0 ? $ss_ab / $df_ab : 0;
    $ms_error = $ss_error / $df_error;

    $f_a = $ms_error > 0 ? $ms_a / $ms_error : 0;
    $f_b = $ms_error > 0 ? $ms_b / $ms_error : 0;
    $f_ab = $ms_error > 0 ? $ms_ab / $ms_error : 0;

    return [
        'factorA' => ['ss' => $ss_a, 'df' => $df_a, 'ms' => $ms_a, 'f' => $f_a],
        'factorB' => ['ss' => $ss_b, 'df' => $df_b, 'ms' => $ms_b, 'f' => $f_b],
        'interaction' => ['ss' => $ss_ab, 'df' => $df_ab, 'ms' => $ms_ab, 'f' => $f_ab],
        'error' => ['ss' => $ss_error, 'df' => $df_error, 'ms' => $ms_error],
        'total' => ['ss' => $ss_total, 'df' => $N - 1],
        'cell_stats' => $cell_stats,
        'factorA_levels' => $factorA_levels,
        'factorB_levels' => $factorB_levels
    ];
}


// =================================================================
//  3. メイン処理
// =================================================================
$active_tab = 'simple'; // デフォルトタブ

// POSTリクエスト処理 (再分析)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error_message = '不正なリクエストです。ページを再読み込みしてください。';
    } else {
        $active_tab = isset($_POST['submit_advanced']) ? 'advanced' : 'simple';
        $selected_question_key = $_POST['analysis_question'] ?? 'q2';
        $selected_group_key = $_POST['analysis_group'] ?? 'gender';
        $factorA_key = $_POST['factor_a'] ?? 'age_group';
        $factorB_key = $_POST['factor_b'] ?? 'gender';
    }
} else {
    $selected_question_key = 'q2';
    $selected_group_key = 'gender';
    $factorA_key = 'age_group';
    $factorB_key = 'gender';
}

$csrf_token = generate_csrf_token();
$questions_text = [
    'q1' => 'ドキュメンタリー動画への興味', 'q2' => 'VR仮想旅行への興味', 'q3' => '専門的な解説への魅力',
    'q4' => '高画質映像への価値', 'q5' => '月々500円の支払い意欲', 'q6' => '月々1500円の支払い意欲',
    'q7' => 'コンテンツの更新頻度への魅力', 'q8' => '利用の手軽さの重要度', 'q9' => '教育利用への関心', 'q10' => '旅行先選択への影響'
];
$group_text_map = ['gender' => '性別', 'age_group' => '年代', 'income_group' => '年収層', 'disability' => '障害有無'];

if (!$error_message) {
    $total_count = get_total_count();
    if ($total_count === false) {
        $error_message = "データの読み込みに失敗しました。";
    } elseif ($total_count > 0) {
        $demographics = get_demographics_data();
        $radar_avg_scores = get_radar_avg_scores();
        if ($demographics === false || $radar_avg_scores === false) {
            $error_message = "データの読み込みに失敗しました。";
            $total_count = 0;
        }
    }
}

if (!$error_message && in_array($_SESSION['role'], ['admin', 'editor'])) {
    if ($total_count > 10) {
        $survey_data = get_all_survey_data();
        if ($survey_data === false) {
            $error_message = "分析用データの読み込みに失敗しました。";
        } else {
            // 一元配置
            $anova_result = calculate_anova($survey_data, $selected_group_key, $selected_question_key);
            if ($anova_result && $anova_result['significance_level'] > 0) {
                $tukey_result = calculate_tukey_hsd($anova_result['groups'], $anova_result['ms_within'], $anova_result['df_within']);
            }

            // 二元配置
            if ($total_count > 20) {
                if ($factorA_key === $factorB_key) {
                    $anova2_error_message = "要因Aと要因Bには異なるグループを選択してください。";
                } else {
                    $anova2_raw_result = calculate_two_way_anova($survey_data, $factorA_key, $factorB_key, $selected_question_key);
                    if (is_array($anova2_raw_result)) {
                        $anova2_result = $anova2_raw_result;
                    } elseif (is_string($anova2_raw_result)) {
                        $anova2_error_message = $anova2_raw_result;
                    }
                }
            } else {
                $anova2_error_message = "二元配置分散分析には、さらに多くのデータ(最低20件以上)が必要です。";
            }
        }
    }
}

// グラフデータ準備
if ($anova_result) {
    $groups_for_chart = [];
    foreach (($anova_result['groups'] ?? []) as $group_name => $values) {
        if (count($values) > 0) $groups_for_chart[$group_name] = array_sum($values) / count($values);
    }
    ksort($groups_for_chart);
    $chart_labels = array_keys($groups_for_chart);
    $chart_data = array_values($groups_for_chart);
}
if ($anova2_result) {
    foreach ($anova2_result['factorB_levels'] as $b_level) {
        $dataset = ['label' => $b_level, 'data' => []];
        foreach ($anova2_result['factorA_levels'] as $a_level) {
            $dataset['data'][] = $anova2_result['cell_stats'][$a_level][$b_level]['mean'] ?? 0;
        }
        $interaction_plot_data[] = $dataset;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アンケート結果分析レポート</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .tab-button.active { border-bottom: 2px solid #3B82F6; color: #2563EB; font-weight: 600; }
        .tab-content.hidden { display: none; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: right; }
        th { background-color: #f7fafc; font-weight: bold; text-align: center; }
        .significant { color: #E53E3E; font-weight: bold; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-4 md:p-8">
        <header class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">分析レポート</h1>
            <div class="text-right">
                <span class="text-sm text-gray-600 mr-4">ようこそ, <?php echo h($_SESSION['user']); ?> さん (権限: <?php echo h($_SESSION['role']); ?>)</span>
                <a href="change_password.php" class="text-sm text-blue-600 hover:underline mr-4">パスワード変更</a>
                <a href="logout.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg text-sm">ログアウト</a>
            </div>
        </header>

        <nav class="bg-white p-4 rounded-lg shadow-md mb-8 flex justify-start items-center gap-4 flex-wrap">
            <span class="font-bold">メニュー:</span>
            <?php if (in_array($_SESSION['role'], ['admin', 'editor'])): ?>
                <a href="view_data.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg text-sm">DB表示・操作</a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="manage_users.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg text-sm">ユーザー管理</a>
            <?php endif; ?>
            <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm">アンケートトップ</a>
            <div class="ml-auto flex items-center gap-4">
                <span class="font-bold">ダウンロード:</span>
                <?php if (in_array($_SESSION['role'], ['admin', 'editor'])): ?>
                     <a href="download.php?type=survey" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-lg text-sm">アンケート結果 (CSV)</a>
                <?php endif; ?>
                 <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="download.php?type=users" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-lg text-sm">ユーザーリスト (CSV)</a>
                <?php endif; ?>
            </div>
        </nav>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-6 rounded-lg shadow-md text-center"><p class="font-bold text-lg">エラー</p><p class="mt-2"><?php echo h($error_message); ?></p></div>
        <?php endif; ?>

        <p class="text-center text-gray-600 mb-6">世界遺産コンテンツに関するアンケート結果 (総回答者数: <?php echo h($total_count); ?>名)</p>

        <?php if ($_SESSION['role'] === 'viewer'): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center"><p class="font-bold text-lg">詳細な分析機能の閲覧権限がありません。</p><p class="mt-2">全体の集計データのみ表示しています。</p></div>
        <?php endif; ?>

        <?php if ($total_count < 10): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center mt-6"><p class="font-bold text-lg">まだ回答データが十分ではありません (現在 <?php echo h($total_count); ?> 件)。</p><p class="mt-2">詳細な分析には最低10件程度のデータが必要です。</p><a href="index.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md">アンケートに回答する</a></div>
        <?php else: ?>
            <div class="mb-8 mt-6">
                <h2 class="text-2xl font-bold mb-4 text-gray-700 border-b-2 pb-2">全体集計データ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-lg"><h3 class="font-semibold text-center mb-2">性別</h3><div class="relative h-72"><canvas id="genderChart"></canvas></div></div>
                    <div class="bg-white p-4 rounded-xl shadow-lg"><h3 class="font-semibold text-center mb-2">年代</h3><div class="relative h-72"><canvas id="ageChart"></canvas></div></div>
                    <div class="bg-white p-4 rounded-xl shadow-lg"><h3 class="font-semibold text-center mb-2">年収層</h3><div class="relative h-72"><canvas id="incomeChart"></canvas></div></div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-lg"><h3 class="font-semibold text-center mb-2">各質問のスコア比較 <span class="text-sm font-normal">(全体の平均評価点)</span></h3><canvas id="radarChart"></canvas></div>
            </div>

            <?php if (in_array($_SESSION['role'], ['admin', 'editor'])): ?>
                <div id="analysis" class="mt-12">
                    <div class="flex border-b border-gray-300"><button data-tab-target="#simple-analysis" class="tab-button px-6 py-3 text-lg <?php echo $active_tab == 'simple' ? 'active' : 'text-gray-500 hover:bg-gray-200'; ?>">かんたん分析 (一元配置)</button><button data-tab-target="#advanced-analysis" class="tab-button px-6 py-3 text-lg <?php echo $active_tab == 'advanced' ? 'active' : 'text-gray-500 hover:bg-gray-200'; ?>">詳細分析 (二元配置)</button></div>
                </div>

                <div id="simple-analysis" class="tab-content <?php echo $active_tab == 'simple' ? '' : 'hidden'; ?>">
                    <div class="bg-white p-6 rounded-b-xl shadow-lg">
                        <h2 class="text-xl font-bold mb-4 text-gray-700">クロス集計と分散分析 (一元配置)</h2>
                        <form method="POST" action="analysis.php#analysis" class="bg-gray-50 p-4 rounded-lg flex flex-wrap items-center gap-4 mb-6">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                            <div class="flex-grow"><label for="analysis_group" class="text-sm font-medium text-gray-700">分析の軸:</label><select name="analysis_group" id="analysis_group" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($group_text_map as $key => $text): ?><option value="<?php echo h($key); ?>" <?php if ($selected_group_key == $key) echo 'selected'; ?>><?php echo h($text); ?></option><?php endforeach; ?></select></div>
                            <div class="flex-grow"><label for="analysis_question_simple" class="text-sm font-medium text-gray-700">分析対象の質問:</label><select name="analysis_question" id="analysis_question_simple" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($questions_text as $key => $text): ?><option value="<?php echo h($key); ?>" <?php if ($selected_question_key == $key) echo 'selected'; ?>><?php echo h($text); ?></option><?php endforeach; ?></select></div>
                            <button type="submit" name="submit_simple" value="1" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md self-end">再分析</button>
                        </form>

                        <?php if ($anova_result): ?>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div>
                                    <h3 class="font-semibold text-center mb-2"><?php echo h($group_text_map[$selected_group_key] ?? ''); ?>別の「<?php echo h($questions_text[$selected_question_key] ?? ''); ?>」平均評価<?php if ($anova_result['significance_level'] > 0): ?><span class="text-red-500 font-bold text-lg"><?php echo ($anova_result['significance_level'] <= 0.01) ? '**' : '*'; ?></span><?php endif; ?></h3>
                                    <canvas id="crossAnalysisChart" class="p-4"></canvas>
                                </div>
                                <div class="overflow-x-auto">
                                    <h3 class="font-semibold mb-2">分散分析 (ANOVA) 結果</h3>
                                    <table>
                                        <thead><tr><th>要因</th><th>平方和</th><th>自由度</th><th>平均平方</th><th>F値</th></tr></thead>
                                        <tbody>
                                            <tr><th><?php echo h($group_text_map[$selected_group_key] ?? ''); ?> (群間)</th><td><?php echo round($anova_result['ss_between'], 2); ?></td><td><?php echo $anova_result['df_between']; ?></td><td><?php echo round($anova_result['ms_between'], 2); ?></td><td class="<?php if($anova_result['significance_level'] > 0) echo 'significant'; ?>"><?php echo round($anova_result['f_value'], 2); ?></td></tr>
                                            <tr><th>残差 (群内)</th><td><?php echo round($anova_result['ss_within'], 2); ?></td><td><?php echo $anova_result['df_within']; ?></td><td><?php echo round($anova_result['ms_within'], 2); ?></td><td>-</td></tr>
                                        </tbody>
                                    </table>
                                    <p class="text-xs mt-2 text-gray-600">* p &lt; 0.05, ** p &lt; 0.01 (簡易判定)</p>

                                    <?php if ($tukey_result): ?>
                                    <h3 class="font-semibold mb-2 mt-6">多重比較 (Tukey's HSD) 結果</h3>
                                    <table>
                                        <thead><tr><th>比較</th><th>平均差</th><th>有意差</th></tr></thead>
                                        <tbody>
                                            <?php foreach($tukey_result as $comp): ?>
                                            <tr class="<?php if($comp['significant']) echo 'significant'; ?>"><th><?php echo h($comp['group1']); ?> vs <?php echo h($comp['group2']); ?></th><td><?php echo round($comp['diff'], 2); ?></td><td><?php echo $comp['significant'] ? 'あり' : 'なし'; ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-gray-500 p-4">分散分析を実行するデータが不足しているか、条件を満たしませんでした。</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="advanced-analysis" class="tab-content <?php echo $active_tab == 'advanced' ? '' : 'hidden'; ?>">
                    <div class="bg-white p-6 rounded-b-xl shadow-lg">
                        <h2 class="text-xl font-bold mb-4 text-gray-700">クロス集計と分散分析 (二元配置)</h2>
                        <form method="POST" action="analysis.php#analysis" class="bg-gray-50 p-4 rounded-lg flex flex-wrap items-center gap-4 mb-6">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                            <div class="flex-grow"><label for="factor_a" class="text-sm font-medium text-gray-700">要因A (横軸):</label><select name="factor_a" id="factor_a" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($group_text_map as $key => $text): ?><option value="<?php echo h($key); ?>" <?php if ($factorA_key == $key) echo 'selected'; ?>><?php echo h($text); ?></option><?php endforeach; ?></select></div>
                            <div class="flex-grow"><label for="factor_b" class="text-sm font-medium text-gray-700">要因B (凡例):</label><select name="factor_b" id="factor_b" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($group_text_map as $key => $text): ?><option value="<?php echo h($key); ?>" <?php if ($factorB_key == $key) echo 'selected'; ?>><?php echo h($text); ?></option><?php endforeach; ?></select></div>
                            <div class="flex-grow"><label for="analysis_question_adv" class="text-sm font-medium text-gray-700">分析対象の質問:</label><select name="analysis_question" id="analysis_question_adv" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($questions_text as $key => $text): ?><option value="<?php echo h($key); ?>" <?php if ($selected_question_key == $key) echo 'selected'; ?>><?php echo h($text); ?></option><?php endforeach; ?></select></div>
                            <button type="submit" name="submit_advanced" value="1" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md self-end">再分析</button>
                        </form>

                        <?php if ($anova2_result): ?>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div><h3 class="font-semibold text-center mb-2">交互作用プロット</h3><canvas id="interactionPlot" class="p-4"></canvas></div>
                                <div class="overflow-x-auto">
                                    <h3 class="font-semibold mb-2">分散分析 (ANOVA) 結果</h3>
                                    <table>
                                        <thead><tr><th>要因</th><th>平方和</th><th>自由度</th><th>平均平方</th><th>F値</th></tr></thead>
                                        <tbody>
                                            <tr><th><?php echo h($group_text_map[$factorA_key] ?? ''); ?> (A)</th><td><?php echo round($anova2_result['factorA']['ss'], 2); ?></td><td><?php echo $anova2_result['factorA']['df']; ?></td><td><?php echo round($anova2_result['factorA']['ms'], 2); ?></td><td><?php echo round($anova2_result['factorA']['f'], 2); ?></td></tr>
                                            <tr><th><?php echo h($group_text_map[$factorB_key] ?? ''); ?> (B)</th><td><?php echo round($anova2_result['factorB']['ss'], 2); ?></td><td><?php echo $anova2_result['factorB']['df']; ?></td><td><?php echo round($anova2_result['factorB']['ms'], 2); ?></td><td><?php echo round($anova2_result['factorB']['f'], 2); ?></td></tr>
                                            <tr><th>交互作用 (A*B)</th><td><?php echo round($anova2_result['interaction']['ss'], 2); ?></td><td><?php echo $anova2_result['interaction']['df']; ?></td><td><?php echo round($anova2_result['interaction']['ms'], 2); ?></td><td><?php echo round($anova2_result['interaction']['f'], 2); ?></td></tr>
                                            <tr><th>残差</th><td><?php echo round($anova2_result['error']['ss'], 2); ?></td><td><?php echo $anova2_result['error']['df']; ?></td><td><?php echo round($anova2_result['error']['ms'], 2); ?></td><td>-</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center"><p class="font-bold">二元配置分散分析を実行できません。</p><?php if ($anova2_error_message): ?><p class="mt-2 text-sm text-red-600 font-semibold"><?php echo h($anova2_error_message); ?></p><?php else: ?><p class="mt-2 text-sm">各グループの組み合わせに、最低2件以上のデータが必要です。データ数を増やすか、別の組み合わせをお試しください。</p><?php endif; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab-button');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = document.querySelector(tab.dataset.tabTarget);
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                if(target) target.classList.remove('hidden');
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
            });
        });
        const pieOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { padding: 15 } } } };
        const pieColors = ['#60A5FA', '#F87171', '#4ADE80', '#FBBF24', '#A78BFA', '#F472B6'];
        <?php if ($total_count > 0 && !empty($demographics)): ?>
            if (document.getElementById('genderChart') && <?php echo json_encode(!empty($demographics['gender'])); ?>) { new Chart('genderChart', { type: 'pie', data: { labels: <?php echo json_encode(array_keys($demographics['gender'])); ?>, datasets: [{ data: <?php echo json_encode(array_values($demographics['gender'])); ?>, backgroundColor: pieColors }] }, options: pieOptions }); }
            if (document.getElementById('ageChart') && <?php echo json_encode(!empty($demographics['age'])); ?>) { new Chart('ageChart', { type: 'pie', data: { labels: <?php echo json_encode(array_keys($demographics['age'])); ?>, datasets: [{ data: <?php echo json_encode(array_values($demographics['age'])); ?>, backgroundColor: pieColors }] }, options: pieOptions }); }
            if (document.getElementById('incomeChart') && <?php echo json_encode(!empty($demographics['income'])); ?>) { new Chart('incomeChart', { type: 'pie', data: { labels: <?php echo json_encode(array_keys($demographics['income'])); ?>, datasets: [{ data: <?php echo json_encode(array_values($demographics['income'])); ?>, backgroundColor: pieColors }] }, options: pieOptions }); }
            if (document.getElementById('radarChart') && <?php echo json_encode(!empty($radar_avg_scores)); ?>) { new Chart('radarChart', { type: 'radar', data: { labels: <?php echo json_encode(array_values($questions_text)); ?>, datasets: [{ label: '全体の平均評価点', data: <?php echo json_encode($radar_avg_scores); ?>, fill: true, backgroundColor: 'rgba(59, 130, 246, 0.2)', borderColor: 'rgb(59, 130, 246)', pointBackgroundColor: 'rgb(59, 130, 246)' }] }, options: { scales: { r: { beginAtZero: true, max: 5, pointLabels: { font: { size: window.innerWidth > 768 ? 12 : 9 } } } }, plugins: { legend: { position: 'bottom' } } } }); }
        <?php endif; ?>
        <?php if ($anova_result && !empty($chart_data)): ?>
            if (document.getElementById('crossAnalysisChart')) { new Chart('crossAnalysisChart', { type: 'bar', data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: '平均評価点', data: <?php echo json_encode($chart_data); ?>, backgroundColor: pieColors }] }, options: { indexAxis: 'y', scales: { x: { beginAtZero: true, max: 5 } }, plugins: { legend: { display: false } } } }); }
        <?php endif; ?>
        <?php if ($anova2_result && !empty($interaction_plot_data)): ?>
            const interactionPlotDatasets = <?php echo json_encode($interaction_plot_data); ?>.map((ds, i) => ({ ...ds, borderColor: pieColors[i % pieColors.length], backgroundColor: pieColors[i % pieColors.length], tension: 0.1 }));
            if (document.getElementById('interactionPlot')) { new Chart('interactionPlot', { type: 'line', data: { labels: <?php echo json_encode($anova2_result['factorA_levels']); ?>, datasets: interactionPlotDatasets }, options: { scales: { y: { beginAtZero: true, max: 5, title: { display: true, text: '平均評価点' } } }, plugins: { legend: { position: 'bottom' } } } }); }
        <?php endif; ?>
    });
    </script>
</body>
</html>