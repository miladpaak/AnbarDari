<?php
final class IssueRequests
{
    public static function ensureSchema(): void
    {
        Database::query("CREATE TABLE IF NOT EXISTS stock_issue_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            issue_code VARCHAR(80) NOT NULL UNIQUE,
            warehouse_id INT UNSIGNED NOT NULL,
            requester_id INT UNSIGNED NOT NULL,
            recipient_name VARCHAR(160) NULL,
            notes TEXT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            reviewer_id INT UNSIGNED NULL,
            review_notes TEXT NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_issue_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_issue_requester FOREIGN KEY (requester_id) REFERENCES users(id),
            CONSTRAINT fk_issue_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_issue_status_date (status, created_at),
            INDEX idx_issue_requester (requester_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::query("CREATE TABLE IF NOT EXISTS stock_issue_request_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            issue_request_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            quantity DECIMAL(14,3) NOT NULL,
            notes VARCHAR(255) NULL,
            CONSTRAINT fk_issue_item_request FOREIGN KEY (issue_request_id) REFERENCES stock_issue_requests(id) ON DELETE CASCADE,
            CONSTRAINT fk_issue_item_item FOREIGN KEY (item_id) REFERENCES items(id),
            INDEX idx_issue_item_request (issue_request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function nextCode(): string
    {
        return 'OUT-' . date('Ymd-His') . '-' . random_int(100, 999);
    }

    public static function create(array $data, array $items): int
    {
        $validItems = self::validItems($items);
        if (!$validItems) {
            throw new RuntimeException('حداقل یک قلم کالا با مقدار معتبر وارد کنید.');
        }
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO stock_issue_requests (issue_code, warehouse_id, requester_id, recipient_name, notes, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, \'pending\', NOW(), NOW())');
            $stmt->execute([$data['issue_code'], $data['warehouse_id'], $data['requester_id'], $data['recipient_name'] ?: null, $data['notes'] ?: null]);
            $requestId = (int) $pdo->lastInsertId();
            $itemStmt = $pdo->prepare('INSERT INTO stock_issue_request_items (issue_request_id, item_id, quantity, notes) VALUES (?, ?, ?, ?)');
            foreach ($validItems as $item) {
                $itemStmt->execute([$requestId, $item['item_id'], $item['quantity'], $item['notes']]);
            }
            $pdo->commit();
            return $requestId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function approve(int $requestId, int $reviewerId, ?string $notes): void
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $request = $pdo->prepare('SELECT * FROM stock_issue_requests WHERE id=? FOR UPDATE');
            $request->execute([$requestId]);
            $issue = $request->fetch();
            if (!$issue || $issue['status'] !== 'pending') {
                throw new RuntimeException('این حواله دیگر در وضعیت انتظار بررسی نیست.');
            }
            $items = Database::all('SELECT ri.*, i.name item_name FROM stock_issue_request_items ri JOIN items i ON i.id=ri.item_id WHERE ri.issue_request_id=?', [$requestId]);
            if (!$items) {
                throw new RuntimeException('این حواله هیچ قلم کالایی ندارد.');
            }
            foreach ($items as $item) {
                if (Inventory::currentStock((int) $item['item_id'], (int) $issue['warehouse_id']) + 0.0001 < (float) $item['quantity']) {
                    throw new RuntimeException('موجودی «' . $item['item_name'] . '» در انبار انتخاب‌شده کافی نیست.');
                }
            }
            $movement = $pdo->prepare('INSERT INTO stock_movements (reference_no, type, item_id, quantity, from_warehouse_id, to_warehouse_id, supplier_id, customer_id, user_id, notes, created_at, updated_at) VALUES (?, \'out\', ?, ?, ?, NULL, NULL, NULL, ?, ?, NOW(), NOW())');
            foreach ($items as $index => $item) {
                Inventory::decreaseStock((int) $item['item_id'], (int) $issue['warehouse_id'], (float) $item['quantity']);
                $movement->execute([$issue['issue_code'] . '-' . ($index + 1), $item['item_id'], $item['quantity'], $issue['warehouse_id'], $reviewerId, 'خروج تاییدشده از حواله ' . $issue['issue_code']]);
                Database::query('UPDATE items SET updated_at=NOW() WHERE id=?', [$item['item_id']]);
            }
            $pdo->prepare("UPDATE stock_issue_requests SET status='approved', reviewer_id=?, review_notes=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$reviewerId, $notes ?: null, $requestId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function reject(int $requestId, int $reviewerId, string $notes): void
    {
        if (trim($notes) === '') {
            throw new RuntimeException('برای رد حواله، ثبت توضیحات الزامی است.');
        }
        $stmt = Database::query("UPDATE stock_issue_requests SET status='rejected', reviewer_id=?, review_notes=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=? AND status='pending'", [$reviewerId, trim($notes), $requestId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('این حواله دیگر در وضعیت انتظار بررسی نیست.');
        }
    }

    public static function details(int $id): ?array
    {
        $issue = Database::one('SELECT r.*, w.name warehouse_name, u.name requester_name, rv.name reviewer_name FROM stock_issue_requests r JOIN warehouses w ON w.id=r.warehouse_id JOIN users u ON u.id=r.requester_id LEFT JOIN users rv ON rv.id=r.reviewer_id WHERE r.id=?', [$id]);
        if (!$issue) return null;
        $issue['items'] = Database::all('SELECT ri.*, i.name item_name, i.sku, i.image_path, un.symbol unit_symbol FROM stock_issue_request_items ri JOIN items i ON i.id=ri.item_id LEFT JOIN units un ON un.id=i.unit_id WHERE ri.issue_request_id=?', [$id]);
        return $issue;
    }

    private static function validItems(array $items): array
    {
        $valid = [];
        foreach ($items as $item) {
            if (empty($item['item_id']) || (float) ($item['quantity'] ?? 0) <= 0) continue;
            $valid[] = ['item_id' => (int) $item['item_id'], 'quantity' => (float) $item['quantity'], 'notes' => trim((string) ($item['notes'] ?? '')) ?: null];
        }
        return $valid;
    }
}
