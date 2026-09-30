<?php
final class Accounting
{
    public static function ensureSchema(): void
    {
        Database::query("CREATE TABLE IF NOT EXISTS bank_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            bank_name VARCHAR(120) NULL,
            account_no VARCHAR(120) NULL,
            iban VARCHAR(120) NULL,
            opening_balance DECIMAL(16,2) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS accounting_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            type ENUM('asset','liability','equity','income','expense') NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS journal_entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_no VARCHAR(80) NOT NULL UNIQUE,
            entry_date DATE NOT NULL,
            source_type VARCHAR(40) NOT NULL DEFAULT 'manual',
            source_id INT UNSIGNED NULL,
            description TEXT NULL,
            user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_journal_date (entry_date),
            INDEX idx_journal_source (source_type, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS journal_lines (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_entry_id INT UNSIGNED NOT NULL,
            account_id INT UNSIGNED NOT NULL,
            debit DECIMAL(16,2) NOT NULL DEFAULT 0,
            credit DECIMAL(16,2) NOT NULL DEFAULT 0,
            party_type ENUM('customer','supplier') NULL,
            party_id INT UNSIGNED NULL,
            bank_account_id INT UNSIGNED NULL,
            memo VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_journal_lines_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_journal_lines_account FOREIGN KEY (account_id) REFERENCES accounting_accounts(id),
            CONSTRAINT fk_journal_lines_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
            INDEX idx_journal_party (party_type, party_id),
            INDEX idx_journal_bank (bank_account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS accounting_item_meta (
            item_id INT UNSIGNED PRIMARY KEY,
            last_purchase_price DECIMAL(16,2) NOT NULL DEFAULT 0,
            default_sale_price DECIMAL(16,2) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_accounting_item_meta_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS invoices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_no VARCHAR(80) NOT NULL UNIQUE,
            invoice_type ENUM('sale','purchase','purchase_return') NOT NULL,
            invoice_date DATE NOT NULL,
            customer_id INT UNSIGNED NULL,
            supplier_id INT UNSIGNED NULL,
            warehouse_id INT UNSIGNED NOT NULL,
            bank_account_id INT UNSIGNED NULL,
            payment_status ENUM('cash','credit','partial') NOT NULL DEFAULT 'credit',
            subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
            discount DECIMAL(16,2) NOT NULL DEFAULT 0,
            tax DECIMAL(16,2) NOT NULL DEFAULT 0,
            shipping_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
            total DECIMAL(16,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            CONSTRAINT fk_invoices_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
            CONSTRAINT fk_invoices_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_invoices_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
            INDEX idx_invoice_type_date (invoice_type, invoice_date),
            INDEX idx_invoice_customer (customer_id),
            INDEX idx_invoice_supplier (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS invoice_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            quantity DECIMAL(14,3) NOT NULL,
            unit_price DECIMAL(16,2) NOT NULL DEFAULT 0,
            cost_price DECIMAL(16,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(16,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
            CONSTRAINT fk_invoice_items_item FOREIGN KEY (item_id) REFERENCES items(id),
            INDEX idx_invoice_items_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::seedAccounts();
    }

    public static function seedAccounts(): void
    {
        $accounts = [
            ['1000', 'صندوق/نقد', 'asset'], ['1010', 'بانک', 'asset'], ['1100', 'حساب‌های دریافتنی', 'asset'], ['1200', 'موجودی کالا', 'asset'],
            ['2000', 'حساب‌های پرداختنی', 'liability'], ['3000', 'سرمایه', 'equity'], ['4000', 'فروش', 'income'], ['4010', 'برگشت از فروش', 'income'],
            ['5000', 'بهای تمام‌شده کالای فروش‌رفته', 'expense'], ['5100', 'خرید کالا', 'expense'], ['5200', 'هزینه حمل', 'expense'], ['5300', 'سایر هزینه‌ها', 'expense'],
        ];
        foreach ($accounts as $account) {
            Database::query('INSERT IGNORE INTO accounting_accounts (code, name, type, is_system, created_at) VALUES (?, ?, ?, 1, NOW())', $account);
        }
    }

    public static function accountId(string $code): int
    {
        $row = Database::one('SELECT id FROM accounting_accounts WHERE code = ?', [$code]);
        if (!$row) {
            throw new RuntimeException('حساب حسابداری پیدا نشد: ' . $code);
        }
        return (int) $row['id'];
    }

    public static function nextNumber(string $prefix): string
    {
        return $prefix . '-' . date('Ymd-His') . '-' . random_int(100, 999);
    }

    public static function createJournal(array $data, array $lines): int
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }
        if (abs($debit - $credit) > 0.01 || $debit <= 0) {
            throw new RuntimeException('جمع بدهکار و بستانکار سند برابر نیست.');
        }
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO journal_entries (entry_no, entry_date, source_type, source_id, description, user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$data['entry_no'], $data['entry_date'], $data['source_type'] ?? 'manual', $data['source_id'] ?? null, $data['description'] ?? null, $data['user_id'] ?? null]);
            $entryId = (int) $pdo->lastInsertId();
            $lineStmt = $pdo->prepare('INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit, party_type, party_id, bank_account_id, memo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            foreach ($lines as $line) {
                $lineStmt->execute([$entryId, $line['account_id'], $line['debit'] ?? 0, $line['credit'] ?? 0, $line['party_type'] ?? null, $line['party_id'] ?? null, $line['bank_account_id'] ?? null, $line['memo'] ?? null]);
            }
            $pdo->commit();
            return $entryId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function createInvoice(array $data, array $items): int
    {
        $validItems = [];
        $subtotal = 0.0;
        $costTotal = 0.0;
        foreach ($items as $item) {
            if (empty($item['item_id']) || (float) ($item['quantity'] ?? 0) <= 0) {
                continue;
            }
            $qty = (float) $item['quantity'];
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $costPrice = (float) ($item['cost_price'] ?? 0);
            $lineTotal = $qty * $unitPrice;
            $validItems[] = ['item_id' => (int) $item['item_id'], 'quantity' => $qty, 'unit_price' => $unitPrice, 'cost_price' => $costPrice, 'line_total' => $lineTotal];
            $subtotal += $lineTotal;
            $costTotal += $qty * $costPrice;
        }
        if (!$validItems) {
            throw new RuntimeException('حداقل یک ردیف کالا برای فاکتور لازم است.');
        }

        $discount = (float) ($data['discount'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);
        $shipping = (float) ($data['shipping_cost'] ?? 0);
        $total = max(0, $subtotal - $discount + $tax + $shipping);
        $paid = min($total, max(0, (float) ($data['paid_amount'] ?? 0)));
        $type = $data['invoice_type'];

        if (in_array($type, ['sale', 'purchase_return'], true)) {
            $requested = [];
            foreach ($validItems as $item) {
                $requested[$item['item_id']] = ($requested[$item['item_id']] ?? 0) + $item['quantity'];
            }
            foreach ($requested as $itemId => $quantity) {
                if (Inventory::currentStock((int) $itemId, (int) $data['warehouse_id']) + 0.0001 < $quantity) {
                    throw new RuntimeException('موجودی کالای انتخاب‌شده برای ثبت فاکتور کافی نیست.');
                }
            }
        }

        Database::query('INSERT INTO invoices (invoice_no, invoice_type, invoice_date, customer_id, supplier_id, warehouse_id, bank_account_id, payment_status, subtotal, discount, tax, shipping_cost, total, paid_amount, notes, user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
            $data['invoice_no'], $type, $data['invoice_date'], $data['customer_id'] ?: null, $data['supplier_id'] ?: null, $data['warehouse_id'], $data['bank_account_id'] ?: null, $data['payment_status'] ?? 'credit', $subtotal, $discount, $tax, $shipping, $total, $paid, $data['notes'] ?? null, $data['user_id'] ?? null,
        ]);
        $invoiceId = (int) Database::connect()->lastInsertId();

        foreach ($validItems as $lineNo => $item) {
            Database::query('INSERT INTO invoice_items (invoice_id, item_id, quantity, unit_price, cost_price, line_total) VALUES (?, ?, ?, ?, ?, ?)', [$invoiceId, $item['item_id'], $item['quantity'], $item['unit_price'], $item['cost_price'], $item['line_total']]);
            if ($type === 'purchase') {
                Database::query('INSERT INTO accounting_item_meta (item_id, last_purchase_price, default_sale_price, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE last_purchase_price = VALUES(last_purchase_price), default_sale_price = IF(default_sale_price = 0, VALUES(default_sale_price), default_sale_price), updated_at = NOW()', [$item['item_id'], $item['unit_price'], $item['unit_price']]);
                Inventory::move(['reference_no' => $data['invoice_no'] . '-' . $invoiceId . '-' . ($lineNo + 1), 'type' => 'in', 'item_id' => $item['item_id'], 'quantity' => $item['quantity'], 'from_warehouse_id' => null, 'to_warehouse_id' => $data['warehouse_id'], 'supplier_id' => $data['supplier_id'] ?: null, 'customer_id' => null, 'user_id' => $data['user_id'] ?? null, 'notes' => 'ثبت خودکار از فاکتور خرید']);
            } elseif ($type === 'sale') {
                Inventory::move(['reference_no' => $data['invoice_no'] . '-' . $invoiceId . '-' . ($lineNo + 1), 'type' => 'out', 'item_id' => $item['item_id'], 'quantity' => $item['quantity'], 'from_warehouse_id' => $data['warehouse_id'], 'to_warehouse_id' => null, 'supplier_id' => null, 'customer_id' => $data['customer_id'] ?: null, 'user_id' => $data['user_id'] ?? null, 'notes' => 'ثبت خودکار از فاکتور فروش']);
            } elseif ($type === 'purchase_return') {
                Inventory::move(['reference_no' => $data['invoice_no'] . '-' . $invoiceId . '-' . ($lineNo + 1), 'type' => 'out', 'item_id' => $item['item_id'], 'quantity' => $item['quantity'], 'from_warehouse_id' => $data['warehouse_id'], 'to_warehouse_id' => null, 'supplier_id' => $data['supplier_id'] ?: null, 'customer_id' => null, 'user_id' => $data['user_id'] ?? null, 'notes' => 'ثبت خودکار از برگشت از خرید']);
            }
        }

        self::postInvoiceJournal($invoiceId, $data, $total, $paid, $subtotal, $costTotal, $shipping);
        return $invoiceId;
    }

    private static function postInvoiceJournal(int $invoiceId, array $data, float $total, float $paid, float $subtotal, float $costTotal, float $shipping): void
    {
        $type = $data['invoice_type'];
        $lines = [];
        $partyType = $type === 'sale' ? 'customer' : 'supplier';
        $partyId = $type === 'sale' ? ($data['customer_id'] ?: null) : ($data['supplier_id'] ?: null);
        if ($type === 'sale') {
            if ($paid > 0) $lines[] = ['account_id' => self::accountId($data['bank_account_id'] ? '1010' : '1000'), 'debit' => $paid, 'bank_account_id' => $data['bank_account_id'] ?: null, 'memo' => 'دریافت بابت فروش'];
            if ($total - $paid > 0.01) $lines[] = ['account_id' => self::accountId('1100'), 'debit' => $total - $paid, 'party_type' => 'customer', 'party_id' => $partyId, 'memo' => 'طلب از مشتری'];
            $lines[] = ['account_id' => self::accountId('4000'), 'credit' => $subtotal, 'memo' => 'فروش کالا'];
            if (($data['tax'] ?? 0) > 0) $lines[] = ['account_id' => self::accountId('4000'), 'credit' => (float) $data['tax'], 'memo' => 'مالیات/اضافات فروش'];
            if (($data['discount'] ?? 0) > 0) $lines[] = ['account_id' => self::accountId('4000'), 'debit' => (float) $data['discount'], 'memo' => 'تخفیف فروش'];
            if ($shipping > 0) $lines[] = ['account_id' => self::accountId('4000'), 'credit' => $shipping, 'memo' => 'درآمد/دریافت حمل فروش'];
            if ($costTotal > 0) {
                $lines[] = ['account_id' => self::accountId('5000'), 'debit' => $costTotal, 'memo' => 'بهای تمام‌شده'];
                $lines[] = ['account_id' => self::accountId('1200'), 'credit' => $costTotal, 'memo' => 'کاهش موجودی کالا'];
            }
        } else {
            if ($type === 'purchase') {
                $lines[] = ['account_id' => self::accountId('1200'), 'debit' => $subtotal, 'memo' => 'خرید و افزایش موجودی'];
                if (($data['tax'] ?? 0) > 0) $lines[] = ['account_id' => self::accountId('1200'), 'debit' => (float) $data['tax'], 'memo' => 'مالیات/اضافات خرید'];
                if ($shipping > 0) $lines[] = ['account_id' => self::accountId('5200'), 'debit' => $shipping, 'memo' => 'هزینه حمل خرید'];
                if (($data['discount'] ?? 0) > 0) $lines[] = ['account_id' => self::accountId('1200'), 'credit' => (float) $data['discount'], 'memo' => 'تخفیف خرید'];
                if ($paid > 0) $lines[] = ['account_id' => self::accountId($data['bank_account_id'] ? '1010' : '1000'), 'credit' => $paid, 'bank_account_id' => $data['bank_account_id'] ?: null, 'memo' => 'پرداخت بابت خرید'];
                if ($total - $paid > 0.01) $lines[] = ['account_id' => self::accountId('2000'), 'credit' => $total - $paid, 'party_type' => 'supplier', 'party_id' => $partyId, 'memo' => 'بدهی به تامین‌کننده'];
            } else {
                $lines[] = ['account_id' => self::accountId('2000'), 'debit' => $total, 'party_type' => 'supplier', 'party_id' => $partyId, 'memo' => 'کاهش بدهی بابت برگشت از خرید'];
                $lines[] = ['account_id' => self::accountId('1200'), 'credit' => $subtotal, 'memo' => 'کاهش موجودی بابت برگشت از خرید'];
                if (($data['tax'] ?? 0) > 0) $lines[] = ['account_id' => self::accountId('1200'), 'credit' => (float) $data['tax'], 'memo' => 'برگشت مالیات/اضافات خرید'];
                if (($data['discount'] ?? 0) > 0) $lines[] = ['account_id' => self::accountId('1200'), 'debit' => (float) $data['discount'], 'memo' => 'برگشت تخفیف خرید'];
                if ($shipping > 0) $lines[] = ['account_id' => self::accountId('5200'), 'credit' => $shipping, 'memo' => 'برگشت هزینه حمل'];
            }
        }
        self::createJournal(['entry_no' => 'JE-' . $data['invoice_no'], 'entry_date' => $data['invoice_date'], 'source_type' => $type, 'source_id' => $invoiceId, 'description' => 'ثبت خودکار فاکتور ' . $data['invoice_no'], 'user_id' => $data['user_id'] ?? null], $lines);
    }

    public static function profitLoss(?string $from = null, ?string $to = null): array
    {
        $filters = [];
        $params = [];
        if ($from) { $filters[] = 'je.entry_date >= ?'; $params[] = $from; }
        if ($to) { $filters[] = 'je.entry_date <= ?'; $params[] = $to; }
        $dateSql = $filters ? ' AND ' . implode(' AND ', $filters) : '';
        $rows = Database::all("SELECT a.code, a.name, a.type, COALESCE(SUM(CASE WHEN je.id IS NOT NULL" . $dateSql . " THEN jl.credit - jl.debit ELSE 0 END),0) balance FROM accounting_accounts a LEFT JOIN journal_lines jl ON jl.account_id = a.id LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE a.type IN ('income','expense') GROUP BY a.id ORDER BY a.code", $params);
        $income = 0.0; $expense = 0.0;
        foreach ($rows as $row) {
            if ($row['type'] === 'income') $income += (float) $row['balance'];
            if ($row['type'] === 'expense') $expense += abs((float) $row['balance']);
        }
        return ['rows' => $rows, 'income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }

}
