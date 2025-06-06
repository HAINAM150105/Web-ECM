<?php
session_start();
require_once 'includes/db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Xử lý thêm/xóa sản phẩm yêu thích
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            $_SESSION['success'] = "Đã thêm sản phẩm vào danh sách yêu thích";
        } elseif ($action === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            $_SESSION['success'] = "Đã xóa sản phẩm khỏi danh sách yêu thích";
        }
    } catch(PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['error'] = "Có lỗi xảy ra, vui lòng thử lại sau";
    }
    
    // Chuyển hướng về trang trước đó
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Lấy danh sách sản phẩm yêu thích
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, b.name as brand_name 
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log($e->getMessage());
    $error = "Có lỗi xảy ra, vui lòng thử lại sau";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách yêu thích</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .product-card {
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .wishlist-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            border-radius: 50%;
            padding: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-4">
        <h2 class="mb-4">Danh sách yêu thích</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($wishlist)): ?>
            <div class="alert alert-info">
                Bạn chưa có sản phẩm nào trong danh sách yêu thích.
                <a href="products.php" class="alert-link">Xem sản phẩm</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-4 g-4">
                <?php foreach ($wishlist as $product): ?>
                    <div class="col">
                        <div class="card h-100 product-card">
                            <div class="position-relative">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <form method="POST" action="" class="wishlist-icon">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="btn btn-link text-danger p-0">
                                        <i class="bi bi-heart-fill"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars($product['category_name']); ?> - 
                                    <?php echo htmlspecialchars($product['brand_name']); ?>
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

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 