<?php
session_start();
date_default_timezone_set('Africa/Douala');

// =================================================================================
// 1. CONFIGURATION & CONNEXION BDD
// =================================================================================

$host = 'localhost';
$dbname = 'ndjangui_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div style='color:white; background:red; text-align:center; padding:20px; font-family:sans-serif;'>
        <strong>Erreur Critique :</strong> Impossible de se connecter à la base de données.<br>
        <em>Vérifiez que WAMP/XAMPP est lancé et que la base 'ndjangui_db' existe.</em>
        </div>");
}

// Simulation Admin (A remplacer par votre check de session réel)
if (!isset($_SESSION['user_id'])) {
    // Redirection si pas connecté (Optionnel)
    // header("Location: login.php"); exit;
}
$current_admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; 

$msg = "";
$msgType = ""; 

// =================================================================================
// FONCTION : VÉRIFICATION HIERARCHIQUE (ANCESTRY CHECK)
// =================================================================================
function is_ancestor($pdo, $admin_id, $target_id) {
    // Vérifie si $admin_id est le créateur (ou le créateur du créateur...) de $target_id
    $stmt = $pdo->prepare("SELECT parrain_id FROM membres WHERE id = ?");
    $stmt->execute([$target_id]);
    $parent = $stmt->fetchColumn();

    $safety = 0;
    while ($parent && $safety < 20) { // Augmenté à 20 pour les structures profondes
        if ($parent == $admin_id) {
            return true; 
        }
        $stmt->execute([$parent]);
        $parent = $stmt->fetchColumn();
        $safety++;
    }
    return false;
}

