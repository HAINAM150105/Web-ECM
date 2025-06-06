<?php
session_start();
require_once 'includes/db.php';

// Khởi tạo biến
$new_products = [];
$best_sellers = [];
$categories = [];
$error = null;

try {
    // Lấy sản phẩm mới nhất
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, b.name as brand_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $new_products = $stmt->fetchAll();

    // Lấy sản phẩm bán chạy
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, b.name as brand_name,
               COUNT(oi.id) as total_orders,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_variants pv ON p.id = pv.product_id
        LEFT JOIN order_items oi ON pv.id = oi.product_variant_id
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY total_orders DESC
        LIMIT 8
    ");
    $best_sellers = $stmt->fetchAll();

    // Lấy danh mục
    $stmt = $pdo->query("
        SELECT * FROM categories 
        WHERE status = 'active' 
        ORDER BY name ASC
    ");
    $categories = $stmt->fetchAll();

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
    <title>Trang Chủ - Shop Thời Trang</title>
    <link rel="icon" href="/hocwebchoichoi/Clothes_Website/assets/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            height: 500px;
            display: flex;
            align-items: center;
            color: white;
        }
        .product-card {
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .category-card {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
        }
        .category-card img {
            transition: transform 0.3s;
            height: 200px;
            object-fit: cover;
        }
        .category-card:hover img {
            transform: scale(1.1);
        }
        .category-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 15px;
        }
        .product-image {
            height: 200px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger m-3">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">Thời Trang Cho Mọi Người</h1>
            <p class="lead mb-4">Khám phá bộ sưu tập mới nhất với giá tốt nhất</p>
            <a href="products.php" class="btn btn-primary btn-lg">Mua sắm ngay</a>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">Danh Mục Sản Phẩm</h2>
            <div class="row row-cols-1 row-cols-md-4 g-4">
                <!-- Boy Category -->
                <div class="col">
                    <a href="category.php?id=1" class="text-decoration-none">
                        <div class="card category-card">
                            <img src="assets/images/boy.jpg" 
                                 class="card-img" 
                                 alt="Boy">
                            <div class="category-overlay">
                                <h5 class="card-title mb-0">Bé Trai</h5>
                            </div>
                        </div>
                    </a>
                </div>
                <!-- Girl Category -->
                <div class="col">
                    <a href="category.php?id=2" class="text-decoration-none">
                        <div class="card category-card">
                            <img src="assets/images/girl-category.jpg" 
                                 class="card-img" 
                                 alt="Girl">
                            <div class="category-overlay">
                                <h5 class="card-title mb-0">Bé Gái</h5>
                            </div>
                        </div>
                    </a>
                </div>
                <!-- Male Category -->
                <div class="col">
                    <a href="category.php?id=3" class="text-decoration-none">
                        <div class="card category-card">
                            <img src="assets/images/male-category.jpg" 
                                 class="card-img" 
                                 alt="Male">
                            <div class="category-overlay">
                                <h5 class="card-title mb-0">Nam</h5>
                            </div>
                        </div>
                    </a>
                </div>
                <!-- Female Category -->
                <div class="col">
                    <a href="category.php?id=4" class="text-decoration-none">
                        <div class="card category-card">
                            <img src="assets/images/female-category.jpg" 
                                 class="card-img" 
                                 alt="Female">
                            <div class="category-overlay">
                                <h5 class="card-title mb-0">Nữ</h5>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- New Products Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Sản Phẩm Mới</h2>
            <?php if (empty($new_products)): ?>
                <div class="alert alert-info">Chưa có sản phẩm nào.</div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-4 g-4">
                    <?php foreach ($new_products as $product): ?>
                        <div class="col">
                            <div class="card h-100 product-card">
                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'assets/images/product-default.jpg'); ?>" 
                                     class="card-img-top product-image" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Chưa phân loại'); ?> - 
                                        <?php echo htmlspecialchars($product['brand_name'] ?? 'Chưa có thương hiệu'); ?>
                                    </p>
                                    <p class="card-text text-primary fw-bold">
                                        <?php echo number_format($product['price']); ?>đ
                                    </p>
                                    <div class="d-flex gap-2">
                                        <a href="product-detail.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-primary flex-grow-1">
                                            Xem chi tiết
                                        </a>
                                        <form method="POST" action="cart.php" class="flex-grow-1">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="action" value="add">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-cart-plus"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-4">
                    <a href="products.php" class="btn btn-outline-primary">Xem tất cả sản phẩm</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Best Sellers Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">Sản Phẩm Bán Chạy</h2>
            <?php if (empty($best_sellers)): ?>
                <div class="alert alert-info">Chưa có sản phẩm bán chạy nào.</div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-4 g-4">
                    <?php foreach ($best_sellers as $product): ?>
                        <div class="col">
                            <div class="card h-100 product-card">
                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'assets/images/product-default.jpg'); ?>" 
                                     class="card-img-top product-image" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Chưa phân loại'); ?> - 
                                        <?php echo htmlspecialchars($product['brand_name'] ?? 'Chưa có thương hiệu'); ?>
                                    </p>
                                    <p class="card-text text-primary fw-bold">
                                        <?php echo number_format($product['price']); ?>đ
                                    </p>
                                    <div class="d-flex gap-2">
                                        <a href="product-detail.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-primary flex-grow-1">
                                            Xem chi tiết
                                        </a>
                                        <form method="POST" action="cart.php" class="flex-grow-1">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="action" value="add">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-cart-plus"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>