# Granite API

Granite API là source nền REST API backend được xây dựng bằng Laravel 13, PHP 8.4 và MySQL 8.4. Toàn bộ môi trường local chạy bằng Docker Compose; máy host không cần cài PHP, Composer, MySQL hoặc Node.js.

Laravel application nằm trong thư mục `src/`. Các cấu hình Docker, tài liệu và cấu hình hỗ trợ phát triển nằm ở repository root.

## Thành phần local

- `granite-app`: chạy Supervisor, Nginx và PHP-FPM.
- `granite-db`: chạy MySQL 8.4.
- `granite-mysql-data`: named volume lưu dữ liệu MySQL.
- Nginx được publish mặc định tại `http://localhost:8868`.
- MySQL được publish ra host mặc định tại `127.0.0.1:3022`.
- Xdebug được cài trong image nhưng tắt mặc định.

## Yêu cầu

- Git.
- Docker Engine hoặc Docker Desktop.
- Docker Compose v2, sử dụng cú pháp `docker compose`.
- Các port `8868` và `3022` chưa được ứng dụng khác sử dụng, hoặc được đổi trong `.env` ở repository root.

Kiểm tra Docker trước khi bắt đầu:

```bash
docker --version
docker compose version
```

## Cài đặt lần đầu

Tất cả command trong tài liệu này được chạy từ repository root, trừ khi có ghi chú khác.

### 1. Tạo file môi trường

```bash
cp .env.example .env
cp src/.env.example src/.env
```

Hai file có mục đích khác nhau:

- `/.env` cấu hình hạ tầng Docker: port, MySQL container, timezone, UID/GID và Xdebug.
- `/src/.env` cấu hình Laravel: kết nối database, application key, JWT, cookie, trusted origin, log và Cloudflare R2.

Giá trị database mặc định trong hai file example đã khớp nhau. Nếu thay đổi `DB_NAME`, `DB_USER` hoặc `DB_PASS` trong `/.env`, phải cập nhật `DB_DATABASE`, `DB_USERNAME` hoặc `DB_PASSWORD` tương ứng trong `src/.env`.

Không commit hai file `.env` hoặc bất kỳ secret thực tế nào lên Git.

### 2. Kiểm tra UID/GID trên Linux

Image map user `www-data` trong container với user của máy host để tránh sinh file thuộc `root` trong source.

```bash
id -u
id -g
```

Cập nhật `HOST_UID` và `HOST_GID` trong `/.env` nếu kết quả khác giá trị mặc định `1001`. Sau khi đổi UID/GID, cần build lại image.

Docker Desktop trên macOS hoặc Windows thường không cần thay đổi hai giá trị này.

### 3. Build image

```bash
docker compose build app
```

### 4. Cài Composer dependencies

Command sau chạy Composer bằng `www-data`, sử dụng Composer home tạm thời và không khởi động database:

```bash
docker compose run --rm --no-deps \
  --user www-data \
  -e COMPOSER_HOME=/tmp/composer \
  --entrypoint composer app install
```

### 5. Khởi động service

```bash
docker compose up -d
docker compose ps
```

Trong lần chạy đầu, chờ MySQL hiển thị `ready for connections` trước khi migrate:

```bash
docker compose logs -f database
```

Nhấn `Ctrl+C` để thoát chế độ theo dõi log; container vẫn tiếp tục chạy.

### 6. Khởi tạo Laravel

```bash
docker compose exec --user www-data app php artisan key:generate
docker compose exec --user www-data app php artisan jwt:secret
docker compose exec --user www-data app php artisan migrate
```

`JWT_SECRET` phải được giữ bí mật và có tối thiểu 32 byte ngẫu nhiên. Ứng dụng sẽ không phát hành hoặc chấp nhận Admin JWT nếu secret không đạt yêu cầu.

### 7. Tạo tài khoản quản trị

```bash
docker compose exec --user www-data app php artisan admin:create
```

Có thể truyền email trực tiếp nhưng mật khẩu luôn được nhập qua hidden prompt:

```bash
docker compose exec --user www-data app php artisan admin:create admin@example.com
```

Mật khẩu Admin phải có ít nhất 12 ký tự.

### 8. Kiểm tra API

