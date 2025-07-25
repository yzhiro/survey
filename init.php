<?php
// アプリケーションの基本設定と初期化

// エラーを画面に表示せず、ログに出力する本番環境向け設定 (開発中は E_ALL を表示しても良い)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// error_log('/path/to/your/php-error.log'); // 実際にはログファイルのパスを指定する

// 文字コード設定
header('Content-Type: text/html; charset=UTF-8');

// 共通の関数ファイルを読み込む
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

// セッションを開始する
session_start_secure();
?>