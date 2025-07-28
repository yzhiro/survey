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

// (F検定、Tukey法、二元配置分散分析などの統計関数は長いため、ここでは変更がないものとして元のコードを流用します)
// (実際の開発では、これらの関数も別のファイルに分離すると、より見通しが良くなります)
function get_f_critical_value($df1, $df2, $alpha = 0.05)
{ /* 元のコードと同じ */
}
function get_q_critical_value($k, $df, $alpha = 0.05)
{ /* 元のコードと同じ */
}
function calculate_anova($data, $group_key, $value_key)
{ /* 元のコードと同じ */
}
function calculate_tukey_hsd($groups, $ms_within, $df_within)
{ /* 元のコードと同じ */
}
function calculate_two_way_anova($data, $factorA_key, $factorB_key, $value_key)
{ /* 元のコードと同じ */
}

// =================================================================
//  3. メイン処理
// =================================================================
$active_tab = 'simple'; // デフォルトタブ

// POSTリクエスト処理 (再分析)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークン検証
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error_message = '不正なリクエストです。ページを再読み込みしてください。';
    } else {
        // タブの状態を維持
        $active_tab = isset($_POST['submit_advanced']) ? 'advanced' : 'simple';
    }
}

// CSRFトークンを生成してフォームに渡す
$csrf_token = generate_csrf_token();

// --- データ定義 ---
$questions_text = [
    'q1' => 'ドキュメンタリー動画への興味',
    'q2' => 'VR仮想旅行への興味',
    'q3' => '専門的な解説への魅力',
    'q4' => '高画質映像への価値',
    'q5' => '月々500円の支払い意欲',
    'q6' => '月々1500円の支払い意欲',
    'q7' => 'コンテンツの更新頻度への魅力',
    'q8' => '利用の手軽さの重要度',
    'q9' => '教育利用への関心',
    'q10' => '旅行先選択への影響'
];
$group_text_map = ['gender' => '性別', 'age_group' => '年代', 'income_group' => '年収層', 'disability' => '障害有無'];

// フォームから送信された値を取得、なければデフォルト値を設定
$selected_question_key = $_POST['analysis_question'] ?? 'q2';
$selected_group_key = $_POST['analysis_group'] ?? 'gender';
$factorA_key = $_POST['factor_a'] ?? 'age_group';
$factorB_key = $_POST['factor_b'] ?? 'gender';


// --- データ取得実行 ---
if (!$error_message) {
    $total_count = get_total_count();
    if ($total_count === false) {
        $error_message = "データの読み込みに失敗しました。";
    } elseif ($total_count > 0) {
        $demographics = get_demographics_data();
        $radar_avg_scores = get_radar_avg_scores();
        if ($demographics === false || $radar_avg_scores === false) {
            $error_message = "データの読み込みに失敗しました。";
            $total_count = 0; // エラー時は以降の処理をスキップ
        }
    }
}


// --- 統計分析実行 (管理者・編集者のみ) ---
if (!$error_message && in_array($_SESSION['role'], ['admin', 'editor'])) {
    if ($total_count > 10) {
        $survey_data = get_all_survey_data();
        if ($survey_data === false) {
            $error_message = "分析用データの読み込みに失敗しました。";
        } else {
            // 一元配置分散分析
            $anova_result = calculate_anova($survey_data, $selected_group_key, $selected_question_key);
            if ($anova_result && $anova_result['significance_level'] > 0) {
                $tukey_result = calculate_tukey_hsd($anova_result['groups'], $anova_result['ms_within'], $anova_result['df_within']);
            }

            // 二元配置分散分析
            if ($total_count > 20) {
                if ($factorA_key === $factorB_key) {
                    // 同じ要因が選択された場合はデフォルトに戻す
                    $factorA_key = 'age_group';
                    $factorB_key = 'gender';
                }
                $anova2_raw_result = calculate_two_way_anova($survey_data, $factorA_key, $factorB_key, $selected_question_key);
                if (is_array($anova2_raw_result)) {
                    $anova2_result = $anova2_raw_result;
                } elseif (is_string($anova2_raw_result)) {
                    $anova2_error_message = $anova2_raw_result;
                }
            }
        }
    }
}