```bash
curl -i http://localhost:8868/api/v1/admin/products
```

Khi chưa đăng nhập, response `401 Unauthorized` với JSON error là kết quả đúng. Điều đó xác nhận request đã đi qua Nginx, PHP-FPM và Laravel.

## Cấu hình môi trường

### Docker environment tại `/.env`

- `DB_NAME`: tên database MySQL, mặc định `granite_database`.
- `DB_USER`: MySQL application user, mặc định `granite`.
- `DB_PASS`: password local cho application user và root user.
- `WEB_PORT`: port Nginx được publish ra host, mặc định `8868`.
- `DB_PORT`: port MySQL được publish ra host, mặc định `3022`.
- `TZ`: timezone của container, mặc định `UTC`.
- `HOST_UID`, `HOST_GID`: UID/GID dùng cho `www-data` trong image.
- `XDEBUG_MODE`: mặc định `off`.

MySQL container được khởi tạo từ các biến `DB_NAME`, `DB_USER` và `DB_PASS` trong `/.env`. Laravel không nhận các biến này trực tiếp từ Compose mà đọc kết nối database từ `/src/.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=granite_database
DB_USERNAME=granite
DB_PASSWORD=granite_local_password
```

Khi chạy trong Docker, không đổi `DB_HOST` thành `127.0.0.1`. Tên service `database` được Docker DNS phân giải tới MySQL container. Port giữa hai container luôn là `3306`; `DB_PORT=3022` ở root `.env` chỉ dành cho kết nối từ máy host.

### Laravel environment tại `/src/.env`

Các biến cần chú ý:

- `APP_ENV=local` để bật các route tham chiếu chỉ dành cho local.
- `APP_DEBUG=true` chỉ dùng trong local.
- `APP_KEY` được sinh bằng `php artisan key:generate`.
- `JWT_SECRET` được sinh bằng `php artisan jwt:secret`.
- Các biến `DB_*` phải khớp database, user và password được khởi tạo bởi root `/.env`.
- `ADMIN_COOKIE_PROFILE=local` cho phép cookie Admin trên loopback HTTP.
- `ADMIN_TRUSTED_ORIGINS` là danh sách origin frontend được phép gửi unsafe request.
- Các biến `R2_*` cấu hình Cloudflare R2 khi sử dụng image storage thực tế.

Ví dụ frontend local chạy ở port `3000`:

```dotenv
ADMIN_TRUSTED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
```

Không sử dụng wildcard cho trusted origin. Sau khi đổi cấu hình Laravel, xóa config cache nếu cần:

```bash
docker compose exec --user www-data app php artisan optimize:clear
```

## Command Docker thường dùng

Khởi động, dừng và xem trạng thái:

```bash
docker compose up -d
docker compose stop
docker compose start
docker compose restart app
docker compose ps
docker compose down
```

`docker compose down` xóa container và network nhưng giữ nguyên dữ liệu trong named volume MySQL.

Theo dõi log:

```bash
docker compose logs -f app
docker compose logs -f database
docker compose logs --tail=100 app
```

Mở shell trong application container:

```bash
docker compose exec --user www-data app bash
```

Kiểm tra phiên bản runtime:

```bash
docker compose exec app php -v
docker compose exec app composer --version
docker compose exec app nginx -v
```

Build lại image sau khi đổi Dockerfile, PHP extension, UID/GID hoặc timezone:

```bash
docker compose build --no-cache app
docker compose up -d --force-recreate app
```

## Composer và Artisan

Chạy Composer trong container đang hoạt động:

```bash
docker compose exec \
  --user www-data \
  -e COMPOSER_HOME=/tmp/composer \
  app composer install
```

Một số command Artisan hữu ích:

```bash
docker compose exec --user www-data app php artisan about
docker compose exec --user www-data app php artisan route:list --except-vendor
docker compose exec --user www-data app php artisan migrate:status
docker compose exec --user www-data app php artisan optimize:clear
```

Không chạy `composer update` trong quy trình setup thông thường vì command này có thể thay đổi `composer.lock`. Chỉ update dependency khi đó là thay đổi có chủ đích và đã được review.

## Database và migration

### Kết nối từ application container

Laravel sử dụng các thông tin sau:

