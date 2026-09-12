<?php
/**
 * Model pre prácu s faktúrami v databáze
 */

class Invoice {
    private $db;
    public $id;
    public $userId;
    public $invoiceNumber;
    public $invoiceDate;
    public $dueDate;
    public $supplierName;
    public $supplierIco;
    public $supplierDic;
    public $supplierAddress;
    public $customerName;
    public $customerIco;
    public $customerDic;
    public $customerAddress;
    public $totalAmount;
    public $vatAmount;
    public $currency;
    public $status;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Vytvorenie novej faktúry
     */
    public function create($userId, $data) {
        try {
            $sql = 'INSERT INTO invoices (
                user_id, invoice_number, invoice_date, due_date,
                supplier_name, supplier_ico, supplier_dic, supplier_address,
                customer_name, customer_ico, customer_dic, customer_address,
                total_amount, vat_amount, currency, description, xml_data, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $this->db->execute($sql, [
                $userId,
                $data['invoiceNumber'] ?? '',
                $data['invoiceDate'] ?? date('Y-m-d'),
                $data['dueDate'] ?? null,
                $data['supplierName'] ?? '',
                $data['supplierIco'] ?? '',
                $data['supplierDic'] ?? '',
                $data['supplierAddress'] ?? '',
                $data['customerName'] ?? '',
                $data['customerIco'] ?? '',
                $data['customerDic'] ?? '',
                $data['customerAddress'] ?? '',
                $data['totalAmount'] ?? 0,
                $data['vatAmount'] ?? 0,
                $data['currency'] ?? 'EUR',
                $data['description'] ?? '',
                $data['xmlData'] ?? null,
                $data['status'] ?? 'draft'
            ]);

            return $this->db->lastInsertId();
        } catch (Exception $e) {
            throw new Exception('Chyba pri vytváraní faktúry: ' . $e->getMessage());
        }
    }

    /**
     * Získanie faktúry podľa ID
     */
    public function getById($invoiceId, $userId = null) {
        $sql = 'SELECT * FROM invoices WHERE id = ?';
        $params = [$invoiceId];

        if ($userId) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }

        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Získanie všetkých faktúr používateľa
     */
    public function getByUserId($userId, $limit = 50, $offset = 0) {
        $sql = 'SELECT * FROM invoices WHERE user_id = ? ORDER BY invoice_date DESC, created_at DESC LIMIT ? OFFSET ?';
        return $this->db->fetchAll($sql, [$userId, $limit, $offset]);
    }

    /**
     * Hľadanie faktúr podľa kritérií
     */
    public function search($userId, $criteria = []) {
        $sql = 'SELECT * FROM invoices WHERE user_id = ?';
        $params = [$userId];

        if (!empty($criteria['invoiceNumber'])) {
            $sql .= ' AND invoice_number LIKE ?';
            $params[] = '%' . $criteria['invoiceNumber'] . '%';
        }

        if (!empty($criteria['customerName'])) {
            $sql .= ' AND customer_name LIKE ?';
            $params[] = '%' . $criteria['customerName'] . '%';
        }

        if (!empty($criteria['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $criteria['status'];
        }

        if (!empty($criteria['dateFrom'])) {
            $sql .= ' AND invoice_date >= ?';
            $params[] = $criteria['dateFrom'];
        }

        if (!empty($criteria['dateTo'])) {
            $sql .= ' AND invoice_date <= ?';
            $params[] = $criteria['dateTo'];
        }

        $sql .= ' ORDER BY invoice_date DESC LIMIT ?';
        $params[] = $criteria['limit'] ?? 50;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Aktualizácia faktúry
     */
    public function update($invoiceId, $userId, $data) {
        $allowedFields = [
            'invoice_number', 'invoice_date', 'due_date',
            'supplier_name', 'supplier_ico', 'supplier_dic', 'supplier_address',
            'customer_name', 'customer_ico', 'customer_dic', 'customer_address',
            'total_amount', 'vat_amount', 'currency', 'description', 'status'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            $camelCase = $this->snakeToCamel($field);
            if (isset($data[$camelCase])) {
                $updates[] = "`$field` = ?";
                $params[] = $data[$camelCase];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $invoiceId;
        $params[] = $userId;

        $sql = 'UPDATE invoices SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?';
        $this->db->execute($sql, $params);

        return true;
    }

    /**
     * Zmazanie faktúry
     */
    public function delete($invoiceId, $userId) {
        $this->db->execute('DELETE FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);
        return $this->db->rowCount('DELETE FROM invoices WHERE id = ? AND user_id = ?', [$invoiceId, $userId]) > 0;
    }

    /**
     * Zmena stavu faktúry
     */
    public function updateStatus($invoiceId, $userId, $status) {
        $validStatuses = ['draft', 'sent', 'received', 'paid', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Neplatný stav faktúry');
        }

        return $this->db->rowCount(
            'UPDATE invoices SET status = ? WHERE id = ? AND user_id = ?',
            [$status, $invoiceId, $userId]
        ) > 0;
    }

    /**
     * Štatistika faktúr používateľa
     */
    public function getStatistics($userId) {
        $stats = $this->db->fetchOne(
            'SELECT 
                COUNT(*) as total_count,
                SUM(total_amount) as total_amount,
                SUM(vat_amount) as total_vat,
                COUNT(CASE WHEN status = "draft" THEN 1 END) as draft_count,
                COUNT(CASE WHEN status = "sent" THEN 1 END) as sent_count,
                COUNT(CASE WHEN status = "paid" THEN 1 END) as paid_count
            FROM invoices WHERE user_id = ?',
            [$userId]
        );
        return $stats;
    }

    /**
     * Konverzia snake_case na camelCase
     */
    private function snakeToCamel($string) {
        $parts = explode('_', $string);
        $camelCase = $parts[0];
        for ($i = 1; $i < count($parts); $i++) {
            $camelCase .= ucfirst($parts[$i]);
        }
        return $camelCase;
    }
}
