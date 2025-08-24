<?php
// api/products.php
header('Content-Type: application/json');
require __DIR__ . '/db.php';

// Ensure products table exists
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(80) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    reorder_point INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255) DEFAULT NULL,
    barcode VARCHAR(128) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
  // Ensure reorder_point exists on legacy tables
  try { $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS reorder_point INT NOT NULL DEFAULT 0"); } catch (Throwable $__) {}
  // Ensure barcode exists on legacy tables
  try { $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode VARCHAR(128) UNIQUE"); } catch (Throwable $__) {}
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Support method override for multipart form updates (e.g., POST with _method=PATCH)
if ($method === 'POST' && isset($_POST['_method'])) {
  $override = strtoupper(trim($_POST['_method']));
  if (in_array($override, ['PUT','PATCH','DELETE','GET'], true)) {
    $method = $override;
  }
}

if ($method === 'DELETE') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Valid product ID required']); exit; }
  try {
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

if ($method === 'GET') {
  $stmt = $pdo->query('SELECT id, name, category, price, stock, reorder_point, image_url, barcode FROM products ORDER BY name');
  $products = $stmt->fetchAll();
  error_log("GET products: " . print_r($products, true));
  echo json_encode(['ok' => true, 'data' => $products]);
  exit;
}

if ($method === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $price = (float)($_POST['price'] ?? 0);
  $stock = (int)($_POST['stock'] ?? 0);
  $reorder_point = isset($_POST['reorder_point']) ? (int)$_POST['reorder_point'] : 0;
  $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : null;
  if ($barcode === '') { $barcode = null; }
  
  if ($name === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Name required']); exit; }
  
  $image_url = null;
  
  // Handle file upload
  if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Invalid file type. Only JPG and PNG allowed']);
      exit;
    }
    
    if ($file['size'] > $maxSize) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'File too large. Maximum 5MB allowed']);
      exit;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('product_') . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
      $image_url = 'uploads/' . $filename;
    }
  }
  
  try {
    $stmt = $pdo->prepare('INSERT INTO products (name, category, price, stock, barcode, image_url, reorder_point) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $category, $price, $stock, $barcode, $image_url, $reorder_point]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
  } catch (Throwable $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    // Handle duplicate barcode
    if (property_exists($e, 'errorInfo') && is_array($e->errorInfo) && isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062 && stripos($msg, 'barcode') !== false) {
      echo json_encode(['ok' => false, 'error' => 'Barcode already exists. Please use a unique barcode.']);
    } else {
      echo json_encode(['ok' => false, 'error' => $msg]);
    }
  }
  exit;
}

if ($method === 'PUT') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Valid product ID required']); exit; }
  
  $name = trim($_POST['name'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $price = (float)($_POST['price'] ?? 0);
  $stock = (int)($_POST['stock'] ?? 0);
  $reorder_point = isset($_POST['reorder_point']) ? (int)$_POST['reorder_point'] : null;
  $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : null;
  if ($barcode === '') { $barcode = null; }
  
  try {
    // Check if product exists
    $checkStmt = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'Product not found']);
      exit;
    }
    
    $image_url = null;
    
    // Handle file upload if provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
      $uploadDir = __DIR__ . '/../uploads/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }
      
      $file = $_FILES['image'];
      $allowedTypes = ['image/jpeg', 'image/png'];
      $maxSize = 5 * 1024 * 1024; // 5MB
      
      if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid file type. Only JPG and PNG allowed']);
        exit;
      }
      
      if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'File too large. Maximum 5MB allowed']);
        exit;
      }
      
      $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = uniqid('product_') . '.' . $extension;
      $uploadPath = $uploadDir . $filename;
      
      if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $image_url = 'uploads/' . $filename;
      }
    }
    
    // Update product
    if ($image_url) {
      $stmt = $pdo->prepare('UPDATE products SET name = ?, category = ?, price = ?, stock = ?, image_url = ?' . ($reorder_point !== null ? ', reorder_point = ?' : '') . ', barcode = ? WHERE id = ?');
      $params = [$name, $category, $price, $stock, $image_url];
      if ($reorder_point !== null) { $params[] = $reorder_point; }
      $params[] = $barcode;
      $params[] = $id;
      $stmt->execute($params);
    } else {
      $stmt = $pdo->prepare('UPDATE products SET name = ?, category = ?, price = ?, stock = ?' . ($reorder_point !== null ? ', reorder_point = ?' : '') . ', barcode = ? WHERE id = ?');
      $params = [$name, $category, $price, $stock];
      if ($reorder_point !== null) { $params[] = $reorder_point; }
      $params[] = $barcode;
      $params[] = $id;
      $stmt->execute($params);
    }
    
    echo json_encode(['ok' => true, 'message' => 'Product updated successfully']);
  } catch (Throwable $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    if (property_exists($e, 'errorInfo') && is_array($e->errorInfo) && isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062 && stripos($msg, 'barcode') !== false) {
      echo json_encode(['ok' => false, 'error' => 'Barcode already exists. Please use a unique barcode.']);
    } else {
      echo json_encode(['ok' => false, 'error' => $msg]);
    }
  }
  exit;
}

