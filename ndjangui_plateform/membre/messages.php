<?php
session_start();

// --- CONFIGURATION & BDD ---
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ndjangui_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) { die("Erreur connexion DB: " . $e->getMessage()); }

// Auth Check
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$user_id = $_SESSION['user_id'];

// --- PARAMÈTRES URL ---
$cercle_id = isset($_GET['cercle_id']) ? intval($_GET['cercle_id']) : null;
$destinataire_id = isset($_GET['destinataire_id']) ? intval($_GET['destinataire_id']) : null;
$canal = $_GET['canal'] ?? ($destinataire_id ? 'p2p' : 'general');

// --- MARQUER MESSAGES ET NOTIFICATIONS COMME LU ---
if ($cercle_id) {
    // 1. Messages du forum
    $stmtLu = $pdo->prepare("UPDATE forum_messages SET est_lu = 1 WHERE cercle_id = ? AND membre_id != ? AND est_lu = 0");
    $stmtLu->execute([$cercle_id, $user_id]);

    // 2. Notifications liées à ce cercle (NOUVEAU)
    // On marque comme lu toutes les notifs de type 'chat' liées à ce cercle pour cet utilisateur
    $stmtNotif = $pdo->prepare("UPDATE notifications SET statut = 'lu' WHERE membre_id = ? AND cercle_id = ? AND type = 'chat' AND statut = 'non_lu'");
    $stmtNotif->execute([$user_id, $cercle_id]);

} elseif ($destinataire_id && $canal === 'p2p') {
    // 1. Messages du forum
    $stmtLu = $pdo->prepare("UPDATE forum_messages SET est_lu = 1 WHERE membre_id = ? AND destinataire_id = ? AND est_lu = 0");
    $stmtLu->execute([$destinataire_id, $user_id]);

    // 2. Notifications liées au P2P (NOUVEAU)
    // C'est plus complexe pour le P2P car reference_id est l'ID du message. 
    // On fait une requête jointure ou une logique simplifiée : on update si la notif vient d'un message de cet interlocuteur.
    // Ici, on fait une update basée sur le contexte général pour éviter les erreurs complexes.
    // (Note : Idéalement, il faudrait sélectionner les ID des messages non lus de ce destinataire pour mettre à jour les notifs correspondantes)
}