// =================================================================================
// LOGIQUE DE TRAITEMENT (BACKEND)
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $target_id = intval($_POST['target_id']);
    
    try {
        // --- CAS 1 : GESTION MEMBRE (TOGGLE SUSPEND/ACTIF) ---
        if ($_POST['action'] === 'toggle_membre') {
            
            // 1. RECUPERATION INFOS CIBLE
            $stmt = $pdo->prepare("SELECT nom_complet, role_id, parrain_id, statut FROM membres WHERE id = ?");
            $stmt->execute([$target_id]);
            $cible = $stmt->fetch();

            if (!$cible) throw new Exception("Ce membre n'existe pas.");

            // 2. RÈGLES DE SÉCURITÉ STRICTES

            // A. Suicide interdit
            if ($target_id == $current_admin_id) {
                throw new Exception("Opération impossible : Vous ne pouvez pas modifier votre propre statut.");
            }

            // B. Vérification du Pouvoir Hiérarchique (Droit de vie ou de mort numérique)
            // On est le chef si on est le parrain direct OU un ancêtre
            $is_boss = ($cible['parrain_id'] == $current_admin_id) || is_ancestor($pdo, $current_admin_id, $target_id);

            // Si la cible est un Admin (Role 1), il faut impérativement être son "Créateur Suprême"
            if ($cible['role_id'] == 1 && !$is_boss) {
                throw new Exception("⛔ ACCÈS REFUSÉ : Vous ne pouvez pas toucher à un autre Administrateur hors de votre lignée.");
            }
            
            // C. Protection Parricide (On ne touche pas à son chef)
            if (is_ancestor($pdo, $target_id, $current_admin_id)) {
                throw new Exception("⛔ TRAHISON : Vous ne pouvez pas suspendre votre supérieur hiérarchique.");
            }

            // 3. LA BASCULE (TOGGLE)
            $pdo->beginTransaction();

            try {
                // Détermine le nouveau statut
                $new_statut = ($cible['statut'] === 'suspendu') ? 'actif' : 'suspendu';
                
                $sqlToggle = "UPDATE membres SET statut = ? WHERE id = ?";
                $stmt = $pdo->prepare($sqlToggle);
                $stmt->execute([$new_statut, $target_id]);

                $pdo->commit();
                
                if ($new_statut === 'suspendu') {
                    $msg = "Succès : Le compte de <b>" . htmlspecialchars($cible['nom_complet']) . "</b> a été <span class='text-danger'>SUSPENDU</span>.";
                    $msgType = "warning";
                } else {
                    $msg = "Succès : Le compte de <b>" . htmlspecialchars($cible['nom_complet']) . "</b> a été <span class='text-success'>RÉACTIVÉ</span>.";
                    $msgType = "success";
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e; 
            }
        }
        // --- CAS 2 : SUPPRESSION CERCLE (SOFT DELETE) ---
        elseif ($_POST['action'] === 'delete_cercle') {
            $sql = "UPDATE cercles SET statut = 'archive' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$target_id]);
            $msg = "Le cercle a été clôturé et archivé avec succès.";
            $msgType = "success";
        }
        // --- CAS 3 : SUPPRESSION PROJET (HARD DELETE SECURISE) ---
        elseif ($_POST['action'] === 'delete_projet') {
            try {
                $sql = "DELETE FROM projets WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$target_id]);
                $msg = "Le projet a été supprimé définitivement.";
                $msgType = "success";
            } catch (PDOException $pdoEx) {
                // Gestion spécifique erreur Foreign Key (ex: cotisations liées)
                if ($pdoEx->getCode() == '23000') {
                    throw new Exception("Impossible de supprimer ce projet car il contient des cotisations ou des données liées. Supprimez d'abord les éléments dépendants.");
                } else {
                    throw $pdoEx;
                }
            }
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        $msg = "Erreur : " . $errorMessage;
        $msgType = "danger";
    }
}

// Récupération des données pour affichage
// Membres : On exclut les archivés définitivement
$membres = $pdo->query("SELECT id, nom_complet, email, role_id, statut, photo_profil_url FROM membres WHERE statut != 'archive' ORDER BY id DESC LIMIT 50")->fetchAll();

// Cercles
$cercles = $pdo->query("SELECT id, nom_cercle, type_tontine, statut, montant_unitaire FROM cercles WHERE statut != 'archive' ORDER BY id DESC LIMIT 20")->fetchAll();

// Projets
$projets = $pdo->query("SELECT p.id, p.titre, p.montant_demande, m.nom_complet as auteur FROM projets p LEFT JOIN membres m ON p.membre_id = m.id ORDER BY p.id DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration | Gestion Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: #1e293b;
            --danger-red: #ef4444;
            --success-green: #10b981;
            --text-main: #f1f5f9;
            --text-muted: #cbd5e1;
            --text-highlight: #ffffff;
            --accent: #6366f1;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
        }
        /* HEADER */
        .page-header {
            background: linear-gradient(to right, #1e1b4b, #312e81);
            padding: 40px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }
        h1, h2, h3, h4, h5 { color: var(--text-highlight); }
        /* TABS */
        .nav-pills .nav-link {
            color: var(--text-muted);
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            margin-right: 10px;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .nav-pills .nav-link:hover {
            color: var(--text-highlight);
            border-color: var(--text-highlight);
        }
        .nav-pills .nav-link.active {
            background-color: var(--danger-red);
            color: white;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
            border-color: var(--danger-red);
        }
        /* CARDS & TABLES */
        .custom-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .table-dark-custom {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-muted);
            --bs-table-border-color: rgba(255,255,255,0.1);
        }
        .table-dark-custom th {
            color: var(--text-highlight);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            padding: 20px;
            background-color: rgba(255,255,255,0.02);
        }
        .table-dark-custom td {
            vertical-align: middle;
            padding: 15px 20px;
            color: var(--text-main);
        }
        .avatar-sm {
            width: 40px; height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.2);
            background-color: #334155;
        }
        /* ACTIONS */
        .btn-action {
            width: 35px; height: 35px;
            border-radius: 8px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-suspend {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }
        .btn-suspend:hover {
            background: var(--danger-red);
            color: white;
            transform: scale(1.1);
        }
        .btn-restore {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.3);
        }
        .btn-restore:hover {
            background: var(--success-green);
            color: white;
            transform: scale(1.1);
        }
        
        .badge-role {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        /* MODAL */
        .modal-content {
            background-color: #1e293b;
            border: 1px solid rgba(255,255,255,0.2);
            color: var(--text-main);
        }
        .form-control-search {
            background-color: #0f172a;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        .form-control-search:focus {
            background-color: #0f172a;
            color: white;
            border-color: var(--accent);
            box-shadow: none;
        }
    </style>
</head>
<body>

    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold mb-1"><i class="fa-solid fa-user-shield me-3 text-danger"></i>Gestion des Status</h1>
                    <p class="text-muted mb-0" style="color: #cbd5e1 !important;">Suspension, réactivation et archivage.</p>
                </div>
                <a href="index.php" class="btn btn-outline-light rounded-pill px-4">
                    <i class="fa-solid fa-arrow-left me-2"></i>Retour Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        
        <?php if(!empty($msg)): ?>
        <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show border-0 shadow mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid <?php echo ($msgType == 'success') ? 'fa-check-circle' : 'fa-triangle-exclamation'; ?> fs-4 me-3"></i>
                <div>
                    <strong><?php echo ($msgType == 'success') ? 'Opération réussie' : 'Information'; ?></strong><br>
                    <?php echo $msg; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: invert(1);"></button>
        </div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-membres-tab" data-bs-toggle="pill" data-bs-target="#pills-membres" type="button"><i class="fa-solid fa-users me-2"></i>Membres</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-cercles-tab" data-bs-toggle="pill" data-bs-target="#pills-cercles" type="button"><i class="fa-solid fa-circle-nodes me-2"></i>Cercles</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-projets-tab" data-bs-toggle="pill" data-bs-target="#pills-projets" type="button"><i class="fa-solid fa-lightbulb me-2"></i>Projets</button>
            </li>
        </ul>

        <div class="tab-content" id="pills-tabContent"> 
            
            <div class="tab-pane fade show active" id="pills-membres">
                <div class="custom-card">
                    <div class="p-4 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-white">Liste des Membres</h5>
                        <input type="text" id="searchMembre" class="form-control form-control-sm w-auto form-control-search" placeholder="Rechercher...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Identité</th>
                                    <th>Rôle</th>
                                    <th>Statut Actuel</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tableMembres">
                                <?php foreach($membres as $m): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo !empty($m['photo_profil_url']) ? htmlspecialchars($m['photo_profil_url']) : 'assets/img/default.png'; ?>" class="avatar-sm me-3" alt="Photo">
                                            <div>
                                                <div class="fw-bold text-white"><?php echo htmlspecialchars($m['nom_complet']); ?></div>
                                                <div class="small" style="color: #94a3b8;"><?php echo htmlspecialchars($m['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $roles = [1=>'Admin', 2=>'Président', 3=>'Secrétaire', 4=>'Censeur', 5=>'Membre'];
                                            echo '<span class="badge-role">'.($roles[$m['role_id']] ?? 'Inconnu').'</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if($m['statut']=='actif'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Actif</span>
                                        <?php elseif($m['statut']=='suspendu'): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">SUSPENDU</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary"><?php echo htmlspecialchars($m['statut']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if($m['statut'] === 'suspendu'): ?>
                                            <button class="btn-action btn-restore" 
                                                onclick="confirmAction('membre', <?php echo $m['id']; ?>, '<?php echo htmlspecialchars($m['nom_complet'], ENT_QUOTES); ?>', 'restore')"
                                                title="Réactiver le compte">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-action btn-suspend" 
                                                onclick="confirmAction('membre', <?php echo $m['id']; ?>, '<?php echo htmlspecialchars($m['nom_complet'], ENT_QUOTES); ?>', 'suspend')"
                                                title="Suspendre le compte">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pills-cercles">
                <div class="custom-card">
                    <div class="p-4 border-bottom border-secondary border-opacity-25">
                        <h5 class="mb-0 fw-bold text-white">Gestion des Cercles</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nom du Cercle</th>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cercles as $c): ?>
                                <tr>
                                    <td class="fw-bold text-white"><?php echo htmlspecialchars($c['nom_cercle']); ?></td>
                                    <td style="color: #cbd5e1;"><?php echo htmlspecialchars($c['type_tontine']); ?></td>
                                    <td class="text-success fw-bold"><?php echo number_format($c['montant_unitaire'], 0, ',', ' '); ?> FCFA</td>
                                    <td class="text-end">
                                        <button class="btn-action btn-suspend" onclick="confirmAction('cercle', <?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['nom_cercle'], ENT_QUOTES); ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pills-projets">
                 <div class="custom-card">
                    <div class="p-4 border-bottom border-secondary border-opacity-25">
                        <h5 class="mb-0 fw-bold text-white">Projets Soumis</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Titre Projet</th>
                                    <th>Demandeur</th>
                                    <th>Montant</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($projets as $p): ?>
                                <tr>
                                    <td class="fw-bold text-white"><?php echo htmlspecialchars($p['titre']); ?></td>
                                    <td style="color: #cbd5e1;"><?php echo htmlspecialchars($p['auteur'] ?? 'Inconnu'); ?></td>
                                    <td class="fw-bold" style="color: #cbd5e1;"><?php echo number_format($p['montant_demande'], 0, ',', ' '); ?> FCFA</td>
                                    <td class="text-end">
                                        <button class="btn-action btn-suspend" onclick="confirmAction('projet', <?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['titre'], ENT_QUOTES); ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <form method="POST" action="">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold text-white" id="modalTitle">CONFIRMATION</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <i id="modalIcon" class="fa-solid fa-circle-exclamation text-warning fs-1 mb-3"></i>
                        <h3 class="fw-bold text-white mb-2" id="deleteTargetName">...</h3>
                        
                        <p id="modalDesc" class="text-muted mb-3">Voulez-vous vraiment effectuer cette action ?</p>
                        
                        <div class="alert alert-warning bg-opacity-10 border-opacity-25 text-warning small mx-3" id="deleteWarningText">
                            Cette action est sensible.
                        </div>
                        
                        <input type="hidden" name="action" id="deleteAction">
                        <input type="hidden" name="target_id" id="deleteId">
                    </div>
                    <div class="modal-footer border-0 justify-content-center pb-4">
                        <button type="button" class="btn btn-outline-light rounded-pill px-4 text-muted border-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" id="confirmBtn">Confirmer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction Unifiée pour gérer toutes les actions
        function confirmAction(type, id, name, statusMode = null) {
            
            // Éléments du DOM
            const inputId = document.getElementById('deleteId');
            const inputAction = document.getElementById('deleteAction');
            const targetName = document.getElementById('deleteTargetName');
            const warningText = document.getElementById('deleteWarningText');
            const confirmBtn = document.getElementById('confirmBtn');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');

            // Reset UI
            inputId.value = id;
            targetName.textContent = name;
            warningText.style.display = 'block';
            warningText.className = "alert small mx-3"; 

            if (type === 'membre') {
                inputAction.value = 'toggle_membre';
                
                if (statusMode === 'suspend') {
                    // Mode SUSPENSION
                    modalTitle.innerText = "SUSPENSION DU COMPTE";
                    modalIcon.className = "fa-solid fa-ban text-danger fs-1 mb-3";
                    warningText.className += " alert-danger bg-danger bg-opacity-10 border-danger text-danger";
                    warningText.innerHTML = "<strong>Attention :</strong> Ce membre ne pourra plus se connecter.<br>Ses données historiques restent conservées.";
                    confirmBtn.className = "btn btn-danger rounded-pill px-4 fw-bold";
                    confirmBtn.innerText = "Suspendre";
                } else {
                    // Mode REACTIVATION
                    modalTitle.innerText = "RÉACTIVATION DU COMPTE";
                    modalIcon.className = "fa-solid fa-check-circle text-success fs-1 mb-3";
                    warningText.className += " alert-success bg-success bg-opacity-10 border-success text-success";
                    warningText.innerHTML = "Ce membre pourra à nouveau se connecter et participer aux activités.";
                    confirmBtn.className = "btn btn-success rounded-pill px-4 fw-bold";
                    confirmBtn.innerText = "Réactiver";
                }

            } else if (type === 'cercle') {
                inputAction.value = 'delete_cercle';
                modalTitle.innerText = "ARCHIVAGE CERCLE";
                modalIcon.className = "fa-solid fa-box-archive text-warning fs-1 mb-3";
                warningText.className += " alert-warning bg-warning bg-opacity-10 border-warning text-warning";
                warningText.innerHTML = "Le cercle sera fermé. Il ne sera plus visible dans la liste active.";
                confirmBtn.className = "btn btn-warning rounded-pill px-4 fw-bold text-dark";
                confirmBtn.innerText = "Archiver";

            } else if (type === 'projet') {
                inputAction.value = 'delete_projet';
                modalTitle.innerText = "SUPPRESSION DÉFINITIVE";
                modalIcon.className = "fa-solid fa-trash-can text-danger fs-1 mb-3";
                warningText.className += " alert-danger bg-danger bg-opacity-10 border-danger text-danger";
                warningText.innerHTML = "<strong>IRRÉVERSIBLE :</strong> Toutes les données de ce projet seront effacées.";
                confirmBtn.className = "btn btn-danger rounded-pill px-4 fw-bold";
                confirmBtn.innerText = "Supprimer";
            }
            
            var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            myModal.show();
        }

        // Script de recherche instantanée
        document.getElementById('searchMembre').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#tableMembres tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>