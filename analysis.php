<?php
session_start();

// ログイン状態のチェック (いずれかの権限でログインしていればOK)
if (!isset($_SESSION['role'])) {
    // ログインしていない場合はログインページにリダイレクト
    $_SESSION['error'] = 'レポートを閲覧するにはログインが必要です。';
    header('Location: login.php');
    exit();
}

// データベース接続ファイルを読み込む
require_once 'db_connect.php';

// --- セッションデータ処理 ---
unset($_SESSION['message'], $_SESSION['my_score']);

// --- タブ表示制御 ---
$active_tab = isset($_POST['submit_advanced']) ? 'advanced' : 'simple';

// =================================================================
//  データ取得・処理関数
// =================================================================

/**
 * 総回答者数を取得する
 * @return int
 */
function get_total_count()
{
    try {
        $pdo = get_pdo_connection();
        return (int)$pdo->query("SELECT COUNT(*) FROM survey_db")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * 回答者の属性（性別、年代、年収層）の分布データを取得する
 * @return array
 */
function get_demographics_data()
{
    $pdo = get_pdo_connection();
    $data = ['gender' => [], 'age' => [], 'income' => []];

    try {
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
        // エラー時は空のデータを返す
        return ['gender' => [], 'age' => [], 'income' => []];
    }
    return $data;
}

/**
 * 全質問の全体の平均スコアを取得する
 * @return array
 */
function get_radar_avg_scores()
{
    $pdo = get_pdo_connection();
    try {
        $query = "SELECT AVG(q1), AVG(q2), AVG(q3), AVG(q4), AVG(q5), AVG(q6), AVG(q7), AVG(q8), AVG(q9), AVG(q10) FROM survey_db";
        return $pdo->query($query)->fetch(PDO::FETCH_NUM);
    } catch (PDOException $e) {
        return array_fill(0, 10, 0); // エラー時は0で埋めた配列を返す
    }
}

/**
 * 詳細な分析のために全データを取得する
 * SQLのCASE文でカテゴリ分けまで行い、PHPでの処理をシンプルにする
 * @return array
 */
function get_all_survey_data()
{
    $pdo = get_pdo_connection();
    try {
        $sql = "
            SELECT
                age, income, gender, disability,
                q1, q2, q3, q4, q5, q6, q7, q8, q9, q10,
                CASE
                    WHEN age < 30 THEN '20代以下'
                    WHEN age BETWEEN 30 AND 39 THEN '30代'
                    WHEN age BETWEEN 40 AND 49 THEN '40代'
                    WHEN age BETWEEN 50 AND 59 THEN '50代'
                    ELSE '60代以上'
                END as age_group,
                CASE
                    WHEN income < 400 THEN '400万円未満'
                    WHEN income BETWEEN 400 AND 799 THEN '400～800万円'
                    ELSE '800万円以上'
                END as income_group
            FROM survey_db
        ";
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();

        // 分析関数が期待する'answers'キーを持つネスト構造に変換
        $survey_data = [];
        foreach ($results as $row) {
            $answers = [];
            for ($i = 1; $i <= 10; $i++) {
                $q_key = 'q' . $i;
                $answers[$q_key] = $row[$q_key];
            }
            $survey_data[] = [
                'age' => $row['age'],
                'gender' => $row['gender'],
                'income' => $row['income'],
                'disability' => $row['disability'],
                'age_group' => $row['age_group'],
                'income_group' => $row['income_group'],
                'answers' => $answers,
            ];
        }
        return $survey_data;
    } catch (PDOException $e) {
        return [];
    }
}


// =================================================================
//  統計処理関数
// =================================================================
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
    // α=0.01 (1%水準)
    $f_table_01 = [
        1 => [1 => 4052, 2 => 98.50, 3 => 34.12, 4 => 21.20, 5 => 16.26, 10 => 10.04, 20 => 8.10, 30 => 7.56, 40 => 7.31, 60 => 7.08, 120 => 6.85, 'inf' => 6.63],
        2 => [1 => 4999, 2 => 99.00, 3 => 30.82, 4 => 18.00, 5 => 13.27, 10 => 7.56,  20 => 5.85, 30 => 5.39, 40 => 5.18, 60 => 4.98, 120 => 4.79, 'inf' => 4.61],
        3 => [1 => 5403, 2 => 99.17, 3 => 29.46, 4 => 16.69, 5 => 12.06, 10 => 6.55,  20 => 4.94, 30 => 4.51, 40 => 4.31, 60 => 4.13, 120 => 3.95, 'inf' => 3.78],
        4 => [1 => 5625, 2 => 99.25, 3 => 28.71, 4 => 15.98, 5 => 11.39, 10 => 5.96,  20 => 4.43, 30 => 4.02, 40 => 3.83, 60 => 3.65, 120 => 3.48, 'inf' => 3.32],
        5 => [1 => 5764, 2 => 99.30, 3 => 28.24, 4 => 15.52, 5 => 10.97, 10 => 5.64,  20 => 4.10, 30 => 3.70, 40 => 3.51, 60 => 3.34, 120 => 3.17, 'inf' => 3.02],
        6 => [1 => 5859, 2 => 99.33, 3 => 27.91, 4 => 15.21, 5 => 10.67, 10 => 5.39,  20 => 3.87, 30 => 3.49, 40 => 3.30, 60 => 3.12, 120 => 2.96, 'inf' => 2.80],
    ];

    $table = ($alpha == 0.01) ? $f_table_01 : $f_table_05;
    if (!isset($table[$df1])) return 999;
    $df2_keys_numeric = array_filter(array_keys($table[$df1]), 'is_numeric');
    if (empty($df2_keys_numeric)) return 999;
    rsort($df2_keys_numeric);
    $found_key = 'inf';
    foreach ($df2_keys_numeric as $key) {
        if ($df2 >= $key) {
            $found_key = $key;
            break;
        }
    }
    return $table[$df1][$found_key] ?? 999;
}

function get_q_critical_value($k, $df, $alpha = 0.05)
{
    if ($alpha != 0.05) return 999;
    $q_table = [
        2 => [10 => 3.15, 15 => 3.01, 20 => 2.95, 30 => 2.89, 40 => 2.86, 60 => 2.83, 120 => 2.80, 'inf' => 2.77],
        3 => [10 => 3.88, 15 => 3.67, 20 => 3.58, 30 => 3.49, 40 => 3.44, 60 => 3.40, 120 => 3.36, 'inf' => 3.31],
        4 => [10 => 4.33, 15 => 4.08, 20 => 3.96, 30 => 3.85, 40 => 3.79, 60 => 3.74, 120 => 3.68, 'inf' => 3.63],
        5 => [10 => 4.65, 15 => 4.37, 20 => 4.23, 30 => 4.10, 40 => 4.04, 60 => 3.98, 120 => 3.92, 'inf' => 3.86],
        6 => [10 => 4.91, 15 => 4.60, 20 => 4.45, 30 => 4.30, 40 => 4.23, 60 => 4.17, 120 => 4.10, 'inf' => 4.03],
    ];
    if (!isset($q_table[$k])) return 999;
    $df_keys = array_keys($q_table[$k]);
    $found_key = 'inf';
    foreach (array_reverse($df_keys) as $key) {
        if ($key !== 'inf' && $df >= $key) {
            $found_key = $key;
            break;
        }
    }
    return $q_table[$k][$found_key] ?? 999;
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
        'groups' => $groups,
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

function calculate_tukey_hsd($groups, $ms_within, $df_within)
{
    $k = count($groups);
    if ($k < 2 || $df_within <= 0 || $ms_within <= 0) return [];
    $q_critical = get_q_critical_value($k, $df_within, 0.05);
    $group_names = array_keys($groups);
    $results = [];
    for ($i = 0; $i < $k; $i++) {
        for ($j = $i + 1; $j < $k; $j++) {
            $name1 = $group_names[$i];
            $name2 = $group_names[$j];
            $group1 = $groups[$name1];
            $group2 = $groups[$name2];
            $n1 = count($group1);
            $n2 = count($group2);
            if ($n1 == 0 || $n2 == 0) continue;
            $mean1 = array_sum($group1) / $n1;
            $mean2 = array_sum($group2) / $n2;
            $mean_diff = abs($mean1 - $mean2);
            $hsd = $q_critical * sqrt(($ms_within / 2) * (1 / $n1 + 1 / $n2));
            $results[] = ['pair' => "{$name1} vs {$name2}", 'mean_diff' => $mean_diff, 'hsd' => $hsd, 'is_significant' => ($mean_diff > $hsd)];
        }
    }
    return $results;
}

/**
 * 二元配置分散分析の計算
 * @return array|string 結果の配列、またはエラーメッセージの文字列を返す
 */
function calculate_two_way_anova($data, $factorA_key, $factorB_key, $value_key)
{
    $cells = [];
    $factorA_levels = [];
    $factorB_levels = [];
    $all_values = [];
    foreach ($data as $row) {
        if (!isset($row[$factorA_key], $row[$factorB_key], $row['answers'][$value_key])) continue;
        $a = $row[$factorA_key];
        $b = $row[$factorB_key];
        $val = $row['answers'][$value_key];
        if (!isset($cells[$a])) $cells[$a] = [];
        if (!isset($cells[$a][$b])) $cells[$a][$b] = [];
        $cells[$a][$b][] = $val;
        if (!in_array($a, $factorA_levels)) $factorA_levels[] = $a;
        if (!in_array($b, $factorB_levels)) $factorB_levels[] = $b;
        $all_values[] = $val;
    }
    sort($factorA_levels);
    sort($factorB_levels);
    $a_count = count($factorA_levels);
    $b_count = count($factorB_levels);
    $N = count($all_values);
    if ($a_count < 2 || $b_count < 2) return "要因の数が不足しているため、分析を実行できません。";

    $cell_stats = [];
    foreach ($factorA_levels as $a) {
        foreach ($factorB_levels as $b) {
            $n = isset($cells[$a][$b]) ? count($cells[$a][$b]) : 0;
            if ($n < 2) {
                global $group_text_map;
                $factorA_name = $group_text_map[$factorA_key] ?? $factorA_key;
                $factorB_name = $group_text_map[$factorB_key] ?? $factorB_key;
                return "データ不足のため分析を中止しました。要因「{$factorA_name}」が「{$a}」で、かつ要因「{$factorB_name}」が「{$b}」の組み合わせのデータが{$n}件しかありません。分析には最低2件必要です。";
            }
            $sum = isset($cells[$a][$b]) ? array_sum($cells[$a][$b]) : 0;
            $mean = $n > 0 ? $sum / $n : 0;
            $cell_stats[$a][$b] = ['n' => $n, 'sum' => $sum, 'mean' => $mean];
        }
    }

    $grand_mean = $N > 0 ? array_sum($all_values) / $N : 0;
    $correction_term = $N > 0 ? pow(array_sum($all_values), 2) / $N : 0;
    $SST = 0;
    foreach ($all_values as $v) {
        $SST += pow($v, 2);
    }
    $SST -= $correction_term;
    $SSA = 0;
    foreach ($factorA_levels as $a) {
        $level_sum = 0;
        $level_n = 0;
        foreach ($factorB_levels as $b) {
            $level_sum += $cell_stats[$a][$b]['sum'];
            $level_n += $cell_stats[$a][$b]['n'];
        }
        if ($level_n > 0) $SSA += pow($level_sum, 2) / $level_n;
    }
    $SSA -= $correction_term;
    $SSB = 0;
    foreach ($factorB_levels as $b) {
        $level_sum = 0;
        $level_n = 0;
        foreach ($factorA_levels as $a) {
            $level_sum += $cell_stats[$a][$b]['sum'];
            $level_n += $cell_stats[$a][$b]['n'];
        }
        if ($level_n > 0) $SSB += pow($level_sum, 2) / $level_n;
    }
    $SSB -= $correction_term;
    $SS_cells = 0;
    foreach ($factorA_levels as $a) {
        foreach ($factorB_levels as $b) {
            if ($cell_stats[$a][$b]['n'] > 0) $SS_cells += pow($cell_stats[$a][$b]['sum'], 2) / $cell_stats[$a][$b]['n'];
        }
    }
    $SS_cells -= $correction_term;
    $SSAB = $SS_cells - $SSA - $SSB;
    $SSE = $SST - $SS_cells;
    $dfA = $a_count - 1;
    $dfB = $b_count - 1;
    $dfAB = $dfA * $dfB;
    $dfE = $N - ($a_count * $b_count);
    if ($dfA <= 0 || $dfB <= 0 || $dfE <= 0) return "自由度が0以下になるため分析できません。データが不足しています。";
    $MSA = $SSA / $dfA;
    $MSB = $SSB / $dfB;
    $MSAB = $SSAB >= 0 && $dfAB > 0 ? $SSAB / $dfAB : 0;
    $MSE = $SSE / $dfE;
    $F_A = $MSE > 0 ? $MSA / $MSE : 0;
    $F_B = $MSE > 0 ? $MSB / $MSE : 0;
    $F_AB = ($MSE > 0 && $MSAB >= 0) ? $MSAB / $MSE : 0;
    $sig_A = 0;
    if ($F_A > get_f_critical_value($dfA, $dfE, 0.01)) $sig_A = 0.01;
    elseif ($F_A > get_f_critical_value($dfA, $dfE, 0.05)) $sig_A = 0.05;
    $sig_B = 0;
    if ($F_B > get_f_critical_value($dfB, $dfE, 0.01)) $sig_B = 0.01;
    elseif ($F_B > get_f_critical_value($dfB, $dfE, 0.05)) $sig_B = 0.05;
    $sig_AB = 0;
    if ($dfAB > 0) {
        if ($F_AB > get_f_critical_value($dfAB, $dfE, 0.01)) $sig_AB = 0.01;
        elseif ($F_AB > get_f_critical_value($dfAB, $dfE, 0.05)) $sig_AB = 0.05;
    }
    return [
        'factor_A' => ['ss' => $SSA, 'df' => $dfA, 'ms' => $MSA, 'f' => $F_A, 'sig' => $sig_A],
        'factor_B' => ['ss' => $SSB, 'df' => $dfB, 'ms' => $MSB, 'f' => $F_B, 'sig' => $sig_B],
        'interaction' => ['ss' => $SSAB, 'df' => $dfAB, 'ms' => $MSAB, 'f' => $F_AB, 'sig' => $sig_AB],
        'error' => ['ss' => $SSE, 'df' => $dfE, 'ms' => $MSE],
        'total' => ['ss' => $SST, 'df' => $N - 1],
        'cell_stats' => $cell_stats,
        'factorA_levels' => $factorA_levels,
        'factorB_levels' => $factorB_levels
    ];
}


// =================================================================
//  メイン処理
// =================================================================

$total_count = get_total_count();
$demographics = [];
$gender_dist_counts = [];
$age_dist_counts = [];
$income_dist_counts = [];
$radar_avg_scores = [];

if ($total_count > 0) {
    $demographics = get_demographics_data();
    $gender_dist_counts = $demographics['gender'];
    $age_dist_counts = $demographics['age'];
    $income_dist_counts = $demographics['income'];
    $radar_avg_scores = get_radar_avg_scores();
}

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

$selected_question_key = $_POST['analysis_question'] ?? 'q2';
$selected_group_key = $_POST['analysis_group'] ?? 'gender';
$factorA_key = $_POST['factor_a'] ?? 'age_group';
$factorB_key = $_POST['factor_b'] ?? 'gender';

$survey_data = [];
$anova_result = false;
$tukey_result = null;
$anova2_result = false;
$anova2_error_message = null;

if ($total_count > 10) {
    $survey_data = get_all_survey_data();
    $anova_result = calculate_anova($survey_data, $selected_group_key, $selected_question_key);
    if ($anova_result && $anova_result['significance_level'] > 0) {
        $tukey_result = calculate_tukey_hsd($anova_result['groups'], $anova_result['ms_within'], $anova_result['df_within']);
    }

    if ($total_count > 20) {
        if ($factorA_key === $factorB_key) {
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

// --- グラフデータ準備 ---
$chart_labels = [];
$chart_data = [];
if ($anova_result) {
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
$interaction_plot_data = [];
if ($anova2_result) {
    foreach ($anova2_result['factorB_levels'] as $b_level) {
        $dataset = ['label' => $b_level, 'data' => []];
        foreach ($anova2_result['factorA_levels'] as $a_level) {
            $dataset['data'][] = $anova2_result['cell_stats'][$a_level][$b_level]['mean'];
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
        <div class="text-right mb-4">
            <span class="text-sm text-gray-600 mr-3">ようこそ, <?php echo htmlspecialchars($_SESSION['user']); ?> さん (権限: <?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'editor'): ?>
                <a href="view_data.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg text-sm">DB表示</a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="manage_users.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg text-sm ml-2">ユーザー管理</a>
            <?php endif; ?>
            <a href="logout.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg text-sm ml-2">ログアウト</a>
        </div>
        <h1 class="text-center text-3xl md:text-4xl font-bold text-gray-800 mb-2">分析レポート</h1>
        <p class="text-center text-gray-600 mb-6">世界遺産コンテンツに関するアンケート結果 (総回答者数: <?php echo $total_count; ?>名)</p>

        <?php if ($total_count < 20): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center">
                <p class="font-bold text-lg">まだ回答データが十分ではありません (現在 <?php echo $total_count; ?> 件)。</p>
                <p class="mt-2">詳細な分析には最低20件程度のデータが必要です。</p>
                <a href="index.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md">アンケートに回答する</a>
            </div>
        <?php else: ?>
            <div class="mb-8">
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
                        <input type="hidden" name="submit_simple" value="1">
                        <div class="flex-grow"><label for="analysis_group" class="text-sm font-medium text-gray-700">分析の軸:</label><select name="analysis_group" id="analysis_group" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($group_text_map as $key => $text): ?><option value="<?php echo $key; ?>" <?php if ($selected_group_key == $key) echo 'selected'; ?>><?php echo $text; ?></option><?php endforeach; ?></select></div>
                        <div class="flex-grow"><label for="analysis_question_simple" class="text-sm font-medium text-gray-700">分析対象の質問:</label><select name="analysis_question" id="analysis_question_simple" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($questions_text as $key => $text): ?><option value="<?php echo $key; ?>" <?php if ($selected_question_key == $key) echo 'selected'; ?>><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?></select></div>
                        <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md self-end">再分析</button>
                    </form>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-semibold text-center mb-2"><?php echo htmlspecialchars($group_text_map[$selected_group_key] ?? ''); ?>別の「<?php echo htmlspecialchars($questions_text[$selected_question_key] ?? ''); ?>」平均評価<?php if ($anova_result && $anova_result['significance_level'] > 0): ?><span class="text-red-500 font-bold text-lg"><?php echo ($anova_result['significance_level'] == 0.01) ? '**' : '*'; ?></span><?php endif; ?></h3>
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
                                <?php if ($anova_result['significance_level'] == 0.01): ?><div class="mt-4 bg-green-100 border-l-4 border-green-600 text-green-900 p-4 rounded-lg shadow">
                                        <p class="font-bold text-lg">結論: 有意差あり (p < 0.01) **</p>
                                                <p class="mt-1">F値(<?php echo round($anova_result['f_value'], 2); ?>)は1%水準の臨界値(<?php echo round($anova_result['critical_value_01'], 2); ?>)を上回り、**極めて強い統計的な有意差**が認められます。</p>
                                    </div>
                                <?php elseif ($anova_result['significance_level'] == 0.05): ?><div class="mt-4 bg-lime-100 border-l-4 border-lime-600 text-lime-900 p-4 rounded-lg shadow">
                                        <p class="font-bold text-lg">結論: 有意差あり (p < 0.05) *</p>
                                                <p class="mt-1">F値(<?php echo round($anova_result['f_value'], 2); ?>)は5%水準の臨界値(<?php echo round($anova_result['critical_value_05'], 2); ?>)を上回り、**統計的に意味のある差**があると考えられます。</p>
                                    </div>
                                <?php else: ?><div class="mt-4 bg-orange-100 border-l-4 border-orange-500 text-orange-800 p-4 rounded-lg shadow">
                                        <p class="font-bold text-lg">結論: 有意差なし (p >= 0.05)</p>
                                        <p class="mt-1">F値(<?php echo round($anova_result['f_value'], 2); ?>)は5%水準の臨界値(<?php echo round($anova_result['critical_value_05'], 2); ?>)を下回り、見られた差は**統計的に偶然の範囲**と判断されます。</p>
                                    </div><?php endif; ?>
                                <?php if (isset($tukey_result) && !empty($tukey_result)): ?><div class="mt-6 pt-4 border-t">
                                        <h3 class="font-semibold mb-2">多重比較 (テューキーのHSD法) の結果</h3>
                                        <p class="text-sm text-gray-600 mb-3">具体的にどのグループ間に差があるかを示します。</p>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm text-left text-gray-500">
                                                <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                                    <tr>
                                                        <th class="px-4 py-2">比較ペア</th>
                                                        <th class="px-4 py-2">平均値の差</th>
                                                        <th class="px-4 py-2">判定</th>
                                                    </tr>
                                                </thead>
                                                <tbody><?php foreach ($tukey_result as $result): ?><tr class="bg-white border-b">
                                                            <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($result['pair']); ?></td>
                                                            <td class="px-4 py-2"><?php echo round($result['mean_diff'], 2); ?></td>
                                                            <td class="px-4 py-2 font-bold <?php echo $result['is_significant'] ? 'text-red-500' : 'text-gray-500'; ?>"><?php echo $result['is_significant'] ? '有意差あり' : '有意差なし'; ?></td>
                                                        </tr><?php endforeach; ?></tbody>
                                            </table>
                                        </div>
                                    </div><?php endif; ?>
                            <?php else: ?><p class="text-center text-gray-500 p-4">分散分析を行うには、各グループに複数のデータが必要です。</p><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="advanced-analysis" class="tab-content <?php echo $active_tab == 'advanced' ? '' : 'hidden'; ?>">
                <div class="bg-white p-6 rounded-b-xl shadow-lg">
                    <h2 class="text-xl font-bold mb-4 text-gray-700">クロス集計と分散分析 (二元配置)</h2>
                    <form method="POST" action="analysis.php#analysis" class="bg-gray-50 p-4 rounded-lg flex flex-wrap items-center gap-4 mb-6">
                        <input type="hidden" name="submit_advanced" value="1">
                        <div class="flex-grow"><label for="factor_a" class="text-sm font-medium text-gray-700">要因A (横軸):</label><select name="factor_a" id="factor_a" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($group_text_map as $key => $text): ?><option value="<?php echo $key; ?>" <?php if ($factorA_key == $key) echo 'selected'; ?>><?php echo $text; ?></option><?php endforeach; ?></select></div>
                        <div class="flex-grow"><label for="factor_b" class="text-sm font-medium text-gray-700">要因B (凡例):</label><select name="factor_b" id="factor_b" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($group_text_map as $key => $text): ?><option value="<?php echo $key; ?>" <?php if ($factorB_key == $key) echo 'selected'; ?>><?php echo $text; ?></option><?php endforeach; ?></select></div>
                        <div class="flex-grow"><label for="analysis_question_adv" class="text-sm font-medium text-gray-700">分析対象の質問:</label><select name="analysis_question" id="analysis_question_adv" class="w-full mt-1 p-2 border border-gray-300 rounded-md"><?php foreach ($questions_text as $key => $text): ?><option value="<?php echo $key; ?>" <?php if ($selected_question_key == $key) echo 'selected'; ?>><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?></select></div>
                        <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md self-end">再分析</button>
                    </form>

                    <?php if ($anova2_result): ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div>
                                <h3 class="font-semibold text-center mb-2">交互作用プロット</h3><canvas id="interactionPlot" class="p-4"></canvas>
                                <div class="text-xs text-center text-gray-500 mt-2">※線が交差、または平行でない場合、交互作用の存在が示唆されます。</div>
                            </div>
                            <div>
                                <h3 class="font-semibold mb-2">分散分析 結果の要約</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left text-gray-500">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                            <tr>
                                                <th class="px-4 py-2">要因</th>
                                                <th class="px-4 py-2">F値</th>
                                                <th class="px-4 py-2">結果</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white border-b">
                                                <td class="px-4 py-2 font-medium">主効果: <?php echo htmlspecialchars($group_text_map[$factorA_key]); ?></td>
                                                <td class="px-4 py-2"><?php echo round($anova2_result['factor_A']['f'], 2); ?></td>
                                                <td class="px-4 py-2 font-bold <?php echo $anova2_result['factor_A']['sig'] ? 'text-red-500' : ''; ?>"><?php echo $anova2_result['factor_A']['sig'] == 0.01 ? 'p < .01 **' : ($anova2_result['factor_A']['sig'] == 0.05 ? 'p < .05 *' : '有意差なし'); ?></td>
                                            </tr>
                                            <tr class="bg-white border-b">
                                                <td class="px-4 py-2 font-medium">主効果: <?php echo htmlspecialchars($group_text_map[$factorB_key]); ?></td>
                                                <td class="px-4 py-2"><?php echo round($anova2_result['factor_B']['f'], 2); ?></td>
                                                <td class="px-4 py-2 font-bold <?php echo $anova2_result['factor_B']['sig'] ? 'text-red-500' : ''; ?>"><?php echo $anova2_result['factor_B']['sig'] == 0.01 ? 'p < .01 **' : ($anova2_result['factor_B']['sig'] == 0.05 ? 'p < .05 *' : '有意差なし'); ?></td>
                                            </tr>
                                            <tr class="bg-white border-b">
                                                <td class="px-4 py-2 font-medium">交互作用</td>
                                                <td class="px-4 py-2"><?php echo round($anova2_result['interaction']['f'], 2); ?></td>
                                                <td class="px-4 py-2 font-bold <?php echo $anova2_result['interaction']['sig'] ? 'text-red-500' : ''; ?>"><?php echo $anova2_result['interaction']['sig'] == 0.01 ? 'p < .01 **' : ($anova2_result['interaction']['sig'] == 0.05 ? 'p < .05 *' : '有意差なし'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="text-xs text-gray-500 mt-1">* p < .05, ** p < .01</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-lg shadow-md text-center">
                                <p class="font-bold">二元配置分散分析を実行できません。</p>
                                <?php if ($anova2_error_message): ?>
                                    <p class="mt-2 text-sm text-red-600 font-semibold"><?php echo htmlspecialchars($anova2_error_message); ?></p>
                                <?php else: ?>
                                    <p class="mt-2 text-sm">各グループの組み合わせに、最低2件以上のデータが必要です。データ数を増やすか、別の組み合わせをお試しください。</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                </div>
            <?php endif; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const tabs = document.querySelectorAll('.tab-button');
                    const tabContents = document.querySelectorAll('.tab-content');
                    tabs.forEach(tab => {
                        tab.addEventListener('click', () => {
                            const target = document.querySelector(tab.dataset.tabTarget);
                            tabContents.forEach(content => content.classList.add('hidden'));
                            if (target) target.classList.remove('hidden');
                            tabs.forEach(t => t.classList.remove('active'));
                            tab.classList.add('active');
                        });
                    });
                });
                <?php if ($total_count > 0): ?>
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
                    if (document.getElementById('genderChart') && <?php echo json_encode(!empty($gender_dist_counts)); ?>) {
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
                    }
                    if (document.getElementById('ageChart') && <?php echo json_encode(!empty($age_dist_counts)); ?>) {
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
                    }
                    if (document.getElementById('incomeChart') && <?php echo json_encode(!empty($income_dist_counts)); ?>) {
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
                    }
                    const radarDatasets = [{
                        label: '全体の平均評価点',
                        data: <?php echo json_encode($radar_avg_scores); ?>,
                        fill: true,
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: 'rgb(59, 130, 246)',
                        pointBackgroundColor: 'rgb(59, 130, 246)'
                    }];
                    if (document.getElementById('radarChart')) {
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
                    }
                    <?php if ($anova_result && !empty($chart_data)): ?>
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
                <?php endif; ?>
            </script>
</body>

</html>