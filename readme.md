# Hệ thống Quản lý Thư viện (Library Management System) - DTC241200004

- Mã số sinh viên: "DTC241200004"
- Học phần / Đề tài: Triển khai Ứng dụng Web Quản lý Thư viện (Quản lý Sách, Độc giả, Mượn/Trả sách) tích hợp Giám sát (Prometheus + Grafana), Log tập trung (Loki + Promtail) và Bảo mật hệ thống (Hardening).
- Công nghệ sử dụng (Tech Stack):
  - Ứng dụng Web: PHP 8.2-FPM (Alpine, Non-root container)
  - Cơ sở dữ liệu: PostgreSQL 15 + pgAdmin 4
  - Reverse Proxy: Nginx Unprivileged (Hỗ trợ HTTP "8080", HTTPS tự ký "8443" & HTTP Security Headers)
  - Giám sát (Monitoring): Prometheus + Grafana + cAdvisor + Nginx Exporter + Postgres Exporter
  - Log tập trung (Centralized Logging): Grafana Loki + Promtail (truy vấn bằng LogQL)

---

## 1. Cấu trúc thư mục dự án

""
dtc241200004_QuanLyThuVien/
├── app/
│   ├── Dockerfile                 # Build PHP 8.2-FPM chạy dưới quyền non-root (UID 10001)
│   ├── db.php                     # Kết nối PDO PostgreSQL & ghi log JSON cho Loki
│   └── index.php                  # Giao diện & nghiệp vụ Quản lý Sách, Độc giả, Mượn/Trả sách
├── database/
│   └── init.sql                   # Khởi tạo bảng (books, readers, borrow_records) & dữ liệu mẫu
├── nginx/
│   ├── Dockerfile                 # Nginx non-root (UID 101) + tự động sinh chứng chỉ SSL tự ký
│   └── default.conf               # Cấu hình Reverse Proxy, HTTPS, Security Headers & stub_status
├── prometheus/
│   ├── prometheus.yml             # Cấu hình thu thập metrics từ cAdvisor, Nginx, PostgreSQL
│   └── grafana-datasources.yml    # Tự động cấu hình Datasource Prometheus & Loki cho Grafana
├── loki/
│   ├── loki-config.yml            # Cấu hình Loki Server lưu trữ log tập trung
│   └── logql-queries.md           # Tổng hợp các câu truy vấn LogQL mẫu
├── promtail/
│   └── promtail-config.yml        # Cấu hình thu thập log từ Nginx, App PHP và Docker Containers
├── .env.example                   # File mẫu biến môi trường (Mật khẩu mạnh)
├── .gitignore                     # Loại trừ file nhạy cảm (.env) và dữ liệu runtime
├── docker-compose.yml             # Định nghĩa toàn bộ 11 services, 3 mạng cô lập & Hardening
└── readme.md                      # Tài liệu hướng dẫn triển khai và vận hành hệ thống
""

## 2. Kiến trúc hệ thống & Cô lập mạng (Network Isolation)

Hệ thống được chia thành 3 mạng Docker tách biệt để đảm bảo an toàn:
1. "frontend_net": Kết nối giữa "nginx" (Reverse Proxy) và "app" (PHP-FPM) cùng giao diện "pgadmin".
2. "backend_net" ("internal: true"): Mạng nội bộ hoàn toàn cô lập với Internet bên ngoài, chỉ cho phép "app", "pgadmin" và "postgres-exporter" giao tiếp với "postgres". Container "postgres" không mở cổng trực tiếp ra máy chủ host.
3. "monitoring_net": Kết nối "prometheus", "grafana", "loki", "promtail", "cadvisor", "nginx-exporter", "postgres-exporter".

---

## 3. Hướng dẫn triển khai nhanh

### Bước 1: Chuẩn bị file cấu hình môi trường ".env"
""bash
cp .env.example .env
""

### Bước 2: Khởi chạy toàn bộ hệ thống bằng Docker Compose
""bash
docker compose up -d --build
""

### Bước 3: Kiểm tra trạng thái các Container
""bash
docker compose ps
""

### Bước 4: Danh sách địa chỉ truy cập dịch vụ

