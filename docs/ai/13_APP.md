# TASK: XÂY DỰNG APP MOBILE THU TIỀN RÁC CHO NHÂN VIÊN THU

Bạn là Senior Flutter Developer, Mobile Architect và UX Designer.

Hãy phân tích và xây dựng ứng dụng mobile cho nhân viên thu tiền rác, kết nối với hệ thống Laravel REST API hiện có.

Ứng dụng phải ưu tiên:

* Dễ sử dụng ngoài thực địa
* Tốc độ phản hồi nhanh
* Ít thao tác
* Tìm hộ dân nhanh
* Thu tiền nhanh
* In phiếu nhanh
* Hoạt động ổn định khi mạng yếu
* Giao diện rõ ràng, chữ dễ đọc
* Tối ưu cho điện thoại Android phổ thông

---

# 1. Công nghệ

## Mobile

* Flutter phiên bản ổn định mới nhất đang tương thích với dự án
* Dart
* Riverpod hoặc state management hiện có
* Dio để gọi API
* GoRouter hoặc router hiện có
* Secure Storage để lưu access token
* SQLite hoặc Drift nếu cần lưu cache/offline
* Freezed hoặc json_serializable nếu dự án đang sử dụng
* Không tự ý thay đổi kiến trúc hiện tại nếu source app đã tồn tại

## Backend

* Laravel REST API
* Laravel Sanctum hoặc cơ chế token hiện tại
* API prefix theo convention hiện tại, ví dụ:

```text
/api/v1
```

---

# 2. Nguyên tắc kiến trúc

Sử dụng kiến trúc rõ ràng:

```text
Presentation
↓
Controller / Provider
↓
Use Case / Service
↓
Repository
↓
Remote Data Source / Local Data Source
↓
Laravel API / SQLite
```

Yêu cầu:

* Không viết logic nghiệp vụ trực tiếp trong Widget
* Không gọi Dio trực tiếp từ màn hình
* Không hardcode URL API
* Không hardcode quyền, trạng thái hoặc cấu hình
* Tách model, DTO, repository, service và UI
* Có xử lý loading, empty, error và retry
* Có cơ chế refresh token nếu backend hỗ trợ
* Có log lỗi ở mức phù hợp
* Không hiển thị stack trace cho người dùng

---

# 3. Phạm vi chức năng

Ứng dụng gồm các module:

1. Đăng nhập
2. Đổi mật khẩu
3. Đăng xuất
4. Màn hình chính
5. Ghi thu tiền rác
6. Tìm hộ dân
7. Chọn tháng thu
8. Xuất hóa đơn điện tử
9. In phiếu thu Bluetooth
10. Thống kê thu
11. Thông báo điều hành
12. Xem chi tiết thông tin điều hành
13. Văn bản, tài liệu
14. Đồng bộ dữ liệu
15. Cấu hình ứng dụng
16. Nhật ký lỗi cục bộ nếu cần

---

# 4. Đăng nhập

Màn hình đăng nhập gồm:

* Logo đơn vị
* Tên hệ thống
* Tên đăng nhập
* Mật khẩu
* Nút hiện/ẩn mật khẩu
* Ghi nhớ tài khoản
* Nút đăng nhập
* Hiển thị phiên bản ứng dụng

Yêu cầu:

* Validate trước khi gửi API
* Hiển thị loading khi đăng nhập
* Không cho bấm nhiều lần
* Lưu token bằng Secure Storage
* Không lưu mật khẩu dạng plain text
* Nếu token hết hạn thì chuyển về đăng nhập
* Hiển thị lỗi rõ ràng, dễ hiểu
* Cho phép đăng nhập lại nhanh

Thông báo lỗi nên thân thiện:

```text
Tên đăng nhập hoặc mật khẩu không đúng.
```

```text
Không thể kết nối máy chủ. Vui lòng kiểm tra mạng.
```

---

# 5. Đổi mật khẩu

Màn hình gồm:

* Mật khẩu hiện tại
* Mật khẩu mới
* Nhập lại mật khẩu mới
* Nút hiện/ẩn mật khẩu
* Nút xác nhận

Yêu cầu:

* Kiểm tra độ dài mật khẩu
* Kiểm tra mật khẩu nhập lại
* Không cho mật khẩu mới trùng mật khẩu cũ nếu backend có quy định
* Sau khi đổi thành công có thể yêu cầu đăng nhập lại
* Hiển thị thông báo thành công rõ ràng

