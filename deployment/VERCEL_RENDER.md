# Deploy demo: Vercel + Render + TiDB Cloud

## 1. Tạo database MySQL tương thích trên TiDB Cloud

1. Đăng ký TiDB Cloud và tạo cluster **Starter** miễn phí.
2. Chọn khu vực gần người dùng nhất.
3. Trong **Connect**, cho phép kết nối từ mọi IP để Render truy cập được. Chỉ dùng cách này cho môi trường demo.
4. Ghi lại `host`, `port`, `database`, `username`, `password`. TiDB Starter thường dùng port `4000` và bắt buộc TLS.

Không đưa thông tin kết nối database vào repository.

## 2. Deploy Laravel API trên Render

Đẩy repository lên GitHub/GitLab, vào Render chọn **New > Blueprint** và chọn repository. Render đọc file `render.yaml` ở thư mục gốc.

Khi Render yêu cầu biến bí mật, nhập:

```env
APP_KEY=base64:KHOA_UNG_DUNG
APP_URL=https://bql-ankhe-api.onrender.com
FRONTEND_URL=https://TEN_DU_AN.vercel.app
DB_HOST=HOST_TIDB
DB_DATABASE=TEN_DATABASE_TIDB
DB_USERNAME=USER_TIDB
DB_PASSWORD=PASSWORD_TIDB
```

Nếu TiDB cung cấp port khác `4000`, sửa `DB_PORT` trên Render. Tạo `APP_KEY` ổn định bằng lệnh local:

```powershell
cd backend
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan key:generate --show
```

Lần deploy đầu giữ `RUN_SEEDER=true`. Sau khi đăng nhập được, đổi thành `false` trong Environment của Render và redeploy. Seeder tạo tài khoản demo `admin` / `Admin@123`; cần đổi mật khẩu ngay.

API có thể mất khoảng một phút để thức dậy sau 15 phút không có truy cập. Kiểm tra API tại:

```text
https://bql-ankhe-api.onrender.com/up
```

Với môi trường demo, `SESSION_DRIVER` và `CACHE_STORE` dùng `file` để tránh tạo thêm truy vấn tới TiDB cho mỗi request. Token đăng nhập và dữ liệu nghiệp vụ vẫn được lưu trong database.

## 3. Deploy Vue/Vite trên Vercel

Import cùng repository và đặt:

- Root Directory: `web-admin`
- Framework Preset: Vite
- Build Command: `npm run build`
- Output Directory: `dist`
- Install Command: `npm ci`

Environment variable:

```env
VITE_API_URL=https://bql-ankhe-api.onrender.com/api/v1
```

Sau khi có domain Vercel chính xác, cập nhật `FRONTEND_URL` trên Render rồi redeploy backend. Nếu đổi `VITE_API_URL`, phải redeploy Vercel.

## 4. Giới hạn quan trọng của Render Free

- Service ngủ sau 15 phút không có request; lần mở đầu có thể chờ khoảng một phút.
- Filesystem là tạm thời. Avatar, tài liệu, ảnh vật tư và chứng từ upload có thể mất khi service restart, sleep hoặc redeploy.
- Dữ liệu nghiệp vụ trong TiDB vẫn được giữ.

Để demo upload bền vững cần chuyển filesystem Laravel sang một object storage. Không dùng Render Free để vận hành chính thức.

## 5. Checklist kiểm tra

1. `/up` trả HTTP 200.
2. Đăng nhập web bằng tài khoản demo.
3. Mở trực tiếp `/payments` rồi refresh để kiểm tra SPA rewrite của Vercel.
4. Tạo thử một hộ dân và một phiếu thu, sau đó kiểm tra dữ liệu vẫn còn sau khi backend thức dậy lại.
5. Kiểm tra CORS nếu domain Vercel thay đổi.
