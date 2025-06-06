<?php
session_start();
require_once 'includes/db.php';
require_once 'cart/functions.php';

$message = '';
$messageType = '';

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'guest';

// Redirect to login if not logged in
if (!$user_id) {
    header("Location: auth/login.php");
    exit;
}

// Get cart items and summary
$cartItems = getCartItems($pdo, $user_id);
$cartSummary = getCartSummary($pdo, $user_id);

// Redirect to cart if cart is empty
if (empty($cartItems)) {
    header("Location: cart/index.php");
    exit;
}

// Danh sách phương thức thanh toán
$payment_methods = [
    'cod' => 'Thanh toán khi nhận hàng (COD)',
    'bank' => 'Chuyển khoản ngân hàng'
];

$selected_payment = $_POST['payment_method'] ?? 'cod';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';

    // Simple validation
    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($payment_method)) {
        $message = 'Vui lòng điền đầy đủ thông tin giao hàng và chọn phương thức thanh toán.';
        $messageType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Email không hợp lệ.';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            // Create order
            $stmt = $pdo->prepare(
                "INSERT INTO orders (user_id, total, status, created_at, payment_method, shipping_name, shipping_email, shipping_phone, shipping_address)
                 VALUES (?, ?, 'pending', NOW(), ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $cartSummary['total'], $payment_method, $name, $email, $phone, $address]);
            $order_id = $pdo->lastInsertId();

            // Add order items
            $stmt_item = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, product_variant_id, quantity, price)
                 VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($cartItems as $item) {
                $stmt_item->execute([$order_id, $item['product_id'], $item['product_variant_id'], $item['quantity'], $item['price']]);

                // TODO: Update stock quantity in product_variants table
            }

            // Clear cart
            clearCart($pdo, $user_id);

            $pdo->commit();

            // Redirect to a thank you page (you need to create this page)
            header("Location: thank-you.php?order_id=$order_id");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            // Log error for debugging
            error_log("Checkout error: " . $e->getMessage());
            $message = 'Đã xảy ra lỗi trong quá trình đặt hàng. Vui lòng thử lại sau.';
            $messageType = 'danger';
        }
    }
}

// Include header
include 'includes/header.html';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonnie Fashion - Thanh toán</title>
    <link rel="icon" href="/hocwebchoichoi/Clothes_Website/assets/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="checkout-container">
        <div class="section-header">
            <h2>Thanh toán</h2>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo ($messageType === 'success' ? 'success' : 'danger'); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="checkout-content">
            <div class="order-summary">
                <h3>Tổng đơn hàng</h3>
                <div class="summary-row">
                    <span>Số lượng sản phẩm:</span>
                    <span><?php echo $cartSummary['count']; ?></span>
                </div>
                <div class="summary-row">
                    <span>Tổng tiền:</span>
                    <span class="total-price"><?php echo number_format($cartSummary['total'], 0, ',', '.'); ?>đ</span>
                </div>
            </div>
            
            <form method="post" class="checkout-form">
                <div class="form-group">
                    <label for="name">Họ và tên:</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Số điện thoại:</label>
                    <input type="tel" name="phone" id="phone" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="address">Địa chỉ:</label>
                    <textarea name="address" id="address" class="form-control" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Phương thức thanh toán:</label>
                    <select name="payment_method" id="payment_method" class="form-control" required>
                        <option value="">-- Chọn phương thức thanh toán --</option>
                        <option value="cod">Thanh toán khi nhận hàng (COD)</option>
                        <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                        <option value="credit_card">Thẻ tín dụng</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="checkout-button">Thanh toán</button>
                    <a href="cart/index.php" class="button">Quay lại giỏ hàng</a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.html'; ?>
</body>
</html>