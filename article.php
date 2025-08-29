<?php
ob_start();
include 'db.php';
session_start();
ini_set('display_errors', 1); // Enable error display for debugging
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); // Report all errors

// Handle 500 error scenario with custom error page
function displayErrorPage($message) {
    header('HTTP/1.1 500 Internal Server Error');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>500 Internal Server Error - News Website</title>
        <style>
            body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background: linear-gradient(135deg, #f5f7fa, #c3cfe2); color: #333; line-height: 1.6; display: flex; justify-content: center; align-items: center; height: 100vh; }
            .error-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.15); text-align: center; max-width: 600px; }
            .error-container h1 { color: #cc0000; font-size: 2.5em; margin-bottom: 20px; }
            .error-container p { color: #666; font-size: 1.2em; margin-bottom: 30px; }
            .error-container a { padding: 12px 25px; background: linear-gradient(to right, #cc0000, #ff3333); color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: background 0.3s ease; }
            .error-container a:hover { background: linear-gradient(to right, #a00000, #cc0000); }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>500 Internal Server Error</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="index.php">Return to Home</a>
        </div>
    </body>
    </html>
    <?php
    ob_end_flush();
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    error_log("Invalid article ID: " . $id, 3, 'errors.log');
    displayErrorPage("Invalid article ID. Please try again or return to the homepage.");
}

try {
    $update_stmt = $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
    $update_stmt->execute([$id]);
} catch (PDOException $e) {
    error_log("Error updating views for article_id: " . $id . ": " . $e->getMessage(), 3, 'errors.log');
    displayErrorPage("Error updating article views. Please contact support.");
}

try {
    $article_stmt = $pdo->prepare("SELECT a.*, c.name AS cat_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.id = ?");
    $article_stmt->execute([$id]);
    $article = $article_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$article || empty($article['title']) || empty($article['image_url']) || empty($article['content'])) {
        error_log("Article not found or incomplete for id: " . $id . ", Data: " . json_encode($article), 3, 'errors.log');
        displayErrorPage("Article not found or incomplete. Please return to the homepage.");
    }
} catch (PDOException $e) {
    error_log("Error fetching article id: " . $id . ": " . $e->getMessage(), 3, 'errors.log');
    displayErrorPage("Error fetching article. Please contact support.");
}

if (!isset($_SESSION['comment_token'])) {
    $_SESSION['comment_token'] = bin2hex(random_bytes(32));
}
$comment_token = $_SESSION['comment_token'];
$image_column_exists = true;
try {
    $pdo->query("SELECT image_url FROM comments LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $image_column_exists = false;
    } else {
        error_log("Error checking comments table: " . $e->getMessage(), 3, 'errors.log');
        displayErrorPage("Error checking comments table. Please contact support.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - News Website</title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background: linear-gradient(135deg, #f5f7fa, #c3cfe2); color: #333; line-height: 1.6; }
        header { background: linear-gradient(to right, #cc0000, #ff3333); color: white; padding: 30px; text-align: center; box-shadow: 0 6px 12px rgba(0,0,0,0.3); position: sticky; top: 0; z-index: 1000; }
        header h1 { margin: 0; font-size: 3em; text-transform: uppercase; letter-spacing: 3px; animation: glow 2s ease-in-out infinite alternate; }
        nav { background: #1a1a1a; padding: 15px; text-align: center; box-shadow: 0 3px 6px rgba(0,0,0,0.2); }
        nav a { color: white; margin: 0 25px; text-decoration: none; font-weight: bold; font-size: 1.1em; transition: color 0.3s ease, transform 0.3s ease; }
        nav a:hover { color: #ff3333; transform: scale(1.15); }
        .article { max-width: 800px; margin: 30px auto; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.15); animation: fadeIn 0.8s ease-out; }
        .article h1 { color: #cc0000; font-size: 2.2em; margin-bottom: 15px; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .article p.meta { color: #666; font-style: italic; font-size: 0.95em; }
        .article img { width: 100%; border-radius: 12px; margin: 20px 0; box-shadow: 0 4px 8px rgba(0,0,0,0.2); transition: transform 0.4s ease; }
        .article img:hover { transform: scale(1.03); }
        .article .content { font-size: 1.1em; line-height: 1.8; }
        .related { max-width: 1200px; margin: 30px auto; padding: 25px; }
        .related h2 { color: #cc0000; border-bottom: 3px solid #ff3333; padding-bottom: 10px; font-size: 1.8em; }
        .featured { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
        .article-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 6px 12px rgba(0,0,0,0.15); transition: transform 0.4s ease, box-shadow 0.4s ease; }
        .article-card:hover { transform: translateY(-10px) scale(1.03); box-shadow: 0 12px 24px rgba(0,0,0,0.3); }
        .article-card img { width: 100%; height: 150px; object-fit: cover; transition: transform 0.4s ease; }
        .article-card:hover img { transform: scale(1.05); }
        .article-card h3 { padding: 15px; margin: 0; color: #cc0000; font-size: 1.2em; }
        .article-card a { display: block; padding: 12px; background: linear-gradient(to right, #cc0000, #ff3333); color: white; text-align: center; text-decoration: none; font-weight: bold; }
        .article-card a:hover { background: linear-gradient(to right, #a00000, #cc0000); }
        .comments { max-width: 800px; margin: 30px auto; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
        .comments h2 { color: #cc0000; border-bottom: 3px solid #ff3333; padding-bottom: 10px; font-size: 1.8em; }
        .comments .comment { border-bottom: 1px solid #eee; padding: 15px 0; display: flex; flex-direction: column; gap: 12px; }
        .comments .comment img { max-width: 200px; max-height: 200px; border-radius: 8px; object-fit: cover; transition: transform 0.3s ease; }
        .comments .comment img:hover { transform: scale(1.05); }
        .comments form { margin-top: 25px; }
        .comments input, .comments textarea, .comments input[type="file"] { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 8px; font-size: 1em; }
        .comments textarea { resize: vertical; min-height: 100px; }
        .comments button { padding: 12px 25px; background: linear-gradient(to right, #cc0000, #ff3333); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: background 0.3s ease; }
        .comments button:hover { background: linear-gradient(to right, #a00000, #cc0000); }
        .error, .success { color: #cc0000; margin: 10px 0; font-weight: bold; font-size: 1em; }
        .success { color: #008000; }
        #search-form { position: absolute; right: 30px; top: 25px; }
        #search-form input { padding: 12px; border: none; border-radius: 8px 0 0 8px; font-size: 1em; }
        #search-form button { padding: 12px; background: #1a1a1a; color: white; border: none; border-radius: 0 8px 8px 0; cursor: pointer; transition: background 0.3s ease; }
        #search-form button:hover { background: #ff3333; }
        @keyframes glow { from { text-shadow: 0 0 5px #fff, 0 0 10px #ff3333; } to { text-shadow: 0 0 10px #fff, 0 0 20px #cc0000; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) { .featured { grid-template-columns: 1fr; } nav a { display: block; margin: 15px 0; } #search-form { position: static; text-align: center; margin: 15px 0; } #search-form input, #search-form button { width: 100%; border-radius: 8px; } .comments .comment img { max-width: 100%; } }
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
                while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<a href="category.php?cat=' . urlencode($cat['name']) . '">' . htmlspecialchars($cat['name']) . '</a>';
                }
            }
        } catch (PDOException $e) {
            echo '<p class="error">Error loading categories: ' . htmlspecialchars($e->getMessage()) . '</p>';
            error_log("Error loading categories: " . $e->getMessage(), 3, 'errors.log');
        }
        ?>
    </nav>
    <section class="article">
        <h1><?php echo htmlspecialchars($article['title']); ?></h1>
        <p class="meta">By <?php echo htmlspecialchars($article['author']); ?> on <?php echo htmlspecialchars($article['publish_date']); ?> | Views: <?php echo $article['views']; ?> | Category: <?php echo htmlspecialchars($article['cat_name'] ?? 'Uncategorized'); ?></p>
        <img src="<?php echo htmlspecialchars($article['image_url']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
        <div class="content"><?php echo nl2br(htmlspecialchars($article['content'])); ?></div>
    </section>
    <section class="related">
        <h2>Related News</h2>
        <div class="featured">
        <?php
        try {
            $related_stmt = $pdo->prepare("SELECT * FROM articles WHERE category_id = ? AND id != ? AND title IS NOT NULL AND image_url IS NOT NULL AND content IS NOT NULL ORDER BY publish_date DESC LIMIT 3");
            $related_stmt->execute([$article['category_id'], $id]);
            if ($related_stmt->rowCount() === 0) {
                echo '<p>No related news available.</p>';
            } else {
                while ($row = $related_stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<div class="article-card">
                        <img src="' . htmlspecialchars($row['image_url']) . '" alt="' . htmlspecialchars($row['title']) . '">
                        <h3>' . htmlspecialchars($row['title']) . '</h3>
                        <a href="article.php?id=' . $row['id'] . '">Read More</a>
                    </div>';
                }
            }
        } catch (PDOException $e) {
            echo '<p class="error">Error loading related articles: ' . htmlspecialchars($e->getMessage()) . '</p>';
            error_log("Error loading related articles for article_id: " . $id . ": " . $e->getMessage(), 3, 'errors.log');
        }
        ?>
        </div>
    </section>
    <section class="comments">
        <h2>Comments</h2>
        <?php
        if (isset($_SESSION['comment_error'])) {
            echo '<p class="error">' . htmlspecialchars($_SESSION['comment_error']) . '</p>';
            unset($_SESSION['comment_error']);
        }
        if (isset($_SESSION['comment_success'])) {
            echo '<p class="success">' . htmlspecialchars($_SESSION['comment_success']) . '</p>';
            unset($_SESSION['comment_success']);
        }
        ?>
        <?php
        try {
            $comments_stmt = $pdo->prepare("SELECT * FROM comments WHERE article_id = ? ORDER BY comment_date DESC");
            $comments_stmt->execute([$id]);
            if ($comments_stmt->rowCount() === 0) {
                echo '<p>No comments yet. Be the first!</p>';
            } else {
                while ($com = $comments_stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<div class="comment">
                        <strong>' . htmlspecialchars($com['user_name']) . '</strong> on ' . htmlspecialchars($com['comment_date']) . ':<br>
                        ' . htmlspecialchars($com['comment']) . '<br>';
                    if ($image_column_exists && !empty($com['image_url'])) {
                        echo '<img src="' . htmlspecialchars($com['image_url']) . '" alt="Comment Image">';
                    }
                    echo '</div>';
                }
            }
        } catch (PDOException $e) {
            echo '<p class="error">Error loading comments: ' . htmlspecialchars($e->getMessage()) . '</p>';
            error_log("Error loading comments for article_id: " . $id . ": " . $e->getMessage(), 3, 'errors.log');
        }
        ?>
        <form method="post" action="comment.php" enctype="multipart/form-data">
            <input type="hidden" name="comment_token" value="<?php echo htmlspecialchars($comment_token); ?>">
            <input type="hidden" name="article_id" value="<?php echo $id; ?>">
            <input type="text" name="user_name" placeholder="Your Name (optional)">
            <textarea name="comment" placeholder="Your Comment" required></textarea>
            <?php if ($image_column_exists): ?>
                <input type="file" name="comment_image" accept="image/jpeg,image/png,image/gif">
            <?php endif; ?>
            <button type="submit">Post Comment</button>
        </form>
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
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Posting...';
            setTimeout(() => {
                button.disabled = false;
                button.textContent = 'Post Comment';
            }, 2000);
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
