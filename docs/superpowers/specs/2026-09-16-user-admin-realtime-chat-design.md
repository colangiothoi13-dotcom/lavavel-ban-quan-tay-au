# Thiết kế chat real-time giữa user và admin

## Mục tiêu

Thêm chức năng nhắn tin cho cửa hàng quần Tây Âu. Mỗi tài khoản khách hàng có một cuộc hội thoại riêng với admin. Tin nhắn được lưu bền vững để admin có thể xem lại khi đăng nhập, đồng thời tin mới được đẩy real-time khi hai bên đang online.

Khách chưa đăng nhập vẫn nhìn thấy mục “Nhắn tin” trong menu. Khi bấm vào, hệ thống yêu cầu đăng nhập; chỉ sau khi xác thực thành công user mới được truy cập và gửi tin.

## Phạm vi và quyết định chính

- Chỉ user đã đăng nhập mới được xem hoặc gửi tin nhắn.
- Một user có đúng một cuộc hội thoại với nhóm tài khoản admin.
- User không chọn người nhận; mọi tin user gửi đều thuộc cuộc hội thoại với admin.
- Admin có thể xem tất cả cuộc hội thoại và trả lời từng user.
- Khi không có admin đang hoạt động, user vẫn gửi được tin và thấy trạng thái “Admin đang bận, sẽ trả lời sau”.
- Khi có admin hoạt động, user thấy trạng thái online; admin thấy tin mới trong inbox real-time.
- Dùng Laravel Reverb và Laravel Echo cho WebSocket.
- Tin nhắn dùng `ShouldBroadcastNow` để không phụ thuộc queue worker cho việc phát tin tức thời.
- Không thêm chat nhóm, đính kèm file, emoji picker hay xóa/sửa tin trong phiên bản này.

## Kiến trúc

### Lưu trữ

Thêm hai bảng:

`chat_conversations`

- `id`
- `user_id` khóa ngoại tới `users`, unique, cascade khi user bị xóa
- `last_message_at` nullable, có index để sắp xếp inbox
- timestamps

`chat_messages`

- `id`
- `conversation_id` khóa ngoại tới `chat_conversations`, cascade
- `sender_id` khóa ngoại tới `users`, cascade
- `body` dạng text
- `read_at` nullable timestamp
- timestamps và index phục vụ lấy tin theo cuộc hội thoại/trạng thái chưa đọc

Admin được xác định bởi `users.role = admin`. Không lưu `recipient_id` vì recipient của user luôn là admin; admin nào cũng có thể xử lý cùng một cuộc hội thoại.

### Miền ứng dụng

- `ChatConversation` và `ChatMessage` model có quan hệ rõ ràng với User.
- `ChatController` xử lý trang và các endpoint đọc/gửi/đánh dấu đã đọc.
- Một lớp/service nhỏ chịu trách nhiệm tìm hoặc tạo cuộc hội thoại, kiểm tra trạng thái admin và tạo tin nhắn trong transaction.
- `ChatMessageSent` là broadcast event phát trên kênh riêng của user và kênh chung của admin.
- Một service presence dùng cache TTL để biết có admin nào vừa gửi heartbeat.

## Luồng dữ liệu

### User gửi tin

1. User mở `/nhan-tin`; middleware `auth` chuyển khách chưa đăng nhập về route đăng nhập.
2. Controller lấy hoặc tạo conversation theo `user_id`, đánh dấu các tin admin trong conversation là đã đọc.
3. User gửi `body`; server trim, validate không rỗng và giới hạn tối đa 2.000 ký tự.
4. Tin nhắn được lưu trong transaction, cập nhật `last_message_at` và phát `ChatMessageSent`.
5. Echo trên kênh `chat.user.{user_id}` cập nhật giao diện user; Echo trên kênh `chat.admins` cập nhật inbox admin nếu có admin đang kết nối.
6. Nếu không có presence admin còn hạn, UI hiển thị “Admin đang bận, sẽ trả lời sau”. Tin vẫn được lưu bình thường.

### Admin nhận và trả lời

1. Admin mở `/admin/nhan-tin`; middleware `auth` và `admin` bảo vệ toàn bộ route.
2. Inbox lấy các conversation có tin nhắn, sắp xếp theo `last_message_at` giảm dần và hiển thị số tin user chưa đọc.
3. Khi admin chọn conversation, server kiểm tra conversation tồn tại rồi đánh dấu tin từ user là đã đọc.
4. Admin gửi câu trả lời; tin được lưu với `sender_id` là admin hiện tại và phát tới kênh admin cùng kênh user tương ứng.
5. User đang mở chat thấy câu trả lời ngay; nếu user offline, tin được lấy khi họ mở lại trang.

### Trạng thái admin

- Layout admin chạy heartbeat ngay khi tải và lặp lại khoảng 30 giây.
- Mỗi heartbeat ghi cache key theo admin với TTL khoảng 75 giây.
- Endpoint trạng thái của user trả về online nếu còn ít nhất một admin có cache presence hợp lệ.
- Nếu heartbeat hết hạn hoặc không có admin, trả về offline/busy.
- Việc mất kết nối Reverb không làm mất tin; lần tải trang kế tiếp luôn đọc lại từ database.

## Kênh và quyền broadcast

- `private-chat.user.{userId}`: chỉ user có `id` tương ứng hoặc admin được phép subscribe để hỗ trợ trường hợp quản trị.
- `private-chat.admins`: chỉ user có `isAdmin()` mới được subscribe.
- `routes/channels.php` định nghĩa authorization; không dùng public channel.
- Payload broadcast chỉ chứa id, conversation id, sender, nội dung, thời gian và cờ người gửi; không phát password, token hay dữ liệu hồ sơ nhạy cảm.