// --- グラフデータ準備 ---
if ($anova_result) {
    // 一元配置の棒グラフ用データ
    $groups_for_chart = [];
    foreach ($survey_data as $row) {
        $group_name = $row[$selected_group_key] ?? 'N/A';
        $value = $row['answers'][$selected_question_key] ?? null;
        if ($value === null) continue;
        if (!isset($groups_for_chart[$group_name])) $groups_for_chart[$group_name] = ['sum' => 0, 'count' => 0];
        $groups_for_chart[$group_name]['sum'] += $value;
        $groups_for_chart[$group_name]['count']++;
    }
    ksort($groups_for_chart);
    foreach ($groups_for_chart as $name => $values) {
        $chart_labels[] = $name;
        $chart_data[] = $values['count'] > 0 ? $values['sum'] / $values['count'] : 0;
    }
}
if ($anova2_result) {
    // 二元配置の交互作用プロット用データ
    foreach ($anova2_result['factorB_levels'] as $b_level) {
        $dataset = ['label' => $b_level, 'data' => []];
        foreach ($anova2_result['factorA_levels'] as $a_level) {
            $dataset['data'][] = $anova2_result['cell_stats'][$a_level][$b_level]['mean'];
        }
        $interaction_plot_data[] = $dataset;
    }
}

