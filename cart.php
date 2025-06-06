<?php
session_start();
require_once 'includes/db.php';

// Kiểm tra đăng nhập người dùng
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_items = [];
$cart_total = 0;
$error = null;
$success = null;

// Xử lý cập nhật số lượng hoặc xóa sản phẩm khỏi giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $item_id = (int)($_POST['item_id'] ?? 0);

    if ($item_id > 0) {
        try {
            if ($action === 'update_quantity') {
                $quantity = (int)($_POST['quantity'] ?? 0);

                // Lấy thông tin mục giỏ hàng và tồn kho biến thể
                $stmt = $pdo->prepare("SELECT ci.*, pv.stock as variant_stock FROM cart_items ci JOIN product_variants pv ON ci.product_variant_id = pv.id WHERE ci.id = ? AND ci.user_id = ?");
                $stmt->execute([$item_id, $user_id]);
                $item = $stmt->fetch();

                if ($item) {
                    if ($quantity > 0 && $quantity <= $item['variant_stock']) {
                        // Cập nhật số lượng
                        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$quantity, $item_id, $user_id]);
                        $success = "Đã cập nhật số lượng sản phẩm.";
                    } elseif ($quantity > $item['variant_stock']) {
                         $error = "Số lượng yêu cầu vượt quá số lượng tồn kho ({$item['variant_stock']}).";
                    } else {
                        // Nếu số lượng là 0 hoặc âm, xóa sản phẩm
                         $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                         $stmt->execute([$item_id, $user_id]);
                         $success = "Đã xóa sản phẩm khỏi giỏ hàng.";
                    }
                } else {
                     $error = "Mục giỏ hàng không tồn tại hoặc không thuộc về bạn.";
                }

            } elseif ($action === 'remove_item') {
                // Xóa sản phẩm khỏi giỏ hàng
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                $stmt->execute([$item_id, $user_id]);
                 // Kiểm tra xem có dòng nào bị ảnh hưởng không
                 if ($stmt->rowCount() > 0) {
                     $success = "Đã xóa sản phẩm khỏi giỏ hàng.";
                 } else {
                     $error = "Mục giỏ hàng không tồn tại hoặc không thuộc về bạn.";
                 }
            }

        } catch (PDOException $e) {
            error_log("Cart Update Error: " . $e->getMessage());
            $error = "Có lỗi xảy ra khi cập nhật giỏ hàng: " . $e->getMessage();
        }
    } else {
         $error = "ID mục giỏ hàng không hợp lệ.";
    }

    // Chuyển hướng lại về trang giỏ hàng để tránh resubmission form
    header('Location: cart.php');
    exit;
}

// Lấy các sản phẩm trong giỏ hàng của người dùng
try {
    $stmt = $pdo->prepare("
        SELECT ci.*, pv.size, pv.color, pv.stock as variant_stock, p.name as product_name, p.price as product_price,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM cart_items ci
        JOIN product_variants pv ON ci.product_variant_id = pv.id
        JOIN products p ON pv.product_id = p.id
        WHERE ci.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll();

    // Tính tổng tiền giỏ hàng
    foreach ($cart_items as $item) {
        $cart_total += $item['quantity'] * $item['product_price'];
    }

} catch (PDOException $e) {
    error_log("Cart Error: " . $e->getMessage());
    $error = "Có lỗi xảy ra khi lấy dữ liệu giỏ hàng.";
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - Shop Thời Trang</title>
    <link rel="icon" href="/hocwebchoichoi/Clothes_Website/assets/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .cart-item-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
        }
        .table th, .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; // Bao gồm header ?>

    <div class="container my-4">
        <h2>Giỏ hàng của bạn</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="alert alert-info">Giỏ hàng của bạn đang trống. <a href="products.php">Tiếp tục mua sắm</a></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Giá</th>
                            <th>Số lượng</th>
                            <th>Tổng cộng</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" class="cart-item-img me-3" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                            <small class="text-muted">Size: <?php echo htmlspecialchars($item['size']); ?>, Màu: <?php echo htmlspecialchars($item['color']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo number_format($item['product_price'], 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <!-- Form/Input để cập nhật số lượng -->
                                    <form action="" method="POST" class="d-inline-block">
                                         <input type="hidden" name="action" value="update_quantity">
                                         <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                         <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['variant_stock']; // Giới hạn số lượng không vượt quá tồn kho ?>" class="form-control form-control-sm text-center" style="width: 70px;" onchange="this.form.submit()">
                                    </form>
                                </td>
                                <td><?php echo number_format($item['quantity'] * $item['product_price'], 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <!-- Form/Button để xóa sản phẩm -->
                                    <form action="" method="POST" class="d-inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?');">
                                         <input type="hidden" name="action" value="remove_item">
                                         <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                         <button type="submit" class="btn btn-danger btn-sm">
                                             <i class="bi bi-x-lg"></i>
                                         </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Tổng tiền giỏ hàng:</strong></td>
                            <td><strong><?php echo number_format($cart_total, 0, ',', '.'); ?> VNĐ</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="row">
                <div class="col-12 text-end">
                    <a href="products.php" class="btn btn-secondary me-2">Tiếp tục mua sắm</a>
                    <a href="checkout.php" class="btn btn-primary">Tiến hành thanh toán</a>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <?php include 'includes/footer.php'; // Bao gồm footer (nếu có) ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 