---

# 6. Đăng xuất

Khi đăng xuất:

* Hiển thị hộp thoại xác nhận
* Xóa token
* Xóa dữ liệu phiên
* Không xóa cache nghiệp vụ nếu cần dùng lại
* Chuyển về màn hình đăng nhập
* Không cho quay lại màn hình trước bằng nút Back

---

# 7. Màn hình chính

Màn hình chính phải đơn giản, ưu tiên thao tác nhanh.

Hiển thị thông tin cá nhân:

* Họ tên
* Ảnh đại diện hoặc chữ cái đại diện
* Mã nhân viên
* Số điện thoại
* Tuyến thu được phân công
* Trạng thái làm việc
* Thời gian đồng bộ gần nhất

Các chỉ số nhanh:

* Số hộ đã thu hôm nay
* Tổng tiền đã thu hôm nay
* Số hộ chưa thu
* Số phiếu chưa in
* Số hóa đơn chưa phát hành
* Số giao dịch chưa đồng bộ

Các nút chức năng lớn:

```text
[ Ghi thu tiền ]

[ Danh sách hộ ]

[ In lại phiếu ]

[ Thống kê ]

[ Thông báo ]

[ Văn bản ]
```

Ưu tiên nút `Ghi thu tiền` nổi bật nhất.

---

# 8. Thiết kế màn hình ghi thu tiền

Đây là màn hình quan trọng nhất của ứng dụng.

Mục tiêu:

* Tìm hộ nhanh
* Chọn tháng nhanh
* Thu tiền trong ít thao tác nhất
* Hạn chế nhập tay
* Cảnh báo rõ khi thu trùng

Luồng đề xuất:

```text
Chọn tuyến
↓
Tìm hộ
↓
Chọn hộ
↓
Xem lịch sử thu gần nhất
↓
Chọn từ tháng – đến tháng
↓
Xem tiền dự kiến
↓
Xác nhận thu
↓
Phát hành hóa đơn
↓
In phiếu
```

---

# 9. Chọn tuyến thu

Màn hình hoặc dropdown chọn tuyến gồm:

* Danh sách tuyến được phân công
* Số hộ của tuyến
* Số hộ đã thu
* Số hộ chưa thu
* Tỷ lệ hoàn thành
* Tìm kiếm tuyến

Nếu nhân viên chỉ được phân một tuyến:

* Tự động chọn tuyến đó
* Không bắt người dùng chọn lại

Lưu tuyến gần nhất đã chọn.

---

# 10. Tìm kiếm hộ dân

Phải hỗ trợ tìm nhanh theo:

* Tên chủ hộ
* Mã hộ
* Số điện thoại
* Địa chỉ
* Số nhà
* Tên đường
* Tuyến thu

Yêu cầu UX:

* Search box lớn, dễ bấm
* Tìm ngay khi nhập nhưng có debounce
* Không gọi API liên tục
* Cho phép tìm không dấu
* Không phân biệt chữ hoa, chữ thường
* Ưu tiên kết quả đúng tuyến đang chọn
* Hiển thị danh sách dạng card

Mỗi card hộ dân hiển thị:

* Tên chủ hộ
* Mã hộ
* Địa chỉ
* Tuyến thu
* Dịch vụ đang sử dụng
* Tháng đã thu gần nhất
* Số tháng đang nợ
* Trạng thái thu
* Tổng công nợ

Màu trạng thái:

* Xanh: đã thu đủ
* Vàng: còn nợ ít
* Đỏ: nợ nhiều
* Xám: tạm ngừng

Cho phép:

* Vuốt để thao tác nhanh
* Bấm vào card để thu tiền
* Ghim hộ thường xuyên
* Hiển thị hộ đã xem gần đây

---

# 11. Chi tiết hộ dân

Hiển thị:

* Tên chủ hộ
* Mã hộ
* Địa chỉ
* Số điện thoại
* Tuyến thu
* Dịch vụ
* Đơn giá
* Thuế
* Phí
* Trạng thái
* Tháng thu gần nhất
* Khoảng tháng đã thu gần nhất
* Công nợ hiện tại
* Lịch sử thanh toán gần nhất

Các nút:

```text
[ Thu tiền ]

[ Xem lịch sử ]

[ Gọi điện ]

[ Xem vị trí ]
```

