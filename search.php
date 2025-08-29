<?php include 'db.php'; 
$query = $_GET['q'] ?? '';
if (empty($query)) {
    echo '<script>location.href = "index.php";</script>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - News Website</title>
    <style>
        /* Same amazing CSS for consistency */
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(to bottom, #f4f4f4, #ffffff); 
            color: #333; 
            line-height: 1.6; 
        }
        header { 
            background: #cc0000; 
            color: white; 
            padding: 20px; 
            text-align: center; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
        }
        header h1 { 
            margin: 0; 
            font-size: 2.5em; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
        }
        nav { 
            background: #000000; 
            color: white; 
            padding: 15px; 
            text-align: center; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        nav a { 
            color: white; 
            margin: 0 20px; 
            text-decoration: none; 
            font-weight: bold; 
            transition: color 0.3s ease, transform 0.3s ease; 
        }
        nav a:hover { 
            color: #cc0000; 
            transform: scale(1.1); 
        }
        .search-results { 
            max-width: 1200px; 
            margin: 20px auto; 
            padding: 20px; 
        }
        .search-results h2 { 
            color: #cc0000; 
            border-bottom: 2px solid #cc0000; 
            padding-bottom: 10px; 
        }
        .featured { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 20px; 
        }
        .article-card { 
            background: white; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
            border-radius: 10px; 
            overflow: hidden; 
            transition: transform 0.4s ease, box-shadow 0.4s ease; 
            animation: fadeIn 0.8s ease-out; 
        }
        .article-card:hover { 
            transform: translateY(-10px) scale(1.02); 
            box-shadow: 0 12px 24px rgba(0,0,0,0.2); 
        }
        .article-card img { 
            width: 100%; 
            height: 200px; 
            object-fit: cover; 
            transition: transform 0.4s ease; 
        }
        .article-card:hover img { 
            transform: scale(1.1); 
        }
        .article-card h3 { 
            padding: 15px; 
            margin: 0; 
            color: #cc0000; 
            font-size: 1.2em; 
        }
        .article-card p { 
            padding: 0 15px 15px; 
            color: #666; 
            font-size: 0.9em; 
        }
        .article-card a { 
            display: block; 
            padding: 10px; 
            background: #cc0000; 
            color: white; 
            text-align: center; 
            text-decoration: none; 
            transition: background 0.3s; 
        }
        .article-card a:hover { 
            background: #a00000; 
        }
        #search-form { 
            position: absolute; 
            right: 20px; 
            top: 20px; 
        }
        #search-form input { 
            padding: 10px; 
            border: none; 
            border-radius: 5px 0 0 5px; 
        }
        #search-form button { 
            padding: 10px; 
            background: #000; 
            color: white; 
            border: none; 
            border-radius: 0 5px 5px 0; 
            cursor: pointer; 
            transition: background 0.3s; 
        }
        #search-form button:hover { 
            background: #cc0000; 
        }
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(20px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        @media (max-width: 768px) { 
            .featured { grid-template-columns: 1fr; } 
            nav a { display: block; margin: 10px 0; } 
            #search-form { position: static; text-align: center; margin: 10px 0; } 
        }
    </style>
</head>
<body>
    <header>
        <h1>News Website</h1>
        <form id="search-form" action="search.php" method="get">
            <input type="text" name="q" placeholder="Search news..." value="<?php echo htmlspecialchars($query); ?>" required>
            <button type="submit">Search</button>
        </form>
    </header>
    <nav>
        <a href="index.php">Home</a>
        <?php
        $stmt = $pdo->query("SELECT * FROM categories");
        while ($cat = $stmt->fetch()) {
            echo '<a href="category.php?cat=' . urlencode($cat['name']) . '">' . htmlspecialchars($cat['name']) . '</a>';
        }
        ?>
    </nav>
    <section class="search-results">
        <h2>Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>
        <div class="featured">
        <?php
        $search_stmt = $pdo->prepare("SELECT * FROM articles WHERE title LIKE ? OR content LIKE ? ORDER BY publish_date DESC");
        $search_stmt->execute(['%' . $query . '%', '%' . $query . '%']);
        if ($search_stmt->rowCount() === 0) {
            echo '<p>No results found for your search.</p>';
        }
        while ($row = $search_stmt->fetch()) {
            echo '<div class="article-card">
                <img src="' . htmlspecialchars($row['image_url']) . '" alt="' . htmlspecialchars($row['title']) . '">
                <h3>' . htmlspecialchars($row['title']) . '</h3>
                <p>' . htmlspecialchars(substr($row['content'], 0, 100)) . '...</p>
                <a href="article.php?id=' . $row['id'] . '">Read More</a>
            </div>';
        }
        ?>
        </div>
    </section>
    <script>
        // JS for smooth redirection
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.body.style.transition = 'opacity 0.5s';
                document.body.style.opacity = 0;
                setTimeout(() => {
                    location.href = this.href;
                }, 500);
            });
        });
    </script>
</body>
</html>
