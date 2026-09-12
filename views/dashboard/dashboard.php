<?php
/**
 * Dashboard - Zoznam faktúr a štatistika
 */

require_once '../../config/database.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/InvoiceController.php';

AuthController::requireAuth();

$invoiceController = new InvoiceController();
$currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$invoicesList = $invoiceController->getInvoicesList($currentPage, 20);
$statistics = $invoiceController->getStatistics();
$userName = AuthController::getCurrentUserName();
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - e-Faktura</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="dashboard-page">
    <!-- Hlavička -->
    <header class="header">
        <div class="container">
            <div class="header-brand">
                <h1>e-Faktura</h1>
            </div>
            <div class="header-user">
                <span class="user-name">Vitajte, <?php echo htmlspecialchars($userName); ?></span>
                <a href="index.php?page=logout" class="btn btn-small">Odhlásiť sa</a>
            </div>
        </div>
    </header>

    <!-- Hlavný obsah -->
    <main class="main-content">
        <div class="container">
            <!-- Návigcia -->
            <nav class="dashboard-nav">
                <a href="index.php?page=dashboard" class="nav-item active">Dashboard</a>
                <a href="index.php?page=invoice&action=upload" class="nav-item btn btn-primary">+ Import XML Faktúry</a>
            </nav>

            <!-- Štatistika -->
            <section class="statistics-section">
                <h2>Prehľad</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $statistics['total_count'] ?? 0; ?></div>
                        <div class="stat-label">Celkem faktúr</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($statistics['total_amount'] ?? 0, 2, ',', ' '); ?> EUR</div>
                        <div class="stat-label">Celková suma</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $statistics['draft_count'] ?? 0; ?></div>
                        <div class="stat-label">Koncepty</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $statistics['paid_count'] ?? 0; ?></div>
                        <div class="stat-label">Zaplatené</div>
                    </div>
                </div>
            </section>

            <!-- Zoznam faktúr -->
            <section class="invoices-section">
                <h2>Faktúry</h2>

                <?php if (empty($invoicesList['invoices'])): ?>
                    <div class="empty-state">
                        <p>Zatiaľ nemáte žiadne faktúry.</p>
                        <a href="index.php?page=invoice&action=upload" class="btn btn-primary">
                            Naimportujte prvú faktúru
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="invoices-table">
                            <thead>
                                <tr>
                                    <th>Číslo faktúry</th>
                                    <th>Dátum</th>
                                    <th>Zákazník</th>
                                    <th>Suma</th>
                                    <th>Stav</th>
                                    <th>Akcie</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoicesList['invoices'] as $invoice): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('d.m.Y', strtotime($invoice['invoice_date'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($invoice['customer_name']); ?>
                                        </td>
                                        <td class="text-right">
                                            <?php echo number_format($invoice['total_amount'], 2, ',', ' '); ?> <?php echo htmlspecialchars($invoice['currency']); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo htmlspecialchars($invoice['status']); ?>">
                                                <?php echo $this->getStatusLabel($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="index.php?page=invoice&action=view&id=<?php echo $invoice['id']; ?>" 
                                               class="btn btn-small btn-secondary">
                                                Zobraziť
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginácia -->
                    <?php if ($invoicesList['invoices'] || $invoicesList['page'] > 1): ?>
                        <div class="pagination">
                            <?php if ($invoicesList['page'] > 1): ?>
                                <a href="index.php?page=dashboard&p=<?php echo $invoicesList['page'] - 1; ?>" class="btn btn-small">
                                    &laquo; Predchádzajúca
                                </a>
                            <?php endif; ?>

                            <span class="pagination-info">
                                Strana <?php echo $invoicesList['page']; ?>
                            </span>

                            <?php if (count($invoicesList['invoices']) >= $invoicesList['limit']): ?>
                                <a href="index.php?page=dashboard&p=<?php echo $invoicesList['page'] + 1; ?>" class="btn btn-small">
                                    Ďalšia &raquo;
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Pätička -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 e-Faktura. Všetky práva vyhradené.</p>
        </div>
    </footer>
</body>
</html>

<?php
/**
 * Pomocná funkcia na preklad stavu
 */
function getStatusLabel($status) {
    $labels = [
        'draft' => 'Koncept',
        'sent' => 'Odoslaná',
        'received' => 'Prijatá',
        'paid' => 'Zaplatená',
        'cancelled' => 'Zrušená'
    ];
    return $labels[$status] ?? $status;
}
?>
