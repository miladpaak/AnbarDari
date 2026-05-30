<?php
final class Inventory
{
    public static function stockSummary(?string $search = null, bool $lowOnly = false): array
    {
        $where = [];
        $params = [];
        if ($search) {
            $where[] = '(i.name LIKE ? OR i.sku LIKE ? OR i.barcode LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $having = $lowOnly ? 'HAVING quantity <= i.min_stock' : '';
        $sql = "SELECT i.*, c.name category_name, u.name unit_name, COALESCE(SUM(s.quantity), 0) quantity
                FROM items i
                LEFT JOIN categories c ON c.id = i.category_id
                LEFT JOIN units u ON u.id = i.unit_id
                LEFT JOIN stocks s ON s.item_id = i.id
                " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                GROUP BY i.id
                $having
                ORDER BY i.updated_at DESC, i.name";
        return Database::all($sql, $params);
    }

    public static function currentStock(int $itemId, ?int $warehouseId = null): float
    {
        $params = [$itemId];
        $warehouse = '';
        if ($warehouseId) {
            $warehouse = ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $row = Database::one("SELECT COALESCE(SUM(quantity), 0) qty FROM stocks WHERE item_id = ? $warehouse", $params);
        return (float) ($row['qty'] ?? 0);
    }

    public static function move(array $data): int
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $type = $data['type'];
            $itemId = (int) $data['item_id'];
            $qty = (float) $data['quantity'];
            $from = $data['from_warehouse_id'] ? (int) $data['from_warehouse_id'] : null;
            $to = $data['to_warehouse_id'] ? (int) $data['to_warehouse_id'] : null;

            if ($type !== 'in' && $from) {
                self::changeStock($itemId, $from, -$qty);
            }
            if ($type !== 'out' && $to) {
                self::changeStock($itemId, $to, $qty);
            }

            $stmt = $pdo->prepare('INSERT INTO stock_movements
                (reference_no, type, item_id, quantity, from_warehouse_id, to_warehouse_id, supplier_id, customer_id, user_id, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([
                $data['reference_no'], $type, $itemId, $qty, $from, $to,
                $data['supplier_id'] ?: null, $data['customer_id'] ?: null, $data['user_id'], $data['notes'] ?? null,
            ]);
            $id = (int) $pdo->lastInsertId();
            Database::query('UPDATE items SET updated_at = NOW() WHERE id = ?', [$itemId]);
            $pdo->commit();
            return $id;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private static function changeStock(int $itemId, int $warehouseId, float $delta): void
    {
        Database::query('INSERT INTO stocks (item_id, warehouse_id, quantity, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = NOW()', [$itemId, $warehouseId, $delta]);
        $stock = self::currentStock($itemId, $warehouseId);
        if ($stock < -0.0001) {
            throw new RuntimeException('موجودی این کالا در انبار انتخاب‌شده کافی نیست.');
        }
    }

    public static function nextReference(string $prefix = 'DP'): string
    {
        return $prefix . '-' . date('Ymd-His') . '-' . random_int(100, 999);
    }
}
