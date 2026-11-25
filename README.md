# API

基于 Laravel 13、PHP 8.4、Filament 和 Laravel Passport 的 API 服务。

## 运行架构

- 应用代码位于 `wwwroot/api`，由工作区根目录的 Docker Compose 项目运行。
- `nginx` 将 `api.tmdsd.top` 的请求转发到应用容器中的 `api/public`，PHP-FPM 监听容器网络的 `app:9000`。
- MySQL、Redis、PHP-FPM 和 Nginx 均由 Compose 管理；应用容器中的项目路径是 `/var/www/html/api`。

## 部署

以下命令在 Compose 项目根目录（当前环境为 `/home/jian/app`）执行。部署目录必须保持 `wwwroot/api` 这一层级，因为 Compose 将 `./wwwroot` 挂载到容器的 `/var/www/html`。

### 1. 准备环境变量

在应用目录创建生产环境配置，**不要提交 `.env`**：

```sh
cd /home/jian/app/wwwroot/api
cp .env.example .env
```

至少设置以下值：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://api.tmdsd.top

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

REDIS_HOST=redis
```

`DB_DATABASE`、`DB_USERNAME` 和 `DB_PASSWORD` 必须与 Compose 根目录 `.env` 中传给 MySQL 服务的值一致。生成 `APP_KEY` 的命令见下一步，不要手工填写或复用开发环境的密钥。

### 2. 构建并启动服务

```sh
cd /home/jian/app
docker compose up -d --build mysql redis app nginx
docker compose ps
```

### 3. 安装应用依赖并初始化数据库

```sh
docker compose exec -T -w /var/www/html/api app composer install --no-dev --optimize-autoloader --no-interaction
docker compose exec -T -w /var/www/html/api app php artisan key:generate --force --no-interaction
docker compose exec -T -w /var/www/html/api app php artisan migrate --force --no-interaction
docker compose exec -T -w /var/www/html/api app php artisan optimize --no-interaction
```

`migrate --force` 会修改生产数据库；先确认 `.env` 指向目标环境并完成数据库备份。不要在生产环境使用会清空数据的迁移或数据库命令。

应用镜像不包含 Node.js。若页面引用 Vite 资源，需在 CI 或具备 Node.js 的构建机上运行 `npm install --ignore-scripts && npm run build`，并将生成的 `public/build` 与发布文件一同部署；不要在 `app` 容器中运行 npm。

### 4. 发布后检查

```sh
docker compose exec -T -w /var/www/html/api app php artisan about --only=environment
curl -fsS -o /dev/null -w '%{http_code}\n' -H 'Host: api.tmdsd.top' http://127.0.0.1/up
docker compose logs --tail=100 app nginx
```

健康检查端点为 `/up`；预期返回 HTTP `200`。

## 域名与 HTTPS

Nginx 配置在工作区的 `nginx/conf/api.conf`，当前仅监听 HTTP `80` 并使用 `api.tmdsd.top` 作为 `server_name`。部署前应：

1. 将域名 DNS 指向部署主机。
2. 在该 Nginx 配置或上游反向代理中配置 TLS 证书和 HTTPS。
3. HTTPS 启用后，将 `APP_URL` 更新为 `https://api.tmdsd.top`，然后运行 `php artisan config:clear` 和 `php artisan optimize`。

## 运行维护

- 查看状态：`docker compose ps`
- 查看服务日志：`docker compose logs -f app nginx`
- 执行应用命令：`docker compose exec -T -w /var/www/html/api app php artisan <command>`
- 更新代码后，重复“安装应用依赖并初始化数据库”中的 `composer install`、`migrate --force` 和 `optimize`；仅在依赖或前端资源变更时才额外构建相应资源。

当前 Supervisor 仅托管 PHP-FPM 和 `crond`。如果以后添加队列 worker 或 Laravel 定时任务，需要在工作区的 Supervisor/cron 配置中显式增加对应进程，不能假定它们会自动运行。

### MTeam 分类快取刷新

```sh
# 按配置顺序刷新全部分类（排程也使用此形式）
docker compose exec -T -w /var/www/html/api app php artisan mteam:refresh-category

# 只刷新指定的一个或多个分类
docker compose exec -T -w /var/www/html/api app php artisan mteam:refresh-category 401 419 412
```

指定分类会按提供顺序处理；重复 ID 仅处理首次出现的一次。所有指定 ID 会在开始刷新前验证，任一 ID 非数字或未配置时不会刷新或改写任何分类快取。

## 测试

测试使用内存 SQLite，不依赖 Compose 中的 MySQL：

```sh
docker compose exec -T -w /var/www/html/api app php artisan test --compact
```
