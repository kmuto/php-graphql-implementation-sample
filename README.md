# Singakusha - 商品カタログ管理 API

Laravel + GraphQL (Lighthouse) による商品カタログ管理アプリケーション。
OpenTelemetry によるゼロコード計装で、トレース・メトリクス・ログを Mackerel に送信する。

## 技術スタック

| 項目 | 詳細 |
|------|------|
| 言語 | PHP 8.4 |
| フレームワーク | Laravel 13 |
| GraphQL | Lighthouse v6 |
| データベース | SQLite (Docker volume で永続化) |
| 実行環境 | Docker Compose (nginx + php-fpm) |
| ポート | 8080 |
| テレメトリ | OpenTelemetry (ゼロコード計装) |
| テレメトリ収集 | otelcol-mackerel (OpenTelemetry Collector) |

## アーキテクチャ

```
┌──────────┐   HTTP :8080   ┌──────────────┐  OTLP :4318   ┌─────────────┐        ┌──────────┐
│  Client  │ ──────────────>│  app (PHP)   │ ─────────────>│  otelcol    │ ──────>│ Mackerel │
│  (curl)  │                │  Laravel     │               │  -mackerel  │        │          │
└──────────┘                │  Lighthouse  │               └─────────────┘        └──────────┘
                            │  OTel auto   │
                            └──────────────┘
```

## セットアップ

### 前提条件

`.env` に Mackerel API キーを設定する:

```
MACKEREL_APIKEY=your_api_key_here
```

### 起動

```bash
# ビルド & 起動
docker compose up -d --build

# ログ確認
docker compose logs -f

# 停止
docker compose down

# データも含めて完全削除
docker compose down -v
```

起動時にマイグレーションとシーディング (ユーザー10件、商品20件) が自動実行される。

## コンテナ構成

| コンテナ | イメージ | 役割 |
|---------|---------|------|
| app | php:8.4-fpm (カスタム) | Laravel アプリケーション (nginx + php-fpm) |
| otelcol | ghcr.io/mackerelio/opentelemetry-collector-mackerel/otelcol-mackerel | OpenTelemetry Collector |

## GraphQL エンドポイント

```
POST http://localhost:8080/graphql
Content-Type: application/json
```

## データモデル

### User

| フィールド | 型 | 説明 |
|-----------|------|------|
| id | ID | 主キー |
| name | String | 名前 |
| email | String | メールアドレス (一意) |
| email_verified_at | DateTime | メール認証日時 |
| created_at | DateTime | 作成日時 |
| updated_at | DateTime | 更新日時 |

### Product

| フィールド | 型 | 説明 |
|-----------|------|------|
| id | ID | 主キー |
| name | String | 商品名 |
| description | String | 商品説明 (任意) |
| price | Int | 価格 (整数) |
| stock | Int | 在庫数 |
| sku | String | SKU コード (一意) |
| is_active | Boolean | 有効/無効 |
| created_at | DateTime | 作成日時 |
| updated_at | DateTime | 更新日時 |

## API 操作一覧

### Query

| 操作 | 説明 |
|------|------|
| `user(id, email)` | ユーザーを ID またはメールで取得 |
| `users(name, first)` | ユーザー一覧 (ページネーション) |
| `product(id)` | 商品を ID で取得 |
| `products(name, is_active, first)` | 商品一覧 (フィルタ・ページネーション) |

### Mutation

| 操作 | 説明 |
|------|------|
| `createUser(input)` | ユーザー作成 |
| `updateUser(id, input)` | ユーザー更新 |
| `deleteUser(id)` | ユーザー削除 |
| `createProduct(input)` | 商品作成 |
| `updateProduct(id, input)` | 商品更新 |
| `deleteProduct(id)` | 商品削除 |

## curl による操作例

以下のすべての例は `| python3 -m json.tool` でレスポンスを整形している。不要なら省略可。

### ユーザー

