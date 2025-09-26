/**
 * Pusher Client for LMS
 * Handles real-time notifications and updates
 */

class PusherClient {
    constructor(pusherConfig, currentUserId, currentUserRole) {
        this.pusherConfig = pusherConfig;
        this.currentUserId = currentUserId;
        this.currentUserRole = currentUserRole;
        this.pusher = null;
        this.channel = null;
        this.isConnected = false;
        
        console.log('🚀 Initializing PusherClient...');
        console.log('📊 Config:', pusherConfig);
        console.log('👤 User ID:', currentUserId);
        console.log('🎭 Role:', currentUserRole);
        
        this.initialize();
    }
    
    initialize() {
        if (!this.pusherConfig || !this.pusherConfig.available) {
            console.warn('⚠️ Pusher not available, skipping initialization');
            return;
        }
        
        try {
            this.pusher = new Pusher(this.pusherConfig.app_key, {
                cluster: this.pusherConfig.cluster,
                encrypted: true
            });
            
            console.log('✅ Pusher instance created');
            
            // Subscribe to user-specific channel
            if (this.currentUserId) {
                this.channel = this.pusher.subscribe(`user-${this.currentUserId}`);
                console.log(`📡 Subscribed to user channel: user-${this.currentUserId}`);
                
                // Bind to notification events
                this.channel.bind('notification', (data) => {
                    console.log('📨 Received notification:', data);
                    this.handleNotification(data);
                });
            }
            
            // Subscribe to role-specific channels
            if (this.currentUserRole) {
                const roleChannel = this.pusher.subscribe(`role-${this.currentUserRole}`);
                console.log(`📡 Subscribed to role channel: role-${this.currentUserRole}`);
                
                roleChannel.bind('notification', (data) => {
                    console.log('📨 Received role notification:', data);
                    this.handleNotification(data);
                });
            }
            
            // Subscribe to general notifications channel
            const generalChannel = this.pusher.subscribe('notifications');
            console.log('📡 Subscribed to general notifications channel');
            
            generalChannel.bind('announcement', (data) => {
                console.log('📢 Received announcement:', data);
                this.handleNotification(data);
            });
            
            // Connection events
            this.pusher.connection.bind('connected', () => {
                console.log('✅ Connected to Pusher');
                this.isConnected = true;
                this.initializeEnrollmentRequestBadges();
            });
            
            this.pusher.connection.bind('disconnected', () => {
                console.log('❌ Disconnected from Pusher');
                this.isConnected = false;
            });
            
            this.pusher.connection.bind('error', (error) => {
                console.error('❌ Pusher connection error:', error);
            });
            
        } catch (error) {
            console.error('❌ Failed to initialize Pusher:', error);
        }
    }
    
    handleNotification(data) {
        console.log('🔔 Handling notification:', data.type);
        
        switch (data.type) {
            case 'new_enrollment_request':
                this.handleNewEnrollmentRequest(data);
                break;
                
            case 'enrollment_update':
                this.handleEnrollmentUpdate(data);
                break;
                
            case 'new_announcement':
                this.handleNewAnnouncement(data);
                break;
                
            case 'module_lock_update':
                this.handleModuleLockUpdate(data);
                break;
                
            case 'module_update':
                this.handleModuleUpdate(data);
                break;
                
            case 'leaderboard_update':
                this.handleLeaderboardUpdate(data);
                break;
                
            case 'score_update':
                this.handleScoreUpdate(data);
                break;
                
            case 'badge_awarded':
                this.handleBadgeAwarded(data);
                break;
                
            case 'course_kicked':
                this.handleCourseKicked(data);
                break;
                
            default:
                console.log('⚠️ Unknown notification type:', data.type);
                this.showNotificationToast(data);
        }
    }
    
    // ===== ENROLLMENT HANDLERS =====
    
    handleNewEnrollmentRequest(data) {
        console.log('🎯 New enrollment request received');
        
        // Show notification toast
        this.showNotificationToast(data);
        
        // Update teacher navbar badge immediately
        if (this.currentUserRole === 'teacher') {
            this.showTeacherNavbarRedDot();
        }
    }
    
    handleEnrollmentUpdate(data) {
        console.log('🔄 Enrollment update received:', data.status);
        
        // Show notification toast
        this.showNotificationToast(data);
        
        // Update student navbar badge immediately
        if (this.currentUserRole === 'student') {
            this.showStudentNavbarRedDot();
        }
    }
    
