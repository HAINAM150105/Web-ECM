<?php
session_start();
require_once 'includes/db.php';

// Lấy sản phẩm thuộc danh mục "Đồ Bé Trai"
$category = 'Đồ Bé Trai';
$query = "SELECT * FROM products WHERE category = :category ORDER BY name";
$stmt = $pdo->prepare($query);
$stmt->execute(['category' => $category]);
$products = $stmt->fetchAll();


// Include phần đầu trang
include("includes/header.html");


?>

<div class="product-section">
  <h2 style="text-align: center; margin: 20px 0;">Sản phẩm - <?php echo htmlspecialchars($category); ?></h2>
  <div class="product-grid">
    <?php if (!empty($products)): ?>
      <?php foreach ($products as $product): ?>
        <div class="product-card">
          <img src="assets/images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
          <h3><?php echo htmlspecialchars($product['name']); ?></h3>
          <p><?php echo number_format($product['price'], 0, ',', '.'); ?> VNĐ</p>
          <a href="product.php?id=<?php echo $product['id']; ?>" class="btn">Xem Chi Tiết</a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="text-align:center;">Hiện chưa có sản phẩm nào trong danh mục này.</p>
    <?php endif; ?>
  </div>
</div>

<style>
  .product-section {
    padding: 20px;
  }
  .product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
  }
  .product-card {
    border: 1px solid #ccc;
    padding: 10px;
    text-align: center;
  }
  .product-card img {
    max-width: 100%;
    height: 180px;
    object-fit: cover;
  }
  .btn {
    display: inline-block;
    margin-top: 10px;
    background-color: #c62828;
    color: #fff;
    padding: 5px 10px;
    text-decoration: none;
  }
</style>

<?php include("includes/footer.html"); ?>
