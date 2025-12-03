<?php
session_start();
// Redirect jika sudah login
if(isset($_SESSION['id'])) {
    if($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: kasir/dashboard.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Computer - Sistem Kasir Modern untuk Toko Komputer</title>
    <meta name="description" content="Sistem kasir online modern untuk toko komputer dengan fitur lengkap, mudah digunakan, dan responsif.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #1e293b;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark);
            overflow-x: hidden;
        }

        /* Navigation - Konsisten dengan tema admin/kasir */
        .navbar {
            background: #1e293b !important;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            border-bottom: 2px solid #3b82f6;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: white !important;
            display: flex;
            align-items: center;
        }

        .navbar-brand i {
            color: #3b82f6;
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .nav-link {
            font-weight: 500;
            margin: 0 0.5rem;
            color: #d1d5db !important;
            transition: all 0.3s ease;
            padding: 8px 15px !important;
            border-radius: 8px;
        }

        .nav-link:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6 !important;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: 2px solid transparent;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
            color: white;
            border-color: #3b82f6;
        }

        /* Hero Section dengan tema biru gelap */
        .hero {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            color: white;
            padding-top: 80px;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            color: #d1d5db;
            font-weight: 300;
            max-width: 600px;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-outline-light-custom {
            border: 2px solid rgba(59, 130, 246, 0.5);
            color: #3b82f6;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            background: transparent;
        }

        .btn-outline-light-custom:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            transform: translateY(-2px);
            border-color: #3b82f6;
        }

        .hero-image {
            position: relative;
            animation: float 6s ease-in-out infinite;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 3px solid rgba(59, 130, 246, 0.3);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Features Section - Konsisten dengan dashboard */
        .features {
            padding: 100px 0;
            background: var(--light);
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
            border-radius: 2px;
        }

        .section-title p {
            font-size: 1.2rem;
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Feature Card - Sama seperti stat-card di dashboard */
        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }

        .feature-card h4 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
            font-size: 1.3rem;
        }

        .feature-card p {
            color: var(--gray);
            line-height: 1.6;
            font-size: 1rem;
        }

        /* About Section */
        .about {
            padding: 100px 0;
            background: white;
        }

        .about-image {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .about-content h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .about-content p {
            font-size: 1.1rem;
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            font-weight: 500;
            color: var(--dark);
        }

        .feature-list li i {
            color: var(--success);
            margin-right: 10px;
            font-size: 1.2rem;
            background: rgba(16, 185, 129, 0.1);
            padding: 8px;
            border-radius: 8px;
        }

        /* Demo Login Section */
        .demo-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 80px 0;
            color: white;
            border-radius: 20px;
            margin: 80px 0;
        }

        .demo-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .demo-credentials {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        /* Stats Section - Sama seperti stats di dashboard */
        .stats {
            background: var(--secondary);
            padding: 80px 0;
            color: white;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 1.1rem;
            color: #94a3b8;
            font-weight: 500;
        }

        /* CTA Section */
        .cta {
            background: var(--light);
            padding: 100px 0;
            text-align: center;
            border-radius: 20px;
            margin: 50px 0;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--gray);
        }

        .btn-light-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: inline-block;
        }

        .btn-light-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
            color: white;
        }

        /* Footer - Konsisten dengan tema */
        .footer {
            background: var(--secondary);
            color: white;
            padding: 60px 0 20px;
            margin-top: 80px;
        }

        .footer h5 {
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: white;
            font-size: 1.2rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.3s ease;
            font-weight: 400;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 20px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }

        .footer-bottom {
            border-top: 1px solid #334155;
            padding-top: 20px;
            margin-top: 40px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.9rem;
        }

        /* Login Modal Styling */
        .login-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .login-modal .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 25px;
        }

        .login-modal .btn-close {
            filter: brightness(0) invert(1);
        }

        .login-form .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
        }

        .login-form .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .feature-card {
                padding: 2rem;
                margin-bottom: 1.5rem;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .navbar {
                padding: 0.5rem 0;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .hero {
                padding-top: 70px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-laptop-code"></i> Kasir Computer
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Fitur</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#demo">Demo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">Tentang</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="auth/login.php" class="btn-primary-custom">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="hero-content">
                        <h1 class="hero-title">
                            Sistem Kasir Modern untuk Toko Komputer Anda
                        </h1>
                        <p class="hero-subtitle">
                            Kelola penjualan, inventori, dan laporan keuangan dengan sistem kasir terintegrasi. 
                            Solusi lengkap untuk bisnis toko komputer yang ingin berkembang.
                        </p>
                        <div class="hero-buttons">
                            <a href="auth/login.php" class="btn-primary-custom">
                                <i class="fas fa-rocket me-2"></i>Mulai Sekarang
                            </a>
                            <a href="#features" class="btn-outline-light-custom">
                                <i class="fas fa-play-circle me-2"></i>Lihat Fitur
                            </a>
                        </div>
                        <div class="mt-4 d-flex align-items-center text-light">
                            <i class="fas fa-shield-alt me-2 text-primary"></i>
                            <small>Keamanan data terjamin • Backup otomatis • Support 24/7</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="hero-image text-center">
                        <img src="assets/img/team.jpg" alt="Dashboard Kasir Computer" 
                             class="img-fluid" style="max-height: 450px; object-fit: cover;">
                        <div class="mt-3 text-light">
                            <small><i class="fas fa-star text-warning me-1"></i> Dashboard interaktif dengan data real-time</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Fitur Lengkap untuk Bisnis Anda</h2>
                <p>Semua yang Anda butuhkan dalam satu sistem terintegrasi</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <h4>Sistem Kasir Modern</h4>
                        <p>Proses transaksi cepat dengan interface user-friendly, support berbagai metode pembayaran, dan cetak struk otomatis.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <h4>Manajemen Inventori</h4>
                        <p>Kelola stok produk secara real-time dengan notifikasi stok menipis dan tracking barang masuk-keluar otomatis.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Laporan & Analisis</h4>
                        <p>Dashboard lengkap dengan grafik interaktif untuk analisis penjualan, profit, dan performa bisnis.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h4>Multi User Role</h4>
                        <p>Dukungan multi user dengan role Admin dan Kasir untuk keamanan dan efisiensi operasional.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>Responsive Design</h4>
                        <p>Akses dari desktop, tablet, atau smartphone dengan tampilan optimal di semua perangkat.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <h4>Backup Otomatis</h4>
                        <p>Data transaksi dan inventori tersimpan aman dengan sistem backup otomatis harian.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Login Section -->
    <section class="demo-section" id="demo">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8" data-aos="fade-up">
                    <div class="demo-box">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold mb-3">Coba Demo Gratis</h2>
                            <p class="mb-0">Login dengan akun demo untuk mencoba fitur-fitur sistem kami</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="demo-credentials">
                                    <h5><i class="fas fa-user-shield me-2"></i>Role Admin</h5>
                                    <p class="mb-1"><strong>Username:</strong> admin</p>
                                    <p class="mb-0"><strong>Password:</strong> admin123</p>
                                    <div class="mt-3">
                                        <small class="text-light">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Akses penuh: produk, user, laporan
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="demo-credentials">
                                    <h5><i class="fas fa-user-tie me-2"></i>Role Kasir</h5>
                                    <p class="mb-1"><strong>Username:</strong> kasir</p>
                                    <p class="mb-0"><strong>Password:</strong> kasir123</p>
                                    <div class="mt-3">
                                        <small class="text-light">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Akses transaksi dan riwayat
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="auth/login.php" class="btn btn-light btn-lg px-5">
                                <i class="fas fa-play me-2"></i>Coba Demo Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="about-image">
                        <img src="assets/img/team2.jpg" alt="Tentang Kasir Computer" 
                             class="img-fluid" style="height: 400px; object-fit: cover; width: 100%;">
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="about-content ps-lg-5">
                        <h3>Tentang Kasir Computer</h3>
                        <p>
                            Kasir Computer adalah solusi sistem kasir modern yang dirancang khusus untuk kebutuhan 
                            toko komputer. Dibangun dengan teknologi terkini untuk memberikan pengalaman terbaik 
                            dalam mengelola bisnis retail.
                        </p>
                        <ul class="feature-list">
                            <li><i class="fas fa-check-circle"></i> Dibangun dengan PHP, MySQL, dan Bootstrap 5</li>
                            <li><i class="fas fa-check-circle"></i> Support teknis dan update rutin</li>
                            <li><i class="fas fa-check-circle"></i> Kompatibel dengan berbagai hardware kasir</li>
                            <li><i class="fas fa-check-circle"></i> Dokumentasi lengkap dan mudah dipahami</li>
                            <li><i class="fas fa-check-circle"></i> Customizable sesuai kebutuhan bisnis</li>
                        </ul>
                        <div class="mt-4">
                            <a href="#contact" class="btn-primary-custom me-3">
                                <i class="fas fa-envelope me-2"></i>Hubungi Kami
                            </a>
                            <a href="#features" class="btn-outline-light-custom" style="border-color: var(--primary); color: var(--primary);">
                                <i class="fas fa-book me-2"></i>Dokumentasi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="row text-center">
                <div class="col-lg-3 col-md-6" data-aos="fade-up">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Pengguna Aktif</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-item">
                        <div class="stat-number">1M+</div>
                        <div class="stat-label">Transaksi Diproses</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-item">
                        <div class="stat-number">99.9%</div>
                        <div class="stat-label">Uptime Server</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Support Teknis</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center" data-aos="fade-up">
                    <h2>Siap Mengembangkan Bisnis Komputer Anda?</h2>
                    <p>Bergabung dengan ratusan toko komputer yang telah mempercayakan sistem kasir mereka kepada kami. Mulai gratis sekarang!</p>
                    <div class="mt-4">
                        <a href="auth/login.php" class="btn-light-custom me-3">
                            <i class="fas fa-play me-2"></i>Mulai Gratis
                        </a>
                        <a href="#" class="btn-outline-light-custom" data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="fas fa-question-circle me-2"></i>Butuh Bantuan?
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4" data-aos="fade-up">
                    <h5><i class="fas fa-laptop-code me-2"></i>Kasir Computer</h5>
                    <p class="mt-3" style="color: #cbd5e1;">
                        Sistem kasir modern untuk toko komputer dengan fitur lengkap dan interface yang user-friendly. 
                        Dibuat untuk mendukung pertumbuhan bisnis retail Anda.
                    </p>
                    <div class="social-links mt-4">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <h5>Menu</h5>
                    <ul class="footer-links">
                        <li><a href="#home">Beranda</a></li>
                        <li><a href="#features">Fitur</a></li>
                        <li><a href="#demo">Demo</a></li>
                        <li><a href="#about">Tentang</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <h5>Kontak</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Jakarta, Indonesia</li>
                        <li><i class="fas fa-phone me-2"></i> +62 812-3456-7890</li>
                        <li><i class="fas fa-envelope me-2"></i> support@kasircomputer.com</li>
                        <li><i class="fas fa-clock me-2"></i> Senin - Jumat: 9:00 - 17:00</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <h5>Proyek Sekolah</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-graduation-cap me-2"></i> Tugas Akhir Sekolah</li>
                        <li><i class="fas fa-user me-2"></i> Dibuat oleh: Abyan</li>
                        <li><i class="fas fa-code me-2"></i> Teknologi: PHP, MySQL, Bootstrap</li>
                        <li><i class="fas fa-calendar me-2"></i> Tahun: 2024</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Kasir Computer. All rights reserved. | 
                   <span class="ms-2">Developed with <i class="fas fa-heart text-danger"></i> by Abyan</span> | 
                   <span class="ms-2"><i class="fas fa-school me-1"></i> Proyek Akhir Sekolah</span>
                </p>
            </div>
        </div>
    </footer>

    <!-- Contact Modal -->
    <div class="modal fade login-modal" id="contactModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Butuh Bantuan?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Hubungi kami untuk konsultasi atau bantuan teknis:</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-envelope text-primary me-2"></i> Email: support@kasircomputer.com</li>
                        <li class="mb-2"><i class="fab fa-whatsapp text-success me-2"></i> WhatsApp: +62 812-3456-7890</li>
                        <li><i class="fas fa-phone text-info me-2"></i> Telepon: (021) 1234-5678</li>
                    </ul>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Untuk demo atau trial, gunakan akun demo yang tersedia.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <a href="mailto:support@kasircomputer.com" class="btn btn-primary">Kirim Email</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(30, 41, 59, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
            } else {
                navbar.style.background = '#1e293b';
                navbar.style.backdropFilter = 'none';
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                if(this.getAttribute('href') !== '#') {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });

        // Demo credentials auto-fill suggestion
        document.addEventListener('DOMContentLoaded', function() {
            const demoLinks = document.querySelectorAll('a[href="auth/login.php"]');
            demoLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    localStorage.setItem('showDemoAlert', 'true');
                });
            });
        });
    </script>
</body>
</html>