Chỉ hiển thị gọi điện hoặc vị trí nếu có dữ liệu.

---

# 12. Chọn tháng thu

Thiết kế phải dễ dùng hơn chọn ngày thông thường.

Không dùng DatePicker ngày.

Sử dụng Month Picker.

Hiển thị:

```text
Từ tháng: 08/2026

Đến tháng: 10/2026
```

Yêu cầu:

* Tự động gợi ý tháng tiếp theo chưa thu
* Hiển thị tháng thu gần nhất
* Hiển thị tổng số tháng đang chọn
* Hiển thị tổng tiền ngay khi chọn
* Không cho chọn tháng kết thúc nhỏ hơn tháng bắt đầu
* Không cho chọn tháng đã thu
* Nếu chọn trùng thì cảnh báo ngay
* Có thể tô màu các tháng:

  * Xanh: đã thu
  * Trắng: chưa thu
  * Vàng: đang chọn
  * Xám: không được chọn

Ví dụ:

```text
Đã thu gần nhất: 03/2026 đến 07/2026

Gợi ý thu tiếp:
08/2026 đến 08/2026
```

Cho phép chọn nhanh:

```text
[ 1 tháng ]

[ 3 tháng ]

[ 6 tháng ]

[ Đến tháng hiện tại ]
```

---

# 13. Tính tiền

Khi chọn tháng, hiển thị chi tiết:

* Dịch vụ
* Đơn giá mỗi tháng
* Số tháng
* Tiền trước thuế
* Thuế
* Phí
* Giảm trừ nếu có
* Tổng thanh toán

Ví dụ:

```text
Dịch vụ sinh hoạt
150.000 đ/tháng

Số tháng: 3

Tiền dịch vụ: 450.000 đ
Thuế: 0 đ
Phí khác: 0 đ

Tổng thanh toán: 450.000 đ
```

Frontend chỉ hiển thị tạm tính.

Backend phải tính lại toàn bộ trước khi lưu.

---

# 14. Xác nhận thu tiền

Trước khi ghi thu, hiển thị màn hình xác nhận gồm:

* Hộ dân
* Địa chỉ
* Khoảng tháng thu
* Số tháng
* Dịch vụ
* Tổng tiền
* Hình thức thanh toán
* Ghi chú

Hình thức thanh toán:

* Tiền mặt
* Chuyển khoản
* Khác

Nút xác nhận phải lớn, rõ:

```text
[ XÁC NHẬN THU TIỀN ]
```

Yêu cầu:

* Không cho bấm hai lần
* Dùng idempotency key nếu API hỗ trợ
* Nếu lỗi mạng phải thông báo rõ trạng thái
* Không tạo giao dịch trùng
* Sau khi thành công hiển thị:

  * Thu thành công
  * Số phiếu
  * Tổng tiền
  * Nút phát hành hóa đơn
  * Nút in phiếu
  * Nút quay lại danh sách

---

# 15. Chế độ mạng yếu và offline

Ứng dụng phải xử lý tốt khi mạng yếu.

Yêu cầu:

* Cache danh sách tuyến
* Cache danh sách hộ theo tuyến
* Cache thông tin dịch vụ
* Cache lịch sử thu gần nhất
* Hiển thị dữ liệu cache khi mất mạng
* Có trạng thái dữ liệu cũ
* Có nút đồng bộ lại
* Không khóa toàn bộ ứng dụng khi mất mạng

Nếu cho phép ghi thu offline:

* Lưu giao dịch cục bộ
* Sinh mã local tạm thời
* Đánh dấu `Chưa đồng bộ`
* Khi có mạng thì đồng bộ
* Không tạo giao dịch trùng
* Kiểm tra lại khoảng tháng với server trước khi xác nhận đồng bộ
* Nếu phát hiện trùng thì chuyển trạng thái `Cần xử lý`
* Không tự động ghi đè dữ liệu server

Các trạng thái đồng bộ:

* Đã đồng bộ
* Chưa đồng bộ
* Đang đồng bộ
* Đồng bộ lỗi
* Cần xử lý

---

# 16. Xuất hóa đơn điện tử

Sau khi thu tiền thành công:

* Cho phép phát hành hóa đơn
* Hiển thị trạng thái hóa đơn
* Cho phép phát hành lại nếu lỗi
* Xem nội dung lỗi
* Tra cứu hóa đơn
* Mở link hóa đơn nếu backend trả về
* Tải PDF hóa đơn nếu có
* Chia sẻ hóa đơn nếu được phép

