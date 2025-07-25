<?php
/**
 * セキュアなセッションを開始するためのラッパー関数
 */
function session_start_secure() {
    // セッションクッキーにHttpOnly属性とSecure属性を付与
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => isset($_SERVER['HTTPS']), // HTTPSならtrue
        'httponly' => true,                  // JavaScriptからのアクセスを禁止
        'samesite' => 'Lax'                  // CSRF対策
    ]);
    session_start();
    // 定期的にセッションIDを再生成する
    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    } elseif (time() - $_SESSION['created_at'] > 1800) { // 30分ごとにIDを更新
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
}

/**
 * HTML特殊文字をエスケープする
 * @param string|null $str エスケープする文字列
 * @return string
 */
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 指定されたURLにリダイレクトする
 * @param string $url リダイレクト先のURL
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit();
}

/**
 * CSRFトークンを生成し、セッションに保存する
 * @return string 生成されたトークン
 */
function generate_csrf_token(): string {
    // 32バイトのランダムな値を生成
    $token = bin2hex(random_bytes(32));
    // セッションにトークンを保存
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * 送信されたCSRFトークンを検証する
 * @param string|null $token POSTリクエストなどから受け取ったトークン
 * @return bool 検証結果 (true: OK, false: NG)
 */
function validate_csrf_token(?string $token): bool {
    // トークンが存在し、セッション内のトークンと一致するか確認
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    // 一度使ったトークンは削除
    unset($_SESSION['csrf_token']);
    return true;
}

/**
 * ログイン状態をチェックし、未ログインなら指定ページにリダイレクト
 * @param string $redirect_to
 */
function require_login(string $redirect_to = 'login.php'): void {
    if (!isset($_SESSION['user'])) {
        $_SESSION['error'] = 'ログインが必要です。';
        redirect($redirect_to);
    }
}

/**
 * 指定された権限を持っているかチェックし、持っていなければリダイレクト
 * @param array $allowed_roles 許可する権限の配列 (例: ['admin', 'editor'])
 * @param string $redirect_to
 */
function require_role(array $allowed_roles, string $redirect_to = 'analysis.php'): void {
    require_login(); // まずログインしているかチェック
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles, true)) {
        $_SESSION['error'] = 'このページにアクセスする権限がありません。';
        redirect($redirect_to);
    }
}
?>