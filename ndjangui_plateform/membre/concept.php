<?php
session_start();
date_default_timezone_set('Africa/Douala');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NDJANGUI | L'Épargne Intelligente</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #10b981;       /* Emerald */
            --brand-secondary: #3b82f6;     /* Blue */
            --bg-dark: #020617;             /* Very Dark Slate */
            --bg-card: #0f172a;             /* Dark Slate */
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --gradient-main: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-text: linear-gradient(to right, #ffffff 0%, #94a3b8 100%);
            --glow: 0 0 80px -20px rgba(16, 185, 129, 0.25);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            overflow-x: hidden;
            line-height: 1.7;
        }

        /* --- TYPOGRAPHY --- */
        h1, h2, h3, h4, h5 { font-weight: 800; letter-spacing: -0.03em; }
        .text-gradient {
            background: linear-gradient(to right, #10b981, #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .text-gradient-silver {
            background: linear-gradient(to bottom, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* --- NAVBAR --- */
        .navbar {
            padding: 24px 0;
            background: rgba(2, 6, 23, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .btn-login {
            color: white;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 10px 24px;
            border-radius: 12px;
            transition: 0.3s;
        }
        .btn-login:hover { background: rgba(255,255,255,0.05); border-color: white; color: white; }
        .btn-join {
            background: var(--brand-primary);
            color: #000;
            font-weight: 700;
            padding: 10px 24px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
            transition: 0.3s;
            border: none;
        }
        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.5);
            background: #34d399;
        }

        /* --- HERO --- */
        .hero-section {
            padding: 160px 0 100px;
            position: relative;
            overflow: hidden;
        }
        .hero-bg-glow {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
            top: -20%;
            right: -10%;
            z-index: 0;
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 100px;
            color: var(--brand-primary);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2rem;
        }

        /* --- MOCKUP PHONE --- */
        .phone-mockup {
            border: 12px solid #1e293b;
            border-radius: 40px;
            overflow: hidden;
            position: relative;
            background: #000;
            box-shadow: 0 50px 100px -20px rgba(0,0,0,0.5);
            z-index: 1;
        }
        .notch {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 25px;
            background: #1e293b;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
            z-index: 2;
        }

        /* --- CARDS & BENTO --- */
        .glass-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            height: 100%;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .glass-card:hover {
            background: rgba(30, 41, 59, 0.7);
            border-color: rgba(16, 185, 129, 0.3);
            transform: translateY(-10px);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--brand-primary);
            font-size: 1.5rem;
            margin-bottom: 25px;
        }

        /* --- STATS STRIP --- */
        .stats-strip {
            border-top: 1px solid rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            background: rgba(15, 23, 42, 0.3);
        }

        /* --- USE CASES --- */
        .use-case-img {
            border-radius: 24px;
            opacity: 0.7;
            transition: 0.5s;
            filter: grayscale(100%);
        }
        .use-case-card:hover .use-case-img {
            opacity: 1;
            filter: grayscale(0%);
            transform: scale(1.02);
        }

        /* --- FOOTER --- */
        .footer-cta {
            background: linear-gradient(135deg, var(--brand-primary), #059669);
            border-radius: 30px;
            color: white;
            padding: 80px 40px;
        }

        @media (max-width: 991px) {
            .hero-section { padding-top: 120px; text-align: center; }
            .hero-bg-glow { display: none; }
            .navbar-brand span { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar fixed-top">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <i class="fa-solid fa-layer-group text-success fs-3"></i>
                <span class="fw-bold text-white fs-4 tracking-tight">NDJANGUI</span>
            </a>
            
            <div class="d-flex gap-2 gap-md-3">
                <a href="login.php" class="btn btn-login d-none d-sm-block">
                    <i class="fa-regular fa-user me-2"></i>Connexion
                </a>
                <a href="login.php" class="btn btn-login d-sm-none px-3">
                    <i class="fa-regular fa-user"></i>
                </a>
                
                <a href="rejoindre.php" class="btn btn-join">
                    Rejoindre <i class="fa-solid fa-arrow-right ms-2 d-none d-sm-inline"></i>
                </a>
            </div>
        </div>
    </nav>

    <header class="hero-section">
        <div class="hero-bg-glow"></div>
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 z-1" data-aos="fade-up">
                    <div class="hero-badge">
                        <i class="fa-solid fa-sparkles me-2"></i> La nouvelle norme financière
                    </div>
                    <h1 class="display-3 mb-4 text-white">
                        Votre tontine,<br>
                        <span class="text-gradient">puissance infinie.</span>
                    </h1>
                    <p class="lead text-muted mb-5 fs-5">
                        Ne laissez plus la gestion manuelle freiner vos ambitions. Automatisez vos cotisations, sécurisez vos fonds et bâtissez votre patrimoine avec la plateforme la plus avancée d'Afrique.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center justify-content-lg-start">
                        <a href="rejoindre.php" class="btn btn-join btn-lg px-5 py-3 rounded-pill">
                            Commencer maintenant
                        </a>
                        <a href="#demo" class="btn btn-outline-light btn-lg px-5 py-3 rounded-pill">
                            Comment ça marche ?
                        </a>
                    </div>
                    
                    <div class="mt-5 pt-3 d-flex align-items-center gap-3 opacity-75 justify-content-center justify-content-lg-start">
                        <div class="d-flex text-warning">
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                        </div>
                        <span class="text-white small">4.9/5 par +10,000 utilisateurs</span>
                    </div>
                </div>

                <div class="col-lg-6 position-relative" data-aos="fade-left" data-aos-delay="200">
                    <div class="phone-mockup mx-auto" style="max-width: 380px;">
                        <div class="notch"></div>
                        <img src="https://images.unsplash.com/photo-1616077168079-7e09a677fb2c?ixlib=rb-1.2.1&auto=format&fit=crop&w=634&q=80" alt="App Interface" class="img-fluid" style="opacity: 0.9;">
                        
                        <div class="position-absolute bottom-0 w-100 p-4" style="background: linear-gradient(to top, #000 0%, transparent 100%);">
                            <div class="bg-dark border border-secondary p-3 rounded-3 d-flex align-items-center gap-3 shadow-lg">
                                <div class="bg-success rounded-circle p-2 text-white">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <div>
                                    <div class="text-white fw-bold">Versement reçu</div>
                                    <div class="text-success small">+ 500,000 FCFA</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="position-absolute top-50 start-50 translate-middle" style="z-index: -1; width: 120%; height: 80%; background: var(--brand-primary); filter: blur(120px); opacity: 0.2;"></div>
                </div>
            </div>
        </div>
    </header>

    <div class="stats-strip py-4">
        <div class="container">
            <p class="text-center text-muted small text-uppercase letter-spacing-2 mb-4">Compatible avec les meilleurs réseaux</p>
            <div class="d-flex justify-content-center flex-wrap align-items-center gap-5 opacity-50 grayscale-hover">
                <h4 class="m-0 text-white fw-bold"><i class="fa-solid fa-building-columns me-2"></i>UBA</h4>
                <h4 class="m-0 text-white fw-bold"><i class="fa-solid fa-mobile-screen me-2"></i>MTN MoMo</h4>
                <h4 class="m-0 text-white fw-bold"><i class="fa-solid fa-wifi me-2"></i>Orange Money</h4>
                <h4 class="m-0 text-white fw-bold"><i class="fa-brands fa-cc-visa me-2"></i>VISA</h4>
            </div>
        </div>
    </div>

    <section class="py-5 my-5">
        <div class="container">
            <div class="row justify-content-center text-center mb-5">
                <div class="col-lg-8">
                    <h2 class="display-5 text-white mb-3">L'ancien système est <span class="text-danger">dépassé.</span></h2>
                    <p class="text-muted fs-5">Les cahiers se perdent, la confiance s'effrite et le cash est risqué. Il est temps de passer à une gestion professionnelle.</p>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="glass-card">
                        <div class="feature-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h4 class="text-white mb-3">Séquestre Bancaire</h4>
                        <p class="text-muted">L'argent ne transite pas de main en main. Il est stocké sur un compte séquestre sécurisé jusqu'au jour du tirage. Zéro risque de vol.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="glass-card">
                        <div class="feature-icon">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <h4 class="text-white mb-3">Autopilotage</h4>
                        <p class="text-muted">Rappels SMS, prélèvement automatique, calcul des pénalités... Notre algorithme gère les tâches ingrates pour vous.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="glass-card">
                        <div class="feature-icon">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <h4 class="text-white mb-3">Transparence Totale</h4>
                        <p class="text-muted">Chaque membre voit le solde du groupe en temps réel. Les rapports financiers sont générés automatiquement chaque mois.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 overflow-hidden">
        <div class="container py-5">
            
            <div class="row align-items-center g-5 mb-5 pb-5">
                <div class="col-lg-6 order-2 order-lg-1" data-aos="fade-right">
                    <div class="position-relative">
                        <img src="https://images.unsplash.com/photo-1556742049-0cfed4f7a07d?auto=format&fit=crop&w=800&q=80" class="img-fluid rounded-4 shadow-lg border border-secondary" alt="Dashboard">
                        <div class="position-absolute top-0 start-0 translate-middle bg-primary rounded-circle p-3 d-none d-lg-block">
                            <i class="fa-solid fa-bolt text-white fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 order-1 order-lg-2 ps-lg-5" data-aos="fade-left">
                    <h6 class="text-success text-uppercase fw-bold letter-spacing-2 mb-3">Gestion Centralisée</h6>
                    <h2 class="display-6 text-white mb-4">Un tableau de bord digne d'un directeur financier.</h2>
                    <p class="text-muted fs-5 mb-4">Visualisez les cycles en cours, les membres en retard et les fonds disponibles en un coup d'œil. Plus besoin de fichier Excel complexe.</p>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-3"><i class="fa-solid fa-check-circle text-success me-2"></i> Export PDF & Excel des transactions</li>
                        <li class="mb-3"><i class="fa-solid fa-check-circle text-success me-2"></i> Historique immuable (Blockchain style)</li>
                        <li class="mb-3"><i class="fa-solid fa-check-circle text-success me-2"></i> Gestion multi-tontines</li>
                    </ul>
                </div>
            </div>

            <div class="row align-items-center g-5 mt-5">
                <div class="col-lg-6 ps-lg-5" data-aos="fade-right">
                    <h6 class="text-success text-uppercase fw-bold letter-spacing-2 mb-3">Crédit & Scoring</h6>
                    <h2 class="display-6 text-white mb-4">Votre réputation devient votre capital.</h2>
                    <p class="text-muted fs-5 mb-4">Le "Ndjangui Score" analyse votre ponctualité. Un bon score vous permet d'emprunter au sein du groupe ou d'accéder à des micro-crédits partenaires.</p>
                    <div class="d-flex align-items-center gap-4 mt-4">
                        <div class="text-center">
                            <h3 class="text-white fw-bold mb-0">850</h3>
                            <small class="text-muted">Score Excellent</small>
                        </div>
                        <div class="vr bg-secondary"></div>
                        <div class="text-center">
                            <h3 class="text-white fw-bold mb-0">2.5%</h3>
                            <small class="text-muted">Taux préférentiel</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <img src="https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&w=800&q=80" class="img-fluid rounded-4 shadow-lg border border-secondary" alt="Scoring">
                </div>
            </div>

        </div>
    </section>

    <section class="py-5 bg-darker">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="text-white">Conçu pour tous les cercles</h2>
                <p class="text-muted">NDJANGUI s'adapte à la dynamique de votre groupe.</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4 use-case-card" data-aos="zoom-in" data-aos-delay="0">
                    <div class="position-relative overflow-hidden rounded-4 mb-3">
                        <img src="https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=600&q=80" class="img-fluid w-100 use-case-img" alt="Office">
                        <div class="position-absolute bottom-0 start-0 p-3 w-100 bg-gradient-to-t">
                            <h5 class="text-white fw-bold m-0">Entre Collègues</h5>
                        </div>
                    </div>
                    <p class="text-muted small">Idéal pour les cotisations mensuelles au bureau sans gêner la trésorerie.</p>
                </div>
                
                <div class="col-md-4 use-case-card" data-aos="zoom-in" data-aos-delay="100">
                    <div class="position-relative overflow-hidden rounded-4 mb-3">
                        <img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&w=600&q=80" class="img-fluid w-100 use-case-img" alt="Commerce">
                        <div class="position-absolute bottom-0 start-0 p-3 w-100 bg-gradient-to-t">
                            <h5 class="text-white fw-bold m-0">Commerçants</h5>
                        </div>
                    </div>
                    <p class="text-muted small">Pour les associations de marché. Cotisez chaque jour, ramassez en gros.</p>
                </div>

                <div class="col-md-4 use-case-card" data-aos="zoom-in" data-aos-delay="200">
                    <div class="position-relative overflow-hidden rounded-4 mb-3">
                        <img src="https://images.unsplash.com/photo-1511895426328-dc8714191300?auto=format&fit=crop&w=600&q=80" class="img-fluid w-100 use-case-img" alt="Family">
                        <div class="position-absolute bottom-0 start-0 p-3 w-100 bg-gradient-to-t">
                            <h5 class="text-white fw-bold m-0">Famille & Diaspora</h5>
                        </div>
                    </div>
                    <p class="text-muted small">Connectez les membres au pays et ceux de la diaspora dans un seul pot commun.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row text-center g-4 border border-secondary rounded-5 p-5 bg-gradient-dark">
                <div class="col-md-3">
                    <h2 class="display-4 fw-bold text-white count">500M+</h2>
                    <p class="text-success text-uppercase small fw-bold">FCFA Sécurisés</p>
                </div>
                <div class="col-md-3">
                    <h2 class="display-4 fw-bold text-white count">12k</h2>
                    <p class="text-success text-uppercase small fw-bold">Membres Actifs</p>
                </div>
                <div class="col-md-3">
                    <h2 class="display-4 fw-bold text-white count">100%</h2>
                    <p class="text-success text-uppercase small fw-bold">Disponibilité</p>
                </div>
                <div class="col-md-3">
                    <h2 class="display-4 fw-bold text-white count">24/7</h2>
                    <p class="text-success text-uppercase small fw-bold">Support Client</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 mb-5">
        <div class="container" data-aos="flip-up">
            <div class="footer-cta text-center position-relative overflow-hidden">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjIiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4xKSIvPjwvc3ZnPg=='); opacity: 0.3;"></div>
                
                <h2 class="display-4 fw-bold mb-4 position-relative z-1">Prêt à moderniser votre épargne ?</h2>
                <p class="fs-5 mb-5 opacity-90 position-relative z-1">Rejoignez la communauté des bâtisseurs dès aujourd'hui. C'est gratuit pour démarrer.</p>
                
                <div class="d-flex justify-content-center gap-3 position-relative z-1 flex-wrap">
                    <a href="rejoindre.php" class="btn btn-light text-success fw-bold btn-lg px-5 py-3 rounded-pill shadow">Créer mon compte</a>
                    <a href="#" class="btn btn-outline-light fw-bold btn-lg px-5 py-3 rounded-pill">Télécharger l'app</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-5 border-top border-secondary bg-darker">
        <div class="container text-center text-muted">
            <div class="mb-4">
                <i class="fa-solid fa-layer-group text-success fs-2 mb-3"></i>
                <h5 class="text-white fw-bold tracking-tight">NDJANGUI</h5>
            </div>
            <div class="d-flex justify-content-center gap-4 mb-4 small">
                <a href="#" class="text-decoration-none text-muted hover-white">À propos</a>
                <a href="#" class="text-decoration-none text-muted hover-white">Sécurité</a>
                <a href="#" class="text-decoration-none text-muted hover-white">CGU</a>
                <a href="#" class="text-decoration-none text-muted hover-white">Confidentialité</a>
                <a href="#" class="text-decoration-none text-muted hover-white">Contact</a>
            </div>
            <p class="small opacity-50">&copy; <?php echo date('Y'); ?> Ndjangui Inc. Tous droits réservés. Designed for Excellence.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });

        // Navbar blur effect on scroll
        window.addEventListener("scroll", function() {
            var nav = document.querySelector("nav");
            if (window.scrollY > 50) {
                nav.style.background = "rgba(2, 6, 23, 0.95)";
                nav.style.padding = "15px 0";
            } else {
                nav.style.background = "rgba(2, 6, 23, 0.8)";
                nav.style.padding = "24px 0";
            }
        });
    </script>
</body>
</html>