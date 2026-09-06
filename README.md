# Hệ thống bán quần Tây Âu

Ứng dụng thương mại điện tử xây dựng bằng Laravel, phục vụ việc bán quần Tây Âu trực tuyến.
## Tính năng

### Khách hàng
- Xem, tìm kiếm, lọc và sắp xếp sản phẩm.
- Chọn màu, kích thước và số lượng sản phẩm.
- Thêm, cập nhật, xóa và đổi biến thể trong giỏ hàng.
- Đăng ký tài khoản bằng OTP email.
- Quên mật khẩu bằng OTP email.
- Quản lý hồ sơ và địa chỉ nhận hàng.
- Đặt hàng với tiền mặt, chuyển khoản hoặc MoMo.
- Theo dõi, hủy, mua lại và thanh toán lại đơn hàng MoMo.
- Xem lịch sử và chi tiết đơn hàng.

### Quản trị viên
- Quản lý danh mục và sản phẩm.
- Quản lý biến thể, giá và tồn kho.
- Xác nhận, giao, hoàn thành hoặc hủy đơn hàng.
- Lọc đơn theo trạng thái và tình trạng thanh toán.
- Xem báo cáo doanh thu.
- Quản lý hồ sơ tài khoản quản trị.

Trên màn hình lớn, các trang tiện ích của khách hàng có sidebar cố định và khung nội dung trắng giống giao diện quản trị. Trang cửa hàng vẫn sử dụng bố cục rộng; trên màn hình nhỏ sidebar chuyển thành menu 3 gạch.
## Công nghệ

- PHP 8.2+
- Laravel 12
- SQLite hoặc MySQL
- PHPUnit 11
- GHN API để tính phí và quản lý thông tin giao hàng
- MoMo Payment API để thanh toán trực tuyến

## Yêu cầu môi trường
- PHP 8.2 trở lên
- Composer
- Node.js và npm
- SQLite hoặc MySQL
- SMTP hoặc mail driver phù hợp nếu cần gửi OTP email

Kiểm tra phiên bản:
```bash
php -v
composer --version
node -v
npm -v
```

## Cài đặt trên Windows
Mở PowerShell tại thư mục dự án:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
```

### Cấu hình cơ sở dữ liệu
Mặc định `.env.example` dùng SQLite. Tạo file database nếu chưa có:

```powershell
New-Item database/database.sqlite -ItemType File
php artisan migrate --seed
```

Nếu dùng MySQL, sửa các biến sau trong `.env` rồi chạy migration:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ten_database
DB_USERNAME=root
DB_PASSWORD=
```

```powershell
php artisan migrate --seed
```

Tạo liên kết storage để hiển thị ảnh:

```powershell
php artisan storage:link
```

## Tài khoản mẫu
Các tài khoản này được tạo bởi `php artisan db:seed`:

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Quản trị viên | `admin@example.com` | `password` |
| Khách hàng | `user@example.com` | `password` |

Chỉ dùng tài khoản mẫu trong môi trường local. Khi triển khai thật, hãy đổi hoặc xóa các tài khoản này.
## Chạy ứng dụng

Cách đơn giản:

```powershell
php artisan serve
npm run dev
```

