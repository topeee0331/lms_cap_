        </div>
    </main>

    <!-- Footer -->
    <footer class="footer mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p class="mb-0">Learning Management System for NEUST-MGT BSIT Department</p>
                    <small>BSIT Capstone Project 2025</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; 2025 BSIT 4E 2025-2026 Group 4</p>
                    <small>Raymond V. Salvador, Lawrence J. Puesca, John Lloyd N. Eusebio, John Joseph Espiritu • All rights reserved</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Confirm delete actions
        $('.delete-confirm').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
        
        // Assessment timer
        function startTimer(duration, display) {
            var timer = duration, minutes, seconds;
            var interval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = minutes + ":" + seconds;

                if (--timer < 0) {
                    clearInterval(interval);
                    display.textContent = "Time's up!";
                    // Auto-submit the assessment
                    document.getElementById('assessment-form').submit();
                }
            }, 1000);
        }
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Chart.js configuration
        Chart.defaults.color = '#6c757d';
        Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
        
        // Progress bar animation
        function animateProgressBars() {
            $('.progress-bar').each(function() {
                var $this = $(this);
                var percentage = $this.attr('aria-valuenow');
                $this.css('width', '0%');
                setTimeout(function() {
                    $this.css('width', percentage + '%');
                }, 500);
            });
        }
        
        // Initialize animations when page loads
        $(document).ready(function() {
            animateProgressBars();
        });
    </script>
</body>
</html> 