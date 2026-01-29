<?php
session_start();
require_once '../config/database.php';

// Vérification de sécurité
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];

// =========================================================
// 1. TRAITEMENT DES FORMULAIRES (POST)
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- A. TRAITEMENT DU PARRAINAGE ---
    if (isset($_POST['action_parrainage'])) {
        try {
            $filleul_id = intval($_POST['filleul_id']);
            $action = $_POST['action_parrainage'];
            $motif = htmlspecialchars(trim($_POST['motif']));

            if ($action === 'valider') {
                $stmtUpd = $pdo->prepare("UPDATE membres SET statut_validation = 'en_vote' WHERE id = ?");
                $stmtUpd->execute([$filleul_id]);

                $stmtVote = $pdo->prepare("INSERT INTO votes_decisions (reference_id, type_vote, membre_id, decision, commentaire, date_vote) VALUES (?, 'adhesion', ?, 'pour', ?, NOW())");
                $stmtVote->execute([$filleul_id, $user_id, $motif]); 
                
                $pdo->prepare("INSERT INTO notifications (membre_id, message, type) VALUES (?, 'Votre parrain a validé votre profil. Le vote du bureau commence.', 'info')")->execute([$filleul_id]);
            } else {
                $stmtUpd = $pdo->prepare("UPDATE membres SET statut_validation = 'rejete' WHERE id = ?");
                $stmtUpd->execute([$filleul_id]);
            }
            header("Location: index.php?success=parrainage");
            exit;
        } catch (PDOException $e) {
            $error = "Erreur parrainage : " . $e->getMessage();
        }
    }

    // --- B. TRAITEMENT ENVOI MESSAGE (NOUVEAU) ---
    if (isset($_POST['action_message'])) {
        try {
            $destinataire_id = !empty($_POST['destinataire_id']) ? intval($_POST['destinataire_id']) : 1; 
            $contenu_message = htmlspecialchars(trim($_POST['message']));

            if (!empty($contenu_message)) {
                $stmtMsg = $pdo->prepare("INSERT INTO notifications (membre_id, message, type, statut, date_creation) VALUES (?, ?, 'message', 'non_lu', NOW())");
                $message_complet = "Message de " . ($_SESSION['user_nom'] ?? 'Membre') . " : " . $contenu_message;
                $stmtMsg->execute([$destinataire_id, $message_complet]);
                
                header("Location: index.php?success=message_envoye");
                exit;
            } else {
                $error = "Le message ne peut pas être vide.";
            }
        } catch (PDOException $e) {
            $error = "Erreur envoi message : " . $e->getMessage();
        }
    }
}

// =========================================================
// 2. RECUPERATION DES DONNEES (GET/SELECT)
// =========================================================

$notifsCount = 0;
$totalNotifs = 0;
$membresBureau = [];
$monEpargne = 0;
$mesDettes = 0;
$monCotise = 0;
$nbMembres = 0;
$mesSousTontines = 0;
$monAssurance = 0;
$montantProjets = 0; 
$notifProjets = 0;
$financementRecu = 0;

