<?php
echo "<h1>Checking Your Folder Structure</h1>";
echo "<p>Current location: " . __DIR__ . "</p>";

echo "<h2>Folders in current directory:</h2>";
$items = scandir(__DIR__);
echo "<ul>";
foreach($items as $item) {
    if($item != '.' && $item != '..') {
        $type = is_dir($item) ? "📁 FOLDER" : "📄 FILE";
        echo "<li>$type: $item</li>";
    }
}
echo "</ul>";

echo "<h2>Searching for images...</h2>";
// Search for image files anywhere
function searchImages($dir, $depth = 0) {
    if ($depth > 3) return;
    
    $items = scandir($dir);
    foreach($items as $item) {
        if($item != '.' && $item != '..') {
            $path = $dir . '/' . $item;
            if(is_dir($path)) {
                searchImages($path, $depth + 1);
            } elseif(preg_match('/\.(jpg|jpeg|png|gif)$/i', $item)) {
                echo "<p>Found image: $path (" . filesize($path) . " bytes)</p>";
                echo "<img src='$path' style='max-width:100px; margin:5px;'>";
            }
        }
    }
}

searchImages(__DIR__);
?>