<?php
final class WordPressSync
{
    private static ?PDO $pdo = null;

    public static function isConfigured(): bool
    {
        $config = app_config()['wordpress_db'] ?? [];
        return !empty($config['host']) && !empty($config['name']) && !empty($config['user']);
    }

    public static function connect(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        if (!self::isConfigured()) {
            throw new RuntimeException('تنظیمات دیتابیس وردپرس در config.php کامل نیست.');
        }
        $config = app_config()['wordpress_db'];
        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['name'], $charset);
        self::$pdo = new PDO($dsn, $config['user'], $config['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }

    public static function tablePrefix(): string
    {
        return app_config()['wordpress_db']['prefix'] ?? 'wp_';
    }

    public static function ensureSchema(): void
    {
        Accounting::ensureSchema();
        Database::query("CREATE TABLE IF NOT EXISTS wordpress_product_maps (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            wp_product_id BIGINT UNSIGNED NOT NULL UNIQUE,
            item_id INT UNSIGNED NOT NULL,
            sku VARCHAR(120) NULL,
            last_synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_wp_product_maps_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            INDEX idx_wp_product_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS wordpress_order_maps (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            wp_order_id BIGINT UNSIGNED NOT NULL UNIQUE,
            invoice_id INT UNSIGNED NOT NULL,
            order_status VARCHAR(80) NULL,
            last_synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_wp_order_maps_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
            INDEX idx_wp_order_invoice (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function status(): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'message' => 'تنظیمات دیتابیس وردپرس وارد نشده است.'];
        }
        $pdo = self::connect();
        $prefix = self::tablePrefix();
        $stmt = $pdo->query('SELECT COUNT(*) c FROM `' . $prefix . 'posts` WHERE post_type IN (\'product\',\'product_variation\')');
        return ['ok' => true, 'message' => 'اتصال برقرار است.', 'products' => (int) ($stmt->fetch()['c'] ?? 0)];
    }

    public static function wordpressProducts(): array
    {
        $pdo = self::connect();
        $prefix = self::tablePrefix();
        $sql = "SELECT p.ID, p.post_title, p.post_modified, p.post_type,
                    MAX(CASE WHEN pm.meta_key='_sku' THEN pm.meta_value END) sku,
                    MAX(CASE WHEN pm.meta_key='_regular_price' THEN pm.meta_value END) regular_price,
                    MAX(CASE WHEN pm.meta_key='_sale_price' THEN pm.meta_value END) sale_price,
                    MAX(CASE WHEN pm.meta_key='_price' THEN pm.meta_value END) price,
                    MAX(CASE WHEN pm.meta_key='_stock' THEN pm.meta_value END) stock,
                    MAX(CASE WHEN pm.meta_key='_low_stock_amount' THEN pm.meta_value END) min_stock,
                    MAX(CASE WHEN pm.meta_key IN ('_unit','unit','pa_unit') THEN pm.meta_value END) unit_name,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR '، ') category_name
                FROM `{$prefix}posts` p
                LEFT JOIN `{$prefix}postmeta` pm ON pm.post_id = p.ID
                LEFT JOIN `{$prefix}term_relationships` tr ON tr.object_id = p.ID
                LEFT JOIN `{$prefix}term_taxonomy` tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy='product_cat'
                LEFT JOIN `{$prefix}terms` t ON t.term_id = tt.term_id
                WHERE p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private')
                GROUP BY p.ID, p.post_title, p.post_modified, p.post_type
                ORDER BY p.ID DESC";
        return $pdo->query($sql)->fetchAll();
    }

    public static function importProducts(int $warehouseId): array
    {
        self::ensureSchema();
        $rows = self::wordpressProducts();
        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?: 'WP-' . $row['ID']));
            $name = trim((string) ($row['post_title'] ?: $sku));
            $price = (float) ($row['sale_price'] !== null && $row['sale_price'] !== '' ? $row['sale_price'] : ($row['price'] ?: $row['regular_price'] ?: 0));
            $stock = is_numeric($row['stock']) ? (float) $row['stock'] : 0.0;
            $minStock = is_numeric($row['min_stock']) ? (float) $row['min_stock'] : 0.0;
            $categoryId = self::ensureCategory($row['category_name'] ?? null);
            $unitId = self::ensureUnit($row['unit_name'] ?? null);
            $updatedAt = strtotime((string) ($row['post_modified'] ?? '')) ? $row['post_modified'] : date('Y-m-d H:i:s');
            $item = Database::one('SELECT i.* FROM items i LEFT JOIN wordpress_product_maps m ON m.item_id=i.id WHERE m.wp_product_id=? OR i.sku=? LIMIT 1', [(int) $row['ID'], $sku]);
            if ($item) {
                Database::query('UPDATE items SET name=?, barcode=?, category_id=?, unit_id=?, min_stock=?, is_active=1, updated_at=? WHERE id=?', [$name, $sku, $categoryId, $unitId, $minStock, $updatedAt, (int) $item['id']]);
                $itemId = (int) $item['id'];
                $updated++;
            } else {
                Database::query('INSERT INTO items (sku, barcode, name, category_id, unit_id, min_stock, description, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)', [$sku, $sku, $name, $categoryId, $unitId, $minStock, 'وارد شده از وردپرس', $updatedAt]);
                $itemId = (int) Database::connect()->lastInsertId();
                $created++;
            }
            Database::query('INSERT INTO wordpress_product_maps (wp_product_id, item_id, sku, last_synced_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE item_id=VALUES(item_id), sku=VALUES(sku), last_synced_at=NOW()', [(int) $row['ID'], $itemId, $sku]);
            Database::query('INSERT INTO accounting_item_meta (item_id, last_purchase_price, default_sale_price, updated_at) VALUES (?, 0, ?, NOW()) ON DUPLICATE KEY UPDATE default_sale_price=VALUES(default_sale_price), updated_at=NOW()', [$itemId, $price]);
            Database::query('INSERT INTO stocks (item_id, warehouse_id, quantity, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), updated_at=NOW()', [$itemId, $warehouseId, $stock]);
        }
        return ['created' => $created, 'updated' => $updated, 'total' => count($rows)];
    }

    public static function importCustomers(): array
    {
        self::ensureSchema();
        $pdo = self::connect();
        $prefix = self::tablePrefix();
        $sql = "SELECT u.ID, u.display_name, u.user_registered,
                    MAX(CASE WHEN um.meta_key='first_name' THEN um.meta_value END) first_name,
                    MAX(CASE WHEN um.meta_key='last_name' THEN um.meta_value END) last_name,
                    MAX(CASE WHEN um.meta_key='billing_phone' THEN um.meta_value END) phone,
                    MAX(CASE WHEN um.meta_key='billing_address_1' THEN um.meta_value END) address
                FROM `{$prefix}users` u
                JOIN `{$prefix}usermeta` cap ON cap.user_id=u.ID AND cap.meta_key=? AND cap.meta_value LIKE '%customer%'
                LEFT JOIN `{$prefix}usermeta` um ON um.user_id=u.ID
                GROUP BY u.ID, u.display_name, u.user_registered
                ORDER BY u.ID DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$prefix . 'capabilities']);
        $rows = $stmt->fetchAll();
        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['display_name'] ?: 'مشتری وردپرس #' . $row['ID']);
            $phone = trim((string) ($row['phone'] ?? ''));
            $address = trim((string) ($row['address'] ?? ''));
            $customer = $phone ? Database::one('SELECT * FROM customers WHERE phone=? LIMIT 1', [$phone]) : null;
            if (!$customer) {
                $customer = Database::one('SELECT * FROM customers WHERE name=? LIMIT 1', [$name]);
            }
            if ($customer) {
                Database::query('UPDATE customers SET name=?, phone=?, address=?, updated_at=NOW() WHERE id=?', [$name, $phone ?: $customer['phone'], $address ?: $customer['address'], (int) $customer['id']]);
                $updated++;
            } else {
                Database::query('INSERT INTO customers (name, phone, address, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', [$name, $phone ?: null, $address ?: null]);
                $created++;
            }
        }
        return ['created' => $created, 'updated' => $updated, 'total' => count($rows)];
    }

    private static function ensureCategory(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $existing = Database::one('SELECT id FROM categories WHERE name=?', [$name]);
        if ($existing) {
            return (int) $existing['id'];
        }
        Database::query('INSERT INTO categories (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', [$name, 'وارد شده از وردپرس']);
        return (int) Database::connect()->lastInsertId();
    }

    private static function ensureUnit(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $existing = Database::one('SELECT id FROM units WHERE name=? OR symbol=?', [$name, $name]);
        if ($existing) {
            return (int) $existing['id'];
        }
        Database::query('INSERT INTO units (name, symbol, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', [$name, $name]);
        return (int) Database::connect()->lastInsertId();
    }

    public static function importOrders(int $warehouseId, int $userId, int $limit = 50): array
    {
        self::ensureSchema();
        $pdo = self::connect();
        $prefix = self::tablePrefix();
        $orders = self::legacyOrders($pdo, $prefix, $limit);
        $created = 0;
        $skipped = 0;
        $failed = [];
        foreach ($orders as $order) {
            if (Database::one('SELECT id FROM wordpress_order_maps WHERE wp_order_id=?', [(int) $order['id']])) {
                $skipped++;
                continue;
            }
            $existingInvoice = Database::one('SELECT id FROM invoices WHERE invoice_no=?', ['WC-' . $order['id']]);
            if ($existingInvoice) {
                Database::query('INSERT INTO wordpress_order_maps (wp_order_id, invoice_id, order_status, last_synced_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE invoice_id=VALUES(invoice_id), order_status=VALUES(order_status), last_synced_at=NOW()', [(int) $order['id'], (int) $existingInvoice['id'], $order['status']]);
                $skipped++;
                continue;
            }
            $lines = self::legacyOrderLines($pdo, $prefix, (int) $order['id']);
            if (!$lines) {
                $skipped++;
                continue;
            }
            try {
                $customerId = self::ensureCustomer($order);
                $invoiceItems = [];
                foreach ($lines as $line) {
                    $itemId = self::itemIdForWpProduct((int) ($line['variation_id'] ?: $line['product_id']), $warehouseId);
                    $qty = max(0.0, (float) $line['qty']);
                    $lineTotal = (float) $line['total'];
                    $unitPrice = $qty > 0 ? $lineTotal / $qty : 0;
                    $meta = Database::one('SELECT last_purchase_price FROM accounting_item_meta WHERE item_id=?', [$itemId]);
                    $invoiceItems[] = ['item_id' => $itemId, 'quantity' => $qty, 'unit_price' => $unitPrice, 'cost_price' => (float) ($meta['last_purchase_price'] ?? 0)];
                }
                $invoiceId = Accounting::createInvoice([
                    'invoice_no' => 'WC-' . $order['id'],
                    'invoice_type' => 'sale',
                    'invoice_date' => substr((string) $order['date'], 0, 10) ?: date('Y-m-d'),
                    'customer_id' => $customerId,
                    'supplier_id' => null,
                    'warehouse_id' => $warehouseId,
                    'bank_account_id' => null,
                    'payment_status' => in_array($order['status'], ['wc-processing', 'wc-completed', 'processing', 'completed'], true) ? 'cash' : 'credit',
                    'discount' => (float) $order['discount_total'],
                    'tax' => (float) $order['tax_total'],
                    'shipping_cost' => (float) $order['shipping_total'],
                    'paid_amount' => in_array($order['status'], ['wc-processing', 'wc-completed', 'processing', 'completed'], true) ? (float) $order['total'] : 0,
                    'notes' => 'وارد شده خودکار از سفارش وردپرس #' . $order['id'],
                    'user_id' => $userId,
                ], $invoiceItems);
                Database::query('INSERT INTO wordpress_order_maps (wp_order_id, invoice_id, order_status, last_synced_at) VALUES (?, ?, ?, NOW())', [(int) $order['id'], $invoiceId, $order['status']]);
                $created++;
            } catch (Throwable $exception) {
                $failed[] = '#' . $order['id'] . ': ' . $exception->getMessage();
            }
        }
        return ['created' => $created, 'skipped' => $skipped, 'failed' => $failed, 'total' => count($orders)];
    }

    private static function legacyOrders(PDO $pdo, string $prefix, int $limit): array
    {
        $sql = "SELECT p.ID id, p.post_date date, p.post_status status,
                    MAX(CASE WHEN pm.meta_key='_billing_first_name' THEN pm.meta_value END) billing_first_name,
                    MAX(CASE WHEN pm.meta_key='_billing_last_name' THEN pm.meta_value END) billing_last_name,
                    MAX(CASE WHEN pm.meta_key='_billing_phone' THEN pm.meta_value END) billing_phone,
                    MAX(CASE WHEN pm.meta_key='_billing_address_1' THEN pm.meta_value END) billing_address,
                    MAX(CASE WHEN pm.meta_key='_order_total' THEN pm.meta_value END) total,
                    MAX(CASE WHEN pm.meta_key='_order_tax' THEN pm.meta_value END) tax_total,
                    MAX(CASE WHEN pm.meta_key='_cart_discount' THEN pm.meta_value END) discount_total,
                    MAX(CASE WHEN pm.meta_key='_order_shipping' THEN pm.meta_value END) shipping_total
                FROM `{$prefix}posts` p
                LEFT JOIN `{$prefix}postmeta` pm ON pm.post_id=p.ID
                WHERE p.post_type='shop_order' AND p.post_status IN ('wc-processing','wc-completed','wc-on-hold')
                GROUP BY p.ID, p.post_date, p.post_status
                ORDER BY p.ID DESC
                LIMIT " . max(1, min(500, $limit));
        return $pdo->query($sql)->fetchAll();
    }

    private static function legacyOrderLines(PDO $pdo, string $prefix, int $orderId): array
    {
        $sql = "SELECT oi.order_item_id,
                    MAX(CASE WHEN oim.meta_key='_product_id' THEN oim.meta_value END) product_id,
                    MAX(CASE WHEN oim.meta_key='_variation_id' THEN oim.meta_value END) variation_id,
                    MAX(CASE WHEN oim.meta_key='_qty' THEN oim.meta_value END) qty,
                    MAX(CASE WHEN oim.meta_key='_line_total' THEN oim.meta_value END) total
                FROM `{$prefix}woocommerce_order_items` oi
                LEFT JOIN `{$prefix}woocommerce_order_itemmeta` oim ON oim.order_item_id=oi.order_item_id
                WHERE oi.order_id=? AND oi.order_item_type='line_item'
                GROUP BY oi.order_item_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    private static function itemIdForWpProduct(int $wpProductId, int $warehouseId): int
    {
        $map = Database::one('SELECT item_id FROM wordpress_product_maps WHERE wp_product_id=?', [$wpProductId]);
        if ($map) {
            return (int) $map['item_id'];
        }
        self::importProducts($warehouseId);
        $map = Database::one('SELECT item_id FROM wordpress_product_maps WHERE wp_product_id=?', [$wpProductId]);
        if (!$map) {
            throw new RuntimeException('محصول وردپرس #' . $wpProductId . ' در سیستم انبار پیدا نشد.');
        }
        return (int) $map['item_id'];
    }

    private static function ensureCustomer(array $order): int
    {
        $name = trim(($order['billing_first_name'] ?? '') . ' ' . ($order['billing_last_name'] ?? '')) ?: 'مشتری وردپرس #' . $order['id'];
        $phone = trim((string) ($order['billing_phone'] ?? ''));
        $address = trim((string) ($order['billing_address'] ?? ''));
        $customer = $phone ? Database::one('SELECT * FROM customers WHERE phone=? LIMIT 1', [$phone]) : null;
        if (!$customer) {
            $customer = Database::one('SELECT * FROM customers WHERE name=? LIMIT 1', [$name]);
        }
        if ($customer) {
            Database::query('UPDATE customers SET name=?, phone=?, address=?, updated_at=NOW() WHERE id=?', [$name, $phone ?: $customer['phone'], $address ?: $customer['address'], (int) $customer['id']]);
            return (int) $customer['id'];
        }
        Database::query('INSERT INTO customers (name, phone, address, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', [$name, $phone ?: null, $address ?: null]);
        return (int) Database::connect()->lastInsertId();
    }
}
