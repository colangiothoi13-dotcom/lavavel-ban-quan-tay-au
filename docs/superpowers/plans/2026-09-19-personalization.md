# Cá nhân hóa và gợi ý sản phẩm Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hoàn thiện lịch sử duyệt, gợi ý sản phẩm theo hành vi và danh sách yêu thích cho khách hàng đã đăng nhập.

**Architecture:** Giữ các route và quan hệ dữ liệu hiện có. Thêm ràng buộc duy nhất cho lượt xem, cập nhật thời điểm xem gần nhất ở cả hai trang chi tiết, và tách tính điểm sản phẩm tương tự thành một dịch vụ chuyên trách. Hoàn thiện biểu mẫu yêu thích và dùng chung menu tài khoản hiện có.

**Tech Stack:** PHP 8.2+, Laravel 12, Eloquent, Blade, SQLite/MySQL.

**Spec:** `docs/superpowers/specs/2026-09-19-personalization-design.md`

## Global Constraints

- Chỉ lưu lịch sử duyệt và danh sách yêu thích cho người dùng đã đăng nhập.
- Mỗi cặp người dùng/sản phẩm có một bản ghi đại diện cho lần xem gần nhất.
- Chỉ đơn có trạng thái `completed` được tính là tín hiệu mua.
- Dùng thuật toán quy tắc xác định, chạy trong ứng dụng và không cần dịch vụ hay thư viện ngoài.
- Tín hiệu mua có trọng số cao hơn yêu thích, và yêu thích cao hơn lượt xem.
- Sản phẩm đã xem, đã yêu thích hoặc đã mua bị loại khỏi danh sách gợi ý.
- Phần tính điểm và truy vấn ứng viên được đặt trong một dịch vụ gợi ý riêng.

**Execution constraint:** Không thêm hoặc chạy kiểm thử tự động trong kế hoạch này nếu người dùng chưa yêu cầu kiểm thử/xác minh.

## Review Focus

- Khách chưa đăng nhập mở route cá nhân hóa vẫn đi qua middleware `auth`, không ghi hoạt động cá nhân. (Task 4: giữ route hiện tại; rà soát đường dẫn điều hướng.)
- Hai lượt mở đồng thời của cùng cặp người dùng/sản phẩm không tạo hai bản ghi; dữ liệu trùng cũ giữ lại lần cập nhật gần nhất. (Task 1: migration và ràng buộc dữ liệu.)
- Mở URL chi tiết chính và URL chi tiết biến thể đều cập nhật cùng một bản ghi; sản phẩm vừa xem đứng đầu lịch sử. (Task 2: ghi nhận và truy vấn lịch sử.)
- Đơn đang xử lý, đơn hủy, hoặc dòng đơn không còn liên kết biến thể không bị hiểu nhầm là thuộc tính mua đã biết. (Task 3: tập tín hiệu gợi ý.)
- Khi không còn ứng viên sau khi loại sản phẩm nguồn, trang không lỗi và chỉ dùng fallback mới nhất còn lại. (Task 3: nhánh fallback.)
- Dữ liệu của tài khoản khác không xuất hiện trong lịch sử, yêu thích hay gợi ý. (Tasks 2–4: truy vấn luôn bắt đầu từ người dùng hiện tại.)

## File Map

- `database/migrations/2026_09_19_000002_add_unique_user_product_views.php` — loại bản ghi xem trùng, thêm chỉ mục duy nhất theo người dùng/sản phẩm và chỉ mục thứ tự cập nhật.
- `app/Http/Controllers/StorefrontController.php` — ghi lần xem mới nhất ở cả hai route chi tiết, sắp lịch sử theo lần xem mới nhất và gọi dịch vụ gợi ý.
- `app/Services/Personalization/ProductRecommendationService.php` — lấy tín hiệu hợp lệ, tính điểm tương đồng và trả tối đa tám sản phẩm.
- `resources/views/shop/show.blade.php` — đặt form yêu thích độc lập với form giỏ hàng.
- `resources/views/shop/wishlist.blade.php` — cung cấp nút bỏ yêu thích không lồng trong liên kết sản phẩm.
- `resources/views/shop/history.blade.php`, `resources/views/shop/wishlist.blade.php`, `resources/views/shop/recommendations.blade.php` — dùng layout tài khoản để có điều hướng nhất quán.
- `resources/views/layouts/app.blade.php` — thêm liên kết ba trang cá nhân hóa vào menu khách hàng.

---

### Task 1: Bảo đảm một bản ghi lượt xem cho mỗi sản phẩm và người dùng

**Files:**
- Create: `database/migrations/2026_09_19_000002_add_unique_user_product_views.php`

**Interfaces:**
- Consumes: `user_product_views` được tạo bởi migration `2026_09_19_000001_create_personalization_tables.php`.
- Produces: ràng buộc duy nhất `user_product_views_user_id_product_id_unique` và chỉ mục `user_product_views_user_id_updated_at_index`.

- [ ] Tạo migration mới để hoạt động cả khi migration nền đã chạy trước đó.
- [ ] Trong `up()`, tìm các cặp `(user_id, product_id)` có nhiều dòng. Với từng cặp, giữ dòng có `updated_at` mới nhất; nếu trùng thời điểm thì giữ `id` lớn nhất; xóa các dòng còn lại.
- [ ] Sau bước dọn dữ liệu, thêm unique index trên `['user_id', 'product_id']` và index trên `['user_id', 'updated_at']`.
- [ ] Trong `down()`, bỏ hai index theo tên đã định nghĩa; không xóa bảng hoặc dữ liệu lượt xem.
- [ ] Rà soát thứ tự thao tác để dữ liệu được dọn trước khi tạo unique index và migration dùng được trên SQLite/MySQL.

