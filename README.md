# Hệ thống quản lý thu phí rác phường An Khê

Monorepo gồm REST API Laravel 13 (`backend`) và Vue 3 Admin (`web-admin`). Luồng chính hỗ trợ quản lý hộ dân, dịch vụ, tuyến thu, người dùng, thu phí theo khoảng tháng, hóa đơn chờ phát hành VNPT, công nợ, log và dashboard.

## Chạy môi trường phát triển

Yêu cầu: PHP 8.3+, Composer, Node.js 22+ và MySQL 8 (có thể dùng SQLite để phát triển).

```bash
cd backend
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

```bash
cd web-admin
copy .env.example .env
npm install
npm run dev
```

Mặc định Vue gọi API tại `http://localhost:8000/api/v1`. Tài khoản seed:

- Email: `admin@ankhe.local`
- Mật khẩu: `Admin@123`

Hãy đổi mật khẩu ngay khi đưa hệ thống lên môi trường dùng thật.

## Kiểm tra

```bash
cd backend && php artisan test
cd web-admin && npm run build
```

## Ghi chú tích hợp

- Hóa đơn VNPT hiện được tạo ở trạng thái `CHO_PHAT_HANH`; cần bổ sung thông tin endpoint/chữ ký do VNPT cung cấp trước khi gọi thật.
- Flutter Collector chưa có trong mã nguồn ban đầu. API đã dùng bearer token và response thống nhất để ứng dụng di động có thể tích hợp ở giai đoạn tiếp theo.
