# Search Consoleの接続準備

現在の状態（2026-11-14）：接続済み。本人がGSC_SERVICE_ACCOUNT_JSONをGitHub ActionsのRepository secretへ登録し、Google認証・確定データ取得・WordPressへの非公開保存まで成功した。毎日6:30 JSTのスケジュールで動く（GitHub側の実行待ちで遅れることがある）。鍵を環境blogへ登録し直す必要はない。

Codexからの確認・取得：

    mkdir -p .private/search-console
    python3 tools/wordpress-edit.py search-report > .private/search-console/from-blog.json
    chmod 600 .private/search-console/from-blog.json

これは既存のHSH_EDIT_TOKENで非公開の取得結果を読む操作。検索語や実績の生データを公開リポジトリ・公開artifactへ保存しない。接続状況はreader-statusのsearch_consoleと、Search Console read-only collectionの実行結果でも確認できる。ローカル環境にGSC_SERVICE_ACCOUNT_JSONがないことだけで「未接続」と判断しない。

以下は初回設定・鍵を作り直す場合の手順の記録。すでに完了している操作を本人へ繰り返し依頼しない。

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

出力は .private/search-console/latest.json。所有者だけが読めるモードで保存し、Git・公開ページ・公開Actions artifactへ検索語の生データを入れない。このローカルクライアントの資格情報確認は、GitHub Actionsでの接続状態とは別。現在はActionsからの非公開配信が動いている。

## 得られる分析と限界

確定データだけを取得し、ページ・検索語・端末・国を同じ行で取得する。同じ28日ずつの比較期間がそろわなければCTRの比較候補を出さない。APIが返さない日を0で補わない。複数ページに出る検索語は競合の確認候補であり、自動で統合しない。100表示の条件は検討の入口で、統計的有意差を意味しない。

公式資料の確認日：2026年11月13日

- [Search Consoleの認証と読み取り専用スコープ](https://developers.google.com/webmaster-tools/v1/how-tos/authorizing)
- [検索データの取得方法・次元・制限](https://developers.google.com/webmaster-tools/v1/searchanalytics/query)
- [Search Consoleのユーザー権限](https://support.google.com/webmasters/answer/7687615?hl=ja)
- [プロジェクトの作成](https://cloud.google.com/resource-manager/docs/creating-managing-projects)
- [サービスアカウントの作成](https://cloud.google.com/iam/docs/service-accounts-create)

## 追記：GitHub Actionsでの接続経路

本人から、プロジェクトhirogaru-search、API有効化、サービスアカウント blog-search-reader@hirogaru-search.iam.gserviceaccount.com の作成とSearch Consoleへの制限付き追加が完了したと回答あり。当時はAPI接続が未検証だったが、2026-11-14に認証・取得・非公開保存まで確認済み。

環境blogではJSONのローカル署名に使える秘密情報の登録方法を確認できていないため、GitHub ActionsのRepository secretを使う接続経路を追加した。

1. Google Cloudのサービスアカウント詳細→鍵→鍵を追加→新しい鍵を作成→JSONで端末へダウンロードする。チャットやGitには送らない。
2. https://github.com/scudetto0630-dotcom/blog/settings/secrets/actions/new で、名前 GSC_SERVICE_ACCOUNT_JSON、Secret欄には端末で開いたJSONファイルの全文を入れ、保存する。
3. Actions→Search Console read-only collection→Run workflowで初回の認証付き読み取りを確認する。毎日6:30 JSTにも動く。秘密情報が未登録なら接続せずにスキップする。
4. 公開ログと要約には認証結果と確定データの有無だけを出す。秘密鍵、検索語、指標の生データは出力しない。生データのartifactは作成せず、一時ファイルを終了時に削除する。この初期方式は、現在はWordPressへの署名付き非公開保存に置き換わっている。最新の取得経路は冒頭の接続済み手順を使う。

この方式ではGoogleの署名処理はGitHub runner内で行われ、環境blogへ秘密鍵を登録しない。鍵が失われた場合はGoogle Cloud側で対象の鍵を削除して失効させる。
