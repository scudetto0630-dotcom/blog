# 公開サイトの日本語校正

使用するツールはtextlint、日本語文章向けの2つのプリセット、助詞の誤用・表記揺れの検査ルールです。
依存関係はアプリ本体と分け、package-lock.jsonで固定しています。公開文章を外部の校正サービスには送信しません。

```sh
cd /workspace/blog
npm ci --prefix tools/proofreading --cache /tmp/hsh-npm-cache --no-audit --no-fund
python tools/proofread-site.py --output /tmp/hsh-proofreading-current
tools/proofreading/node_modules/.bin/textlint --config tools/proofreading/.textlintrc.json --format json '/tmp/hsh-proofreading-current/*.md' > /tmp/hsh-proofreading-findings.json
```

校正ツールは、指摘がある場合に終了コード1を返します。JSONを読み、実際の問題と文体上の推奨を確認してください。
ルールを無効化して合格させないでください。修正前後は同じ抽出処理・同じ設定で比較します。
ナビゲーション、表の項目名、画像の代替テキストなどは短いラベルとして扱います。
本文と同じ句点を強制しないよう、校正用Markdownでは見出しに対応付けています。本文はそのまま検査します。
リンク前後を別の文として切り出さないこと、非表示のSNSボタンやスクリプトを本文に混ぜないことをテストしています。

公開URLからは16ページ（固定ページ5、記事4、カテゴリー5、著者一覧、検索結果）を収集します。
画像内の文字は自動で抽出できないため、実画像の目視確認と転記の校正を併用します。
正しい製品情報・実体験の確認、文章の意味・文脈・自然さは、textlintの指摘の有無だけでは判定できません。
公開前に全文を読み、公開後に再収集して修正漏れを調べます。
