<?php
session_start();
date_default_timezone_set('Africa/Douala');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$pdo = Database::getConnection();
$membre_id = $_SESSION['user_id'];

// --- 1. CALCUL DU FONDS DE SOLIDARITÉ DISPONIBLE (ASSURANCES) ---
$stmtSomme = $pdo->query("SELECT SUM(montant_paye) as total_caisse FROM assurances");
$rowSomme = $stmtSomme->fetch(PDO::FETCH_ASSOC);
$plafond_disponible = $rowSomme['total_caisse'] ? floatval($rowSomme['total_caisse']) : 0.00;

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_lancer'])) {
    $titre = htmlspecialchars($_POST['titre']);
    $montant = floatval($_POST['montant']);
    $description = htmlspecialchars($_POST['description']);

    // Validation de base
    if ($montant > 0 && !empty($titre) && !empty($description)) {
        
        // --- 2. VERIFICATION DU PLAFOND ---
        if ($montant > $plafond_disponible) {
            $message = "insufficient_funds";
        } else {
            try {
                // CORRECTION ICI : Statut mis à 'en_attente' pour correspondre à l'ENUM de la DB
                $stmt = $pdo->prepare("INSERT INTO projets (membre_id, titre, montant_demande, description, date_creation, statut) VALUES (?, ?, ?, ?, NOW(), 'en_attente')");
                $stmt->execute([$membre_id, $titre, $montant, $description]);
                
                // --- NOTIFICATIONS ---
                $new_projet_id = $pdo->lastInsertId();
                
                // On notifie les autres membres (sauf celui qui a créé le projet)
                $stmtDest = $pdo->prepare("SELECT id FROM membres WHERE id != ? AND statut_validation != 'rejete'");
                $stmtDest->execute([$membre_id]);
                $destinataires = $stmtDest->fetchAll();

                $stmtNotif = $pdo->prepare("INSERT INTO notifications (membre_id, message, type, date_creation, statut) VALUES (?, ?, 'info', NOW(), 'non_lu')");
                $msg_notif = "Nouveau projet : " . $titre . ". Votez maintenant dans l'espace projets.";

                foreach ($destinataires as $dest) {
                    $stmtNotif->execute([$dest['id'], $msg_notif]);
                }
                
                $message = "success";
            } catch (PDOException $e) {
                $message = "error";
                // Pour le débogage seulement (à retirer en prod)
                // echo $e->getMessage();
            }
        }
    } else {
        $message = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lancer un Projet | NDJANGUI Premium</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0f172a;
            --accent-color: #3b82f6;
            --accent-hover: #2563eb;
            --bg-color: #f8fafc;
            --card-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
            --input-bg: #f1f5f9;
        }

        body { 
            background-color: var(--bg-color);
            background-image: radial-gradient(at 0% 0%, hsla(217,91%,93%,1) 0, transparent 50%), 
                              radial-gradient(at 100% 0%, hsla(225,89%,94%,1) 0, transparent 50%);
            font-family: 'Inter', sans-serif; 
            color: #334155;
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        /* Navigation Minimaliste */
        .top-nav { padding: 1.5rem 0 2.5rem 0; }
        .btn-back {
            color: #64748b; font-weight: 600; font-size: 0.9rem; text-decoration: none;
            transition: all 0.2s; display: inline-flex; align-items: center;
        }
        .btn-back:hover { color: var(--primary-color); transform: translateX(-3px); }

        /* Carte Principale */
        .main-card {
            background: #ffffff; border-radius: 24px; box-shadow: var(--card-shadow);
            border: 1px solid rgba(255,255,255,0.8); overflow: hidden;
        }

        /* Formulaire */
        .form-section { padding: 3rem; }
        .form-label {
            font-size: 0.85rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.5px; color: #64748b; margin-bottom: 0.5rem;
        }
        .input-group-text {
            background: var(--input-bg); border: 1px solid transparent; color: #94a3b8; border-radius: 12px 0 0 12px;
        }
        .form-control {
            background: var(--input-bg); border: 1px solid transparent; padding: 0.8rem 1rem;
            font-weight: 500; color: var(--primary-color); border-radius: 12px; transition: all 0.3s ease;
        }
        .input-group .form-control { border-radius: 0 12px 12px 0; }
        .input-group .input-group-text + .form-control { border-left: none; }
        .form-control:focus {
            background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); border-color: #e2e8f0;
        }
        .form-control::placeholder { color: #cbd5e1; font-weight: 400; }

        /* Bouton Submit */
        .btn-launch {
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%);
            border: none; color: white; padding: 1rem; border-radius: 12px; font-weight: 600;
            letter-spacing: 0.3px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease; width: 100%;
        }
        .btn-launch:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4); }

        /* Sidebar Info */
        .info-sidebar {
            background: #f8fafc; border-left: 1px solid #f1f5f9; padding: 3rem 2rem; position: relative;
        }
        .step-card { display: flex; align-items: flex-start; margin-bottom: 2rem; position: relative; }
        .step-icon {
            width: 40px; height: 40px; border-radius: 12px; background: white; color: var(--accent-color);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); flex-shrink: 0; margin-right: 1rem; border: 1px solid #e2e8f0;
        }

        /* Alertes */
        .alert-custom { border-radius: 12px; border: none; padding: 1rem; display: flex; align-items: center; font-size: 0.95rem; }
        .alert-success-custom { background: #dcfce7; color: #166534; }
        .alert-error-custom { background: #fee2e2; color: #991b1b; }
        .alert-warning-custom { background: #ffedd5; color: #9a3412; }

        @media (max-width: 991px) {
            .info-sidebar { border-left: none; border-top: 1px solid #f1f5f9; padding: 2rem; }
            .form-section { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-nav d-flex justify-content-between align-items-center">
        <a href="index.php" class="btn-back">
            <i class="fa-solid fa-chevron-left me-2"></i> Retour au Dashboard
        </a>
        <div class="d-flex align-items-center gap-2">
            <div class="badge bg-white text-dark border shadow-sm px-3 py-2 rounded-pill fw-normal">
                <i class="fa-solid fa-vault text-primary me-2"></i> 
                Caisse Dispo: <strong><?php echo number_format($plafond_disponible, 0, ',', ' '); ?> FCFA</strong>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-12">
            
            <div class="mb-4 text-center text-lg-start">
                <h2 class="fw-bold text-dark mb-1">Lancer un Projet</h2>
                <p class="text-muted">Soumettez votre idée à la communauté Njangui.</p>
            </div>

            <div class="main-card">
                <div class="row g-0">
                    
                    <div class="col-lg-7 form-section">
                        
                        <?php if($message === "success"): ?>
                            <div class="alert alert-custom alert-success-custom mb-4 animate-fade">
                                <i class="fa-solid fa-circle-check fa-lg me-3"></i>
                                <div>
                                    <strong>Félicitations !</strong><br>
                                    Votre projet est maintenant <strong>en attente</strong>. Les membres peuvent commencer à voter.
                                </div>
                            </div>
                        <?php elseif($message === "insufficient_funds"): ?>
                            <div class="alert alert-custom alert-warning-custom mb-4 animate-fade">
                                <i class="fa-solid fa-piggy-bank fa-lg me-3"></i>
                                <div>
                                    <strong>Budget Indisponible</strong><br>
                                    Le montant demandé (<?php echo number_format($montant, 0, ',', ' '); ?> FCFA) dépasse 
                                    la somme totale disponible en caisse assurance (<?php echo number_format($plafond_disponible, 0, ',', ' '); ?> FCFA).
                                </div>
                            </div>
                        <?php elseif($message === "error" || $message === "warning"): ?>
                            <div class="alert alert-custom alert-error-custom mb-4 animate-fade">
                                <i class="fa-solid fa-circle-exclamation fa-lg me-3"></i>
                                <div>
                                    <strong>Attention</strong><br>
                                    Veuillez vérifier tous les champs ou réessayer plus tard.
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" autocomplete="off">
                            
                            <div class="mb-4">
                                <label class="form-label">Nom du projet</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-lightbulb"></i></span>
                                    <input type="text" name="titre" class="form-control" value="<?php echo isset($_POST['titre']) ? htmlspecialchars($_POST['titre']) : ''; ?>" placeholder="Ex: Achat de matériel agricole" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Budget Requis</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-coins"></i></span>
                                    <input type="number" name="montant" class="form-control" 
                                           max="<?php echo $plafond_disponible; ?>" 
                                           placeholder="0" required>
                                    <span class="input-group-text bg-white border-start-0 text-dark fw-bold" style="border-radius: 0 12px 12px 0;">FCFA</span>
                                </div>
                                <div class="form-text mt-2 ms-1 text-primary small">
                                    <i class="fa-solid fa-circle-info me-1"></i> 
                                    Plafond actuel : <?php echo number_format($plafond_disponible, 0, ',', ' '); ?> FCFA
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Détails & Objectifs</label>
                                <textarea name="description" class="form-control" rows="5" placeholder="Expliquez pourquoi ce projet est important..." required style="resize: none;"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>

                            <div class="mt-5">
                                <button type="submit" name="btn_lancer" class="btn-launch">
                                    Soumettre le dossier <i class="fa-solid fa-arrow-right ms-2"></i>
                                </button>
                                <p class="text-center mt-3 text-muted small">
                                    En cliquant, vous acceptez les <a href="#" class="text-decoration-none fw-bold">règles de solidarité</a>.
                                </p>
                            </div>

                        </form>
                    </div>

                    <div class="col-lg-5 info-sidebar d-flex flex-column justify-content-center">
                        
                        <h5 class="fw-bold mb-4 text-dark">Processus de validation</h5>

                        <div class="step-card">
                            <div class="step-icon"><i class="fa-solid fa-paper-plane"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1 fs-6">1. Soumission</h6>
                                <p class="small text-muted mb-0">Vérification automatique de la disponibilité des fonds.</p>
                            </div>
                        </div>

                        <div class="step-card">
                            <div class="step-icon"><i class="fa-solid fa-check-to-slot"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1 fs-6">2. Vote Collégial</h6>
                                <p class="small text-muted mb-0">Le projet passe en statut <strong>Attente</strong> pour le vote.</p>
                            </div>
                        </div>

                        <div class="step-card">
                            <div class="step-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1 fs-6">3. Financement</h6>
                                <p class="small text-muted mb-0">Débit immédiat de la caisse assurance vers votre compte.</p>
                            </div>
                        </div>

                        <div class="mt-auto pt-4 border-top">
                            <div class="d-flex align-items-center p-3 rounded-3 bg-white border">
                                <i class="fa-solid fa-shield-halved text-success fs-4 me-3"></i>
                                <div>
                                    <span class="d-block fw-bold text-dark small">Garantie Njangui</span>
                                    <span class="d-block x-small text-muted" style="font-size: 0.75rem;">Fonds sécurisés et limités</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>