# PHP製 アンケート＆自動統計分析アプリケーション

このプロジェクトは、ユーザーからアンケートを収集し、その結果をリアルタイムで統計分析・可視化するWebアプリケーションです。

アンケートフォーム(`index.php`)から送信された回答はMySQLデータベースに保存されます。回答後、ユーザーは全体の集計データと自身の回答を比較できる分析レポートページ(`analysis.php`)にリダイレクトされ、自身の立ち位置を視覚的に把握できます。

## ✨ 主な機能

  * **アンケートフォーム**: 質問への回答と回答者の属性（年齢、性別など）を収集します。
  * **データベース連携**: `submit.php`がフォームデータを受け取り、バリデーションとサニタイズを行った後、PDOのプリペアドステートメントを用いて安全にMySQLデータベースへ保存します。
  * **リアルタイム分析レポート**:
      * **全体集計**: 回答者数や属性（性別、年代、年収層）の分布を円グラフで可視化します。
      * **レーダーチャート**: 全回答の平均スコアと自分のスコアを重ねて表示し、傾向を比較できます。
      * **統計分析**:
          * **一元配置分散分析 (ANOVA)**: 特定の属性（例: 年代）が回答に有意な差を与えるかを検定します。
          * **多重比較 (テューキーのHSD法)**: 有意差があった場合に、具体的にどのグループ間に差があるかを明らかにします。
          * **二元配置分散分析 (ANOVA)**: 2つの属性（例: 年代と性別）が回答に与える影響や、その交互作用を分析します。
  * **動的グラフ生成**: UIには[Chart.js](https://www.chartjs.org/)を利用し、分析結果をインタラクティブなグラフとして描画します。
  * **レスポンシブデザイン**: [Tailwind CSS](https://tailwindcss.com/)を使用し、PCでもスマートフォンでも見やすいレイアウトになっています。

## 🛠️ 使用技術

  * **バックエンド**: PHP
  * **データベース**: MySQL
  * **フロントエンド**: HTML, JavaScript, Tailwind CSS
  * **ライブラリ**: Chart.js

## 🚀 セットアップ方法

1.  **リポジトリの配置**:
    サーバーの公開ディレクトリ（`public_html`など）に、このリポジトリのファイルをすべてアップロードします。

2.  **データベースの準備**:
    サーバーの管理ツール（例: phpMyAdmin）を使い、MySQLデータベースと、そのデータベースにアクセスするためのユーザーを作成します。

3.  **テーブルの作成**:
    作成したデータベースに、以下のSQLクエリを実行して`survey_db`テーブルを作成します。

    ```sql
    CREATE TABLE `survey_db` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `age` int(11) NOT NULL,
      `gender` varchar(10) NOT NULL,
      `income` int(11) NOT NULL,
      `disability` varchar(10) NOT NULL,
      `q1` int(11) NOT NULL,
      `q2` int(11) NOT NULL,
      `q3` int(11) NOT NULL,
      `q4` int(11) NOT NULL,
      `q5` int(11) NOT NULL,
      `q6` int(11) NOT NULL,
      `q7` int(11) NOT NULL,
      `q8` int(11) NOT NULL,
      `q9` int(11) NOT NULL,
      `q10` int(11) NOT NULL,
      `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ```

4.  **データベース接続設定**:
    `db_connect.php`ファイルを開き、ご自身の環境に合わせてデータベースの接続情報を書き換えます。

    ```php
    // ご自身のサーバー情報に書き換えてください
    define('DB_HOST', 'mysqlXXX.db.sakura.ne.jp');
    define('DB_NAME', 'your_database_name');
    define('DB_USER', 'your_username');
    define('DB_PASS', 'your_password');
    ```

5.  **PHPバージョンの確認**:
    サーバーのPHPバージョンが**7.4以上**であることを確認してください。

6.  **動作確認**:
    ブラウザで`index.php`にアクセスし、アンケートフォームが表示されればセットアップは完了です。

## 📂 ファイル構成

```
.
├── index.php         # アンケートフォーム画面
├── submit.php        # フォームデータ処理・DB登録
├── analysis.php      # 分析レポート画面
├── db_connect.php    # DB接続情報
└── .gitignore        # Git追跡除外設定
```