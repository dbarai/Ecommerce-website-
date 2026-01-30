<?php
// admin/categories.php
require_once '../includes/auth_check.php';

// Only admin can access
if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Handle actions
$action = $_GET['action'] ?? '';
$categoryId = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Delete category
if ($action === 'delete' && $categoryId) {
    // Check if category has products
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $checkStmt->execute([$categoryId]);
    $productCount = $checkStmt->fetch()['count'];
    
    if ($productCount > 0) {
        $error = "Cannot delete category with existing products!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$categoryId])) {
            $message = "Category deleted successfully!";
        } else {
            $error = "Error deleting category!";
        }
    }
}

// Handle status change
if ($action === 'status' && $categoryId) {
    $status = $_GET['status'] ?? '';
    if (in_array($status, ['active', 'inactive'])) {
        $stmt = $pdo->prepare("UPDATE categories SET status = ? WHERE id = ?");
        $stmt->execute([$status, $categoryId]);
        $message = "Category status updated!";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $description = sanitize($_POST['description']);
    $status = sanitize($_POST['status']);
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $sortOrder = intval($_POST['sort_order']);
    
    $slug = generateSlug($name);
    
    if ($categoryId > 0) {
        // Update category
        $stmt = $pdo->prepare("
            UPDATE categories SET 
                name = ?, slug = ?, parent_id = ?, description = ?, 
                status = ?, is_featured = ?, sort_order = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $slug, $parentId, $description, 
            $status, $isFeatured, $sortOrder, $categoryId
        ]);
        $message = "Category updated successfully!";
    } else {
        // Add new category
        $stmt = $pdo->prepare("
            INSERT INTO categories (name, slug, parent_id, description, status, is_featured, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $slug, $parentId, $description, 
            $status, $isFeatured, $sortOrder
        ]);
        $message = "Category added successfully!";
        header("Location: categories.php");
        exit();
    }
}

