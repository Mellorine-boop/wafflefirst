<?php

require_once __DIR__ . '/includes/bootstrap.php';
// Require an authenticated owner before displaying this page.
$auth->requireRole('owner');
// Handle owner-only account management actions before loading the dashboard records.
$accountMessage = '';
$accountError = '';
$manageAccounts = ($_GET['manage_accounts'] ?? '') === '1';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_action'])) {
    try {
        // Read the account action selected by the owner.
        $accountAction = $_POST['account_action'];
        // Read the currently logged-in owner's ID for protected account operations.
        $currentOwnerId = $auth->currentUserId();
        // Create a new admin or cashier account when requested.
        if ($accountAction === 'create') {
            $userRepo->createAccount($_POST['username'] ?? '', $_POST['full_name'] ?? '', $_POST['role'] ?? '', $_POST['password'] ?? '');
            $accountMessage = 'Account created successfully.';
        // Deactivate an existing admin or cashier account when requested.
        } elseif ($accountAction === 'deactivate') {
            $userRepo->deactivateAccount((int)($_POST['user_id'] ?? 0), $currentOwnerId);
            $accountMessage = 'Account removed from active login access.';
        // Reactivate a previously deactivated account when requested.
        } elseif ($accountAction === 'activate') {
            $userRepo->activateAccount((int)($_POST['user_id'] ?? 0));
            $accountMessage = 'Account restored successfully.';
        // Transfer an existing admin/cashier position when requested.
        } elseif ($accountAction === 'change_role') {
            $userRepo->changeRole((int)($_POST['user_id'] ?? 0), $_POST['role'] ?? '');
            $accountMessage = 'Account position updated successfully.';
        }
        // Return to the account manager after a successful action so the owner sees the updated list.
        $manageAccounts = true;
    } catch (Throwable $ex) {
        // Show a safe application-level error without exposing database credentials or SQL details.
        $accountError = $ex->getMessage();
        $manageAccounts = true;
    }
}

// Read the current owner summary cards from the sales repository.
$summary=$saleRepo->summary();
// Read optional report filters submitted through the GET form.
$dateFrom=trim($_GET['date_from'] ?? '');
// Read the optional ending date filter.
$dateTo=trim($_GET['date_to'] ?? '');
// Read the optional cashier filter.
$cashierId=(int)($_GET['cashier_id'] ?? 0);
// Read the optional payment filter.
$paymentMethod=trim($_GET['payment_method'] ?? '');
// Read the optional status filter.
$status=trim($_GET['status'] ?? '');
// Read the optional card view so the owner can click a summary card and see its records.
$cardView=trim($_GET['card_view'] ?? '');
// Load the filtered daily summary.
$daily=$saleRepo->dailySales($dateFrom ?: null,$dateTo ?: null);
// Load the filtered master audit ledger.
$ledger=$saleRepo->ledger(['date_from'=>$dateFrom,'date_to'=>$dateTo,'cashier_id'=>$cashierId,'payment_method'=>$paymentMethod,'status'=>$status]);
// Load the active cashier list for the cashier filter.
$cashiers=$saleRepo->cashiers();
// Load all staff accounts for the owner account-management floating card.
$accounts=$userRepo->allAccounts();
// Build the common filter values used by the master ledger and the three independent card tables.
$recordFilters=['date_from'=>$dateFrom,'date_to'=>$dateTo,'cashier_id'=>$cashierId,'payment_method'=>$paymentMethod];
// Load completed records for the Net Active Revenue floating table.
$activeRevenueRecords=$saleRepo->activeRevenueRecords($recordFilters);
// Load completed records for the Net Audited Invoice Volume floating table.
$invoiceVolumeRecords=$saleRepo->invoiceVolumeRecords($recordFilters);
// Load voided records for the Voided Receipts floating table.
$voidedReceiptRecords=$saleRepo->voidedReceiptRecords($recordFilters);
// Build the current filter query so card links preserve the owner's selected filters.
$filterQuery=http_build_query(['date_from'=>$dateFrom,'date_to'=>$dateTo,'cashier_id'=>$cashierId ?: '','payment_method'=>$paymentMethod,'status'=>$status]);