| Dịch vụ | Địa chỉ URL | Thông tin đăng nhập / Ghi chú |
| :--- | :--- | :--- |
| Web Quản lý Thư viện (HTTP) | http://localhost:8080 | Giao diện có menu riêng: Tổng quan, Quản lý sách, Quản lý độc giả, Mượn / trả sách |
| Web Quản lý Thư viện (HTTPS) | https://localhost:8443 | Chứng chỉ SSL tự ký ("CN=dtc241200004.local") |
| pgAdmin 4 | http://localhost:5050 | Email: xem biến `PGADMIN_DEFAULT_EMAIL` trong `.env`<br>Password: xem biến `PGADMIN_DEFAULT_PASSWORD` trong `.env` |
| PostgreSQL (Từ pgAdmin) | Host: "postgres" (Port "5432") | DB, user và password: xem các biến `POSTGRES_DB`, `POSTGRES_USER` và `POSTGRES_PASSWORD` trong `.env` |
| Prometheus | http://localhost:9090 | Kiểm tra "Status -> Targets" (4 targets "UP") |
| Grafana | http://localhost:3000 | User và password: xem các biến `GF_SECURITY_ADMIN_USER` và `GF_SECURITY_ADMIN_PASSWORD` trong `.env` |
| Loki API | http://localhost:3100/ready | Kiểm tra trạng thái Loki |

> Các thông tin đăng nhập được cấu hình trong file `.env` tại thư mục gốc dự án. Nếu chưa có file này, sao chép `.env.example` thành `.env`, thay các giá trị mẫu bằng thông tin riêng trước khi chạy hệ thống. Không chia sẻ hoặc commit file `.env`.

---

## 4. Giám sát hệ thống với Prometheus + Grafana

Prometheus thu thập metrics định kỳ mỗi "15s" từ 4 mục tiêu:
1. "docker_containers" ("cadvisor:8080"): Giám sát CPU, RAM, Network I/O của từng container ("dtc241200004_*").
2. "nginx_webserver" ("nginx-exporter:9113"): Giám sát số kết nối đang hoạt động ("nginx_connections_active"), tổng số request ("nginx_http_requests_total").
3. "postgresql_database" ("postgres-exporter:9187"): Giám sát kết nối DB ("pg_stat_database_numbackends"), số lượng giao dịch commit/rollback, kích thước DB.
4. "prometheus" ("localhost:9090"): Giám sát trạng thái Prometheus Server.

Một số câu truy vấn PromQL tiêu biểu trên Prometheus / Grafana:
- Trạng thái sống của các dịch vụ:
  ""promql
  up
  ""
- Tốc độ request vào Nginx Reverse Proxy (requests/s):
  ""promql
  rate(nginx_http_requests_total[5m])
  ""
- Số kết nối đang hoạt động tới Database "library_db":
  ""promql
  pg_stat_database_numbackends{datname="library_db"}
  ""
- Lượng RAM tiêu thụ của các container thuộc dự án:
  ""promql
  container_memory_usage_bytes{name=~"dtc241200004.*"}
  ""

---

## 5. Hệ thống Log tập trung Loki + Promtail & Truy vấn LogQL

Promtail tự động thu thập log từ:
- Nginx Reverse Proxy ("/var/log/nginx/access.log" & "error.log") với nhãn "job="nginx"".
- Ứng dụng PHP Quản lý Thư viện ("/var/log/app/library.log") định dạng JSON với nhãn "job="library_app"", tự động bóc tách nhãn "level" ("INFO", "WARN", "ERROR") và "action" ("BORROW_BOOK", "RETURN_BOOK", "ADD_BOOK", "ADD_READER").
- Docker Containers với nhãn "job="docker_containers"".

### Các câu truy vấn LogQL (Truy cập Grafana -> Explore -> Chọn Datasource "Loki"):

1. Truy vấn toàn bộ log truy cập của Nginx Reverse Proxy:
   ""logql
   {job="nginx", student_id="dtc241200004"}
   ""

2. Truy vấn lọc các request HTTP lỗi (4xx và 5xx) trên Nginx:
   ""logql
   {job="nginx"} |~ "\" (4[0-9]{2}|5[0-9]{2}) "
   ""

