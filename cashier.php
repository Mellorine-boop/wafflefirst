<?php

require_once __DIR__ . '/includes/bootstrap.php';
$auth->requireRole('cashier');

$message = '';
$error = '';
$success = null;

// Handle checkout. The OrderService recalculates the subtotal and discount on the server.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    try {
        $result = $orderService->checkout(
            $_POST['items'] ?? [],
            $_POST['payment_method'] ?? 'Cash',
            $auth->currentUserId(),
            [
                'type' => $_POST['discount_type'] ?? 'None',
                'reference' => $_POST['membership_card'] ?? '',
                'product_id' => (int)($_POST['membership_item'] ?? 0),
            ]
        );
        $success = $result;
        $_SESSION['cart'] = [];
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// A cashier can request a void, but an owner/admin passcode must authorize it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['void_sale'])) {
    try {
        $privileged = $userRepo->verifyPrivilegedPasscode($_POST['authorized_role'] ?? '', $_POST['authorized_passcode'] ?? '');
        if (!$privileged) throw new RuntimeException('Invalid owner/admin passcode. The void was not processed.');

        $choice = trim($_POST['void_reason_choice'] ?? '');
        $reason = $choice;
        if ($choice === 'Other') {
            $reason = trim($_POST['void_reason_other'] ?? '');
        }
        if ($reason === '') throw new RuntimeException('Please select a void reason or type the reason under Other.');

        $orderService->voidSale(
            (int)$_POST['sale_id'],
            $auth->currentUserId(),
            (int)$privileged['user_id'],
            $reason
        );
        $message = 'Sale #' . (int)$_POST['sale_id'] . ' was voided with ' . $privileged['role'] . ' authorization. Stock was reversed.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$products = $productsRepo->all();
// Group the active menu into the requested cashier categories.
$categoryOrder = ['Waffle', 'Tea', 'Coffee Blend', 'Other Beverage'];
$productsByCategory = array_fill_keys($categoryOrder, []);
foreach ($products as $item) {
    $category = $item->getCategory();
    if (!isset($productsByCategory[$category])) $productsByCategory[$category] = [];
    $productsByCategory[$category][] = $item;
}
$todaySales = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(sale_date)=CURRENT_DATE() AND status='Completed'")->fetchColumn();
$todayCount = $db->query("SELECT COUNT(*) FROM sales WHERE DATE(sale_date)=CURRENT_DATE() AND status='Completed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>21 GRIDS — Cashier</title>
<style>
body{font-family:'Segoe UI',sans-serif;background:#faf8f5;margin:40px;color:#3f3028}
.nav-link{background:#faf8f5;color:#5c3d2e;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;font-size:18px;box-shadow:0 2px 4px rgba(0,0,0,.1)}
.nav-link:hover{background:#eee}
.pos-container{display:flex;gap:40px;align-items:flex-start}
.menu-area{width:65%}.category-section{margin-bottom:28px}.category-title{font-size:26px;color:#5c3d2e;margin:0 0 14px;border-bottom:3px solid #ebdcb9;padding-bottom:8px}.menu-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.product-description{margin:0;color:#777;font-size:14px;min-height:18px}
.menu-card{background:white;padding:18px 16px;border:3px solid #ebdcb9;border-radius:16px;text-align:center}
.menu-card h2{margin:6px 0 8px;font-size:20px}
.qty-input{padding:7px;font-size:18px;text-align:center;width:82px;border:1px solid #ccc;border-radius:6px} .qty-input::-webkit-inner-spin-button{width:22px;height:28px;opacity:1} .qty-input::-webkit-outer-spin-button{width:22px;height:28px;opacity:1}
.sidebar{width:35%;display:flex;flex-direction:column;gap:30px}
.cart-panel{background:white;border:4px solid #5c3d2e;border-radius:16px;padding:28px}
.cart-line{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #eee;font-size:17px}
.cart-line small{color:#777}
.summary-row{display:flex;justify-content:space-between;margin:10px 0;font-size:18px}
.summary-total{font-size:28px;font-weight:bold;color:#2e7d32;border-top:2px solid #ebdcb9;padding-top:12px}
.discount-box{background:#fdfaf4;border:2px dashed #865d43;padding:15px;border-radius:10px;margin:15px 0}
.discount-box select,.discount-box input,.payment-select{padding:13px;font-size:17px;width:100%;box-sizing:border-box;border-radius:8px;border:1px solid #ccc;margin:6px 0 10px}
.btn{background:#2e7d32;color:white;padding:20px;width:100%;border:none;border-radius:12px;font-size:22px;font-weight:bold;cursor:pointer}
.btn-blue{background:#0277bd}.btn-red{background:#c62828}.btn-brown{background:#5c3d2e}
.notice{background:#e8f5e9;border-left:6px solid #2e7d32;padding:15px;margin-bottom:20px;font-size:17px}
.error{background:#ffebee;border-left:6px solid #c62828;padding:15px;margin-bottom:20px;font-size:17px;color:#8b0000}
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:9999}
.modal-box{background:white;border:4px solid #5c3d2e;border-radius:20px;width:480px;padding:40px;text-align:center;max-height:90vh;overflow:auto}
.modal-box input,.modal-box select{padding:14px;width:100%;box-sizing:border-box;font-size:18px;border-radius:7px;border:1px solid #ccc;margin-bottom:14px}
@media(max-width:900px){.pos-container{flex-direction:column}.menu-area,.sidebar{width:100%}}
</style>
<script>
// Live cart calculation: changing a product quantity immediately changes its line and the order subtotal.
const prices = <?=json_encode(array_reduce($products,function($out,$p){$out[$p->getId()]=['name'=>$p->getName(),'price'=>$p->getPrice()];return $out;},[]), JSON_UNESCAPED_UNICODE)?>;

function money(value){return '₱' + Number(value).toFixed(2);}

function updateCart(){
    // Get the visible cart container.
    const cart = document.getElementById('cartLines');
    // Get the membership item selector.
    const membershipItem = document.getElementById('membership_item');
    // Always calculate the subtotal from the product-price map, never from formatted text on the page.
    let subtotal = 0;
    // Start the cart HTML as an empty string.
    let html = '';
    // Start the membership selector with its default option.
    let membershipOptions = '<option value="">Select one item</option>';

    // Loop through every product displayed on the cashier screen.
    Object.keys(prices).forEach(function(id){
        // Find the quantity input belonging to the current product.
        const input = document.querySelector('input[name="items['+id+']"]');
        // Read only the numeric quantity from that input.
        const qty = input ? Math.max(0, parseInt(input.value || '0',10)) : 0;
        // Add the product to the cart only when its quantity is greater than zero.
        if(qty > 0){
            // Calculate this product's line total directly from its numeric price and quantity.
            const line = Number(prices[id].price) * qty;
            // Add the line total to the numeric subtotal.
            subtotal += line;
            // Display the product name, quantity, unit price, and line total.
            html += '<div class="cart-line"><div><b>'+escapeHtml(prices[id].name)+'</b><br><small>'+qty+'x × '+money(prices[id].price)+'</small></div><strong>'+money(line)+'</strong></div>';
            // Add the product to the membership discount choices.
            membershipOptions += '<option value="'+id+'">'+escapeHtml(prices[id].name)+'</option>';
        }
    });

    // Show the cart lines or the empty-cart message.
    cart.innerHTML = html || '<p style="color:#777;text-align:center;">No items added yet.</p>';
    // Remember the previously selected membership item.
    const oldMembership = membershipItem.value;
    // Replace the membership choices with the products currently in the cart.
    membershipItem.innerHTML = membershipOptions;
    // Restore the old membership selection when that product is still in the cart.
    if([...membershipItem.options].some(o=>o.value===oldMembership)) membershipItem.value=oldMembership;

    // Display the exact numeric subtotal.
    document.getElementById('subtotal').textContent = money(subtotal);
    // Recalculate the discount and final total from the same numeric subtotal.
    applyDiscountPreview(subtotal);
}

function applyDiscountPreview(subtotal){
    // Read the currently selected discount type.
    const type = document.getElementById('discount_type').value;
    // Start the discount amount at zero.
    let discount = 0;
    // Apply five percent to the subtotal for Senior Citizen/PWD.
    if(type === 'Senior/PWD') discount = Number(subtotal) * 0.05;
    // Apply the membership discount to exactly one selected item.
    if(type === 'Membership'){
        // Read the selected membership-discount product.
        const id = document.getElementById('membership_item').value;
        // Find the quantity input for the selected membership product.
        const input = id ? document.querySelector('input[name="items['+id+']"]') : null;
        // Read the selected product quantity.
        const qty = input ? parseInt(input.value || '0',10) : 0;
        // Give fifty percent off one unit when the selected item is in the cart.
        if(id && qty > 0) discount = Number(prices[id].price) * 0.50;
    }
    // Display the calculated discount.
    document.getElementById('discount_amount').textContent = money(discount);
    // Display the final total using subtotal minus discount.
    document.getElementById('grand_total').textContent = money(Math.max(0,Number(subtotal)-Number(discount)));
    // Show membership-specific fields only for the membership discount.
    document.getElementById('membership_fields').style.display = type === 'Membership' ? 'block' : 'none';
}

function escapeHtml(value){return String(value).replace(/[&<>'"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];});}
function toggleVoid(open){document.getElementById('voidM').style.display=open?'flex':'none';}
function toggleReport(open){document.getElementById('shiftM').style.display=open?'flex':'none';}
function toggleOtherReason(){document.getElementById('otherReason').style.display=document.getElementById('void_reason_choice').value==='Other'?'block':'none';}
window.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.qty-input').forEach(function(input){input.addEventListener('input',updateCart);});
    document.getElementById('discount_type').addEventListener('change',function(){applyDiscountPreview(parseFloat(document.getElementById('subtotal').textContent.replace(/[^0-9.-]/g,''))||0);});
    document.getElementById('membership_item').addEventListener('change',function(){applyDiscountPreview(parseFloat(document.getElementById('subtotal').textContent.replace(/[^0-9.-]/g,''))||0);});
    updateCart();
});
</script>
</head>
<body>
<div style="background:#5c3d2e;padding:15px 30px;margin:-40px -40px 30px -40px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 8px rgba(0,0,0,.05);">
    <h2 style="color:white;margin:0;font-size:26px;">21 GRIDS — Cashier Counter Register</h2>
    <span><a href="logout.php" class="nav-link">⬅️ Logout</a></span>
</div>

<?php if($message): ?><div class="notice">✅ <?=e($message)?></div><?php endif; ?>
<?php if($error): ?><div class="error">❌ <?=e($error)?></div><?php endif; ?>

<form method="POST" id="checkoutForm">
<div class="pos-container">
    <div class="menu-area">
        <?php foreach($categoryOrder as $category): ?>
            <?php if(empty($productsByCategory[$category])) continue; ?>
            <section class="category-section">
                <h2 class="category-title"><?=e($category)?></h2>
                <div class="menu-grid">
                <?php foreach($productsByCategory[$category] as $item): ?>
                <div class="menu-card">
                    <h2><?=e($item->getName())?></h2>
                    <p class="product-description"><?=e($item->getDescription())?></p>
                    <h3 style="color:#2e7d32;margin:15px 0;">₱<?=number_format($item->getPrice(),2)?></h3>
                    <input class="qty-input" type="number" name="items[<?=$item->getId()?>]" value="0" min="0" max="20">
                </div>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="sidebar">
        <div class="cart-panel">
            <h3 style="margin-top:0;">🛒 Current Order</h3>
            <div id="cartLines"></div>
            <div class="summary-row"><span>Subtotal</span><strong id="subtotal">₱0.00</strong></div>

            <div class="discount-box">
                <strong>🏷️ Discount</strong>
                <select name="discount_type" id="discount_type">
                    <option value="None">No Discount</option>
                    <option value="Senior/PWD">Senior Citizen / PWD — 5% off</option>
                    <option value="Membership">Membership Card — 50% off one item</option>
                </select>
                <div id="membership_fields" style="display:none;">
                    <input type="text" name="membership_card" placeholder="Membership card number">
                    <select name="membership_item" id="membership_item"><option value="">Select one item</option></select>
                    <small style="color:#6f625b;">Membership discount may be used once per card per day. Only one discount can be applied to a sale.</small>
                </div>
            </div>

            <div class="summary-row"><span>Discount</span><strong id="discount_amount">₱0.00</strong></div>
            <div class="summary-row summary-total"><span>Total</span><strong id="grand_total">₱0.00</strong></div>

            <select class="payment-select" name="payment_method">
                <option value="Cash">💵 Cash at Register</option>
                <option value="Online Payment/Bank transfer">📱 Online Payment/Bank transfer</option>
                <option value="Card">💳 Card Payment</option>
            </select>
            <button type="submit" name="checkout" class="btn">Complete Checkout</button>
            <button type="button" onclick="toggleReport(true)" class="btn btn-blue" style="margin-top:20px;font-size:20px;">📊 Register Report</button>
            <button type="button" onclick="toggleVoid(true)" class="btn btn-red" style="margin-top:20px;font-size:20px;">💸 Refund / Void Order</button>
        </div>
    </div>
</div>
</form>

<!-- Register report modal keeps the original layout's modal behavior. -->
<div id="shiftM" class="modal-overlay">
    <div class="modal-box">
        <h3>📊 Station Shift Tally</h3>
        <h2 style="text-align:left;background:#fdfaf4;padding:15px;border-left:6px solid #865d43;">Active Orders: <?=e($todayCount)?></h2>
        <h2 style="text-align:left;background:#fdfaf4;padding:15px;border-left:6px solid #2e7d32;">Active Sales: ₱<?=number_format((float)$todaySales,2)?></h2>
        <button class="btn btn-brown" type="button" onclick="toggleReport(false)">Close View</button>
    </div>
</div>

<!-- Void modal: cashier supplies the sale and reason, while owner/admin supplies the authorization passcode. -->
<div id="voidM" class="modal-overlay">
    <div class="modal-box" style="border-color:#c62828;">
        <h3 style="color:#c62828;">💸 Protected Void / Refund</h3>
        <p style="text-align:left;color:#555;">A cashier cannot complete a void alone. An owner or admin must authorize this transaction with their passcode.</p>
        <form method="POST">
            <input type="number" name="sale_id" min="1" required placeholder="Receipt / Sale ID">
            <select name="void_reason_choice" id="void_reason_choice" onchange="toggleOtherReason()" required>
                <option value="">Select reason</option>
                <option>Customer requested cancellation</option>
                <option>Wrong item entered</option>
                <option>Wrong quantity entered</option>
                <option>Duplicate transaction</option>
                <option>Incorrect payment method</option>
                <option>Other</option>
            </select>
            <input type="hidden" name="void_reason" id="void_reason_hidden">
            <div id="otherReason" style="display:none;"><input type="text" name="void_reason_other" placeholder="Type the reason"></div>
            <select name="authorized_role" required>
                <option value="">Who is authorizing?</option>
                <option value="owner">Owner</option>
                <option value="admin">Admin</option>
            </select>
            <input type="password" name="authorized_passcode" required placeholder="Owner / Admin passcode">
            <button type="submit" name="void_sale" class="btn btn-red" style="font-size:20px;padding:15px;">Execute Protected Void</button>
            <button type="button" class="btn" onclick="toggleVoid(false)" style="background:#777;font-size:18px;padding:12px;margin-top:10px;">Cancel Operation</button>
        </form>
    </div>
</div>

<?php if($success): ?>
<div class="modal-overlay" style="display:flex;">
    <div class="modal-box" style="border-color:#2e7d32;">
        <h3 style="color:#2e7d32;">🎉 Order Logged Successfully!</h3>
        <h2>Receipt Reference: #<?=e($success['sale_id'])?></h2>
        <p>Subtotal: ₱<?=number_format($success['subtotal'],2)?></p>
        <p>Discount: ₱<?=number_format($success['discount'],2)?></p>
        <h1 style="color:#2e7d32;">Total: ₱<?=number_format($success['total'],2)?></h1>
        <button class="btn" onclick="window.location.href='cashier.php';">Clear Canvas</button>
    </div>
</div>
<?php endif; ?>
</body>
</html>
