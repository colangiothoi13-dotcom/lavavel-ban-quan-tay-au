# Hệ thống bán Quần Tây Âu

Ứng dụng thương mại điện tử xây dựng bằng Laravel 12, phục vụ hai nhóm người dùng:

- Khách hàng: tìm sản phẩm, chọn biến thể, quản lý giỏ hàng, đặt hàng và theo dõi đơn mua.
- Quản trị viên: quản lý danh mục, sản phẩm, tồn kho, đơn hàng và báo cáo doanh thu.

## Công nghệ sử dụng

- PHP 8.2 và Laravel 12
- MySQL
- Blade, CSS và JavaScript
- Vite và Tailwind CSS 4
- Laravel Mail/Notification
- OpenStreetMap, Leaflet và Photon cho phần địa chỉ
- PHPUnit cho kiểm thử tự động

## Luồng tổng quan

```text
Khách truy cập
    │
    ├── Xem cửa hàng, tìm kiếm, lọc sản phẩm
    ├── Xem chi tiết và chọn màu/size
    └── Thêm sản phẩm vào giỏ hàng
             │
             ▼
Đăng ký / đăng nhập
    │
    ├── Xác minh email bằng OTP
    └── Đồng bộ giỏ hàng session với tài khoản
             │
             ▼
Chọn địa chỉ + phương thức thanh toán
             │
             ▼
Tạo đơn và trừ tồn kho trong transaction
             │
             ▼
pending → processing → shipping → completed
   └───────────────→ cancelled
             │
             ├── Event gửi email khi bắt đầu giao
             ├── Event gửi email khi hoàn thành
             └── Hoàn thành/đã hủy được lưu trữ sau 2 ngày
```

## Luồng khách hàng

### 1. Đăng ký và đăng nhập

1. Người dùng nhập họ tên, số điện thoại, email và mật khẩu.
2. Hệ thống kiểm tra định dạng dữ liệu và email đã tồn tại hay chưa.
3. Mã OTP 6 chữ số được gửi qua email và có hiệu lực trong 15 phút.
4. Chỉ khi OTP hợp lệ, tài khoản mới được tạo và đánh dấu đã xác minh email.
5. Sau khi đăng nhập hoặc đăng ký thành công, giỏ hàng trong session được đồng bộ vào bảng `cart_items`.

Luồng quên mật khẩu cũng sử dụng OTP email có thời hạn.

### 2. Xem và tìm sản phẩm

Trang cửa hàng hỗ trợ:

- Tìm kiếm theo tên và gợi ý sản phẩm khi đang nhập.
- Lọc theo giới tính, kích thước, danh mục và khoảng giá.
- Sắp xếp sản phẩm.
- Xem chi tiết sản phẩm và tất cả biến thể màu/size.
- Đổi hình sản phẩm theo biến thể được chọn.

Ảnh sản phẩm được lưu trong `storage/app/public/products` và truy cập qua liên kết `public/storage`.

### 3. Giỏ hàng

Mỗi dòng giỏ hàng đại diện cho một biến thể sản phẩm.

- Khách có thể tăng, giảm hoặc xóa số lượng.
- Có thể đổi trực tiếp sang màu/size khác.
- Nếu đổi sang biến thể đã có trong giỏ, số lượng được gộp.
- Backend luôn kiểm tra tổng số lượng không vượt quá tồn kho.
- Với tài khoản đã đăng nhập, database là nguồn dữ liệu dự phòng khi session bị mất hoặc hết hạn.

### 4. Địa chỉ nhận hàng

Khách hàng có thể lưu nhiều địa chỉ và chọn một địa chỉ mặc định.

Luồng thêm địa chỉ:

1. Chọn Tỉnh/Thành phố.
2. Chọn Phường/Xã.
3. Tìm địa chỉ cụ thể qua Photon/OpenStreetMap.
4. Xem vị trí trên bản đồ Leaflet.
5. Lưu tên người nhận, số điện thoại, địa chỉ và tọa độ.