```text
Host: database
Port: 3306
Database: granite_database
Username: granite
Password: giá trị DB_PASS trong /.env
```

### Kết nối từ máy host

Sử dụng database client với:

```text
Host: 127.0.0.1
Port: 3022 hoặc giá trị DB_PORT
Database: granite_database hoặc giá trị DB_NAME
Username: granite hoặc giá trị DB_USER
Password: giá trị DB_PASS
```

Mở MySQL CLI trong database container; password được nhập qua prompt để tránh lưu trong shell history:

```bash
docker compose exec database mysql -u granite -p granite_database
```

Migration thường dùng:

```bash
docker compose exec --user www-data app php artisan migrate
docker compose exec --user www-data app php artisan migrate:status
docker compose exec --user www-data app php artisan migrate:rollback
```

Command sau xóa toàn bộ bảng rồi chạy lại migration. Chỉ sử dụng khi chắc chắn dữ liệu local có thể mất:

```bash
docker compose exec --user www-data app php artisan migrate:fresh
```

### Named volume MySQL

Dữ liệu MySQL được lưu trong Docker named volume `granite-mysql-data`, không nằm trong repository. Volume vẫn tồn tại sau `docker compose down` và được mount lại ở lần chạy tiếp theo.

Kiểm tra volume:

```bash
docker volume inspect granite-mysql-data
```

Reset hoàn toàn database local:

```bash
docker compose down -v
docker compose up -d
```

> Cảnh báo: `docker compose down -v` xóa named volume và toàn bộ dữ liệu MySQL local. Không thể khôi phục nếu không có backup.

Các biến `MYSQL_DATABASE`, `MYSQL_USER` và `MYSQL_PASSWORD` chỉ được image MySQL áp dụng khi volume được khởi tạo lần đầu. Nếu đổi credential trong `/.env` nhưng giữ volume cũ, user/password trong MySQL không tự thay đổi.

## Authentication Admin

Endpoint xác thực:

- `POST /api/v1/admin/auth/login`
- `POST /api/v1/admin/auth/logout`

Admin JWT dùng HS256, hết hạn sau 180 phút và chỉ được nhận từ cookie cấu hình của ứng dụng. API không nhận JWT từ bearer authorization header.

Local sử dụng cookie `granite_admin_token_local`. Môi trường hosted phải chuyển sang `ADMIN_COOKIE_PROFILE=hosted`, sử dụng secure cookie `__Host-granite_admin_token` và HTTPS.

Unsafe request tới Admin API phải có header `Origin` khớp chính xác một origin trong `ADMIN_TRUSTED_ORIGINS`. Login giới hạn năm lần thất bại cho mỗi email và IP trong 15 phút.

## Product CRUD tham chiếu

Product minh họa luồng `FormRequest → Controller → Service → Eloquent → JsonResource`:

- `GET /api/v1/admin/products`
- `POST /api/v1/admin/products`
- `GET /api/v1/admin/products/{product}`
- `PATCH /api/v1/admin/products/{product}`
- `DELETE /api/v1/admin/products/{product}`

Product route và migration chỉ được load trong môi trường `local` và `testing`; production không có Product route hoặc Product table.

## Test và kiểm tra chất lượng

Test suite sử dụng SQLite in-memory và fake R2 storage. Chạy test không đọc, ghi hoặc reset MySQL local.

```bash
docker compose exec --user www-data app php artisan test --compact
```

Có thể chạy thông qua Composer script:

```bash
docker compose exec \
  --user www-data \
  -e COMPOSER_HOME=/tmp/composer \
  app composer test
```

Chạy một file hoặc một test cụ thể:

```bash
docker compose exec --user www-data app php artisan test --compact tests/Feature/Admin/ProductTest.php
docker compose exec --user www-data app php artisan test --compact --filter=test_name
```

Kiểm tra format, Composer metadata và dependency security:

```bash
docker compose exec --user www-data app ./vendor/bin/pint --test
docker compose exec -e COMPOSER_HOME=/tmp/composer app composer validate --strict
docker compose exec -e COMPOSER_HOME=/tmp/composer app composer audit
```

`composer audit` cần kết nối Internet để tải advisory mới nhất.

## Supervisor, Nginx và PHP-FPM