Trạng thái:

* Chưa phát hành
* Đang xử lý
* Đã phát hành
* Phát hành lỗi
* Đã hủy

Yêu cầu:

* Không để người dùng phát hành trùng
* Nút phát hành phải có loading
* Nếu lỗi phải hiển thị nội dung dễ hiểu
* Không hiển thị lỗi kỹ thuật thô từ VNPT
* Không cho sửa số tiền sau khi đã phát hành hóa đơn

---

# 17. In phiếu thu Bluetooth

Ứng dụng cần hỗ trợ máy in nhiệt Bluetooth:

```text
Bluetooth RI-5809DD
```

Trước khi triển khai, hãy kiểm tra:

* Máy hỗ trợ chuẩn Bluetooth Classic hay BLE
* Máy dùng ESC/POS hay giao thức riêng
* Khổ giấy 58 mm
* Số ký tự mỗi dòng
* Hỗ trợ tiếng Việt có dấu hay không
* Có SDK từ nhà sản xuất hay không

Ưu tiên:

* Dùng thư viện Flutter hỗ trợ Bluetooth ESC/POS ổn định
* Không khóa cứng theo một máy duy nhất
* Thiết kế lớp PrinterService để dễ thay đổi máy sau này

Chức năng:

* Quét thiết bị Bluetooth
* Hiển thị danh sách máy in
* Kết nối
* Ngắt kết nối
* Lưu máy in mặc định
* Tự động kết nối lại
* In thử
* In phiếu thu
* In lại phiếu
* Kiểm tra trạng thái kết nối
* Xử lý lỗi hết giấy, mất kết nối nếu thiết bị hỗ trợ

---

# 18. Mẫu phiếu thu POS58

Phiếu thu cần hiển thị:

```text
BAN QUẢN LÝ PHƯỜNG AN KHÊ
PHIẾU THU TIỀN DỊCH VỤ

Số phiếu: PT000123
Ngày thu: 29/07/2026 14:30

Chủ hộ: Nguyễn Văn A
Mã hộ: HD000123
Địa chỉ: 12 Nguyễn Huệ
Tuyến: Tuyến trung tâm

Dịch vụ: Thu gom rác sinh hoạt
Thu từ: 08/2026
Đến: 10/2026
Số tháng: 3

Đơn giá: 150.000 đ/tháng
Tổng tiền: 450.000 đ

Hình thức: Tiền mặt
Nhân viên thu: Trần Văn B

Cảm ơn quý hộ đã thanh toán.
```

Yêu cầu:

* Canh lề phù hợp khổ 58 mm
* Chữ rõ
* Không in quá nhỏ
* Tự xuống dòng địa chỉ dài
* Không vỡ chữ tiếng Việt
* Có thể in QR tra cứu hóa đơn
* Có thể in logo đơn vị nếu máy hỗ trợ
* Cho phép cấu hình bật/tắt logo và QR
* Có nút in lại
* Lưu lịch sử in

Nếu máy không hỗ trợ tiếng Việt Unicode:

* Kiểm tra cách in bằng ảnh bitmap
* Hoặc chuyển nội dung phiếu thành bitmap trước khi in
* Ưu tiên giữ tiếng Việt có dấu đầy đủ

---

# 19. Thống kê thu

Màn hình thống kê cho nhân viên gồm:

## Hôm nay

* Tổng số hộ đã thu
* Tổng số tiền
* Tiền mặt
* Chuyển khoản
* Số hóa đơn đã phát hành
* Số phiếu chưa in
* Số giao dịch chưa đồng bộ

## Theo thời gian

Bộ lọc:

* Hôm nay
* Tuần này
* Tháng này
* Khoảng ngày

Hiển thị:

* Số hộ đã thu
* Tổng số tiền
* Số giao dịch
* Tỷ lệ hoàn thành tuyến
* Hộ chưa thu
* Công nợ còn lại

Có biểu đồ đơn giản:

* Thu theo ngày
* Thu theo tuyến
* Tiền mặt và chuyển khoản

Không dùng biểu đồ quá phức tạp trên mobile.

---

# 20. Danh sách giao dịch

Hiển thị giao dịch gần nhất:

* Thời gian
* Hộ dân
* Khoảng tháng thu
* Số tiền
* Trạng thái đồng bộ
* Trạng thái hóa đơn
* Trạng thái in

Cho phép:

* Tìm kiếm
* Lọc
* Xem chi tiết
* Phát hành hóa đơn
* In lại
* Đồng bộ lại
* Xem lỗi

---

# 21. Thông báo điều hành

Ứng dụng có module thông báo điều hành.

Danh sách hiển thị:

* Tiêu đề
* Nội dung tóm tắt
* Ngày ban hành
* Người ban hành
* Mức độ ưu tiên
* Trạng thái đã đọc/chưa đọc
* Có file đính kèm hay không

Mức độ:

* Bình thường
* Quan trọng
* Khẩn

Chức năng:

* Xem danh sách
* Xem chi tiết
* Đánh dấu đã đọc
* Lọc chưa đọc
* Tìm kiếm
* Nhận push notification nếu hệ thống hỗ trợ Firebase
* Hiển thị badge số thông báo chưa đọc

Thông báo khẩn phải nổi bật nhưng không gây khó chịu.

---

# 22. Chi tiết thông tin điều hành

Hiển thị:

* Tiêu đề
* Nội dung đầy đủ
* Người ban hành
* Ngày ban hành
* Đơn vị ban hành
* Mức độ ưu tiên
* File đính kèm
* Danh sách người đã đọc nếu có quyền
* Thời gian người dùng đã đọc

Cho phép:

* Mở file
* Tải file
* Chia sẻ file nếu được phép
* Đánh dấu đã đọc
* Ghim thông báo

---

# 23. Văn bản và tài liệu

Danh mục tài liệu:

* Văn bản điều hành
* Hướng dẫn nghiệp vụ
* Biểu mẫu
* Tài liệu đào tạo
* Quy trình nội bộ
* Tài liệu khác

Thông tin tài liệu:

* Tên tài liệu
* Loại tài liệu
* Số ký hiệu
* Ngày ban hành
* Cơ quan ban hành
* Mô tả
* File đính kèm
* Dung lượng
* Định dạng

Hỗ trợ:

* PDF
* Word
* Excel
* Hình ảnh

Yêu cầu:

* Xem PDF trực tiếp trong app
* Mở tài liệu bằng ứng dụng ngoài nếu cần
* Tải về thiết bị
* Hiển thị tiến trình tải
* Cache file đã mở gần đây
* Tìm kiếm theo tên
* Lọc theo loại
* Hiển thị tài liệu mới

---

# 24. Điều hướng chính

Sử dụng Bottom Navigation với tối đa 5 mục:

```text
Trang chủ

Thu tiền

Giao dịch

Thông báo

Cá nhân
```

Mục `Văn bản` có thể đặt trong:

* Trang chủ
* Cá nhân
* Hoặc menu mở rộng

Không đặt quá nhiều tab dưới cùng.

---

# 25. Màn hình cá nhân

Hiển thị:

* Ảnh đại diện
* Họ tên
* Mã nhân viên
* Số điện thoại
* Tuyến được phân công
* Phiên bản ứng dụng
* Thời gian đồng bộ gần nhất

Menu:

* Thông tin cá nhân
* Đổi mật khẩu
* Máy in Bluetooth
* Đồng bộ dữ liệu
* Cấu hình
* Hướng dẫn sử dụng
* Đăng xuất

---

# 26. Tối ưu hiệu năng

Bắt buộc tối ưu:

## Danh sách hộ dân

* Pagination hoặc lazy loading
* Không tải toàn bộ dữ liệu quá lớn trong một lần
* Cache theo tuyến
* Debounce tìm kiếm
* Không rebuild toàn bộ màn hình khi chỉ đổi một item
* Dùng ListView.builder
* Sử dụng key ổn định
* Tối ưu ảnh thumbnail
* Không decode ảnh lớn trực tiếp

## API

* Hủy request cũ khi nhập tìm kiếm mới
* Timeout hợp lý
* Retry có kiểm soát
* Không retry nghiệp vụ thu tiền tự động nếu có nguy cơ tạo trùng
* Gộp API nếu giảm được số request
* Cache dữ liệu danh mục

## State

* Không dùng một provider khổng lồ
* Tách state theo màn hình
* Chỉ lắng nghe dữ liệu cần thiết
* Tránh gọi API lại khi chuyển tab

## File

