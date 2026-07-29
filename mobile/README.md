# BQL An Khê Collector

Ứng dụng Flutter dành cho nhân viên thu phí. API không được hardcode khi build release; truyền bằng `--dart-define`.

## Chạy development

```bash
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Với điện thoại thật, thay `10.0.2.2` bằng IP LAN hoặc domain HTTPS của máy chủ.

## Build

```bash
flutter analyze
flutter test
flutter build apk --debug --dart-define=API_BASE_URL=https://example.vn/api/v1
```

## Phạm vi hiện tại

- Sanctum login/logout, token trong Secure Storage.
- Trang chủ và thống kê thu trong ngày.
- Tuyến thu, tìm hộ có debounce, xem trước tiền và ghi thu.
- Danh sách giao dịch, thông báo điều hành, hồ sơ.
- API URL qua environment.

## Giai đoạn tiếp theo

- Xác minh RI-5809DD dùng Bluetooth Classic/BLE và ESC/POS trước khi chọn package máy in.
- Drift/SQLite, hàng đợi offline và idempotency server.
- Xem/tải tài liệu, PDF hóa đơn, phát hành hóa đơn trong app.
- Android/iOS platform scaffold phải được sinh bằng Flutter SDK (`flutter create .`) trước lần build đầu tiên.
