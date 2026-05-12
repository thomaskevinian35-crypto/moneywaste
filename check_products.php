<?php
require_once 'includes/config.php';

echo "<h1>Current Products in Database</h1>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Name</th><th>Current Image URL</th><th>Local Image</th></tr>";

$products = $pdo->query("SELECT id, name, image_url FROM products")->fetchAll();

foreach ($products as $product) {
    echo "<tr>";
    echo "<td>{$product['id']}</td>";
    echo "<td>{$product['name']}</td>";
    echo "<td>" . substr($product['image_url'], 0, 50) . "...</td>";
    
    // Check if we have local images for these products
    $localImages = ['jacket1.jpg', 'tshirtblk.jpg', 'tshirtwht.jpg'];
    $hasLocal = '';
    
    foreach ($localImages as $img) {
        if (strpos(strtolower($product['name']), 'jacket') !== false && $img == 'jacket1.jpg') {
            $hasLocal = $img;
        } elseif (strpos(strtolower($product['name']), 'tee') !== false || strpos(strtolower($product['name']), 't-shirt') !== false) {
            if (strpos(strtolower($product['name']), 'black') !== false && $img == 'tshirtblk.jpg') {
                $hasLocal = $img;
            } elseif (strpos(strtolower($product['name']), 'white') !== false && $img == 'tshirtwht.jpg') {
                $hasLocal = $img;
            }
        }
    }
    
    echo "<td>" . ($hasLocal ? $hasLocal : 'No local image') . "</td>";
    echo "</tr>";
}

echo "</table>";
?>