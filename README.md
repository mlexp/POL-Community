# POL Community

POL Communityは、PlayOnlineのメンバー向けコミュニティサイトです。主にFINAL FANTASY XI（FF11）のリンクシェルメンバー同士が、キャラクター情報、コミュニティ（掲示板）、日記などを通じて交流できる場を提供します。

Laravel 13、Livewire 4、Tailwind CSSで構築されています。

## 利用上の注意

`public/assets/ffxi`に含まれる画像は、FINAL FANTASY XIの著作物であり、商用・営利目的では利用できません。そのため、本アプリケーションは基本的に非商用での利用を想定しています。

本アプリケーションを商用・営利目的で利用する場合は、`public/assets/ffxi`内の画像を、商用利用可能な画像へ差し替えてください。詳細は、スクウェア・エニックスの[ファイナルファンタジーXI 著作物利用許諾条件](https://support.jp.square-enix.com/rule.php?id=11&la=0&tag=authc)を確認してください。

また、管理者ページの「サイト管理」→「サイト設定」で設定するフッターには、`(C) SQUARE ENIX`の表記を含めてください。

## 動作要件

- PHP 8.3
- MySQL 8.0
- Apache HTTP Server 2.4
- Composer（配布物の作成時に使用）
- Node.js / npm（フロントエンド資産のビルド時に使用）

さくらインターネットのレンタルサーバーで動作確認済みです。本番サーバーにComposerやNode.jsがない場合は、開発環境で依存関係とフロントエンド資産を含む配布物を作成します。

## 主な機能

- トップページにサイトの更新情報、コミュニティと日記の新着情報、外部RSSを表示
- ログインユーザー向けマイページ
- キャラクターの登録・更新・検索、ジョブレベルや合成スキルなどのプロフィール管理
- コミュニティ（掲示板）のスレッド・コメント投稿、更新、検索
- 日記・日記コメントの投稿、更新、検索
- 画像のアップロード、外部画像、YouTube・ニコニコ動画の埋め込み
- 公開、メンバー限定、非公開などの公開範囲とアクセス制御
- メールアドレスまたはログインIDによる認証、パスワード再設定、パスキー、二要素認証
- サイト設定、ユーザー、投稿、カテゴリ、更新情報、RSS、バナー、画像などの管理者向け管理機能
- HTMLサニタイズ、CSRF対策、レート制限、画像認可、RSS取得時のSSRF対策などのセキュリティ機能
- Laravel Schedulerによる外部RSSの定期取得

## 新規配備

以下は、新しい環境へ配備して動作させるための最低限の手順です。実際の本番更新、切り替え、ロールバックについては[リリースマニュアル](release-manual.md)を参照してください。

### 1. 配布物を作成する

開発環境でリポジトリを取得し、PHP依存関係とフロントエンド資産を作成します。

```bash
composer install --no-dev --prefer-dist --optimize-autoloader \
  --classmap-authoritative --no-interaction
npm ci
npm run build
```

本番へはアプリケーション一式、`vendor`、`public/build`を配備します。`.env`、開発用の`node_modules`、テスト、旧CMS資材は配布物へ含めません。

### 2. 本番用の共有領域を準備する

以下のように、環境設定と永続ファイルをreleaseの外へ置く構成を推奨します。

```text
/path/to/pol-community/
├── releases/<RELEASE_ID>/
├── current -> releases/<RELEASE_ID>
└── shared/
    ├── .env
    └── storage/
```

`.env.example`を基に`shared/.env`を作成し、最低限次を本番用に設定します。

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY`（`php artisan key:generate --show`で一度だけ生成）
- `APP_URL`
- MySQLの`DB_HOST`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`
- メール送信設定
- HTTPS利用時の`SESSION_SECURE_COOKIE=true`

`APP_KEY`は運用開始後に変更しないでください。`.env`はWeb公開せず、所有者だけが読める権限にします。

### 3. releaseを初期化する

releaseへ共有領域をリンクし、Laravelが必要とするディレクトリを書き込み可能にします。

```bash
cd /path/to/pol-community/releases/<RELEASE_ID>
ln -s /path/to/pol-community/shared/.env .env
mv storage storage.package
ln -s /path/to/pol-community/shared/storage storage
chmod -R u+rwX bootstrap/cache /path/to/pol-community/shared/storage

php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan view:cache
php artisan route:clear
```

本アプリでは本番環境でroute cacheを使用すると問題が生じた実績があるため、`php artisan optimize`と`php artisan route:cache`は実行せず、最後に必ず`route:clear`を実行します。

初期管理者を画面から作成する場合は、十分に長いランダム値を`INITIAL_SETUP_TOKEN`へ設定し、`INITIAL_SETUP_ENABLED=true`にしてHTTPSの`/setup`へアクセスします。作成完了後は直ちに`INITIAL_SETUP_ENABLED=false`へ戻し、設定キャッシュを再生成してください。

### 4. ApacheとSchedulerを設定する

ApacheのDocumentRootはrelease直下ではなく、必ず`current/public`に設定します。`public/.htaccess`を有効にするため、対象Directoryで`AllowOverride All`とURL rewritingを利用できるようにしてください。

外部RSSを定期取得する場合は、cronからLaravel Schedulerを実行します。

```cron
* * * * * cd /path/to/pol-community/current && /usr/local/bin/php artisan schedule:run > /dev/null 2>&1
```

ホスティングサービスの最短実行間隔に制限がある場合は、その範囲内で設定してください。さくらインターネットのレンタルサーバーでは毎時実行での動作を確認しています。

### 5. 動作を確認する

```bash
cd /path/to/pol-community/current
php artisan about --only=environment
php artisan migrate:status
php artisan schedule:list
```

`production`、`APP_DEBUG OFF`、正しいURLとMySQLデータベース、全migrationが`Ran`であることを確認します。ブラウザではトップページ、`/login`、静的資産の表示に加え、`.env`、`storage`、`vendor`が外部から取得できないことを確認してください。

## 開発環境

### 開発コンテナのセキュリティ上の注意

`.devcontainer/Dockerfile`、`.devcontainer/devcontainer.json`、`compose.yaml`は、信頼できるソースコードをローカルで開発するための設定です。現在の構成では、開発上の利便性のため次の強い権限やホスト連携を有効にしています。

- ホストのDockerソケットをコンテナへマウント
- `SYS_ADMIN` capabilityを付与
- seccompを無効化
- コンテナ内でパスワードなし`sudo`を許可
- ホストの`~/.codex`をコンテナへマウント

これらはコンテナからホストやDockerデーモンへ強い操作権限を与えるため、不特定のコードを実行する環境には適しません。開発コンテナ自体を第三者へ公開する場合や、共有・CI・本番用途へ転用する場合は、必要性を個別に確認し、不要な設定を削除または無効化してください。本番アプリケーションの配布物には、これらの開発コンテナ設定を含めません。

### 品質確認コマンド

```bash
APP_URL=http://localhost VIEW_COMPILED_PATH=/tmp/pol-community-test-views php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
composer audit --locked --no-interaction
npm audit --audit-level=moderate
```

## 商標・著作物に関する表示

PlayOnline 及び ファイナルファンタジーXI は、株式会社スクウェア・エニックスの登録商標です。また、アセットに使用されている一部の画像データの著作権は、株式会社スクウェア・エニックスに帰属します。
