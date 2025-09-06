<?php
$page_title = 'Course Leaderboard';
require_once '../includes/header.php';
requireRole('teacher');
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap');

:root {
    --main-green: #2E5E4E;
    --accent-green: #7DCB80;
    --highlight-yellow: #FFE066;
    --off-white: #F7FAF7;
    --white: #FFFFFF;
}

body {
    background: linear-gradient(135deg, var(--off-white) 0%, var(--accent-green) 100%);
    font-family: 'Rajdhani', sans-serif;
    min-height: 100vh;
}

.game-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(46,94,78,0.1);
    margin: 20px 0;
    padding: 2rem;
}

.leaderboard-card {
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border: none;
    background: linear-gradient(145deg, #ffffff, #f8f9fa);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.leaderboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--main-green), var(--accent-green), var(--highlight-yellow));
    background-size: 200% 100%;
    animation: rainbow 3s ease-in-out infinite;
}

@keyframes rainbow {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.rank-1 {
    background: linear-gradient(135deg, var(--highlight-yellow), #fff6b0);
    color: var(--main-green);
    transform: scale(1.02);
    box-shadow: 0 15px 60px rgba(255, 224, 102, 0.3);
}

.rank-2 {
    background: linear-gradient(135deg, var(--accent-green), #9dd8a0);
    color: var(--main-green);
    transform: scale(1.01);
    box-shadow: 0 12px 30px rgba(125, 203, 128, 0.3);
}

.rank-3 {
    background: linear-gradient(135deg, var(--main-green), #3a6b5a);
    color: white;
    transform: scale(1.005);
    box-shadow: 0 10px 25px rgba(46, 94, 78, 0.3);
}

.leaderboard-item {
    transition: all 0.3s ease;
    border-radius: 15px;
    margin-bottom: 10px;
    position: relative;
    overflow: hidden;
}

.leaderboard-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.leaderboard-item:hover::before {
    left: 100%;
}

.leaderboard-item:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}

.rank-badge {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.3rem;
    font-family: 'Orbitron', monospace;
    position: relative;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    60% { transform: translateY(-5px); }
}

.rank-1 .rank-badge {
    background: linear-gradient(135deg, var(--highlight-yellow), #fff6b0);
    color: var(--main-green);
    box-shadow: 0 5px 15px rgba(255, 224, 102, 0.5);
}

.rank-2 .rank-badge {
    background: linear-gradient(135deg, var(--accent-green), #9dd8a0);
    color: var(--main-green);
    box-shadow: 0 4px 12px rgba(125, 203, 128, 0.5);
}

.rank-3 .rank-badge {
    background: linear-gradient(135deg, var(--main-green), #3a6b5a);
    color: white;
    box-shadow: 0 3px 10px rgba(46, 94, 78, 0.5);
}

.student-profile-pic {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.student-profile-link {
    display: block;
    text-decoration: none;
    transition: all 0.3s ease;
    border-radius: 50%;
}

.student-profile-link:hover {
    transform: scale(1.05);
}

.student-profile-link:hover .student-profile-pic {
    box-shadow: 0 8px 25px rgba(34, 197, 94, 0.4);
    border-color: rgba(34, 197, 94, 0.8);
}

.leaderboard-item:hover .student-profile-pic {
    transform: scale(1.1);
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
}

.rank-1 .student-profile-pic {
    border-color: var(--highlight-yellow);
    box-shadow: 0 5px 15px rgba(255, 224, 102, 0.5);
}

.rank-2 .student-profile-pic {
    border-color: var(--accent-green);
    box-shadow: 0 4px 12px rgba(125, 203, 128, 0.5);
}

.rank-3 .student-profile-pic {
    border-color: var(--main-green);
    box-shadow: 0 3px 10px rgba(46, 94, 78, 0.5);
}

.progress-container {
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
    height: 8px;
    margin: 5px 0;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    border-radius: 10px;
    background: linear-gradient(90deg, var(--main-green), var(--accent-green), var(--highlight-yellow));
    background-size: 200% 100%;
    animation: shimmer 2s ease-in-out infinite;
    transition: width 1s ease;
}

@keyframes shimmer {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.achievement-badge {
    display: inline-block;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    margin: 0 2px;
    text-align: center;
    line-height: 25px;
    font-size: 12px;
    color: white;
    animation: badgeGlow 2s ease-in-out infinite alternate;
}

@keyframes badgeGlow {
    0% { box-shadow: 0 0 5px currentColor; }
    100% { box-shadow: 0 0 20px currentColor; }
}

.badge-modules { background: var(--main-green); }
.badge-scores { background: var(--highlight-yellow); color: var(--main-green); }
.badge-videos { background: var(--accent-green); }
.badge-badges { background: var(--main-green); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    background: linear-gradient(145deg, #ffffff, #f8f9fa);
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--main-green), var(--accent-green));
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    font-family: 'Orbitron', monospace;
    color: var(--main-green);
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.game-title {
    font-family: 'Orbitron', monospace;
    font-weight: 900;
    background: linear-gradient(45deg, var(--main-green), var(--accent-green), var(--highlight-yellow));
    background-size: 300% 300%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: titleGlow 3s ease-in-out infinite;
    text-align: center;
    margin: 20px 0;
}

@keyframes titleGlow {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.section-badge {
    background: linear-gradient(135deg, var(--main-green), var(--accent-green));
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    animation: sectionPulse 2s ease-in-out infinite;
}

@keyframes sectionPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.filter-section {
    background: linear-gradient(145deg, #ffffff, #f8f9fa);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border: 2px solid rgba(46, 94, 78, 0.1);
}

.filter-section select {
    border-radius: 10px;
    border: 2px solid rgba(46, 94, 78, 0.2);
    transition: all 0.3s ease;
}

.filter-section select:focus {
    border-color: var(--main-green);
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.particles {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: -1;
}

.particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: rgba(46, 94, 78, 0.3);
    border-radius: 50%;
    animation: float 6s infinite linear;
}

@keyframes float {
    0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
}

/* Podium Styling */
.podium-container {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    margin: 30px 0;
    height: 300px;
    position: relative;
    background: 
        linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(16, 185, 129, 0.15)),
        linear-gradient(45deg, rgba(5, 150, 105, 0.1), rgba(6, 182, 212, 0.1)),
        radial-gradient(circle at 20% 80%, rgba(34, 197, 94, 0.2) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.2) 0%, transparent 50%),
        linear-gradient(to bottom, rgba(0, 0, 0, 0.02), rgba(0, 0, 0, 0.05));
    border-radius: 25px;
    padding: 25px;
    border: 3px solid rgba(34, 197, 94, 0.3);
    box-shadow: 
        0 15px 40px rgba(34, 197, 94, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.1),
        0 0 20px rgba(34, 197, 94, 0.1);
    overflow: hidden;
    animation: podiumGlow 3s ease-in-out infinite alternate;
}

.podium-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 50% 50%, rgba(255, 215, 0, 0.08) 0%, transparent 70%),
        radial-gradient(circle at 30% 30%, rgba(34, 197, 94, 0.1) 0%, transparent 60%),
        radial-gradient(circle at 70% 70%, rgba(16, 185, 129, 0.1) 0%, transparent 60%);
    border-radius: 25px;
    pointer-events: none;
    animation: podiumShimmer 4s ease-in-out infinite;
}

.podium-container::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: 
        repeating-linear-gradient(
            45deg,
            transparent,
            transparent 10px,
            rgba(34, 197, 94, 0.03) 10px,
            rgba(34, 197, 94, 0.03) 20px
        );
    animation: podiumScan 8s linear infinite;
    pointer-events: none;
    z-index: 1;
}

@keyframes podiumGlow {
    0% {
        box-shadow: 
            0 15px 40px rgba(34, 197, 94, 0.2),
            inset 0 1px 0 rgba(255, 255, 255, 0.1),
            0 0 20px rgba(34, 197, 94, 0.1);
        border-color: rgba(34, 197, 94, 0.3);
    }
    100% {
        box-shadow: 
            0 20px 50px rgba(34, 197, 94, 0.4),
            inset 0 1px 0 rgba(255, 255, 255, 0.2),
            0 0 30px rgba(34, 197, 94, 0.3);
        border-color: rgba(34, 197, 94, 0.5);
    }
}

@keyframes podiumShimmer {
    0%, 100% {
        opacity: 0.5;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.05);
    }
}

@keyframes podiumScan {
    0% {
        transform: translateX(-100%) translateY(-100%) rotate(45deg);
    }
    100% {
        transform: translateX(100%) translateY(100%) rotate(45deg);
    }
}

.podium-row {
    display: flex;
    align-items: flex-end;
    gap: 40px;
    position: relative;
    width: 100%;
    max-width: 500px;
}

.podium-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    transition: all 0.4s ease;
    min-width: 140px;
    flex: 1;
}

.podium-item:hover {
    transform: translateY(-15px);
}

/* 1st Place - Center with Gold */
.podium-1st {
    order: 2;
    z-index: 3;
    transform: scale(1.15);
}

/* 2nd Place - Left with Silver */
.podium-2nd {
    order: 1;
    z-index: 2;
    transform: scale(0.95);
}

/* 3rd Place - Right with Bronze */
.podium-3rd {
    order: 3;
    z-index: 1;
    transform: scale(0.85);
}

.podium-rank {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    font-family: 'Orbitron', monospace;
    color: white;
    z-index: 10;
}

.podium-1st .podium-rank {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #8B4513;
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6);
    animation: goldGlow 2s ease-in-out infinite alternate;
    border: 3px solid #FFA500;
}

.podium-2nd .podium-rank {
    background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
    color: #2F4F4F;
    box-shadow: 0 6px 20px rgba(192, 192, 192, 0.6);
    animation: silverGlow 2s ease-in-out infinite alternate;
    border: 3px solid #A9A9A9;
}

.podium-3rd .podium-rank {
    background: linear-gradient(135deg, #CD7F32, #B8860B);
    color: white;
    box-shadow: 0 4px 15px rgba(205, 127, 50, 0.6);
    animation: bronzeGlow 2s ease-in-out infinite alternate;
    border: 3px solid #B8860B;
}

@keyframes goldGlow {
    0% { box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6); }
    100% { box-shadow: 0 12px 35px rgba(255, 215, 0, 0.9); }
}

@keyframes silverGlow {
    0% { box-shadow: 0 6px 20px rgba(192, 192, 192, 0.6); }
    100% { box-shadow: 0 10px 30px rgba(192, 192, 192, 0.9); }
}

@keyframes bronzeGlow {
    0% { box-shadow: 0 4px 15px rgba(205, 127, 50, 0.6); }
    100% { box-shadow: 0 8px 25px rgba(205, 127, 50, 0.9); }
}

.podium-profile {
    width: 100px;
    height: 100px;
    border-radius: 50% !important;
    margin-bottom: 15px;
    position: relative;
    z-index: 5;
    overflow: hidden !important;
}

.podium-1st .podium-profile {
    width: 120px;
    height: 120px;
}

.podium-profile-pic {
    width: 100%;
    height: 100%;
    border-radius: 50% !important;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    background: linear-gradient(135deg, var(--accent-green), var(--main-green));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    overflow: hidden !important;
    position: relative;
    aspect-ratio: 1 !important;
}

.podium-profile-pic img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    border-radius: 50% !important;
    display: block;
}

.podium-profile-link {
    display: block;
    text-decoration: none;
    transition: all 0.3s ease;
    border-radius: 50%;
}

.podium-profile-link:hover {
    transform: scale(1.1);
}

.podium-profile-link:hover .podium-profile-pic {
    transform: scale(1.05);
}

.podium-1st .podium-profile-link:hover .podium-profile-pic {
    box-shadow: 0 15px 40px rgba(255, 215, 0, 0.8);
    border-color: rgba(255, 215, 0, 0.9);
}

.podium-2nd .podium-profile-link:hover .podium-profile-pic {
    box-shadow: 0 15px 40px rgba(192, 192, 192, 0.8);
    border-color: rgba(192, 192, 192, 0.9);
}

.podium-3rd .podium-profile-link:hover .podium-profile-pic {
    box-shadow: 0 15px 40px rgba(205, 127, 50, 0.8);
    border-color: rgba(205, 127, 50, 0.9);
}

.podium-placeholder {
    background: linear-gradient(135deg, var(--accent-green), var(--main-green));
    color: white;
    font-size: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50% !important;
    width: 100% !important;
    height: 100% !important;
    border: 4px solid white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    overflow: hidden !important;
    position: relative;
    aspect-ratio: 1 !important;
}

.podium-placeholder i {
    font-size: 2.5rem;
    z-index: 2;
    position: relative;
}

.podium-1st .podium-placeholder {
    background: linear-gradient(135deg, #FFD700, #FFA500) !important;
    color: #8B4513 !important;
    font-weight: bold;
    border-color: #FFD700 !important;
    box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6) !important;
    overflow: hidden !important;
}

.podium-2nd .podium-placeholder {
    background: linear-gradient(135deg, #C0C0C0, #A9A9A9) !important;
    color: #2F4F4F !important;
    font-weight: bold;
    border-color: #C0C0C0 !important;
    box-shadow: 0 12px 35px rgba(192, 192, 192, 0.6) !important;
    overflow: hidden !important;
}

.podium-3rd .podium-placeholder {
    background: linear-gradient(135deg, #CD7F32, #B8860B) !important;
    color: white !important;
    font-weight: bold;
    border-color: #CD7F32 !important;
    box-shadow: 0 12px 35px rgba(205, 127, 50, 0.6) !important;
    overflow: hidden !important;
}

.podium-1st .podium-profile-pic {
    border-color: #FFD700;
    box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
    background: linear-gradient(135deg, #FFD700, #FFA500);
    animation: goldPulse 3s ease-in-out infinite;
}

.podium-1st .podium-profile-pic img {
    border-color: #FFD700;
    box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
}

.podium-2nd .podium-profile-pic {
    border-color: #C0C0C0;
    box-shadow: 0 12px 35px rgba(192, 192, 192, 0.6);
    background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
    animation: silverPulse 3s ease-in-out infinite;
}

.podium-2nd .podium-profile-pic img {
    border-color: #C0C0C0;
    box-shadow: 0 12px 35px rgba(192, 192, 192, 0.6);
}

.podium-3rd .podium-profile-pic {
    border-color: #CD7F32;
    box-shadow: 0 12px 35px rgba(205, 127, 50, 0.6);
    background: linear-gradient(135deg, #CD7F32, #B8860B);
    animation: bronzePulse 3s ease-in-out infinite;
}

.podium-3rd .podium-profile-pic img {
    border-color: #CD7F32;
    box-shadow: 0 12px 35px rgba(205, 127, 50, 0.6);
}

@keyframes goldPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes silverPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes bronzePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.podium-name {
    font-weight: bold;
    font-size: 1rem;
    text-align: center;
    margin-bottom: 8px;
    color: var(--main-green);
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.podium-score {
    font-size: 0.9rem;
    font-weight: 600;
    color: #495057;
    text-align: center;
    background: rgba(255,255,255,0.8);
    padding: 4px 12px;
    border-radius: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    position: relative;
    z-index: 10;
}

.podium-1st .podium-name {
    color: #8B4513;
    font-size: 1.1rem;
}

.podium-1st .podium-score {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #8B4513;
    font-weight: bold;
    font-size: 1rem;
    padding: 6px 16px;
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
    z-index: 15;
}

.podium-2nd .podium-name {
    color: #2F4F4F;
}

.podium-2nd .podium-score {
    background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
    color: #2F4F4F;
    font-weight: bold;
}

.podium-3rd .podium-name {
    color: #8B4513;
}

.podium-3rd .podium-score {
    background: linear-gradient(135deg, #CD7F32, #B8860B);
    color: white;
    font-weight: bold;
}

/* Podium Base */
.podium-item::after {
    content: '';
    position: absolute;
    bottom: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 25px;
    background: linear-gradient(135deg, var(--main-green), var(--accent-green));
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(46, 94, 78, 0.4);
}

.podium-1st::after {
    width: 160px;
    height: 35px;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    box-shadow: 0 10px 30px rgba(255, 215, 0, 0.6);
    border-radius: 20px;
}

.podium-2nd::after {
    width: 140px;
    height: 30px;
    background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
    box-shadow: 0 8px 25px rgba(192, 192, 192, 0.6);
    border-radius: 18px;
}

.podium-3rd::after {
    width: 120px;
    height: 25px;
    background: linear-gradient(135deg, #CD7F32, #B8860B);
    box-shadow: 0 6px 20px rgba(205, 127, 50, 0.6);
    border-radius: 15px;
}

/* Special effects for top 3 */
.podium-1st {
    animation: goldGlow 3s ease-in-out infinite;
}

.podium-2nd {
    animation: silverGlow 3s ease-in-out infinite;
}

.podium-3rd {
    animation: bronzeGlow 3s ease-in-out infinite;
}

@keyframes goldGlow {
    0%, 100% { 
        filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3));
    }
    50% { 
        filter: drop-shadow(0 0 20px rgba(255, 215, 0, 0.6));
    }
}

@keyframes silverGlow {
    0%, 100% { 
        filter: drop-shadow(0 0 8px rgba(192, 192, 192, 0.3));
    }
    50% { 
        filter: drop-shadow(0 0 16px rgba(192, 192, 192, 0.6));
    }
}

@keyframes bronzeGlow {
    0%, 100% { 
        filter: drop-shadow(0 0 6px rgba(205, 127, 50, 0.3));
    }
    50% { 
        filter: drop-shadow(0 0 12px rgba(205, 127, 50, 0.6));
    }
}

/* Medal icons for top 3 */
.podium-1st .podium-rank::before {
    content: '🥇';
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 24px;
    animation: medalBounce 2s ease-in-out infinite;
}

.podium-2nd .podium-rank::before {
    content: '🥈';
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 20px;
    animation: medalBounce 2s ease-in-out infinite 0.3s;
}

.podium-3rd .podium-rank::before {
    content: '🥉';
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 18px;
    animation: medalBounce 2s ease-in-out infinite 0.6s;
}

@keyframes medalBounce {
    0%, 20%, 50%, 80%, 100% { transform: translateX(-50%) translateY(0); }
    40% { transform: translateX(-50%) translateY(-8px); }
    60% { transform: translateX(-50%) translateY(-4px); }
}

@media (max-width: 768px) {
    .game-container {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .leaderboard-item {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
}
</style>

<?php
$course_id = (int)($_GET['course_id'] ?? 0);
$selected_section_id = (int)($_GET['section_id'] ?? 0);

// Verify teacher owns this course
$stmt = $db->prepare("
    SELECT c.*, ap.academic_year, ap.semester_name, u.first_name, u.last_name
    FROM courses c
    JOIN academic_periods ap ON c.academic_period_id = ap.id
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ? AND c.teacher_id = ?
");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if (!$course) {
    redirectWithMessage('courses.php', 'Course not found or access denied.', 'danger');
}

// Get all sections assigned to this course
// Get sections for this course (from courses.sections JSON)
$stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$sections_json = $stmt->fetchColumn();
$sections = [];
if ($sections_json) {
    $section_ids = json_decode($sections_json, true);
    if (is_array($section_ids) && !empty($section_ids)) {
        $placeholders = str_repeat('?,', count($section_ids) - 1) . '?';
        $stmt = $db->prepare("SELECT id, section_name as name, year_level as year FROM sections WHERE id IN ($placeholders) ORDER BY year_level, section_name");
        $stmt->execute($section_ids);
        $sections = $stmt->fetchAll();
    }
}

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year']}{$section['name']}";
}

// Get students enrolled in this course (normalized schema)
$stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular, u.identifier
                      FROM course_enrollments ce
                      JOIN users u ON ce.student_id = u.id
                      WHERE ce.course_id = ? AND ce.status = 'active'
                      ORDER BY u.last_name, u.first_name");
$stmt->execute([$course_id]);
$students_in_sections = $stmt->fetchAll();

// Build the leaderboard query for normalized schema
$leaderboard_sql = "
    SELECT 
        u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier,
        COALESCE(s.section_name, 'Not Assigned') as section_name, 
        COALESCE(s.year_level, 'N/A') as section_year,
        0 as badge_count,
        0 as completed_modules,
        0 as watched_videos,
        (SELECT AVG(aa.score) FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE aa.student_id = u.id AND a.course_id = ? AND aa.status = 'completed') as average_score,
        (SELECT COUNT(*) FROM assessment_attempts aa 
         JOIN assessments a ON aa.assessment_id = a.id 
         WHERE aa.student_id = u.id AND a.course_id = ? AND aa.score >= 70 AND aa.status = 'completed') as high_scores,
        (
            COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa 
                      JOIN assessments a ON aa.assessment_id = a.id 
                      WHERE aa.student_id = u.id AND a.course_id = ? AND aa.status = 'completed'), 0) * 0.5 +
            0 * 5
        ) as calculated_score
    FROM course_enrollments ce
    JOIN users u ON ce.student_id = u.id
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE ce.course_id = ? AND ce.status = 'active'
";

// Add section filter if specified
if ($selected_section_id > 0) {
    $leaderboard_sql .= " AND s.id = ?";
    $params = [$course_id, $course_id, $course_id, $course_id, $selected_section_id];
} else {
    $params = [$course_id, $course_id, $course_id, $course_id];
}

$leaderboard_sql .= " ORDER BY calculated_score DESC";

$stmt = $db->prepare($leaderboard_sql);
$stmt->execute($params);
$leaderboard = $stmt->fetchAll();

// Get course statistics
$total_students = count($leaderboard);
$total_modules = 0;
$total_videos = 0;
$total_assessments = 0;

if ($total_students > 0) {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(JSON_LENGTH(modules), 0) as total_modules,
            0 as total_videos,
            (SELECT COUNT(*) FROM assessments WHERE course_id = ?) as total_assessments
        FROM courses WHERE id = ?
    ");
    $stmt->execute([$course_id, $course_id]);
    $course_stats = $stmt->fetch();
    $total_modules = $course_stats['total_modules'];
    $total_videos = $course_stats['total_videos'];
    $total_assessments = $course_stats['total_assessments'];
}
?>

<!-- Floating Particles -->
<div class="particles" id="particles"></div>

<div class="container-fluid" style="margin-bottom: 150px;">
    <a href="course.php?id=<?php echo $course_id; ?>" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Back to Course
    </a>
    
    <div class="game-container">
        <h1 class="game-title">🏆 COURSE LEADERBOARD ARENA 🏆</h1>
        <p class="text-center text-muted mb-4">
            <?php echo htmlspecialchars($course['course_name']); ?> • <?php echo htmlspecialchars($course['course_code']); ?>
        </p>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-2"><i class="bi bi-funnel me-2"></i>Filter by Section</h5>
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <select name="section_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" 
                                        <?php echo $selected_section_id == $section['id'] ? 'selected' : ''; ?>>
                                    <?php echo formatSectionName($section); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        <strong><?php echo $total_students; ?></strong> students found
                        <?php if ($selected_section_id > 0): ?>
                            in selected section
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_modules; ?></div>
                <div class="stat-label">Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_videos; ?></div>
                <div class="stat-label">Videos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_assessments; ?></div>
                <div class="stat-label">Assessments</div>
            </div>
        </div>
        
        <!-- Leaderboard -->
        <div class="card leaderboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-trophy me-2"></i>Student Rankings
                    <?php if ($selected_section_id > 0): ?>
                        <span class="section-badge ms-2">
                            <?php 
                            $selected_section_name = '';
                            foreach ($sections as $section) {
                                if ($section['id'] == $selected_section_id) {
                                    $selected_section_name = formatSectionName($section);
                                    break;
                                }
                            }
                            echo htmlspecialchars($selected_section_name);
                            ?>
                        </span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($leaderboard)): ?>
                    <div class="empty-state">
                        <i class="bi bi-people"></i>
                        <h5>No Students Found</h5>
                        <p>No students are enrolled in this course for the selected section.</p>
                    </div>
                <?php else: ?>
                    <!-- Podium for Top 3 -->
                    <?php if (count($leaderboard) >= 3): ?>
                        <div class="podium-container mb-4">
                            <div class="podium-row">
                                <!-- 2nd Place -->
                                <div class="podium-item podium-2nd">
                                    <div class="podium-rank">2</div>
                                    <div class="podium-profile">
                                        <?php 
                                        $student = $leaderboard[1] ?? null;
                                        if ($student): 
                                        ?>
                                            <a href="student_detail.php?id=<?php echo $student['id']; ?>&course=<?php echo $course_id; ?>" 
                                               class="podium-profile-link" title="View <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>'s details">
                                                <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'large'); ?>" 
                                                     alt="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                     class="podium-profile-pic">
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="podium-name"><?php echo $student ? htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) : 'N/A'; ?></div>
                                    <div class="podium-score"><?php echo $student ? round($student['calculated_score'] ?? 0) : 0; ?> pts</div>
                                </div>
                                
                                <!-- 1st Place -->
                                <div class="podium-item podium-1st">
                                    <div class="podium-rank">1</div>
                                    <div class="podium-profile">
                                        <?php 
                                        $student = $leaderboard[0] ?? null;
                                        if ($student): 
                                        ?>
                                            <a href="student_detail.php?id=<?php echo $student['id']; ?>&course=<?php echo $course_id; ?>" 
                                               class="podium-profile-link" title="View <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>'s details">
                                                <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'large'); ?>" 
                                                     alt="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                     class="podium-profile-pic">
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="podium-name"><?php echo $student ? htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) : 'N/A'; ?></div>
                                    <div class="podium-score"><?php echo $student ? round($student['calculated_score'] ?? 0) : 0; ?> pts</div>
                                </div>
                                
                                <!-- 3rd Place -->
                                <div class="podium-item podium-3rd">
                                    <div class="podium-rank">3</div>
                                    <div class="podium-profile">
                                        <?php 
                                        $student = $leaderboard[2] ?? null;
                                        if ($student): 
                                        ?>
                                            <a href="student_detail.php?id=<?php echo $student['id']; ?>&course=<?php echo $course_id; ?>" 
                                               class="podium-profile-link" title="View <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>'s details">
                                                <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'large'); ?>" 
                                                     alt="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                     class="podium-profile-pic">
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="podium-name"><?php echo $student ? htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) : 'N/A'; ?></div>
                                    <div class="podium-score"><?php echo $student ? round($student['calculated_score'] ?? 0) : 0; ?> pts</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Rest of Leaderboard (4th onwards) -->
                    <?php foreach (array_slice($leaderboard, 3) as $index => $student): ?>
                        <div class="leaderboard-item d-flex align-items-center p-3 mb-2 rounded" 
                             style="animation-delay: <?php echo ($index + 3) * 0.1; ?>s;">
                            <div class="rank-badge me-3"><?php echo $index + 4; ?></div>
                            <div class="me-3">
                                <a href="student_detail.php?id=<?php echo $student['id']; ?>&course=<?php echo $course_id; ?>" 
                                   class="student-profile-link" title="View <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>'s details">
                                    <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'medium'); ?>" 
                                         alt="Profile" class="student-profile-pic">
                                </a>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                <small class="text-muted">
                                    <?php echo $student['badge_count']; ?> badges • 
                                    <?php echo $student['completed_modules']; ?> modules • 
                                    <?php echo $student['watched_videos']; ?> videos
                                </small>
                                
                                <!-- Progress Bar -->
                                <div class="progress-container mt-2">
                                    <div class="progress-bar" style="width: <?php echo min(100, ($student['calculated_score'] / max(array_column($leaderboard, 'calculated_score'))) * 100); ?>%"></div>
                                </div>
                                
                                <!-- Achievement Badges -->
                                <div class="mt-2">
                                    <?php if ($student['completed_modules'] > 0): ?>
                                        <span class="achievement-badge badge-modules" title="Modules Completed"><i class="bi bi-book"></i></span>
                                    <?php endif; ?>
                                    <?php if ($student['average_score'] > 70): ?>
                                        <span class="achievement-badge badge-scores" title="High Scorer"><i class="bi bi-star"></i></span>
                                    <?php endif; ?>
                                    <?php if ($student['watched_videos'] > 0): ?>
                                        <span class="achievement-badge badge-videos" title="Video Watcher"><i class="bi bi-play"></i></span>
                                    <?php endif; ?>
                                    <?php if ($student['badge_count'] > 0): ?>
                                        <span class="achievement-badge badge-badges" title="Badge Collector"><i class="bi bi-trophy"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end me-3">
                                <span class="section-badge">
                                    <?php echo formatSectionName(['year' => $student['section_year'], 'name' => $student['section_name']]); ?>
                                </span>
                            </div>
                            <div class="text-end">
                                <h6 class="mb-0"><?php echo round($student['calculated_score'] ?? 0); ?> pts</h6>
                                <?php if ($student['average_score']): ?>
                                    <small class="text-muted"><?php echo round($student['average_score'] ?? 0, 1); ?>% avg</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- If less than 3 students, show them in regular format -->
                    <?php if (count($leaderboard) < 3): ?>
                        <?php foreach ($leaderboard as $index => $student): ?>
                            <?php 
                            $rank_class = '';
                            if ($index < 3) $rank_class = 'rank-' . ($index + 1);
                            ?>
                            <div class="leaderboard-item d-flex align-items-center p-3 mb-2 rounded <?php echo $rank_class; ?>" 
                                 style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <div class="rank-badge me-3"><?php echo $index + 1; ?></div>
                                <div class="me-3">
                                    <a href="student_detail.php?id=<?php echo $student['id']; ?>&course=<?php echo $course_id; ?>" 
                                       class="student-profile-link" title="View <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>'s details">
                                        <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'medium'); ?>" 
                                             alt="Profile" class="student-profile-pic">
                                    </a>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo $student['badge_count']; ?> badges • 
                                        <?php echo $student['completed_modules']; ?> modules • 
                                        <?php echo $student['watched_videos']; ?> videos
                                    </small>
                                    
                                    <!-- Progress Bar -->
                                    <div class="progress-container mt-2">
                                        <div class="progress-bar" style="width: <?php echo min(100, ($student['calculated_score'] / max(array_column($leaderboard, 'calculated_score'))) * 100); ?>%"></div>
                                    </div>
                                    
                                    <!-- Achievement Badges -->
                                    <div class="mt-2">
                                        <?php if ($student['completed_modules'] > 0): ?>
                                            <span class="achievement-badge badge-modules" title="Modules Completed"><i class="bi bi-book"></i></span>
                                        <?php endif; ?>
                                        <?php if ($student['average_score'] > 70): ?>
                                            <span class="achievement-badge badge-scores" title="High Scorer"><i class="bi bi-star"></i></span>
                                        <?php endif; ?>
                                        <?php if ($student['watched_videos'] > 0): ?>
                                            <span class="achievement-badge badge-videos" title="Video Watcher"><i class="bi bi-play"></i></span>
                                        <?php endif; ?>
                                        <?php if ($student['badge_count'] > 0): ?>
                                            <span class="achievement-badge badge-badges" title="Badge Collector"><i class="bi bi-trophy"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end me-3">
                                    <span class="section-badge">
                                        <?php echo formatSectionName(['year' => $student['section_year'], 'name' => $student['section_name']]); ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <h6 class="mb-0"><?php echo round($student['calculated_score'] ?? 0); ?> pts</h6>
                                    <?php if ($student['average_score']): ?>
                                        <small class="text-muted"><?php echo round($student['average_score'] ?? 0, 1); ?>% avg</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Create floating particles
function createParticles() {
    const particlesContainer = document.getElementById('particles');
    const particleCount = 50;
    
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 6 + 's';
        particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
        particlesContainer.appendChild(particle);
    }
}

// Animate leaderboard items on load
function animateLeaderboard() {
    const items = document.querySelectorAll('.leaderboard-item');
    items.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Animate podium items
function animatePodium() {
    const podiumItems = document.querySelectorAll('.podium-item');
    podiumItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(50px) scale(0.8)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.8s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0) scale(1)';
        }, index * 200);
    });
}

// Initialize animations
document.addEventListener('DOMContentLoaded', function() {
    createParticles();
    animatePodium();
    animateLeaderboard();
});
</script>

<?php require_once '../includes/footer.php'; ?>
