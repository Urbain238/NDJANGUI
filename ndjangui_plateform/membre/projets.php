<?php
session_start();
require_once '../config/database.php';

// --- 1. SÉCURITÉ ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$pdo = Database::getConnection();
$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'] ?? 'Membre';

// --- 2. RÉCUPÉRATION DES DONNÉES ---
$projets = [];
try {
    $sql = "
        SELECT p.*, 
               m.nom_complet as createur,
               -- Compteurs de votes (pour info visuelle)
               (SELECT COUNT(*) FROM votes_decisions v WHERE v.reference_id = p.id AND v.type_vote = 'projet' AND v.choix = 'pour') as nb_pour,
               (SELECT COUNT(*) FROM votes_decisions v WHERE v.reference_id = p.id AND v.type_vote = 'projet' AND v.choix = 'contre') as nb_contre,
               -- Vérification si l'utilisateur a voté
               CASE WHEN EXISTS (SELECT 1 FROM votes_decisions v2 WHERE v2.reference_id = p.id AND v2.membre_id = :uid AND v2.type_vote = 'projet') THEN 1 ELSE 0 END as a_vote
        FROM projets p
        JOIN membres m ON p.membre_id = m.id
        ORDER BY FIELD(p.statut, 'en_attente', 'approuve', 'rejete'), p.date_creation DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $projets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projets | NDJANGUI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            --primary: #0f172a;
            --primary-light: #1e293b;
            --accent: #6366f1; /* Indigo vibrant */
            --accent-hover: #4f46e5;
            --bg-body: #f8fafc;
            --success-soft: #dcfce7;
            --success-text: #166534;
            --card-border: #f1f5f9;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: #334155;
            padding-bottom: 80px;
        }

        /* Navbar Style */
        .navbar-glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.4rem;
            letter-spacing: -0.5px;
        }

        /* Hero Section */
        .page-header {
            margin-bottom: 3rem;
            position: relative;
        }
        
        .page-title {
            font-weight: 800;
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        /* Cards Design */
        .project-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .project-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
            border-color: #e2e8f0;
        }

        /* Badge "A Voté" flottant */
        .voted-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--success-soft);
            color: var(--success-text);
            font-size: 0.7rem;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .card-body-custom {
            padding: 1.75rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        /* User Header in Card */
        .creator-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.25rem;
        }

        .avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--primary);
        }

        .project-status {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }
        .status-en_attente { background: #fff7ed; color: #ea580c; }
        .status-approuve { background: #f0fdf4; color: #166534; }
        .status-rejete { background: #fef2f2; color: #dc2626; }

        .card-title {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .amount-highlight {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(to right, var(--primary), #475569);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            display: block;
        }

        .card-text {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-footer-custom {
            padding: 1.25rem 1.75rem;
            background: #fff;
            border-top: 1px dashed #e2e8f0;
        }

        /* Buttons */
        .btn-card {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-vote-primary {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
        }
        .btn-vote-primary:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
            color: white;
        }

        .btn-vote-secondary {
            background-color: #fff;
            color: var(--primary);
            border: 2px solid #e2e8f0;
        }
        .btn-vote-secondary:hover {
            border-color: var(--primary);
            background-color: #f8fafc;
            color: var(--primary);
        }

        .btn-add-project {
            background: var(--accent);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            border: none;
            transition: 0.3s;
            text-decoration: none;
        }
        .btn-add-project:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            color: white;
        }
        /* Conteneur du menu */
.custom-dropdown {
    min-width: 220px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(0,0,0,0.05) !important;
}

/* Style des éléments du menu */
.custom-dropdown .dropdown-item {
    color: #475569;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

/* Effet au survol */
.custom-dropdown .dropdown-item:hover {
    background-color: #f8fafc;
    color: #4f46e5;
    transform: translateX(5px);
}

.custom-dropdown .dropdown-item.text-danger:hover {
    background-color: #fef2f2;
    color: #dc2626 !important;
}

/* Petite boîte pour l'icône */
.icon-box {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 0.85rem;
}

/* Couleurs douces pour les icônes */
.bg-danger-soft {
    background-color: rgba(220, 38, 38, 0.1);
}

.bg-light {
    background-color: #f1f5f9 !important;
}
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-glass">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                 <i class="fa-solid fa-handshake-simple fs-2 me-2 logo-icon"></i> NDJANGUI
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none dropdown-toggle fw-bold text-dark p-0" type="button" data-bs-toggle="dropdown">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar" style="width:35px; height:35px; font-size:0.9rem;">
                                <?php echo strtoupper(substr($user_nom, 0, 1)); ?>
                            </div>
                            <span class="d-none d-md-block"><?php echo htmlspecialchars($user_nom); ?></span>
                        </div>
                    </button>
                   <ul class="dropdown-menu dropdown-menu-end shadow-xl border-0 mt-3 p-2 rounded-4 custom-dropdown">
    <li class="px-3 py-2">
        <span class="text-uppercase small fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05rem;">Mon Espace</span>
    </li>
    <li>
        <a class="dropdown-item rounded-3 d-flex align-items-center py-2" href="index.php">
            <div class="icon-box me-3 bg-light text-primary">
                <i class="fa-solid fa-house-chimney-user"></i>
            </div>
            <span>Tableau de bord</span>
        </a>
    </li>
    <li><hr class="dropdown-divider opacity-50"></li>
    <li>
        <a class="dropdown-item rounded-3 d-flex align-items-center py-2 text-danger" href="../logout.php">
            <div class="icon-box me-3 bg-danger-soft text-danger">
                <i class="fa-solid fa-power-off"></i>
            </div>
            <span class="fw-semibold">Déconnexion</span>
        </a>
    </li>
</ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <div class="row align-items-end page-header">
            <div class="col-md-7">
                <h1 class="page-title animate__animated animate__fadeInDown">Investissements</h1>
                <p class="text-muted fs-5 mb-0 animate__animated animate__fadeInUp animate__delay-1s">
                    Analysez les opportunités et participez au développement de la communauté.
                </p>
            </div>
            <div class="col-md-5 text-md-end mt-4 mt-md-0 animate__animated animate__fadeIn animate__delay-1s">
                <a href="nouveau_projet.php" class="btn-add-project">
                    <i class="fa-solid fa-plus me-2"></i> Soumettre un projet
                </a>
            </div>
        </div>

        <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger rounded-3 shadow-sm mb-4"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if(empty($projets)): ?>
                <div class="col-12 text-center py-5">
                    <div class="bg-white rounded-4 p-5 shadow-sm border border-light mx-auto" style="max-width: 500px;">
                        <i class="fa-solid fa-folder-open fa-3x text-secondary opacity-25 mb-3"></i>
                        <h4 class="fw-bold text-dark">Aucun projet actif</h4>
                        <p class="text-muted">Soyez le premier à proposer un projet à la communauté.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($projets as $p): 
                    $date = new DateTime($p['date_creation']);
                    
                    // Textes et classes selon statut
                    $status_text = match($p['statut']) {
                        'en_attente' => 'En vote',
                        'approuve' => 'Validé',
                        'rejete' => 'Refusé',
                        default => 'Inconnu'
                    };
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="project-card animate__animated animate__fadeInUp">
                        
                        <?php if($p['a_vote']): ?>
                            <div class="voted-badge">
                                <i class="fa-solid fa-check-circle"></i> A voté
                            </div>
                        <?php endif; ?>

                        <div class="card-body-custom">
                            <div class="creator-info">
                                <div class="avatar">
                                    <?php echo strtoupper(substr($p['createur'], 0, 1)); ?>
                                </div>
                                <div class="d-flex flex-column">
                                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Porteur</small>
                                    <span class="fw-bold text-dark small"><?php echo htmlspecialchars($p['createur']); ?></span>
                                </div>
                                <div class="ms-auto">
                                    <span class="project-status status-<?php echo $p['statut']; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                            </div>

                            <h5 class="card-title"><?php echo htmlspecialchars($p['titre']); ?></h5>
                            <span class="amount-highlight">
                                <?php echo number_format($p['montant_demande'], 0, ',', ' '); ?> <small class="fs-6 text-muted fw-normal">FCFA</small>
                            </span>

                            <p class="card-text">
                                <?php echo htmlspecialchars($p['description']); ?>
                            </p>

                            <?php if($p['statut'] === 'approuve' && $p['montant_verse'] > 0): ?>
                                <div class="mt-auto p-2 bg-success bg-opacity-10 rounded-3 text-center border border-success border-opacity-10">
                                    <small class="text-success fw-bold"><i class="fa-solid fa-coins me-1"></i> Financé : <?php echo number_format($p['montant_verse'], 0, ',', ' '); ?> FCFA</small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer-custom">
                            <div class="d-flex justify-content-between text-muted small mb-3">
                                <span><i class="fa-regular fa-clock me-1"></i> <?php echo $date->format('d/m/Y'); ?></span>
                                <?php if($p['cercle_id']): ?>
                                    <span><i class="fa-solid fa-layer-group me-1"></i> Cercle #<?php echo $p['cercle_id']; ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if($p['statut'] === 'en_attente'): ?>
                                <?php if($p['a_vote']): ?>
                                    <a href="voter_projet.php?id=<?php echo $p['id']; ?>" class="btn-card btn-vote-secondary">
                                        <i class="fa-solid fa-eye"></i> Voir détails & mon vote
                                    </a>
                                <?php else: ?>
                                    <a href="voter_projet.php?id=<?php echo $p['id']; ?>" class="btn-card btn-vote-primary">
                                        <i class="fa-solid fa-gavel"></i> Examiner & Voter
                                    </a>
                                <?php endif; ?>
                            
                            <?php else: ?>
                                <a href="voter_projet.php?id=<?php echo $p['id']; ?>" class="btn-card btn-light text-muted border">
                                    <i class="fa-solid fa-list-check"></i> Voir l'historique
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>