// DSA Algorithm #4: bubble sort the daily report by net sales.
$dailyBySales=App\DSA\Algorithms::bubbleSort($daily,fn($a,$b)=>(float)$b['net_sales']<=>(float)$a['net_sales']);
// DSA Algorithm #5: selection sort the audit ledger by the most recent event date and time first.
$ledger=App\DSA\Algorithms::selectionSort($ledger,fn($a,$b)=>(strtotime($b['sale_date']) <=> strtotime($a['sale_date'])) ?: ((int)$b['audit_id']<=> (int)$a['audit_id']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>21 GRIDS — Owner</title>
<style>
body{font-family:'Segoe UI',sans-serif;background:#faf8f5;margin:40px;color:#3f3028}.nav-link{background:#faf8f5;color:#5c3d2e;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;font-size:18px;box-shadow:0 2px 4px rgba(0,0,0,.1)}.nav-link:hover{background:#eee}.analytics-flex{display:flex;gap:30px}.account-manager-card{width:118px;height:118px;border-radius:50%;background:white;border:3px solid #ebdcb9;display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;color:#5c3d2e;font-weight:bold;cursor:pointer;box-shadow:0 5px 14px rgba(0,0,0,.08);transition:transform .2s,box-shadow .2s}.account-manager-card:hover{transform:scale(1.05) translateY(-2px);box-shadow:0 10px 22px rgba(0,0,0,.14)}.account-manager-card .icon{font-size:32px;line-height:1}.account-manager-card .label{font-size:13px;margin-top:6px}.account-modal-box{position:relative;width:min(980px,94vw);max-height:86vh;overflow:auto;background:#faf8f5;border:3px solid #ebdcb9;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(0,0,0,.28);transform:translateY(18px) scale(.98);animation:modalPop .22s ease forwards}.account-form{background:white;border:2px solid #ebdcb9;border-radius:12px;padding:18px;margin-bottom:20px}.account-form-grid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:10px;align-items:end}.account-form label{display:block;font-weight:bold;color:#5c3d2e;font-size:13px}.account-form input,.account-form select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccc;border-radius:7px;margin-top:5px}.account-table th,.account-table td{font-size:15px;padding:12px}.account-actions{display:flex;gap:6px;flex-wrap:wrap}.account-actions form{margin:0}.account-actions button{border:none;border-radius:7px;padding:8px 10px;font-weight:bold;cursor:pointer}.btn-role{background:#f5eedc;color:#5c3d2e}.btn-remove{background:#ffcdd2;color:#b71c1c}.btn-restore{background:#c8e6c9;color:#2e7d32}.stat-card{background:white;padding:30px;border:3px solid #ebdcb9;border-radius:16px;flex:1;text-align:center;text-decoration:none;color:inherit;cursor:pointer}.stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,.08)}.stat-card h3{color:#5c3d2e}.stat-card p{font-size:34px;font-weight:bold;color:#2e7d32}.section-title{color:#5c3d2e;font-size:26px;margin:50px 0 5px}table{width:100%;border-collapse:collapse;margin-top:20px;background:white;border-radius:12px;overflow:hidden;box-shadow:0 8px 16px rgba(0,0,0,.02)}th,td{border:2px solid #ebdcb9;padding:16px;text-align:left;font-size:17px}th{background:#f5eedc;color:#5c3d2e}.voided{background:#fff5f5;color:#b71c1c}.small{color:#777;font-size:15px}.badge{padding:6px 12px;border-radius:6px;font-weight:bold;font-size:14px}.completed{background:#c8e6c9;color:#2e7d32}.void{background:#ffcdd2;color:#b71c1c}.filter-box{background:white;border:3px solid #ebdcb9;border-radius:16px;padding:20px;margin-top:25px}.filter-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}.filter-grid label{font-weight:bold;color:#5c3d2e;font-size:14px}.filter-grid input,.filter-grid select{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ccc;border-radius:7px;margin-top:5px}.filter-actions{margin-top:15px;display:flex;gap:10px}.filter-btn{background:#5c3d2e;color:white;border:none;border-radius:8px;padding:12px 18px;font-weight:bold;cursor:pointer;text-decoration:none}.clear-btn{background:#eee;color:#5c3d2e}.selected-view{background:#fff8e1;border-left:6px solid #865d43;padding:12px;margin-top:15px;font-weight:bold}.modal-overlay{position:fixed;inset:0;background:rgba(38,25,18,.58);display:flex;align-items:center;justify-content:center;padding:28px;z-index:999;opacity:0;animation:modalFade .22s ease forwards}.modal-box{position:relative;width:min(1180px,96vw);max-height:86vh;overflow:auto;background:#faf8f5;border:3px solid #ebdcb9;border-radius:18px;padding:26px;box-shadow:0 18px 50px rgba(0,0,0,.28);transform:translateY(18px) scale(.98);animation:modalPop .22s ease forwards}.modal-box h2{margin-top:0;color:#5c3d2e}.modal-table{margin-top:16px;min-width:980px}.modal-close{position:absolute;top:16px;right:18px;display:inline-block;background:#5c3d2e;color:white;text-decoration:none;border-radius:8px;padding:9px 16px;font-weight:bold;z-index:2}.modal-close:hover{background:#3f3028}@keyframes modalFade{to{opacity:1}}@keyframes modalPop{to{transform:translateY(0) scale(1)}}.daily-table{margin-top:18px}.daily-table td,.daily-table th{text-align:center}.daily-table td:first-child,.daily-table th:first-child{text-align:left}@media(max-width:1000px){.filter-grid{grid-template-columns:1fr 1fr}.analytics-flex{flex-direction:column}.modal-overlay{padding:12px}.modal-box{max-height:90vh;padding:18px}}
</style>
</head>
<body>
<div style="background:#5c3d2e;padding:15px 30px;margin:-40px -40px 10px -40px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 8px rgba(0,0,0,.05);"><h2 style="color:white;margin:0;font-size:26px;">📊 21 GRIDS — Store Owner Monitoring Dashboard</h2><a href="logout.php" class="nav-link">⬅️ Logout</a></div>
<div style="display:flex;justify-content:flex-end;margin:0 0 18px 0;">
<a class="account-manager-card" href="?manage_accounts=1#accountManager" aria-label="Manage accounts">
<span class="icon">👤</span>
<span class="label">Manage Account</span>
</a>
</div>
<div class="analytics-flex">
<a class="stat-card" href="?card_view=active_revenue&<?=$filterQuery?>#cardRecords"><h3>Net Active Revenue</h3><p>₱<?=number_format($summary['revenue'],2)?></p></a>
<a class="stat-card" href="?card_view=invoice_volume&<?=$filterQuery?>#cardRecords"><h3>Net Audited Invoice Volume</h3><p><?=$summary['orders']?> Receipts</p></a>
<a class="stat-card" href="?card_view=voided_receipts&<?=$filterQuery?>#cardRecords"><h3>Voided Receipts</h3><p><?=$summary['voided']?></p></a>
</div>
<?php if($accountMessage): ?><div class="notice" style="margin-top:18px;">✅ <?=e($accountMessage)?></div><?php endif; ?>
<?php if($accountError): ?><div class="error" style="margin-top:18px;">❌ <?=e($accountError)?></div><?php endif; ?>
<div class="filter-box">
<h3 style="margin-top:0;color:#5c3d2e;">🔎 Transaction Filters</h3>
<form method="GET">
<div class="filter-grid">
<div><label>Date From</label><input type="date" name="date_from" value="<?=e($dateFrom)?>"></div>
<div><label>Date To</label><input type="date" name="date_to" value="<?=e($dateTo)?>"></div>
<div><label>Cashier</label><select name="cashier_id"><option value="">All Cashiers</option><?php foreach($cashiers as $cashier): ?><option value="<?=e($cashier['user_id'])?>" <?=$cashierId==(int)$cashier['user_id']?'selected':''?>><?=e($cashier['full_name'])?></option><?php endforeach; ?></select></div>
<div><label>Payment</label><select name="payment_method"><option value="">All Payments</option><option value="Cash" <?=$paymentMethod==='Cash'?'selected':''?>>Cash</option><option value="Online Payment/Bank transfer" <?=$paymentMethod==='Online Payment/Bank transfer'?'selected':''?>>Online Payment/Bank transfer</option><option value="Card" <?=$paymentMethod==='Card'?'selected':''?>>Card</option></select></div>
<div><label>Status</label><select name="status"><option value="">All Statuses</option><option value="Completed" <?=$status==='Completed'?'selected':''?>>Completed</option><option value="Voided" <?=$status==='Voided'?'selected':''?>>Voided</option></select></div>
</div>
<div class="filter-actions"><button class="filter-btn" type="submit">Apply Filters</button><a class="filter-btn clear-btn" href="owner.php#records">Clear Filters</a></div>
</form>
</div>
<?php if($manageAccounts): ?>
<!-- Owner account manager: this floating card controls admin and cashier login access and positions. -->
<div class="modal-overlay" id="accountManager" style="display:flex;">
<div class="account-modal-box">
<a class="modal-close" href="owner.php#records">Close</a>
<h2 style="color:#5c3d2e;margin-top:0;">👤 Manage Accounts</h2>
<p class="small">The owner can create, deactivate, restore, and transfer Admin/Cashier positions.</p>
<div class="account-form">
<h3 style="margin-top:0;color:#5c3d2e;">Add Account</h3>
<form method="POST">
<input type="hidden" name="account_action" value="create">
<div class="account-form-grid">
<div><label>Username</label><input type="text" name="username" required></div>
<div><label>Full Name</label><input type="text" name="full_name" required></div>
<div><label>Position</label><select name="role" required><option value="cashier">Cashier</option><option value="admin">Admin</option></select></div>
<div><label>Password</label><input type="password" name="password" required minlength="4"></div>
<div><button class="filter-btn" type="submit">Add Account</button></div>
</div>
</form>
</div>
<table class="account-table"><thead><tr><th>Username</th><th>Name</th><th>Position</th><th>Login Access</th><th>Actions</th></tr></thead><tbody>
<?php foreach($accounts as $account): ?>
<tr>
<td><?=e($account['username'])?></td>
<td><?=e($account['full_name'])?></td>
<td><?=e(ucfirst($account['role']))?></td>
<td><?=((int)$account['is_active']===1)?'Active':'Removed'?></td>
<td>
<div class="account-actions">
<?php if($account['role']!=='owner'): ?>
<form method="POST"><input type="hidden" name="account_action" value="change_role"><input type="hidden" name="user_id" value="<?=e($account['user_id'])?>"><select name="role" onchange="this.form.submit()" class="btn-role" aria-label="Transfer position"><option value="cashier" <?=$account['role']==='cashier'?'selected':''?>>Cashier</option><option value="admin" <?=$account['role']==='admin'?'selected':''?>>Admin</option></select></form>
<?php if((int)$account['is_active']===1): ?>
<form method="POST" onsubmit="return confirm('Remove this account from login access?');"><input type="hidden" name="account_action" value="deactivate"><input type="hidden" name="user_id" value="<?=e($account['user_id'])?>"><button class="btn-remove" type="submit">Remove</button></form>
<?php else: ?>
<form method="POST"><input type="hidden" name="account_action" value="activate"><input type="hidden" name="user_id" value="<?=e($account['user_id'])?>"><button class="btn-restore" type="submit">Restore</button></form>
<?php endif; ?>
<?php else: ?>
<span class="small">Current Owner</span>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
</div>
<?php endif; ?>

<?php if($cardView): ?>
<!-- Floating summary-card modal keeps each KPI's records in its own independent table. -->
<div class="modal-overlay" id="cardRecords" style="display:flex;">
<div class="modal-box">
<?php if($cardView==='active_revenue'): ?>
<!-- Net Active Revenue shows only completed sales contributing to active revenue. -->
<h2>💰 Net Active Revenue Records</h2>
<p class="small">Completed transactions contributing to active revenue.</p>
<?php $cardRecords=$activeRevenueRecords; ?>
<?php elseif($cardView==='invoice_volume'): ?>
<!-- Net Audited Invoice Volume shows only completed invoices. -->
<h2>🧾 Net Audited Invoice Volume Records</h2>
<p class="small">Completed invoices included in the audited invoice count.</p>
<?php $cardRecords=$invoiceVolumeRecords; ?>
<?php else: ?>
<!-- Voided Receipts shows only voided sales retained for documentation. -->
<h2>🚫 Voided Receipt Records</h2>
<p class="small">Voided transactions retained for documentation and excluded from active revenue.</p>
<?php $cardRecords=$voidedReceiptRecords; ?>
<?php endif; ?>
<?php if(empty($cardRecords)): ?>
<p class="small">No records match the selected filters.</p>
<?php else: ?>
<table class="modal-table"><thead><tr><th>Transaction ID</th><th>Date &amp; Time</th><th>Cashier</th><th>Items</th><th>Subtotal</th><th>Discount</th><th>Total</th><th>Payment</th><th>Status</th><th>Void Details</th></tr></thead><tbody>
<?php foreach($cardRecords as $tx): ?>
<tr class="<?=$tx['status']==='Voided'?'voided':''?>"><td><strong><?=e($tx['transaction_id'])?></strong></td><td><?=e($tx['sale_date'])?></td><td><?=e($tx['cashier']??'—')?></td><td><?=e($tx['items_list']??'—')?></td><td>₱<?=number_format((float)($tx['subtotal_amount']??0),2)?></td><td><?=e($tx['discount_type']??'None')?> — ₱<?=number_format((float)($tx['discount_amount']??0),2)?></td><td><strong>₱<?=number_format((float)$tx['total_amount'],2)?></strong></td><td><?=e($tx['payment_method']??'—')?></td><td><span class="badge <?=$tx['status']==='Voided'?'void':'completed'?>"><?=e($tx['status'])?></span></td><td><?= $tx['status']==='Voided' ? e(($tx['void_reason']??'No reason recorded').' | Verified by: '.($tx['voided_by_name']??'Unknown')) : '—' ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<a class="modal-close" href="owner.php<?=$filterQuery?'?'.$filterQuery:''?>#records">Close</a>
</div>
</div>
<?php endif; ?>
<h3 class="section-title">📅 Daily Sales Summary</h3>
<?php if(empty($daily)): ?><p class="small">No daily sales records exist for the selected date filters.</p><?php else: ?>
<table class="daily-table"><thead><tr><th>Date</th><th>Completed Orders</th><th>Net Active Sales</th><th>Voided Orders</th><th>Voided Amount</th></tr></thead><tbody>
<?php foreach($dailyBySales as $day): ?>
<tr><td><?=e($day['sale_date'])?></td><td><?=e($day['completed_count'])?></td><td><strong>₱<?=number_format((float)$day['net_sales'],2)?></strong></td><td><?=e($day['voided_count'])?></td><td>₱<?=number_format((float)$day['voided_amount'],2)?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<h3 class="section-title" id="records">📋 Live System Master Audit Ledger</h3>
<?php if(empty($ledger)): ?><p class="small">No transaction history exists for the selected filters.</p><?php else: ?>
<table><thead><tr><th>Transaction ID</th><th>Date &amp; Time</th><th>Cashier</th><th>Items Sold</th><th>Subtotal</th><th>Discount</th><th>Invoice Value</th><th>Payment</th><th>Status</th><th>Void Details</th></tr></thead><tbody>
<?php foreach($ledger as $tx): ?><tr class="<?=$tx['status']==='Voided'?'voided':''?>"><td><strong><?=e($tx['transaction_id'])?></strong></td><td><?=e($tx['sale_date'])?></td><td><?=e($tx['cashier']??'—')?></td><td><?=e($tx['items_list']??'—')?></td><td>₱<?=number_format((float)($tx['subtotal_amount']??0),2)?></td><td><?=e($tx['discount_type']??'None')?> — ₱<?=number_format((float)($tx['discount_amount']??0),2)?></td><td style="font-weight:bold;">₱<?=number_format($tx['total_amount'],2)?></td><td><?=e($tx['payment_method']??'—')?></td><td><span class="badge <?=$tx['status']==='Voided'?'void':'completed'?>"><?=e($tx['status'])?></span></td><td><?= $tx['status']==='Voided' ? e(($tx['void_reason']??'No reason recorded').' | Verified by: '.($tx['voided_by_name']??'Unknown')) : '—' ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</body>
</html>
