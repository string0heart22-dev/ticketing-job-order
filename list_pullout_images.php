<?php
/**
 * List all files in the pullout_images directory
 */

echo "<h1>Pull Out Images Directory Listing</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: linear-gradient(80deg, #1f2937, #d60b0e); color: white; }
    tr:nth-child(even) { background: #f9fafb; }
    img { max-width: 100px; height: auto; border-radius: 4px; }
</style>";

$upload_dir = 'uploads/pullout_images/';

if (!file_exists($upload_dir)) {
    echo "<p class='error'>Directory does not exist: $upload_dir</p>";
    echo "<p><a href='check_pullout_uploads.php'>Run diagnostics</a></p>";
    exit;
}

$files = scandir($upload_dir);
$image_files = array_filter($files, function($file) use ($upload_dir) {
    return is_file($upload_dir . $file) && !in_array($file, ['.', '..']);
});

echo "<p class='success'>Found " . count($image_files) . " files in $upload_dir</p>";

if (empty($image_files)) {
    echo "<p>No files found in directory.</p>";
} else {
    echo "<table>";
    echo "<tr><th>Preview</th><th>Filename</th><th>Size</th><th>Modified</th><th>Full Path</th></tr>";
    
    foreach ($image_files as $file) {
        $full_path = $upload_dir . $file;
        $file_size = filesize($full_path);
        $file_time = filemtime($full_path);
        
        // Check if it's an image
        $is_image = @getimagesize($full_path);
        
        echo "<tr>";
        
        // Preview
        echo "<td>";
        if ($is_image) {
            echo "<img src='$full_path' alt='$file'>";
        } else {
            echo "<em>Not an image</em>";
        }
        echo "</td>";
        
        // Filename
        echo "<td><strong>$file</strong></td>";
        
        // Size
        echo "<td>" . number_format($file_size / 1024, 2) . " KB</td>";
        
        // Modified date
        echo "<td>" . date('Y-m-d H:i:s', $file_time) . "</td>";
        
        // Full path
        echo "<td><code>$full_path</code></td>";
        
        echo "</tr>";
    }
    
    echo "</table>";
}

// Check database records
require_once 'config.php';

$sql = "SELECT COUNT(*) as total FROM pullout_images";
$result = $conn->query($sql);
$db_count = $result->fetch_assoc()['total'];

echo "<h2>Database vs File System</h2>";
echo "<ul>";
echo "<li>Files in directory: <strong>" . count($image_files) . "</strong></li>";
echo "<li>Records in database: <strong>$db_count</strong></li>";
echo "</ul>";

if (count($image_files) != $db_count) {
    echo "<p class='error'>⚠ Mismatch detected! Some images may be orphaned or missing.</p>";
} else {
    echo "<p class='success'>✓ File count matches database records.</p>";
}

$conn->close();
?>

<p style="margin-top: 30px;">
    <a href="test_pullout_image_display.php">Test Image Display</a> | 
    <a href="fix_pullout_image_paths.php">Fix Image Paths</a> | 
    <a href="tickets_pullout.php">Back to Tickets</a>
</p>
