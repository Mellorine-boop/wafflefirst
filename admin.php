<?php

require_once __DIR__ . '/includes/bootstrap.php';
$auth->requireRole('admin');

$show_admin_modal = false; $modal_title = ''; $modal_msg = '';
function adminAlert(&$show, &$title, &$msg, $t, $m) { $show=true; $title=$t; $msg=$m; }

// Add a product through the polymorphic MenuItem subclasses before saving it.
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_product'])) {
    try {
        $name=trim($_POST['prod_name']??''); $cat=in_array($_POST['prod_category']??'', ['Waffle','Tea','Coffee Blend','Other Beverage'], true) ? $_POST['prod_category'] : 'Waffle'; $price=(float)($_POST['prod_price']??0); $description=trim($_POST['prod_description']??'');
        $item=$cat==='Waffle' ? new App\Models\Waffle(0,$name,$price,$cat,$description) : new App\Models\Drink(0,$name,$price,$cat,$description);
        $stmt=$db->prepare('INSERT INTO products(name,category,price,description,is_available) VALUES(?,?,?,?,1)');
        $stmt->execute([$item->getName(),$cat,$item->getPrice(),$item->getDescription()]);
        adminAlert($show_admin_modal,$modal_title,$modal_msg,'📥 Catalog Expanded','Product added and now visible to cashiers.');
    } catch(Throwable $e){adminAlert($show_admin_modal,$modal_title,$modal_msg,'❌ Database Error',$e->getMessage());}
}

// Edit a product while reusing the same OOP validation.
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_product'])) {
    try {
        $id=(int)$_POST['edit_id']; $name=trim($_POST['edit_name']); $price=(float)$_POST['edit_price'];
        $row=$db->prepare('SELECT category FROM products WHERE product_id=?'); $row->execute([$id]); $cat=$row->fetchColumn();
        if (!$cat) throw new RuntimeException('Product not found.');
        $item=$cat==='Waffle' ? new App\Models\Waffle($id,$name,$price) : new App\Models\Drink($id,$name,$price);
        $db->prepare('UPDATE products SET name=?,price=? WHERE product_id=?')->execute([$item->getName(),$item->getPrice(),$id]);
        adminAlert($show_admin_modal,$modal_title,$modal_msg,'✏️ Product Updated','Name and price were saved. Past sales keep their original prices.');
    } catch(Throwable $e){adminAlert($show_admin_modal,$modal_title,$modal_msg,'❌ Update Failed',$e->getMessage());}
}

// Toggle availability instead of deleting historical products.
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_product'])) {
    try {
        $id=(int)$_POST['toggle_id'];
        $db->prepare('UPDATE products SET is_available=1-is_available WHERE product_id=?')->execute([$id]);
        $now=$db->prepare('SELECT is_available FROM products WHERE product_id=?'); $now->execute([$id]); $available=(int)$now->fetchColumn();
        adminAlert($show_admin_modal,$modal_title,$modal_msg,$available?'✅ Item Available':'🚫 Item Hidden',$available?'This item is back on the cashier menu.':'This item is now hidden from the cashier terminal.');
    } catch(Throwable $e){adminAlert($show_admin_modal,$modal_title,$modal_msg,'❌ Database Error','Availability could not be changed.');}
}

// Safe removal: hide the product instead of physically deleting it so sold items remain in historical sales.
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_product'])) {
    try {
        $db->prepare('UPDATE products SET is_available=0 WHERE product_id=?')->execute([(int)$_POST['remove_id']]);
        adminAlert($show_admin_modal,$modal_title,$modal_msg,'🚫 Item Removed','The product was hidden from the cashier menu. Historical sales were preserved.');
    } catch(Throwable $e){adminAlert($show_admin_modal,$modal_title,$modal_msg,'❌ Database Error','The product could not be removed from the cashier menu.');}
}

