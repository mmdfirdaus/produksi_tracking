</main>
    </div>

    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // ========================================
        // NAVBAR SCROLL TRANSPARENCY EFFECT
        // ========================================
        const navbar = document.querySelector('.navbar.fixed-top');
        if (navbar) {
            // Set transparent if at the top
            if (window.scrollY <= 50) {
                 navbar.classList.add('navbar-transparent');
                 navbar.classList.remove('navbar-dark');
                 navbar.classList.add('navbar-light');
            }
            window.addEventListener('scroll', function() {
                if (window.scrollY > 50) { 
                    navbar.classList.remove('navbar-transparent');
                    navbar.classList.remove('navbar-light');
                    navbar.classList.add('navbar-dark');
                } else { 
                    navbar.classList.add('navbar-transparent');
                    navbar.classList.remove('navbar-dark');
                    navbar.classList.add('navbar-light');
                }
            });
        }

        // ========================================
        // ENHANCED LOGOUT CONFIRMATION WITH LOADING ANIMATION
        // ========================================
        const logoutButton = document.getElementById('logout-link');
        if (logoutButton) {
            logoutButton.addEventListener('click', function (e) {
                e.preventDefault();
                
                // Detect if mobile for responsive styling
                const isMobile = window.innerWidth <= 768;
                
                // Close mobile menu first if open
                const navbarCollapse = document.querySelector('.navbar-collapse');
                if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                    const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    }
                }
                
                // Show confirmation dialog with dark blue theme
                setTimeout(() => {
                    Swal.fire({
                        title: 'Anda Yakin?',
                        text: "Anda akan keluar dari sesi ini.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#667eea',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Ya, Logout!',
                        cancelButtonText: 'Batal',
                        background: 'rgba(30, 60, 114, 0.95)',
                        color: '#fff',
                        width: isMobile ? '90%' : undefined,
                        backdrop: `
                            rgba(0,0,0,0.4)
                            left top
                            no-repeat
                        `,
                        customClass: {
                            popup: 'swal-dark-blue-popup',
                            confirmButton: 'swal-confirm-btn',
                            cancelButton: 'swal-cancel-btn'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Show loading animation with dark blue theme
                            Swal.fire({
                                title: 'Memproses logout...',
                                html: '<div style="margin-top: 20px;"><i class="bi bi-arrow-clockwise" style="font-size: 3rem; animation: spin 1s linear infinite;"></i></div>',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false,
                                background: 'rgba(30, 60, 114, 0.95)',
                                color: '#fff',
                                width: isMobile ? '90%' : undefined,
                                backdrop: `
                                    rgba(0,0,0,0.6)
                                    left top
                                    no-repeat
                                `,
                                didOpen: () => {
                                    // Add custom spin animation
                                    const style = document.createElement('style');
                                    style.innerHTML = `
                                        @keyframes spin {
                                            0% { transform: rotate(0deg); }
                                            100% { transform: rotate(360deg); }
                                        }
                                    `;
                                    document.head.appendChild(style);
                                }
                            });
                            
                            // Redirect after smooth delay (800ms)
                            setTimeout(() => {
                                window.location.href = '/produksi_tracking/auth/logout.php';
                            }, 800);
                        }
                    });
                }, 100);
            });
        }

    });
    </script>
    
    <!-- Additional Custom Styles for SweetAlert Dark Blue Theme -->
    <style>
        /* Custom styling for SweetAlert dark blue theme */
        .swal-dark-blue-popup {
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            border-radius: 16px !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.5) !important;
        }
        
        .swal-confirm-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 10px 24px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
        }
        
        .swal-confirm-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4) !important;
        }
        
        .swal-cancel-btn {
            border-radius: 8px !important;
            padding: 10px 24px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
        }
        
        .swal-cancel-btn:hover {
            transform: translateY(-2px) !important;
        }

        /* Loading spinner animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    
    </body>
</html>