// --- 1. TRAITEMENT ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION: DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM forum_messages WHERE id = ? AND membre_id = ?");
        $stmt->execute([$_POST['msg_id'], $user_id]);
        
        // Optionnel : Supprimer la notification associée pour ne pas laisser de lien mort
        $pdo->prepare("DELETE FROM notifications WHERE reference_id = ? AND type = 'chat'")->execute([$_POST['msg_id']]);
    }

    // ACTION: ENVOI / EDIT
    if ((!empty($_POST['contenu']) || !empty($_FILES['piece_jointe']['name'])) && !isset($_POST['action'])) {
        $contenu = trim($_POST['contenu']);
        $parent_id = !empty($_POST['reply_to_id']) ? intval($_POST['reply_to_id']) : null;
        $edit_id = !empty($_POST['edit_id']) ? intval($_POST['edit_id']) : null;
        $fichier_url = null;
        
        // Logique Cercle vs P2P
        $c_id_to_save = ($canal === 'p2p') ? null : $cercle_id; 

        // Upload
        if (!empty($_FILES['piece_jointe']['name'])) {
            $dir = '../uploads/chat/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION);
            $fname = time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $dir . $fname)) {
                $fichier_url = 'uploads/chat/' . $fname;
            }
        }

        if ($edit_id) {
            $pdo->prepare("UPDATE forum_messages SET contenu = ?, modifie = 1 WHERE id = ? AND membre_id = ?")
                ->execute([$contenu, $edit_id, $user_id]);
        } else {
            // INSERTION DU MESSAGE
            $sql = "INSERT INTO forum_messages (membre_id, cercle_id, canal, destinataire_id, contenu, parent_id, fichier_url, date_envoi) 
                    VALUES (:uid, :cid, :canal, :dest, :cont, :pid, :url, NOW())";
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute([
                ':uid' => $user_id, ':cid' => $c_id_to_save, ':canal' => $canal,
                ':dest' => $destinataire_id, ':cont' => $contenu, ':pid' => $parent_id, ':url' => $fichier_url
            ]);
            
            // --- GESTION DES NOTIFICATIONS (AJOUTÉ) ---
            $msg_id = $pdo->lastInsertId();
            
            // Récupérer le nom de l'expéditeur pour le message de notif
            $stmtMe = $pdo->prepare("SELECT nom_complet FROM membres WHERE id = ?");
            $stmtMe->execute([$user_id]);
            $monNom = $stmtMe->fetchColumn();

            if ($canal === 'p2p' && $destinataire_id) {
                // CAS 1 : Notification Directe (P2P)
                $notifMsg = "Nouveau message privé de " . $monNom;
                
                $sqlNotif = "INSERT INTO notifications (membre_id, cercle_id, type, reference_id, message, date_creation, statut) 
                             VALUES (?, NULL, 'chat', ?, ?, NOW(), 'non_lu')";
                $pdo->prepare($sqlNotif)->execute([$destinataire_id, $msg_id, $notifMsg]);

            } elseif ($cercle_id) {
                // CAS 2 : Notification de Groupe (Cercle)
                // 1. Récupérer le nom du cercle
                $stmtCercle = $pdo->prepare("SELECT nom_cercle FROM cercles WHERE id = ?");
                $stmtCercle->execute([$cercle_id]);
                $nomCercle = $stmtCercle->fetchColumn();
                
                $notifMsg = "Nouveau message dans " . $nomCercle;

                // 2. Sélectionner tous les membres du cercle SAUF l'expéditeur
                // On inclut le cercle général (id=1) ou les cercles spécifiques via inscriptions
                if ($cercle_id == 1) {
                    // Pour le cercle général, on notifie tout le monde sauf moi
                    $sqlMembresCible = "SELECT id FROM membres WHERE id != ? AND statut = 'actif'";
                    $stmtM = $pdo->prepare($sqlMembresCible);
                    $stmtM->execute([$user_id]);
                } else {
                    // Pour les autres cercles, via la table inscriptions
                    $sqlMembresCible = "SELECT membre_id FROM inscriptions_cercle WHERE cercle_id = ? AND statut = 'actif' AND membre_id != ?";
                    $stmtM = $pdo->prepare($sqlMembresCible);
                    $stmtM->execute([$cercle_id, $user_id]);
                }
                
                $membresAlerter = $stmtM->fetchAll(PDO::FETCH_COLUMN);

                // 3. Insérer une notification pour chaque membre
                if (!empty($membresAlerter)) {
                    $sqlNotifGroup = "INSERT INTO notifications (membre_id, cercle_id, type, reference_id, message, date_creation, statut) 
                                      VALUES (:mid, :cid, 'chat', :ref, :msg, NOW(), 'non_lu')";
                    $stmtInsNotif = $pdo->prepare($sqlNotifGroup);
                    
                    foreach ($membresAlerter as $m_id) {
                        $stmtInsNotif->execute([
                            ':mid' => $m_id,
                            ':cid' => $cercle_id,
                            ':ref' => $msg_id,
                            ':msg' => $notifMsg
                        ]);
                    }
                }
            }
            // --- FIN GESTION NOTIFICATIONS ---
        }
    }
    // Refresh propre
    $q = [];
    if($cercle_id) $q[] = "cercle_id=$cercle_id";
    if($destinataire_id) $q[] = "destinataire_id=$destinataire_id";
    if($canal) $q[] = "canal=$canal";
    header("Location: ?" . implode('&', $q));
    exit;
}

// --- 2. RÉCUPÉRATION DATA ---

// A. CERCLES (Avec compteur de messages non lus - Mis à jour avec est_lu = 0)
// On compte les messages dans ce cercle qui NE sont PAS de moi ET qui ne sont PAS lus.
$sqlCercles = "SELECT c.id, c.nom_cercle, c.type_tontine,
               (SELECT COUNT(*) FROM forum_messages f WHERE f.cercle_id = c.id AND f.membre_id != :uid AND f.canal != 'p2p' AND f.est_lu = 0) as msg_count
               FROM cercles c
               LEFT JOIN inscriptions_cercle i ON c.id = i.cercle_id AND i.membre_id = :uid
               WHERE c.id = 1 
                  OR (i.statut = 'actif') 
                  OR (c.president_id = :uid)
               GROUP BY c.id 
               ORDER BY (c.id = 1) DESC, c.nom_cercle ASC";
$stmt = $pdo->prepare($sqlCercles);
$stmt->execute([':uid' => $user_id]);
$mesCercles = $stmt->fetchAll();

