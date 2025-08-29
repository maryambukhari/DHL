<?php
ob_start();
include 'db.php';
session_start();
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
$category = trim($_GET['cat'] ?? '');
if (empty($category)) {
    error_log("No category specified", 3, 'errors.log');
    header("Location: index.php");
    exit;
}
try {
    $cat_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE name = ?");
    $cat_stmt->execute([$category]);
    $cat = $cat_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cat) {
        error_log("Category not found: " . $category, 3, 'errors.log');
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching category: " . $category . ": " . $e->getMessage(), 3, 'errors.log');
    echo "<p class='error'>Error fetching category: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cat['name']); ?> - News Website</title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background: linear-gradient(135deg, #f5f7fa, #c3cfe2); color: #333; line-height: 1.6; }
        header { background: linear-gradient(to right, #cc0000, #ff3333); color: white; padding: 30px; text-align: center; box-shadow: 0 6px 12px rgba(0,0,0,0.3); position: sticky; top: 0; z-index: 1000; }
        header h1 { margin: 0; font-size: 3em; text-transform: uppercase; letter-spacing: 3px; animation: glow 2s ease-in-out infinite alternate; }
        nav { background: #1a1a1a; padding: 15px; text-align: center; box-shadow: 0 3px 6px rgba(0,0,0,0.2); }
        nav a { color: white; margin: 0 25px; text-decoration: none; font-weight: bold; font-size: 1.1em; transition: color 0.3s ease, transform 0.3s ease; }
        nav a:hover { color: #ff3333; transform: scale(1.15); }
        .featured { max-width: 1200px; margin: 30px auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; padding: 20px; }
        .article-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 6px 12px rgba(0,0,0,0.15); transition: transform 0.4s ease, box-shadow 0.4s ease; }
        .article-card:hover { transform: translateY(-10px) scale(1.03); box-shadow: 0 12px 24px rgba(0,0,0,0.3); }
        .article-card img { width: 100%; height: 200px; object-fit: cover; transition: transform 0.4s ease; }
        .article-card:hover img { transform: scale(1.05); }
        .article-card h2 { padding: 15px; margin: 0; color: #cc0000; font-size: 1.3em; line-height: 1.4; }
        .article-card p { padding: 0 15px 15px; color: #666; font-size: 0.95em; line-height: 1.5; }
        .article-card a { display: block; padding: 12px; background: linear-gradient(to right, #cc0000, #ff3333); color: white; text-align: center; text-decoration: none; font-weight: bold; transition: background 0.3s ease; }
        .article-card a:hover { background: linear-gradient(to right, #a00000, #cc0000); }
        .error { color: #cc0000; text-align: center; margin: 20px; font-weight: bold; }
        #search-form { position: absolute; right: 30px; top: 25px; }
        #search-form input { padding: 12px; border: none; border-radius: 8px 0 0 8px; font-size: 1em; }
        #search-form button { padding: 12px; background: #1a1a1a; color: white; border: none; border-radius: 0 8px 8px 0; cursor: pointer; transition: background 0.3s ease; }
        #search-form button:hover { background: #ff3333; }
        @keyframes glow { from { text-shadow: 0 0 5px #fff, 0 0 10px #ff3333; } to { text-shadow: 0 0 10px #fff, 0 0 20px #cc0000; } }
        @media (max-width: 768px) { .featured { grid-template-columns: 1fr; } nav a { display: block; margin: 15px 0; } #search-form { position: static; text-align: center; margin: 15px 0; } #search-form input, #search-form button { width: 100%; border-radius: 8px; } }
    </style>
</head>
<body>
    <header>
        <h1>News Website</h1>
        <form id="search-form" action="search.php" method="get">
            <input type="text" name="q" placeholder="Search news..." required>
            <button type="submit">Search</button>
        </form>
    </header>
    <nav>
        <a href="index.php">Home</a>
        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM categories");
            if ($stmt->rowCount() === 0) {
                echo '<p class="error">No categories available.</p>';
            } else {
                while ($cat_nav = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<a href="category.php?cat=' . urlencode($cat_nav['name']) . '">' . htmlspecialchars($cat_nav['name']) . '</a>';
                }
            }
        } catch (PDOException $e) {
            echo '<p class="error'>Error loading categories: ' . htmlspecialchars($e->getMessage()) . '</p>';
            error_log("Error loading categories: " . $e->getMessage(), 3, 'errors.log');
        }
        ?>
    </nav>
    <section class="featured">
        <h2><?php echo htmlspecialchars($cat['name']); ?> News</h2>
        <?php
        try {
            $stmt = $pdo->prepare("SELECT a.*, c.name AS cat_name FROM articles a JOIN categories c ON a.category_id = c.id WHERE c.name = ? AND a.title IS NOT NULL AND a.image_url IS NOT NULL AND a.content IS NOT NULL ORDER BY a.publish_date DESC");
            $stmt->execute([$category]);
            if ($stmt->rowCount() === 0) {
                echo '<p class="error">No articles available in this category.</p>';
            } else {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<div class="article-card">
                        <img src="' . htmlspecialchars($row['image_url']) . '" alt="' . htmlspecialchars($row['title']) . '">
                        <h2>' . htmlspecialchars($row['title']) . '</h2>
                        <p>By ' . htmlspecialchars($row['author']) . ' | ' . htmlspecialchars($row['publish_date']) . '</p>
                        <a href="article.php?id=' . $row['id'] . '">Read More</a>
                    </div>';
                }
            }
        } catch (PDOException $e) {
            echo '<p class="error">Error loading articles: ' . htmlspecialchars($e->getMessage()) . '</p>';
            error_log("Error loading articles for category: " . $category . ": " . $e->getMessage(), 3, 'errors.log');
        }
        ?>
    </section>
    <script>
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.body.style.transition = 'opacity 0.5s';
                document.body.style.opacity = 0;
                setTimeout(() => { location.href = this.href; }, 500);
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
