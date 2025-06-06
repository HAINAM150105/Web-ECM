<?php
session_start();
require_once 'includes/db.php';
require_once 'cart/functions.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';

// Get user ID from session (after login)
$user_id = $_SESSION['user_id'] ?? null;

// Handle form submission (add to cart)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!$user_id) {
        // Nếu chưa đăng nhập, chuyển hướng đến trang đăng nhập
        header("Location: auth/login.php");
        exit;
    }

    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $selected_variant_id = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
    
    if ($quantity <= 0) {
        $quantity = 1;
    }

    if ($selected_variant_id <= 0) {
        $message = 'Vui lòng chọn một biến thể sản phẩm.';
        $messageType = 'error';
    } else {
         // Kiểm tra tồn kho của biến thể đã chọn
         $stmt_stock = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ? LIMIT 1");
         $stmt_stock->execute([$selected_variant_id]);
         $available_stock = $stmt_stock->fetchColumn();

         if ($available_stock !== false && $quantity > $available_stock) {
              $message = 'Số lượng yêu cầu vượt quá tồn kho.';
              $messageType = 'error';
         } else {
             // Gọi hàm thêm vào giỏ hàng với variant_id
             if (addToCart($pdo, $user_id, $product_id, $selected_variant_id, $quantity)) {
                 $message = 'Sản phẩm đã được thêm vào giỏ hàng.';
                 $messageType = 'success';

                 // Cập nhật số lượng trên header (cần JS và hàm getCartSummary)
                 // Sau khi thêm vào giỏ, nên chuyển hướng hoặc cập nhật trang bằng AJAX
                 // Tạm thời chỉ hiển thị thông báo

             } else {
                 $message = 'Không thể thêm sản phẩm vào giỏ hàng.';
                 $messageType = 'error';
             }
         }
    }
}

// Fetch product details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

// Redirect to home if product not found
if (!$product) {
    header('Location: index.php');
    exit;
}

// Fetch product variants
$stmt_variants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY size, color");
$stmt_variants->execute([$product_id]);
$variants = $stmt_variants->fetchAll();

// Lấy ảnh sản phẩm
$stmt_img = $pdo->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id LIMIT 1");
$stmt_img->execute([$product_id]);
$main_image = $stmt_img->fetch()['image_path'] ?? null;


// Get related products from same category (need to join with products table)
$relatedStmt = $pdo->prepare("
    SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM products p
    WHERE p.category_id = ? AND p.id != ? 
    ORDER BY RAND() 
    LIMIT 4
");
$relatedStmt->execute([$product['category_id'], $product_id]);
$relatedProducts = $relatedStmt->fetchAll();

// Include header
include 'includes/header.php'; // Sử dụng header.php
?>

<div class="product-detail-container">
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo ($messageType === 'success' ? 'success' : 'danger'); ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="product-detail">
        <div class="product-images">
            <?php if (!empty($main_image) && file_exists($main_image)): // Kiểm tra sự tồn tại của tệp ảnh ?>
            <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="main-image">
            <?php else: ?>
            <?php
                // Placeholder image logic (cần lấy thông tin category name để tạo placeholder)
                // Tạm thời sử dụng placeholder chung
            ?>
            <img src="https://via.placeholder.com/500x600?text=No+Image" alt="<?php echo htmlspecialchars($product['name']); ?>" class="main-image">
            <?php endif; ?>
             <!-- TODO: Hiển thị thêm các ảnh phụ nếu có -->
        </div>
        
        <div class="product-info">
            <h1 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h1>
             <!-- TODO: Hiển thị danh mục và thương hiệu từ JOIN nếu cần -->
            <p class="product-category">Danh mục ID: <?php echo htmlspecialchars($product['category_id']); ?></p>
            <div class="product-price">
                <span class="current-price"><?php echo number_format($product['price'], 0, ',', '.'); ?> VNĐ</span>
            </div>
            
            <?php if (!empty($product['description'])): ?>
            <div class="product-description">
                <h3>Mô tả sản phẩm</h3>
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
            <?php endif; ?>
            
            <form method="post" class="add-to-cart-form">
                 <?php if (!empty($variants)): ?>
                     <div class="form-group mb-3">
                         <label for="variant">Chọn biến thể:</label>
                         <select name="variant_id" id="variant" class="form-select" required>
                             <option value="">-- Chọn Size và Màu sắc --</option>
                             <?php foreach ($variants as $variant): ?>
                                  <option value="<?php echo $variant['id']; ?>">
                                     Size: <?php echo htmlspecialchars($variant['size']); ?> - Màu: <?php echo htmlspecialchars($variant['color']); ?> (Kho: <?php echo $variant['stock']; ?>)
                                  </option>
                             <?php endforeach; ?>
                         </select>
                     </div>
                 <?php else: ?>
                      <div class="alert alert-warning">Sản phẩm này chưa có biến thể nào hoặc đã hết hàng.</div>
                 <?php endif; ?>

                <div class="form-group mb-3">
                    <label for="quantity">Số lượng:</label>
                    <input type="number" name="quantity" id="quantity" value="1" min="1" class="form-control" style="width: 100px;" required>
                     <!-- Max quantity based on selected variant's stock - requires JS -->
                </div>
                
                <div class="form-actions">
                     <?php if (!empty($variants)): // Chỉ hiển thị nút thêm nếu có biến thể ?>
                         <button type="submit" name="add_to_cart" class="btn btn-primary">
                             <i class="bi bi-cart-plus"></i> Thêm vào giỏ hàng
                         </button>
                     <?php else: ?>
                          <button type="button" class="btn btn-secondary" disabled>Hết hàng</button>
                     <?php endif; ?>
                     <!-- TODO: Add Wishlist button logic -->
                     <button type="button" class="btn btn-outline-secondary ms-2">
                         <i class="bi bi-heart"></i> Yêu thích
                     </button>
                </div>
            </form>
            
            <div class="product-meta mt-3">
                <p><strong>Mã sản phẩm:</strong> VPF-<?php echo $product['id']; ?></p>
                 <!-- TODO: Cập nhật thời gian thực tế -->
                <p><strong>Ngày tạo:</strong> <?php echo $product['created_at']; ?></p>
            </div>
        </div>
    </div>
    
    <?php if (!empty($relatedProducts)): ?>
    <div class="related-products mt-4">
        <h3>Sản phẩm liên quan</h3>
        <div class="row">
            <?php foreach ($relatedProducts as $relatedProduct): ?>
                <div class="col-md-3">
                    <div class="card mb-3">
                         <?php if (!empty($relatedProduct['image'])): ?>
                            <img src="<?php echo htmlspecialchars($relatedProduct['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/300x200?text=No+Image" class="card-img-top" alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>">
                        <?php endif; ?>
                         <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($relatedProduct['name']); ?></h5>
                            <p class="card-text"><?php echo number_format($relatedProduct['price'], 0, ',', '.'); ?> VNĐ</p>
                            <a href="product.php?id=<?php echo $relatedProduct['id']; ?>" class="btn btn-primary btn-sm">Xem chi tiết</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>


<?php include 'includes/footer.php'; // Bao gồm footer (nếu có) ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- TODO: Add JS for quantity control and variant stock update -->
</body>
</html>