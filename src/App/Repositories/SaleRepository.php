<?php

namespace App\Repositories;

use PDO;

class SaleRepository {
    public function __construct(private PDO $db) {}

    public function summary(): array {
        return [
            'revenue' => (float)($this->db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE status='Completed'")->fetchColumn() ?: 0),
        
            'orders' => (int)$this->db->query("SELECT COUNT(*) FROM sales WHERE status='Completed'")->fetchColumn(),
            
            'voided' => (int)$this->db->query("SELECT COUNT(*) FROM sales WHERE status='Voided'")->fetchColumn(),
        ];
    }


    public function dailySales(?string $dateFrom = null, ?string $dateTo = null): array {
        
        $sql = "SELECT DATE(sale_date) sale_date,
                       SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) completed_count,
                       SUM(CASE WHEN status='Completed' THEN total_amount ELSE 0 END) net_sales,
                       SUM(CASE WHEN status='Voided' THEN 1 ELSE 0 END) voided_count,
                       SUM(CASE WHEN status='Voided' THEN total_amount ELSE 0 END) voided_amount
                FROM sales";
        
        $params = [];
        // Add a starting date condition only when the owner selected one.
        if ($dateFrom !== null && $dateFrom !== '') {
            $sql .= " WHERE DATE(sale_date) >= ?";
            $params[] = $dateFrom;
        }
        // Add an ending date condition while preserving a possible starting date.
        if ($dateTo !== null && $dateTo !== '') {
            $sql .= empty($params) ? " WHERE DATE(sale_date) <= ?" : " AND DATE(sale_date) <= ?";
            $params[] = $dateTo;
        }
        // Group the report by calendar date and show newest dates first.
        $sql .= " GROUP BY DATE(sale_date) ORDER BY sale_date DESC";
        // Prepare the report so date values are safely bound.
        $stmt = $this->db->prepare($sql);
        // Execute the report with the selected date parameters.
        $stmt->execute($params);
        // Return all daily report rows.
        return $stmt->fetchAll();
    }

