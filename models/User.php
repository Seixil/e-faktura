<?php
/**
 * Model pre prácu s používateľmi v databáze
 */

class User {
    private $db;
    public $id;
    public $email;
    public $firstName;
    public $lastName;
    public $companyName;
    public $companyIco;
    public $companyDic;
    public $address;
    public $phone;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Registrácia nového používateľa
     */
    public function register($email, $password, $firstName, $lastName) {
        // Validácia
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Heslo musí mať minimálne ' . PASSWORD_MIN_LENGTH . ' znakov');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Neplatná e-mailová adresa');
        }

        // Kontrola, či e-mail už existuje
        $existing = $this->db->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            throw new Exception('Používateľ s týmto e-mailom už existuje');
        }

        // Hešovanie hesla
        $passwordHash = password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);

        // Uloženie do databázy
        try {
            $this->db->execute(
                'INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)',
                [$email, $passwordHash, $firstName, $lastName]
            );
            return true;
        } catch (Exception $e) {
            throw new Exception('Chyba pri registrácii: ' . $e->getMessage());
        }
    }

    /**
     * Prihlásenie používateľa
     */
    public function login($email, $password) {
        // Vyhľadanie používateľa
        $user = $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        
        if (!$user) {
            throw new Exception('Nesprávne meno alebo heslo');
        }

        // Overenie hesla
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Nesprávne meno alebo heslo');
        }

        // Nastavenie session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_company'] = $user['company_name'];

        return true;
    }

    /**
     * Získanie údajov používateľa podľa ID
     */
    public function getById($userId) {
        return $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
    }

    /**
     * Aktualizácia profilu používateľa
     */
    public function updateProfile($userId, $data) {
        $allowedFields = ['first_name', 'last_name', 'company_name', 'company_ico', 'company_dic', 'address', 'phone'];
        
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "`$field` = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $userId;
        
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $this->db->execute($sql, $params);
        
        return true;
    }

    /**
     * Zmena hesla
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Nové heslo musí mať minimálne ' . PASSWORD_MIN_LENGTH . ' znakov');
        }

        $user = $this->getById($userId);
        if (!$user) {
            throw new Exception('Používateľ nenájdený');
        }

        if (!password_verify($oldPassword, $user['password_hash'])) {
            throw new Exception('Staré heslo je nesprávne');
        }

        $newHash = password_hash($newPassword, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
        $this->db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $userId]);
        
        return true;
    }
}
