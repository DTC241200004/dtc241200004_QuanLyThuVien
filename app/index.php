<?php
/**
 * Ứng dụng Web Quản lý Thư viện (Sách, Độc giả, Mượn/Trả sách)
 * Mã sinh viên: DTC241200004
 */
session_start();
require_once __DIR__ . '/db.php';

// Endpoint kiểm tra sức khỏe hệ thống (Healthcheck)
if (isset($_GET['health'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query('SELECT 1');
        echo json_encode([
            'status'     => 'healthy',
            'student_id' => 'dtc241200004',
            'database'   => 'connected',
            'timestamp'  => date('c'),
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status'   => 'unhealthy',
            'database' => 'disconnected',
        ]);
    }
    exit;
}

$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType    = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

function set_flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type']    = $type;
}

$dbError = null;
$pdo     = null;

try {
    $pdo = get_db_connection();
} catch (Exception $e) {
    $dbError = 'Không thể kết nối tới cơ sở dữ liệu PostgreSQL. Vui lòng kiểm tra container postgres.';
}

// Xử lý các thao tác POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_book') {
            $bookCode = trim($_POST['book_code'] ?? '');
            $title    = trim($_POST['title'] ?? '');
            $author   = trim($_POST['author'] ?? '');
            $category = trim($_POST['category'] ?? 'Giáo trình');
            $year     = (int)($_POST['publish_year'] ?? date('Y'));
            $quantity = max(1, (int)($_POST['total_quantity'] ?? 1));

            if ($bookCode === '' || $title === '' || $author === '') {
                write_app_log('WARN', 'ADD_BOOK_INVALID', 'Thiếu thông tin bắt buộc khi thêm sách', ['book_code' => $bookCode]);
                set_flash('Vui lòng nhập đầy đủ Mã sách, Tên sách và Tác giả!', 'danger');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO books (book_code, title, author, category, publish_year, total_quantity, available_quantity)
                     VALUES (:code, :title, :author, :category, :year, :qty, :avail)'
                );
                $stmt->execute([
                    ':code'     => $bookCode,
                    ':title'    => $title,
                    ':author'   => $author,
                    ':category' => $category,
                    ':year'     => $year,
                    ':qty'      => $quantity,
                    ':avail'    => $quantity,
                ]);
                write_app_log('INFO', 'ADD_BOOK', "Đã thêm sách mới: {$title} ({$bookCode})", [
                    'book_code' => $bookCode,
                    'title'     => $title,
                    'quantity'  => $quantity,
                ]);
                set_flash("Đã thêm sách \"{$title}\" ({$bookCode}) thành công!");
            }
        } elseif ($action === 'delete_book') {
            $bookId = (int)($_POST['book_id'] ?? 0);
            $stmt   = $pdo->prepare('DELETE FROM books WHERE id = :id');
            $stmt->execute([':id' => $bookId]);
            write_app_log('WARN', 'DELETE_BOOK', "Đã xóa sách ID={$bookId}", ['book_id' => $bookId]);
            set_flash("Đã xóa sách khỏi hệ thống.", 'warning');
        } elseif ($action === 'add_reader') {
            $readerCode = trim($_POST['reader_code'] ?? '');
            $fullName   = trim($_POST['full_name'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $phone      = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? 'Công nghệ thông tin');

            if ($readerCode === '' || $fullName === '' || $email === '') {
                write_app_log('WARN', 'ADD_READER_INVALID', 'Thiếu thông tin độc giả', ['reader_code' => $readerCode]);
                set_flash('Vui lòng điền đầy đủ Mã độc giả, Họ tên và Email!', 'danger');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO readers (reader_code, full_name, email, phone, department, status)
                     VALUES (:code, :name, :email, :phone, :dept, \'active\')'
                );
                $stmt->execute([
                    ':code'  => $readerCode,
                    ':name'  => $fullName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':dept'  => $department,
                ]);
                write_app_log('INFO', 'ADD_READER', "Đã đăng ký độc giả mới: {$fullName} ({$readerCode})", [
                    'reader_code' => $readerCode,
                    'email'       => $email,
                ]);
                set_flash("Đã thêm độc giả \"{$fullName}\" ({$readerCode}) thành công!");
            }
        } elseif ($action === 'delete_reader') {
            $readerId = (int)($_POST['reader_id'] ?? 0);
            $stmt     = $pdo->prepare('DELETE FROM readers WHERE id = :id');
            $stmt->execute([':id' => $readerId]);
            write_app_log('WARN', 'DELETE_READER', "Đã xóa độc giả ID={$readerId}", ['reader_id' => $readerId]);
            set_flash("Đã xóa độc giả thành công.", 'warning');
        } elseif ($action === 'borrow_book') {
            $bookId   = (int)($_POST['book_id'] ?? 0);
            $readerId = (int)($_POST['reader_id'] ?? 0);
            $dueDate  = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
            $notes    = trim($_POST['notes'] ?? '');

            $pdo->beginTransaction();
            $checkStmt = $pdo->prepare('SELECT title, book_code, available_quantity FROM books WHERE id = :id FOR UPDATE');
            $checkStmt->execute([':id' => $bookId]);
            $book = $checkStmt->fetch();

            if (!$book || (int)$book['available_quantity'] <= 0) {
                $pdo->rollBack();
                write_app_log('WARN', 'BORROW_FAILED_OUT_OF_STOCK', 'Sách đã hết trong kho, không thể mượn', [
                    'book_id'   => $bookId,
                    'reader_id' => $readerId,
                ]);
                set_flash('Sách này hiện đã hết bản có sẵn để mượn!', 'danger');
            } else {
                $insertBorrow = $pdo->prepare(
                    'INSERT INTO borrow_records (book_id, reader_id, borrow_date, due_date, status, notes)
                     VALUES (:book_id, :reader_id, CURRENT_DATE, :due_date, \'borrowed\', :notes)'
                );
                $insertBorrow->execute([
                    ':book_id'   => $bookId,
                    ':reader_id' => $readerId,
                    ':due_date'  => $dueDate,
                    ':notes'     => $notes,
                ]);

                $updateBook = $pdo->prepare('UPDATE books SET available_quantity = available_quantity - 1 WHERE id = :id');
                $updateBook->execute([':id' => $bookId]);
                $pdo->commit();

                write_app_log('INFO', 'BORROW_BOOK', "Độc giả ID={$readerId} mượn sách {$book['book_code']}", [
                    'book_id'   => $bookId,
                    'book_code' => $book['book_code'],
                    'reader_id' => $readerId,
                    'due_date'  => $dueDate,
                ]);
                set_flash("Lập phiếu mượn sách \"{$book['title']}\" thành công!");
            }
        } elseif ($action === 'return_book') {
            $recordId = (int)($_POST['record_id'] ?? 0);

            $pdo->beginTransaction();
            $recStmt = $pdo->prepare('SELECT book_id, reader_id, status FROM borrow_records WHERE id = :id FOR UPDATE');
            $recStmt->execute([':id' => $recordId]);
            $record = $recStmt->fetch();

            if ($record && $record['status'] !== 'returned') {
                $updRecord = $pdo->prepare(
                    'UPDATE borrow_records SET status = \'returned\', return_date = CURRENT_DATE WHERE id = :id'
                );
                $updRecord->execute([':id' => $recordId]);

                $updBook = $pdo->prepare('UPDATE books SET available_quantity = available_quantity + 1 WHERE id = :id');
                $updBook->execute([':id' => $record['book_id']]);
                $pdo->commit();

                write_app_log('INFO', 'RETURN_BOOK', "Đã trả sách cho phiếu mượn ID={$recordId}", [
                    'record_id' => $recordId,
                    'book_id'   => $record['book_id'],
                    'reader_id' => $record['reader_id'],
                ]);
                set_flash("Xác nhận trả sách thành công (Phiếu #{$recordId})!");
            } else {
                $pdo->rollBack();
                set_flash('Phiếu mượn không hợp lệ hoặc đã được trả trước đó.', 'warning');
            }
        } elseif ($action === 'generate_sample_logs') {
            write_app_log('INFO', 'BORROW_BOOK', 'Log mẫu INFO: Độc giả DTC241200004 mượn sách BK-001 thành công', ['student' => 'DTC241200004', 'book' => 'BK-001']);
            write_app_log('WARN', 'OVERDUE_CHECK', 'Log mẫu WARN: Phát hiện 1 phiếu mượn quá hạn cần nhắc nhở', ['overdue_count' => 1]);
            write_app_log('ERROR', 'AUTH_ATTEMPT', 'Log mẫu ERROR: Cảnh báo truy cập trái phép hoặc sai thông tin xác thực', ['ip' => '192.168.1.100']);
            set_flash('Đã sinh 3 dòng log mẫu (INFO, WARN, ERROR) gửi tới Promtail / Loki để kiểm thử LogQL!');
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        write_app_log('ERROR', 'DB_QUERY_ERROR', 'Lỗi truy vấn cơ sở dữ liệu', [
            'action' => $action,
            'error'  => $e->getMessage(),
        ]);
        set_flash('Lỗi thao tác dữ liệu (có thể mã sách/mã độc giả hoặc email bị trùng lặp).', 'danger');
    }

    header('Location: /');
    exit;
}

// Truy vấn dữ liệu hiển thị
$stats = [
    'total_titles'    => 0,
    'total_copies'    => 0,
    'available_books' => 0,
    'total_readers'   => 0,
    'active_borrows'  => 0,
    'returned_count'  => 0,
];
$books         = [];
$readers       = [];
$borrowRecords = [];
$searchQuery   = trim($_GET['q'] ?? '');

if ($pdo !== null) {
    $statRow = $pdo->query(
        'SELECT
            COUNT(*) AS total_titles,
            COALESCE(SUM(total_quantity), 0) AS total_copies,
            COALESCE(SUM(available_quantity), 0) AS available_books
         FROM books'
    )->fetch();
    $stats['total_titles']    = (int)$statRow['total_titles'];
    $stats['total_copies']    = (int)$statRow['total_copies'];
    $stats['available_books'] = (int)$statRow['available_books'];

    $stats['total_readers']  = (int)$pdo->query('SELECT COUNT(*) FROM readers')->fetchColumn();
    $stats['active_borrows'] = (int)$pdo->query("SELECT COUNT(*) FROM borrow_records WHERE status = 'borrowed'")->fetchColumn();
    $stats['returned_count'] = (int)$pdo->query("SELECT COUNT(*) FROM borrow_records WHERE status = 'returned'")->fetchColumn();

    if ($searchQuery !== '') {
        $stmtBooks = $pdo->prepare(
            'SELECT * FROM books
             WHERE title ILIKE :q OR author ILIKE :q OR book_code ILIKE :q OR category ILIKE :q
             ORDER BY id ASC'
        );
        $stmtBooks->execute([':q' => "%{$searchQuery}%"]);
        $books = $stmtBooks->fetchAll();
        write_app_log('INFO', 'SEARCH_BOOK', "Tìm kiếm sách với từ khóa: {$searchQuery}", ['query' => $searchQuery]);
    } else {
        $books = $pdo->query('SELECT * FROM books ORDER BY id ASC')->fetchAll();
    }

    $readers = $pdo->query('SELECT * FROM readers ORDER BY id ASC')->fetchAll();

    $borrowRecords = $pdo->query(
        'SELECT br.*, b.book_code, b.title AS book_title, r.reader_code, r.full_name AS reader_name
         FROM borrow_records br
         JOIN books b ON br.book_id = b.id
         JOIN readers r ON br.reader_id = r.id
         ORDER BY br.status ASC, br.id DESC'
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Quản lý Thư viện - DTC241200004</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.15s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .section-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }
        .badge-soft-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-soft-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-soft-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="/">
            📚 QUẢN LÝ THƯ VIỆN | <span class="badge bg-light text-primary">DTC241200004</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <form method="POST" class="m-0">
                <input type="hidden" name="action" value="generate_sample_logs">
                <button type="submit" class="btn btn-sm btn-warning fw-semibold">
                    ⚡ Tạo Log Mẫu (Test Loki)
                </button>
            </form>
            <a href="/?health=1" target="_blank" class="btn btn-sm btn-outline-light">Health JSON</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php if ($dbError): ?>
        <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Thống kê tổng quan -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 bg-white">
                <div class="text-muted small fw-semibold">ĐẦU SÁCH / TỔNG BẢN</div>
                <div class="fs-3 fw-bold text-primary"><?= $stats['total_titles'] ?> <span class="fs-6 text-secondary">(<?= $stats['total_copies'] ?> cuốn)</span></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 bg-white">
                <div class="text-muted small fw-semibold">SÁCH SẴN SÀNG CHO MƯỢN</div>
                <div class="fs-3 fw-bold text-success"><?= $stats['available_books'] ?> cuốn</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 bg-white">
                <div class="text-muted small fw-semibold">TỔNG SỐ ĐỘC GIẢ</div>
                <div class="fs-3 fw-bold text-info"><?= $stats['total_readers'] ?> độc giả</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 bg-white">
                <div class="text-muted small fw-semibold">ĐANG MƯỢN / ĐÃ TRẢ</div>
                <div class="fs-3 fw-bold text-warning"><?= $stats['active_borrows'] ?> <span class="fs-6 text-muted">/ <?= $stats['returned_count'] ?> đã trả</span></div>
            </div>
        </div>
    </div>

    <!-- Khu vực 1: Quản lý Mượn / Trả Sách -->
    <div class="card section-card mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">🔄 Quản lý Mượn / Trả Sách</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#formBorrow">
                + Lập Phiếu Mượn Mới
            </button>
        </div>
        <div class="collapse" id="formBorrow">
            <div class="card-body bg-light border-bottom">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="borrow_book">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Chọn Sách (Còn trong kho)</label>
                        <select name="book_id" class="form-select" required>
                            <option value="">-- Chọn sách cần mượn --</option>
                            <?php foreach ($books as $b): ?>
                                <?php if ((int)$b['available_quantity'] > 0): ?>
                                    <option value="<?= (int)$b['id'] ?>">
                                        [<?= htmlspecialchars($b['book_code']) ?>] <?= htmlspecialchars($b['title']) ?> (Còn: <?= (int)$b['available_quantity'] ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Chọn Độc Giả</label>
                        <select name="reader_id" class="form-select" required>
                            <option value="">-- Chọn độc giả --</option>
                            <?php foreach ($readers as $r): ?>
                                <option value="<?= (int)$r['id'] ?>">
                                    [<?= htmlspecialchars($r['reader_code']) ?>] <?= htmlspecialchars($r['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Hạn trả sách</label>
                        <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Ghi chú</label>
                        <div class="input-group">
                            <input type="text" name="notes" class="form-control" placeholder="Mượn học tập...">
                            <button type="submit" class="btn btn-success">Xác nhận</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã Phiếu</th>
                            <th>Sách</th>
                            <th>Độc Giả</th>
                            <th>Ngày Mượn</th>
                            <th>Hạn Trả</th>
                            <th>Ngày Trả</th>
                            <th>Trạng Thái</th>
                            <th class="text-end">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($borrowRecords as $rec): ?>
                            <?php
                            $isOverdue = ($rec['status'] === 'borrowed' && strtotime($rec['due_date']) < strtotime(date('Y-m-d')));
                            ?>
                            <tr>
                                <td class="fw-semibold">#<?= (int)$rec['id'] ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($rec['book_code']) ?></span>
                                    <div class="fw-semibold"><?= htmlspecialchars($rec['book_title']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($rec['reader_code']) ?></span>
                                    <div><?= htmlspecialchars($rec['reader_name']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($rec['borrow_date']) ?></td>
                                <td><?= htmlspecialchars($rec['due_date']) ?></td>
                                <td><?= $rec['return_date'] ? htmlspecialchars($rec['return_date']) : '<span class="text-muted">-</span>' ?></td>
                                <td>
                                    <?php if ($rec['status'] === 'returned'): ?>
                                        <span class="badge badge-soft-success">Đã trả</span>
                                    <?php elseif ($isOverdue): ?>
                                        <span class="badge badge-soft-danger">Quá hạn</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-warning">Đang mượn</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($rec['status'] !== 'returned'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="return_book">
                                            <input type="hidden" name="record_id" value="<?= (int)$rec['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success">✔ Trả sách</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Hoàn tất</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Khu vực 2: Danh mục Sách -->
        <div class="col-lg-7">
            <div class="card section-card h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 fw-bold">📖 Quản lý Kho Sách</h5>
                    <div class="d-flex gap-2">
                        <form method="GET" class="d-flex">
                            <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control form-control-sm me-1" placeholder="Tìm tên sách, mã...">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Tìm</button>
                        </form>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#formAddBook">+ Thêm Sách</button>
                    </div>
                </div>
                <div class="collapse" id="formAddBook">
                    <div class="card-body bg-light border-bottom">
                        <form method="POST" class="row g-2">
                            <input type="hidden" name="action" value="add_book">
                            <div class="col-md-3">
                                <input type="text" name="book_code" class="form-control form-control-sm" placeholder="Mã sách (VD: BK-006)" required>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="title" class="form-control form-control-sm" placeholder="Tên sách" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="author" class="form-control form-control-sm" placeholder="Tác giả" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="category" class="form-control form-control-sm" placeholder="Thể loại" value="Công nghệ thông tin">
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="publish_year" class="form-control form-control-sm" value="2025" placeholder="Năm XB">
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="total_quantity" class="form-control form-control-sm" value="5" min="1" placeholder="Số lượng">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-sm btn-success">Lưu</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã</th>
                                    <th>Tên Sách & Tác Giả</th>
                                    <th>Thể Loại</th>
                                    <th>Tồn Kho</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $b): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($b['book_code']) ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($b['title']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($b['author']) ?> (<?= (int)$b['publish_year'] ?>)</small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($b['category']) ?></span></td>
                                        <td>
                                            <span class="fw-bold <?= (int)$b['available_quantity'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= (int)$b['available_quantity'] ?>
                                            </span> / <?= (int)$b['total_quantity'] ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" onsubmit="return confirm('Xác nhận xóa sách này?');">
                                                <input type="hidden" name="action" value="delete_book">
                                                <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">×</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Khu vực 3: Danh sách Độc giả -->
        <div class="col-lg-5">
            <div class="card section-card h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">👨‍🎓 Quản lý Độc Giả</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#formAddReader">+ Thêm Độc Giả</button>
                </div>
                <div class="collapse" id="formAddReader">
                    <div class="card-body bg-light border-bottom">
                        <form method="POST" class="row g-2">
                            <input type="hidden" name="action" value="add_reader">
                            <div class="col-md-5">
                                <input type="text" name="reader_code" class="form-control form-control-sm" placeholder="Mã SV (VD: DTC241200005)" required>
                            </div>
                            <div class="col-md-7">
                                <input type="text" name="full_name" class="form-control form-control-sm" placeholder="Họ và tên" required>
                            </div>
                            <div class="col-md-6">
                                <input type="email" name="email" class="form-control form-control-sm" placeholder="Email" required>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="phone" class="form-control form-control-sm" placeholder="Số điện thoại">
                            </div>
                            <div class="col-md-8">
                                <input type="text" name="department" class="form-control form-control-sm" value="Công nghệ thông tin" placeholder="Khoa / Lớp">
                            </div>
                            <div class="col-md-4 d-grid">
                                <button type="submit" class="btn btn-sm btn-success">Thêm</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã Độc Giả</th>
                                    <th>Thông Tin</th>
                                    <th>Khoa</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($readers as $r): ?>
                                    <tr>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['reader_code']) ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($r['email']) ?></small>
                                        </td>
                                        <td><small><?= htmlspecialchars($r['department']) ?></small></td>
                                        <td class="text-end">
                                            <form method="POST" onsubmit="return confirm('Xác nhận xóa độc giả này?');">
                                                <input type="hidden" name="action" value="delete_reader">
                                                <input type="hidden" name="reader_id" value="<?= (int)$r['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">×</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center text-muted small mt-4 pt-3 border-top">
        Hệ thống Quản lý Thư viện | Sinh viên thực hiện: <strong>DTC241200004</strong> |
        Stack: PHP 8.2 + PostgreSQL + pgAdmin + Nginx Reverse Proxy + Prometheus + Grafana + Loki & Promtail
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
