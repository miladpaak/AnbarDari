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

$route = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$basePath = trim(str_replace('/public/index.php', '', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($basePath && str_starts_with($route, $basePath)) {
    $route = trim(substr($route, strlen($basePath)), '/');
}
$route = $route ?: 'dashboard';

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
            'items' => Database::one('SELECT COUNT(*) c FROM items')['c'] ?? 0,
            'stock' => Database::one('SELECT COALESCE(SUM(quantity),0) c FROM stocks')['c'] ?? 0,
            'low' => count(Inventory::stockSummary(null, true)),
            'today' => Database::one('SELECT COUNT(*) c FROM stock_movements WHERE DATE(created_at)=CURDATE()')['c'] ?? 0,
        ];
        render('dashboard', ['stats' => $stats, 'lowItems' => Inventory::stockSummary(null, true)]);
    } elseif ($route === 'items') {
        require_permission('view');
        $q = $_GET['q'] ?? null;
        render('items', ['rows' => Inventory::stockSummary($q), 'q' => $q]);
    } elseif ($route === 'items/save') {
        require_permission('manage_items');
        $id = (int) ($_POST['id'] ?? 0);
        $barcode = $_POST['barcode'] ?: ($_POST['sku'] ?? '');
        $params = [$_POST['sku'], $barcode, $_POST['name'], $_POST['category_id'] ?: null, $_POST['unit_id'] ?: null, $_POST['min_stock'] ?: 0, $_POST['description'] ?? null];
        if ($id) {
            $params[] = $id;
            Database::query('UPDATE items SET sku=?, barcode=?, name=?, category_id=?, unit_id=?, min_stock=?, description=?, updated_at=NOW() WHERE id=?', $params);
        } else {
            Database::query('INSERT INTO items (sku, barcode, name, category_id, unit_id, min_stock, description, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())', $params);
        }
        flash('کالا ذخیره شد.');
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
