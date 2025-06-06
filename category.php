<?php
session_start();
require_once 'includes/db.php';

// Lấy category_id từ URL
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Khởi tạo biến
$category = null;
$products = [];
$error = null;

try {
    // Lấy thông tin danh mục
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();

    if (!$category) {
        throw new Exception("Danh mục không tồn tại hoặc đã bị vô hiệu hóa.");
    }

    // Lấy danh sách sản phẩm trong danh mục
    $stmt = $pdo->prepare("
        SELECT p.*, 
               b.name as brand_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as image_path,
               MIN(pv.price) as min_price,
               MAX(pv.price) as max_price
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_variants pv ON p.id = pv.product_id
        WHERE p.category_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$category_id]);
    $products = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Lấy danh sách thương hiệu cho bộ lọc
$stmt = $pdo->prepare("
    SELECT DISTINCT b.* 
    FROM brands b
    JOIN products p ON b.id = p.brand_id
    WHERE p.category_id = ?
    ORDER BY b.name ASC
");
$stmt->execute([$category_id]);
$brands = $stmt->fetchAll();

// Lấy khoảng giá cho bộ lọc
$stmt = $pdo->prepare("
    SELECT MIN(pv.price) as min_price, MAX(pv.price) as max_price
    FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
    WHERE p.category_id = ?
");
$stmt->execute([$category_id]);
$price_range = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> - Shop Thời Trang</title>
    <link rel="icon" href="/hocwebchoichoi/Clothes_Website/assets/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .category-banner {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), 
                        url('<?php echo htmlspecialchars($category['image_path'] ?? 'assets/images/category-default.jpg'); ?>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            margin-bottom: 2rem;
        }
        .product-card {
            transition: transform 0.3s;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .price-range {
            width: 100%;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <?php if ($error): ?>
        <div class="container mt-4">
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Banner -->
        <div class="category-banner">
            <div class="container">
                <h1 class="display-4"><?php echo htmlspecialchars($category['name']); ?></h1>
                <p class="lead"><?php echo htmlspecialchars($category['description']); ?></p>
            </div>
        </div>

        <div class="container">
            <div class="row">
                <!-- Bộ lọc -->
                <div class="col-md-3">
                    <div class="filter-section">
                        <h5>Bộ lọc</h5>
                        
                        <!-- Lọc theo thương hiệu -->
                        <div class="mb-3">
                            <h6>Thương hiệu</h6>
                            <?php foreach ($brands as $brand): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="brands[]" value="<?php echo $brand['id']; ?>" 
                                           id="brand<?php echo $brand['id']; ?>">
                                    <label class="form-check-label" for="brand<?php echo $brand['id']; ?>">
                                        <?php echo htmlspecialchars($brand['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Lọc theo giá -->
                        <div class="mb-3">
                            <h6>Khoảng giá</h6>
                            <input type="range" class="price-range" 
                                   min="<?php echo $price_range['min_price']; ?>" 
                                   max="<?php echo $price_range['max_price']; ?>" 
                                   step="100000">
                            <div class="d-flex justify-content-between mt-2">
                                <span><?php echo number_format($price_range['min_price']); ?>đ</span>
                                <span><?php echo number_format($price_range['max_price']); ?>đ</span>
                            </div>
                        </div>

                        <!-- Lọc theo sắp xếp -->
                        <div class="mb-3">
                            <h6>Sắp xếp</h6>
                            <select class="form-select" name="sort">
                                <option value="newest">Mới nhất</option>
                                <option value="price_asc">Giá tăng dần</option>
                                <option value="price_desc">Giá giảm dần</option>
                                <option value="name_asc">Tên A-Z</option>
                                <option value="name_desc">Tên Z-A</option>
                            </select>
                        </div>

                        <button class="btn btn-primary w-100" id="applyFilter">
                            Áp dụng
                        </button>
                    </div>
                </div>

                <!-- Danh sách sản phẩm -->
                <div class="col-md-9">
                    <?php if (empty($products)): ?>
                        <div class="alert alert-info">
                            Không có sản phẩm nào trong danh mục này.
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-3 g-4">
                            <?php foreach ($products as $product): ?>
                                <div class="col">
                                    <div class="card product-card">
                                        <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'assets/images/product-default.jpg'); ?>" 
                                             class="card-img-top product-image" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                            <p class="card-text text-muted">
                                                <?php echo htmlspecialchars($product['brand_name'] ?? 'Thương hiệu khác'); ?>
                                            </p>
                                            <p class="card-text">
                                                <?php if ($product['min_price'] == $product['max_price']): ?>
                                                    <?php echo number_format($product['min_price']); ?>đ
                                                <?php else: ?>
                                                    <?php echo number_format($product['min_price']); ?>đ - 
                                                    <?php echo number_format($product['max_price']); ?>đ
                                                <?php endif; ?>
                                            </p>
                                            <a href="product.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-primary w-100">
                                                Xem chi tiết
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Xử lý bộ lọc
        document.getElementById('applyFilter').addEventListener('click', function() {
            const brands = Array.from(document.querySelectorAll('input[name="brands[]"]:checked'))
                .map(cb => cb.value);
            const priceRange = document.querySelector('.price-range').value;
            const sort = document.querySelector('select[name="sort"]').value;

            // Gửi request AJAX để lọc sản phẩm
            fetch(`ajax/filter_products.php?category_id=<?php echo $category_id; ?>`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    brands: brands,
                    price_range: priceRange,
                    sort: sort
                })
            })
            .then(response => response.json())
            .then(data => {
                // Cập nhật danh sách sản phẩm
                const productsContainer = document.querySelector('.row-cols-1');
                productsContainer.innerHTML = data.products.map(product => `
                    <div class="col">
                        <div class="card product-card">
                            <img src="${product.image_path || 'assets/images/product-default.jpg'}" 
                                 class="card-img-top product-image" 
                                 alt="${product.name}">
                            <div class="card-body">
                                <h5 class="card-title">${product.name}</h5>
                                <p class="card-text text-muted">${product.brand_name || 'Thương hiệu khác'}</p>
                                <p class="card-text">
                                    ${product.min_price === product.max_price 
                                        ? `${product.min_price.toLocaleString()}đ`
                                        : `${product.min_price.toLocaleString()}đ - ${product.max_price.toLocaleString()}đ`}
                                </p>
                                <a href="product.php?id=${product.id}" class="btn btn-primary w-100">
                                    Xem chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => console.error('Error:', error));
        });
    </script>
</body>
</html> 