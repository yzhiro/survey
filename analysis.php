<?php
session_start();
// submit.phpからのメッセージと自分のスコアを受け取る
if (isset($_SESSION['message'])) {
    $flash_message = $_SESSION['message'];
    unset($_SESSION['message']);
} else {
    $flash_message = null;
}
if (isset($_SESSION['my_score'])) {
    $my_score_data = $_SESSION['my_score'];
    unset($_SESSION['my_score']); // 1回表示したら削除
} else {
    $my_score_data = null;
}

function load_survey_data()
{
    $file_name = 'data.txt';
    $survey_data = [];
    if (file_exists($file_name)) {
        $lines = file($file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $decoded_line = json_decode($line, true);
            if (is_array($decoded_line)) {
                $survey_data[] = $decoded_line;
            }
        }
    }
    return $survey_data;
}

function get_f_critical_value($df1, $df2, $alpha = 0.05)
{
    // α=0.05 (5%水準)
    $f_table_05 = [
        1 => [1 => 161.4, 2 => 18.51, 3 => 10.13, 4 => 7.71, 5 => 6.61, 10 => 4.96, 20 => 4.35, 30 => 4.17, 40 => 4.08, 60 => 4.00, 120 => 3.92, 'inf' => 3.84],
        2 => [1 => 199.5, 2 => 19.00, 3 => 9.55,  4 => 6.94, 5 => 5.79, 10 => 4.10, 20 => 3.49, 30 => 3.32, 40 => 3.23, 60 => 3.15, 120 => 3.07, 'inf' => 3.00],
        3 => [1 => 215.7, 2 => 19.16, 3 => 9.28,  4 => 6.59, 5 => 5.41, 10 => 3.71, 20 => 3.10, 30 => 2.92, 40 => 2.84, 60 => 2.76, 120 => 2.68, 'inf' => 2.60],
        4 => [1 => 224.6, 2 => 19.25, 3 => 9.12,  4 => 6.39, 5 => 5.19, 10 => 3.48, 20 => 2.87, 30 => 2.69, 40 => 2.61, 60 => 2.53, 120 => 2.45, 'inf' => 2.37],
        5 => [1 => 230.2, 2 => 19.30, 3 => 9.01,  4 => 6.26, 5 => 5.05, 10 => 3.33, 20 => 2.71, 30 => 2.53, 40 => 2.45, 60 => 2.37, 120 => 2.29, 'inf' => 2.21],
        6 => [1 => 234.0, 2 => 19.33, 3 => 8.94,  4 => 6.16, 5 => 4.95, 10 => 3.22, 20 => 2.60, 30 => 2.42, 40 => 2.34, 60 => 2.25, 120 => 2.17, 'inf' => 2.10],
    ];
    $f_table_01 = [
        1 => [1 => 4052, 2 => 98.50, 3 => 34.12, 4 => 21.20, 5 => 16.26, 10 => 10.04, 20 => 8.10, 30 => 7.56, 40 => 7.31, 60 => 7.08, 120 => 6.85, 'inf' => 6.63],
        2 => [1 => 4999, 2 => 99.00, 3 => 30.82, 4 => 18.00, 5 => 13.27, 10 => 7.56,  20 => 5.85, 30 => 5.39, 40 => 5.18, 60 => 4.98, 120 => 4.79, 'inf' => 4.61],
        3 => [1 => 5403, 2 => 99.17, 3 => 29.46, 4 => 16.69, 5 => 12.06, 10 => 6.55,  20 => 4.94, 30 => 4.51, 40 => 4.31, 60 => 4.13, 120 => 3.95, 'inf' => 3.78],
        4 => [1 => 5625, 2 => 99.25, 3 => 28.71, 4 => 15.98, 5 => 11.39, 10 => 5.96,  20 => 4.43, 30 => 4.02, 40 => 3.83, 60 => 3.65, 120 => 3.48, 'inf' => 3.32],
        5 => [1 => 5764, 2 => 99.30, 3 => 28.24, 4 => 15.52, 5 => 10.97, 10 => 5.64,  20 => 4.10, 30 => 3.70, 40 => 3.51, 60 => 3.34, 120 => 3.17, 'inf' => 3.02],
        6 => [1 => 5859, 2 => 99.33, 3 => 27.91, 4 => 15.21, 5 => 10.67, 10 => 5.39,  20 => 3.87, 30 => 3.49, 40 => 3.30, 60 => 3.12, 120 => 2.96, 'inf' => 2.80],
    ];

    $table = ($alpha == 0.01) ? $f_table_01 : $f_table_05;
    $df1 = max(1, min(count($table), $df1));
    $df2_keys_numeric = array_filter(array_keys($table[$df1]), 'is_numeric');
    sort($df2_keys_numeric);

    $found_key = 'inf';
    foreach ($df2_keys_numeric as $key) {
        if ($df2 >= $key) {
            $found_key = $key;
        } else {
            break;
        }
    }
    return $table[$df1][$found_key] ?? 999;
}

