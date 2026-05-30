<?php
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/Database.php';
require __DIR__ . '/../lib/Auth.php';
require __DIR__ . '/../lib/Inventory.php';
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
    return [
        'items' => Database::all('SELECT id, name, sku FROM items WHERE is_active = 1 ORDER BY name'),
        'warehouses' => Database::all('SELECT id, name FROM warehouses ORDER BY name'),
        'suppliers' => Database::all('SELECT id, name FROM suppliers ORDER BY name'),
        'customers' => Database::all('SELECT id, name FROM customers ORDER BY name'),
        'categories' => Database::all('SELECT id, name FROM categories ORDER BY name'),
        'units' => Database::all('SELECT id, name, symbol FROM units ORDER BY name'),
    ];
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
        $params = [];
        $where = [];
        if (!empty($_GET['item_id'])) {$where[]='m.item_id=?';$params[]=$_GET['item_id'];}
        if (!empty($_GET['from'])) {$where[]='DATE(m.created_at)>=?';$params[]=$_GET['from'];}
        if (!empty($_GET['to'])) {$where[]='DATE(m.created_at)<=?';$params[]=$_GET['to'];}
        $sql = 'SELECT m.*, i.name item_name, i.sku, fw.name from_name, tw.name to_name FROM stock_movements m JOIN items i ON i.id=m.item_id LEFT JOIN warehouses fw ON fw.id=m.from_warehouse_id LEFT JOIN warehouses tw ON tw.id=m.to_warehouse_id ' . ($where ? ' WHERE '.implode(' AND ', $where) : '') . ' ORDER BY m.created_at DESC LIMIT 500';
        $stale = Database::all('SELECT i.*, MAX(m.created_at) last_move FROM items i LEFT JOIN stock_movements m ON m.item_id=i.id GROUP BY i.id HAVING last_move IS NULL OR last_move < DATE_SUB(NOW(), INTERVAL 90 DAY) ORDER BY last_move ASC');
        render('reports', lists() + ['rows' => Database::all($sql, $params), 'lowItems' => Inventory::stockSummary(null, true), 'stale' => $stale]);
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
        render('contacts', ['suppliers' => Database::all('SELECT * FROM suppliers ORDER BY updated_at DESC'), 'customers' => Database::all('SELECT * FROM customers ORDER BY updated_at DESC')]);
    } elseif ($route === 'contacts/save') {
        require_permission('contacts');
        $table = ($_POST['kind'] ?? '') === 'customer' ? 'customers' : 'suppliers';
        Database::query("INSERT INTO $table (name, phone, address, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())", [$_POST['name'], $_POST['phone'] ?? null, $_POST['address'] ?? null]);
        flash('اطلاعات طرف حساب ذخیره شد.');
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
