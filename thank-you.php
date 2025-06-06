<?php
session_start();
require_once 'includes/db.php';

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? null;

// Redirect to login if not logged in
if (!$user_id) {
    header("Location: auth/login.php");
    exit;
}

// Get order ID from query parameter
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Fetch order details
$order = null;
$orderItems = [];
if ($order_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if ($order) {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $orderItems = $stmt->fetchAll();
    }
}

// Include header
include 'includes/header.html';
?>

<div class="thank-you-container">
    <div class="section-header">
        <h2>Cảm ơn bạn đã đặt hàng!</h2>
    </div>

    <?php if ($order): ?>
    <div class="order-confirmation">
        <p>Đơn hàng của bạn đã được đặt thành công. Chúng tôi sẽ liên hệ với bạn sớm để xác nhận.</p>
        <h3>Thông tin đơn hàng #<?= $order['id'] ?></h3>
        <p>Trạng thái: <span class="status"><?= htmlspecialchars($order['status']) ?></span></p>
        <p>Tổng tiền: <span class="total-price"><?= number_format($order['total'], 0, ',', '.') ?>đ</span></p>
        <h4>Chi tiết đơn hàng</h4>
        <table class="order-table">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Giá</th>
                    <th>Số lượng</th>
                    <th>Tổng</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td class="price"><?= number_format($item['price'], 0, ',', '.') ?>đ</td>
                    <td><?= $item['quantity'] ?></td>
                    <td class="total"><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="index.php" class="button">Quay lại trang chủ</a>
    </div>
    <?php else: ?>
    <div class="error">
        <p>Không tìm thấy đơn hàng. Vui lòng liên hệ hỗ trợ.</p>
        <a href="index.php" class="button">Quay lại trang chủ</a>
    </div>
    <?php endif; ?>
</div>

<style>
.thank-you-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 0 20px;
    text-align: center;
}

.order-confirmation p {
    margin-bottom: 15px;
}

.status {
    color: #4CAF50;
    font-weight: bold;
}

.total-price {
    font-weight: bold;
    color: #c62828;
    font-size: 1.2rem;
}

.order-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

.order-table th, .order-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.button {
    display: inline-block;
    padding: 10px 20px;
    background-color: #333;
    color: #fff;
    border-radius: 4px;
    text-decoration: none;
    margin-top: 20px;
}

.error {
    text-align: center;
    padding: 50px 0;
}

@media (max-width: 768px) {
    .order-table {
        font-size: 0.9rem;
    }
}
</style>

<?php include 'includes/footer.html'; ?>