* Tải file theo luồng
* Hiển thị tiến trình tải
* Cache file theo dung lượng giới hạn
* Xóa cache cũ tự động

---

# 27. Trải nghiệm người dùng

Nguyên tắc:

* Nút chính phải nằm trong vùng ngón tay dễ chạm
* Kích thước nút tối thiểu phù hợp mobile
* Chữ dễ đọc ngoài trời
* Không dùng font quá nhỏ
* Tương phản màu tốt
* Không bắt nhập lại dữ liệu không cần thiết
* Luôn giữ tuyến gần nhất
* Luôn gợi ý tháng tiếp theo
* Cho phép thao tác bằng một tay
* Hạn chế modal chồng modal
* Các tác vụ chính chỉ nên cần 3–5 bước

Dùng phản hồi:

* SnackBar cho thông báo nhẹ
* Dialog cho xác nhận quan trọng
* Full-screen error cho lỗi không thể tiếp tục
* Skeleton cho tải dữ liệu
* Empty state có hướng dẫn rõ

---

# 28. Thiết kế giao diện

Phong cách:

* Hiện đại
* Gọn
* Chuyên nghiệp
* Phù hợp đơn vị hành chính
* Tối ưu sử dụng ngoài trời

Màu gợi ý:

```css
Primary: #2563EB
Success: #16A34A
Warning: #F59E0B
Danger: #DC2626
Background: #F5F7FB
Card: #FFFFFF
Text: #1E293B
Muted: #64748B
```

Yêu cầu:

* Card bo góc nhẹ
* Shadow nhẹ
* Icon đồng nhất
* Không dùng quá nhiều gradient
* Không lạm dụng animation
* Có dark mode nếu cần nhưng không ưu tiên hơn khả năng đọc ngoài trời

---

# 29. API dự kiến

Kiểm tra API thực tế trước khi sử dụng.

