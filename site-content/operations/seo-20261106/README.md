# 検索流入に向けた改稿（2026年11月6日）

サイト：https://hirogarushumi.com/ 。プラグイン1.32.0。

## 公開した変更

|ページ|候補とする検索意図|実施内容|
|---|---|---|
|cat-carrier-checklist|猫用リュックキャリーの変形・買い替え|5.5kgの猫との実体験をタイトルと冒頭に。一般的な選び方の前に体験を配置。重複説明を削減|
|glenlivet-12-first-bottles|グレンリベット12年の感想・飲みやすさ|本人の飲みやすさ・花のような香りの感想を先に。重複する節を整理し既存アンカーは保持|
|sherry-cask-notes|グレンファークラス12年の香り・感想|ぶどうパン・レーズンという本人の表現を先に。一般的なシェリーの解説と区別し、公式の用語出典を追加|
|hsh-hakushu-visit|白州蒸溜所見学の感想|2025年7月の体験と本人撮影写真を主軸に。現在の予約・営業・アクセスの公式確認先を別の節へ|
|hsh-whisky-notes#arboralis-taste|アルボラリスは甘いか・飲みやすいか|既存の感想に検索意図の見出しと安定したアンカーを追加。関連記事から内部リンク|

検索需要・競争度を確認した上での「低競争キーワード選定」ではない。本人の一次情報があり、本文が質問に答えられる候補として選んだ。検索数・順位の推定値は作っていない。

- 新しいURLへ移さず、既存のリンクを維持。
- キャリーの型番・耐荷重、飲んでいない味の感想、参加したツアー名・試飲銘柄などは補っていない。
- 白州の当日の体験（2025年7月）と、公式案内の確認日（2026年11月6日）を区別。
- 関係のない栄養用語を猫用品の記事へ一律に出す処理を整理。
- データ抽出の同じ例を仕事の体験談やCSV記事に繰り返さず、専用の記事へ集約。CSVの違いの説明はCSV記事に残した。
- 不要と指定された練習CSVの追加・案内は復活させていない。
- 公開日の維持と、既存ページの競合確認を行って更新。

## 確認できたこと

5ページともHTTP200、本文はJavaScriptなしでも配信、H1・description・canonicalがそれぞれ1つ、noindexなし、正規URLがWordPressサイトマップに掲載。robots.txtは通常の記事を拒否していない。

PHP950項目、PC・390px・320pxの15画面検証に成功。全体巡回の問題0件、孤立記事0件。これらはクロール・表示・内部リンクの確認であり、Googleへの登録を証明するものではない。

## 未完了・実測できないこと

1. **Googleへの登録状況**：こちらの接続からSearch Consoleへアクセスできず、URL検査を実施できていない。未登録と断定しない。
2. **検索順位・表示回数・検索語・CTR**：Search Consoleデータが未取込。ゼロや推定値で埋めていない。
3. **公開検索結果による競合比較**：Googleは確認画面が表示されたため突破せず終了。Bingは指定語と無関係な結果を返したため分析の根拠から除外。正確な競合順位の調査を完了したとはしていない。
4. **インデックス登録のリクエスト**：Googleアカウントの認証がないため送信していない。サイトマップの更新を、Googleへの送信・登録完了とはしていない。

したがって「上位表示した」「検索需要が確認できた」「Google未登録が原因だった」という結果は報告できない。次の改善判断には、Search Consoleの対象URLの検査結果と、ページ別・検索語別の実績が必要。

## 根拠にした公式資料

- Google検索の基本事項：https://developers.google.com/search/docs/essentials?hl=ja
- 有用で信頼できるユーザー第一のコンテンツ：https://developers.google.com/search/docs/fundamentals/creating-helpful-content?hl=ja
- 再クロールの依頼：https://developers.google.com/search/docs/crawling-indexing/ask-google-to-recrawl?hl=ja
- 白州蒸溜所：https://www.suntory.co.jp/factory/hakushu/
- オロロソ：https://www.sherry.wine/sherry-wine/dry-sherry-wines/oloroso
- ペドロ・ヒメネス：https://www.sherry.wine/sherry-wine/naturally-sweet-sherry-wine/pedro-ximenez

公開前バックアップ：`/workspace/artifacts/seo-before-20261106.zip`（非公開）。個別の候補語・検証値・検索取得制限・実測時間は同ディレクトリのJSONに保存。