### Task 2: Ghi và hiển thị lượt xem mới nhất

**Files:**
- Modify: `app/Http/Controllers/StorefrontController.php`

**Interfaces:**
- Consumes: ràng buộc duy nhất từ Task 1 và quan hệ `User::productViews()`.
- Produces: helper nội bộ `recordProductView(Request $request, Product $product): void`; lịch sử sản phẩm phân trang theo `user_product_views.updated_at` giảm dần.

- [ ] Thêm helper `recordProductView()`; nếu request có người dùng, gọi `productViews()->updateOrCreate(['product_id' => $product->id])` để thao tác xem lại cập nhật `updated_at`.
- [ ] Tiêm `Request` vào `show()` và gọi helper sau khi tải biến thể của sản phẩm.
- [ ] Tiêm `Request` vào `showVariant()`; giữ kiểm tra biến thể thuộc sản phẩm trước khi gọi helper.
- [ ] Trong `browseHistory()`, giữ điều kiện `user_product_views.user_id` là ID người dùng hiện tại, bỏ `distinct()` vì Task 1 bảo đảm mỗi cặp là duy nhất, và sắp theo `updated_at` giảm dần.
- [ ] Giữ eager loading `variants` và phân trang 12 sản phẩm như trang hiện có.

### Task 3: Tách và tích hợp gợi ý dựa trên hành vi

**Files:**
- Create: `app/Services/Personalization/ProductRecommendationService.php`
- Modify: `app/Http/Controllers/StorefrontController.php`

**Interfaces:**
- Consumes: `User`, quan hệ lượt xem/yêu thích, `Order::items`, `OrderItem::variant`, và các trường `Product::category_id`/`gender`.
- Produces: `ProductRecommendationService::recommendFor(User $user, int $limit = 8): Collection` trả `Collection<int, Product>` đã nạp quan hệ `variants`.

- [ ] Tạo dịch vụ trong namespace `App\Services\Personalization` và định nghĩa trọng số nguồn: đơn hoàn tất `5`, yêu thích `3`, lượt xem `1`.
- [ ] Tạo tập sản phẩm nguồn từ đơn `status = completed` qua `items.variant.product`, sản phẩm yêu thích và sản phẩm đã xem. Bỏ qua dòng đơn không còn variant/product; không suy diễn danh mục/giới tính từ `product_name`.
- [ ] Gộp trọng số nếu cùng sản phẩm có nhiều nguồn. Đồng thời tạo danh sách ID loại trừ gồm sản phẩm đã mua, yêu thích và xem.
- [ ] Lấy ứng viên còn lại có danh mục khớp hoặc giới tính tương thích với ít nhất một nguồn. Với mỗi nguồn, cộng `2 × trọng số nguồn` khi cùng danh mục và `1 × trọng số nguồn` khi giới tính bằng nhau hoặc một trong hai là `unisex`.
- [ ] Sắp ứng viên theo điểm giảm dần, rồi `created_at` và `id` giảm dần; eager load `variants`, trả tối đa `$limit` sản phẩm.
- [ ] Nếu không có nguồn hoặc không có ứng viên được chấm điểm, lấy sản phẩm mới nhất còn lại, vẫn loại ID đã mua/yêu thích/xem và giới hạn theo `$limit`.
- [ ] Tiêm dịch vụ vào `StorefrontController`; thay logic so khớp từ trong `recommendations()` bằng lời gọi `recommendFor($request->user())`.

### Task 4: Hoàn thiện thao tác yêu thích và điều hướng tài khoản

**Files:**
- Modify: `resources/views/shop/show.blade.php`
- Modify: `resources/views/shop/wishlist.blade.php`
- Modify: `resources/views/shop/history.blade.php`
- Modify: `resources/views/shop/recommendations.blade.php`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Consumes: các route `shop.wishlist.toggle`, `shop.wishlist.index`, `shop.history.index`, `shop.recommendations` đang có và hành vi flash status hiện tại.
- Produces: các thao tác thêm/bỏ yêu thích hoạt động độc lập với form giỏ hàng; ba trang cá nhân hóa có chung menu khách hàng.

- [ ] Trong `show.blade.php`, đóng form giỏ hàng trước form yêu thích; giữ CSRF token, route toggle và nhãn thay đổi theo trạng thái hiện tại.
- [ ] Trong `wishlist.blade.php`, thay liên kết bao toàn bộ thẻ bằng cấu trúc thẻ không lồng tương tác; đặt liên kết xem sản phẩm và form bỏ yêu thích cạnh nhau trong cùng thẻ.
- [ ] Đổi ba trang cá nhân hóa sang `layouts.app`, phù hợp với layout mà trang chủ và chi tiết sản phẩm dùng cho tài khoản đăng nhập.
- [ ] Thêm ba liên kết có trạng thái active theo URL dưới menu khách hàng trong `layouts/app.blade.php`.
- [ ] Giữ nguyên route, nội dung trang lịch sử đơn hàng và luồng mua hàng hiện tại.

## Hướng triển khai

Các task dùng chung `StorefrontController` và ràng buộc dữ liệu, nên thực hiện tuần tự: Task 1 → Task 2 → Task 3 → Task 4. Workspace hiện có các thay đổi chưa commit ở một số tệp trong phạm vi; khi triển khai cần giữ nguyên phần khác với từng task và không stage toàn bộ tệp đã thay đổi.

Không có bước tạo hoặc chạy kiểm thử tự động trong kế hoạch này theo giới hạn hiện tại. Kế hoạch đề xuất **Native execution** vì bốn task phụ thuộc tuần tự và cùng chỉnh các tệp hiện có; một lượt review độc lập sau khi hoàn thành sẽ phù hợp hơn việc chia mỗi task cho một agent khác nhau.
