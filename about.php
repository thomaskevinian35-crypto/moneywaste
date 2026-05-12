<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MONEYWASTE — About</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0a0a0a;color:#fff;line-height:1.6;}
        .minimal-header{display:flex;align-items:center;justify-content:space-between;padding:1.5rem 2rem;border-bottom:1px solid #1a1a1a;background:#0a0a0a;position:sticky;top:0;z-index:1000;}
        .logo{display:flex;align-items:center;gap:12px;font-size:1.5rem;letter-spacing:3px;font-weight:400;text-transform:uppercase;}
        .logo-img{width:40px;height:40px;object-fit:contain;}
        .minimal-nav ul{display:flex;list-style:none;gap:2.5rem;}
        .nav-link{text-decoration:none;color:#999;font-size:0.9rem;letter-spacing:1.5px;text-transform:uppercase;}
        .nav-link:hover,.nav-link.active{color:#fff;}
        .about-hero{height:70vh;min-height:600px;background:linear-gradient(rgba(0,0,0,0.7),rgba(0,0,0,0.7)),url('images/about4.jpg');background-size:cover;background-position:center;background-attachment:fixed;display:flex;align-items:center;justify-content:center;border-bottom:3px solid #d4af37;position:relative;z-index:1;}
        .hero-content{text-align:center;}
        .hero-title{font-size:6rem;font-weight:800;letter-spacing:10px;text-transform:uppercase;text-shadow:4px 4px 0 #d4af37;}
        .hero-subtitle{font-size:1.3rem;color:#d4af37;letter-spacing:6px;text-transform:uppercase;}
        .about-container{max-width:1200px;margin:0 auto;padding:5rem 2rem;}
        .story-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:2rem;margin-bottom:5rem;}
        .story-card{background:#111;padding:2rem;border-left:4px solid #d4af37;}
        .story-card h3{color:#d4af37;margin-bottom:1rem;}
        .street-cred{display:grid;grid-template-columns:1fr 1fr;gap:4rem;margin:5rem 0;align-items:center;}
        .street-text h2{font-size:2rem;border-left:4px solid #d4af37;padding-left:1.5rem;margin-bottom:1.5rem;}
        .street-image{width:100%;height:400px;object-fit:cover;filter:grayscale(100%);border:2px solid #d4af37;}
        .quote-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:2rem;margin:5rem 0;}
        .quote-card{background:#111;padding:2rem;border:1px solid #333;}
        .quote-card p{font-size:1.2rem;margin-bottom:1rem;}
        .quote-author{color:#d4af37;}
        .values-section{background:#111;padding:4rem;margin:5rem 0;}
        .values-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:3rem;}
        .value-item{text-align:center;padding:2rem;background:#0a0a0a;border:1px solid #333;}
        .value-number{font-size:3rem;color:#d4af37;opacity:0.3;}
        .message-section{text-align:center;padding:5rem;border-top:2px solid #d4af37;border-bottom:2px solid #d4af37;margin:5rem 0;}
        .message-content{font-size:2rem;color:#d4af37;}
        .image-gallery{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin:5rem 0;}
        .gallery-item{height:300px;overflow:hidden;border:1px solid #333;}
        .gallery-item img{width:100%;height:100%;object-fit:cover;filter:grayscale(100%);}
        .gallery-item:hover img{filter:grayscale(0%);transform:scale(1.1);}
        .collection-preview{display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;margin:5rem 0;}
        .preview-item{height:400px;overflow:hidden;border:1px solid #333;position:relative;}
        .preview-item img{width:100%;height:100%;object-fit:cover;filter:grayscale(80%);}
        .preview-overlay{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,#000);padding:2rem;text-align:center;transform:translateY(100%);transition:transform 0.3s;}
        .preview-item:hover .preview-overlay{transform:translateY(0);}
        .minimal-footer{border-top:1px solid #1a1a1a;padding:3rem;text-align:center;}
        .footer-text{color:#666;text-transform:uppercase;margin-bottom:1rem;}
        .social-links{display:flex;justify-content:center;gap:2rem;margin:1.5rem 0;}
        .social-link{display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:50%;background:#1a1a1a;transition:all 0.3s ease;text-decoration:none;}
        .social-link:hover{transform:translateY(-5px);background:#d4af37;}
        .social-link:hover svg{fill:#0a0a0a;}
        .social-link svg{width:24px;height:24px;fill:#d4af37;transition:fill 0.3s ease;}
        @media (max-width:992px){.hero-title{font-size:4rem;}.story-grid,.street-cred,.quote-grid{grid-template-columns:1fr;}.values-grid,.image-gallery{grid-template-columns:repeat(2,1fr);}}
        @media (max-width:768px){.hero-title{font-size:3rem;}.values-grid,.image-gallery,.collection-preview{grid-template-columns:1fr;}.minimal-header{flex-direction:column;}}
    </style>
</head>
<body>
    <header class="minimal-header">
        <div class="logo">
            <img src="images/logo.jpg" alt="MoneyWaste Logo" class="logo-img">
            MONEYWASTE
        </div>
        <nav class="minimal-nav">
            <ul>
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="products.php" class="nav-link">Collection</a></li>
                <li><a href="about.php" class="nav-link active">About</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
                <?php if(isLoggedIn()): ?>
                    <li><a href="logout.php" class="nav-link">Logout</a></li>
                    <?php if($_SESSION['username'] == 'admin'): ?>
                        <li><a href="admin/dashboard.php" class="nav-link">Admin</a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link">Login</a></li>
                    <li><a href="signup.php" class="nav-link">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    
    <section class="about-hero">
        <div class="hero-content">
            <h1 class="hero-title">MONEYWASTE</h1>
            <p class="hero-subtitle">STREET CODE • URBAN LEGEND • REAL TALK</p>
        </div>
    </section>
    
    <main class="about-container">
        <div class="story-grid">
            <div class="story-card"><h3>THE BEGINNING</h3><p>Started from the bottom, grinding every night. MoneyWaste was born in the backstreets, turning nothing into something.</p></div>
            <div class="story-card"><h3>THE HUSTLE</h3><p>While others sleep, we grind. Every piece carries the energy of late nights and relentless pursuit.</p></div>
            <div class="story-card"><h3>THE CODE</h3><p>Loyalty over everything. Respect is earned, never given. We play by our own rules.</p></div>
            <div class="story-card"><h3>THE FUTURE</h3><p>From the streets to the world. This is just the beginning. The movement grows stronger every day.</p></div>
        </div>
        
        <div class="image-gallery">
            <div class="gallery-item"><img src="images/about1.jpg" alt="Street Life"></div>
            <div class="gallery-item"><img src="images/about2.jpg" alt="Street Culture"></div>
            <div class="gallery-item"><img src="images/about3.jpg" alt="Street Movement"></div>
        </div>
        
        <div class="street-cred">
            <div class="street-text"><h2>THE MOVEMENT</h2><p>MoneyWaste ain't just a brand—it's a lifestyle. We represent the night crawlers, the dream chasers.</p><p>Every piece tells a story. Every design speaks the language of the streets.</p></div>
            <div><img src="images/about1.jpg" alt="Street Movement" class="street-image"></div>
        </div>
        
        <div class="quote-grid">
            <div class="quote-card"><p>Money comes and goes, but respect lasts forever.</p><div class="quote-author">— STREET WISDOM</div></div>
            <div class="quote-card"><p>In a world full of followers, be a leader.</p><div class="quote-author">— THE MOVEMENT</div></div>
            <div class="quote-card"><p>Every scar tells a story. Every victory builds a legacy.</p><div class="quote-author">— SURVIVAL</div></div>
            <div class="quote-card"><p>Never forget where you came from.</p><div class="quote-author">— ROOTS</div></div>
        </div>
        
        <div class="message-section">
            <h2>THE MESSAGE</h2>
            <div class="message-content">"STAY TRUE TO THE STREETS. STAY TRUE TO YOURSELF. THIS IS ARMOR."</div>
        </div>
        
        <div class="values-section">
            <div class="values-grid">
                <div class="value-item"><div class="value-number">01</div><h3>AUTHENTICITY</h3><p>Real street credibility. No fake stories.</p></div>
                <div class="value-item"><div class="value-number">02</div><h3>LOYALTY</h3><p>To the ones who stayed down. This is for you.</p></div>
                <div class="value-item"><div class="value-number">03</div><h3>RESPECT</h3><p>Earned on the streets, never given.</p></div>
            </div>
        </div>
        
        <div class="street-cred">
            <div><img src="images/about2.jpg" alt="Street Life" class="street-image"></div>
            <div class="street-text"><h2>THE REALITY</h2><p>Life in the streets made us who we are. MoneyWaste represents transformation from pain to power.</p></div>
        </div>
        
        <div style="text-align:center;margin:5rem 0;padding:4rem;border:2px solid #d4af37;background:#111;">
            <p style="font-size:3rem;letter-spacing:5px;">MONEY OVER EVERYTHING</p>
            <p style="color:#d4af37;">BUT RESPECT OVER MONEY</p>
            <p style="color:#666;">Born in the streets. Built for the struggle.</p>
        </div>
    </main>
    
    <footer class="minimal-footer">
        <p class="footer-text">MONEYWASTE • STREET CODE SINCE 2024 • AUTHENTIC STREETWEAR</p>
        
        <div class="social-links">
            <a href="https://www.instagram.com/moneywasteofficial" target="_blank" class="social-link" aria-label="Instagram">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM12 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
            </a>
            
            <a href="https://www.facebook.com/profile.php?id=100076257069294" target="_blank" class="social-link" aria-label="Facebook">
                <svg viewBox="0 0 24 24">
                    <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/>
                </svg>
            </a>
        </div>
        
        <p class="footer-text" style="font-size:0.8rem;">FOLLOW US ON SOCIAL MEDIA</p>
    </footer>
</body>
</html>