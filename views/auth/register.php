<?php
/**
 * Registračný formulár
 */

require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';

$authController = new AuthController();
$csrfToken = $authController->generateCSRFToken();

$registerResponse = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registerResponse = $authController->handleRegister();
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrácia - e-Faktura</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box auth-box-register">
            <div class="auth-header">
                <h1>e-Faktura</h1>
                <p>Vytvorte si účet</p>
            </div>

            <?php if ($registerResponse && !$registerResponse['success']): ?>
                <div class="alert alert-error">
                    <strong>Chyba!</strong> <?php echo htmlspecialchars($registerResponse['message']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Meno:</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            placeholder="Vaše meno" 
                            value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                            required
                        >
                        <?php if (isset($registerResponse['errors']['first_name'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($registerResponse['errors']['first_name']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Priezvisko:</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            placeholder="Vaše priezvisko" 
                            value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                            required
                        >
                        <?php if (isset($registerResponse['errors']['last_name'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($registerResponse['errors']['last_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">E-mailová adresa:</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="vase@email.com" 
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                    <?php if (isset($registerResponse['errors']['email'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($registerResponse['errors']['email']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">Heslo:</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Minimálne 8 znakov" 
                        required
                    >
                    <?php if (isset($registerResponse['errors']['password'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($registerResponse['errors']['password']); ?></span>
                    <?php endif; ?>
                    <small class="help-text">Heslo musí obsahovať minimálne 8 znakov</small>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Potvrdite heslo:</label>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        placeholder="Zopakujte heslo" 
                        required
                    >
                    <?php if (isset($registerResponse['errors']['password_confirm'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($registerResponse['errors']['password_confirm']); ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Zaregistrovať sa
                </button>
            </form>

            <div class="auth-footer">
                <p>Už máte účet? <a href="index.php?page=login">Prihláste sa tu</a></p>
            </div>
        </div>
    </div>
</body>
</html>