3. Truy vấn log nghiệp vụ Mượn sách và Trả sách từ ứng dụng Quản lý Thư viện:
   ""logql
   {job="library_app", action=~"BORROW_BOOK|RETURN_BOOK"}
   ""

4. Truy vấn các sự kiện cảnh báo ("WARN") hoặc lỗi ("ERROR") trong ứng dụng:
   ""logql
   {job="library_app", level=~"WARN|ERROR"}
   ""

5. Thống kê tổng số dòng log theo từng dịch vụ trong khoảng thời gian 5 phút:
   ""logql
   sum by (job) (count_over_time({student_id="dtc241200004"}[5m]))
   ""

---

## 6. Các biện pháp Bảo mật & Hardening đã áp dụng

1. Non-root Container:
   - Container "app" (PHP-FPM) được tạo và chạy dưới tài khoản "appuser" ("UID 10001:10001"), không chạy dưới quyền "root".
   - Container "nginx" sử dụng base image "nginxinc/nginx-unprivileged:1.25-alpine", chạy dưới quyền "UID 101".
2. Network Isolation (Cô lập mạng):
   - Tách biệt thành 3 mạng "frontend_net", "backend_net" ("internal: true") và "monitoring_net".
   - Cơ sở dữ liệu "postgres" chỉ nằm trong "backend_net" ("internal: true") và không mở port ra máy host, ngăn chặn hoàn toàn truy cập trực tiếp từ bên ngoài.
3. Mật khẩu mạnh & Quản lý thông tin nhạy cảm:
   - Đặt mật khẩu riêng, đủ mạnh cho PostgreSQL, pgAdmin và Grafana trong file ".env"; không dùng lại mật khẩu giữa các dịch vụ.
   - Lưu trữ trong ".env" và chặn đẩy lên Git thông qua ".gitignore" (chỉ đẩy ".env.example").
4. Hạn chế quyền Container ("cap_drop", "no-new-privileges", "read-only" mounts, Resource Limits):
   - Thiết lập "security_opt: ["no-new-privileges:true"]" ngăn chặn leo thang đặc quyền.
   - Thiết lập "cap_drop: ["ALL"]" thu hồi toàn bộ Linux capabilities không cần thiết.
   - Mount mã nguồn và file cấu hình ở chế độ chỉ đọc (":ro").
   - Giới hạn CPU & RAM ("deploy.resources.limits") để chống tấn công làm cạn kiệt tài nguyên (DoS).
5. Nginx HTTPS tự ký & Security Headers:
   - Tắt hiển thị phiên bản máy chủ ("server_tokens off;" trên Nginx và "expose_php = Off" trên PHP).
   - Cấu hình chứng chỉ SSL/TLS tự ký (TLSv1.2 / TLSv1.3) tại cổng "8443".
   - Bổ sung đầy đủ các HTTP Security Headers:
     - "Strict-Transport-Security: max-age=31536000; includeSubDomains"
     - "X-Frame-Options: SAMEORIGIN"
     - "X-Content-Type-Options: nosniff"
     - "X-XSS-Protection: 1; mode=block"
     - "Referrer-Policy: strict-origin-when-cross-origin"
     - "Permissions-Policy: geolocation=(), microphone=(), camera=()"
     - "Content-Security-Policy"

---

## 7. Lịch sử Git Commit & Hướng dẫn đẩy lên GitHub

Dự án được tổ chức thành các commit rõ ràng theo đúng yêu cầu bài tập:
- Commit 1: Triển khai ứng dụng Web Quản lý Thư viện, PostgreSQL, pgAdmin và Nginx reverse proxy (HTTPS + Security Headers).
- Commit 2: Tích hợp Prometheus, Grafana và Exporters giám sát container, Nginx và PostgreSQL.
- Commit 3: Triển khai hệ thống log tập trung Loki + Promtail và truy vấn LogQL.
- Commit 4: Áp dụng các biện pháp Hardening (non-root, network isolation, hạn chế quyền) và hoàn thiện tài liệu README.

Các lệnh đẩy lên GitHub cá nhân ("dtc241200004"):
""bash
git remote add origin https://github.com/dtc241200004/dtc241200004_QuanLyThuVien.git
git branch -M main
git push -u origin main
""
