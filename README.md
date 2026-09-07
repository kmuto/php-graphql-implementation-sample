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