// Fetch all categories
$categoriesStmt = $pdo->query("
    SELECT c1.*, c2.name as parent_name,
           (SELECT COUNT(*) FROM products WHERE category_id = c1.id) as product_count
    FROM categories c1
    LEFT JOIN categories c2 ON c1.parent_id = c2.id
    ORDER BY c1.parent_id, c1.sort_order, c1.name
");
$categories = $categoriesStmt->fetchAll();

// Fetch parent categories for dropdown
$parentCategories = $pdo->query("
    SELECT id, name FROM categories 
    WHERE parent_id IS NULL 
    ORDER BY name
")->fetchAll();

// Fetch category for editing
$editCategory = null;
if ($action === 'edit' && $categoryId) {
    $editStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $editStmt->execute([$categoryId]);
    $editCategory = $editStmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #2c3e50 0%, #1a1a2e 100%);
            color: white;
            padding: 0;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left-color: #dc3545;
        }
        
        .sidebar-menu .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .category-tree {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tree-item {
            padding: 10px 15px;
            border-left: 3px solid #dee2e6;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        
        .tree-item:hover {
            background: #f8f9fa;
            border-left-color: #0d6efd;
        }
        
        .tree-item .actions {
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tree-item:hover .actions {
            opacity: 1;
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .child-categories {
            margin-left: 30px;
            border-left: 2px dashed #dee2e6;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-0"><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        </div>
        <div class="sidebar-menu">
            <?php include 'admin-sidebar.php'; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1">Manage Categories</h2>
                    <p class="text-muted mb-0">Organize your products into categories</p>
                </div>
                <div>
                    <a href="categories.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Category
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if($action === 'add' || $action === 'edit'): ?>
        <!-- Category Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4">
                    <?php echo $action === 'add' ? 'Add New Category' : 'Edit Category'; ?>
                </h4>
                
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Category Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Parent Category</label>
                            <select name="parent_id" class="form-select">
                                <option value="">None (Main Category)</option>
                                <?php foreach($parentCategories as $cat): 
                                    if ($action === 'edit' && $cat['id'] == $categoryId) continue;
                                ?>
                                <option value="<?php echo $cat['id']; ?>"
                                        <?php echo ($editCategory['parent_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo ($editCategory['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($editCategory['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0"
                                   value="<?php echo $editCategory['sort_order'] ?? '0'; ?>">
                            <small class="text-muted">Lower numbers appear first</small>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="is_featured" class="form-check-input" id="is_featured"
                                       <?php echo ($editCategory['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_featured">Featured Category</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $action === 'add' ? 'Add Category' : 'Update Category'; ?>
                        </button>
                        <a href="categories.php" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Categories Tree -->
        <div class="category-tree">
            <h5 class="fw-bold mb-4">Categories Hierarchy</h5>
            
            <?php if(empty($categories)): ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <h5>No categories found</h5>
                <p class="text-muted">Start by adding your first category</p>
                <a href="categories.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Category
                </a>
            </div>
            <?php else: ?>
                <?php
                // Organize categories by parent
                $categoryTree = [];
                foreach ($categories as $category) {
                    $parentId = $category['parent_id'] ?? 0;
                    if (!isset($categoryTree[$parentId])) {
                        $categoryTree[$parentId] = [];
                    }
                    $categoryTree[$parentId][] = $category;
                }
                
                // Recursive function to display categories
                function displayCategories($parentId, $categoryTree, $level = 0) {
                    if (!isset($categoryTree[$parentId])) return '';
                    
                    $html = '';
                    foreach ($categoryTree[$parentId] as $category) {
                        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                        $hasChildren = isset($categoryTree[$category['id']]);
                        
                        $html .= '<div class="tree-item">';
                        $html .= '<div class="d-flex justify-content-between align-items-center">';
                        $html .= '<div>';
                        $html .= $level > 0 ? '<i class="fas fa-arrow-right text-muted me-2"></i>' : '';
                        $html .= '<strong>' . htmlspecialchars($category['name']) . '</strong>';
                        $html .= '<div class="small text-muted mt-1">';
                        $html .= 'Products: <span class="badge bg-secondary">' . $category['product_count'] . '</span>';
                        if ($category['parent_name']) {
                            $html .= ' | Parent: ' . htmlspecialchars($category['parent_name']);
                        }
                        if ($category['is_featured']) {
                            $html .= ' | <span class="badge bg-warning">Featured</span>';
                        }
                        $html .= '</div>';
                        $html .= '</div>';
                        
                        $html .= '<div class="actions">';
                        $html .= '<span class="badge-status bg-' . ($category['status'] == 'active' ? 'success' : 'secondary') . ' me-2">';
                        $html .= ucfirst($category['status']);
                        $html .= '</span>';
                        
                        $html .= '<div class="btn-group btn-group-sm">';
                        $html .= '<a href="categories.php?action=edit&id=' . $category['id'] . '" class="btn btn-outline-primary" title="Edit">';
                        $html .= '<i class="fas fa-edit"></i>';
                        $html .= '</a>';
                        if ($category['product_count'] == 0) {
                            $html .= '<a href="categories.php?action=delete&id=' . $category['id'] . '" class="btn btn-outline-danger" title="Delete" onclick="return confirm(\'Delete this category?\')">';
                            $html .= '<i class="fas fa-trash"></i>';
                            $html .= '</a>';
                        }
                        $html .= '</div>';
                        $html .= '</div>';
                        $html .= '</div>';
                        
                        // Display child categories
                        if ($hasChildren) {
                            $html .= '<div class="child-categories mt-2">';
                            $html .= displayCategories($category['id'], $categoryTree, $level + 1);
                            $html .= '</div>';
                        }
                        
                        $html .= '</div>';
                    }
                    return $html;
                }
                
                echo displayCategories(null, $categoryTree);
                ?>
            <?php endif; ?>
        </div>
        
        <!-- Statistics -->
        <div class="row mt-4 g-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h1 class="text-primary fw-bold">
                            <?php echo count($categories); ?>
                        </h1>
                        <h6 class="text-muted">Total Categories</h6>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <?php
                        $mainCategories = array_filter($categories, function($cat) {
                            return $cat['parent_id'] === null;
                        });
                        ?>
                        <h1 class="text-success fw-bold">
                            <?php echo count($mainCategories); ?>
                        </h1>
                        <h6 class="text-muted">Main Categories</h6>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <?php
                        $featuredCategories = array_filter($categories, function($cat) {
                            return $cat['is_featured'] == 1;
                        });
                        ?>
                        <h1 class="text-warning fw-bold">
                            <?php echo count($featuredCategories); ?>
                        </h1>
                        <h6 class="text-muted">Featured Categories</h6>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <?php
                        $totalProducts = array_sum(array_column($categories, 'product_count'));
                        ?>
                        <h1 class="text-info fw-bold">
                            <?php echo number_format($totalProducts); ?>
                        </h1>
                        <h6 class="text-muted">Total Products</h6>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
