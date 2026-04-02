<?php
/**
 * index.php – Entry/Splash Screen
 * Student Home Visit Map System
 */
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= APP_NAME ?> · Welcome</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&family=Outfit:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-dark: #0f172a;
      --accent: #4f46e5;
      --accent-light: #818cf8;
      --text: #f8fafc;
      --text-muted: #94a3b8;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      background-color: var(--bg-dark);
      color: var(--text);
      font-family: 'Sarabun', 'Outfit', sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    /* Ambient Background Particles Effect */
    .ambient-bg {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: radial-gradient(circle at 50% 50%, #1e293b 0%, #0f172a 100%);
      z-index: -1;
    }

    .glow-sphere {
      position: absolute;
      width: 40vw; height: 40vw;
      background: radial-gradient(circle, rgba(79, 70, 229, 0.15) 0%, rgba(79, 70, 229, 0) 70%);
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      filter: blur(40px);
      animation: pulse-glow 8s infinite alternate;
    }

    @keyframes pulse-glow {
      from { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
      to { transform: translate(-50%, -50%) scale(1.3); opacity: 0.8; }
    }

    /* Main Container */
    .container {
      text-align: center;
      z-index: 10;
      width: 100%;
      max-width: 500px;
      padding: 2rem;
    }

    /* Logo / Icon Animation */
    .logo-container {
      margin-bottom: 2.5rem;
      position: relative;
    }

    .logo-icon {
      font-size: 5rem;
      display: inline-block;
      animation: float 4s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-15px); }
    }

    .logo-ring {
      position: absolute;
      top: 50%; left: 50%;
      width: 120px; height: 120px;
      margin-top: -60px; margin-left: -60px;
      border: 2px solid rgba(79, 70, 229, 0.3);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin 1.5s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Typography */
    .title {
      font-family: 'Outfit', sans-serif;
      font-size: 1.8rem;
      font-weight: 800;
      letter-spacing: -0.01em;
      margin-bottom: 0.5rem;
      background: linear-gradient(to bottom right, #fff, #94a3b8);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      opacity: 0;
      animation: fade-in-up 0.8s forwards 0.2s;
    }

    .subtitle {
      font-size: 1rem;
      color: var(--text-muted);
      margin-bottom: 3rem;
      opacity: 0;
      animation: fade-in-up 0.8s forwards 0.4s;
    }

    @keyframes fade-in-up {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Loading Bar */
    .loader-wrap {
      width: 240px;
      height: 4px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 99px;
      margin: 0 auto;
      position: relative;
      overflow: hidden;
      opacity: 0;
      animation: fade-in 0.5s forwards 0.6s;
    }

    .loader-bar {
      position: absolute;
      top: 0; left: 0;
      height: 100%;
      background: linear-gradient(90deg, var(--accent), var(--accent-light));
      width: 0%;
      border-radius: 99px;
      box-shadow: 0 0 15px rgba(79, 70, 229, 0.5);
      transition: width 0.1s ease-out;
    }

    @keyframes fade-in {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .loading-text {
      margin-top: 1rem;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      color: var(--accent-light);
      opacity: 0;
      animation: fade-in 0.5s forwards 0.8s;
    }

    /* Particle effect helper */
    .dot {
      position: absolute;
      background: white;
      border-radius: 50%;
      opacity: 0.15;
      pointer-events: none;
    }
  </style>
</head>
<body>

  <div class="ambient-bg"></div>
  <div class="glow-sphere"></div>

  <div class="container" id="mainContainer">
    <div class="logo-container">
      <div class="logo-ring"></div>
      <div class="logo-icon">🗺️</div>
    </div>

    <h1 class="title"><?= APP_NAME ?></h1>
    <p class="subtitle">ระบบแผนความคืบหน้าการสำรวจข้อมูลและเยี่ยมบ้าน</p>

    <div class="loader-wrap">
      <div class="loader-bar" id="loaderBar"></div>
    </div>
    <div class="loading-text" id="loadingState">Initializing System...</div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const bar = document.getElementById('loaderBar');
      const state = document.getElementById('loadingState');
      const phrases = [
        'กำลังเตรียมฐานข้อมูล...',
        'กำลังดึงพิกัดแผนที่...',
        'ตรวจสอบความปลอดภัย...',
        'พร้อมสำรวจข้อมูลแล้ว'
      ];
      
      let progress = 0;
      let phraseIndex = 0;

      // Update progress bar
      const interval = setInterval(() => {
        progress += Math.random() * 2;
        if (progress > 100) progress = 100;
        
        bar.style.width = progress + '%';

        // Update phrases
        if (progress > (phraseIndex + 1) * 25 && phraseIndex < phrases.length) {
          state.textContent = phrases[phraseIndex];
          phraseIndex++;
        }

        if (progress === 100) {
          clearInterval(interval);
          
          // Subtle fade out before redirect
          setTimeout(() => {
            document.getElementById('mainContainer').style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
            document.getElementById('mainContainer').style.opacity = '0';
            document.getElementById('mainContainer').style.transform = 'scale(0.95)';
            
            setTimeout(() => {
              window.location.href = 'public/';
            }, 600);
          }, 400);
        }
      }, 30);

      // Add floating particles
      for (let i = 0; i < 20; i++) {
        const dot = document.createElement('div');
        dot.className = 'dot';
        const size = Math.random() * 3;
        dot.style.width = size + 'px';
        dot.style.height = size + 'px';
        dot.style.left = Math.random() * 100 + 'vw';
        dot.style.top = Math.random() * 100 + 'vh';
        dot.style.animation = `float ${5 + Math.random() * 10}s linear infinite`;
        dot.style.opacity = Math.random() * 0.2;
        document.body.appendChild(dot);
      }
    });
  </script>
</body>
</html>
