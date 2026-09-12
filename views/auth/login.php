<?php
/**
 * Prihlasovací formulár
 */

require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';

$authController = new AuthController();
$csrfToken = $authController->generateCSRFToken();

$loginResponse = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginResponse = $authController->handleLogin();
}

$isRegistered = isset($_GET['registered']);
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prihlásenie - e-Faktura</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>e-Faktura</h1>
                <p>Systém na správu elektronických faktúr</p>
            </div>

            <?php if ($isRegistered): ?>
                <div class="alert alert-success">
                    <strong>Úspech!</strong> Registrácia bola úspešná. Teraz sa môžete prihlásiť.
                </div>
            <?php endif; ?>

            <?php if ($loginResponse && !$loginResponse['success']): ?>
                <div class="alert alert-error">
                    <strong>Chyba!</strong> <?php echo htmlspecialchars($loginResponse['message']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

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
                    <?php if (isset($loginResponse['errors']['email'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($loginResponse['errors']['email']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">Heslo:</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Zadajte vaše heslo" 
                        required
                    >
                    <?php if (isset($loginResponse['errors']['password'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($loginResponse['errors']['password']); ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Prihlásiť sa
                </button>
            </form>

            <div class="auth-footer">
                <p>Nemáte ešte účet? <a href="index.php?page=register">Zaregistrujte sa tu</a></p>
            </div>
        </div>
    </div>
</body>
</html>
