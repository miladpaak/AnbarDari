<?php
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/Database.php';
require __DIR__ . '/../lib/Auth.php';
require __DIR__ . '/../lib/Inventory.php';
require __DIR__ . '/../lib/Accounting.php';
require __DIR__ . '/../lib/WordPressSync.php';
require __DIR__ . '/../lib/Barcode.php';

$config = app_config();
session_name($config['security']['session_name'] ?? 'DP_SESSION');
session_start();
verify_csrf();

$requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/');
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$basePath = trim(preg_replace('#/(public/)?index\.php$#', '', $scriptName) ?: '', '/');

if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
    $requestPath = trim(substr($requestPath, strlen($basePath)), '/');
}
if (str_starts_with($requestPath, 'public/')) {
    $requestPath = trim(substr($requestPath, strlen('public/')), '/');
}
if ($requestPath === 'index.php') {
    $requestPath = '';
}
$route = $requestPath ?: 'dashboard';

function render(string $view, array $data = []): void
{
    extract($data);
    $currentRoute = $GLOBALS['route'];
    require __DIR__ . '/../views/layout.php';
}

function lists(): array
{
    Accounting::ensureSchema();
    return [
        'items' => Database::all('SELECT id, name, sku FROM items WHERE is_active = 1 ORDER BY name'),
        'warehouses' => Database::all('SELECT id, name FROM warehouses ORDER BY name'),
        'suppliers' => Database::all('SELECT id, name FROM suppliers ORDER BY name'),
        'customers' => Database::all('SELECT id, name FROM customers ORDER BY name'),
        'categories' => Database::all('SELECT id, name FROM categories ORDER BY name'),
        'units' => Database::all('SELECT id, name, symbol FROM units ORDER BY name'),
        'banks' => Database::all('SELECT id, name, bank_name FROM bank_accounts WHERE is_active = 1 ORDER BY name'),
        'accounts' => Database::all('SELECT id, code, name, type FROM accounting_accounts ORDER BY code'),
    ];
}



function report_filters(): array
{
    $params = [];
    $where = [];
    if (!empty($_GET['item_id'])) {
        $where[] = 'm.item_id=?';
        $params[] = $_GET['item_id'];
    }
    if (!empty($_GET['from'])) {
        $where[] = 'DATE(m.created_at)>=?';
        $params[] = $_GET['from'];
    }
    if (!empty($_GET['to'])) {
        $where[] = 'DATE(m.created_at)<=?';
        $params[] = $_GET['to'];
    }
    return [$where, $params];
}

function report_rows(): array
{
    [$where, $params] = report_filters();
    $sql = 'SELECT m.*, i.name item_name, i.sku, fw.name from_name, tw.name to_name
            FROM stock_movements m
            JOIN items i ON i.id=m.item_id
            LEFT JOIN warehouses fw ON fw.id=m.from_warehouse_id
            LEFT JOIN warehouses tw ON tw.id=m.to_warehouse_id '
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY m.created_at DESC LIMIT 500';
    return Database::all($sql, $params);
}

