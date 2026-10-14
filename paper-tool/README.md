# PaperDesk — NU SKIN 学術論文整理ツール

最大5成分の入力、製品の保存・編集・削除、7工程のデモ、履歴、成分別・ご指定51分類別の結果画面を実装した日本語Webアプリです。

**現在の公開設定は無料の静的サイトです。有料サーバー・ディスク・カード登録用設定はありません。** Renderの無料枠の利用上限は適用されます。実際のPubMed検索、AI仕分け、独立した再チェック、論文結果ZIPは未実装です。

## 提供された実データを使う

左の **「提供データを使う」** から、受け取ったZIP/TXTをまとめて読み込めます。実論文の検索、人間版との比較、51分類の整理結果ZIPを利用できます。資料は外部へ送らずブラウザ内で保存します。これは新規のPubMed検索やAI判定を実行する機能とは別です。[詳細](docs/REAL_DATA.md)

## 無料版の保存とデモ処理

- 製品・履歴・デモ結果は、利用者のブラウザのIndexedDBに保存。
- 同じURL・同じブラウザであれば、更新やブラウザ終了後も保存データを再利用できます。
- 端末間の共有はありません。ブラウザのデータ削除、プライベートモードの終了、公開URLの変更で引き継がれない場合があります。
- 「保存した製品」の **バックアップを保存 / バックアップから復元** でJSONのバックアップを利用できます。
- デモは進捗画面を開いている間に進みます。閉じた場合は履歴から続きの工程を再開します。
- 保存容量や保存領域の制限で失敗した場合は、エラーを表示して入力を残します。
- バックアップ復元は現在の製品・履歴を置き換えます。内容を検証してから原子的に復元し、失敗時は変更を取り消します。
- 論文の判定と2回のチェックは固定デモです。PubMedリンクは成分の検索ページで、実在する論文のPMIDを捏造しません。

## 無料版を手元で起動

```sh
cd /workspace/blog/paper-tool
python tools/build_static.py
python -m http.server 8001 --bind 127.0.0.1 --directory build
```

起動したパソコンのブラウザで `http://127.0.0.1:8001` を開きます。これは外部公開URLではありません。

## Renderで無料公開

1. Render「New Blueprint Instance」で `scudetto0630-dotcom/blog` を選ぶ。
2. Branchは `main`、Blueprint Pathは `render.yaml`。
3. 内容が **Static Site / runtime static** であることを確認。有料のStarterやDiskが表示される場合は、古い設定なので画面を再読み込み。
4. 内容を確認してDeploy / Apply。
5. 公開されたHTTPS URLを開く。無料版にはログイン画面・共有パスワード・APIキーはありません。
6. 製品を保存→ページ更新→デモ→結果→バックアップを確認。

公開設定はリポジトリのルート `render.yaml` にあります。`rootDir: paper-tool`、ビルド `python3 tools/build_static.py`、配信先 `build`。Pythonはビルド時だけ使用し、公開後はサーバーを稼働させません。

## 画面

[PC入力画面](docs/images/desktop-input.png) / [スマホ入力画面](docs/images/mobile-input.png)

## テスト

```sh
python -m unittest discover -s tests -p 'test_*.py' -v
node tests/browser.cjs
node tests/static-browser.cjs
```

ブラウザテストはPlaywrightとChromiumが必要です。必要なら `npm install --prefix /tmp/paperdesk-tests --cache /tmp/paperdesk-npm-cache playwright`、`NODE_PATH=/tmp/paperdesk-tests/node_modules` を設定。`CHROMIUM_PATH` で実行ファイルを指定できます。

無料版はIndexedDBを使う実ブラウザ操作、保存失敗、バックアップ復元、複数画面幅を検証。既存のサーバー版も回帰テストを継続しています。詳細は [docs/TEST_REPORT.md](docs/TEST_REPORT.md)。

## 次段階のために残しているサーバー版

`python server.py` でPython + SQLiteのサーバー版を起動できます。これは無料の静的サイト公開設定では使いません。サーバー版はブラウザを閉じても処理を継続し、サーバー再起動後は工程から再開します。

サーバー版のDBは `data/paperdesk.sqlite3`。`python tools/backup.py` でバックアップできます。外部公開時は12文字以上の `APP_ACCESS_PASSWORD` と永続保存先が必要です。

将来のPubMed・AIエンジンはサーバー側へ接続し、APIキーをブラウザへ埋め込みません。その段階の保存・実行環境・費用は別途設計します。

- `app/static/browser-store.js`: 無料版の保存・デモ・バックアップ。
- `tools/build_static.py`: 正式51分類と固定デモを含む静的サイトのビルド。
- `app/static/app.js`: 共通UIとデータ管理アダプターの切り替え。
- `app/models.py`, `app/store.py`, `app/worker.py`: 次段階用の入力・サーバー保存・デモワーカー。

既存WordPressファイルやその認証キーは変更・利用していません。