    handleCourseKicked(data) {
        console.log('🚫 Course kick notification received:', data);
        
        // Show notification toast
        this.showNotificationToast(data);
        
        // Update student navbar badge immediately
        if (this.currentUserRole === 'student') {
            this.showStudentNavbarRedDot();
        }
        
        // Optionally refresh the page to update course list
        setTimeout(() => {
            if (window.location.pathname.includes('courses') || window.location.pathname.includes('dashboard')) {
                window.location.reload();
            }
        }, 3000);
    }
    
    // ===== ANNOUNCEMENT HANDLERS =====
    
    handleNewAnnouncement(data) {
        console.log('📢 New announcement:', data.title);
        
        // Show notification toast
        this.showNotificationToast(data);
        
        // Update announcement count in navbar
        this.updateAnnouncementCount();
    }
    
    // ===== MODULE UPDATE HANDLERS =====
    
    handleModuleUpdate(data) {
        console.log('📝 Module update received:', data);
        
        // Update the UI immediately without page refresh
        this.updateModuleInTable(data);
        
        // Show notification toast only if it's not a lock status change
        // (lock changes are handled by the lock update handler)
        if (data.update_type !== 'lock_change') {
            this.showNotificationToast(data);
        }
    }
    
    updateModuleInTable(data) {
        console.log('🔄 Updating module in table...');
        
        // Find the module row in the table
        const moduleRows = document.querySelectorAll('.module-row');
        let targetRow = null;
        
        moduleRows.forEach(row => {
            const moduleId = row.querySelector('.module-checkbox')?.value;
            if (moduleId === data.module_id) {
                targetRow = row;
            }
        });
        
        if (targetRow) {
            // Update module title
            const titleElement = targetRow.querySelector('.module-info h6');
            if (titleElement) {
                titleElement.textContent = data.module_title;
            }
            
            // Update module description
            const descElement = targetRow.querySelector('.module-info p');
            if (descElement) {
                if (data.module_description) {
                    descElement.textContent = data.module_description.length > 100 ? 
                        data.module_description.substring(0, 100) + '...' : 
                        data.module_description;
                    descElement.style.display = 'block';
                } else {
                    descElement.style.display = 'none';
                }
            }
            
            // Update module order
            const orderBadge = targetRow.querySelector('td:nth-child(4) .badge');
            if (orderBadge) {
                orderBadge.textContent = data.module_order;
            }
            
            // Update lock status
            const statusBadge = targetRow.querySelector('.module-status-badge');
            if (statusBadge) {
                if (data.is_locked) {
                    statusBadge.className = 'badge module-status-badge bg-danger bg-opacity-75 fs-6 px-3 py-2';
                    statusBadge.innerHTML = '<i class="bi bi-lock-fill me-1"></i>Locked';
                } else {
                    statusBadge.className = 'badge module-status-badge bg-success bg-opacity-75 fs-6 px-3 py-2';
                    statusBadge.innerHTML = '<i class="bi bi-unlock-fill me-1"></i>Unlocked';
                }
            }
            
            // Update lock/unlock button
            const lockButton = targetRow.querySelector('.lock-module-btn');
            if (lockButton) {
                if (data.is_locked) {
                    lockButton.title = 'Unlock Module';
                    lockButton.innerHTML = '<i class="bi bi-unlock-fill"></i>';
                    lockButton.setAttribute('onclick', `toggleModuleLock('${data.module_id}', ${data.course_id}, 1, event)`);
                } else {
                    lockButton.title = 'Lock Module';
                    lockButton.innerHTML = '<i class="bi bi-lock-fill"></i>';
                    lockButton.setAttribute('onclick', `toggleModuleLock('${data.module_id}', ${data.course_id}, 0, event)`);
                }
            }
            
            // Update edit button onclick
            const editButton = targetRow.querySelector('.edit-module-btn');
            if (editButton) {
                const safeTitle = data.module_title ? data.module_title.replace(/'/g, "\\'") : '';
                const safeDescription = data.module_description ? data.module_description.replace(/'/g, "\\'") : '';
                editButton.setAttribute('onclick', `editModule('${data.module_id}', '${safeTitle}', '${safeDescription}', ${data.course_id}, ${data.module_order}, ${data.is_locked}, event)`);
            }
            
            // Update delete button onclick
            const deleteButton = targetRow.querySelector('.delete-module-btn');
            if (deleteButton) {
                const safeTitle = data.module_title ? data.module_title.replace(/'/g, "\\'") : '';
                deleteButton.setAttribute('onclick', `deleteModule('${data.module_id}', '${safeTitle}', ${data.course_id}, event)`);
            }
            
            // Add animation to show the update
            targetRow.style.transition = 'all 0.3s ease';
            targetRow.style.backgroundColor = '#f0fff4';
            setTimeout(() => {
                targetRow.style.backgroundColor = '';
            }, 1000);
            
            console.log('✅ Module updated in table');
        } else {
            console.warn('⚠️ Module row not found for ID:', data.module_id);
        }
    }
    
    // ===== MODULE LOCK HANDLERS =====
    
    handleModuleLockUpdate(data) {
        console.log('🔒 Module lock update received:', data);
        
        // Update the UI immediately without page refresh
        this.updateModuleLockStatus(data);
        
        // Show notification toast
        this.showNotificationToast(data);
    }
    
    updateModuleLockStatus(data) {
        console.log('🔄 Updating module lock status in UI...');
        
        // Find the module row in the table
        const moduleRows = document.querySelectorAll('.module-row');
        let targetRow = null;
        
        moduleRows.forEach(row => {
            const moduleId = row.querySelector('.module-checkbox')?.value;
            if (moduleId === data.module_id) {
                targetRow = row;
            }
        });
        
        if (targetRow) {
            // Update the status badge with animation (find the correct status badge)
            // Use the specific class we added to the status badge
            let statusBadge = targetRow.querySelector('.module-status-badge');
            
            // Fallback: try column selector
            if (!statusBadge) {
                statusBadge = targetRow.querySelector('td:nth-child(6) .badge'); // Status column is the 6th column
            }
            
            // Final fallback: look for badge that contains lock/unlock icons
            if (!statusBadge) {
                const badges = targetRow.querySelectorAll('.badge');
                badges.forEach(badge => {
                    if (badge.innerHTML.includes('lock-fill') || badge.innerHTML.includes('unlock-fill')) {
                        statusBadge = badge;
                    }
                });
            }
            
            if (statusBadge) {
                // Add transition effect
                statusBadge.style.transition = 'all 0.3s ease';
                
                if (data.is_locked) {
                    statusBadge.className = 'badge module-status-badge bg-danger bg-opacity-75 fs-6 px-3 py-2';
                    statusBadge.innerHTML = '<i class="bi bi-lock-fill me-1"></i>Locked';
                } else {
                    statusBadge.className = 'badge module-status-badge bg-success bg-opacity-75 fs-6 px-3 py-2';
                    statusBadge.innerHTML = '<i class="bi bi-unlock-fill me-1"></i>Unlocked';
                }
                
                // Add pulse animation
                statusBadge.style.animation = 'pulse 0.6s ease-in-out';
                setTimeout(() => {
                    statusBadge.style.animation = '';
                }, 600);
                
                console.log('✅ Status badge updated successfully');
            } else {
                console.warn('⚠️ Status badge not found in row');
            }
            
            // Update the lock/unlock button
            const lockButton = targetRow.querySelector('button[onclick*="toggleModuleLock"]');
            if (lockButton) {
                if (data.is_locked) {
                    lockButton.title = 'Unlock Module';
                    lockButton.innerHTML = '<i class="bi bi-unlock-fill"></i>';
                    // Update onclick to pass correct parameters
                    lockButton.setAttribute('onclick', `toggleModuleLock('${data.module_id}', ${data.course_id}, 1)`);
                } else {
                    lockButton.title = 'Lock Module';
                    lockButton.innerHTML = '<i class="bi bi-lock-fill"></i>';
                    // Update onclick to pass correct parameters
                    lockButton.setAttribute('onclick', `toggleModuleLock('${data.module_id}', ${data.course_id}, 0)`);
                }
            }
            
            // Add a subtle animation to show the change
            targetRow.style.transition = 'all 0.3s ease';
            targetRow.style.backgroundColor = data.is_locked ? '#fff5f5' : '#f0fff4';
            setTimeout(() => {
                targetRow.style.backgroundColor = '';
            }, 1000);
            
            // Add a subtle glow effect
            targetRow.style.boxShadow = data.is_locked ? '0 0 10px rgba(220, 53, 69, 0.3)' : '0 0 10px rgba(25, 135, 84, 0.3)';
            setTimeout(() => {
                targetRow.style.boxShadow = '';
            }, 1000);
            
            console.log('✅ Module lock status updated in UI');
        } else {
            console.warn('⚠️ Module row not found for ID:', data.module_id);
        }
    }
    
    // ===== LEADERBOARD HANDLERS =====
    
    handleLeaderboardUpdate(data) {
        console.log('🏆 Leaderboard update received:', data);
        
        // Update the leaderboard display in real-time
        this.updateLeaderboardDisplay(data);
        
        // Show notification toast
        this.showNotificationToast(data);
    }
    
    handleScoreUpdate(data) {
        console.log('📊 Score update received:', data);
        
        // Update individual student scores
        this.updateStudentScore(data);
        
        // Show notification toast
        this.showNotificationToast(data);
    }
    
    handleBadgeAwarded(data) {
        console.log('🏅 Badge awarded:', data);
        
        // Update badge count in leaderboard
        this.updateStudentBadges(data);
        
        // Show notification toast
        this.showNotificationToast(data);
    }
    
    updateLeaderboardDisplay(data) {
        console.log('🔄 Updating leaderboard display...');
        
        // Check if we're on the leaderboard page
        if (!window.location.pathname.includes('leaderboard.php')) {
            return;
        }
        
        // Update the entire leaderboard with new data
        if (data.leaderboard) {
            this.renderLeaderboard(data.leaderboard);
        }
        
        // Update current user's rank
        if (data.current_user_rank !== undefined) {
            this.updateCurrentUserRank(data.current_user_rank, data.total_students);
        }
    }
    
    updateStudentScore(data) {
        console.log('🔄 Updating student score...');
        
        // Find the student in the leaderboard
        const studentElements = document.querySelectorAll('.leaderboard-item');
        let targetStudent = null;
        
        studentElements.forEach(element => {
            const studentId = element.querySelector('[data-student-id]')?.getAttribute('data-student-id');
            if (studentId === data.student_id.toString()) {
                targetStudent = element;
            }
        });
        
        if (targetStudent) {
            // Update score display
            const scoreElement = targetStudent.querySelector('.text-end h6');
            if (scoreElement) {
                scoreElement.textContent = `${Math.round(data.new_score)} pts`;
                
                // Add animation
                scoreElement.style.transition = 'all 0.3s ease';
                scoreElement.style.color = '#28a745';
                scoreElement.style.transform = 'scale(1.1)';
                
                setTimeout(() => {
                    scoreElement.style.color = '';
                    scoreElement.style.transform = '';
                }, 1000);
            }
            
            // Update average score if provided
            if (data.average_score !== undefined) {
                const avgScoreElement = targetStudent.querySelector('.text-end small');
                if (avgScoreElement) {
                    avgScoreElement.textContent = `${Math.round(data.average_score, 1)}% avg`;
                }
            }
            
            // Add highlight effect
            targetStudent.style.transition = 'all 0.3s ease';
            targetStudent.style.backgroundColor = '#f0fff4';
            targetStudent.style.boxShadow = '0 0 10px rgba(40, 167, 69, 0.3)';
            
            setTimeout(() => {
                targetStudent.style.backgroundColor = '';
                targetStudent.style.boxShadow = '';
            }, 2000);
            
            console.log('✅ Student score updated');
        }
    }
    
    updateStudentBadges(data) {
        console.log('🔄 Updating student badges...');
        
        // Find the student in the leaderboard
        const studentElements = document.querySelectorAll('.leaderboard-item');
        let targetStudent = null;
        
        studentElements.forEach(element => {
            const studentId = element.querySelector('[data-student-id]')?.getAttribute('data-student-id');
            if (studentId === data.student_id.toString()) {
                targetStudent = element;
            }
        });
        
        if (targetStudent) {
            // Update badge count in the stats
            const badgeElements = targetStudent.querySelectorAll('.badge-badges');
            badgeElements.forEach(badge => {
                badge.textContent = data.badge_count;
                
                // Add celebration animation
                badge.style.animation = 'bounce 0.6s ease-in-out';
                setTimeout(() => {
                    badge.style.animation = '';
                }, 600);
            });
            
            // Add new badge to achievement badges section
            this.addNewBadgeToDisplay(data);
            
            console.log('✅ Student badges updated');
        }
    }
    
    addNewBadgeToDisplay(data) {
        // Find the achievement badges container
        const badgesContainer = document.querySelector('.achievement-badges');
        if (badgesContainer) {
            const newBadge = document.createElement('span');
            newBadge.className = 'achievement-badge badge-badges';
            newBadge.title = data.badge_name || 'New Badge';
            newBadge.innerHTML = '<i class="fas fa-trophy"></i>';
            
            // Add celebration effect
            newBadge.style.animation = 'badgeGlow 2s ease-in-out infinite alternate, bounce 0.6s ease-in-out';
            setTimeout(() => {
                newBadge.style.animation = 'badgeGlow 2s ease-in-out infinite alternate';
            }, 600);
            
            badgesContainer.appendChild(newBadge);
        }
    }
    
    updateCurrentUserRank(newRank, totalStudents) {
        console.log(`🔄 Updating current user rank: ${newRank}/${totalStudents}`);
        
        // Update rank display
        const rankElement = document.querySelector('.rank-badge');
        if (rankElement) {
            rankElement.textContent = newRank;
            
            // Add animation for rank change
            rankElement.style.animation = 'bounce 0.6s ease-in-out';
            setTimeout(() => {
                rankElement.style.animation = '';
            }, 600);
        }
        
        // Update rank text
        const rankTextElement = document.querySelector('small.text-muted');
        if (rankTextElement && totalStudents) {
            rankTextElement.textContent = `Rank #${newRank} out of ${totalStudents} students`;
        }
        
        // Update level indicator
        this.updateLevelIndicator(newRank);
    }
    
    updateLevelIndicator(rank) {
        const levelElement = document.querySelector('.level-indicator');
        if (levelElement) {
            let levelClass = '';
            let levelText = '';
            
            if (rank <= 3) {
                levelClass = 'level-expert';
                levelText = 'EXPERT';
            } else if (rank <= 10) {
                levelClass = 'level-advanced';
                levelText = 'ADVANCED';
            } else if (rank <= 20) {
                levelClass = 'level-intermediate';
                levelText = 'INTERMEDIATE';
            } else {
                levelClass = 'level-beginner';
                levelText = 'BEGINNER';
            }
            
            levelElement.className = `level-indicator ${levelClass}`;
            levelElement.textContent = levelText;
            
            // Add animation
            levelElement.style.animation = 'levelPulse 2s infinite';
        }
    }
    
    renderLeaderboard(leaderboardData) {
        console.log('🔄 Rendering updated leaderboard...');
        
        // This would re-render the entire leaderboard with new data
        // For now, we'll just log that we received the data
        // In a full implementation, you'd update the DOM with the new leaderboard data
        
        console.log('✅ Leaderboard data received:', leaderboardData.length, 'students');
    }
    
    // ===== NAVBAR UPDATE METHODS =====
    
    showTeacherNavbarRedDot() {
        console.log('🔴 Showing red dot immediately in teacher navbar...');
        
        const enrollmentRequestsLink = document.getElementById('teacher-enrollment-requests-link');
        
        if (enrollmentRequestsLink) {
            // Remove existing badge first
            const existingBadge = enrollmentRequestsLink.querySelector('.position-relative');
            if (existingBadge) {
                existingBadge.remove();
            }
            
            // Add red dot immediately
            const badgeContainer = document.createElement('span');
            badgeContainer.className = 'position-relative';
            badgeContainer.innerHTML = '<span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 8px; height: 8px; z-index: 1;"></span>';
            enrollmentRequestsLink.appendChild(badgeContainer);
            console.log('✅ Added red dot to teacher navbar enrollment requests link');
        }
    }
    
    showStudentNavbarRedDot() {
        console.log('🔴 Showing red dot immediately in student navbar...');
        
        const enrollmentRequestsLink = document.getElementById('student-enrollment-requests-link');
        
        if (enrollmentRequestsLink) {
            // Remove existing badge first
            const existingBadge = enrollmentRequestsLink.querySelector('.position-relative');
            if (existingBadge) {
                existingBadge.remove();
            }
            
            // Add red dot immediately
            const badgeContainer = document.createElement('span');
            badgeContainer.className = 'position-relative';
            badgeContainer.innerHTML = '<span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 8px; height: 8px; z-index: 1;"></span>';
            enrollmentRequestsLink.appendChild(badgeContainer);
            console.log('✅ Added red dot to student navbar enrollment requests link');
        }
    }
    
    // ===== INITIALIZATION METHODS =====
    
    async initializeEnrollmentRequestBadges() {
        console.log('🔧 Initializing enrollment request badges...');
        
        if (this.currentUserRole === 'teacher') {
            await this.updateTeacherNavbarEnrollmentBadge();
        } else if (this.currentUserRole === 'student') {
            await this.updateStudentNavbarEnrollmentBadge();
        }
    }
    
    async updateTeacherNavbarEnrollmentBadge() {
        console.log('🔄 Updating teacher navbar enrollment badge...');
        
        try {
            const response = await fetch('/lms_cap/ajax_get_enrollment_requests.php');
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.pending_count > 0) {
                    this.showTeacherNavbarRedDot();
                }
            }
        } catch (error) {
            console.warn('⚠️ Error fetching teacher enrollment count:', error);
        }
    }
    
    async updateStudentNavbarEnrollmentBadge() {
        console.log('🔄 Updating student navbar enrollment badge...');
        
        try {
            const response = await fetch('/lms_cap/ajax_get_student_enrollment_requests.php');
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.pending_count > 0) {
                    this.showStudentNavbarRedDot();
                }
            }
        } catch (error) {
            console.warn('⚠️ Error fetching student enrollment count:', error);
        }
    }
    
    // ===== UTILITY METHODS =====
    
    showNotificationToast(data) {
        console.log('🍞 Showing notification toast:', data);
        
        let title, message, type = 'info';
        
        switch (data.type) {
            case 'new_enrollment_request':
                title = 'New Enrollment Request';
                message = `${data.student_name} requested to enroll in ${data.course_name}`;
                type = 'info';
                break;
                
            case 'enrollment_update':
                title = 'Enrollment Update';
                message = `Your enrollment in ${data.course_name} has been ${data.status}`;
                type = data.status === 'approved' ? 'success' : 'warning';
                break;
                
            case 'new_announcement':
                title = 'New Announcement';
                message = data.title;
                type = 'info';
                break;
                
            case 'module_lock_update':
                title = 'Module Status Updated';
                message = `Module "${data.module_title}" has been ${data.is_locked ? 'locked' : 'unlocked'}`;
                type = data.is_locked ? 'warning' : 'success';
                break;
                
            case 'module_update':
                title = 'Module Updated';
                message = `Module "${data.module_title}" has been updated`;
                type = 'info';
                break;
                
            case 'leaderboard_update':
                title = 'Leaderboard Updated';
                message = 'The leaderboard has been updated with new rankings';
                type = 'info';
                break;
                
            case 'score_update':
                title = 'Score Updated';
                message = `Your score has been updated to ${Math.round(data.new_score)} points`;
                type = 'success';
                break;
                
            case 'badge_awarded':
                title = 'Badge Earned!';
                message = `Congratulations! You earned the "${data.badge_name}" badge`;
                type = 'success';
                break;
                
            default:
                title = 'Notification';
                message = data.message || 'You have a new notification';
                type = 'info';
        }
        
        // Create toast notification
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        // Add to toast container
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        
        // Show the toast
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        // Remove toast after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }
    
    updateAnnouncementCount() {
        console.log('📢 Updating announcement count...');
        // This would typically fetch the latest announcement count
        console.log('✅ Announcement count updated');
    }
    
    // ===== STUDENT DASHBOARD INITIALIZATION =====
    
    initializeStudentDashboard(userId) {
        console.log('🎓 Initializing student dashboard for user:', userId);
        
        // Set the user ID if not already set
        if (!this.currentUserId) {
            this.currentUserId = userId;
        }
        
        // Initialize enrollment request badges
        this.initializeEnrollmentRequestBadges();
        
        // Set up any student-specific real-time features
        this.setupStudentDashboardFeatures();
        
        console.log('✅ Student dashboard initialized successfully');
    }
    
    setupStudentDashboardFeatures() {
        console.log('🔧 Setting up student dashboard features...');
        
        // Add any student-specific dashboard features here
        // For example: real-time progress updates, notification badges, etc.
        
        console.log('✅ Student dashboard features set up');
    }
}

// Initialize PusherClient when DOM is ready - with delay to avoid conflicts
$(document).ready(function() {
    // Add a small delay to ensure Bootstrap dropdowns are initialized first
    setTimeout(function() {
        if (typeof window.pusherConfig !== 'undefined' && 
            typeof window.currentUserId !== 'undefined' && 
            typeof window.currentUserRole !== 'undefined') {
            
            window.pusherClient = new PusherClient(
                window.pusherConfig,
                window.currentUserId,
                window.currentUserRole
            );
            
            console.log('✅ PusherClient initialized successfully');
        } else {
            console.warn('⚠️ PusherClient initialization skipped - missing configuration');
        }
    }, 100); // 100ms delay
});