## Route dự kiến

User:

- `GET /nhan-tin` — trang chat; yêu cầu auth.
- `GET /nhan-tin/messages` — lấy tin của conversation hiện tại.
- `POST /nhan-tin/messages` — gửi tin tới admin.
- `POST /nhan-tin/read` — đánh dấu tin admin đã đọc.
- `GET /nhan-tin/admin-status` — trả về trạng thái admin online/busy.

Admin:

- `GET /admin/nhan-tin` — inbox và conversation đang chọn.
- `GET /admin/nhan-tin/{conversation}/messages` — lấy tin của conversation.
- `POST /admin/nhan-tin/{conversation}/messages` — trả lời user.
- `POST /admin/nhan-tin/presence` — heartbeat presence.
- `POST /admin/nhan-tin/{conversation}/read` — đánh dấu tin user đã đọc.

Tên route sẽ dùng namespace `chat.*` nhất quán. Mục menu trỏ tới route chat kể cả khi chưa đăng nhập; middleware auth sẽ tự redirect về login.

## Giao diện

### User

- Thêm mục “Nhắn tin” vào menu storefront (`layouts.shop`) để khách chưa đăng nhập nhìn thấy trên các trang shop.
- Thêm mục “Nhắn tin” vào sidebar user (`layouts.app`) cho user đã đăng nhập; link dùng chung và middleware auth sẽ chuyển khách chưa đăng nhập tới trang login.
- Trang chat gồm trạng thái admin, khung lịch sử tin, textarea và nút gửi.
- Có trạng thái trống khi chưa có tin, lỗi validate dễ hiểu và badge số tin chưa đọc.
- Sau khi gửi lúc admin offline, giữ tin trong lịch sử và hiện thông báo bận.
- Echo cập nhật tin đến mà không cần refresh; có fallback tải lại/lấy JSON khi WebSocket tạm lỗi.

### Admin

- Thêm mục “Tin nhắn” vào sidebar admin, kèm tổng số tin chưa đọc.
- Trang gồm danh sách conversation bên trái và khung chat bên phải.
- Mỗi conversation hiển thị tên user, tin cuối, thời gian và badge chưa đọc.
- Tin mới từ kênh admin tự thêm hoặc cập nhật conversation tương ứng, kể cả khi admin đang xem conversation khác.
- Heartbeat được chạy trong layout admin để presence phản ánh việc admin còn đăng nhập/đang hoạt động trên hệ thống.

## Bảo mật và lỗi

- Tất cả endpoint chat yêu cầu CSRF và auth; endpoint admin yêu cầu thêm `admin` middleware.
- User chỉ được truy cập conversation có `conversation.user_id = auth()->id()`.
- Admin chỉ được truy cập conversation tồn tại; không tin vào id do client gửi để quyết định quyền.
- Validate nội dung sau khi trim, không cho rỗng, tối đa 2.000 ký tự; áp dụng throttle gửi tin theo user/IP.
- Nội dung hiển thị bằng escaped Blade/text rendering để tránh XSS.
- Không có admin vẫn cho phép lưu tin; nếu broadcast/presence lỗi, API vẫn trả lỗi rõ ràng hoặc lưu thành công và UI báo kết nối real-time gián đoạn.
- Không để lỗi Reverb làm mất transaction lưu tin.

## Kiểm thử và tiêu chí chấp nhận

Feature tests phải chứng minh:

1. Khách chưa đăng nhập thấy link “Nhắn tin”, bấm route chat thì bị chuyển tới login.
2. User đã đăng nhập mở được chat và chỉ thấy conversation của chính mình.
3. User gửi tin tạo đúng một conversation và lưu đúng nội dung.
4. Gửi lần tiếp theo tái sử dụng conversation cũ, không tạo conversation thứ hai.
5. User không thể đọc/gửi vào conversation của user khác.
6. Admin xem được inbox toàn bộ user và số tin chưa đọc.
7. Admin trả lời thì tin lưu với sender là admin và user nhận được khi tải lại.
8. Đánh dấu đã đọc làm giảm đúng số unread của phía tương ứng.
9. Message rỗng/quá dài bị từ chối.
10. User không thể gọi endpoint admin; tài khoản thường nhận 403 ở endpoint admin.
11. Presence trả về busy khi không có heartbeat còn hạn và online khi admin heartbeat.
12. Broadcast event dùng đúng private channels và payload không chứa dữ liệu nhạy cảm.

Acceptance criteria cuối cùng:

- Hai tài khoản đang mở chat thấy tin mới của nhau ngay qua WebSocket.
- User gửi được khi admin offline; tin xuất hiện trong inbox admin sau khi admin đăng nhập.
- Khách chưa đăng nhập thấy mục chat nhưng không thể gửi/xem tin trước khi đăng nhập.
- Không thể đọc hoặc gửi nhầm dữ liệu giữa hai user.
- Test suite hiện tại và test chat đều pass; Vite build thành công.

## Vận hành

Cần thêm dependency và cấu hình Reverb/Echo, các biến môi trường broadcast cần thiết, cùng hướng dẫn chạy tiến trình Reverb. Testing sẽ dùng broadcast connection `null` và không phụ thuộc máy chủ WebSocket thật; một test tích hợp nhẹ sẽ kiểm tra event/channel/payload.
