USE waffle_sip_pos;

-- Show completed sales by date.
SELECT sale_id, sale_date, cashier_id, total_amount, discount_type, discount_amount
FROM sales
WHERE status = 'Completed'
ORDER BY sale_date DESC;

-- Show all audit events, including the original Completed event and later Voided event.
SELECT a.audit_id, a.sale_id, a.event_time, a.event_status, a.void_reason, s.cashier_id
FROM sale_audit_log a
JOIN sales s ON s.sale_id = a.sale_id
ORDER BY a.event_time DESC, a.audit_id DESC;

-- Show payments by method.
SELECT method, COUNT(*) AS transaction_count, SUM(amount) AS total_amount
FROM payments
GROUP BY method
ORDER BY total_amount DESC;

-- Show the M:N Sale-Product relationship through sale_items.
SELECT si.sale_id, p.name AS product_name, si.quantity, si.unit_price,
       (si.quantity * si.unit_price) AS line_total
FROM sale_items si
JOIN products p ON p.product_id = si.product_id
ORDER BY si.sale_id DESC, p.name;

-- Check for completed sales without a payment.
SELECT s.sale_id, s.sale_date, s.total_amount
FROM sales s
LEFT JOIN payments p ON p.sale_id = s.sale_id
WHERE s.status = 'Completed' AND p.payment_id IS NULL;

-- Check that each sale has at most one payment.
SELECT sale_id, COUNT(*) AS payment_count
FROM payments
GROUP BY sale_id
HAVING COUNT(*) > 1;