# 専用編集接続（1.1.0）

## 一度だけ必要な操作

1. `hirogaru-home.zip` をWordPressへアップロードし、現在のプラグインを置き換える。
2. 「外観 > ひろがるトップページ」で「接続キーを発行・再発行」を押す。
3. キーをCodexクラウド環境設定の **Secrets / 秘密情報** にある `HSH_EDIT_TOKEN` に登録する。チャット・GitHub・スクリーンショットには含めない。
4. 環境設定の変更を保存・公開し、実行環境に反映する。
5. キーの値ではなく、設定完了の旨だけをチャットで知らせる。

キーはWordPressの画面に一度だけ表示します。WordPressにはSHA-256ハッシュのみを保存します。
キーが分からなくなった場合は再発行し、環境側も更新してください。
停止は同じ画面の「編集接続を無効にする」。プラグイン無効化でもキーを無効化します。

## 権限

- トップページの見出し3行、紹介文、プロフィール、背景・文字・ボタン色、空の記事一覧の非表示。
- この接続が新規作成する運営者情報・編集方針・お問い合わせの固定ページ。初期値は下書き。
- 公開済みの専用固定ページはトップページのフッターにリンク。
- 既存記事、既存ページ、ユーザー、テーマ、プラグイン、PHPコード、任意ファイルは変更不可。
- プラグインの機能追加・PHP更新には引き続き管理画面でのZIP更新が必要。この接続でのコード更新は意図的に提供しない。

## 開発側の使用

既存チェックアウト `/workspace/blog` を使う。ユーザーから指示がない限りGit worktreeを作らない。
キーの名前・存在だけを確認し、値は出力しない。プロキシのプレースホルダーでも勝手に無効なキーと判断しない。

```sh
cd /workspace/blog
python tools/wordpress-edit.py status
```

接続確認成功後、ユーザーが依頼した変更だけを行う。
POSTは `python tools/wordpress-edit.py home --payload /tmp/home.json` または `page --payload /tmp/page.json`。
RESTの通常URLが利用できない場合は `--query-route` でWordPress標準のクエリ形式を確認。
AuthorizationヘッダーにBearerトークンを直接渡す。秘密情報はこの宛先のHTTPSプロキシで置換される。
リダイレクト先へ認証情報は転送しない。TLS検証は無効化しない。
タイムアウト後は状態を確認してから再試行する。

homepage payload: heading_1 / heading_2 / heading_3 / intro / about / background / text_color / accent / hide_empty。
色は #RRGGBB、hide_empty はJSON boolean、about のHTMLはWordPressの wp_kses_post で制限。
page payload: key (about/editorial/contact), title, content, status (draft/publish)。

## 検証

- PHP 7.4 構文解析。
- PHP-WASMで認証・失効・入力検査・既存ページ保護・再試行・キャッシュ制御の単体テスト（WordPress APIはスタブ）。
- 実WordPressでの接続・保存・表示確認は、プラグイン更新とキー登録後に行う。
- サーバーのWAFやセキュリティプラグインがこのRESTルートを拒否する場合は、そのルートに限定した設定調整が必要。サイト全体の保護を無効にしない。
