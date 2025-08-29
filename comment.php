<?php
// Start output buffering
ob_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';
session_start();

// Validate article ID
$article_id = (int)($_POST['article_id'] ?? 0);
if ($article_id <= 0) {
    $_SESSION['comment_error'] = 'Invalid article ID.';
    error_log("Invalid article ID: " . $article_id, 3, 'errors.log');
    header("Location: index.php");
    exit;
}

// Verify CSRF token
if (!isset($_POST['comment_token']) || !isset($_SESSION['comment_token']) || $_POST['comment_token'] !== $_SESSION['comment_token']) {
    $_SESSION['comment_error'] = 'Invalid submission. Please try again.';
    error_log("CSRF token validation failed for article_id: " . $article_id, 3, 'errors.log');
    header("Location: article.php?id=" . $article_id);
    exit;
}

// Check if article exists
try {
    $stmt = $pdo->prepare("SELECT id FROM articles WHERE id = ?");
    $stmt->execute([$article_id]);
    if ($stmt->rowCount() === 0) {
        $_SESSION['comment_error'] = 'Article not found.';
        error_log("Article not found for id: " . $article_id, 3, 'errors.log');
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['comment_error'] = 'Database error: ' . htmlspecialchars($e->getMessage());
    error_log("Error checking article_id: " . $article_id . ": " . $e->getMessage(), 3, 'errors.log');
    header("Location: article.php?id=" . $article_id);
    exit;
}

// Check if image_url column exists
$image_column_exists = true;
try {
    $pdo->query("SELECT image_url FROM comments LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $image_column_exists = false;
    } else {
        $_SESSION['comment_error'] = 'Database error: ' . htmlspecialchars($e->getMessage());
        error_log("Error checking comments table: " . $e->getMessage(), 3, 'errors.log');
        header("Location: article.php?id=" . $article_id);
        exit;
    }
}

// Handle comment submission
$user_name = trim($_POST['user_name'] ?? 'Anonymous');
$comment = trim($_POST['comment'] ?? '');
$image_url = null;

// Input validation
if (empty($comment)) {
    $_SESSION['comment_error'] = 'Comment cannot be empty.';
    error_log("Empty comment for article_id: " . $article_id, 3, 'errors.log');
    header("Location: article.php?id=" . $article_id);
    exit;
}

// Handle image upload if column exists
if ($image_column_exists && !empty($_FILES) && isset($_FILES['comment_image']) && $_FILES['comment_image']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    $file = $_FILES['comment_image'];
    $upload_dir = 'Uploads/';
    $file_name = uniqid() . '_' . str_replace(' ', '_', basename($file['name']));
    $upload_path = $upload_dir . $file_name;

    // Attempt to create Uploads directory
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create Uploads directory for article_id: " . $article_id, 3, 'errors.log');
            $_SESSION['comment_error'] = 'Uploads directory creation failed. Comment posted without image.';
        }
    }

    // Check uploads directory
    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
        $_SESSION['comment_error'] = 'Uploads directory is missing or not writable. Comment posted without image.';
        error_log("Uploads directory issue for article_id: " . $article_id, 3, 'errors.log');
    } elseif (!in_array($file['type'], $allowed_types)) {
        $_SESSION['comment_error'] = 'Invalid image type. Only JPEG, PNG, and GIF are allowed.';
        error_log("Invalid image type: " . $file['type'] . " for article_id: " . $article_id, 3, 'errors.log');
        header("Location: article.php?id=" . $article_id);
        exit;
    } elseif ($file['size'] > $max_size) {
        $_SESSION['comment_error'] = 'Image size exceeds 2MB.';
        error_log("Image size exceeds 2MB for article_id: " . $article_id, 3, 'errors.log');
        header("Location: article.php?id=" . $article_id);
        exit;
    } else {
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            $_SESSION['comment_error'] = 'Failed to upload image. Comment posted without image.';
            error_log("Failed to move uploaded file for article_id: " . $article_id, 3, 'errors.log');
        } else {
            $image_url = $upload_path;
        }
    }
} elseif ($image_column_exists && !empty($_FILES) && isset($_FILES['comment_image']) && $_FILES['comment_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $_SESSION['comment_error'] = 'Image upload error: ' . $_FILES['comment_image']['error'] . '. Comment posted without image.';
    error_log("Image upload error: " . $_FILES['comment_image']['error'] . " for article_id: " . $article_id, 3, 'errors.log');
}

// Insert comment into database
try {
    // Sanitize inputs
    $user_name = substr(strip_tags($user_name), 0, 100);
    $comment = substr(strip_tags($comment), 0, 1000);

    if ($image_column_exists) {
        $comment_stmt = $pdo->prepare("INSERT INTO comments (article_id, user_name, comment, image_url, comment_date) VALUES (?, ?, ?, ?, NOW())");
        $result = $comment_stmt->execute([$article_id, $user_name, $comment, $image_url]);
    } else {
        $comment_stmt = $pdo->prepare("INSERT INTO comments (article_id, user_name, comment, comment_date) VALUES (?, ?, ?, NOW())");
        $result = $comment_stmt->execute([$article_id, $user_name, $comment]);
    }

    if ($result) {
        $_SESSION['comment_success'] = 'Comment posted successfully!';
        error_log("Comment inserted successfully for article_id: " . $article_id . ", user: " . $user_name . ", comment: " . substr($comment, 0, 50), 3, 'errors.log');
    } else {
        $_SESSION['comment_error'] = 'Failed to insert comment into database.';
        error_log("Failed to insert comment for article_id: " . $article_id . ", user: " . $user_name . ", comment: " . substr($comment, 0, 50), 3, 'errors.log');
    }

    // Generate new token
    $_SESSION['comment_token'] = bin2hex(random_bytes(32));
} catch (PDOException $e) {
    $_SESSION['comment_error'] = 'Failed to post comment: ' . htmlspecialchars($e->getMessage());
    error_log("Comment insertion error for article_id: " . $article_id . ": " . $e->getMessage(), 3, 'errors.log');
}

// Clean output buffer and redirect
ob_end_clean();
header("Location: article.php?id=" . $article_id);
exit;
?>
