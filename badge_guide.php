<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How to Earn Badges - LMS Guide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .badge-category {
            border-left: 4px solid #007bff;
            padding-left: 20px;
            margin-bottom: 30px;
        }
        .badge-category.course-completion {
            border-left-color: #28a745;
        }
        .badge-category.high-score {
            border-left-color: #ffc107;
        }
        .badge-category.participation {
            border-left-color: #17a2b8;
        }
        .criteria-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 3px solid #dee2e6;
        }
        .criteria-item:hover {
            border-left-color: #007bff;
            background: #e9ecef;
        }
        .progress-indicator {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #28a745);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="text-center mb-5">
                    <h1 class="display-4">
                        <i class="fas fa-trophy text-warning"></i>
                        How to Earn Badges
                    </h1>
                    <p class="lead text-muted">Complete various activities to unlock achievements and earn badges</p>
                </div>

                <!-- Course Completion Badges -->
                <div class="badge-category course-completion">
                    <h2 class="mb-4">
                        <i class="fas fa-graduation-cap text-success"></i>
                        Course Completion Badges
                    </h2>
                    <p class="text-muted">Earn badges by completing courses and modules</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="criteria-item">
                                <h5><i class="fas fa-star text-success"></i> First Course Complete</h5>
                                <p class="mb-2">Complete your first course successfully</p>
                                <div class="progress-indicator">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Progress: 0/1 courses completed</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="criteria-item">
                                <h5><i class="fas fa-crown text-warning"></i> Course Master</h5>
                                <p class="mb-2">Complete 5 courses</p>
                                <div class="progress-indicator">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Progress: 0/5 courses completed</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="criteria-item">
                                <h5><i class="fas fa-puzzle-piece text-info"></i> Module Explorer</h5>
                                <p class="mb-2">Complete 10 modules</p>
                                <div class="progress-indicator">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Progress: 0/10 modules completed</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- High Score Badges -->
                <div class="badge-category high-score">
                    <h2 class="mb-4">
                        <i class="fas fa-medal text-warning"></i>
                        High Score Badges
                    </h2>
                    <p class="text-muted">Earn badges by achieving excellent scores on assessments</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="criteria-item">
                                <h5><i class="fas fa-percentage text-danger"></i> Perfect Score</h5>
                                <p class="mb-2">Achieve a perfect score (100%) on any assessment</p>
                                <div class="progress-indicator">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Progress: 0/1 perfect scores</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="criteria-item">
                                <h5><i class="fas fa-chart-line text-success"></i> High Achiever</h5>
                                <p class="mb-2">Maintain an average score of 90% or higher</p>
                                <div class="progress-indicator">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Current average: 0%</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Participation Badges -->
                <div class="badge-category participation">
                    <h2 class="mb-4">
                        <i class="fas fa-users text-info"></i>
                        Participation Badges
                    </h2>
                    <p class="text-muted">Earn badges by actively participating in learning activities</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="criteria-item">
                                <h5><i class="fas fa-play-circle text-primary"></i> Video Watcher</h5>
                                <p class="mb-2">Watch 20 video lessons</p>
                                <div class="progress-indicator">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Progress: 0/20 videos watched</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="criteria-item">
                                <h5><i class="fas fa-clipboard-check text-success"></i> Assessment Taker</h5>
                                <p class="mb-2">Complete 10 assessments</p>
                                <div class="progress-indicator">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Progress: 0/10 assessments completed</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="criteria-item">
                                <h5><i class="fas fa-calendar-check text-info"></i> Consistent Learner</h5>
                                <p class="mb-2">Maintain consistent learning for 7 consecutive days</p>
                                <div class="progress-indicator">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Current streak: 0 days</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips Section -->
                <div class="card mt-5">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-lightbulb"></i>
                            Tips for Earning Badges
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="fas fa-check-circle text-success"></i> Complete Activities</h5>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-arrow-right text-primary"></i> Watch all video lessons in modules</li>
                                    <li><i class="fas fa-arrow-right text-primary"></i> Take all available assessments</li>
                                    <li><i class="fas fa-arrow-right text-primary"></i> Complete modules fully</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-star text-warning"></i> Aim for Excellence</h5>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-arrow-right text-primary"></i> Study thoroughly before assessments</li>
                                    <li><i class="fas fa-arrow-right text-primary"></i> Review materials after watching videos</li>
                                    <li><i class="fas fa-arrow-right text-primary"></i> Practice regularly to improve scores</li>
                                </ul>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h5><i class="fas fa-clock text-info"></i> Stay Consistent</h5>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-arrow-right text-primary"></i> Log in daily to maintain streaks</li>
                                    <li><i class="fas fa-arrow-right text-primary"></i> Set regular study schedules</li>
                                    <li><i class="fas fa-arrow-right text-primary"></i> Don't skip learning sessions</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-trophy text-warning"></i> Track Progress</h5>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-arrow-right text-primary"></i> Check your badge progress regularly</li>
                                    <li><i class="fas fa-arrow-right text-primary"></i> View your achievements dashboard</li>
                                    <li><i class="fas fa-arrow-right text-primary"></i> Celebrate your accomplishments</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center mt-5">
                    <a href="student/badges.php" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-trophy"></i> View My Badges
                    </a>
                    <a href="student/dashboard.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 