Supervisor là process chính của `granite-app` và tự khởi động lại PHP-FPM hoặc Nginx nếu một process dừng bất thường.

Kiểm tra trạng thái:

```bash
docker compose exec app \
  supervisorctl -s http://127.0.0.1:19001 status
```

Kết quả bình thường:

```text
granite-nginx   RUNNING
php-fpm         RUNNING
```

Supervisor control port `19001` chỉ tồn tại trong container và không được publish ra máy host. Cấu hình Supervisor hiện tại được giữ nguyên; việc chuyển PID/log runtime hoặc đổi control socket sẽ được đánh giá riêng.

## Scheduler

Ứng dụng có lịch xóa JWT revocation hết hạn mỗi ngày lúc `02:00` UTC.

Kiểm tra hoặc chạy scheduler thủ công:

```bash
docker compose exec --user www-data app php artisan schedule:list
docker compose exec --user www-data app php artisan schedule:run
```

Docker local hiện chưa chạy scheduler liên tục. Khi triển khai production cần cấu hình cron gọi `schedule:run` mỗi phút hoặc chạy `schedule:work` bằng một process được quản lý riêng.

## Cloudflare R2

Cấu hình trong `/src/.env`:

```dotenv
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
R2_URL=https://media.example.com
R2_REGION=auto
```

Application phụ thuộc vào `App\Contracts\ImageStorage`, hiện được bind tới `App\Storage\R2ImageStorage`. Không commit R2 credential vào repository.

## Xdebug

Xdebug mặc định tắt để tránh ảnh hưởng hiệu năng và không tự kết nối IDE:

```dotenv
XDEBUG_MODE=off
```

Khi cần debug, đổi trong `/.env`:

```dotenv
XDEBUG_MODE=debug
```

Sau đó recreate application container:

```bash
docker compose up -d --force-recreate app
```

Xdebug sử dụng port IDE `9003`, `start_with_request=trigger` và host `host.docker.internal`. Trên Linux, cần bảo đảm hostname `host.docker.internal` được Docker phân giải trước khi sử dụng; cấu hình host mapping sẽ được bổ sung riêng nếu môi trường hiện tại cần.

## Troubleshooting

### Port đã được sử dụng

Nếu `8868` hoặc `3022` đang bị chiếm, đổi `WEB_PORT` hoặc `DB_PORT` trong `/.env`, sau đó recreate container:

```bash
docker compose down
docker compose up -d
```

### Thiếu `vendor/autoload.php`

Chạy lại bước cài dependencies:

```bash
docker compose run --rm --no-deps \
  --user www-data \
  -e COMPOSER_HOME=/tmp/composer \
  --entrypoint composer app install
```

### Laravel không kết nối được MySQL

Kiểm tra container và log:

```bash
docker compose ps
docker compose logs --tail=100 database
docker compose exec app php artisan config:show database.default
docker compose exec app php artisan config:show database.connections.mysql
```

Trong container, `DB_HOST` phải là `database` và `DB_PORT` phải là `3306`.

### File trong source thuộc `root`

Kiểm tra `HOST_UID` và `HOST_GID` trong `/.env`, build lại image rồi chạy Composer/Artisan với `--user www-data`. Không chạy Composer trong application container bằng root.

### Laravel vẫn sử dụng cấu hình cũ

```bash
docker compose exec --user www-data app php artisan optimize:clear
docker compose restart app
```

### Kiểm tra cấu hình Compose

```bash
docker compose config
```

## Kiến trúc và quy ước response

API error trả về stable error code, thông báo tiếng Việt, details và request ID:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Dữ liệu gửi lên không hợp lệ.",
    "details": {
      "issues": [
        {
          "field": "name",
          "code": "REQUIRED",
          "message": "Tên sản phẩm là bắt buộc."
        }
      ]
    }
  },
  "request_id": "01J8Z4Y6BCDEFGHJKMNPQRSTVW"
}
```

`X-Request-ID` từ client chỉ được chấp nhận khi là ULID hợp lệ. Response đã xác thực có `Cache-Control: no-store`; unexpected exception được log phía server và không lộ SQL, stack trace, path hoặc secret cho client.

Xem thêm quyết định kiến trúc tại [docs/architecture.md](docs/architecture.md).
