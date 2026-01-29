<?php
session_start();
// Important : Définir le fuseau horaire pour que le compte à rebours soit juste
date_default_timezone_set('Africa/Douala');
require_once '../config/database.php';

// 1. Vérification de sécurité
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2, 3, 4])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id']; // Récupération de l'ID pour les notifications personnelles

try {
    // 2. Récupération des statistiques réelles
    $totalCollecte = $pdo->query("SELECT SUM(montant_paye) FROM cotisations WHERE statut = 'payé'")->fetchColumn() ?: 0;
    $totalMembres = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut = 'actif'")->fetchColumn() ?: 0;
    $totalEpargne = $pdo->query("SELECT SUM(montant_paye) FROM epargnes WHERE statut = 'payé'")->fetchColumn() ?: 0;
    $totalAssurance = $pdo->query("SELECT SUM(montant_paye) FROM assurances WHERE statut = 'payé'")->fetchColumn() ?: 0;
    $totalTontineTour = $pdo->query("SELECT SUM(montant_paye) FROM cotisations WHERE statut = 'payé'")->fetchColumn() ?: 0; // Note: Vérifiez si c'est la bonne requête pour "Tontine Tour"
    $totalImpayes = $pdo->query("SELECT SUM(montant_attendu - montant_paye) FROM cotisations WHERE statut != 'payé'")->fetchColumn() ?: 0;
    $totalSousTontines = $pdo->query("SELECT COUNT(*) FROM cercles")->fetchColumn() ?: 0;

    // --- DIAGRAMME BÂTON (6 derniers mois) ---
    $queryBar = "SELECT 
                    DATE_FORMAT(date_limite, '%b') as mois, 
                    SUM(montant_paye) as total 
                 FROM cotisations 
                 WHERE statut = 'payé' 
                 GROUP BY MONTH(date_limite), DATE_FORMAT(date_limite, '%b')
                 ORDER BY date_limite ASC 
                 LIMIT 6";
    $resBar = $pdo->query($queryBar)->fetchAll(PDO::FETCH_ASSOC);

    $labelsBaton = [];
    $dataBaton = [];

    if (empty($resBar)) {
        $labelsBaton = ['Aucune donnée'];
        $dataBaton = [0];
    } else {
        foreach($resBar as $row) {
            $labelsBaton[] = $row['mois'];
            $dataBaton[] = $row['total'];
        }
    }

    // On compte : Notifs non lues + Parrainages en attente + Votes non effectués par cet admin
    $notifsCount = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM notifications WHERE (membre_id = $user_id OR membre_id IS NULL) AND statut = 'non_lu') + 
        (SELECT COUNT(*) FROM membres WHERE parrain_id = $user_id AND statut_validation = 'en_attente_parrain') +
        (SELECT COUNT(*) FROM membres m WHERE m.statut_validation = 'en_vote' 
         AND NOT EXISTS (SELECT 1 FROM votes_decisions v WHERE v.reference_id = m.id AND v.membre_id = $user_id AND v.type_vote = 'adhesion'))
    ")->fetchColumn() ?: 0;

    $stmtChrono = $pdo->query("SELECT * FROM seances WHERE statut = 'prevue' ORDER BY date_seance ASC, heure_limite_pointage ASC LIMIT 1");
    $prochaineSeance = $stmtChrono->fetch();

    $secondes_restantes = 0;
    if ($prochaineSeance) {
        // Construction date fin : YYYY-MM-DD HH:MM:SS
        $str_fin = $prochaineSeance['date_seance'] . ' ' . $prochaineSeance['heure_limite_pointage'];
        $timestamp_fin = strtotime($str_fin);
        $timestamp_now = time();
        $secondes_restantes = $timestamp_fin - $timestamp_now;
        if ($secondes_restantes < 0) $secondes_restantes = 0;
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3f37c9;
            --secondary-bg: #f3f4f6;
            --sidebar-bg: #111827;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--secondary-bg);
            color: var(--text-main);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, .brand-font {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--sidebar-bg);
            color: #e5e7eb;
            z-index: 1050;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-menu {
            padding: 1rem;
            flex-grow: 1;
            overflow-y: auto;
        }

        .nav-category {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin: 1.5rem 0 0.5rem 0.75rem;
            font-weight: 600;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #d1d5db;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .nav-link-custom:hover, .nav-link-custom.active {
            background: rgba(67, 97, 238, 0.15);
            color: #60a5fa;
        }

        .nav-link-custom i {
            width: 1.5rem;
            font-size: 1.1rem;
            opacity: 0.8;
        }

        .nav-link-custom.text-danger:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
        }

        /* --- MAIN CONTENT --- */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        /* --- CARDS & WIDGETS --- */
        .dashboard-card {
            background: var(--card-bg);
            border-radius: 1rem;
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 10px 15px -5px rgba(0,0,0,0.02);
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);
        }

        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .bg-primary-soft { background: rgba(67, 97, 238, 0.1); color: var(--primary-color); }
        .bg-success-soft { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .bg-warning-soft { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .bg-danger-soft { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .bg-info-soft { background: rgba(6, 182, 212, 0.1); color: #06b6d4; }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0;
            letter-spacing: -0.5px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* --- HEADER ACTIONS --- */
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-action {
            border-radius: 50px;
            padding: 0.5rem 1.25rem;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* --- TIMER --- */
        .timer-widget {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 0.6rem 1.25rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .timer-digits {
            font-family: 'Inter', monospace;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 1px;
        }

        /* --- NOTIFICATIONS --- */
        .notif-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: white;
            border: 1px solid #e5e7eb;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        .notif-btn:hover { background: #f9fafb; color: var(--primary-color); }
        .badge-pulse {
            position: absolute;
            top: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background: var(--danger);
            border: 2px solid white;
            border-radius: 50%;
        }

        /* --- RESPONSIVE --- */
        .mobile-header {
            display: none;
            background: var(--sidebar-bg);
            padding: 1rem;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1040;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
            z-index: 1045;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
            backdrop-filter: blur(2px);
        }

        .overlay.active { opacity: 1; visibility: visible; }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1.5rem; }
            .mobile-header { display: flex; }
            .stat-value { font-size: 1.5rem; }
            .header-flex-col { flex-direction: column; align-items: flex-start !important; gap: 1rem; }
            .timer-widget { width: 100%; justify-content: center; }
        } 
    .mobile-header {
    background-color: #111827; /* Même couleur que la sidebar */
    display: flex; /* Active Flexbox */
    justify-content: space-between; /* Espacement max entre logo et bouton */
    align-items: center; /* Centrage vertical */
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 1040;
}

/* Petit effet sur le bouton menu */
#btnToggleSidebar:active {
    transform: scale(0.95);
}
    </style>
</head>
<body>

    <div class="overlay" id="sidebarOverlay"></div>

   <div class="mobile-header px-4 py-3 border-bottom border-white border-opacity-10">
    <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center justify-content-center rounded-3 bg-primary bg-opacity-25 text-primary" 
             style="width: 40px; height: 40px;">
            <i class="fa-solid fa-handshake-simple fs-5 text-white"></i>
        </div>
        <span class="fw-bold brand-font fs-5 text-white tracking-wide">NDJANGUI</span>
    </div>

    <button class="btn btn-link p-0 text-white text-opacity-75 hover-opacity-100 transition-all" 
            id="btnToggleSidebar" 
            style="width: 40px; height: 40px; display: grid; place-items: center;">
        <i class="fa-solid fa-bars-staggered fs-4"></i>
    </button>
</div>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <div class="bg-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                    <i class="fa-solid fa-handshake-simple text-white"></i>
                </div>
                <div>
                    <h5 class="m-0 fw-bold text-white brand-font">NDJANGUI</h5>
                    <small class="text-muted" style="font-size: 0.7rem;">ADMINISTRATION</small>
                </div>
                <button class="btn text-white ms-auto d-lg-none" id="btnCloseSidebar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
<div class="sidebar-menu">
    <div class="nav-category">Pilotage</div>
    <a href="index.php" class="nav-link-custom active">
        <i class="fa-solid fa-chart-pie me-2"></i> Tableau de bord
    </a>

    <div class="nav-category">Membres & Accès</div>
    <a class="nav-link-custom collapsed" data-bs-toggle="collapse" href="#membersCollapse">
        <i class="fa-solid fa-users-gear me-2"></i> 
        <span>Administration</span>
        <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
    </a>
    <div class="collapse" id="membersCollapse">
        <div class="ps-3 border-start border-secondary ms-3 my-1">
            <a href="seances.php" class="nav-link-custom py-2 small">
                <i class="fa-solid fa-calendar-day me-2"></i> Séances
            </a>
            <a href="admin-rights.php" class="nav-link-custom py-2 small">
                <i class="fa-solid fa-user-shield me-2"></i> Droits & Rôles
            </a>
            <a href="suppressions.php" class="nav-link-custom py-2 small">
                <i class="fa-solid fa-user-minus me-2"></i> Suppressions
            </a>
            <a href="admin-historiques.php" class="nav-link-custom py-2 small">
                <i class="fa-solid fa-clock-rotate-left me-2"></i> Historiques
            </a>
        </div>
    </div>

    <div class="nav-category">Finance</div>
    <a class="nav-link-custom collapsed" data-bs-toggle="collapse" href="#financeCollapse">
        <i class="fa-solid fa-wallet me-2"></i> 
        <span>Trésorerie</span>
        <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
    </a>
    <div class="collapse" id="financeCollapse">
        <div class="ps-3 border-start border-secondary ms-3 my-1">
            <a href="rapport-cotisations.php" class="nav-link-custom py-2 small">
                <i class="fa-solid fa-hand-holding-dollar me-2"></i> Cotisations
            </a>
            <a href="admin-historiques.php" class="nav-link-custom py-2 small">
                <i class="fa-solid fa-scale-balanced me-2"></i> États des soldes
            </a>
            <a href="sanctions-amendes.php" class="nav-link-custom py-2 small">
                <i class="fa-solid fa-gavel me-2"></i> Sanctions
            </a>
            <a href="assurances.php" class="nav-link-custom py-2 small">
    <i class="fa-solid fa-shield-heart me-2"></i> Assurance
</a>
        </div>
    </div>

    <div class="nav-category">Communication</div>
     <a href="messages.php" class="nav-link-custom py-2 small">
                 <i class="fa-solid fa-comments me-2"></i> Forum
            </a>
    <a href="admin-historiques.php" class="nav-link-custom">
        <i class="fa-solid fa-file-pen me-2"></i> Rapports
    </a>
</div>

        <div class="p-3 border-top border-secondary">
            <a href="../logout.php" class="nav-link-custom text-danger justify-content-center bg-danger bg-opacity-10 rounded-3">
                <i class="fa-solid fa-power-off me-2"></i> Déconnexion
            </a>
        </div>
    </nav>

    <main class="main-content">
        
        <div class="d-flex justify-content-between align-items-center mb-5 header-flex-col">
            <div>
                <h2 class="fw-bold text-dark m-0">Vue d'ensemble</h2>
                <p class="text-muted small m-0">Bonjour, <?php echo htmlspecialchars($_SESSION['user_nom']); ?>. Voici ce qui se passe aujourd'hui.</p>
            </div>

            <div class="header-actions flex-wrap">
                <?php if($secondes_restantes > 0): ?>
                <div class="timer-widget" title="Temps avant la prochaine séance">
                    <i class="fa-regular fa-clock"></i>
                    <div class="timer-digits">
                        <span id="md">00</span>j : <span id="mh">00</span>h : <span id="mm">00</span>m : <span id="ms">00</span>s
                    </div>
                </div>
                <?php endif; ?>

                <a href="../membre/notifications.php" class="notif-btn shadow-sm">
                    <i class="fa-regular fa-bell"></i>
                    <?php if($notifsCount > 0): ?>
                        <span class="badge-pulse"></span>
                    <?php endif; ?>
                </a>

                <div class="d-flex gap-2">
                    <button class="btn btn-light bg-white border shadow-sm rounded-pill px-3 fw-bold text-muted">
                        <i class="fa-solid fa-download me-2"></i> Exporter
                    </button>
                    <div class="dropdown">
    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa-solid fa-plus me-2"></i> Action
    </button>
    
    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2 mt-2" style="border-radius: 12px; min-width: 220px;">
        <li>
            <a class="dropdown-item py-2 rounded d-flex align-items-center" href="ajouter-membre.php">
                <i class="fa-solid fa-user-plus me-3 text-primary bg-primary bg-opacity-10 p-2 rounded"></i> 
                <span>Ajouter un membre</span>
            </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
    </ul>
</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label">Total Collecté</p>
                            <h3 class="stat-value text-primary"><?php echo number_format($totalCollecte, 0, ',', ' '); ?> <small class="fs-6 text-muted fw-normal">FCFA</small></h3>
                        </div>
                        <div class="stat-icon-wrapper bg-primary-soft">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label">Membres Actifs</p>
                            <h3 class="stat-value"><?php echo $totalMembres; ?></h3>
                        </div>
                        <div class="stat-icon-wrapper bg-success-soft">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label">Sous-tontines</p>
                            <h3 class="stat-value"><?php echo sprintf("%02d", $totalSousTontines); ?></h3>
                        </div>
                        <div class="stat-icon-wrapper bg-warning-soft">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label">Impayés Totaux</p>
                            <h3 class="stat-value text-danger"><?php echo number_format($totalImpayes, 0, ',', ' '); ?> <small class="fs-6 text-muted fw-normal">FCFA</small></h3>
                        </div>
                        <div class="stat-icon-wrapper bg-danger-soft">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="dashboard-card border-bottom border-4 border-primary">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon-wrapper bg-primary-soft me-3 mb-0" style="width:40px; height:40px; font-size:1rem;">
                            <i class="fa-solid fa-piggy-bank"></i>
                        </div>
                        <h6 class="fw-bold m-0 text-uppercase small text-muted">Épargne Globale</h6>
                    </div>
                    <h3 class="fw-bold m-0"><?php echo number_format($totalEpargne, 0, ',', ' '); ?> <span class="fs-6 text-muted">FCFA</span></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card border-bottom border-4 border-success">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon-wrapper bg-success-soft me-3 mb-0" style="width:40px; height:40px; font-size:1rem;">
                            <i class="fa-solid fa-shield-heart"></i>
                        </div>
                        <h6 class="fw-bold m-0 text-uppercase small text-muted">Caisse Assurance</h6>
                    </div>
                    <h3 class="fw-bold m-0"><?php echo number_format($totalAssurance, 0, ',', ' '); ?> <span class="fs-6 text-muted">FCFA</span></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card border-bottom border-4 border-info">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon-wrapper bg-info-soft me-3 mb-0" style="width:40px; height:40px; font-size:1rem;">
                            <i class="fa-solid fa-circle-notch"></i>
                        </div>
                        <h6 class="fw-bold m-0 text-uppercase small text-muted">Tontine (Tour)</h6>
                    </div>
                    <h3 class="fw-bold m-0"><?php echo number_format($totalTontineTour, 0, ',', ' '); ?> <span class="fs-6 text-muted">FCFA</span></h3>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="dashboard-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold m-0">Évolution des Cotisations</h5>
                        <select class="form-select form-select-sm w-auto border-0 bg-light fw-bold text-muted">
                            <option>6 derniers mois</option>
                        </select>
                    </div>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="dashboard-card h-100">
                    <h5 class="fw-bold mb-4">Répartition des Fonds</h5>
                    <div style="height: 250px; width: 100%; position: relative;">
                        <canvas id="pieChart"></canvas>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                            <small class="text-muted d-block">Total</small>
                            <span class="fw-bold text-dark">100%</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="d-flex justify-content-between small mb-2">
                            <span><i class="fa-solid fa-circle text-primary me-1"></i> Épargne</span>
                            <span class="fw-bold"><?php echo number_format($totalEpargne, 0, ',', ' '); ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span><i class="fa-solid fa-circle text-success me-1"></i> Assurance</span>
                            <span class="fw-bold"><?php echo number_format($totalAssurance, 0, ',', ' '); ?></span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span><i class="fa-solid fa-circle text-warning me-1"></i> Tontine</span>
                            <span class="fw-bold"><?php echo number_format($totalTontineTour, 0, ',', ' '); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- SIDEBAR TOGGLE LOGIC ---
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const btnOpen = document.getElementById('btnToggleSidebar');
        const btnClose = document.getElementById('btnCloseSidebar');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        btnOpen.addEventListener('click', toggleSidebar);
        btnClose.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // --- TIMER LOGIC ---
        let totalSeconds = <?php echo ($secondes_restantes > 0) ? $secondes_restantes : 0; ?>;
        
        function updateMiniTimer() {
            let days = Math.floor(totalSeconds / (3600 * 24));
            let hours = Math.floor((totalSeconds % (3600 * 24)) / 3600);
            let minutes = Math.floor((totalSeconds % 3600) / 60);
            let seconds = Math.floor(totalSeconds % 60);

            const pad = (num) => num < 10 ? "0" + num : num;

            const elD = document.getElementById('md');
            const elH = document.getElementById('mh');
            const elM = document.getElementById('mm');
            const elS = document.getElementById('ms');

            if(elD) elD.innerText = pad(days);
            if(elH) elH.innerText = pad(hours);
            if(elM) elM.innerText = pad(minutes);
            if(elS) elS.innerText = pad(seconds);
        }

        if (totalSeconds > 0) {
            updateMiniTimer();
            const timerInterval = setInterval(() => {
                totalSeconds--;
                if (totalSeconds < 0) {
                    clearInterval(timerInterval);
                    location.reload(); 
                    return;
                }
                updateMiniTimer();
            }, 1000);
        }

        // --- CHARTS CONFIG ---
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false } // Custom legend in HTML for better control
            }
        };

        // Bar Chart
        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labelsBaton); ?>,
                datasets: [{
                    label: 'Cotisations (FCFA)',
                    data: <?php echo json_encode($dataBaton); ?>,
                    backgroundColor: '#4361ee',
                    borderRadius: 6,
                    barThickness: 25,
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [5, 5], color: '#f3f4f6' },
                        ticks: { font: { family: 'Inter', size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 } }
                    }
                }
            }
        });

        // Pie Chart
        new Chart(document.getElementById('pieChart'), {
            type: 'doughnut',
            data: {
                labels: ['Épargne', 'Assurance', 'Tontine'],
                datasets: [{
                    data: [<?php echo $totalEpargne; ?>, <?php echo $totalAssurance; ?>, <?php echo $totalTontineTour; ?>],
                    backgroundColor: ['#4361ee', '#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</body>
</html>