try {
    // 1. Données personnelles & Epargne Nette
    $stmtNetEpargne = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(montant_paye), 0) FROM epargnes WHERE membre_id = ? AND statut = 'payé') - 
            (SELECT COALESCE(SUM(montant), 0) FROM demandes_retrait WHERE membre_id = ? AND source_fond = 'epargne' AND statut = 'valide')
    ");
    $stmtNetEpargne->execute([$user_id, $user_id]);
    $monEpargne = $stmtNetEpargne->fetchColumn() ?: 0;

    $mesDettes = $pdo->query("SELECT SUM(montant_attendu - montant_paye) FROM cotisations WHERE membre_id = $user_id AND statut IN ('non_paye', 'partiel')")->fetchColumn() ?: 0;
    $monCotise = $pdo->query("SELECT SUM(montant_paye) FROM cotisations WHERE membre_id = $user_id")->fetchColumn() ?: 0;
    $monAssurance = $pdo->query("SELECT SUM(montant_paye) FROM assurances WHERE membre_id = $user_id AND statut = 'payé'")->fetchColumn() ?: 0;
    $mesSousTontines = $pdo->query("SELECT COUNT(DISTINCT cercle_id) FROM cotisations WHERE membre_id = $user_id")->fetchColumn() ?: 0;
    
    // 2. Données Globales
    $nbMembres = $pdo->query("SELECT COUNT(*) FROM membres WHERE statut = 'actif'")->fetchColumn() ?: 0;
    
    $stmtProjetPerso = $pdo->prepare("SELECT SUM(montant_verse) FROM projets WHERE membre_id = ? AND statut = 'approuve'");
    $stmtProjetPerso->execute([$user_id]);
    $montantProjets = $stmtProjetPerso->fetchColumn() ?: 0;

    $stmtPrets = $pdo->prepare("SELECT SUM(montant_accorde) FROM prets WHERE membre_id = ? AND statut_pret IN ('approuve', 'en_remboursement', 'solde')");
    $stmtPrets->execute([$user_id]);
    $totalPrets = $stmtPrets->fetchColumn() ?: 0;

    $stmtProjetsTotal = $pdo->prepare("SELECT SUM(montant_verse) FROM projets WHERE membre_id = ? AND statut IN ('approuve', 'en_cours', 'termine')");
    $stmtProjetsTotal->execute([$user_id]);
    $totalProjetsVerse = $stmtProjetsTotal->fetchColumn() ?: 0;

    $financementRecu = $totalPrets + $totalProjetsVerse;

    // 3. Notifications (Calculs)
    
    // A. Badge Communauté (Messages uniquement)
    $stmtTotalMsg = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE membre_id = ? AND statut = 'non_lu' AND type IN ('message', 'message_cercle')");
    $stmtTotalMsg->execute([$user_id]);
    $totalNotifs = $stmtTotalMsg->fetchColumn() ?: 0;

    // B. Compteur Global (Cloche)
    $notifsCount += $totalNotifs; 
    
    // Autres notifs système
    $notifsCount += $pdo->query("SELECT COUNT(*) FROM notifications WHERE (membre_id = $user_id OR membre_id IS NULL OR membre_id = 0) AND statut = 'non_lu' AND type NOT IN ('message', 'message_cercle', 'demande_adhesion')")->fetchColumn();
    
    // AJOUT : Compter les demandes d'adhésion pour le président
    $stmtAdhesion = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE membre_id = ? AND statut = 'non_lu' AND type = 'demande_adhesion'");
    $stmtAdhesion->execute([$user_id]);
    $notifsCount += $stmtAdhesion->fetchColumn();

    $notifsCount += $pdo->query("SELECT COUNT(*) FROM membres WHERE parrain_id = $user_id AND statut_validation = 'en_attente_parrain'")->fetchColumn();
    $notifsCount += $pdo->query("SELECT COUNT(*) FROM membres m WHERE m.statut_validation = 'en_vote' AND NOT EXISTS (SELECT 1 FROM votes_decisions v WHERE v.reference_id = m.id AND v.membre_id = $user_id AND v.type_vote = 'adhesion')")->fetchColumn();
    
    $notifProjets = $pdo->query("SELECT COUNT(*) FROM projets p WHERE p.statut = 'en_attente' AND NOT EXISTS (SELECT 1 FROM votes_decisions v WHERE v.reference_id = p.id AND v.membre_id = $user_id AND v.type_vote = 'projet')")->fetchColumn();
    $notifsCount += $notifProjets;
    
    $notifsCount += $pdo->query("SELECT COUNT(*) FROM epargnes WHERE membre_id = $user_id AND statut = 'en attente'")->fetchColumn();
    $notifsCount += $pdo->query("SELECT COUNT(*) FROM cotisations WHERE membre_id = $user_id AND statut IN ('non_paye', 'partiel')")->fetchColumn();
    
    // 4. Liste Bureau
    $queryMembres = "SELECT nom_complet, adresse_physique, profession, role_id, statut FROM membres WHERE role_id IN (1, 2, 3) AND statut = 'actif' ORDER BY nom_complet ASC";
    $stmtMembres = $pdo->query($queryMembres);
    if($stmtMembres) {
        $membresBureau = $stmtMembres->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

} catch (PDOException $e) {
    $error = "Erreur de chargement : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Membre Premium | NDJANGUI</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --primary: #1a237e; 
            --secondary: #00c853; 
            --accent: #ff9100; 
            --info: #00b0ff;
            --purple: #6200ea;
            --bg: #f8faff; 
            --sidebar-w: 280px; 
        }     
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); margin: 0; overflow-x: hidden; }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-w); height: 100vh; background: linear-gradient(180deg, #1a237e 0%, #0d1250 100%);
            position: fixed; color: white; padding: 20px; overflow-y: auto;
            transition: 0.3s; z-index: 1040; box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }
        .topbar {
            height: 75px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            position: fixed; top: 0; right: 0; left: var(--sidebar-w);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 30px; box-shadow: 0 2px 20px rgba(0,0,0,0.05); z-index: 1030;
            transition: 0.3s;
        }
        .main-content { margin-left: var(--sidebar-w); padding: 105px 30px 30px; transition: 0.3s; }
        
        /* Navigation Links */
        .nav-link-custom {
            color: rgba(255,255,255,0.6); text-decoration: none;
            display: flex; align-items: center; padding: 14px 18px;
            border-radius: 12px; margin-bottom: 8px; transition: 0.3s; font-size: 0.92rem;
            border: none; background: transparent; width: 100%; text-align: left;
            cursor: pointer;
        }
        .nav-link-custom:hover, .nav-link-custom.active { 
            background: rgba(255,255,255,0.12); color: white; transform: translateX(5px);
        }
        .nav-link-custom i:first-child { width: 28px; font-size: 1.1rem; }
        
        /* Sub-menus */
        .collapse-menu { background: rgba(0,0,0,0.15); border-radius: 12px; margin-bottom: 12px; padding: 8px 0; }
        .collapse-menu a { display: block; padding: 8px 15px 8px 50px; opacity: 0.8; font-size: 0.85rem; color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.2s; }
        .collapse-menu a:hover { color: white; opacity: 1; transform: translateX(5px); }
        
        /* Cards */
        .chart-card {
            background: white; border-radius: 24px; padding: 25px;
            box-shadow: 0 10px 35px rgba(26, 35, 126, 0.05); height: 100%;
            transition: 0.3s; border: 1px solid rgba(0,0,0,0.02);
            position: relative; overflow: hidden;
        }
        .chart-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(26, 35, 126, 0.08); }
        .stat-icon-bg {
            position: absolute; right: -10px; bottom: -10px; font-size: 4rem;
            opacity: 0.07; transform: rotate(-15deg); color: inherit;
        }
        .chart-container { position: relative; height: 300px; width: 100%; }

        /* Notifications & Profile */
        .notif-wrapper { position: relative; margin-right: 20px; }
        .notif-btn { 
            background: #f0f3ff; color: var(--primary); border: none; 
            width: 42px; height: 42px; border-radius: 12px; transition: 0.3s;
        }
        .notif-badge {
            position: absolute; top: -5px; right: -5px; background: #ff3d00;
            color: white; font-size: 10px; padding: 3px 6px; border-radius: 50%; border: 2px solid white;
        }
        .profile-dropdown img { 
            width: 45px; height: 45px; border-radius: 14px; 
            object-fit: cover; cursor: pointer; border: 2px solid #eef2ff;
        }
        .assurance-card { background: linear-gradient(135deg, #00b0ff 0%, #0081cb 100%); color: white; }
        .project-card-kpi { background: linear-gradient(135deg, #6200ea 0%, #3700b3 100%); color: white; cursor: pointer; }
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar { left: -100%; }
            .sidebar.active { left: 0; }
            .topbar, .main-content { left: 0; margin-left: 0; }
            .topbar .btn-hamburger { display: block !important; }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="d-flex justify-content-between align-items-center mb-5 px-2">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-handshake-simple fs-2 me-2 text-white"></i>
                <h3 class="fw-800 text-uppercase m-0" style="letter-spacing: 1.5px; font-size: 1.4rem;">NDJANGUI</h3>
            </div>
            <button type="button" class="btn text-white d-lg-none" onclick="toggleSidebar()"><i class="fa-solid fa-xmark"></i></button>
        </div>    

        <a href="index.php" class="nav-link-custom active"><i class="fa-solid fa-chart-line"></i> Tableau de bord</a>

        <button type="button" class="nav-link-custom" data-bs-toggle="collapse" data-bs-target="#menuFinance">
            <i class="fa-solid fa-wallet"></i> Finances <i class="fa-solid fa-chevron-down ms-auto small"></i>
        </button>
        <div class="collapse collapse-menu" id="menuFinance">
            <a href="cotiser.php" class="text-warning"><i class="fa-solid fa-coins me-2"></i> Cotiser MoMo/OM</a>
            <a href="retraits.php" class="text-info">
    <i class="fa-solid fa-hand-holding-dollar me-2"></i> Retrait des fonds
</a>
            <a href="historique.php"><i class="fa-solid fa-receipt me-2"></i> Historique</a>
        </div>

        <button type="button" class="nav-link-custom" data-bs-toggle="collapse" data-bs-target="#menuProjets">
            <i class="fa-solid fa-rocket"></i> Investissements 
            <?php if($notifProjets > 0): ?>
                <span class="badge bg-danger ms-2 rounded-pill small"><?php echo $notifProjets; ?></span>
            <?php endif; ?>
            <i class="fa-solid fa-chevron-down ms-auto small"></i>
        </button>
        <div class="collapse collapse-menu" id="menuProjets">
            <a href="projets.php">
                <i class="fa-solid fa-list-check me-2"></i> Liste des projets
                <?php if($notifProjets > 0): ?>
                    <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.7rem;">Votes</span>
                <?php endif; ?>
            </a> 
            <a href="nouveau_projet.php"><i class="fa-solid fa-plus me-2"></i> Lancer un projet</a>
        </div>

        <button type="button" class="nav-link-custom" data-bs-toggle="collapse" data-bs-target="#menuTontines">
            <i class="fa-solid fa-layer-group"></i> Sous-Tontines <i class="fa-solid fa-chevron-down ms-auto small"></i>
        </button>
        <div class="collapse collapse-menu" id="menuTontines">
            <a href="creer-tontine.php"><i class="fa-solid fa-plus-circle me-2"></i> Créer une tontine</a>
           <a href="/ndjangui_plateform/membre/tontine/rejoindre-tontine.php">
    <i class="fa-solid fa-right-to-bracket me-2"></i> Rejoindre un groupe
</a>
            <a href="tontine/mes-tontines.php"><i class="fa-solid fa-eye me-2"></i> Voir mes tontines</a>
        </div>

    <button type="button" class="nav-link-custom" data-bs-toggle="collapse" data-bs-target="#menuSocial">
    <i class="fa-solid fa-users"></i> Communauté 
    <?php if ($totalNotifs > 0): ?>
        <span class="badge bg-danger rounded-pill ms-1"><?= $totalNotifs ?></span>
    <?php endif; ?>
    <i class="fa-solid fa-chevron-down ms-auto small"></i>
</button>
<div class="collapse collapse-menu" id="menuSocial">
    <a href="messages.php" class="d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-comments me-2"></i> Messages</span>
        <span id="badge-messages-interne" class="badge bg-danger rounded-pill d-none" style="font-size: 0.7rem;">0</span>
    </a>
    <a href="#" data-bs-toggle="modal" data-bs-target="#messageModal">
        <i class="fa-solid fa-paper-plane me-2"></i> Contacter le Bureau
    </a>
</div>

        <a href="demander-pret.php" class="nav-link-custom"><i class="fa-solid fa-hand-holding-dollar"></i> Demander un prêt</a>
        
        <hr class="my-4 opacity-25">
        <a href="../logout.php" class="nav-link-custom text-danger mt-auto"><i class="fa-solid fa-power-off me-2"></i> Déconnexion</a>
    </div>

    <div class="topbar">
        <button type="button" class="btn btn-hamburger d-none text-primary" onclick="toggleSidebar()"><i class="fa-solid fa-bars-staggered fs-4"></i></button>
        <h5 class="fw-800 m-0 text-primary d-none d-md-block">PANNEAU DE CONTRÔLE</h5>
        
       <div class="d-flex align-items-center ms-auto"> 
    <div class="notif-wrapper position-relative">
        <a href="notifications.php" 
           class="notif-btn text-decoration-none d-flex align-items-center justify-content-center" 
           data-bs-toggle="tooltip" 
           data-bs-placement="bottom" 
           title="<?= (isset($notifsCount) && $notifsCount > 0) ? "Vous avez $notifsCount nouvelles notifications" : "Aucune nouvelle notification" ?>">
            
            <i class="fa-<?= (isset($notifsCount) && $notifsCount > 0) ? 'solid' : 'regular' ?> fa-bell fa-lg <?= (isset($notifsCount) && $notifsCount > 0) ? 'text-primary' : '' ?>"></i>
            
            <?php if(isset($notifsCount) && $notifsCount > 0): ?>
                <span class="notif-badge badge bg-danger rounded-pill border border-light animate__animated animate__pulse animate__infinite shadow-sm" 
                      style="position: absolute; top: -5px; right: -5px; font-size: 0.65rem; padding: 0.35em 0.6em;">
                    <?php echo ($notifsCount > 99) ? '99+' : $notifsCount; ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</div>
        
            <div class="dropdown profile-dropdown">
                <div class="d-flex align-items-center" data-bs-toggle="dropdown" style="cursor: pointer;">
                    <div class="text-end me-3 d-none d-sm-block">
                        <p class="m-0 fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'Membre'); ?></p>
                        <?php if($monAssurance > 0): ?>
                            <span class="badge bg-success-subtle text-success" style="font-size: 10px;">MEMBRE PREMIUM</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger" style="font-size: 10px;">ASSURANCE IMPAYÉE</span>
                        <?php endif; ?>
                    </div>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_nom'] ?? 'User'); ?>&background=1a237e&color=fff&bold=true" alt="Profil">
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2" style="border-radius: 20px; min-width: 240px;">
                    <li><a class="dropdown-item rounded-3 py-2" href="profil.php"><i class="fa-solid fa-user-circle me-2"></i> Mon Profil</a></li>
                    <li><a class="dropdown-item rounded-3 py-2" href="assurances.php"><i class="fa-solid fa-shield-cat me-2"></i> Statut Assurance</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item rounded-3 py-2 text-danger" href="../logout.php"><i class="fa-solid fa-sign-out-alt me-2"></i> Déconnexion</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger rounded-4 mb-4 shadow-sm border-0"><i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success rounded-4 mb-4 shadow-sm border-0"><i class="fa-solid fa-check-circle me-2"></i> Opération effectuée avec succès.</div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <div class="col-md-4 col-xl-2">
                <div class="chart-card bg-primary text-white border-0">
                    <i class="fa-solid fa-piggy-bank stat-icon-bg"></i>
                    <small class="opacity-75 fw-bold text-uppercase">Mon Épargne</small>
                    <h4 class="fw-800 m-0 mt-2"><?php echo number_format($monEpargne, 0, ',', ' '); ?> <span class="fs-6">XAF</span></h4>
                </div>
            </div>
            <div class="col-md-4 col-xl-2">
                <div class="chart-card assurance-card border-0">
                    <i class="fa-solid fa-shield-heart stat-icon-bg"></i>
                    <small class="opacity-75 fw-bold text-uppercase text-white">Mon Assurance</small>
                    <h4 class="fw-800 m-0 mt-2 text-white"><?php echo number_format($monAssurance, 0, ',', ' '); ?> <span class="fs-6 text-white">XAF</span></h4>
                </div>
            </div>
            <div class="col-md-4 col-xl-2">
                <div class="chart-card border-start border-danger border-4">
                    <i class="fa-solid fa-file-invoice-dollar stat-icon-bg text-danger"></i>
                    <small class="text-muted fw-bold text-uppercase">Mes Dettes</small>
                    <h4 class="fw-800 m-0 mt-2 text-danger"><?php echo number_format($mesDettes, 0, ',', ' '); ?> <span class="fs-6">XAF</span></h4>
                    <small class="x-small text-muted">(Reste à payer)</small>
                </div>
            </div>
            <div class="col-md-4 col-xl-2">
                <div class="chart-card border-start border-success border-4">
                    <i class="fa-solid fa-hand-holding-dollar stat-icon-bg text-success"></i>
                    <small class="text-muted fw-bold text-uppercase">Cotisé</small>
                    <h4 class="fw-800 m-0 mt-2 text-success"><?php echo number_format($monCotise, 0, ',', ' '); ?> <span class="fs-6">XAF</span></h4>
                </div>
            </div>
            
          <div class="col-md-4 col-xl-2">
    <a href="projets.php" class="text-decoration-none">
        <div class="chart-card project-card-kpi border-0 h-100">
            <i class="fa-solid fa-rocket stat-icon-bg text-white"></i>
            <div class="d-flex justify-content-between align-items-start">
                <small class="opacity-75 fw-bold text-uppercase text-white">Financements Reçus</small>
                <i class="fa-solid fa-arrow-up-right-from-square text-white opacity-50 small"></i>
            </div>
            <h4 class="fw-800 m-0 mt-2 text-white"><?php echo number_format($financementRecu, 0, ',', ' '); ?> <span class="fs-6 text-white">XAF</span></h4>
            <small class="text-white opacity-50" style="font-size: 0.65rem;">(Prêts accordés + Projets versés)</small>
        </div>
    </a>
</div>

            <div class="col-md-4 col-xl-2">
                <div class="chart-card">
                    <i class="fa-solid fa-users stat-icon-bg text-primary"></i>
                    <small class="text-muted fw-bold text-uppercase">Membres</small>
                    <h4 class="fw-800 m-0 mt-2"><?php echo $nbMembres; ?></h4>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="chart-card">
                    <h6 class="fw-bold small text-muted mb-4">ÉTAT DU RÉSEAU</h6>
                    <div class="chart-container">
                        <canvas id="chartPie"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-5 col-md-6">
                <div class="chart-card">
                    <h6 class="fw-bold small text-muted mb-4">ÉVOLUTION ÉPARGNE (FCFA)</h6>
                    <div class="chart-container">
                        <canvas id="chartLine"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12">
                <div class="chart-card">
                    <h6 class="fw-bold small text-muted mb-4">MON ASSIDUITÉ (%)</h6>
                    <div class="chart-container">
                        <canvas id="chartBar"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        </div>

    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header border-bottom-0">
            <h5 class="modal-title fw-bold text-primary" id="messageModalLabel">Envoyer un message</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          
          <form method="POST" action="index.php">
            <div class="modal-body">
                <input type="hidden" name="action_message" value="1">
                <input type="hidden" name="destinataire_id" value="1"> 

                <div class="mb-3">
                    <label for="message-text" class="col-form-label fw-bold">Votre message :</label>
                    <textarea name="message" class="form-control bg-light border-0" id="message-text" rows="5" required placeholder="Écrivez votre message ici..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-paper-plane me-2"></i>Envoyer</button>
            </div>
          </form>

        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar Mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        window.onload = function() {
            // Configuration Charts...
            const globalOptions = { maintainAspectRatio: false, responsive: true, plugins: { legend: { position: 'bottom' } } };           
            
            // Chart Pie
            new Chart(document.getElementById('chartPie'), {
                type: 'doughnut',
                data: {
                    labels: ['Actifs', 'Alertes'],
                    datasets: [{
                        data: [<?php echo $nbMembres; ?>, <?php echo $notifsCount; ?>],
                        backgroundColor: ['#00c853', '#ff9100'],
                        borderWidth: 0
                    }]
                },
                options: { ...globalOptions, cutout: '75%' }
            });

            // Chart Line (Exemple)
            new Chart(document.getElementById('chartLine'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
                    datasets: [{
                        label: 'Épargne',
                        data: [20000, 45000, 38000, 80000, 75000, <?php echo $monEpargne; ?>],
                        borderColor: '#1a237e', borderWidth: 3, fill: true,
                        backgroundColor: 'rgba(26, 35, 126, 0.05)', tension: 0.4
                    }]
                }, options: globalOptions
            });

            // Chart Bar (Exemple)
            new Chart(document.getElementById('chartBar'), {
                type: 'bar',
                data: {
                    labels: ['Assiduité', 'Ponctualité'],
                    datasets: [{
                        label: 'Score %', data: [95, 100],
                        backgroundColor: ['#ff9100', '#00b0ff'], borderRadius: 10
                    }]
                }, options: globalOptions
            });
        };
    </script>
</body>
</html>