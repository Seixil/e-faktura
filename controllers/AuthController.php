<?php
/**
 * Kontrolér pre spracovanie autentifikácie (prihlásenie, registrácia, odhlásenie)
 */

class AuthController {
    private $user;
    private $db;

    public function __construct() {
        $this->user = new User();
        $this->db = Database::getInstance();
    }

    /**
     * Spracovanie registrácie
     */
    public function handleRegister() {
        $response = [
            'success' => false,
            'message' => '',
            'errors' => []
        ];

        // Kontrola metódy
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $response;
        }

        // Validácia CSRF tokenu (základná ochrana)
        if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] ?? null) {
            $response['message'] = 'Bezpečnostný token nie je platný';
            return $response;
        }

        // Zbieranie vstupov
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        // Validácia vstupov
        if (empty($email)) {
            $response['errors']['email'] = 'E-mail je povinný';
        }

        if (empty($firstName)) {
            $response['errors']['first_name'] = 'Meno je povinné';
        }

        if (empty($lastName)) {
            $response['errors']['last_name'] = 'Priezvisko je povinné';
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $response['errors']['password'] = 'Heslo musí mať minimálne ' . PASSWORD_MIN_LENGTH . ' znakov';
        }

        if ($password !== $passwordConfirm) {
            $response['errors']['password_confirm'] = 'Heslá sa nezhodujú';
        }

        if (!empty($response['errors'])) {
            $response['message'] = 'Prosím, opravte chyby vo formulári';
            return $response;
        }

        // Registrácia
        try {
            $this->user->register($email, $password, $firstName, $lastName);
            $response['success'] = true;
            $response['message'] = 'Registrácia bola úspešná. Teraz sa môžete prihlásiť.';
            
            // Automatické presmerovanie
            $_SESSION['registration_success'] = true;
            header('Location: index.php?page=login&registered=1');
            exit();
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * Spracovanie prihlásenia
     */
    public function handleLogin() {
        $response = [
            'success' => false,
            'message' => '',
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

        // Zbieranie vstupov
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validácia vstupov
        if (empty($email)) {
            $response['errors']['email'] = 'E-mail je povinný';
        }

        if (empty($password)) {
            $response['errors']['password'] = 'Heslo je povinné';
        }

        if (!empty($response['errors'])) {
            $response['message'] = 'Prosím, vyplňte všetky poľa';
            return $response;
        }

        // Prihlásenie
        try {
            $this->user->login($email, $password);
            $response['success'] = true;
            $response['message'] = 'Prihlásenie bolo úspešné';
            
            // Presmerovanie na dashboard
            header('Location: index.php?page=dashboard');
            exit();
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            // Log attempt
            $this->logAuthAttempt($email, false);
        }

        return $response;
    }

    /**
     * Spracovanie odhlásenia
     */
    public function handleLogout() {
        if (isset($_SESSION['user_id'])) {
            // Log logout
            $this->logAuthAttempt($_SESSION['user_email'] ?? 'unknown', true, 'logout');
        }
        
        session_destroy();
        header('Location: index.php?page=login');
        exit();
    }

    /**
     * Generovanie CSRF tokenu
     */
    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Zaznamenanie pokusu o prihlásenie
     */
    private function logAuthAttempt($email, $success = false, $action = 'login') {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $description = $action . ' - ' . ($success ? 'success' : 'failed');
            
            $this->db->execute(
                'INSERT INTO audit_log (action, entity_type, description, ip_address) VALUES (?, ?, ?, ?)',
                ['auth_' . $action, 'user', $description, $ipAddress]
            );
        } catch (Exception $e) {
            error_log('Failed to log auth attempt: ' . $e->getMessage());
        }
    }

    /**
     * Kontrola, či je používateľ prihláený
     */
    public static function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Získanie ID aktuálneho používateľa
     */
    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Získanie mena aktuálneho používateľa
     */
    public static function getCurrentUserName() {
        return $_SESSION['user_name'] ?? 'Neznámy používateľ';
    }

    /**
     * Kontrola oprávnenia - presmerovanie ak nie je prihlásený
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            header('Location: index.php?page=login');
            exit();
        }
    }
}
