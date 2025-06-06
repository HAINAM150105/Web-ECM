<?php
session_start();
require_once 'includes/db.php';

// Lấy các tham số tìm kiếm
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$min_price = isset($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? $_GET['max_price'] : '';
$size = isset($_GET['size']) ? $_GET['size'] : '';
$color = isset($_GET['color']) ? $_GET['color'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

try {
    // Xây dựng câu query
    $sql = "SELECT DISTINCT p.*, c.name as category_name, b.name as brand_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            WHERE p.status = 'active'";
    
    $params = [];
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($category) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category;
    }
    
    if ($brand) {
        $sql .= " AND p.brand_id = ?";
        $params[] = $brand;
    }
    
    if ($min_price) {
        $sql .= " AND p.price >= ?";
        $params[] = $min_price;
    }
    
    if ($max_price) {
        $sql .= " AND p.price <= ?";
        $params[] = $max_price;
    }
    
    if ($size) {
        $sql .= " AND pv.size = ?";
        $params[] = $size;
    }
    
    if ($color) {
        $sql .= " AND pv.color = ?";
        $params[] = $color;
    }
    
    // Sắp xếp
    switch ($sort) {
        case 'price_asc':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'name_asc':
            $sql .= " ORDER BY p.name ASC";
            break;
        case 'name_desc':
            $sql .= " ORDER BY p.name DESC";
            break;
        default:
            $sql .= " ORDER BY p.created_at DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Lấy danh sách danh mục và thương hiệu cho bộ lọc
    $categories = $pdo->query("SELECT * FROM categories WHERE status = 'active'")->fetchAll();
    $brands = $pdo->query("SELECT * FROM brands WHERE status = 'active'")->fetchAll();
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $error = "Có lỗi xảy ra, vui lòng thử lại sau.";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả tìm kiếm - Shop Thời Trang</title>
    <link rel="icon" href="/hocwebchoichoi/Clothes_Website/assets/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .product-card {
            transition: transform 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .product-img {
            height: 220px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        .add-to-cart-btn {
            width: 100%;
            margin-top: 10px;
        }
        .alert-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 250px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-4">
        <div class="row">
            <!-- Bộ lọc -->
            <div class="col-md-3">
                <div class="filter-section">
                    <h4>Bộ lọc</h4>
                    <form method="GET" action="">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Danh mục</label>
                            <select name="category" class="form-select">
                                <option value="">Tất cả</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Thương hiệu</label>
                            <select name="brand" class="form-select">
                                <option value="">Tất cả</option>
                                <?php foreach ($brands as $br): ?>
                                    <option value="<?php echo $br['id']; ?>" <?php echo $brand == $br['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($br['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Khoảng giá</label>
                            <div class="d-flex gap-2">
                                <input type="number" name="min_price" class="form-control" placeholder="Từ" value="<?php echo $min_price; ?>">
                                <input type="number" name="max_price" class="form-control" placeholder="Đến" value="<?php echo $max_price; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Size</label>
                            <select name="size" class="form-select">
                                <option value="">Tất cả</option>
                                <option value="S" <?php echo $size == 'S' ? 'selected' : ''; ?>>S</option>
                                <option value="M" <?php echo $size == 'M' ? 'selected' : ''; ?>>M</option>
                                <option value="L" <?php echo $size == 'L' ? 'selected' : ''; ?>>L</option>
                                <option value="XL" <?php echo $size == 'XL' ? 'selected' : ''; ?>>XL</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Màu sắc</label>
                            <select name="color" class="form-select">
                                <option value="">Tất cả</option>
                                <option value="Đỏ" <?php echo $color == 'Đỏ' ? 'selected' : ''; ?>>Đỏ</option>
                                <option value="Xanh" <?php echo $color == 'Xanh' ? 'selected' : ''; ?>>Xanh</option>
                                <option value="Đen" <?php echo $color == 'Đen' ? 'selected' : ''; ?>>Đen</option>
                                <option value="Trắng" <?php echo $color == 'Trắng' ? 'selected' : ''; ?>>Trắng</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sắp xếp</label>
                            <select name="sort" class="form-select">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                                <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Giá tăng dần</option>
                                <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Giá giảm dần</option>
                                <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Tên A-Z</option>
                                <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Tên Z-A</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Áp dụng</button>
                    </form>
                </div>
            </div>

            <!-- Danh sách sản phẩm -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Kết quả tìm kiếm</h2>
                    <div class="text-muted">
                        <?php echo count($products); ?> sản phẩm tìm thấy
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (empty($products)): ?>
                    <div class="alert alert-info">Không tìm thấy sản phẩm nào phù hợp với tiêu chí tìm kiếm.</div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-3 g-4">
                        <?php foreach ($products as $product): ?>
                            <div class="col">
                                <div class="card h-100 product-card">
                                    <?php
                                    $img = !empty($product['image']) && file_exists('assets/images/' . $product['image'])
                                        ? 'assets/images/' . htmlspecialchars($product['image'])
                                        : 'assets/images/product-default.jpg';
                                    ?>
                                    <img src="<?= $img ?>" class="card-img-top product-img" alt="<?= htmlspecialchars($product['name']) ?>">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                        <p class="card-text text-muted mb-1">
                                            <?= htmlspecialchars($product['category_name']) ?> - <?= htmlspecialchars($product['brand_name']) ?>
                                        </p>
                                        <p class="card-text text-primary fw-bold mb-2">
                                            <?= number_format($product['price']) ?>đ
                                        </p>
                                        <form class="add-to-cart-form mt-auto" data-product-id="<?= $product['id'] ?>">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <input type="number" name="quantity" value="1" min="1" class="form-control mb-2" style="width:80px;display:inline-block;">
                                            <button type="submit" class="btn btn-danger add-to-cart-btn w-100"><i class="bi bi-cart-plus"></i> Thêm vào giỏ</button>
                                        </form>
                                        <a href="product-detail.php?id=<?= $product['id'] ?>" class="btn btn-outline-primary w-100 mt-2">Xem chi tiết</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="alertBox" class="alert alert-success alert-fixed d-none"></div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('.add-to-cart-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const productId = form.querySelector('[name="product_id"]').value;
            const quantity = form.querySelector('[name="quantity"]').value;
            fetch('cart-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&product_id=${productId}&quantity=${quantity}`
            })
            .then(res => res.json())
            .then(data => {
                const alertBox = document.getElementById('alertBox');
                if (data.success) {
                    alertBox.textContent = 'Đã thêm vào giỏ hàng!';
                    alertBox.className = 'alert alert-success alert-fixed';
                    if (document.getElementById('cart-count')) {
                        document.getElementById('cart-count').textContent = data.cart_count;
                    }
                } else {
                    alertBox.textContent = data.message || 'Có lỗi xảy ra!';
                    alertBox.className = 'alert alert-danger alert-fixed';
                }
                alertBox.classList.remove('d-none');
                setTimeout(() => alertBox.classList.add('d-none'), 2000);
            });
        });
    });
    </script>
</body>
</html> 