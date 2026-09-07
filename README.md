# Singakusha - 商品カタログ管理 API

Laravel + GraphQL (Lighthouse) による商品カタログ管理アプリケーション。

## 技術スタック

| 項目 | 詳細 |
|------|------|
| 言語 | PHP 8.4 |
| フレームワーク | Laravel 13 |
| GraphQL | Lighthouse v6 |
| データベース | SQLite (Docker volume で永続化) |
| 実行環境 | Docker Compose (nginx + php-fpm) |
| ポート | 8080 |

## セットアップ

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

## ディレクトリ構成 (主要ファイル)

```
.
├── app/Models/
│   ├── Product.php          # 商品モデル
│   └── User.php             # ユーザーモデル
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
│   └── schema.graphql       # GraphQL スキーマ定義
├── docker-compose.yml
├── Dockerfile
├── docker-entrypoint.sh
├── nginx.conf
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
```