### 5. Thanh toán và tạo đơn

Hiện tại ứng dụng hỗ trợ hai lựa chọn:

- Tiền mặt khi nhận hàng.
- Chuyển khoản ngân hàng.

Khi xác nhận đặt hàng:

1. Backend kiểm tra địa chỉ, phương thức thanh toán và các sản phẩm đã chọn.
2. Các biến thể được khóa bằng `lockForUpdate()`.
3. Tồn kho được kiểm tra lại ngay trước khi tạo đơn.
4. Đơn hàng và từng `order_items` được tạo trong database transaction.
5. Tồn kho được trừ.
6. Các sản phẩm đã đặt được xóa khỏi giỏ hàng.

Nếu bất kỳ bước nào thất bại, transaction được rollback để không tạo đơn hoặc trừ kho dở dang.

> MoMo và liên kết tài khoản ngân hàng chưa được tích hợp. Không lưu mật khẩu, OTP hoặc thông tin đăng nhập ngân hàng trong database. Muốn liên kết thật cần tài khoản đối tác và API xác thực của nhà cung cấp.

### 6. Trang Đơn mua

Trang danh sách có các tab:

- Tất cả
- Chờ xác nhận
- Đang giao
- Hoàn thành
- Đã hủy

Tab Chờ xác nhận bao gồm cả `pending` và `processing`. Menu Đơn mua hiện mở thẳng tab Hoàn thành.

Mỗi thẻ đơn hiển thị mã đơn, ngày đặt, sản phẩm, biến thể, tổng tiền, thanh toán và trạng thái. Khách hàng có thể:

- Xem trang chi tiết và tiến trình giao hàng.
- Hủy đơn đang hoạt động.
- Mua lại đơn hoàn thành hoặc đã hủy.
- Tìm theo mã đơn hoặc tên sản phẩm.

Khi hủy, người dùng bắt buộc nhập lý do từ 5 đến 500 ký tự. Lý do được lưu vào đơn, hiển thị cho khách hàng và quản trị viên. Tồn kho chỉ được hoàn lại một lần.

## Luồng quản trị viên

### 1. Danh mục và sản phẩm

Admin có thể:

- Thêm, sửa và xóa danh mục.
- Thêm, sửa và xóa sản phẩm.
- Khai báo các biến thể màu/size, giá và tồn kho.
- Tải ảnh chính và ảnh biến thể.
- Lọc và xem chi tiết sản phẩm.

### 2. Quản lý đơn hàng

Luồng trạng thái trên giao diện:

```text
pending             Chờ xác nhận
    │ Xác nhận đơn
    ▼
processing          Đã xác nhận / đang đóng gói
    │ Giao cho bên vận chuyển
    ▼
shipping            Đang giao
    │ Hoàn thành đơn
    ▼
completed           Hoàn thành
```

Quy tắc quan trọng:

- Controller chặn chuyển từ `completed` ngược về `pending`, kể cả request được gửi thủ công.
- Khi trạng thái chuyển sang `shipping`, `OrderStatusChanged` được phát và Listener gửi email báo đơn đã đóng gói, giao cho đơn vị vận chuyển.
- Khi trạng thái chuyển sang `completed`, Event được phát và gửi email báo giao hàng thành công.
- Listener được Laravel tự động phát hiện và chỉ chạy một lần cho mỗi Event.
- Lỗi SMTP được ghi vào log nhưng không rollback trạng thái đơn đã cập nhật.
- Admin có thể cập nhật trạng thái thanh toán độc lập.

### 3. Sắp xếp và lưu trữ đơn

Ở cả trang Admin và User:

- Các đơn đang hoạt động được xếp phía trên.
- Đơn `completed` và `cancelled` được đẩy xuống cuối ngay khi đổi trạng thái.
- Sau 2 ngày, đơn kết thúc tự ẩn khỏi danh sách.