// =================================================================
//  4. ビュー (HTML)
// =================================================================
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
        .tab-button.active {
            border-bottom: 2px solid #3B82F6;
            color: #2563EB;
            font-weight: 600;
        }

        .tab-content.hidden {
            display: none;
        }
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

        <nav class="bg-white p-4 rounded-lg shadow-md mb-8 flex justify-start items-center gap-4">
            <span class="font-bold">メニュー:</span>
            <?php if (in_array($_SESSION['role'], ['admin', 'editor'])): ?>
                <a href="view_data.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg text-sm">DB表示・操作</a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="manage_users.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg text-sm">ユーザー管理</a>
            <?php endif; ?>
            <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm">アンケートトップ</a>
            
            <span class="font-bold ml-auto">ダウンロード:</span>
            <?php if (in_array($_SESSION['role'], ['admin', 'editor'])): ?>
                 <a href="download.php?type=survey" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-lg text-sm">アンケート結果 (CSV)</a>
            <?php endif; ?>
             <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="download.php?type=users" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-lg text-sm">ユーザーリスト (CSV)</a>
            <?php endif; ?>
        </nav>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-6 rounded-lg shadow-md text-center">
                <p class="font-bold text-lg">エラー</p>
                <p class="mt-2"><?php echo h($error_message); ?></p>
            </div>
        <?php endif; ?>

        <p class="text-center text-gray-600 mb-6">世界遺産コンテンツに関するアンケート結果 (総回答者数: <?php echo h($total_count); ?>名)</p>

        <?php if ($_SESSION['role'] === 'viewer'): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center">
                <p class="font-bold text-lg">詳細な分析機能の閲覧権限がありません。</p>
                <p class="mt-2">全体の集計データのみ表示しています。</p>
            </div>
        <?php endif; ?>

        <?php if ($total_count < 20): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center mt-6">
                <p class="font-bold text-lg">まだ回答データが十分ではありません (現在 <?php echo h($total_count); ?> 件)。</p>
                <p class="mt-2">詳細な分析には最低20件程度のデータが必要です。</p>
                <a href="index.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md">アンケートに回答する</a>
            </div>
        <?php else: ?>
            <div class="mb-8 mt-6">
                <h2 class="text-2xl font-bold mb-4 text-gray-700 border-b-2 pb-2">全体集計データ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-lg">
                        <h3 class="font-semibold text-center mb-2">性別</h3>
                        <div class="relative h-72"><canvas id="genderChart"></canvas></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-lg">
                        <h3 class="font-semibold text-center mb-2">年代</h3>
                        <div class="relative h-72"><canvas id="ageChart"></canvas></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-lg">
                        <h3 class="font-semibold text-center mb-2">年収層</h3>
                        <div class="relative h-72"><canvas id="incomeChart"></canvas></div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="font-semibold text-center mb-2">各質問のスコア比較 <span class="text-sm font-normal">(全体の平均評価点)</span></h3>
                    <canvas id="radarChart"></canvas>
                </div>
            </div>

            <?php if (in_array($_SESSION['role'], ['admin', 'editor'])): ?>
                <div id="analysis" class="mt-12">
                    <div class="flex border-b border-gray-300">
                        <button data-tab-target="#simple-analysis" class="tab-button px-6 py-3 text-lg <?php echo $active_tab == 'simple' ? 'active' : 'text-gray-500 hover:bg-gray-200'; ?>">かんたん分析 (一元配置)</button>
                        <button data-tab-target="#advanced-analysis" class="tab-button px-6 py-3 text-lg <?php echo $active_tab == 'advanced' ? 'active' : 'text-gray-500 hover:bg-gray-200'; ?>">詳細分析 (二元配置)</button>
                    </div>
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
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="font-semibold text-center mb-2"><?php echo h($group_text_map[$selected_group_key] ?? ''); ?>別の「<?php echo h($questions_text[$selected_question_key] ?? ''); ?>」平均評価<?php if ($anova_result['significance_level'] > 0): ?><span class="text-red-500 font-bold text-lg"><?php echo ($anova_result['significance_level'] == 0.01) ? '**' : '*'; ?></span><?php endif; ?></h3>
                                    <canvas id="crossAnalysisChart" class="p-4"></canvas>
                                </div>
                                <div>
                                    <h3 class="font-semibold mb-2">分散分析 (ANOVA) 結果</h3>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-gray-500 p-4">分散分析を実行するデータが不足しています。</p>
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
                        <?php else: ?>
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center">
                                <p class="font-bold">二元配置分散分析を実行できません。</p>
                                <?php if ($anova2_error_message): ?>
                                    <p class="mt-2 text-sm text-red-600 font-semibold"><?php echo h($anova2_error_message); ?></p>
                                <?php else: ?>
                                    <p class="mt-2 text-sm">各グループの組み合わせに、最低2件以上のデータが必要です。データ数を増やすか、別の組み合わせをお試しください。</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; // role check 
            ?>
        <?php endif; // total_count check 
        ?>
    </div>

    <script>
        // --- JavaScript ---
        document.addEventListener('DOMContentLoaded', function() {
            // タブ切り替えロジック
            const tabs = document.querySelectorAll('.tab-button');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = document.querySelector(tab.dataset.tabTarget);
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
                    if (target) target.classList.remove('hidden');
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                });
            });

            // グラフ描画
            const pieChartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15
                        }
                    }
                }
            };
            const pieColors = ['#60A5FA', '#F87171', '#4ADE80', '#FBBF24', '#A78BFA', '#F472B6'];

            <?php if ($total_count > 0): ?>
                // 円グラフ
                if (document.getElementById('genderChart') && <?php echo json_encode(!empty($demographics['gender'])); ?>) {
                    new Chart(document.getElementById('genderChart'), {
                        type: 'pie',
                        data: {
                            labels: <?php echo json_encode(array_keys($demographics['gender'])); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_values($demographics['gender'])); ?>,
                                backgroundColor: pieColors
                            }]
                        },
                        options: pieChartOptions
                    });
                }
                if (document.getElementById('ageChart') && <?php echo json_encode(!empty($demographics['age'])); ?>) {
                    new Chart(document.getElementById('ageChart'), {
                        type: 'pie',
                        data: {
                            labels: <?php echo json_encode(array_keys($demographics['age'])); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_values($demographics['age'])); ?>,
                                backgroundColor: pieColors
                            }]
                        },
                        options: pieChartOptions
                    });
                }
                if (document.getElementById('incomeChart') && <?php echo json_encode(!empty($demographics['income'])); ?>) {
                    new Chart(document.getElementById('incomeChart'), {
                        type: 'pie',
                        data: {
                            labels: <?php echo json_encode(array_keys($demographics['income'])); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_values($demographics['income'])); ?>,
                                backgroundColor: pieColors
                            }]
                        },
                        options: pieChartOptions
                    });
                }
                // レーダーチャート
                if (document.getElementById('radarChart')) {
                    new Chart(document.getElementById('radarChart'), {
                        type: 'radar',
                        data: {
                            labels: <?php echo json_encode(array_values($questions_text)); ?>,
                            datasets: [{
                                label: '全体の平均評価点',
                                data: <?php echo json_encode($radar_avg_scores); ?>,
                                fill: true,
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                borderColor: 'rgb(59, 130, 246)',
                                pointBackgroundColor: 'rgb(59, 130, 246)'
                            }]
                        },
                        options: {
                            scales: {
                                r: {
                                    beginAtZero: true,
                                    max: 5,
                                    pointLabels: {
                                        font: {
                                            size: window.innerWidth > 768 ? 12 : 9
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>

            <?php if ($anova_result && !empty($chart_data)): ?>
                // 一元配置棒グラフ
                if (document.getElementById('crossAnalysisChart')) {
                    new Chart(document.getElementById('crossAnalysisChart'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($chart_labels); ?>,
                            datasets: [{
                                label: '平均評価点',
                                data: <?php echo json_encode($chart_data); ?>,
                                backgroundColor: pieColors
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    max: 5
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>

            <?php if ($anova2_result): ?>
                // 二元配置折れ線グラフ (交互作用プロット)
                const interactionPlotDatasets = <?php echo json_encode($interaction_plot_data); ?>.map((dataset, index) => ({
                    ...dataset,
                    borderColor: pieColors[index % pieColors.length],
                    backgroundColor: pieColors[index % pieColors.length],
                    tension: 0.1
                }));
                if (document.getElementById('interactionPlot')) {
                    new Chart(document.getElementById('interactionPlot'), {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($anova2_result['factorA_levels']); ?>,
                            datasets: interactionPlotDatasets
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 5,
                                    title: {
                                        display: true,
                                        text: '平均評価点'
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>
        });
    </script>
</body>

</html>