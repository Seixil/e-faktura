<?php
/**
 * XML Parser pre spracovanie elektronických faktúr
 * Podporuje štandardné XML formáty e-faktúr
 */

class XMLParser {
    private $xmlContent;
    private $xmlObject;
    private $errors = [];

    /**
     * Kons truktor - načíta XML obsah
     */
    public function __construct($xmlPath) {
        if (!file_exists($xmlPath)) {
            throw new Exception('XML súbor neexistuje: ' . $xmlPath);
        }

        $this->xmlContent = file_get_contents($xmlPath);
        
        // Zabezpečené načítanie XML
        libxml_use_internal_errors(true);
        $this->xmlObject = simplexml_load_string($this->xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        if ($this->xmlObject === false) {
            $this->errors = libxml_get_errors();
            throw new Exception('Chyba pri parsovaní XML: Neplatný XML formát');
        }
        
        libxml_clear_errors();
    }

    /**
     * Extrahovanie základných údajov faktúry
     */
    public function extractInvoiceData() {
        try {
            $data = [
                'invoiceNumber' => $this->getNodeValue('//Invoice/InvoiceNumber', $this->getNodeValue('//InvoiceNumber')),
                'invoiceDate' => $this->parseDate($this->getNodeValue('//Invoice/IssueDate', $this->getNodeValue('//IssueDate'))),
                'dueDate' => $this->parseDate($this->getNodeValue('//Invoice/DueDate', $this->getNodeValue('//DueDate'))),
                'currency' => $this->getNodeValue('//Invoice/DocumentCurrencyCode', 'EUR'),
                'description' => $this->getNodeValue('//Invoice/Note', ''),
            ];

            return $data;
        } catch (Exception $e) {
            throw new Exception('Chyba pri extrakování základných údajov: ' . $e->getMessage());
        }
    }

    /**
     * Extrahovanie údajov dodávateľa (supplier)
     */
    public function extractSupplierData() {
        try {
            $supplier = [
                'name' => $this->getNodeValue('//Invoice/AccountingSupplierParty/Party/PartyName/Name', ''),
                'ico' => $this->getNodeValue('//Invoice/AccountingSupplierParty/Party/PartyTaxScheme/CompanyID', ''),
                'dic' => $this->getNodeValue('//Invoice/AccountingSupplierParty/Party/PartyTaxScheme/CompanyID', ''),
                'address' => $this->extractAddress('//Invoice/AccountingSupplierParty/Party/PostalAddress'),
            ];

            return $supplier;
        } catch (Exception $e) {
            throw new Exception('Chyba pri extrakování údajov dodávateľa: ' . $e->getMessage());
        }
    }

    /**
     * Extrahovanie údajov zákazníka (customer)
     */
    public function extractCustomerData() {
        try {
            $customer = [
                'name' => $this->getNodeValue('//Invoice/AccountingCustomerParty/Party/PartyName/Name', ''),
                'ico' => $this->getNodeValue('//Invoice/AccountingCustomerParty/Party/PartyTaxScheme/CompanyID', ''),
                'dic' => $this->getNodeValue('//Invoice/AccountingCustomerParty/Party/PartyTaxScheme/CompanyID', ''),
                'address' => $this->extractAddress('//Invoice/AccountingCustomerParty/Party/PostalAddress'),
            ];

            return $customer;
        } catch (Exception $e) {
            throw new Exception('Chyba pri extrakování údajov zákazníka: ' . $e->getMessage());
        }
    }

    /**
     * Extrahovanie položiek faktúry
     */
    public function extractInvoiceLines() {
        try {
            $lines = [];
            $lineItems = $this->xmlObject->xpath('//Invoice/InvoiceLine');

            if (!$lineItems) {
                $lineItems = $this->xmlObject->xpath('//InvoiceLine');
            }

            if (empty($lineItems)) {
                return $lines;
            }

            $order = 1;
            foreach ($lineItems as $line) {
                $item = [
                    'order' => $order,
                    'description' => (string)($line->Item->Description ?? $line->Description ?? ''),
                    'quantity' => floatval($line->InvoicedQuantity ?? $line->Quantity ?? 0),
                    'unit' => (string)($line->InvoicedQuantity['unitCode'] ?? $line->Quantity['unitCode'] ?? 'KS'),
                    'unitPrice' => floatval($line->Price->PriceAmount ?? $line->UnitPrice ?? 0),
                    'vatRate' => floatval($line->Item->ClassifiedTaxCategory->Percent ?? 20),
                    'totalPrice' => floatval($line->LineExtensionAmount ?? $line->TotalPrice ?? 0),
                ];
                $lines[] = $item;
                $order++;
            }

            return $lines;
        } catch (Exception $e) {
            throw new Exception('Chyba pri extrakování položiek faktúry: ' . $e->getMessage());
        }
    }

    /**
     * Extrahovanie finálnych ímátok faktúry
     */
    public function extractTotals() {
        try {
            $totals = [
                'netAmount' => floatval($this->getNodeValue('//Invoice/LegalMonetaryTotal/LineExtensionTotalAmount', 0)),
                'taxAmount' => floatval($this->getNodeValue('//Invoice/LegalMonetaryTotal/TaxExclusiveAmount', 0)),
                'vatAmount' => floatval($this->getNodeValue('//Invoice/TaxTotal/TaxAmount', 0)),
                'totalAmount' => floatval($this->getNodeValue('//Invoice/LegalMonetaryTotal/PayableAmount', 0)),
            ];

            return $totals;
        } catch (Exception $e) {
            throw new Exception('Chyba pri extrakování finálnych íemov: ' . $e->getMessage());
        }
    }

    /**
     * Kompletne spracovanie XML a návrat všetkých dát
     */
    public function parseComplete() {
        try {
            $invoice = [
                'basic' => $this->extractInvoiceData(),
                'supplier' => $this->extractSupplierData(),
                'customer' => $this->extractCustomerData(),
                'items' => $this->extractInvoiceLines(),
                'totals' => $this->extractTotals(),
                'xmlRaw' => $this->xmlContent,
            ];

            return $invoice;
        } catch (Exception $e) {
            throw new Exception('Chyba pri komplexnom parsovaní XML: ' . $e->getMessage());
        }
    }

    /**
     * Extrahovanie adresy z XML
     */
    private function extractAddress($xpathPrefix) {
        $address = [
            'street' => $this->getNodeValue($xpathPrefix . '/StreetName', ''),
            'buildingNumber' => $this->getNodeValue($xpathPrefix . '/BuildingNumber', ''),
            'city' => $this->getNodeValue($xpathPrefix . '/CityName', ''),
            'postalCode' => $this->getNodeValue($xpathPrefix . '/PostalZone', ''),
            'country' => $this->getNodeValue($xpathPrefix . '/Country/IdentificationCode', ''),
        ];

        return trim(
            implode(' ', [
                $address['street'],
                $address['buildingNumber'],
                $address['postalCode'],
                $address['city'],
                $address['country']
            ])
        ) ?: '';
    }

    /**
     * Získanie hodnoty uzla z XML
     */
    private function getNodeValue($xpath, $default = '') {
        $result = $this->xmlObject->xpath($xpath);
        
        if (!empty($result) && isset($result[0])) {
            return (string)$result[0];
        }

        return $default;
    }

    /**
     * Parsovanie dátumu do formátu YYYY-MM-DD
     */
    private function parseDate($dateString) {
        if (empty($dateString)) {
            return null;
        }

        try {
            $date = new DateTime($dateString);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Validácia XML
     */
    public function validate() {
        if (!$this->xmlObject) {
            return false;
        }

        // Kontrolá, či XML obsahuje požadované prvky
        $requiredElements = [
            '//Invoice/InvoiceNumber' => 'InvoiceNumber',
            '//Invoice/IssueDate' => 'IssueDate',
            '//Invoice/AccountingSupplierParty' => 'Supplier info',
            '//Invoice/AccountingCustomerParty' => 'Customer info',
        ];

        foreach ($requiredElements as $xpath => $name) {
            $result = $this->xmlObject->xpath($xpath);
            if (empty($result)) {
                $this->errors[] = 'Chýbajú požadované polia: ' . $name;
            }
        }

        return empty($this->errors);
    }

    /**
     * Návrat chyb
     */
    public function getErrors() {
        return $this->errors;
    }
}