Hệ thống không xóa vật lý đơn hàng. Trường `archived_at` được sử dụng để giữ dữ liệu cho báo cáo, kiểm toán và lịch sử thanh toán.

Lệnh lưu trữ:

```bash
php artisan orders:archive-expired
```

Scheduler chạy lệnh trên mỗi giờ. Điều kiện 2 ngày cũng được kiểm tra khi truy vấn danh sách, nên đơn quá hạn vẫn bị ẩn kể cả khi scheduler chưa kịp chạy.

### 4. Báo cáo doanh thu

Báo cáo chỉ tính các đơn:

- Đã thanh toán.
- Không ở trạng thái đã hủy.

Admin có thể lọc theo khoảng ngày, xem doanh thu theo ngày, sản phẩm đã bán và xuất file XLSX.

## Cấu trúc dữ liệu chính

| Model | Vai trò |
|---|---|
| `User` | Tài khoản khách hàng hoặc quản trị viên |
| `Category` | Danh mục sản phẩm |
| `Product` | Thông tin chung của sản phẩm |
| `ProductVariant` | Màu, size, giá, ảnh và tồn kho |
| `CartItem` | Giỏ hàng lâu dài của người dùng |
| `Address` | Địa chỉ nhận hàng đã lưu |
| `Order` | Người nhận, thanh toán, trạng thái và tổng tiền |
| `OrderItem` | Ảnh chụp tên, biến thể, số lượng và giá lúc đặt |

Quan hệ chính:

```text
User 1 ── n Address
User 1 ── n CartItem n ── 1 ProductVariant
User 1 ── n Order 1 ── n OrderItem
Category 1 ── n Product 1 ── n ProductVariant
```

## Cài đặt dự án

### Yêu cầu

- PHP 8.2 trở lên
- Composer
- Node.js và npm
- MySQL
- Các PHP extension thông dụng: PDO MySQL, OpenSSL, Mbstring, Fileinfo

### Các bước

```bash
composer install
npm install
```

Tạo file môi trường:

```powershell
Copy-Item .env.example .env
```

Cấu hình kết nối database và SMTP trong `.env`, sau đó chạy:

```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

Khởi động môi trường phát triển:

```bash
composer run dev
```

Nếu không dùng lệnh trên, có thể chạy riêng:

```bash
php artisan serve
npm run dev
php artisan schedule:work
```

Không commit `.env`, mật khẩu SMTP, khóa API hoặc thông tin thanh toán lên Git.

## Cấu hình email

Các chức năng cần SMTP:

- OTP đăng ký.
- OTP quên mật khẩu.
- Thông báo đơn bắt đầu giao.
- Thông báo đơn hoàn thành.

Các biến môi trường cần thiết:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
```

Sau khi thay đổi cấu hình:

```bash
php artisan config:clear
```

## Kiểm thử

Chạy toàn bộ kiểm thử:

```bash
php artisan test
```

Hoặc trên Windows:

```powershell
vendor\bin\phpunit.bat --do-not-cache-result
```

Bộ kiểm thử bao phủ các luồng chính:

- Hủy đơn và hoàn tồn kho đúng một lần.
- Bắt buộc nhập lý do hủy.
- Không xem được đơn của người khác.
- Chặn `completed → pending`.
- Phát Event và gửi Notification khi giao/hoàn thành.
- Đổi biến thể và đồng bộ giỏ hàng.
- Lưu trữ đơn hoàn thành/đã hủy sau 2 ngày.
- Báo cáo doanh thu.
- Đăng ký và xác minh OTP.

## Các giới hạn hiện tại

- Chưa tích hợp MoMo hoặc API ngân hàng thật.
- Trạng thái giao hàng được Admin cập nhật, chưa đồng bộ API của hãng vận chuyển.
- Phí vận chuyển và voucher chưa có cột dữ liệu riêng; trang chi tiết hiện suy ra từ tổng đơn.
- Đơn được lưu trữ sau 2 ngày chứ không xóa vật lý.
