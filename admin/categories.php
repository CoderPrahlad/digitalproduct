<?php
$pageTitle = 'Categories';
require_once __DIR__ . '/includes/admin_header.php';

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL . '/admin/categories.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            try {
                $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
                flash('success', "Category \"$name\" created!");
            } catch (Exception $e) {
                flash('error', 'Could not create category.');
            }
        } else { flash('error', 'Category name is required.'); }
    } elseif ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            $pdo->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$name, $id]);
            flash('success', 'Category updated.');
        } else { flash('error', 'Category name is required.'); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Products in this category simply become "uncategorized" (category_id -> NULL via FK)
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        flash('success', 'Category deleted.');
    }
    redirect(SITE_URL . '/admin/categories.php');
}

$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();
?>
<div class="admin-topbar">
  <h1>📂 Categories <span class="cat-count-pill"><?= count($categories) ?> total</span></h1>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('addCatModal').style.display='flex'">+ Add Category</button>
</div>

<?php if (empty($categories)): ?>
  <div class="section-card" style="text-align:center;color:var(--muted);padding:60px 20px">
    <div style="font-size:40px;margin-bottom:10px">📂</div>
    No categories yet. Create your first one!
  </div>
<?php else: ?>
<div class="cat-grid">
  <?php
    // Rotate a small set of accent colors so the grid feels lively, not flat.
    $accents = ['#a78bfa','#60a5fa','#34d399','#fbbf24','#f472b6','#38bdf8'];
  ?>
  <?php foreach ($categories as $i => $c): $accent = $accents[$i % count($accents)]; ?>
  <div class="cat-card" style="--accent:<?= $accent ?>">
    <div class="cat-card-top">
      <div class="cat-card-icon">📁</div>
      <span class="cat-card-id">#<?= $c['id'] ?></span>
    </div>
    <h4 class="cat-card-name"><?= clean($c['name']) ?></h4>
    <div class="cat-card-meta">
      <span class="cat-card-count"><?= (int)$c['product_count'] ?></span>
      <span><?= (int)$c['product_count'] === 1 ? 'product' : 'products' ?></span>
    </div>
    <div class="cat-card-actions">
      <button class="btn btn-outline btn-sm" onclick="editCategory(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['name']), ENT_QUOTES) ?>)">✏️ Edit</button>
      <form method="POST" onsubmit="return confirm('Delete category <?= clean($c['name']) ?>? Products in it will become uncategorized.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $c['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit">🗑️</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Category Modal -->
<div id="addCatModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:28px;width:100%;max-width:420px;margin:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Add Category</h3>
      <button onclick="document.getElementById('addCatModal').style.display='none'" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer">✕</button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label>Category Name *</label>
        <input class="form-control" type="text" name="name" placeholder="e.g. E-Commerce" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create Category</button>
    </form>
  </div>
</div>

<!-- Edit Category Modal -->
<div id="editCatModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:28px;width:100%;max-width:420px;margin:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Edit Category</h3>
      <button onclick="document.getElementById('editCatModal').style.display='none'" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer">✕</button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editCatId">
      <div class="form-group">
        <label>Category Name *</label>
        <input class="form-control" type="text" name="name" id="editCatName" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
    </form>
  </div>
</div>

<script>
function editCategory(id, name) {
  document.getElementById('editCatId').value = id;
  document.getElementById('editCatName').value = name;
  document.getElementById('editCatModal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>