#### ユーザー一覧を取得

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ users(first: 5) { data { id name email } paginatorInfo { total currentPage lastPage } } }"}' \
  | python3 -m json.tool
```

#### ID でユーザーを取得

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ user(id: 1) { id name email email_verified_at created_at } }"}' \
  | python3 -m json.tool
```

#### メールアドレスでユーザーを取得

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ user(email: \"test@example.com\") { id name email } }"}' \
  | python3 -m json.tool
```

#### ユーザーを名前で検索 (部分一致)

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ users(name: \"%Test%\", first: 10) { data { id name email } } }"}' \
  | python3 -m json.tool
```

#### ユーザーを作成

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"mutation { createUser(input: { name: \"Taro Yamada\", email: \"taro@example.com\", password: \"secret123\" }) { id name email created_at } }"}' \
  | python3 -m json.tool
```

#### ユーザーを更新

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"mutation { updateUser(id: 1, input: { name: \"Updated Name\" }) { id name email updated_at } }"}' \
  | python3 -m json.tool
```

#### ユーザーを削除

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"mutation { deleteUser(id: 1) { id name } }"}' \
  | python3 -m json.tool
```

### 商品カタログ

#### 商品一覧を取得

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ products(first: 5) { data { id name description price stock sku is_active } paginatorInfo { total currentPage lastPage } } }"}' \
  | python3 -m json.tool
```

#### 2ページ目を取得

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ products(first: 5, page: 2) { data { id name price } paginatorInfo { currentPage lastPage } } }"}' \
  | python3 -m json.tool
```

#### ID で商品を取得

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ product(id: 1) { id name description price stock sku is_active created_at } }"}' \
  | python3 -m json.tool
```

#### 有効な商品のみ取得

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ products(is_active: true, first: 10) { data { id name price stock } } }"}' \
  | python3 -m json.tool
```

#### 商品名で検索 (部分一致)

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ products(name: \"%est%\", first: 10) { data { id name price } } }"}' \
  | python3 -m json.tool
```

#### 商品を作成

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"mutation { createProduct(input: { name: \"Laravel Tシャツ\", description: \"公式ロゴ入りTシャツ\", price: 3500, stock: 100, sku: \"SKU-LARAVEL-001\" }) { id name description price stock sku is_active created_at } }"}' \
  | python3 -m json.tool
```

#### 商品を更新 (価格と在庫を変更)

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"mutation { updateProduct(id: 1, input: { price: 2980, stock: 50 }) { id name price stock updated_at } }"}' \
  | python3 -m json.tool
```

#### 商品を無効化

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"mutation { updateProduct(id: 1, input: { is_active: false }) { id name is_active } }"}' \
  | python3 -m json.tool
```

#### 商品を削除

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"mutation { deleteProduct(id: 1) { id name } }"}' \
  | python3 -m json.tool
```

## OpenTelemetry (ゼロコード計装)

### 概要

PHP の opentelemetry 拡張と自動計装パッケージにより、コード変更なしでテレメトリを収集する。
収集したデータは otelcol-mackerel 経由で Mackerel に送信される。

### 自動計装の対象

| パッケージ | 計装対象 |
|-----------|---------|
| `opentelemetry-auto-laravel` | HTTP リクエスト、ルーティング、ミドルウェア |
| `opentelemetry-auto-pdo` | データベースクエリ (SQLite) |
| `opentelemetry-auto-http-async` | 外部 HTTP クライアント呼び出し |

### OTel 環境変数 (docker-compose.yml で設定)

| 変数 | 値 | 説明 |
|------|------|------|
| `OTEL_PHP_AUTOLOAD_ENABLED` | `true` | 自動計装を有効化 |
| `OTEL_SERVICE_NAME` | `sg` | Mackerel に表示されるサービス名 |
| `OTEL_TRACES_EXPORTER` | `otlp` | トレースエクスポーター |
| `OTEL_METRICS_EXPORTER` | `otlp` | メトリクスエクスポーター |
| `OTEL_LOGS_EXPORTER` | `otlp` | ログエクスポーター |
| `OTEL_EXPORTER_OTLP_PROTOCOL` | `http/protobuf` | OTLP プロトコル |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | `http://otelcol:4318` | Collector エンドポイント |
| `OTEL_PROPAGATORS` | `baggage,tracecontext` | コンテキスト伝搬方式 |