## Authentication

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/change-password
GET  /api/v1/auth/profile
```

## Tuyến và hộ dân

```text
GET /api/v1/mobile/routes
GET /api/v1/mobile/routes/{id}/households
GET /api/v1/mobile/households
GET /api/v1/mobile/households/{id}
GET /api/v1/mobile/households/{id}/payment-history
GET /api/v1/mobile/households/{id}/payment-suggestion
```

## Thu tiền

```text
POST /api/v1/mobile/payments/preview
POST /api/v1/mobile/payments
GET  /api/v1/mobile/payments
GET  /api/v1/mobile/payments/{id}
```

## Hóa đơn

```text
POST /api/v1/mobile/payments/{id}/issue-invoice
GET  /api/v1/mobile/payments/{id}/invoice
POST /api/v1/mobile/payments/{id}/retry-invoice
```

## Thống kê

```text
GET /api/v1/mobile/statistics/summary
GET /api/v1/mobile/statistics/daily
GET /api/v1/mobile/statistics/routes
```

## Thông báo

```text
GET  /api/v1/mobile/announcements
GET  /api/v1/mobile/announcements/{id}
POST /api/v1/mobile/announcements/{id}/read
```

## Tài liệu

```text
GET /api/v1/mobile/documents
GET /api/v1/mobile/documents/{id}
GET /api/v1/mobile/documents/{id}/download
```

## Đồng bộ

```text
GET  /api/v1/mobile/sync/master-data
POST /api/v1/mobile/sync/payments
GET  /api/v1/mobile/sync/status
```

---

# 30. Cấu trúc thư mục đề xuất

Chỉ áp dụng nếu phù hợp với source hiện tại.

```text
lib/
├── app/
│   ├── app.dart
│   ├── router.dart
│   └── theme.dart
├── core/
│   ├── api/
│   ├── database/
│   ├── errors/
│   ├── storage/
│   ├── printer/
│   ├── network/
│   ├── utils/
│   └── widgets/
├── features/
│   ├── auth/
│   ├── home/
│   ├── households/
│   ├── payments/
│   ├── invoices/
│   ├── printing/
│   ├── statistics/
│   ├── announcements/
│   ├── documents/
│   ├── sync/
│   └── profile/
└── main.dart
```

Mỗi feature nên có:

```text
data/
domain/
presentation/
```

Không bắt buộc áp dụng Clean Architecture quá cứng nếu làm dự án phức tạp hơn mà không mang lại lợi ích.

---

# 31. Phân quyền

Ứng dụng phải dựa trên quyền backend trả về.

Ví dụ:

```text
mobile.payment.view
mobile.payment.create
mobile.payment.print
mobile.invoice.issue
mobile.statistics.view
mobile.announcement.view
mobile.document.view
```

Yêu cầu:

* Ẩn menu nếu không có quyền
* Ẩn nút nếu không có quyền
* Backend vẫn phải kiểm tra quyền
* Không chỉ dựa vào frontend

---

# 32. Bảo mật

* Lưu token bằng Secure Storage
* Không log token
* Không log mật khẩu
* Không lưu dữ liệu nhạy cảm dạng plain text
* Kiểm tra SSL ở production
* Không cho phép HTTP production
* Có timeout phiên
* Xóa dữ liệu phiên khi logout
* Bảo vệ file cache nếu có dữ liệu nhạy cảm
* Không để API base URL cứng trong source
* Dùng environment hoặc flavor

Các flavor:

```text
development
staging
production
```

---

# 33. Kiểm thử

Cần có test tối thiểu:

* Đăng nhập thành công
* Đăng nhập sai
* Token hết hạn
* Tìm kiếm hộ
* Chọn khoảng tháng hợp lệ
* Cảnh báo tháng đã thu
* Preview tiền
* Ghi thu thành công
* Không gửi trùng giao dịch
* In phiếu
* Mất kết nối máy in
* Phát hành hóa đơn
* Đồng bộ offline
* Tải thông báo
* Xem tài liệu

Thực hiện:

* Unit Test cho service
* Widget Test cho màn hình quan trọng
* Integration Test cho luồng ghi thu

---

# 34. Thứ tự triển khai

Không triển khai toàn bộ cùng lúc.

## Giai đoạn 1 – Nền tảng

1. Cấu trúc dự án
2. Theme
3. API client
4. Secure Storage
5. Router
6. Authentication
7. Profile

## Giai đoạn 2 – Danh sách hộ

1. Tuyến thu
2. Danh sách hộ
3. Tìm kiếm
4. Chi tiết hộ
5. Cache dữ liệu

## Giai đoạn 3 – Thu tiền

1. Chọn tháng
2. Preview tiền
3. Xác nhận thu
4. Lịch sử thu
5. Chống giao dịch trùng

## Giai đoạn 4 – In và hóa đơn

1. Kết nối Bluetooth
2. In thử
3. In phiếu
4. In lại
5. Phát hành hóa đơn
6. Theo dõi trạng thái

## Giai đoạn 5 – Thống kê và điều hành

1. Thống kê
2. Thông báo
3. Văn bản
4. Tài liệu

## Giai đoạn 6 – Offline và tối ưu

1. SQLite
2. Đồng bộ
3. Xử lý xung đột
4. Tối ưu danh sách
5. Test
6. Build release

---

# 35. Yêu cầu khi thực hiện bằng Codex

Trước khi code:

1. Đọc toàn bộ source app hiện tại
2. Đọc `docs/ai`
3. Đọc API backend hiện có
4. Kiểm tra cấu trúc response
5. Kiểm tra authentication
6. Kiểm tra quyền
7. Liệt kê file sẽ tạo và sửa
8. Đề xuất package cần thêm
9. Không cài package trùng chức năng
10. Không tự ý đổi package lớn nếu chưa cần

Sau khi code:

* Chạy `flutter analyze`
* Chạy test
* Chạy build debug
* Sửa toàn bộ lỗi
* Báo cáo package đã thêm
* Báo cáo file đã tạo
* Báo cáo file đã sửa
* Báo cáo API đang dùng
* Báo cáo dữ liệu đang mock
* Báo cáo phần backend còn thiếu

---

# 36. Kết quả mong muốn

Ứng dụng cuối cùng phải giúp nhân viên thực hiện luồng thu tiền nhanh:

```text
Mở app
↓
Chọn tuyến
↓
Tìm hộ
↓
Chọn tháng
↓
Xác nhận thu
↓
In phiếu
```

Mục tiêu trải nghiệm:

* Có thể tìm hộ trong vài giây
* Không phải nhập lại dữ liệu nhiều lần
* Không thu trùng tháng
* Không tạo giao dịch trùng
* In phiếu nhanh
* Hoạt động được khi mạng yếu
* Dữ liệu đồng bộ an toàn
* Giao diện dễ dùng với người không rành công nghệ
