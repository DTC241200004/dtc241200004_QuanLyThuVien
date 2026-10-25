# Danh sách Truy vấn LogQL (Grafana Loki) - DTC241200004

Dưới đây là các câu truy vấn **LogQL** dùng trong **Grafana -> Explore -> Chọn Datasource: `Loki`**:

## Query 1: Truy vấn toàn bộ log truy cập và lỗi từ Nginx Reverse Proxy
```logql
{job="nginx", student_id="dtc241200004"}
```

## Query 2: Lọc các request HTTP lỗi (4xx hoặc 5xx) trên Nginx
```logql
{job="nginx"} |~ "\" (4[0-9]{2}|5[0-9]{2}) "
```

## Query 3: Truy vấn log nghiệp vụ Mượn / Trả sách từ ứng dụng Quản lý Thư viện
```logql
{job="library_app"} |= "BORROW_BOOK" or {job="library_app"} |= "RETURN_BOOK"
```
Hoặc lọc theo nhãn JSON đã được Promtail trích xuất:
```logql
{job="library_app", action=~"BORROW_BOOK|RETURN_BOOK"}
```

## Query 4: Truy vấn các cảnh báo (WARN) và lỗi (ERROR) trong ứng dụng
```logql
{job="library_app", level=~"WARN|ERROR"}
```

## Query 5: Thống kê số lượng log phát sinh theo từng dịch vụ trong mỗi 5 phút (Metric Query)
```logql
sum by (job) (count_over_time({student_id="dtc241200004"}[5m]))
```