// B. MEMBRES (Pour P2P avec compteur - Mis à jour avec est_lu = 0)
// On compte les messages envoyés PAR ce membre À moi ET qui ne sont PAS lus.
$sqlMembres = "SELECT m.id, m.nom_complet, m.photo_profil_url, m.profession,
               (SELECT COUNT(*) FROM forum_messages f WHERE f.membre_id = m.id AND f.destinataire_id = :uid AND f.canal = 'p2p' AND f.est_lu = 0) as msg_count
               FROM membres m 
               WHERE m.id != :uid AND m.statut = 'actif' 
               ORDER BY m.nom_complet ASC";
$stmt = $pdo->prepare($sqlMembres);
$stmt->execute([':uid' => $user_id]);
$tousMembres = $stmt->fetchAll();

// C. MESSAGES
$messages = [];
$chatTitle = "Sélectionnez une discussion";
$chatSub = "";
$chatImg = "";

if ($cercle_id || $destinataire_id) {
    $sqlMsg = "SELECT f.*, 
               sender.nom_complet, sender.photo_profil_url,
               parent.contenu as parent_txt, parent_sender.nom_complet as parent_nom
               FROM forum_messages f
               JOIN membres sender ON f.membre_id = sender.id
               LEFT JOIN forum_messages parent ON f.parent_id = parent.id
               LEFT JOIN membres parent_sender ON parent.membre_id = parent_sender.id
               WHERE 1=1 ";
    $p = [];

    if ($canal === 'p2p' && $destinataire_id) {
        $sqlMsg .= " AND f.canal = 'p2p' AND ((f.membre_id = :me AND f.destinataire_id = :other) OR (f.membre_id = :other AND f.destinataire_id = :me))";
        $p = [':me' => $user_id, ':other' => $destinataire_id];
        
        // Infos Header
        foreach($tousMembres as $m) {
            if ($m['id'] == $destinataire_id) {
                $chatTitle = $m['nom_complet'];
                $chatSub = $m['profession'];
                $chatImg = $m['photo_profil_url'];
                break;
            }
        }

    } elseif ($cercle_id) {
        $sqlMsg .= " AND f.cercle_id = :cid AND f.canal != 'p2p'";
        $p = [':cid' => $cercle_id];
        
        // Infos Header
        foreach($mesCercles as $c) {
            if ($c['id'] == $cercle_id) {
                $chatTitle = $c['nom_cercle'];
                $chatSub = ($c['id'] == 1) ? "Canal Officiel - Général" : "Groupe " . ucfirst($c['type_tontine']);
                break;
            }
        }
    }

    $sqlMsg .= " ORDER BY f.date_envoi ASC";
    $stmt = $pdo->prepare($sqlMsg);
    $stmt->execute($p);
    $messages = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Chat | Ndjangui</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1; /* Indigo vibrant */
            --primary-dark: #4f46e5;
            --secondary: #ec4899;
            --bg-sidebar: #ffffff;
            --bg-chat: #f8fafc;
            --bubble-me: #6366f1;
            --text-me: #ffffff;
            --bubble-other: #ffffff;
            --text-other: #1e293b;
            --border: #e2e8f0;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-chat); height: 100vh; overflow: hidden; margin: 0; }
        
        /* LAYOUT GLOBAL */
        .app-container { display: flex; height: 100vh; width: 100%; position: relative; }

        /* --- SIDEBAR --- */
        .sidebar { width: 350px; background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; z-index: 20; transition: transform 0.3s ease; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border); background: #fff; display: flex; justify-content: space-between; align-items: center; }
        .search-wrapper { position: relative; margin: 10px 20px; }
        .search-wrapper input { width: 100%; background: #f1f5f9; border: none; padding: 12px 15px 12px 40px; border-radius: 12px; font-size: 0.9rem; outline: none; transition: 0.2s; }
        .search-wrapper input:focus { background: #fff; box-shadow: 0 0 0 2px var(--primary-dark); }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        .list-container { flex: 1; overflow-y: auto; padding: 10px; }
        /* Scrollbar Sidebar */
        .list-container::-webkit-scrollbar { width: 5px; }
        .list-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .category-label { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin: 15px 10px 5px; }

        .nav-item { display: flex; align-items: center; padding: 12px; border-radius: 12px; cursor: pointer; text-decoration: none; color: inherit; transition: all 0.2s; margin-bottom: 5px; position: relative; }
        .nav-item:hover { background: #f1f5f9; }
        .nav-item.active { background: #e0e7ff; }
        .nav-item.active .item-title { color: var(--primary-dark); font-weight: 700; }
        
        .avatar-box { width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-weight: 600; font-size: 1rem; position: relative; overflow: hidden; flex-shrink: 0; }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-general { background: linear-gradient(135deg, #6366f1, #a855f7); color: white; }
        .avatar-group { background: #fef3c7; color: #d97706; }
        .avatar-user { background: #e2e8f0; color: #64748b; }
        
        .item-content { flex: 1; overflow: hidden; }
        .item-title { display: block; font-size: 0.95rem; font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .item-sub { display: block; font-size: 0.8rem; color: #64748b; }

        /* BADGE COUNT */
        .badge-count {
            background-color: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
            min-width: 20px;
            text-align: center;
        }

        /* --- MAIN CHAT --- */
        .main-chat { flex: 1; display: flex; flex-direction: column; background: var(--bg-chat); position: relative; min-width: 0; }
        
        /* Chat Header */
        .chat-header { height: 75px; background: #fff; border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 20px; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.02); z-index: 10; }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .back-btn { display: none; font-size: 1.2rem; color: #334155; cursor: pointer; padding: 5px; }
        
        /* Chat Area */
        .messages-wrapper { flex: 1; overflow-y: auto; padding: 20px 5%; display: flex; flex-direction: column; gap: 20px; scroll-behavior: smooth; background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px; }
        .messages-wrapper::-webkit-scrollbar { width: 6px; }
        .messages-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .msg-row { display: flex; width: 100%; }
        .msg-row.me { justify-content: flex-end; }
        
        .bubble-container { max-width: 70%; position: relative; }
        .msg-bubble { padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; box-shadow: 0 2px 5px rgba(0,0,0,0.05); position: relative; }
        .msg-row.me .msg-bubble { background: var(--bubble-me); color: var(--text-me); border-bottom-right-radius: 4px; }
        .msg-row.other .msg-bubble { background: var(--bubble-other); color: var(--text-other); border-bottom-left-radius: 4px; }

        /* Reply Context */
        .reply-context { background: rgba(0,0,0,0.1); padding: 5px 10px; border-left: 3px solid rgba(255,255,255,0.5); border-radius: 4px; font-size: 0.8rem; margin-bottom: 6px; cursor: pointer; }
        .msg-row.other .reply-context { border-left-color: var(--primary); background: #f1f5f9; }

        /* Meta info */
        .msg-meta { display: flex; align-items: center; justify-content: flex-end; gap: 5px; font-size: 0.7rem; margin-top: 4px; opacity: 0.8; }
        .msg-sender { font-size: 0.75rem; font-weight: 700; color: var(--primary); margin-bottom: 4px; display: block; }

        /* Hover Actions */
        .msg-actions { position: absolute; top: -15px; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 2px 8px; display: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .msg-row.me .msg-actions { right: auto; left: 0; }
        .bubble-container:hover .msg-actions { display: flex; align-items: center; gap: 8px; animation: popIn 0.2s ease; }
        .action-icon { font-size: 0.85rem; color: #64748b; cursor: pointer; padding: 4px; }
        .action-icon:hover { color: var(--primary); transform: scale(1.2); }
        .action-icon.del:hover { color: #ef4444; }

        /* Footer Input */
        .chat-footer { background: #fff; padding: 15px 20px; border-top: 1px solid var(--border); }
        .reply-bar { display: none; background: #f1f5f9; padding: 8px 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid var(--primary); align-items: center; justify-content: space-between; }
        
        .input-box { display: flex; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 25px; padding: 6px 15px; transition: 0.2s; }
        .input-box:focus-within { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .input-box textarea { border: none; background: transparent; flex: 1; height: 40px; max-height: 100px; padding: 10px; resize: none; outline: none; }
        .btn-attach { color: #94a3b8; font-size: 1.2rem; cursor: pointer; padding: 5px; transition: 0.2s; }
        .btn-attach:hover { color: var(--primary); }
        .btn-send { background: var(--primary); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; margin-left: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
        .btn-send:hover { background: var(--primary-dark); transform: scale(1.05); }

        /* RESPONSIVE MOBILE */
        @media (max-width: 768px) {
            .sidebar { position: absolute; width: 100%; height: 100%; transform: translateX(0); }
            .main-chat { position: absolute; width: 100%; height: 100%; transform: translateX(100%); transition: transform 0.3s ease; background: #fff; }
            
            /* État : Conversation active */
            body.chat-active .sidebar { transform: translateX(-100%); }
            body.chat-active .main-chat { transform: translateX(0); }
            
            .back-btn { display: block; }
            .messages-wrapper { padding: 15px 10px; }
            .bubble-container { max-width: 85%; }
        }

        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body class="<?= ($cercle_id || $destinataire_id) ? 'chat-active' : '' ?>">

<div class="app-container">
    
    <aside class="sidebar">
        <div class="sidebar-header">
            <h4 class="fw-bold m-0" style="color:var(--text-other)">Messagerie</h4>
            <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-circle" title="Retour Accueil">
                <i class="fa-solid fa-house"></i>
            </a>
        </div>
        
        <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Rechercher...">
        </div>

        <div class="list-container">
            <div class="category-label">Cercles & Groupes</div>
            <?php foreach($mesCercles as $c): ?>
                <a href="?cercle_id=<?= $c['id'] ?>&canal=general" class="nav-item <?= ($cercle_id == $c['id'] && !$destinataire_id) ? 'active' : '' ?> filter-item" data-name="<?= strtolower($c['nom_cercle']) ?>">
                    <div class="avatar-box <?= ($c['id'] == 1) ? 'avatar-general' : 'avatar-group' ?>">
                        <?php if($c['id'] == 1): ?>
                            <i class="fa-solid fa-globe"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-users"></i>
                        <?php endif; ?>
                    </div>
                    <div class="item-content">
                        <span class="item-title"><?= htmlspecialchars($c['nom_cercle']) ?></span>
                        <span class="item-sub"><?= ($c['id'] == 1) ? 'Canal officiel' : 'Sous-groupe' ?></span>
                    </div>
                    <?php if($c['msg_count'] > 0): ?>
                        <span class="badge-count"><?= $c['msg_count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <div class="category-label mt-3">Messages Privés</div>
            <?php foreach($tousMembres as $m): ?>
                <a href="?destinataire_id=<?= $m['id'] ?>&canal=p2p" class="nav-item <?= ($destinataire_id == $m['id']) ? 'active' : '' ?> filter-item" data-name="<?= strtolower($m['nom_complet']) ?>">
                    <div class="avatar-box avatar-user">
                        <?php if($m['photo_profil_url']): ?>
                            <img src="<?= htmlspecialchars($m['photo_profil_url']) ?>" alt="p">
                        <?php else: ?>
                            <?= strtoupper(substr($m['nom_complet'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="item-content">
                        <span class="item-title"><?= htmlspecialchars($m['nom_complet']) ?></span>
                        <span class="item-sub"><?= htmlspecialchars($m['profession'] ?? 'Membre') ?></span>
                    </div>
                    <?php if($m['msg_count'] > 0): ?>
                        <span class="badge-count"><?= $m['msg_count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <main class="main-chat">
        <div class="chat-header">
            <div class="header-left">
                <i class="fa-solid fa-arrow-left back-btn" onclick="window.location.href='?'"></i>
                
                <div class="avatar-box <?= ($destinataire_id) ? 'avatar-user' : (($cercle_id==1) ? 'avatar-general' : 'avatar-group') ?>" style="width:40px; height:40px;">
                    <?php if($chatImg): ?>
                        <img src="<?= htmlspecialchars($chatImg) ?>">
                    <?php elseif($destinataire_id): ?>
                        <?= strtoupper(substr($chatTitle,0,1)) ?>
                    <?php elseif($cercle_id==1): ?>
                        <i class="fa-solid fa-globe"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-users"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="fw-bold text-dark lh-1"><?= htmlspecialchars($chatTitle) ?></div>
                    <small class="text-muted" style="font-size:0.75rem"><?= htmlspecialchars($chatSub) ?></small>
                </div>
            </div>
            <i class="fa-solid fa-ellipsis-vertical text-muted fs-5"></i>
        </div>

        <div class="messages-wrapper" id="chatBox">
            <?php if(empty($messages)): ?>
                <div class="d-flex flex-column align-items-center justify-content-center h-75 text-muted opacity-50">
                    <i class="fa-regular fa-paper-plane fa-3x mb-3"></i>
                    <p>Démarrez la conversation ici...</p>
                </div>
            <?php else: ?>
                <?php foreach($messages as $msg): $isMe = ($msg['membre_id'] == $user_id); ?>
                    <div class="msg-row <?= $isMe ? 'me' : 'other' ?>" id="msg-<?= $msg['id'] ?>">
                        <div class="bubble-container">
                            
                            <?php if(!$isMe && !$destinataire_id): ?>
                                <span class="msg-sender"><?= htmlspecialchars($msg['nom_complet']) ?></span>
                            <?php endif; ?>

                            <div class="msg-bubble">
                                <?php if($msg['parent_txt']): ?>
                                    <div class="reply-context">
                                        <strong><?= htmlspecialchars($msg['parent_nom']) ?></strong>
                                        <div class="text-truncate"><?= htmlspecialchars($msg['parent_txt']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?= nl2br(htmlspecialchars($msg['contenu'])) ?>

                                <?php if($msg['fichier_url']): ?>
                                    <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
                                        <a href="../<?= htmlspecialchars($msg['fichier_url']) ?>" target="_blank" class="d-flex align-items-center text-decoration-none" style="color: inherit;">
                                            <i class="fa-solid fa-file-arrow-down fs-4 me-2"></i>
                                            <span class="small text-truncate">Fichier joint</span>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div class="msg-meta">
                                    <?= (new DateTime($msg['date_envoi']))->format('H:i') ?>
                                    <?php if($msg['modifie']): ?><i class="fa-solid fa-pen-fancy" style="font-size:0.6rem"></i><?php endif; ?>
                                </div>
                            </div>

                            <div class="msg-actions">
                                <i class="fa-solid fa-reply action-icon" onclick="replyMsg('<?= $msg['id'] ?>', '<?= addslashes($msg['nom_complet']) ?>', '<?= addslashes(preg_replace('/\s+/', ' ', substr($msg['contenu'],0,60))) ?>')" title="Répondre"></i>
                                <?php if($isMe): ?>
                                    <i class="fa-solid fa-pencil action-icon" onclick="editMsg('<?= $msg['id'] ?>', '<?= addslashes(str_replace(["\r", "\n"], '\n', $msg['contenu'])) ?>')" title="Modifier"></i>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce message ?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                                        <button class="bg-transparent border-0 p-0"><i class="fa-solid fa-trash action-icon del" title="Supprimer"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if($cercle_id || $destinataire_id): ?>
        <div class="chat-footer">
            <div id="replyBar" class="reply-bar">
                <div>
                    <span class="text-primary fw-bold small">Réponse à <span id="repName">...</span></span>
                    <div class="text-muted small text-truncate" style="max-width:200px;" id="repText">...</div>
                </div>
                <i class="fa-solid fa-xmark text-secondary cursor-pointer" onclick="resetForm()"></i>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="reply_to_id" id="replyId">
                <input type="hidden" name="edit_id" id="editId">
                
                <div class="input-box">
                    <label for="fileInput" class="btn-attach">
                        <i class="fa-solid fa-paperclip"></i>
                    </label>
                    <input type="file" name="piece_jointe" id="fileInput" hidden onchange="this.parentElement.style.borderColor = 'var(--primary)';">
                    
                    <textarea name="contenu" id="msgInput" placeholder="Écrivez un message..."></textarea>
                    
                    <button type="submit" class="btn-send" id="submitBtn">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
    // Scroll Bottom
    const box = document.getElementById('chatBox');
    if(box) box.scrollTop = box.scrollHeight;

    // Search Filter
    document.getElementById('searchInput').addEventListener('keyup', (e) => {
        const val = e.target.value.toLowerCase();
        document.querySelectorAll('.filter-item').forEach(el => {
            el.style.display = el.getAttribute('data-name').includes(val) ? 'flex' : 'none';
        });
    });

    // Reply Logic
    function replyMsg(id, name, text) {
        resetForm();
        document.getElementById('replyId').value = id;
        document.getElementById('replyBar').style.display = 'flex';
        document.getElementById('repName').innerText = name;
        document.getElementById('repText').innerText = text;
        document.getElementById('msgInput').focus();
    }

    // Edit Logic
    function editMsg(id, text) {
        resetForm();
        document.getElementById('editId').value = id;
        document.getElementById('msgInput').value = text;
        document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-check"></i>';
        document.getElementById('msgInput').focus();
    }

    // Reset Form
    function resetForm() {
        if(document.getElementById('replyId')) document.getElementById('replyId').value = '';
        if(document.getElementById('editId')) document.getElementById('editId').value = '';
        if(document.getElementById('msgInput')) document.getElementById('msgInput').value = '';
        if(document.getElementById('replyBar')) document.getElementById('replyBar').style.display = 'none';
        if(document.getElementById('submitBtn')) document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
    }

    // Auto Resize Textarea
    const tx = document.getElementById("msgInput");
    if(tx) {
        tx.addEventListener("input", function() {
            this.style.height = "40px";
            this.style.height = (this.scrollHeight) + "px";
        });
    }
</script>

</body>
</html>