if ($method === 'PATCH') {
  $id = (int)($_POST['id'] ?? 0);
  error_log("PATCH request received - ID: $id, POST data: " . print_r($_POST, true));
  error_log("Raw POST data: " . file_get_contents('php://input'));
  if ($id <= 0) { 
    http_response_code(400); 
    echo json_encode(['ok'=>false,'error'=>'Valid product ID required', 'received_id' => $_POST['id'] ?? 'null', 'post_data' => $_POST]); 
    exit; 
  }
  
  try {
    // Check if product exists
    $checkStmt = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'Product not found']);
      exit;
    }
    
    // Build dynamic UPDATE query based on provided fields
    $updates = [];
    $params = [];
    
    if (isset($_POST['name']) && $_POST['name'] !== '') {
      $updates[] = 'name = ?';
      $params[] = trim($_POST['name']);
    }
    if (isset($_POST['category']) && $_POST['category'] !== '') {
      $updates[] = 'category = ?';
      $params[] = trim($_POST['category']);
    }
    if (isset($_POST['price'])) {
      $updates[] = 'price = ?';
      $params[] = (float)$_POST['price'];
    }
    if (isset($_POST['stock'])) {
      $updates[] = 'stock = ?';
      $params[] = (int)$_POST['stock'];
    }
    if (isset($_POST['reorder_point'])) {
      $updates[] = 'reorder_point = ?';
      $params[] = (int)$_POST['reorder_point'];
    }
    if (isset($_POST['barcode'])) {
      $updates[] = 'barcode = ?';
      $params[] = (trim($_POST['barcode']) === '' ? null : trim($_POST['barcode']));
    }
    
    // Handle file upload if provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
      $uploadDir = __DIR__ . '/../uploads/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }
      
      $file = $_FILES['image'];
      $allowedTypes = ['image/jpeg', 'image/png'];
      $maxSize = 5 * 1024 * 1024; // 5MB
      
      if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid file type. Only JPG and PNG allowed']);
        exit;
      }
      
      if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'File too large. Maximum 5MB allowed']);
        exit;
      }
      
      $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = uniqid('product_') . '.' . $extension;
      $uploadPath = $uploadDir . $filename;
      
      if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $updates[] = 'image_url = ?';
        $params[] = 'uploads/' . $filename;
      }
    }
    
    if (empty($updates)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'No fields to update']);
      exit;
    }
    
    $params[] = $id; // Add ID for WHERE clause
    $sql = 'UPDATE products SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['ok' => true, 'message' => 'Product updated successfully']);
  } catch (Throwable $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    if (property_exists($e, 'errorInfo') && is_array($e->errorInfo) && isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062 && stripos($msg, 'barcode') !== false) {
      echo json_encode(['ok' => false, 'error' => 'Barcode already exists. Please use a unique barcode.']);
    } else {
      echo json_encode(['ok' => false, 'error' => $msg]);
    }
  }
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);