    // Return the complete live master audit ledger, including every completed and voided event.
    public function ledger(array $filters = []): array {
        // Read audit events instead of only the current sales status so the original completed event survives a later void.
        $sql = "SELECT a.audit_id, a.sale_id, CONCAT(YEAR(s.sale_date), '-', DATE_FORMAT(s.sale_date, '%m%d'), '-', (SELECT COUNT(*) FROM sales s2 WHERE YEAR(s2.sale_date)=YEAR(s.sale_date) AND MONTH(s2.sale_date)=MONTH(s.sale_date) AND s2.sale_id <= s.sale_id)) AS transaction_id, a.event_time AS sale_date, a.event_status AS status, a.voided_at, a.void_reason, s.total_amount, s.discount_type, s.discount_amount, p.method AS payment_method, u.full_name AS cashier, CONCAT(vu.full_name, ' (', UPPER(vu.role), ')') AS voided_by_name, COALESCE(SUM(si.quantity * si.unit_price),0) AS subtotal_amount, COALESCE(GROUP_CONCAT(CONCAT(si.quantity,'x ',pr.name) ORDER BY pr.name SEPARATOR ', '),'—') AS items_list
                FROM sale_audit_log a
                INNER JOIN sales s ON s.sale_id=a.sale_id
                LEFT JOIN sale_items si ON si.sale_id=s.sale_id
                LEFT JOIN products pr ON pr.product_id=si.product_id
                LEFT JOIN payments p ON p.sale_id=s.sale_id
                LEFT JOIN users u ON u.user_id=s.cashier_id
                LEFT JOIN users vu ON vu.user_id=s.voided_by";
        // Start the filter list empty so every owner-selected filter can be applied to the audit event.
        $where = [];
        // Start the parameter list empty for prepared-statement values.
        $params = [];
        // Filter by the audit event's date and time rather than only the sale's current status.
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(a.event_time) >= ?";
            $params[] = $filters['date_from'];
        }
        // Filter by the audit event's ending date.
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(a.event_time) <= ?";
            $params[] = $filters['date_to'];
        }
        // Filter by the cashier attached to the original sale.
        if (!empty($filters['cashier_id'])) {
            $where[] = "s.cashier_id = ?";
            $params[] = (int)$filters['cashier_id'];
        }
        // Filter by payment method when selected.
        if (!empty($filters['payment_method'])) {
            $where[] = "p.method = ?";
            $params[] = $filters['payment_method'];
        }
        // Filter by audit-event status when selected.
        if (!empty($filters['status'])) {
            $where[] = "a.event_status = ?";
            $params[] = $filters['status'];
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " GROUP BY a.audit_id, a.sale_id, a.event_time, a.event_status, a.voided_at, a.void_reason, s.total_amount, s.discount_type, s.discount_amount, p.method, u.full_name, vu.full_name, vu.role ORDER BY a.event_time DESC, a.audit_id DESC";
        // Prepare the audit-ledger query.
        $stmt = $this->db->prepare($sql);
        // Execute the query with the selected owner filters.
        $stmt->execute($params);
        // Return every audit event, including the original completed event of a later-voided sale.
        return $stmt->fetchAll();
    }

    // Return completed sales for the Net Active Revenue floating table.
    public function activeRevenueRecords(array $filters = []): array {
        // Query only sales whose current status is Completed so voided sales do not contribute to active revenue.
        return $this->transactionRecords('Completed', $filters);
    }

    // Return completed sales for the Net Audited Invoice Volume floating table.
    public function invoiceVolumeRecords(array $filters = []): array {
        // The audited invoice volume uses the same completed-sale population but is kept as a separate repository method for the separate owner table.
        return $this->transactionRecords('Completed', $filters);
    }

    // Return voided sales for the Voided Receipts floating table.
    public function voidedReceiptRecords(array $filters = []): array {
        // Query only sales whose current status is Voided so the voided-receipt table contains only voided transactions.
        return $this->transactionRecords('Voided', $filters);
    }

    // Build the common transaction query used by the three independent owner KPI tables.
    private function transactionRecords(string $status, array $filters = []): array {
        // Select the sale, cashier, payment, and item information needed by the owner tables.
        $sql = "SELECT s.sale_id, CONCAT(YEAR(s.sale_date), '-', DATE_FORMAT(s.sale_date, '%m%d'), '-', (SELECT COUNT(*) FROM sales s2 WHERE YEAR(s2.sale_date)=YEAR(s.sale_date) AND MONTH(s2.sale_date)=MONTH(s.sale_date) AND s2.sale_id <= s.sale_id)) AS transaction_id, s.sale_date, s.status, s.total_amount, s.discount_type, s.discount_amount, p.method AS payment_method, u.full_name AS cashier, CONCAT(vu.full_name, ' (', UPPER(vu.role), ')') AS voided_by_name, COALESCE(SUM(si.quantity * si.unit_price),0) AS subtotal_amount, COALESCE(GROUP_CONCAT(CONCAT(si.quantity,'x ',pr.name) ORDER BY pr.name SEPARATOR ', '),'—') AS items_list
                FROM sales s
                LEFT JOIN sale_items si ON si.sale_id=s.sale_id
                LEFT JOIN products pr ON pr.product_id=si.product_id
                LEFT JOIN payments p ON p.sale_id=s.sale_id
                LEFT JOIN users u ON u.user_id=s.cashier_id
                LEFT JOIN users vu ON vu.user_id=s.voided_by";
        // Keep the selected filter conditions separate from the SQL text.
        $where = ["s.status = ?"];
        // Start the prepared-statement values with the requested status.
        $params = [$status];
        // Apply the selected starting date to the sale date.
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(s.sale_date) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(s.sale_date) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['cashier_id'])) {
            $where[] = "s.cashier_id = ?";
            $params[] = (int)$filters['cashier_id'];
        }

        if (!empty($filters['payment_method'])) {
            $where[] = "p.method = ?";
            $params[] = $filters['payment_method'];
        }

        $sql .= " WHERE " . implode(" AND ", $where);

        $sql .= " GROUP BY s.sale_id, s.sale_date, s.status, s.total_amount, s.discount_type, s.discount_amount, p.method, u.full_name, vu.full_name ORDER BY s.sale_date DESC, s.sale_id DESC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchAll();
    }


    public function cashiers(): array {

        return $this->db->query("SELECT user_id, full_name FROM users WHERE role='cashier' AND is_active=1 ORDER BY full_name")->fetchAll();
    }
}
