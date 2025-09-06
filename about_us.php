<?php
// about_us.php
$current_page = 'about_us.php';
require_once 'includes/header.php';
?>
<style>
:root {
    --main-green: #2E5E4E;
    --accent-green: #7DCB80;
    --highlight-yellow: #FFE066;
    --off-white: #F7FAF7;
    --white: #FFFFFF;
}
body {
    background: linear-gradient(120deg, var(--off-white) 0%, var(--accent-green) 100%);
    min-height: 100vh;
    position: relative;
}
/* Subtle pattern overlay */
.about-bg-pattern {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    width: 100vw;
    height: 100vh;
    z-index: 0;
    pointer-events: none;
    opacity: 0.13;
    background: url('data:image/svg+xml;utf8,<svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="30" cy="30" r="1.5" fill="%237DCB80"/><circle cx="10" cy="50" r="1" fill="%23FDD744"/><circle cx="50" cy="10" r="1" fill="%232E5E4E"/></svg>');
    background-repeat: repeat;
}
/* Abstract SVG shapes overlay */
.about-bg-svg {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    width: 100vw;
    height: 100vh;
    z-index: 1;
    pointer-events: none;
}
.about-main-content {
    position: relative;
    z-index: 2;
}
.about-hero {
    text-align: center;
    margin-top: 3.5rem;
    margin-bottom: 2.5rem;
    color: var(--main-green);
    padding: 2.5rem 1rem 2rem 1rem;
    border-radius: 1.5rem;
    background: linear-gradient(120deg, var(--main-green) 60%, var(--accent-green) 100%);
    box-shadow: 0 8px 32px rgba(46,94,78,0.10);
    backdrop-filter: blur(2px);
    animation: fadeInDown 1s;
}
.about-hero img {
    max-width: 110px;
    margin-bottom: 1.2rem;
    filter: drop-shadow(0 4px 16px rgba(0,0,0,0.12));
}
.about-hero h1 {
    font-size: 2.7rem;
    font-weight: 800;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
    color: var(--highlight-yellow);
}
.about-hero p {
    font-size: 1.25rem;
    font-weight: 400;
    margin-bottom: 0;
    color: #eafbe7;
}
.team-section {
    background: var(--white);
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(46,94,78,0.10);
    padding: 2.5rem 1.5rem 2rem 1.5rem;
    margin-bottom: 2.5rem;
    animation: fadeInUp 1.2s;
}
.team-title {
    color: var(--main-green);
    font-weight: 700;
    font-size: 2rem;
    margin-bottom: 2.2rem;
    text-align: center;
}
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 2.2rem;
    justify-items: center;
}
.team-member {
    background: var(--off-white);
    border-radius: 1rem;
    box-shadow: 0 2px 16px rgba(46,94,78,0.08);
    padding: 2rem 1.2rem 1.5rem 1.2rem;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
    animation: fadeInUp 1.3s;
}
.team-member:hover {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 8px 32px rgba(125,203,128,0.13);
}
.team-member img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid var(--accent-green);
    margin-bottom: 1rem;
    transition: border 0.2s;
}
.team-member:hover img {
    border: 3px solid var(--main-green);
}
.team-member h5 {
    font-weight: 700;
    font-size: 1.15rem;
    margin-bottom: 0.2rem;
    color: var(--main-green);
}
.team-member .role {
    font-size: 0.98rem;
    color: var(--accent-green);
    font-weight: 600;
    margin-bottom: 0.3rem;
}
.team-member .email {
    font-size: 0.97rem;
    color: #374151;
    margin-bottom: 0.5rem;
    word-break: break-all;
}
.team-member .email-icon {
    color: var(--main-green);
    font-size: 1.3rem;
    margin-right: 0.3rem;
    vertical-align: middle;
}
.about-section {
    background: var(--white);
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(46,94,78,0.10);
    padding: 2.2rem 1.5rem 2rem 1.5rem;
    margin-bottom: 2.5rem;
    animation: fadeInUp 1.4s;
}
.about-section h3 {
    color: var(--main-green);
    font-weight: 700;
    margin-bottom: 1.2rem;
}
.about-features {
    list-style: none;
    padding-left: 0;
    margin-bottom: 0;
}
.about-features li {
    font-size: 1.08rem;
    margin-bottom: 0.5rem;
    color: #374151;
    position: relative;
    padding-left: 1.7rem;
}
.about-features li::before {
    content: '\2713';
    color: var(--main-green);
    position: absolute;
    left: 0;
    font-size: 1.2rem;
    top: 0.1rem;
}
.contact-section {
    background: var(--white);
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(46,94,78,0.10);
    padding: 2.2rem 1.5rem 2rem 1.5rem;
    text-align: center;
    animation: fadeInUp 1.5s;
}
.contact-section h3 {
    color: var(--main-green);
    font-weight: 700;
    margin-bottom: 1.2rem;
}
.contact-section .email-icon {
    color: var(--main-green);
    font-size: 1.3rem;
    margin-right: 0.3rem;
    vertical-align: middle;
}
.contact-section a {
    color: var(--main-green);
    text-decoration: underline;
    font-weight: 500;
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
<div class="about-bg-pattern"></div>
<div class="about-bg-svg">
    <svg width="100vw" height="100vh" viewBox="0 0 1440 600" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:100vw;height:100vh;">
        <ellipse cx="200" cy="100" rx="300" ry="120" fill="#7DCB80" fill-opacity="0.18"/>
        <ellipse cx="1240" cy="500" rx="320" ry="140" fill="#2E5E4E" fill-opacity="0.13"/>
        <ellipse cx="900" cy="100" rx="180" ry="80" fill="#FFE066" fill-opacity="0.10"/>
    </svg>
</div>
<div class="about-main-content">
    <div class="container" style="max-width: 1100px;">
        <div class="about-hero">
            <img src="uploads/logo/mainLogo.png" alt="NEUST LMS Logo">
            <h1>About Us</h1>
            <p>Empowering NEUST-MGT BSIT students and faculty with a modern, robust, and accessible digital learning platform.</p>
        </div>
        <div class="team-section">
            <div class="team-title">Meet the Team</div>
            <div class="team-grid">
                <div class="team-member">
                    <img src="uploads/profiles/mon.png" alt="mon">
                    <h5>Raymond V. Salvador</h5>
                    <div class="role">Team Leader/UI Lead/Developer</div>
                    <div class="email"><i class="bi bi-envelope-fill email-icon"></i>raymondvicente74@gmail.com</div>
                </div>
                <div class="team-member">
                    <img src="uploads/profiles/doshi.png" alt="doshi">
                    <h5>Lawrence J. Puesca</h5>
                    <div class="role">Documentation Lead/Developer</div>
                    <div class="email"><i class="bi bi-envelope-fill email-icon"></i>lawrencepuesca@gmail.com</div>
                </div>
                <div class="team-member">
                    <img src="uploads/profiles/jl.png" alt="jl">
                    <h5>John Lloyd N. Eusebio</h5>
                    <div class="role">Backend Lead/Developer</div>
                    <div class="email"><i class="bi bi-envelope-fill email-icon"></i>eusebiojohnlloyd512@gmail.com</div>
                </div>
                <div class="team-member">
                    <img src="uploads/profiles/sep.png" alt="sep">
                    <h5>John Joseph Espiritu</h5>
                    <div class="role">Tester Lead/Developer</div>
                    <div class="email"><i class="bi bi-envelope-fill email-icon"></i>johnjosephespiritu3596@gmail.com</div>
                </div>
            </div>
        </div>
        <div class="about-section">
            <h3>Our Platform</h3>
            <p>
                Welcome to the NEUST-MGT BSIT Learning Management System (LMS)!<br>
                Our LMS is designed to streamline the educational experience for students, teachers, and administrators. We provide a user-friendly platform for managing courses, assignments, grades, and communication within an academic environment.
            </p>
            <strong>Key Features:</strong>
            <ul class="about-features">
                <li>Course and content management</li>
                <li>Student enrollment and tracking</li>
                <li>Announcements and notifications</li>
                <li>Assessment and grading tools</li>
                <li>Badge and achievement system</li>
            </ul>
        </div>
        <div class="contact-section">
            <h3>Contact Us</h3>
            <div class="mb-2">
                <i class="bi bi-envelope-fill email-icon"></i><a href="mailto:raymond.salvador777@gmail.com">raymond.salvador777@gmail.com</a>
            </div>
            <div>
                <i class="bi bi-geo-alt-fill email-icon"></i> NEUST-MGT, 400 Diaz St, Talavera, Nueva Ecija, Philippines
            </div>
        </div>
        <div class="text-center mt-4 mb-3">
            <a href="index.php" class="btn btn-primary" style="background:var(--main-green); border:none; font-weight:600; padding:0.7rem 2.2rem; font-size:1.1rem; border-radius:2rem;">Back to Home</a>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 