function calculate_anova($data, $group_key, $value_key)
{
    $groups = [];
    $all_values = [];
    foreach ($data as $row) {
        $group_name = $row[$group_key] ?? 'N/A';
        $value = $row['answers'][$value_key] ?? null;
        if ($value === null) continue;
        if (!isset($groups[$group_name])) {
            $groups[$group_name] = [];
        }
        $groups[$group_name][] = $value;
        $all_values[] = $value;
    }
    if (count($groups) < 2) return false;
    $total_n = count($all_values);
    if ($total_n < 2) return false;
    $grand_mean = array_sum($all_values) / $total_n;
    $ss_between = 0;
    $ss_within = 0;
    foreach ($groups as $values) {
        $group_n = count($values);
        if ($group_n == 0) continue;
        $group_mean = array_sum($values) / $group_n;
        $ss_between += $group_n * pow($group_mean - $grand_mean, 2);
        foreach ($values as $value) {
            $ss_within += pow($value - $group_mean, 2);
        }
    }
    $df_between = count($groups) - 1;
    $df_within = $total_n - count($groups);
    if ($df_between <= 0 || $df_within <= 0) return false;
    $ms_between = $ss_between / $df_between;
    $ms_within = $ss_within / $df_within;
    $f_value = ($ms_within > 0) ? $ms_between / $ms_within : 0;

    $f_crit_05 = get_f_critical_value($df_between, $df_within, 0.05);
    $f_crit_01 = get_f_critical_value($df_between, $df_within, 0.01);

    $significance_level = 0;
    if ($f_value > $f_crit_01) {
        $significance_level = 0.01;
    } elseif ($f_value > $f_crit_05) {
        $significance_level = 0.05;
    }

    return [
        'df_between' => $df_between,
        'ss_between' => $ss_between,
        'ms_between' => $ms_between,
        'df_within' => $df_within,
        'ss_within' => $ss_within,
        'ms_within' => $ms_within,
        'f_value' => $f_value,
        'critical_value_05' => $f_crit_05,
        'critical_value_01' => $f_crit_01,
        'significance_level' => $significance_level
    ];
}

// --- データ処理 ---
$survey_data = load_survey_data();
foreach ($survey_data as $key => $row) {
    $age = $row['age'] ?? 0;
    if ($age < 30) {
        $age_group = '20代以下';
    } elseif ($age < 40) {
        $age_group = '30代';
    } elseif ($age < 50) {
        $age_group = '40代';
    } elseif ($age < 60) {
        $age_group = '50代';
    } else {
        $age_group = '60代以上';
    }
    $survey_data[$key]['age_group'] = $age_group;
    $income = $row['income'] ?? 0;
    if ($income < 400) {
        $income_group = '400万円未満';
    } elseif ($income < 800) {
        $income_group = '400～800万円';
    } else {
        $income_group = '800万円以上';
    }
    $survey_data[$key]['income_group'] = $income_group;
}
$total_count = count($survey_data);
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
$gender_dist_counts = [];
$age_dist_counts = [];
$income_dist_counts = [];
$answer_dist = [];
foreach (array_keys($questions_text) as $q_key) {
    $answer_dist[$q_key] = array_fill(1, 5, 0);
}

