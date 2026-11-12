# Search Consoleの接続準備

現状：本人はGoogle Cloud未利用。アカウント作成・本人の権限設定はログイン中の本人が操作する。こちらはサイト専用・読み取り専用の取得コードと私有レポート保存先まで準備済み。まだ実接続・自動取得は開始していない。

## 最初に行うこと

1. [Google Cloudのプロジェクト作成](https://console.cloud.google.com/projectcreate)を、Search Consoleを使っているGoogleアカウントで開く。
2. プロジェクト名を hirogaru-search などにして作成する。表示されるプロジェクトIDはGoogleが決めるので、そのままでよい。
3. 作成したプロジェクトを選び、[Search Console API](https://console.cloud.google.com/apis/library/searchconsole.googleapis.com)を有効にする。
4. [サービスアカウント](https://console.cloud.google.com/iam-admin/serviceaccounts)で「サービスアカウントを作成」。名前は blog-search-reader など。Google Cloudのプロジェクトに対する管理用ロールの付与は、今回のSearch Consoleの閲覧用としては設定しない。
5. 表示されたサービスアカウントのメールアドレス（末尾が iam.gserviceaccount.com）を控える。これは公開用のアカウント識別子。鍵やパスワードとは別。
6. Search Consoleの https://hirogarushumi.com/ を選び、「設定→ユーザーと権限→ユーザーを追加」。このメールアドレスを閲覧用の「制限付き」で追加する。

ここまでの画面を確認してから、資格情報の安全な登録を進める。JSON鍵の中身やGoogleのパスワード・認証コードをチャットに貼らない。

## その後の資格情報

サービスアカウントのJSON資格情報を安全に使える環境設定へ GSC_SERVICE_ACCOUNT_JSON として登録する。ローカル署名に使うため、単なるHTTPヘッダー置換用の秘密情報ではなく、JSONとしてクライアントが読み込める設定が必要。登録方法は環境の実際の設定画面で確認する。安全に登録できることを確認するまで鍵ファイルをチャットにアップロードしない。

取得時の接続先は oauth2.googleapis.com と www.googleapis.com。ネットワーク許可と秘密情報の適用範囲に、この2ホストを追加する必要がある。既存のブログ用トークンや既存許可ホストは保持する。コードはWordPressの編集キーをGoogleへ送らない。

準備確認：

    .private/search-venv/bin/python tools/search-console-sync.py --status

設定後の実接続：

    .private/search-venv/bin/python tools/search-console-sync.py

出力は .private/search-console/latest.json。所有者だけが読めるモードで保存し、Git・公開ページ・公開Actions artifactへ検索語の生データを入れない。現在は資格情報がなく、状態は未接続。定期実行は接続検証後に設定する。

## 得られる分析と限界

確定データだけを取得し、ページ・検索語・端末・国を同じ行で取得する。同じ28日ずつの比較期間がそろわなければCTRの比較候補を出さない。APIが返さない日を0で補わない。複数ページに出る検索語は競合の確認候補であり、自動で統合しない。100表示の条件は検討の入口で、統計的有意差を意味しない。

公式資料の確認日：2026年11月13日

- [Search Consoleの認証と読み取り専用スコープ](https://developers.google.com/webmaster-tools/v1/how-tos/authorizing)
- [検索データの取得方法・次元・制限](https://developers.google.com/webmaster-tools/v1/searchanalytics/query)
- [Search Consoleのユーザー権限](https://support.google.com/webmasters/answer/7687615?hl=ja)
- [プロジェクトの作成](https://cloud.google.com/resource-manager/docs/creating-managing-projects)
- [サービスアカウントの作成](https://cloud.google.com/iam/docs/service-accounts-create)