Mở [http://127.0.0.1:8000](http://127.0.0.1:8000).

Hoặc dùng script Laravel có sẵn để chạy server, queue, log và Vite cùng lúc:

```powershell
composer run dev
```

Build frontend cho môi trường production:

```powershell
npm run build
```

## Cấu hình email và OTP
Chức năng đăng ký và quên mật khẩu cần mail driver hoạt động. Ví dụ dùng SMTP:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-account
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Minh Tri Tailor"
```

OTP quên mật khẩu có các quy tắc:

- Có hiệu lực trong 15 phút.
- Tối đa 5 lần nhập sai theo email và IP.
- Giới hạn gửi lại khoảng 3 lần mỗi phút theo email và IP.
- OTP bị vô hiệu ngay sau khi xác minh thành công.
- Bước đổi mật khẩu bắt buộc có token xác minh trong session.
- Email không tồn tại và email tồn tại nhận cùng một phản hồi để tránh lộ tài khoản.

## Cấu hình GHN
GHN được dùng để lấy tỉnh, quận, phường và tính phí giao hàng:

```env
GHN_BASE_URL=https://dev-online-gateway.ghn.vn/shiip/public-api
GHN_TOKEN=
GHN_SHOP_ID=
GHN_VERIFY_SSL=true
GHN_FROM_DISTRICT_ID=
GHN_SERVICE_TYPE_ID=2
GHN_DEFAULT_WEIGHT=500
GHN_DEFAULT_LENGTH=20
GHN_DEFAULT_WIDTH=15
GHN_DEFAULT_HEIGHT=10
GHN_TIMEOUT=10
```

Không có `GHN_TOKEN` và `GHN_SHOP_ID`, các chức năng địa chỉ và tính phí GHN sẽ không hoạt động đầy đủ.

## Cấu hình MoMo
MoMo cần callback truy cập được từ phía MoMo. Khi phát triển local, có thể dùng tunnel HTTPS để MoMo gọi được IPN.

```env
MOMO_PARTNER_CODE=
MOMO_ACCESS_KEY=
MOMO_SECRET_KEY=
MOMO_BASE_URL=https://test-payment.momo.vn
MOMO_CREATE_ENDPOINT=/v2/gateway/api/create
MOMO_IPN_ROUTE=momo.ipn
MOMO_RETURN_ROUTE=momo.result
MOMO_TIMEOUT=15
MOMO_PAYMENT_TIMEOUT=30
```

Luồng MoMo:

1. Tạo đơn và giữ tồn kho.
2. Gọi MoMo để tạo liên kết thanh toán.
3. Cập nhật `payment_status=paid` khi return hoặc IPN hợp lệ.
4. Return/IPN lặp không ghi nhận thanh toán lần hai.
5. Đơn MoMo chưa thanh toán có thể thanh toán lại.
6. Đơn MoMo quá hạn thanh toán sẽ được hủy và hoàn tồn kho.

Chạy thủ công job hủy đơn MoMo hết hạn:

```powershell
php artisan orders:cancel-expired-payments
```

Scheduler cần được chạy định kỳ trong môi trường production. Job này mặc định chạy mỗi 5 phút.

## Trạng thái đơn hàng
### Tiến độ đơn hàng

```text
pending -> processing -> shipping -> completed
   \-> cancelled
processing -----------------> cancelled
shipping -------------------> cancelled
```

`completed` và `cancelled` là trạng thái kết thúc, không thể chuyển sang trạng thái khác.

### Thanh toán

- `unpaid`: chưa thanh toán.
- `paid`: đã thanh toán.

Khi admin chuyển một đơn chưa thanh toán sang `shipping`, hệ thống có thể tự chuyển phương thức thanh toán không tiền mặt về tiền mặt theo nghiệp vụ giao hàng.

## Kiểm thử và kiểm tra chất lượng
Chạy toàn bộ test:

```powershell
php artisan test
```

Hoặc:

```powershell
composer test
```

Kiểm tra cú pháp một file PHP:

```powershell
php -l app/Http/Controllers/AuthController.php
```

Kiểm tra format bằng Laravel Pint:

```powershell
vendor/bin/pint --test
```

## Các route chính

| Khu vực | URL |
| --- | --- |
| Cửa hàng | `/cua-hang` |
| Giỏ hàng | `/gio-hang` |
| Đăng nhập | `/buyer/login` |
| Đăng ký | `/buyer/register` |
| Quên mật khẩu | `/buyer/forgot-password` |
| Hồ sơ khách hàng | `/user/profile` |
| Địa chỉ | `/user/addresses` |
| Đơn mua | `/user/don-mua` |
| Quản trị | `/admin` |
| Quản lý sản phẩm | `/admin/products` |
| Quản lý đơn hàng | `/admin/orders` |
| Báo cáo | `/admin/reports` |

Xem toàn bộ route:

```powershell
php artisan route:list
```

## Scheduler và queue

Scheduler hiện có các job:

- Lưu trữ đơn đã hoàn thành hoặc đã hủy quá thời hạn.
- Hủy đơn MoMo chưa thanh toán khi hết hạn và hoàn tồn kho.

Trong production, cần cấu hình scheduler Laravel và worker queue phù hợp với môi trường triển khai.

## Lưu ý production

Trong `.env` production bắt buộc kiểm tra:

```env
APP_ENV=production
APP_DEBUG=false
GHN_VERIFY_SSL=true
LOG_LEVEL=info
```

Ngoài ra:

- Không commit `.env`, mật khẩu, token, secret key hoặc dữ liệu cá nhân.
- Dùng HTTPS cho website và callback MoMo.
- Không dùng tài khoản mẫu với mật khẩu `password`.
- Chạy `php artisan config:cache`, `php artisan route:cache` và `php artisan view:cache` sau khi cấu hình production.
- Bảo đảm scheduler chạy đều để đơn MoMo hết hạn không giữ tồn kho vô thời hạn.
- Kiểm tra quyền truy cập: khách hàng chỉ được xem và thao tác trên đơn của chính mình; khu vực `/admin` yêu cầu role admin.

## Cấu trúc thư mục chính

```text
app/
   Http/Controllers/       Controller web và nghiệp vụ
   Models/                 Eloquent models
   Services/Payments/      Tích hợp MoMo
   Notifications/          Thông báo trạng thái đơn
database/
   migrations/             Schema cơ sở dữ liệu
   seeders/                Dữ liệu mẫu
resources/views/          Blade templates
routes/                   Web và console routes
tests/Feature/            Feature tests
public/                   Entry point và tài nguyên public
```

## License

Dự án sử dụng cấu trúc ứng dụng Laravel. Kiểm tra license của các package trong `composer.json` và `package.json` trước khi phân phối thương mại.
