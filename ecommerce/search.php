<?php
include 'db.php';
include 'includes/header.php';

$where = "WHERE 1";

if(isset($_GET['keyword']) && $_GET['keyword']!=""){
    $key = $_GET['keyword'];
    $where .= " AND name LIKE '%$key%'";
}

if(isset($_GET['category']) && $_GET['category']!=""){
    $cat = $_GET['category'];
    $where .= " AND category_id=$cat";
}
?>

<h2>Search Products</h2>

<form method="get">
    <input type="text" name="keyword" placeholder="Search product">

    <select name="category">
        <option value="">All Categories</option>
        <?php
        $cats = $conn->query("SELECT * FROM categories");
        while($c=$cats->fetch_assoc()){
            echo "<option value='{$c['id']}'>{$c['name']}</option>";
        }
        ?>
    </select>

    <button>Search</button>
</form>

<hr>

<?php
$res = $conn->query("SELECT * FROM products $where");
if($res->num_rows==0){
    echo "No products found";
}

while($p = $res->fetch_assoc()){
?>
<div class="product">
    <b><?php echo $p['name']; ?></b><br>
    ₹<?php echo $p['price']; ?><br>
    <a href="product.php?id=<?php echo $p['id']; ?>">View</a>
</div>
<?php } ?>

<?php include 'includes/footer.php'; ?>