### otelcol-mackerel 設定

Collector の設定ファイルは `otelcol-config.yaml`。
デフォルトで debug exporter が有効になっており、受信したテレメトリを標準出力に出力する。

```bash
# Collector のログでテレメトリ受信を確認
docker compose logs -f otelcol
```

## GraphQL カスタム計装

### 背景と課題

PHP の OpenTelemetry ゼロコード計装 (`opentelemetry-auto-laravel`) は HTTP リクエスト単位でスパンを生成するが、GraphQL は単一エンドポイント (`POST /graphql`) で全操作を処理するため、スパン名がすべて `POST /graphql` となり、どのオペレーションが実行されたか判別できない。

この課題を解決するため、Lighthouse のイベントシステムを利用して GraphQL 固有の子スパンを生成し、セマンティック属性を付与している。

### 仕組み

`app/Listeners/GraphQLTelemetry.php` が Lighthouse の実行イベントをリッスンし、GraphQL オペレーションごとにスパンを生成する。

```
HTTP リクエスト (自動計装)
└── POST /graphql                          ← opentelemetry-auto-laravel が生成
    └── graphql query GetUsers             ← GraphQLTelemetry が生成 (子スパン)
        ├── graphql.operation.type: query
        ├── graphql.operation.name: GetUsers
        └── graphql.document: query GetUsers { ... }
```

Laravel のイベントオートディスカバリにより `app/Listeners/` 配下のリスナーは自動登録される。手動でのイベント登録は不要。

### イベントフロー

| Lighthouse イベント | タイミング | GraphQLTelemetry の処理 |
| --- | --- | --- |
| `StartExecution` | GraphQL クエリ実行開始時 | AST を解析し、子スパンを作成。オペレーション属性を付与 |
| `EndExecution` | GraphQL クエリ実行完了時 | エラーがあればスパンにエラーステータスとイベントを記録し、スパンを終了 |

### スパン属性

| 属性 | 型 | 説明 | 例 |
| --- | --- | --- | --- |
| `graphql.operation.type` | string | オペレーション種別 | `query`, `mutation` |
| `graphql.operation.name` | string | オペレーション名 (クライアントが指定した場合) | `GetUsers`, `CreateProduct` |
| `graphql.document` | string | GraphQL クエリ文字列 (2048 文字以下の場合) | `query GetUsers { users { ... } }` |

スパン名は `graphql {type} {name}` の形式で生成される (例: `graphql query GetUsers`, `graphql mutation`)。

### エラー記録

GraphQL レスポンスにエラーが含まれる場合、スパンに以下が記録される:

- スパンステータスが `ERROR` に設定される
- エラーごとに `graphql.error` イベントが追加される
  - `graphql.error.message` — エラーメッセージ
  - `graphql.error.path` — エラーが発生したフィールドパス (例: `users.0.email`)

### 設計上の判断

**親スパンの変更ではなく子スパンを作成する理由:**
Laravel の自動計装はコントローラ処理の完了後にルートスパン名を `POST /graphql` で上書きする。そのため、`StartExecution` 時に親スパンの名前や属性を変更しても最終的に失われる。独立した子スパンを作成することで、GraphQL 属性が確実に保持される。

**`static` プロパティでスパンを管理する理由:**
PHP-FPM はリクエストごとにプロセスの状態がリセットされるため、`StartExecution` で作成したスパンを `EndExecution` で参照するのに `static` プロパティを安全に使用できる。リクエスト間でスパンが漏洩することはない。