// ★★★★★ 修正箇所：レーダーチャート用の平均値計算ロジックを修正 ★★★★★
$radar_avg_scores = [];
if ($total_count > 0) {
    $gender_dist_counts = array_count_values(array_column($survey_data, 'gender'));
    $age_dist_counts = array_count_values(array_column($survey_data, 'age_group'));
    ksort($age_dist_counts);
    $income_dist_counts = array_count_values(array_column($survey_data, 'income_group'));
    ksort($income_dist_counts);

    // 回答分布を集計
    foreach ($survey_data as $row) {
        foreach ($row['answers'] as $q_key => $answer) {
            if (isset($answer_dist[$q_key][$answer])) {
                $answer_dist[$q_key][$answer]++;
            }
        }
    }
    // 集計結果から平均値を算出
    foreach ($answer_dist as $q_key => $counts) {
        $total_score = 0;
        $total_responses = array_sum($counts);
        if ($total_responses > 0) {
            foreach ($counts as $score => $count) {
                $total_score += $score * $count;
            }
            $radar_avg_scores[] = $total_score / $total_responses;
        } else {
            $radar_avg_scores[] = 0;
        }
    }
}
// ★★★★★ 修正ここまで ★★★★★


$selected_group_key = $_POST['analysis_group'] ?? 'gender';
$selected_question_key = $_POST['analysis_question'] ?? 'q2';
$group_text_map = ['gender' => '性別', 'age_group' => '年代', 'income_group' => '年収層', 'disability' => '障害有無'];
$selected_group_text = $group_text_map[$selected_group_key] ?? '';
$anova_result = ($total_count > 1) ? calculate_anova($survey_data, $selected_group_key, $selected_question_key) : false;
$my_score_for_chart = null;
if ($my_score_data) {
    $my_score_for_chart = [];
    foreach (array_keys($questions_text) as $q_key) {
        $my_score_for_chart[] = $my_score_data['answers'][$q_key] ?? 0;
    }
}
$chart_labels = [];
$chart_data = [];
if (!empty($survey_data)) {
    $groups_for_chart = [];
    foreach ($survey_data as $row) {
        $group_name = $row[$selected_group_key] ?? 'N/A';
        $value = $row['answers'][$selected_question_key] ?? null;
        if ($value === null) continue;
        if (!isset($groups_for_chart[$group_name])) {
            $groups_for_chart[$group_name] = ['sum' => 0, 'count' => 0];
        }
        $groups_for_chart[$group_name]['sum'] += $value;
        $groups_for_chart[$group_name]['count']++;
    }
    ksort($groups_for_chart);
    foreach ($groups_for_chart as $name => $values) {
        $chart_labels[] = $name;
        $chart_data[] = $values['count'] > 0 ? $values['sum'] / $values['count'] : 0;
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
</head>

<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-4 md:p-8">
        <h1 class="text-center text-3xl md:text-4xl font-bold text-gray-800 mb-2">分析レポート</h1>
        <p class="text-center text-gray-600 mb-6">世界遺産コンテンツに関するアンケート結果 (総回答者数: <?php echo $total_count; ?>名)</p>
        <?php if ($flash_message): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg shadow-md mb-8" role="alert">
                <p class="font-bold"><?php echo htmlspecialchars($flash_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($total_count < 10): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center">
                <p class="font-bold text-lg">まだ回答データが十分ではありません (現在 <?php echo $total_count; ?> 件)。</p>
                <p class="mt-2">データが集まると、より詳細な分析が表示されます。</p>
                <a href="index.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md">アンケートに回答する</a>
            </div>
        <?php else: ?>
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4 text-gray-700 border-b-2 pb-2">全体集計データ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-lg">
                        <h3 class="font-semibold text-center mb-2">性別</h3><canvas id="genderChart"></canvas>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-lg">
                        <h3 class="font-semibold text-center mb-2">年代</h3><canvas id="ageChart"></canvas>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-lg">
                        <h3 class="font-semibold text-center mb-2">年収層</h3><canvas id="incomeChart"></canvas>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="font-semibold text-center mb-2">
                        各質問のスコア比較
                        <?php if ($my_score_data): ?>
                            <span class="text-sm font-normal">(<span class="text-blue-600">青: 全体平均</span>, <span class="text-red-600">赤: あなたの回答</span>)</span>
                        <?php else: ?>
                            <span class="text-sm font-normal">(全体の平均評価点)</span>
                        <?php endif; ?>
                    </h3>
                    <canvas id="radarChart"></canvas>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-bold mb-4 text-gray-700 border-b-2 pb-2">クロス集計と分散分析</h2>
                <form method="POST" action="analysis.php#analysis-section" id="analysis-section" class="bg-gray-50 p-4 rounded-lg flex flex-wrap items-center gap-4 mb-6">
                    <div class="flex-grow">
                        <label for="analysis_group" class="text-sm font-medium text-gray-700">分析の軸:</label>
                        <select name="analysis_group" id="analysis_group" class="w-full mt-1 p-2 border border-gray-300 rounded-md">
                            <option value="gender" <?php if ($selected_group_key == 'gender') echo 'selected'; ?>>性別</option>
                            <option value="age_group" <?php if ($selected_group_key == 'age_group') echo 'selected'; ?>>年代</option>
                            <option value="income_group" <?php if ($selected_group_key == 'income_group') echo 'selected'; ?>>年収層</option>
                            <option value="disability" <?php if ($selected_group_key == 'disability') echo 'selected'; ?>>障害有無</option>
                        </select>
                    </div>
                    <div class="flex-grow">
                        <label for="analysis_question" class="text-sm font-medium text-gray-700">分析対象の質問:</label>
                        <select name="analysis_question" id="analysis_question" class="w-full mt-1 p-2 border border-gray-300 rounded-md">
                            <?php foreach ($questions_text as $key => $text): ?>
                                <option value="<?php echo $key; ?>" <?php if ($selected_question_key == $key) echo 'selected'; ?>><?php echo htmlspecialchars($text); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md self-end">再分析</button>
                </form>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-center mb-2">
                            <?php echo htmlspecialchars($selected_group_text); ?>別の「<?php echo htmlspecialchars($questions_text[$selected_question_key]); ?>」平均評価
                            <?php if ($anova_result && $anova_result['significance_level'] > 0): ?>
                                <span class="text-red-500 font-bold text-lg">
                                    <?php echo ($anova_result['significance_level'] == 0.01) ? '**' : '*'; ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <canvas id="crossAnalysisChart" class="p-4"></canvas>
                    </div>
                    <div>
                        <h3 class="font-semibold mb-2">分散分析 (ANOVA) 結果</h3>
                        <?php if ($anova_result): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left text-gray-500">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                        <tr>
                                            <th class="px-4 py-2">要因</th>
                                            <th class="px-4 py-2">平方和</th>
                                            <th class="px-4 py-2">自由度</th>
                                            <th class="px-4 py-2">平均平方</th>
                                            <th class="px-4 py-2">F値</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="bg-white border-b">
                                            <td class="px-4 py-2 font-medium">群間</td>
                                            <td><?php echo round($anova_result['ss_between'], 2); ?></td>
                                            <td><?php echo $anova_result['df_between']; ?></td>
                                            <td><?php echo round($anova_result['ms_between'], 2); ?></td>
                                            <td rowspan="2" class="align-middle font-bold text-lg text-center"><?php echo round($anova_result['f_value'], 2); ?></td>
                                        </tr>
                                        <tr class="bg-white border-b">
                                            <td class="px-4 py-2 font-medium">群内</td>
                                            <td><?php echo round($anova_result['ss_within'], 2); ?></td>
                                            <td><?php echo $anova_result['df_within']; ?></td>
                                            <td><?php echo round($anova_result['ms_within'], 2); ?></td>
                                        </tr>
                                        <tr class="bg-gray-50">
                                            <td class="px-4 py-2 font-bold">合計</td>
                                            <td><?php echo round($anova_result['ss_between'] + $anova_result['ss_within'], 2); ?></td>
                                            <td><?php echo $anova_result['df_between'] + $anova_result['df_within']; ?></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($anova_result['significance_level'] == 0.01): ?>
                                <div class="mt-4 bg-green-100 border-l-4 border-green-600 text-green-900 p-4 rounded-lg shadow">
                                    <p class="font-bold text-lg">結論: 有意差あり (p < 0.01) **</p>
                                            <p class="mt-1">F値(<?php echo round($anova_result['f_value'], 2); ?>)は1%水準の臨界値(<?php echo round($anova_result['critical_value_01'], 2); ?>)を上回り、**極めて強い統計的な有意差**が認められます。</p>
                                </div>
                            <?php elseif ($anova_result['significance_level'] == 0.05): ?>
                                <div class="mt-4 bg-lime-100 border-l-4 border-lime-600 text-lime-900 p-4 rounded-lg shadow">
                                    <p class="font-bold text-lg">結論: 有意差あり (p < 0.05) *</p>
                                            <p class="mt-1">F値(<?php echo round($anova_result['f_value'], 2); ?>)は5%水準の臨界値(<?php echo round($anova_result['critical_value_05'], 2); ?>)を上回り、**統計的に意味のある差**があると考えられます。</p>
                                </div>
                            <?php else: ?>
                                <div class="mt-4 bg-orange-100 border-l-4 border-orange-500 text-orange-800 p-4 rounded-lg shadow">
                                    <p class="font-bold text-lg">結論: 有意差なし (p >= 0.05)</p>
                                    <p class="mt-1">F値(<?php echo round($anova_result['f_value'], 2); ?>)は5%水準の臨界値(<?php echo round($anova_result['critical_value_05'], 2); ?>)を下回り、見られた差は**統計的に偶然の範囲**と判断されます。</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-center text-gray-500 p-4">分散分析を行うには、各グループに複数のデータが必要です。</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
        <?php if ($total_count >= 10): ?>
            const pieChartOptions = {
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
            new Chart(document.getElementById('genderChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($gender_dist_counts)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($gender_dist_counts)); ?>,
                        backgroundColor: pieColors
                    }]
                },
                options: pieChartOptions
            });
            new Chart(document.getElementById('ageChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($age_dist_counts)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($age_dist_counts)); ?>,
                        backgroundColor: pieColors
                    }]
                },
                options: pieChartOptions
            });
            new Chart(document.getElementById('incomeChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($income_dist_counts)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($income_dist_counts)); ?>,
                        backgroundColor: pieColors
                    }]
                },
                options: pieChartOptions
            });

            const radarDatasets = [{
                label: '全体の平均評価点',
                data: <?php echo json_encode($radar_avg_scores); ?>,
                fill: true,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: 'rgb(59, 130, 246)',
                pointBackgroundColor: 'rgb(59, 130, 246)',
            }];
            <?php if ($my_score_for_chart): ?>
                radarDatasets.push({
                    label: 'あなたのスコア',
                    data: <?php echo json_encode($my_score_for_chart); ?>,
                    fill: true,
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderColor: 'rgb(239, 68, 68)',
                    pointBackgroundColor: 'rgb(239, 68, 68)'
                });
            <?php endif; ?>
            new Chart(document.getElementById('radarChart'), {
                type: 'radar',
                data: {
                    labels: <?php echo json_encode(array_values($questions_text)); ?>,
                    datasets: radarDatasets
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
            new Chart(document.getElementById('crossAnalysisChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: '平均評価点',
                        data: <?php echo json_encode($chart_data); ?>,
                        backgroundColor: ['rgba(59, 130, 246, 0.7)', 'rgba(239, 68, 68, 0.7)', 'rgba(16, 185, 129, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(139, 92, 246, 0.7)'],
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
        <?php endif; ?>
    </script>
</body>

</html>