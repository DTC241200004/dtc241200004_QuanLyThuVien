-- ==============================================================================
-- KHỞI TẠO CƠ SỞ DỮ LIỆU QUẢN LÝ THƯ VIỆN (PostgreSQL)
-- Sinh viên: DTC241200004
-- ==============================================================================

SET client_encoding = 'UTF8';

-- 1. Bảng Quản lý Sách (books)
CREATE TABLE IF NOT EXISTS books (
    id SERIAL PRIMARY KEY,
    book_code VARCHAR(30) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    publish_year INT CHECK (publish_year >= 1900 AND publish_year <= 2100),
    total_quantity INT NOT NULL DEFAULT 1 CHECK (total_quantity >= 0),
    available_quantity INT NOT NULL DEFAULT 1 CHECK (available_quantity >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng Quản lý Độc giả (readers)
CREATE TABLE IF NOT EXISTS readers (
    id SERIAL PRIMARY KEY,
    reader_code VARCHAR(30) UNIQUE NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    department VARCHAR(100) DEFAULT 'Công nghệ thông tin',
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'locked')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng Quản lý Mượn / Trả sách (borrow_records)
CREATE TABLE IF NOT EXISTS borrow_records (
    id SERIAL PRIMARY KEY,
    book_id INT NOT NULL REFERENCES books(id) ON DELETE CASCADE,
    reader_id INT NOT NULL REFERENCES readers(id) ON DELETE CASCADE,
    borrow_date DATE NOT NULL DEFAULT CURRENT_DATE,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'borrowed' CHECK (status IN ('borrowed', 'returned', 'overdue')),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tạo Index tối ưu truy vấn
CREATE INDEX IF NOT EXISTS idx_books_title ON books(title);
CREATE INDEX IF NOT EXISTS idx_books_category ON books(category);
CREATE INDEX IF NOT EXISTS idx_readers_code ON readers(reader_code);
CREATE INDEX IF NOT EXISTS idx_borrow_status ON borrow_records(status);

-- ==============================================================================
-- DỮ LIỆU MẪU BAN ĐẦU (SEED DATA)
-- ==============================================================================

INSERT INTO books (book_code, title, author, category, publish_year, total_quantity, available_quantity)
VALUES
    ('BK-001', 'Quản trị Hệ thống Linux và Docker Container', 'Nguyễn Văn An', 'Hệ thống & Mạng', 2024, 10, 8),
    ('BK-002', 'Cơ sở dữ liệu PostgreSQL Nâng cao', 'Trần Thị Bình', 'Cơ sở dữ liệu', 2023, 8, 7),
    ('BK-003', 'Bảo mật Hệ thống & DevSecOps Thực chiến', 'Lê Hoàng Nam', 'An toàn thông tin', 2025, 6, 5),
    ('BK-004', 'Giám sát Hệ thống với Prometheus, Grafana và Loki', 'Phạm Minh Tuấn', 'DevOps & Cloud', 2024, 5, 5),
    ('BK-005', 'Phát triển Ứng dụng Web với PHP & Nginx', 'Hoàng Đức Việt', 'Lập trình Web', 2023, 12, 11)
ON CONFLICT (book_code) DO NOTHING;

INSERT INTO readers (reader_code, full_name, email, phone, department, status)
VALUES
    ('DTC241200004', 'Sinh viên Thực hành (DTC241200004)', 'dtc241200004@ictu.edu.vn', '0912345004', 'Công nghệ thông tin', 'active'),
    ('DTC241200001', 'Nguyễn Minh Khôi', 'khoinm@ictu.edu.vn', '0987654321', 'An toàn thông tin', 'active'),
    ('DTC241200002', 'Trần Thu Hà', 'hatt@ictu.edu.vn', '0911223344', 'Khoa học máy tính', 'active'),
    ('DTC241200003', 'Lê Quang Huy', 'huylq@ictu.edu.vn', '0933445566', 'Kỹ thuật phần mềm', 'active')
ON CONFLICT (reader_code) DO NOTHING;

INSERT INTO borrow_records (book_id, reader_id, borrow_date, due_date, return_date, status, notes)
VALUES
    (1, 1, CURRENT_DATE - INTERVAL '5 days', CURRENT_DATE + INTERVAL '9 days', NULL, 'borrowed', 'Mượn phục vụ đồ án triển khai Docker'),
    (2, 1, CURRENT_DATE - INTERVAL '3 days', CURRENT_DATE + INTERVAL '11 days', NULL, 'borrowed', 'Tham khảo cấu hình PostgreSQL'),
    (3, 2, CURRENT_DATE - INTERVAL '10 days', CURRENT_DATE - INTERVAL '1 day', NULL, 'borrowed', 'Nghiên cứu Hardening Container'),
    (5, 3, CURRENT_DATE - INTERVAL '7 days', CURRENT_DATE + INTERVAL '7 days', NULL, 'borrowed', 'Học lập trình PHP'),
    (1, 4, CURRENT_DATE - INTERVAL '14 days', CURRENT_DATE - INTERVAL '2 days', CURRENT_DATE - INTERVAL '3 days', 'returned', 'Đã trả đúng hạn');