function export_reports_xlsx(array $rows): never
{
    $headers = ['تاریخ شمسی', 'شماره ثبت', 'نوع', 'کالا', 'کد کالا', 'مقدار', 'از انبار', 'به انبار'];
    $data = [$headers];
    foreach ($rows as $row) {
        $data[] = [
            jalali_like_datetime($row['created_at']),
            $row['reference_no'],
            $row['type'],
            $row['item_name'],
            $row['sku'],
            moneyless_number($row['quantity']),
            $row['from_name'] ?: '-',
            $row['to_name'] ?: '-',
        ];
    }

    if (!class_exists('ZipArchive')) {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="inventory-report.xls"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="UTF-8"></head><body dir="rtl"><table border="1">';
        foreach ($data as $line) {
            echo '<tr>';
            foreach ($line as $cell) {
                echo '<td>' . e((string) $cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="گزارش گردش" sheetId="1" r:id="rId1"/></sheets></workbook>');

    $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" rightToLeft="1"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews><sheetData>';
    foreach ($data as $rIndex => $line) {
        $sheet .= '<row r="' . ($rIndex + 1) . '">';
        foreach ($line as $cIndex => $cell) {
            $ref = chr(65 + $cIndex) . ($rIndex + 1);
            $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $cell, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</t></is></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="inventory-report.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

function export_reports_pdf_html(array $rows): never
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="inventory-report-pdf.html"');
    echo "\xEF\xBB\xBF";
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>خروجی PDF گزارش گردش کالا</title><style>body{font-family:Tahoma,Arial,sans-serif;direction:rtl;padding:20px;color:#111}table{width:100%;border-collapse:collapse;direction:rtl}th,td{border:1px solid #999;padding:7px;text-align:right}th{background:#eee}.no-print{margin-bottom:16px}@media print{.no-print{display:none}@page{size:A4 landscape;margin:12mm}}</style></head><body><div class="no-print"><button onclick="window.print()">چاپ / ذخیره PDF</button><p>برای دریافت PDF، در پنجره چاپ گزینه Save as PDF را انتخاب کنید. خروجی UTF-8 و راست‌به‌چپ است.</p></div><h1>گزارش گردش کالا</h1><table><tr><th>تاریخ شمسی</th><th>شماره ثبت</th><th>نوع</th><th>کالا</th><th>کد کالا</th><th>مقدار</th><th>از انبار</th><th>به انبار</th></tr>';
    foreach ($rows as $row) {
        echo '<tr><td>' . e(jalali_like_datetime($row['created_at'])) . '</td><td>' . e($row['reference_no']) . '</td><td>' . e($row['type']) . '</td><td>' . e($row['item_name']) . '</td><td>' . e($row['sku']) . '</td><td>' . e(moneyless_number($row['quantity'])) . '</td><td>' . e($row['from_name'] ?: '-') . '</td><td>' . e($row['to_name'] ?: '-') . '</td></tr>';
    }
    echo '</table><script>window.onload=function(){setTimeout(function(){window.print()},300)}</script></body></html>';
    exit;
}

function ensure_audit_log_table(): void
{
    Database::query("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        item_id INT UNSIGNED NULL,
        action VARCHAR(40) NOT NULL,
        old_data JSON NULL,
        new_data JSON NULL,
        ip_address VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_action_date (action, created_at),
        INDEX idx_audit_item_date (item_id, created_at),
        INDEX idx_audit_user_date (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function log_item_audit(string $action, ?int $itemId, ?array $oldData, ?array $newData, array $user): void
{
    ensure_audit_log_table();
    Database::query('INSERT INTO audit_logs (user_id, item_id, action, old_data, new_data, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())', [
        $user['id'] ?? null,
        $itemId,
        $action,
        $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
        $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

try {
    if ($route === 'login') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (Auth::attempt($_POST['username'] ?? '', $_POST['password'] ?? '')) {
                redirect('dashboard');
            }
            flash('نام کاربری یا رمز عبور اشتباه است.', 'error');
        }
        require __DIR__ . '/../views/login.php';
        exit;
    }
    if ($route === 'logout') {
        Auth::logout();
        redirect('login');
    }

    $user = require_login();

    if ($route === 'dashboard') {
        $stats = [
            'items' => Database::one('SELECT COUNT(*) c FROM items WHERE is_active = 1')['c'] ?? 0,
            'stock' => Database::one('SELECT COALESCE(SUM(s.quantity),0) c FROM stocks s JOIN items i ON i.id = s.item_id WHERE i.is_active = 1')['c'] ?? 0,
            'low' => count(Inventory::stockSummary(null, true)),
            'today' => Database::one('SELECT COUNT(*) c FROM stock_movements WHERE DATE(created_at)=CURDATE()')['c'] ?? 0,
        ];
        render('dashboard', ['stats' => $stats, 'lowItems' => Inventory::stockSummary(null, true)]);
    } elseif ($route === 'items') {
        require_permission('view');
        $q = $_GET['q'] ?? null;
        $editItem = null;
        if (!empty($_GET['edit'])) {
            if (!is_warehouse_manager()) {
                http_response_code(403);
                exit('فقط مدیر انبار اجازه ویرایش کالا را دارد.');
            }
            $editItem = Database::one('SELECT * FROM items WHERE id = ? AND is_active = 1', [(int) $_GET['edit']]);
            if (!$editItem) {
                flash('کالای مورد نظر برای ویرایش پیدا نشد.', 'error');
                redirect('items');
            }
        }
        render('items', ['rows' => Inventory::stockSummary($q), 'q' => $q, 'editItem' => $editItem]);
    } elseif ($route === 'items/save') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && !is_warehouse_manager()) {
            http_response_code(403);
            exit('فقط مدیر انبار اجازه ویرایش کالا را دارد.');
        }
        if (!$id) {
            require_permission('manage_items');
        }
        $oldItem = $id ? Database::one('SELECT * FROM items WHERE id = ? AND is_active = 1', [$id]) : null;
        if ($id && !$oldItem) {
            flash('کالای مورد نظر برای ویرایش پیدا نشد.', 'error');
            redirect('items');
        }
        $barcode = $_POST['barcode'] ?: ($_POST['sku'] ?? '');
        $params = [$_POST['sku'], $barcode, $_POST['name'], $_POST['category_id'] ?: null, $_POST['unit_id'] ?: null, $_POST['min_stock'] ?: 0, $_POST['description'] ?? null];
        if ($id) {
            $params[] = $id;
            Database::query('UPDATE items SET sku=?, barcode=?, name=?, category_id=?, unit_id=?, min_stock=?, description=?, updated_at=NOW() WHERE id=?', $params);
            $newItem = Database::one('SELECT * FROM items WHERE id = ?', [$id]);
            log_item_audit('edit_item', $id, $oldItem, $newItem, $user);
        } else {
            Database::query('INSERT INTO items (sku, barcode, name, category_id, unit_id, min_stock, description, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())', $params);
        }
        flash($id ? 'تغییرات کالا ذخیره شد.' : 'کالا ذخیره شد.');
        redirect('items');
    } elseif ($route === 'items/delete') {
        if (!is_warehouse_manager()) {
            http_response_code(403);
            exit('فقط مدیر انبار اجازه حذف کالا را دارد.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $item = Database::one('SELECT * FROM items WHERE id = ? AND is_active = 1', [$id]);
        if (!$item) {
            flash('کالای مورد نظر برای حذف پیدا نشد.', 'error');
            redirect('items');
        }
        $movementCount = (int) (Database::one('SELECT COUNT(*) c FROM stock_movements WHERE item_id = ?', [$id])['c'] ?? 0);
        if ($movementCount === 0) {
            Database::query('DELETE FROM items WHERE id = ?', [$id]);
            log_item_audit('delete_item', $id, $item, null, $user);
            flash('کالا به‌طور کامل حذف شد.');
        } else {
            Database::query('UPDATE items SET is_active = 0, updated_at = NOW() WHERE id = ?', [$id]);
            $newItem = Database::one('SELECT * FROM items WHERE id = ?', [$id]);
            log_item_audit('deactivate_item', $id, $item, $newItem, $user);
            flash('کالا از لیست فعال حذف شد. سوابق گردش و موجودی آن برای گزارش‌ها حفظ می‌شود.');
        }
        redirect('items');
    } elseif ($route === 'movement') {
        require_permission('stock_in');
        render('movement', lists() + ['type' => $_GET['type'] ?? 'in', 'reference' => Inventory::nextReference()]);
    } elseif ($route === 'movement/save') {
        $type = $_POST['type'] ?? 'in';
        require_permission($type === 'out' ? 'stock_out' : ($type === 'transfer' ? 'transfer' : 'stock_in'));
        $id = Inventory::move([
            'reference_no' => $_POST['reference_no'] ?: Inventory::nextReference(),
            'type' => $type,
            'item_id' => $_POST['item_id'],
            'quantity' => $_POST['quantity'],
            'from_warehouse_id' => $_POST['from_warehouse_id'] ?? null,
            'to_warehouse_id' => $_POST['to_warehouse_id'] ?? null,
            'supplier_id' => $_POST['supplier_id'] ?? null,
            'customer_id' => $_POST['customer_id'] ?? null,
            'user_id' => $user['id'],
            'notes' => $_POST['notes'] ?? null,
        ]);
        flash('رسید/حواله ثبت شد.');
        redirect('movement/print?id=' . $id);
    } elseif ($route === 'movement/print') {
        require_permission('view');
        $row = Database::one('SELECT m.*, i.name item_name, i.sku, fw.name from_name, tw.name to_name, u.name user_name
            FROM stock_movements m
            JOIN items i ON i.id=m.item_id
            LEFT JOIN warehouses fw ON fw.id=m.from_warehouse_id
            LEFT JOIN warehouses tw ON tw.id=m.to_warehouse_id
            LEFT JOIN users u ON u.id=m.user_id
            WHERE m.id=?', [(int) ($_GET['id'] ?? 0)]);
        render('print_movement', ['row' => $row]);
    } elseif ($route === 'reports') {
        require_permission('reports');
        $stale = Database::all('SELECT i.*, MAX(m.created_at) last_move FROM items i LEFT JOIN stock_movements m ON m.item_id=i.id GROUP BY i.id HAVING last_move IS NULL OR last_move < DATE_SUB(NOW(), INTERVAL 90 DAY) ORDER BY last_move ASC');
        render('reports', lists() + ['rows' => report_rows(), 'lowItems' => Inventory::stockSummary(null, true), 'stale' => $stale]);
    } elseif ($route === 'reports/export-xlsx') {
        require_permission('reports');
        export_reports_xlsx(report_rows());
    } elseif ($route === 'reports/export-pdf') {
        require_permission('reports');
        export_reports_pdf_html(report_rows());
    } elseif ($route === 'accounting') {
        require_permission('accounting');
        Accounting::ensureSchema();
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $pl = Accounting::profitLoss($from, $to);
        $salesToday = Database::one("SELECT COALESCE(SUM(total),0) c FROM invoices WHERE invoice_type='sale' AND invoice_date = CURDATE()")['c'] ?? 0;
        $salesMonth = Database::one("SELECT COALESCE(SUM(total),0) c FROM invoices WHERE invoice_type='sale' AND YEAR(invoice_date)=YEAR(CURDATE()) AND MONTH(invoice_date)=MONTH(CURDATE())")['c'] ?? 0;
        $gross = Database::one("SELECT COALESCE(SUM(ii.line_total - (ii.quantity * ii.cost_price)),0) c FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE i.invoice_type='sale'")['c'] ?? 0;
        $debtors = Database::all("SELECT c.name, COALESCE(SUM(i.total - i.paid_amount),0) balance FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.invoice_type='sale' AND i.total > i.paid_amount GROUP BY c.id HAVING balance > 0 ORDER BY balance DESC LIMIT 10");
        $creditors = Database::all("SELECT s.name, COALESCE(SUM(i.total - i.paid_amount),0) balance FROM invoices i JOIN suppliers s ON s.id=i.supplier_id WHERE i.invoice_type='purchase' AND i.total > i.paid_amount GROUP BY s.id HAVING balance > 0 ORDER BY balance DESC LIMIT 10");
        render('accounting_dashboard', ['pl' => $pl, 'salesToday' => $salesToday, 'salesMonth' => $salesMonth, 'gross' => $gross, 'debtors' => $debtors, 'creditors' => $creditors, 'from' => $from, 'to' => $to]);
    } elseif ($route === 'accounting/wordpress') {
        require_permission('accounting');
        WordPressSync::ensureSchema();
        $status = null;
        if (WordPressSync::isConfigured()) {
            try {
                $status = WordPressSync::status();
            } catch (Throwable $exception) {
                $status = ['ok' => false, 'message' => $exception->getMessage()];
            }
        } else {
            $status = ['ok' => false, 'message' => 'تنظیمات دیتابیس وردپرس در config.php کامل نیست.'];
        }
        render('wordpress_sync', lists() + ['status' => $status]);
    } elseif ($route === 'accounting/wordpress/import-products') {
        require_permission('accounting');
        $result = WordPressSync::importProducts((int) $_POST['warehouse_id']);
        flash('همگام‌سازی محصولات وردپرس انجام شد. جدید: ' . $result['created'] . '، بروزرسانی: ' . $result['updated'] . '، کل: ' . $result['total']);
        redirect('accounting/wordpress');
    } elseif ($route === 'accounting/wordpress/import-orders') {
        require_permission('accounting');
        $result = WordPressSync::importOrders((int) $_POST['warehouse_id'], (int) $user['id'], (int) ($_POST['limit'] ?? 50));
        $message = 'همگام‌سازی سفارش‌های وردپرس انجام شد. فاکتور جدید: ' . $result['created'] . '، تکراری/بدون آیتم: ' . $result['skipped'];
        if ($result['failed']) {
            $message .= '، خطا: ' . implode(' | ', array_slice($result['failed'], 0, 3));
            flash($message, 'error');
        } else {
            flash($message);
        }
        redirect('accounting/wordpress');
    } elseif ($route === 'accounting/banks') {
        require_permission('accounting');
        Accounting::ensureSchema();
        render('bank_accounts', ['banks' => Database::all('SELECT * FROM bank_accounts ORDER BY updated_at DESC')]);
    } elseif ($route === 'accounting/banks/save') {
        require_permission('accounting');
        Accounting::ensureSchema();
        Database::query('INSERT INTO bank_accounts (name, bank_name, account_no, iban, opening_balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())', [$_POST['name'], $_POST['bank_name'] ?? null, $_POST['account_no'] ?? null, $_POST['iban'] ?? null, $_POST['opening_balance'] ?: 0]);
        flash('حساب بانکی ذخیره شد.');
        redirect('accounting/banks');
    } elseif ($route === 'accounting/journal') {
        require_permission('accounting');
        Accounting::ensureSchema();
        $rows = Database::all('SELECT je.*, COALESCE(SUM(jl.debit),0) debit, COALESCE(SUM(jl.credit),0) credit FROM journal_entries je LEFT JOIN journal_lines jl ON jl.journal_entry_id=je.id GROUP BY je.id ORDER BY je.entry_date DESC, je.id DESC LIMIT 100');
        render('journal', lists() + ['rows' => $rows, 'entryNo' => Accounting::nextNumber('JE')]);
    } elseif ($route === 'accounting/journal/save') {
        require_permission('accounting');
        Accounting::ensureSchema();
        $lines = [];
        foreach (($_POST['lines'] ?? []) as $line) {
            if (empty($line['account_id'])) continue;
            $lines[] = ['account_id' => (int) $line['account_id'], 'debit' => (float) ($line['debit'] ?? 0), 'credit' => (float) ($line['credit'] ?? 0), 'party_type' => $line['party_type'] ?: null, 'party_id' => $line['party_id'] ?: null, 'bank_account_id' => $line['bank_account_id'] ?: null, 'memo' => $line['memo'] ?? null];
        }
        Accounting::createJournal(['entry_no' => $_POST['entry_no'] ?: Accounting::nextNumber('JE'), 'entry_date' => $_POST['entry_date'] ?: date('Y-m-d'), 'description' => $_POST['description'] ?? null, 'user_id' => $user['id']], $lines);
        flash('سند دستی حسابداری ثبت شد.');
        redirect('accounting/journal');
    } elseif ($route === 'accounting/invoices') {
        require_permission('accounting');
        Accounting::ensureSchema();
        $type = $_GET['type'] ?? 'sale';
        $rows = Database::all('SELECT i.*, c.name customer_name, s.name supplier_name, w.name warehouse_name FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id LEFT JOIN suppliers s ON s.id=i.supplier_id JOIN warehouses w ON w.id=i.warehouse_id WHERE i.invoice_type=? ORDER BY i.invoice_date DESC, i.id DESC LIMIT 100', [$type]);
        $itemMeta = Database::all('SELECT item_id, last_purchase_price, default_sale_price FROM accounting_item_meta');
        render('invoices', lists() + ['type' => $type, 'rows' => $rows, 'invoiceNo' => Accounting::nextNumber($type === 'sale' ? 'SALE' : ($type === 'purchase' ? 'BUY' : 'PR')), 'itemMeta' => $itemMeta]);
    } elseif ($route === 'accounting/invoices/save') {
        require_permission('accounting');
        Accounting::ensureSchema();
        $id = Accounting::createInvoice([
            'invoice_no' => $_POST['invoice_no'] ?: Accounting::nextNumber('INV'),
            'invoice_type' => $_POST['invoice_type'],
            'invoice_date' => $_POST['invoice_date'] ?: date('Y-m-d'),
            'customer_id' => $_POST['customer_id'] ?? null,
            'supplier_id' => $_POST['supplier_id'] ?? null,
            'warehouse_id' => $_POST['warehouse_id'],
            'bank_account_id' => $_POST['bank_account_id'] ?? null,
            'payment_status' => $_POST['payment_status'] ?? 'credit',
            'discount' => $_POST['discount'] ?? 0,
            'tax' => $_POST['tax'] ?? 0,
            'shipping_cost' => $_POST['shipping_cost'] ?? 0,
            'paid_amount' => $_POST['paid_amount'] ?? 0,
            'notes' => $_POST['notes'] ?? null,
            'user_id' => $user['id'],
        ], $_POST['items'] ?? []);
        flash('فاکتور ثبت شد و گردش انبار/سند حسابداری آن به‌صورت خودکار ایجاد شد.');
        redirect('accounting/invoices?type=' . urlencode($_POST['invoice_type']));
    } elseif ($route === 'accounting/customer') {
        require_permission('accounting');
        Accounting::ensureSchema();
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $customer = $customerId ? Database::one('SELECT * FROM customers WHERE id=?', [$customerId]) : null;
        $invoices = $customerId ? Database::all("SELECT * FROM invoices WHERE customer_id=? AND invoice_type='sale' ORDER BY invoice_date DESC", [$customerId]) : [];
        $journals = $customerId ? Database::all("SELECT je.entry_date, je.entry_no, jl.debit, jl.credit, jl.memo FROM journal_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.party_type='customer' AND jl.party_id=? ORDER BY je.entry_date DESC", [$customerId]) : [];
        render('customer_file', lists() + ['customer' => $customer, 'invoices' => $invoices, 'journals' => $journals]);
    } elseif ($route === 'accounting/sales-report') {
        require_permission('accounting');
        Accounting::ensureSchema();
        $group = ($_GET['group'] ?? 'daily') === 'monthly' ? '%Y-%m' : '%Y-%m-%d';
        $rows = Database::all("SELECT DATE_FORMAT(i.invoice_date, '$group') period, COUNT(*) invoice_count, COALESCE(SUM(i.total),0) total, COALESCE(SUM(ii.quantity * ii.cost_price),0) cost, COALESCE(SUM(ii.line_total - ii.quantity * ii.cost_price),0) gross_profit FROM invoices i LEFT JOIN invoice_items ii ON ii.invoice_id=i.id WHERE i.invoice_type='sale' GROUP BY period ORDER BY period DESC LIMIT 60");
        render('sales_report', ['rows' => $rows, 'group' => $_GET['group'] ?? 'daily']);
    } elseif ($route === 'audit-log') {
        if (!is_admin()) {
            http_response_code(403);
            exit('فقط ادمین اصلی اجازه مشاهده تاریخچه ویرایش و حذف را دارد.');
        }
        ensure_audit_log_table();
        $rows = Database::all('SELECT a.*, u.name user_name, u.username, i.name item_name, i.sku FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN items i ON i.id = a.item_id ORDER BY a.created_at DESC LIMIT 300');
        render('audit_log', ['rows' => $rows]);
    } elseif ($route === 'contacts') {
        require_permission('contacts');
        $editSupplier = !empty($_GET['edit_supplier']) ? Database::one('SELECT * FROM suppliers WHERE id = ?', [(int) $_GET['edit_supplier']]) : null;
        $editCustomer = !empty($_GET['edit_customer']) ? Database::one('SELECT * FROM customers WHERE id = ?', [(int) $_GET['edit_customer']]) : null;
        render('contacts', [
            'suppliers' => Database::all('SELECT * FROM suppliers ORDER BY updated_at DESC'),
            'customers' => Database::all('SELECT * FROM customers ORDER BY updated_at DESC'),
            'editSupplier' => $editSupplier,
            'editCustomer' => $editCustomer,
        ]);
    } elseif ($route === 'contacts/save') {
        require_permission('contacts');
        $table = ($_POST['kind'] ?? '') === 'customer' ? 'customers' : 'suppliers';
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            Database::query("UPDATE $table SET name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?", [$_POST['name'], $_POST['phone'] ?? null, $_POST['address'] ?? null, $id]);
            flash('اطلاعات طرف حساب ویرایش شد.');
        } else {
            Database::query("INSERT INTO $table (name, phone, address, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())", [$_POST['name'], $_POST['phone'] ?? null, $_POST['address'] ?? null]);
            flash('اطلاعات طرف حساب ذخیره شد.');
        }
        redirect('contacts');
    } elseif ($route === 'settings') {
        require_permission('manage_items');
        render('settings', lists() + ['users' => Database::all('SELECT id,name,username,role,is_active,updated_at FROM users ORDER BY updated_at DESC')]);
    } elseif ($route === 'settings/save-basic') {
        require_permission('manage_items');
        $table = match ($_POST['kind'] ?? '') {'unit' => 'units', 'warehouse' => 'warehouses', default => 'categories'};
        $extra = $table === 'warehouses' ? 'location' : ($table === 'units' ? 'symbol' : 'description');
        Database::query("INSERT INTO $table (name, $extra, created_at, updated_at) VALUES (?, ?, NOW(), NOW())", [$_POST['name'], $_POST['extra'] ?? null]);
        flash('اطلاعات پایه ذخیره شد.');
        redirect('settings');
    } elseif ($route === 'users/save') {
        require_permission('manage_items');
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        Database::query('INSERT INTO users (name, username, password_hash, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())', [$_POST['name'], $_POST['username'], $hash, $_POST['role']]);
        flash('کاربر جدید ساخته شد.');
        redirect('settings');
    } else {
        http_response_code(404);
        echo 'صفحه پیدا نشد.';
    }
} catch (Throwable $exception) {
    http_response_code(500);
    render('error', ['message' => $exception->getMessage()]);
}
