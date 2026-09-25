# リリースマニュアル

## 1. 目的

本書は、Laravelアプリケーションを本番環境へ更新リリースするための一般的な手順を示す。環境固有の接続先、URL、パス、コマンドの場所は固定せず、実際の環境に合わせて置き換える。

初回構築、サーバー移行、災害復旧、破壊的なデータベース変更は対象外とする。

## 2. 前提となる構成

本書では、リリースごとのアプリケーションと永続データを分離し、`current` シンボリックリンクで稼働バージョンを切り替える構成を想定する。

```text
<APP_ROOT>/
├── releases/
│   ├── <PREVIOUS_RELEASE_ID>/
│   └── <RELEASE_ID>/
├── current -> releases/<RELEASE_ID>
└── shared/
    ├── .env
    └── storage/
```

Webサーバーの公開ディレクトリは `<APP_ROOT>/current/public` とする。`.env`、`storage`、`vendor` などを直接公開しない。

## 3. 作業前に決める値

次の値を実際の環境に合わせて設定する。プレースホルダーを残したままコマンドを実行しない。

| 変数 | 内容 | 例 |
|---|---|---|
| `<SSH_HOST>` | SSH接続先 | `production-server` |
| `<APP_ROOT>` | アプリケーション配置先 | `/var/www/example-app` |
| `<PUBLIC_URL>` | 公開URL | `https://example.com` |
| `<PHP>` | PHP CLI | `php` |
| `<RELEASE_ID>` | 新しいリリースの識別子 | `20260925-01` |
| `<BACKUP_DIR>` | バックアップ先 | `/var/backups/example-app/<BACKUP_ID>` |

作業開始時に、現在の `current` の参照先を記録する。この値がロールバック先となる。

## 4. 基本方針

- 稼働中のリリースを直接編集しない。
- `.env` と `storage` はリリースに含めず、共有領域を使用する。
- 本番運用開始後に `APP_KEY` を変更しない。
- 秘密値をGit、配布物、ログ、手順書へ含めない。
- データベース変更前に、復元可能なバックアップを取得する。
- 切り替え後のデータを失う可能性があるロールバックは自動で行わない。
- 旧リリースとバックアップは、動作確認が完了するまで削除しない。

## 5. リリース手順

### 5.1 変更内容を確認する

リリース対象の差分を確認し、次のどちらに該当するか判断する。

- **データベース変更なし**: アプリケーション、設定、表示、静的資産だけの変更
- **後方互換性のある変更あり**: テーブルやnullable列の追加など、変更後のDBでも旧リリースが動作する変更

列・テーブルの削除や名前変更、不可逆なデータ変換など、旧リリースが動作しなくなる変更は通常手順でリリースしない。段階的な移行と専用の復旧計画を作成する。

新しい環境変数、PHP拡張、cron、キュー、メール、ファイル保存形式への影響も確認する。

### 5.2 品質確認を行う

プロジェクトで定めたテスト、静的解析、コード整形確認、脆弱性監査を実行する。例:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit --locked --no-interaction
npm audit --audit-level=moderate
```

失敗がある場合は、原因を解消するまでリリースしない。

### 5.3 配布物を作成する

開発環境またはCIで、本番用のPHP依存関係とフロントエンド資産を作成する。

```bash
composer install --no-dev --prefer-dist --optimize-autoloader \
  --classmap-authoritative --no-interaction
npm ci
npm run build
```

配布物にはアプリケーション本体、`vendor`、ビルド済み資産を含める。次のものは含めない。

- `.env` や秘密鍵などのSecret
- `node_modules`
- テスト用データやローカルDB
- 不要なログ、バックアップ、開発専用ファイル

配布物をアーカイブし、SHA-256などのチェックサムを作成する。転送後に同じ値であることを確認する。

### 5.4 本番へ配置する

配布物を `<APP_ROOT>/releases/<RELEASE_ID>` へ展開する。この時点では `current` を変更しない。

共有する `.env` と `storage` を新しいリリースへリンクする。

```bash
cd <APP_ROOT>/releases/<RELEASE_ID>
ln -s <APP_ROOT>/shared/.env .env
mv storage storage.package
ln -s <APP_ROOT>/shared/storage storage
chmod -R u+rwX bootstrap/cache <APP_ROOT>/shared/storage
```

ファイルの所有者と権限は、WebサーバーおよびCLIの実行ユーザーに合わせる。

### 5.5 切り替え前バックアップを取得する

最低限、次を公開領域外へ保存する。

- 現在の `current` の参照先
- データベースのダンプ
- ユーザーアップロードなどの永続ファイル
- 本番の環境設定（アクセス権を制限する）

ダンプとアーカイブが空でないこと、読み取り可能であること、チェックサムが一致することを確認する。重要な環境では、定期的に別環境への復元試験も行う。

### 5.6 新しいリリースを準備する

新しいリリースのディレクトリで設定キャッシュを作成し、環境とDB接続を確認する。

```bash
cd <APP_ROOT>/releases/<RELEASE_ID>
<PHP> artisan config:cache
<PHP> artisan event:cache
<PHP> artisan view:cache
<PHP> artisan migrate:status
```

使用しているLaravelのバージョンやアプリケーション構成によっては、一部のキャッシュが利用できない場合がある。プロジェクトの動作確認済みコマンドだけを使用する。

### 5.7 データベースを更新する

migrationがない場合は、この手順を省略する。

後方互換性のあるmigrationがある場合は、必要に応じてメンテナンス状態にしてから実行する。

```bash
cd <APP_ROOT>/current
<PHP> artisan down --retry=60