$rows=$db->query('SELECT product_id,name,category,price,description,is_available FROM products ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>21 GRIDS — Admin</title>
<style>
body{font-family:'Segoe UI',sans-serif;background:#faf8f5;margin:40px;color:#3f3028}.nav-link{background:#faf8f5;color:#5c3d2e;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;font-size:18px;box-shadow:0 2px 4px rgba(0,0,0,.1)}.nav-link:hover{background:#eee}.admin-flex{display:flex;gap:40px;align-items:flex-start}table{flex:1;border-collapse:collapse;background:white;border-radius:12px;overflow:hidden}th,td{border:1px solid #ebdcb9;padding:16px;text-align:left;font-size:18px}th{background:#f5eedc;color:#5c3d2e}input,select{padding:16px;margin-bottom:20px;width:100%;font-size:18px;box-sizing:border-box}.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:9999}.modal-box{background:white;border:4px solid #5c3d2e;border-radius:20px;width:450px;padding:40px;text-align:center}.act{display:inline-block;border:none;border-radius:6px;padding:8px 12px;font-size:15px;font-weight:bold;cursor:pointer;color:white;margin:2px 0}.act-edit{background:#0277bd}.act-hide{background:#ef6c00}.act-show{background:#2e7d32}.act-del{background:#c62828}.badge{padding:4px 10px;border-radius:6px;font-size:14px;font-weight:bold}.badge-on{background:#c8e6c9;color:#2e7d32}.badge-off{background:#ffcdd2;color:#b71c1c}
</style>
<script>
// Opens the edit dialog without changing the original table/card placement.
function openEdit(id,name,price){document.getElementById('edit_id').value=id;document.getElementById('edit_name').value=name;document.getElementById('edit_price').value=price;document.getElementById('editM').style.display='flex';}
function closeEdit(){document.getElementById('editM').style.display='none';}
</script>
</head>
<body>
<div style="background:#5c3d2e;padding:15px 30px;margin:-40px -40px 30px -40px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 8px rgba(0,0,0,.05);">
<h2 style="color:white;margin:0;font-size:26px;">🛠️ 21 GRIDS — Admin Menu Schema Control Layer</h2><a href="logout.php" class="nav-link">⬅️ Logout</a>
</div>
<div class="admin-flex">
<div style="width:30%;background:#fdfaf4;padding:40px;border-radius:16px;border:3px dashed #865d43;">
<h3>Add Product Structure</h3>
<form method="POST"><input type="text" name="prod_name" required placeholder="Product Node Name"><select name="prod_category"><option value="Waffle">Waffle</option><option value="Tea">Tea</option><option value="Coffee Blend">Coffee Blend</option><option value="Other Beverage">Other Beverage</option></select><input type="number" step="0.01" min="0.01" name="prod_price" required placeholder="Base Price Value"><input type="text" name="prod_description" maxlength="100" placeholder="Short description (1-3 words)"><button type="submit" name="add_product" style="padding:15px;width:100%;background:#2e7d32;color:white;border:none;border-radius:8px;font-size:20px;font-weight:bold;cursor:pointer;">Push Node</button></form>
</div>
<table><thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Category</th><th>Price</th><th>Cashier Menu</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr style="<?=$r['is_available']?'':'background:#fafafa;color:#999;'?>"><td>#<?=$r['product_id']?></td><td><strong><?=e($r['name'])?></strong></td><td><?=e($r['description']??'')?></td><td><?=e($r['category'])?></td><td style="color:#2e7d32;font-weight:bold;">₱<?=number_format($r['price'],2)?></td><td><span class="badge <?=$r['is_available']?'badge-on':'badge-off'?>"><?=$r['is_available']?'AVAILABLE':'HIDDEN'?></span></td><td>
<button type="button" class="act act-edit" onclick='openEdit(<?=json_encode((int)$r['product_id'])?>,<?=json_encode($r['name'])?>,<?=json_encode((float)$r['price'])?>)'>✏️ Edit</button>
<form method="POST" style="display:inline;margin:0"><input type="hidden" name="toggle_id" value="<?=$r['product_id']?>"><button class="act <?=$r['is_available']?'act-hide':'act-show'?>" name="toggle_product"><?=$r['is_available']?'Hide':'Show'?></button></form>
<form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Remove this product from the cashier menu?')"><input type="hidden" name="remove_id" value="<?=$r['product_id']?>"><button class="act act-del" name="remove_product">Remove</button></form>
</td></tr>
<?php endforeach; ?></tbody></table>
</div>

<div id="editM" class="modal-overlay"><div class="modal-box"><h3>✏️ Edit Product</h3><form method="POST"><input type="hidden" name="edit_id" id="edit_id"><input type="text" name="edit_name" id="edit_name" required><input type="number" step="0.01" min="0.01" name="edit_price" id="edit_price" required><button class="act act-edit" style="font-size:18px;padding:14px;width:100%;" name="edit_product">Save Changes</button><button type="button" class="act" style="background:#777;font-size:18px;padding:14px;width:100%;" onclick="closeEdit()">Cancel</button></form></div></div>
<?php if($show_admin_modal): ?><div class="modal-overlay" style="display:flex"><div class="modal-box"><h3><?=e($modal_title)?></h3><p style="background:#fdfaf4;padding:15px;border-left:6px solid #865d43;text-align:left;font-size:18px;"><?=e($modal_msg)?></p><button class="act" style="background:#5c3d2e;font-size:18px;padding:14px;width:100%;" onclick="window.location.href='admin.php'">Acknowledge</button></div></div><?php endif; ?>
</body></html>
