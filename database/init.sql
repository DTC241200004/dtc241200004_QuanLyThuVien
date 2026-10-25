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
