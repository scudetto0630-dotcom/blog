# A8案件の記事と掲載管理

受領24件。犬向け2件・ジュエリー1件・人用健康食品1件・ベビー用品1件を除外し、対象19件を公開済み。今回、接続制限で保留していた6件の調査・執筆・公開と、既存記事の商品画像の配置を完了した。対象案件に保留は残っていない。37件予定のうち未受領13件は作成対象に含めていない。

## 接続復旧後の反映

公開版1.27.1。環境設定の公開後、公式ページ19件と、元の画像候補38点すべての取得が成功。公式情報を直接読んで保留6件を新規作成した。新記事は約1,500字、5節と確認表。既存13記事のタイトルと本文は変更せず保持した。

本文に合計39点の公式商品・サービス画像を配置。各記事2点以上（和漢みらいは3点）。表紙は19種類すべて、提供されたまんもすの写真を元に生成した合成画像。本文の商品画像と区別して管理し、実際に使った写真や使用経験は作っていない。写真下に提供写真・AI生成などの定型ラベルは表示しない。

毎日美肌は、広告リンク先のPS-B1＆ローヤルゼリーのパッケージ・説明画像を使用。別処方のコラーゲンのパッケージを採用しない。公式素材も所有者撮影とは扱わず、管理画面に「公式商品・サービス画像」「広告主公式素材」の区分を追加した。

A8の元URLと受領した計測画像を保持。リンクにはnofollow/sponsoredを指定。公開検証では広告をクリックせず、外部計測を遮断する。公開記事の冒頭は広告表示のみ。未使用という定型文は置かず、購入・使用・効果の体験談も創作しない。

## ファイル

- 掲載管理.csv：24行。元URL、掲載先、実際の公開日時、画像数、出典確認状況を管理。UTF-8 BOM、スプレッドシートへ取り込み可能。
- links.json：元のA8コード・遷移先・管理IDを保持した機械可読の管理表。
- 記事/：公開中19記事の原稿。
- source-recovery-report.json：直接取得成功の記録。以前の資料ZIPの確認記録はresearch.jsonに残す。
- source-recovery/：新規6記事で使用した公式ページ保存データ。
- recovered-image-review.json：掲載した公式画像39点の出典・SHA256・目視確認記録。
- cover-generation.json：19枚の合成表紙と元写真の記録。
- recovery-verification.json：今回の最終確認。以前のverification、rewrite-verification、product-image-verificationは当時の履歴。
- 構成案/：以前の保留時点の検討メモ。現在の公開原稿は記事/を参照。

非公開管理画面にも掲載先・元リンク・画像の出典・表紙の元写真を記録。管理ファイルや取得HTMLを公開プラグインへ同梱しない。自動生成の画像取得コードは復旧前の補助手段として残すが、今回は使用者によるZIP作成を必要としなかった。

## 掲載一覧

| 記事 | 公開日（WordPress記録・日本時間） | 本文画像 |
|---|---|---|
| [パウズグリーンデリの猫用お試しセット。主食とトッピング、どちらを選ぶ？](https://hirogarushumi.com/paws-green-deli-cat-food/) | 2026-11-04 | 2点 |
| [HARIOの猫用食器「にゃんプレトール」。大きさと手入れで選びたい](https://hirogarushumi.com/hario-cat-bowl-guide/) | 2026-11-04 | 2点 |
| [和漢みらいのキャットフードは3タイプ。低たんぱく・バランス・高エナジーの違いを見る](https://hirogarushumi.com/mirai-cat-food-types/) | 2026-11-04 | 3点 |
| [M&Co.の猫モチーフ雑貨。ニットバッグとチャームを見てみる](https://hirogarushumi.com/m-and-cat-knit-bag/) | 2026-11-04 | 2点 |
| [猫の葬儀、何を決めればいい？ ペット葬儀110番の相談前に](https://hirogarushumi.com/pet-sougi-110-cat-guide/) | 2026-11-04 | 2点 |
| [猫の写真がフィギュアに。digxipopの作り方と注文前の確認点](https://hirogarushumi.com/digxipop-cat-figure/) | 2026-11-04 | 2点 |
| [ペットパラダイスの猫おもちゃ。洋ナシのけりぐるみと、かもめの猫じゃらしが気になる](https://hirogarushumi.com/pet-paradise-cat-toys/) | 2026-11-05 | 2点 |
| [カナガンデンタルはどんなフード？ 海藻成分と、選ぶ前に知っておきたいこと](https://hirogarushumi.com/canagan-dental-cat-food/) | 2026-11-05 | 2点 |
| [カナガンのチキンとサーモン、何が違う？ 原材料とカロリーを比べてみた](https://hirogarushumi.com/canagan-chicken-salmon/) | 2026-11-05 | 2点 |
| [マックアダムズの猫用フード。チキン＆ターキーと、オーブンベイクの特徴を見る](https://hirogarushumi.com/mcadams-cat-chicken-turkey/) | 2026-11-05 | 2点 |
| [エッセンシャルの猫用フードを探すなら。ジャガーの原材料と粒の大きさを確認](https://hirogarushumi.com/essential-jaguar-cat-food/) | 2026-11-05 | 2点 |
| [モグニャンは白身魚65%。粒の大きさと、切り替え方を見ておきたい](https://hirogarushumi.com/mognyan-cat-food-guide/) | 2026-11-05 | 2点 |
| [猫用「毎日健心」はどんなサプリ？ 成分と与え方の確認ポイント](https://hirogarushumi.com/withpety-nekokenshin/) | 2026-11-04 | 2点 |
| [猫の遺伝子検査Pontely。3つのプランと検査の流れを知っておく](https://hirogarushumi.com/pontely-cat-dna-test/) | 2026-11-04 | 2点 |
| [猫用「毎日美肌」の成分は？ 毛と皮膚のケアを考えるときに](https://hirogarushumi.com/withpety-nekobihada/) | 2026-11-04 | 2点 |
| [おねこさまのキャットタワー、置き場所と猫の動きから選ぶ](https://hirogarushumi.com/onekosama-cat-tower/) | 2026-11-04 | 2点 |
| [猫用「毎日良肝」は肝臓ケア向け。成分と購入前の確認点](https://hirogarushumi.com/withpety-nekoryoukan/) | 2026-11-04 | 2点 |
| [CatlogとCatlog Boardの違いは？ 首輪で行動、トイレで体重を記録](https://hirogarushumi.com/catlog-cat-monitor/) | 2026-11-04 | 2点 |
| [猫用「毎日一緒」はシニアケア向け。DHA・EPAと毎日の見守り](https://hirogarushumi.com/withpety-nekoissho/) | 2026-11-04 | 2点 |

検証：PHP825項目・Python21件、公開前76件の画面確認。公開後も76件の画面確認と、全46ページの監査を通過（問題0件）。19件の元広告URLと計測画像の一致、管理画面への保存も検証済み。詳細は recovery-verification.json を参照。校正ツールの指摘は確認したが、会話調や製品固有名に対する指摘もあり、無指摘を意味するものではない。
