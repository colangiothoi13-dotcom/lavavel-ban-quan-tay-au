# Cá nhân hóa và gợi ý sản phẩm

## Mục tiêu

Hoàn thiện trải nghiệm cá nhân hóa cho khách hàng đã đăng nhập của cửa hàng: xem lại sản phẩm đã duyệt, nhận gợi ý dựa trên hành vi và quản lý danh sách yêu thích. Tận dụng lịch sử đơn hàng hiện có làm tín hiệu mua hàng, không tạo một hệ thống lịch sử mua riêng.

## Bối cảnh hiện tại

Workspace đã có migration và quan hệ dữ liệu cho sản phẩm yêu thích và sản phẩm đã xem, các route yêu cầu đăng nhập, cùng các trang Blade cơ bản. Việc ghi lượt xem hiện chỉ chạy ở trang chi tiết chính; trang chi tiết biến thể chưa ghi nhận. Lượt xem lại không cập nhật thời gian, nên danh sách không phản ánh sản phẩm được xem gần nhất. Bộ gợi ý hiện so khớp từ trong tên sản phẩm. Nút yêu thích đang lồng trong form giỏ hàng, và các trang cá nhân hóa chưa có liên kết nhất quán trong điều hướng tài khoản.

## Quyết định thiết kế

### Phạm vi tài khoản và dữ liệu

- Chỉ lưu lịch sử duyệt và danh sách yêu thích cho người dùng đã đăng nhập. Khách chưa đăng nhập vẫn duyệt và mua hàng theo luồng hiện tại, nhưng không có lịch sử cá nhân được lưu.
- Ghi nhận lượt mở trang chi tiết sản phẩm và trang chi tiết biến thể. Mỗi cặp người dùng/sản phẩm có một bản ghi đại diện cho lần xem gần nhất; xem lại cập nhật thời điểm của bản ghi thay vì tạo một dòng cho mỗi lần mở.
- Trang lịch sử duyệt hiển thị các sản phẩm duy nhất theo thời điểm xem gần nhất, phân trang theo quy ước hiện có.
- Lịch sử mua tiếp tục dùng đơn hàng và dòng sản phẩm trong đơn hiện có. Chỉ đơn có trạng thái `completed` được tính là tín hiệu mua; đơn đang xử lý hoặc đã hủy không được tính. Dữ liệu sản phẩm còn liên kết được qua biến thể dùng danh mục và giới tính làm tín hiệu; tên sản phẩm đã lưu là dữ liệu lịch sử dự phòng, không được dùng để suy đoán thuộc tính chưa biết.

### Gợi ý sản phẩm

Dùng thuật toán quy tắc xác định, chạy trong ứng dụng và không cần dịch vụ hay thư viện ngoài. Sản phẩm đã mua, yêu thích và xem là nguồn sở thích; tín hiệu mua có trọng số cao hơn yêu thích, và yêu thích cao hơn lượt xem. Mỗi sản phẩm ứng viên được xếp hạng theo mức khớp danh mục và giới tính với các sản phẩm nguồn. Sản phẩm đã xem, đã yêu thích hoặc đã mua bị loại khỏi danh sách gợi ý. Khi không có ứng viên khớp, hiển thị sản phẩm mới nhất còn lại.

Trang gợi ý giữ cấu trúc lưới sản phẩm hiện có. Không yêu cầu hiển thị lời giải thích cho từng kết quả trong phạm vi này.

### Danh sách yêu thích và điều hướng

- Giữ thao tác thêm/bỏ yêu thích trên trang chi tiết; biểu mẫu yêu thích phải độc lập với biểu mẫu thêm giỏ hàng.
- Trang yêu thích hiển thị sản phẩm đã lưu và có thao tác bỏ từng sản phẩm. Hai thao tác dùng cùng route toggle có xác thực hiện tại.
- Thêm lối vào gợi ý, yêu thích và lịch sử duyệt trong điều hướng khách hàng đã đăng nhập để các trang dùng chung một đường đi dễ tìm.

## Kiến trúc và luồng dữ liệu

`StorefrontController` tiếp tục xử lý route và chuẩn bị dữ liệu cho các trang. Phần tính điểm và truy vấn ứng viên được đặt trong một dịch vụ gợi ý riêng để không làm controller cửa hàng phình thêm. Quan hệ hiện có trên `User`, `Product`, `Order` và `OrderItem` là giao diện dữ liệu giữa dịch vụ, controller và cơ sở dữ liệu.

Khi người dùng đã đăng nhập mở trang chi tiết, ứng dụng cập nhật bản ghi xem gần nhất. Các trang lịch sử và yêu thích chỉ truy vấn dữ liệu gắn với người dùng hiện tại. Trang gợi ý lấy các tín hiệu của người dùng, xếp hạng sản phẩm phù hợp, loại sản phẩm trùng với tín hiệu nguồn rồi trả tối đa tám sản phẩm cho view.

Ràng buộc dữ liệu cần bảo đảm mỗi người dùng chỉ có một bản ghi xem cho mỗi sản phẩm, kể cả khi có các yêu cầu đồng thời. Nếu bảng đã có dữ liệu trùng, migration cần giữ lại bản ghi có thời điểm cập nhật mới nhất trước khi thêm ràng buộc duy nhất. Xóa người dùng hoặc sản phẩm tiếp tục xóa các liên kết yêu thích và lượt xem bằng khóa ngoại cascade.

## Quyền truy cập và lỗi

Các route lịch sử, yêu thích, gợi ý và thao tác yêu thích tiếp tục nằm sau middleware `auth`. Truy vấn luôn giới hạn theo người dùng đã xác thực; không nhận `user_id` từ đầu vào. Guest truy cập route riêng tư sẽ đi qua hành vi chuyển hướng đăng nhập hiện có. Nếu người dùng chưa có tín hiệu hoặc không tìm thấy sản phẩm phù hợp, trang gợi ý hiển thị trạng thái rỗng hoặc danh sách sản phẩm mới theo quy tắc đã nêu, không phát sinh lỗi.

## Tiêu chí chấp nhận

1. Trang chi tiết và trang chi tiết biến thể đều cập nhật lịch sử xem của người dùng đã đăng nhập.
2. Xem lại một sản phẩm làm sản phẩm đó lên đầu lịch sử duyệt; trang lịch sử không lặp sản phẩm.
3. Người dùng có thể thêm và bỏ yêu thích từ trang chi tiết và bỏ yêu thích từ danh sách.
4. Các thao tác yêu thích không gửi form giỏ hàng; dữ liệu và trang chỉ hiển thị yêu thích của người dùng hiện tại.
5. Gợi ý thay đổi theo sản phẩm đã mua, yêu thích hoặc xem; kết quả ưu tiên sản phẩm cùng danh mục/giới tính, không chứa sản phẩm đã xem/yêu thích/đã mua, và có fallback sản phẩm mới.
6. Tài khoản khách hàng có thể mở ba trang cá nhân hóa qua điều hướng nhất quán.

## Ngoài phạm vi

- Theo dõi khách chưa đăng nhập hoặc hợp nhất lịch sử khách với tài khoản sau khi đăng nhập.
- Lưu mọi sự kiện xem thành nhật ký riêng, xóa toàn bộ lịch sử, phân tích hành vi quản trị, gợi ý bằng học máy hoặc dịch vụ bên ngoài.
- Thay thế hay thiết kế lại trang lịch sử đơn hàng hiện có.
