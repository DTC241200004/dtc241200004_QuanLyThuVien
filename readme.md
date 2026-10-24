# Hệ thống Quản lý Thư viện - DTC241200004

Dự án triển khai **Hệ thống Quản lý Thư viện** (Quản lý Sách, Độc giả, Mượn/Trả sách) trên nền tảng Docker Compose.

## Thành phần hệ thống (Giai đoạn 1)
- **Web App**: PHP 8.2-FPM (`app/`)
- **Database**: PostgreSQL 15 (`database/init.sql`)
- **Quản trị CSDL**: pgAdmin 4
- **Reverse Proxy**: Nginx (hỗ trợ HTTP `8080`, HTTPS tự ký `8443` và Security Headers)