cd <APP_ROOT>/releases/<RELEASE_ID>
<PHP> artisan migrate --force
<PHP> artisan migrate:status
```

migrationが失敗した場合は、理由を確認せずにロールバックを実行しない。適用済みmigrationとDB状態を記録し、復旧方法を判断する。

### 5.8 リリースを切り替える

新しいシンボリックリンクを作成し、同一ファイルシステム上で `current` を切り替える。利用できるデプロイツールがある場合は、そのアトミックな切り替え機能を優先する。

```bash
cd <APP_ROOT>
ln -s releases/<RELEASE_ID> current.new
mv -T current.new current
readlink current
```

`mv -T` を利用できないOSでは、事前に検証した同等の切り替え方法を使用する。

メンテナンス状態にした場合は、新しいリリースで解除する。

```bash
cd <APP_ROOT>/current
<PHP> artisan up
```

## 6. 切り替え後の確認

次を機械的に確認する。

```bash
curl -fsS <PUBLIC_URL>/ > /dev/null
curl -fsS <PUBLIC_URL>/login > /dev/null

cd <APP_ROOT>/current
<PHP> artisan about --only=environment
<PHP> artisan migrate:status
<PHP> artisan schedule:list
```

確認項目:

- `current` が新しいリリースを参照している
- 本番環境でデバッグが無効になっている
- トップページ、ログイン、変更対象の機能が正常に動作する
- CSS、JavaScript、画像が読み込める
- migrationがすべて適用済みである
- `.env`、`storage`、`vendor` などを公開URLから取得できない
- cron、Scheduler、キュー、メールなど、変更の影響を受ける処理が動作する
- アプリケーションログとWebサーバーのログに新しい異常がない

必要に応じて、認証、認可、投稿、管理画面などを実ユーザー相当の操作で確認する。

## 7. ロールバック

### 7.1 DB変更がない場合

`current` を記録済みの直前リリースへ戻し、切り替え後と同じ確認を行う。

```bash
cd <APP_ROOT>
ln -s releases/<PREVIOUS_RELEASE_ID> current.rollback
mv -T current.rollback current
readlink current
```

### 7.2 後方互換性のあるDB変更がある場合

旧リリースが変更後のスキーマでも動作することを確認済みの場合に限り、コードだけを直前リリースへ戻す。追加した列やテーブルは、その場で削除しない。

### 7.3 非互換なDB変更やデータ破損がある場合

自動でDBを戻さない。サービスをメンテナンス状態にし、現在のDBと永続ファイルを別途保全してから、切り替え前バックアップを復元するか判断する。復元によって失われる更新データを特定し、関係者の承認を得る。

## 8. 完了記録

リリースごとに、次を作業記録へ残す。Secretは記録しない。

- 実施日時、実施者、変更概要
- リリースIDと直前リリースID
- コミットIDまたはタグ
- バックアップの識別子と確認結果
- テスト、静的解析、脆弱性監査の結果
- migrationの有無と結果
- 配布物のチェックサム
- 切り替え後の確認結果
- 発生した問題、対処、ロールバックの有無

## 9. 中断条件

次の場合は切り替えを中止するか、メンテナンス状態を維持して原因を調査する。

- テスト、静的解析、脆弱性監査が失敗した
- 配布物にSecretや不要なデータが含まれている
- チェックサムが一致しない
- 接続先の環境やDBが想定と異なる
- バックアップが失敗または不完全である
- migrationが破壊的、非互換、または内容不明である
- migrationや切り替え後の動作確認が失敗した
- 非公開ファイルや非公開データを外部から取得できる
- 復旧によって失われるデータを判断できない