**`AppServiceProvider` でイベントリスナーを手動登録しない理由:**
Laravel 13 は `app/Listeners/` 配下のクラスを自動的にスキャンし、メソッドの型ヒントからイベントとリスナーを対応付ける (イベントオートディスカバリ)。`AppServiceProvider::boot()` で `Event::listen()` を併用すると同じリスナーが二重登録され、スパンが 2 回作成される不具合を引き起こす。リスナーを `app/Listeners/` に配置する場合、手動登録は行わないこと。

## テールベースサンプリング

### サンプリングの概要

OpenTelemetry Collector (`otelcol-config.yaml`) でテールベースサンプリングを実施する。ヘッドベースサンプリング (アプリ側で送信前に判断) と異なり、トレース全体のスパンが揃った後に属性に基づいて取捨選択を行う。これにより `graphql.operation.name` などの属性を使ったサンプリングルールが可能になる。

### サンプリングポリシー

| ポリシー名 | 条件 | サンプリング率 | 用途 |
| --- | --- | --- | --- |
| `errors-always` | スパンステータスが `ERROR` | 100% | 障害調査のためエラーは全件保持 |
| `mutations-always` | `graphql.operation.type` = `mutation` | 100% | データ変更操作は全件保持 |
| `get-users-10pct` | `graphql.operation.name` = `GetUsers` | 10% | 高頻度クエリはサンプリングでコスト削減 |
| `default-50pct` | (条件なし) | 50% | 上記に該当しないトレースのデフォルト |

### ポリシー評価の注意点

ポリシーは **OR 評価**される。いずれか 1 つのポリシーが「保持」と判定すればトレースは残る。

```
トレース → errors-always?  → YES → 保持
              ↓ NO
           mutations-always? → YES → 保持
              ↓ NO
           get-users-10pct?  → YES (10%の確率) → 保持
              ↓ NO
           default-50pct?    → YES (50%の確率) → 保持
              ↓ NO
           → 破棄
```

この仕組みにより、`get-users-10pct` で 10% サンプリングとしても、同じトレースが `default-50pct` (50%) に該当して保持される可能性がある。`GetUsers` の実効サンプリング率は 10% + (90% × 50%) = **55%** となる。

厳密にレートを分離するには、`composite` ポリシータイプで排他的な評価順序を定義するか、`default-50pct` 側に特定オペレーションを除外する条件を追加する必要がある。

### カスタマイズ例

特定のオペレーションのサンプリング率を変更:

```yaml
# query GetProduct を 5% サンプリングに追加
- name: get-product-5pct
  type: and
  and:
    and_sub_policy:
      - name: match-op
        type: string_attribute
        string_attribute:
          key: graphql.operation.name
          values: [GetProduct]
      - name: sample-5pct
        type: probabilistic
        probabilistic:
          sampling_percentage: 5
```

## ディレクトリ構成 (主要ファイル)

```
.
├── app/Models/
│   ├── Product.php              # 商品モデル
│   └── User.php                 # ユーザーモデル
├── database/
│   ├── factories/
│   │   ├── ProductFactory.php
│   │   └── UserFactory.php
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   └── 2026_09_07_000001_create_products_table.php
│   └── seeders/
│       └── DatabaseSeeder.php
├── graphql/
│   └── schema.graphql           # GraphQL スキーマ定義
├── docker-compose.yml           # コンテナ構成 (app + otelcol)
├── Dockerfile                   # PHP 8.4 + opentelemetry 拡張
├── docker-entrypoint.sh         # 起動時の DB 初期化・マイグレーション
├── nginx.conf
├── otelcol-config.yaml          # OpenTelemetry Collector 設定
└── supervisord.conf
```

## 開発用コマンド

```bash
# コンテナ内でコマンド実行
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan tinker

# GraphQL スキーマの変更後、キャッシュをクリア
docker compose exec app php artisan lighthouse:clear-cache

# OTel 拡張が有効か確認
docker compose exec app php -m | grep opentelemetry

# Collector のテレメトリ受信ログを確認
docker compose logs -f otelcol
```
