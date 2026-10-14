# PaperDesk 成分・論文整理ツール

既存の製品・成分入力画面から、提供済みのEGCG・カテキン論文と人間版の分類を確認し、ZIPを保存できます。利用者に資料アップロードやAPI設定を求めません。

**新規PubMed検索・AI仕分け・独立再チェックを公開運用する完成版にはまだ達していません。** 現在利用できるのは提供資料に基づく結果です。未対応成分は架空結果を出さず停止します。

詳細は [実データと本番エンジンの状況](docs/REAL_DATA.md)、検証は [テスト報告](docs/TEST_REPORT.md)。

## 起動

```sh
cd /workspace/blog/paper-tool
python tools/build_static.py
python -m http.server 8001 --bind 127.0.0.1 --directory build
```

公開済みRender静的サイトは `main` 更新から既存の `render.yaml` でビルドします。有料サーバーやディスクは追加していません。
製品と履歴はブラウザ内保存で、端末間の共有や閉じた後のサーバー処理はありません。

## 検証

```sh
python -m unittest discover -s tests -p 'test_*.py' -v
node tests/browser.cjs
node tests/static-browser.cjs
python tools/build_static.py
node tests/input-research-browser.cjs
node tests/research-browser.cjs
node tests/live-browser.cjs
```

PlaywrightとChromiumが必要です。現在の開発環境では `/usr/bin/chromium` を利用します。
資料インポートの互換性テストは添付資料が存在する場合のみ実行できます。

`python server.py` は既定では旧デモ用Python/SQLiteサーバーです。`PAPERDESK_RESEARCH_ENGINE=1` で本番ジョブ用APIと画面を有効にできます。公開静的サイトには適用されません。承認・キー・単価と上限がないとAI処理は停止します。既存WordPressファイルやその認証キーは変更していません。
