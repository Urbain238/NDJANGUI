<?php
// ---------------------------------------------------------
// ACTIVATION DU MODE DEBUG (Pour éviter les pages blanches)
// ---------------------------------------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NDJANGUI | La Solidarité Digitale</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #1a237e; /* Bleu profond pour la confiance */
            --secondary: #2ecc71; /* Vert pour la croissance financière */
            --dark: #0a0f1d;
            --light: #f8f9fa;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--dark); }

        .navbar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .logo-icon { color: var(--primary); }

        .hero-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #f5f7fa 0%, #d6e0f0 100%);
            border-bottom-left-radius: 80px;
            border-bottom-right-radius: 80px;
        }

        .btn-primary-custom {
            background: var(--primary);
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-primary-custom:hover { background: #0d1250; transform: scale(1.05); }

        .feature-card {
            border: none;
            border-radius: 20px;
            padding: 30px;
            transition: 0.4s;
            background: #fff;
            height: 100%;
        }
        .feature-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        
        .icon-box {
            width: 70px;
            height: 70px;
            background: rgba(26, 35, 126, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            font-size: 30px;
            margin-bottom: 25px;
        }

        footer { background: var(--dark); color: #fff; padding: 80px 0 20px; }
        .footer-link { color: #bdc3c7; text-decoration: none; transition: 0.3s; }
        .footer-link:hover { color: #fff; padding-left: 5px; }
        
        .payment-badge {
            background: white;
            padding: 5px 12px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 100px;
        }

        /* AJUSTEMENT RESPONSIVE POUR LE MENU CONNEXION */
        @media (max-width: 991px) {
            .navbar-nav { padding-top: 20px; }
            .nav-item { margin-bottom: 10px; width: 100%; text-align: center; }
            .ms-lg-3, .ms-lg-2 { margin-left: 0 !important; }
            .dropdown-menu { text-align: center; border: 1px solid #eee !important; }
            .btn { width: 100%; margin-right: 0 !important; }
        }

        @media (min-width: 992px) {
            .nav-item.dropdown:hover .dropdown-menu {
                display: block;
                margin-top: 0; 
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="fa-solid fa-handshake-simple fs-2 me-2 logo-icon"></i>
            <span class="fw-bold fs-3 text-uppercase" style="letter-spacing: 1px;">NDJANGUI</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link px-3 fw-semibold" href="membre/concept.php">
                        <i class="fa-solid fa-lightbulb me-1"></i> Concept
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 fw-semibold" href="membre/securite.php">
                        <i class="fa-solid fa-shield-halved me-1"></i> Sécurité
                    </a>
                </li>

                <li class="nav-item dropdown ms-lg-3">
                    <a class="btn btn-outline-primary rounded-pill px-4 me-2 fw-bold dropdown-toggle d-inline-flex align-items-center justify-content-center" 
                       href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-user-lock me-2"></i> Connexion
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-lg-3 p-2" aria-labelledby="navbarDropdown" style="border-radius: 15px;">
                        <li>
                            <a class="dropdown-item rounded-3 py-2" href="membre/login.php">
                                <i class="fa-solid fa-user me-2 text-primary"></i> Espace Membre
                            </a>
                        </li>
                        <li><hr class="dropdown-divider opacity-50"></li>
                        <li>
                            <a class="dropdown-item rounded-3 py-2" href="admin/admin_login.php">
                                <i class="fa-solid fa-shield-halved me-2 text-danger"></i> Administration
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-primary-custom text-white shadow d-inline-flex align-items-center justify-content-center" href="membre/rejoindre.php">
    <i class="fa-solid fa-user-plus me-2"></i> REJOINDRE
</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

    <header class="hero-section overflow-hidden">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 animate__animated animate__fadeInLeft">
                    <span class="badge bg-primary text-white px-3 py-2 rounded-pill mb-3 fw-bold shadow-sm text-uppercase">L'union fait la force</span>
                    <h1 class="display-3 fw-bold mb-4" style="color: var(--primary);">La tontine <span style="color: var(--secondary);">solidaire</span> & connectée.</h1>
                    <p class="lead text-muted mb-5">Bienvenue sur <strong>NDJANGUI</strong>. Digitalisez votre cercle de confiance, sécurisez vos cotisations et boostez vos projets grâce au pouvoir de la communauté.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="membre/login.php" class="btn btn-primary-custom text-white btn-lg shadow d-flex align-items-center text-decoration-none">
                            <i class="fa-solid fa-wallet me-2"></i> COTISER MAINTENANT
                        </a>
                        <button class="btn btn-outline-dark btn-lg rounded-pill px-4 fw-bold d-flex align-items-center">
                           <a href="membre/concept.php" class="btn btn-primary-custom text-white btn-lg shadow d-flex align-items-center text-decoration-none">
                           <i class="fa-solid fa-magnifying-glass-chart me-2"></i> DÉCOUVRIR
                        </a>
                        </button>
                    </div>
                </div>
                <div class="col-lg-6 mt-5 mt-lg-0 animate__animated animate__zoomIn text-center">
                    <img src="https://img.freepik.com/fotos-premium/maos-de-pessoas-de-negocios-e-sucesso-da-visao-superior-da-motivacao-da-celebracao-da-equipe-e-apoio-na-reuniao-de-confianca-ou-colaboracao-trabalhadores-da-diversidade-maos-juntas-e-metas-de-inicializacao-visao-e-parceria_590464-129988.jpg" 
                           class="img-fluid rounded-4 shadow-lg border border-5 border-white" 
                           alt="Collaboration NDJANGUI"
                           style="object-fit: cover; height: 450px; width: 100%;">
                </div>
            </div>
        </div>
    </header>

    <section class="py-5 bg-white">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-5">Le digital au service de l'entraide</h2>
                <p class="text-muted lead">Une plateforme conçue pour la transparence et la croissance collective.</p>
            </div>
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="feature-card shadow-sm">
                        <div class="icon-box mx-auto shadow-sm"><i class="fa-solid fa-fingerprint"></i></div>
                        <h4 class="fw-bold">Identité Certifiée</h4>
                        <p class="text-muted small">Chaque membre est vérifié via son compte Mobile Money et parrainé par le cercle pour une confiance absolue.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card shadow-sm">
                        <div class="icon-box mx-auto shadow-sm"><i class="fa-solid fa-money-bill-transfer"></i></div>
                        <h4 class="fw-bold">Paiements Instantanés</h4>
                        <p class="text-muted small">Collecte automatique via <strong>MoMo & OM</strong>. Fini les calculs manuels et les risques liés aux espèces.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card shadow-sm">
                        <div class="icon-box mx-auto shadow-sm"><i class="fa-solid fa-chart-line"></i></div>
                        <h4 class="fw-bold">Crédit Équitable</h4>
                        <p class="text-muted small">Un système de <strong>Credit Scoring</strong> basé sur votre historique pour accéder aux prêts sans paperasse.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" style="background: var(--primary); color: white;">
        <div class="container text-center py-4">
            <h3 class="fw-bold mb-4 text-uppercase">Gérez votre épargne en toute transparence</h3>
            <img src="https://images.unsplash.com/photo-1556742044-3c52d6e88c62?auto=format&fit=crop&q=80&w=1000" class="img-fluid rounded-4 shadow mb-4" style="max-height: 350px; width: 100%; object-fit: cover;" alt="Paiement Digital">
        </div>
    </section>

   <footer class="pt-5 pb-3">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 mb-4">
                    <h5 class="fw-bold mb-4 text-uppercase" style="letter-spacing: 2px; color: var(--secondary);">NDJANGUI</h5>
                    <p class="small mb-4" style="color: #bdc3c7; line-height: 1.8;">
                        Digitalisez vos tontines en toute sécurité. La plateforme de référence pour la solidarité financière au Cameroun.
                    </p>
                    <h6 class="text-white small fw-bold mb-3 text-uppercase">Restez informé</h6>
                    <div class="input-group mb-3 shadow-sm">
                        <input type="email" class="form-control bg-dark border-secondary text-white small" placeholder="Votre email" aria-label="Email">
                        <button class="btn btn-primary" type="button"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2 mb-4">
                    <h6 class="fw-bold text-white mb-4 text-uppercase small">Liens Utiles</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="footer-link small">Comment ça marche ?</a></li>
                        <li><a href="membre/register.php" class="footer-link small">Postuler / Rejoindre</a></li>
                        <li><a href="#" class="footer-link small">Actualités</a></li>
                        <li><a href="#" class="footer-link small">Nous contacter</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-3 col-lg-2 mb-4">
                    <h6 class="fw-bold text-white mb-4 text-uppercase small">Aide & Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="footer-link small">Centre d'aide / FAQ</a></li>
                        <li><a href="#" class="footer-link small">Sécurité des fonds</a></li>
                        <li><a href="#" class="footer-link small">Confidentialité</a></li>
                        <li><a href="#" class="footer-link small">CGU & Mentions</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 mb-4">
    <h6 class="fw-bold text-white mb-4 text-uppercase small">Partenaires de Paiement</h6>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        
        <div class="payment-badge border-0" style="background: #FFCC00; width: 110px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/9/93/Mtn-logo.ce068660.png" 
                 style="height: 25px; width: auto;" 
                 onerror="this.style.display='none'"
                 alt="">
            <span class="text-dark fw-bold ms-2" style="font-size: 11px;">MoMo</span>
        </div>

        <div class="payment-badge border-0" style="background: #FF6600; width: 110px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c8/Orange_logo.svg" 
                 style="height: 25px; width: auto;" 
                 onerror="this.style.display='none'"
                 alt="">
            <span class="text-white fw-bold ms-1" style="font-size: 11px;">Money</span>
        </div>

    </div>
    
    <h6 class="fw-bold text-white mb-3 text-uppercase small">Suivez-nous</h6>
    <div class="d-flex gap-3">
        <a href="#" class="text-white-50 fs-5"><i class="fa-brands fa-facebook"></i></a>
        <a href="#" class="text-white-50 fs-5"><i class="fa-brands fa-linkedin"></i></a>
        <a href="#" class="text-white-50 fs-5"><i class="fa-brands fa-whatsapp"></i></a>
    </div>
</div>
            </div>

            <hr class="mt-5 mb-4" style="border-color: rgba(255,255,255,0.1);">

            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <span class="small text-muted">© 2026 NDJANGUI Technologies. Agrément n° 234/Fintech/CAM.</span>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <span class="small text-muted">
                        <i class="fa-solid fa-lock text-success me-2"></i>Connexion sécurisée SSL 256-bit
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>