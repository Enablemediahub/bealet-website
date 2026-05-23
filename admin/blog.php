<?php
/**
 * Bealet Website - Admin Blog Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

requireSuperAdmin();

global $db;

$errors = [];
$success = false;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list';
$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$formData = [
    'title' => '',
    'excerpt' => '',
    'content' => '',
    'featured_image' => '',
    'is_published' => 0
];

// Handle post deletion (before any output)
if (isset($_GET['delete'])) {
    $postId = (int)$_GET['delete'];
    $post = $db->fetch("SELECT featured_image FROM blog_posts WHERE id = ?", [$postId]);
    
    if ($post && $post['featured_image']) {
        $imagePath = getBlogImageLocalPath($post['featured_image']);
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    $db->update("DELETE FROM blog_posts WHERE id = ?", [$postId]);
    createLog('BLOG_POST_DELETED', "Blog post #$postId deleted");
    setFlashMessage('success', 'Blog post deleted successfully');
    header('Location: ' . APP_URL . '/admin/blog.php');
    exit;
}

// Handle post form submission (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $title = sanitize($_POST['title'] ?? '');
        $excerpt = sanitize($_POST['excerpt'] ?? '');
        $content = $_POST['content'] ?? '';
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        
        if (empty($title)) $errors[] = 'Title is required';
        if (empty($content)) $errors[] = 'Content is required';
        
        // Handle image upload
        $featuredImage = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['featured_image'], 'blog');
            if ($uploadResult['success']) {
                $featuredImage = $uploadResult['filename'];
            } else {
                $errors[] = $uploadResult['error'];
            }
        }
        
        if (empty($errors)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
            $adminId = $_SESSION['user_id'];
            
            if ($postId > 0) {
                // Update existing post
                $existingPost = $db->fetch("SELECT featured_image FROM blog_posts WHERE id = ?", [$postId]);
                
                if (!empty($featuredImage) && !empty($existingPost['featured_image'])) {
                    $oldImagePath = getBlogImageLocalPath($existingPost['featured_image']);
                    if ($oldImagePath && file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                } elseif (empty($featuredImage)) {
                    $featuredImage = $existingPost['featured_image'];
                }
                
                $db->update(
                    "UPDATE blog_posts SET title = ?, slug = ?, excerpt = ?, content = ?, featured_image = ?, is_published = ?, published_at = ?, updated_at = NOW() WHERE id = ?",
                    [$title, $slug, $excerpt, $content, $featuredImage, $isPublished, $isPublished ? date('Y-m-d H:i:s') : null, $postId]
                );
                createLog('BLOG_POST_UPDATED', "Blog post #$postId updated");
                setFlashMessage('success', 'Blog post updated successfully');
            } else {
                // Create new post
                $db->update(
                    "INSERT INTO blog_posts (title, slug, excerpt, content, featured_image, author_id, is_published, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$title, $slug, $excerpt, $content, $featuredImage, $adminId, $isPublished, $isPublished ? date('Y-m-d H:i:s') : null]
                );
                createLog('BLOG_POST_CREATED', "New blog post created: $title");
                setFlashMessage('success', 'Blog post created successfully');
            }
            
            header('Location: ' . APP_URL . '/admin/blog.php');
            exit;
        }
    }
}

// Include header after all redirect logic
require_once __DIR__ . '/inc/header.php';

?>

        <!-- Blog Management -->
        <div class="container-fluid mt-4 mb-5">
            <?php 
            // Get blog posts for list view
            $blogPosts = [];
            $post = null;
            
            if ($mode === 'list') {
                $blogPosts = $db->fetchAll(
                    "SELECT bp.*, u.name as author_name FROM blog_posts bp
                     LEFT JOIN users u ON bp.author_id = u.id
                     ORDER BY bp.created_at DESC"
                );
            } elseif ($mode === 'edit' && $postId > 0) {
                $post = $db->fetch(
                    "SELECT * FROM blog_posts WHERE id = ?",
                    [$postId]
                );
                
                if ($post) {
                    $formData = [
                        'title' => $post['title'],
                        'excerpt' => $post['excerpt'],
                        'content' => $post['content'],
                        'featured_image' => $post['featured_image'],
                        'is_published' => $post['is_published']
                    ];
                }
            }
            ?>
            <?php if ($mode === 'list'): ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Blog Management</h2>
                    <p class="text-muted">Create and manage blog posts</p>
                </div>
                <a href="<?php echo APP_URL; ?>/admin/blog.php?mode=create" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Write New Post
                </a>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                <div><?php echo sanitize($error); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Blog Posts Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Status</th>
                                <th>Views</th>
                                <th>Published</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($blogPosts)): ?>
                                <?php foreach ($blogPosts as $post): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($post['title']); ?></strong></td>
                                    <td><?php echo sanitize($post['author_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $post['is_published'] ? 'success' : 'warning'; ?>">
                                            <?php echo $post['is_published'] ? 'Published' : 'Draft'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $post['views']; ?></td>
                                    <td><?php echo $post['published_at'] ? formatDate($post['published_at']) : '-'; ?></td>
                                    <td><?php echo formatDate($post['created_at']); ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/admin/blog.php?mode=edit&id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>
                                        <a href="?delete=<?php echo $post['id']; ?>" onclick="return confirmDelete('Delete this blog post?')" class="btn btn-sm btn-outline-danger">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 1rem;"></i>
                                    No blog posts yet. <a href="<?php echo APP_URL; ?>/admin/blog.php?mode=create">Create your first post</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- Blog Post Editor -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><?php echo ($postId > 0) ? 'Edit Post' : 'Write New Post'; ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger mb-3">
                                <?php foreach ($errors as $error): ?>
                                <div><?php echo sanitize($error); ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Post Title</label>
                                    <input type="text" class="form-control" name="title" value="<?php echo sanitize($formData['title']); ?>" required>
                                    <small class="text-muted">This will also be used for the post URL</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Featured Image</label>
                                    <div class="input-group mb-2">
                                        <input type="file" class="form-control" name="featured_image" accept="image/*" id="imageInput">
                                        <label class="input-group-text" for="imageInput">Choose</label>
                                    </div>
                                    <?php if (!empty($formData['featured_image'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted d-block mb-2">Current image:</small>
                                        <img src="<?php echo getBlogImageUrl($formData['featured_image']); ?>" alt="Featured" style="max-width: 200px; border-radius: 0.5rem;">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Excerpt</label>
                                    <textarea class="form-control" name="excerpt" rows="3" placeholder="Brief summary of the post..."><?php echo sanitize($formData['excerpt']); ?></textarea>
                                    <small class="text-muted">Optional brief summary (appears in listings)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Post Content</label>
                                    <textarea class="form-control" id="postContent" name="content" rows="8" required><?php echo sanitize($formData['content']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="isPublished" name="is_published" <?php echo $formData['is_published'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="isPublished">
                                            Publish this post
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> <?php echo ($postId > 0) ? 'Update' : 'Publish'; ?>
                                    </button>
                                    <a href="<?php echo APP_URL; ?>/admin/blog.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Post Info</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Status</strong>
                                <p class="mb-0">
                                    <?php if ($postId > 0): ?>
                                        <span class="badge bg-<?php echo $formData['is_published'] ? 'success' : 'warning'; ?>">
                                            <?php echo $formData['is_published'] ? 'Published' : 'Draft'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">New Post</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <strong>Author</strong>
                                <p class="mb-0"><?php echo sanitize(getCurrentUser()['name']); ?></p>
                            </div>
                            <?php if ($postId > 0): ?>
                            <div class="mb-3">
                                <strong>Views</strong>
                                <p class="mb-0" id="viewCount"><?php echo $db->fetch("SELECT views FROM blog_posts WHERE id = ?", [$postId])['views']; ?></p>
                            </div>
                            <div class="mb-3">
                                <strong>Created</strong>
                                <p class="mb-0"><?php echo formatDate($post['created_at']); ?></p>
                            </div>
                            <div class="mb-3">
                                <strong>Last Updated</strong>
                                <p class="mb-0"><?php echo formatDate($post['updated_at']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
