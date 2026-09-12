<?php
/**
 * Kontrolér pre spracovanie faktúr - import XML, zoznam, detail, ediciá
 */

class InvoiceController {
    private $invoice;
    private $xmlParser;
    private $db;
    private $userId;

    public function __construct() {
        AuthController::requireAuth();
        $this->invoice = new Invoice();
        $this->db = Database::getInstance();
        $this->userId = AuthController::getCurrentUserId();
    }

    /**
     * Spracovanie uploadu XML faktúry
     */
    public function handleUpload() {
        $response = [
            'success' => false,
            'message' => '',
            'invoiceId' => null,
            'errors' => []
        ];

        // Kontrola metódy
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $response;
        }

        // Validácia CSRF tokenu
        if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] ?? null) {
            $response['message'] = 'Bezpečnostný token nie je platný';
            return $response;
        }

        // Kontrola, či bol súbor nahraný
        if (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
            $response['errors']['xml_file'] = 'Chyba pri nahraní súboru';
            return $response;
        }

        $file = $_FILES['xml_file'];
        $mimeType = mime_content_type($file['tmp_name']);
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

        // Validácia typu súboru
        if ($fileExtension !== 'xml' && $mimeType !== 'application/xml' && $mimeType !== 'text/xml') {
            $response['errors']['xml_file'] = 'Súbor musí byť v formáte XML';
            return $response;
        }

        // Validácia veľkosti súboru (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $response['errors']['xml_file'] = 'Súbor je príliš veľký (maximum 5MB)';
            return $response;
        }

        if (!empty($response['errors'])) {
            return $response;
        }

        // Uschovatá súbor
        $fileName = 'invoice_' . $this->userId . '_' . time() . '.xml';
        $filePath = UPLOADS_DIR . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $response['message'] = 'Chyba pri uschovaniu súboru';
            return $response;
        }

        // Parsovanie XML
        try {
            $this->xmlParser = new XMLParser($filePath);

            // Validácia XML
            if (!$this->xmlParser->validate()) {
                $response['message'] = 'XML súbor obsahuje neplatné údaje';
                $response['errors']['xml_content'] = $this->xmlParser->getErrors();
                unlink($filePath);
                return $response;
            }

            // Extrahovanie údajov
            $parsedData = $this->xmlParser->parseComplete();

            // Vytvorenie faktúry
            $invoiceData = [
                'invoiceNumber' => $parsedData['basic']['invoiceNumber'],
                'invoiceDate' => $parsedData['basic']['invoiceDate'],
                'dueDate' => $parsedData['basic']['dueDate'],
                'supplierName' => $parsedData['supplier']['name'],
                'supplierIco' => $parsedData['supplier']['ico'],
                'supplierDic' => $parsedData['supplier']['dic'],
                'supplierAddress' => $parsedData['supplier']['address'],
                'customerName' => $parsedData['customer']['name'],
                'customerIco' => $parsedData['customer']['ico'],
                'customerDic' => $parsedData['customer']['dic'],
                'customerAddress' => $parsedData['customer']['address'],
                'totalAmount' => $parsedData['totals']['totalAmount'],
                'vatAmount' => $parsedData['totals']['vatAmount'],
                'currency' => $parsedData['basic']['currency'],
                'description' => $parsedData['basic']['description'],
                'xmlData' => $parsedData['xmlRaw'],
                'status' => 'draft'
            ];

            // Uloseň faktúru
            $invoiceId = $this->invoice->create($this->userId, $invoiceData);

            // Uloženie položiek
            if (!empty($parsedData['items'])) {
                $this->saveInvoiceItems($invoiceId, $parsedData['items']);
            }

            $response['success'] = true;
            $response['invoiceId'] = $invoiceId;
            $response['message'] = 'Faktúra bola úspešne naimportovaná';

            // Log
            $this->logAction('invoice_imported', 'invoice', $invoiceId, 'Import XML faktúry: ' . $parsedData['basic']['invoiceNumber']);

        } catch (Exception $e) {
            $response['message'] = 'Chyba pri parsovaní XML: ' . $e->getMessage();
            unlink($filePath);
        }

        return $response;
    }

    /**
     * Získanie zoznamu faktúr používateľa
     */
    public function getInvoicesList($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $invoices = $this->invoice->getByUserId($this->userId, $limit, $offset);
        
        return [
            'invoices' => $invoices,
            'page' => $page,
            'limit' => $limit
        ];
    }

    /**
     * Získanie detailu faktúry
     */
    public function getInvoiceDetail($invoiceId) {
        $invoice = $this->invoice->getById($invoiceId, $this->userId);
        
        if (!$invoice) {
            throw new Exception('Faktúra nenajdená');
        }

        // Získanie položiek
        $items = $this->db->fetchAll(
            'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_order',
            [$invoiceId]
        );

        $invoice['items'] = $items;
        return $invoice;
    }

    /**
     * Aktualizovanie stavu faktúry
     */
    public function updateInvoiceStatus($invoiceId, $status) {
        $success = $this->invoice->updateStatus($invoiceId, $this->userId, $status);
        
        if ($success) {
            $this->logAction('invoice_status_changed', 'invoice', $invoiceId, 'Zmena stavu: ' . $status);
        }

        return $success;
    }

    /**
     * Zmazá faktúru
     */
    public function deleteInvoice($invoiceId) {
        $invoice = $this->invoice->getById($invoiceId, $this->userId);
        
        if (!$invoice) {
            throw new Exception('Faktúra nenajdená');
        }

        $success = $this->invoice->delete($invoiceId, $this->userId);
        
        if ($success) {
            $this->logAction('invoice_deleted', 'invoice', $invoiceId, 'Zmazá faktúra: ' . $invoice['invoice_number']);
        }

        return $success;
    }

    /**
     * Hládanie faktúr
     */
    public function searchInvoices($criteria) {
        return $this->invoice->search($this->userId, $criteria);
    }

    /**
     * Získanie štatistiky faktúr
     */
    public function getStatistics() {
        return $this->invoice->getStatistics($this->userId);
    }

    /**
     * Uloženie položiek faktúry
     */
    private function saveInvoiceItems($invoiceId, $items) {
        foreach ($items as $item) {
            $this->db->execute(
                'INSERT INTO invoice_items (
                    invoice_id, item_order, description, quantity, unit, 
                    unit_price, vat_rate, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $invoiceId,
                    $item['order'],
                    $item['description'],
                    $item['quantity'],
                    $item['unit'],
                    $item['unitPrice'],
                    $item['vatRate'],
                    $item['totalPrice']
                ]
            );
        }
    }

    /**
     * Zaznamenanie akcie do audit logu
     */
    private function logAction($action, $entityType, $entityId, $description) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $this->db->execute(
                'INSERT INTO audit_log (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
                [$this->userId, $action, $entityType, $entityId, $description, $ipAddress]
            );
        } catch (Exception $e) {
            error_log('Failed to log action: ' . $e->getMessage());
        }
    }
}
