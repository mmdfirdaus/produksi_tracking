<?php
// Selalu panggil header di awal
require_once '../../templates/header_superadmin.php';
// Panggil koneksi database
require_once '../../system/database_connection.php';

// Pastikan session dimulai untuk mendapatkan ID pengguna yang sedang login
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ambil ID superadmin yang sedang login
$current_superadmin_id = $_SESSION['user_id'] ?? 0;
// Ambil peran superadmin yang sedang login
$current_superadmin_role = $_SESSION['role'] ?? '';

$users = []; // Inisialisasi sebagai array kosong untuk mencegah error jika query gagal
$db_error = null; // Variabel untuk menampung pesan error

try {
    // Ambil data untuk statistics cards
    $stmt_stats = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $role_stats = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total_users = array_sum($role_stats);
    $total_superadmins = $role_stats['superadmin'] ?? 0;
    $total_admins = $role_stats['admin'] ?? 0;
    // PERBAIKAN: Mengganti 'operator' menjadi 'user' agar sesuai dengan database dan kode lama
    $total_users_role = $role_stats['user'] ?? 0; 
    
    // Ambil data users
    $sql = "SELECT id, username, full_name, role FROM users ORDER BY 
            CASE 
                WHEN role = 'superadmin' THEN 1 
                WHEN role = 'admin' THEN 2 
                ELSE 3 
            END, 
            username ASC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = "Gagal mengambil data dari database: " . $e->getMessage();
}
?>

<style>
    /* Modern UI with Optimized Performance */
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --primary-light: #818cf8;
        --secondary: #8b5cf6;
        --secondary-dark: #7c3aed;
        --success: #10b981;
        --success-light: #34d399;
        --danger: #ef4444;
        --danger-light: #f87171;
        --warning: #f59e0b;
        --warning-light: #fbbf24;
        --info: #3b82f6;
        --dark: #1e293b;
        --gray: #64748b;
        --gray-light: #e2e8f0;
        --white: #ffffff;
        --bg-body: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background: var(--bg-body);
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    /* Main Container */
    .main-container {
        max-width: 1400px;
        margin: 2rem auto;
        padding: 0 1rem;
        animation: fadeInUp 0.6s ease;
    }

    /* Page Header with Glass Effect */
    .page-header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .header-content {
        text-align: center;
    }

    .page-title {
        font-size: 2.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }

    .page-subtitle {
        color: var(--gray);
        font-size: 1.1rem;
        font-weight: 400;
    }

    /* Statistics Cards */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeInUp 0.8s ease;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.3);
        transform: translateY(0);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .stat-card:hover::before {
        opacity: 1;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        color: white;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }

    .stat-card.total .stat-icon { background: linear-gradient(135deg, var(--info), #2563eb); }
    .stat-card.superadmins .stat-icon { background: linear-gradient(135deg, var(--danger), #dc2626); }
    .stat-card.admins .stat-icon { background: linear-gradient(135deg, var(--warning), #ea580c); }
    /* PERBAIKAN: Mengganti 'operators' menjadi 'users' */
    .stat-card.users .stat-icon { background: linear-gradient(135deg, var(--success), #059669); }

    .stat-content h3 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        color: var(--gray);
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Actions Section */
    .actions-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.3);
        animation: fadeInUp 1s ease;
    }

    .actions-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    /* Search Box Modern */
    .search-box {
        position: relative;
        flex: 1;
        min-width: 280px;
        max-width: 500px;
    }

    .search-input {
        width: 100%;
        padding: 0.875rem 1rem 0.875rem 3rem;
        border: 2px solid var(--gray-light);
        border-radius: 12px;
        font-size: 0.95rem;
        background: white;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        transform: translateY(-1px);
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray);
        transition: color 0.3s ease;
    }

    .search-input:focus ~ .search-icon {
        color: var(--primary);
    }

    /* Modern Button */
    .btn-add-user {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        padding: 0.875rem 1.75rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.3);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .btn-add-user::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--primary-dark), var(--secondary));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .btn-add-user:hover::before {
        opacity: 1;
    }

    .btn-add-user span {
        position: relative;
        z-index: 1;
    }

    .btn-add-user:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);
    }

    .btn-add-user:active {
        transform: translateY(0);
    }

    /* Table Container */
    .table-container {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.3);
        animation: fadeInUp 1.2s ease;
        padding: 1.5rem;
    }

    /* DataTables Custom Styling */
    .dataTables_wrapper {
        padding: 0;
    }

    .dataTables_length, .dataTables_filter {
        margin-bottom: 1.5rem;
    }

    .dataTables_length label, .dataTables_filter label {
        font-weight: 500;
        color: var(--gray);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .dataTables_length select,
    .dataTables_filter input {
        border: 2px solid var(--gray-light);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        margin-left: 0.5rem;
        transition: all 0.3s ease;
    }

    .dataTables_filter input {
        min-width: 250px;
    }

    .dataTables_length select:focus,
    .dataTables_filter input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    /* Modern Table */
    .modern-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.95rem;
    }

    .modern-table thead th {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        padding: 1.25rem 1rem;
        font-weight: 600;
        text-align: left;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.05em;
        border: none;
        cursor: pointer;
        position: relative;
    }

    .modern-table thead th:first-child {
        border-radius: 12px 0 0 0;
    }

    .modern-table thead th:last-child {
        border-radius: 0 12px 0 0;
    }

    .modern-table tbody tr {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .modern-table tbody tr:hover {
        background: rgba(99, 102, 241, 0.05);
        transform: scale(1.01);
    }

    .modern-table tbody td {
        padding: 1rem;
        border-bottom: 1px solid var(--gray-light);
        vertical-align: middle;
        color: var(--dark);
    }

    .modern-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* User Info Cell */
    .user-info {
        display: flex;
        align-items: center;
        gap: 0.875rem;
    }

    .user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1rem;
        text-transform: uppercase;
        flex-shrink: 0;
        box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
    }

    .user-details {
        flex-grow: 1;
    }

    .user-name {
        font-weight: 600;
        color: var(--dark);
        font-size: 0.95rem;
        margin-bottom: 0.125rem;
        display: block;
    }

    .user-username {
        color: var(--gray);
        font-size: 0.85rem;
        display: block;
    }

    /* Modern Role Badges */
    .role-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .role-badge i {
        font-size: 0.875rem;
    }

    .role-badge.superadmin {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        color: var(--danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .role-badge.superadmin:hover {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.1));
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    }

    .role-badge.admin {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
        color: var(--warning);
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .role-badge.admin:hover {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.1));
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2);
    }

    /* PERBAIKAN: Mengganti 'operator' menjadi 'user' */
    .role-badge.user {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        color: var(--success);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    /* PERBAIKAN: Mengganti 'operator' menjadi 'user' */
    .role-badge.user:hover {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.1));
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .btn-action {
        padding: 0.625rem 1rem;
        border: none;
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        position: relative;
        overflow: hidden;
    }

    .btn-edit {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .btn-edit:hover {
        background: var(--warning);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.3);
    }

    .btn-delete {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .btn-delete:hover {
        background: var(--danger);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.3);
    }

    /* Loading Skeleton */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .skeleton-row {
        height: 60px;
        margin: 10px 0;
        border-radius: 8px;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-icon {
        font-size: 4rem;
        color: var(--gray-light);
        margin-bottom: 1rem;
    }

    .empty-title {
        font-size: 1.5rem;
        color: var(--dark);
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .empty-text {
        color: var(--gray);
        font-size: 1rem;
    }

    /* Modal Styling */
    .modal-content {
        border-radius: 20px;
        border: none;
        
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        padding: 1.5rem;
        border: none;
    }

    .modal-title {
        font-weight: 600;
        font-size: 1.25rem;
    }

    .modal-body {
        padding: 2rem;
        overflow-y: auto;   /* <-- TAMBAHKAN INI */
  max-height: 70vh;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
        display: block;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-light);
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }
    
    /* PERBAIKAN: Menambahkan style untuk form control yang disabled (dari kode lama) */
    .form-control:disabled {
        background-color: #e9ecef;
        opacity: 1;
        cursor: not-allowed;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .form-text {
        font-size: 0.85rem;
        color: var(--gray);
        margin-top: 0.25rem;
    }

    /* DataTables Pagination */
    .dataTables_paginate {
        margin-top: 1.5rem;
        display: flex;
        justify-content: center;
    }

    .paginate_button {
        padding: 0.5rem 1rem;
        margin: 0 0.25rem;
        border-radius: 8px;
        background: white;
        color: var(--gray);
        border: 1px solid var(--gray-light);
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .paginate_button:hover:not(.disabled) {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    .paginate_button.current {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border-color: var(--primary);
    }

    .paginate_button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Mobile Card Layout */
    .mobile-cards {
        display: none;
    }

    .user-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--gray-light);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .user-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .card-avatar {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.25rem;
        text-transform: uppercase;
    }

    .card-info {
        flex-grow: 1;
    }

    .card-name {
        font-weight: 600;
        color: var(--dark);
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
    }

    .card-username {
        color: var(--gray);
        font-size: 0.9rem;
    }

    .card-badge {
        align-self: flex-start;
    }

    .card-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--gray-light);
    }

    .card-actions .btn-action {
        flex: 1;
        justify-content: center;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .main-container {
            margin: 1rem auto;
        }

        .page-header {
            padding: 1.5rem;
        }

        .page-title {
            font-size: 2rem;
        }

        .stats-container {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .actions-wrapper {
            flex-direction: column;
        }

        .search-box {
            width: 100%;
            max-width: 100%;
        }

        .btn-add-user {
            width: 100%;
            justify-content: center;
        }

        /* Hide desktop table, show mobile cards */
        .table-desktop {
            display: none !important;
        }

        .mobile-cards {
            display: block;
        }

        /* Hide DataTables controls on mobile */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            display: none !important;
        }
    }

    @media (max-width: 480px) {
        .stats-container {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .page-title {
            font-size: 1.75rem;
        }

        .stat-card {
            padding: 1.25rem;
        }
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
        100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
    }

    /* Loading State */
    .is-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.6;
    }

    .is-loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 40px;
        height: 40px;
        margin: -20px 0 0 -20px;
        border: 4px solid var(--primary);
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 0.8s linear infinite;
    }
    
    /* PERBAIKAN: Menambahkan animasi spin dari kode lama (untuk tombol) */
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
        margin-right: 5px;
        vertical-align: middle;
    }

    /* Toast Notifications */
    .toast {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: white;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 9999;
    }

    .toast.success {
        border-left: 4px solid var(--success);
    }

    .toast.error {
        border-left: 4px solid var(--danger);
    }

    .toast.warning {
        border-left: 4px solid var(--warning);
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Floating Action Button for Mobile */
    @media (max-width: 768px) {
        .fab-add {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: auto;
            padding: 0 1.25rem;
            height: 56px;
            border-radius: 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            z-index: 999;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* Style untuk teks di dalam FAB */
        .fab-add span {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .fab-add:hover {
            transform: scale(1.1);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.5);
        }

        .fab-add i {
            font-size: 1.5rem;
        }

        .btn-add-user {
            display: none;
        }
    }

    /* Optimized Transitions */
    /* Optimized Transitions */
    .stat-card, 
    .btn-add-user, 
    .btn-action,
    .user-card,
    .modern-table tbody tr {
        -webkit-transform: translateZ(0);
        -webkit-backface-visibility: hidden;
        -webkit-perspective: 1000px;
    }

    /* Reduce motion for accessibility */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>

<div class="main-container">
        <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">Manajemen Pengguna</h1>
            <p class="page-subtitle">Kelola semua akun pengguna dalam sistem</p>
        </div>
    </div>

        <div class="stats-container">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_users; ?></h3>
                <div class="stat-label">Total Pengguna</div>
            </div>
        </div>

        <div class="stat-card superadmins">
            <div class="stat-icon">
                <i class="fas fa-crown"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_superadmins; ?></h3>
                <div class="stat-label">Superadmin</div>
            </div>
        </div>

        <div class="stat-card admins">
            <div class="stat-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_admins; ?></h3>
                <div class="stat-label">Admin</div>
            </div>
        </div>

                <div class="stat-card users">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_users_role; ?></h3>
                <div class="stat-label">User</div>
            </div>
        </div>
    </div>

        <div class="actions-container">
        <div class="actions-wrapper">
            <div class="search-box">
                <input type="text" class="search-input" id="custom-search" placeholder="Cari pengguna...">
                <i class="fas fa-search search-icon"></i>
            </div>
            <button class="btn-add-user" id="add-user-btn">
                <i class="fas fa-plus"></i>
                <span>Tambah Pengguna</span>
            </button>
        </div>
    </div>

        <div class="table-container">
                <div class="table-desktop">
            <table id="usersTable" class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pengguna</th>
                        <th>Peran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <?php 
                            $initials = strtoupper(substr($user['full_name'], 0, 2));
                            $roleClass = strtolower($user['role']);
                            $roleIcon = '';
                            $roleSortValue = 3; // Default untuk 'user'
                            switch($user['role']) {
                                case 'superadmin':
                                    $roleIcon = 'fa-crown';
                                    $roleSortValue = 1;
                                    break;
                                case 'admin':
                                    $roleIcon = 'fa-user-shield';
                                    $roleSortValue = 2;
                                    break;
                                // PERBAIKAN: Default adalah 'user', bukan 'operator'
                                default: // case 'user'
                                    $roleIcon = 'fa-user';
                                    $roleSortValue = 3;
                            }
                        ?>
                        <tr data-user='<?php echo json_encode($user); ?>'>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar"><?php echo $initials; ?></div>
                                    <div class="user-details">
                                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                        <span class="user-username">@<?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-sort="<?php echo $roleSortValue; ?>">
                                <span class="role-badge <?php echo $roleClass; ?>">
                                    <i class="fas <?php echo $roleIcon; ?>"></i>
                                    <?php echo strtoupper($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </button>
                                    <?php if ($current_superadmin_id != $user['id']): ?>
                                    <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                        Hapus
                                    </button>
                                    <?php else: ?>
                                        <button class="btn-action btn-delete" disabled style="cursor: not-allowed; opacity: 0.6;">
                                        <i class="fas fa-lock"></i>
                                        Hapus
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

                <div class="mobile-cards">
            <?php foreach($users as $user): ?>
                <?php 
                    $initials = strtoupper(substr($user['full_name'], 0, 2));
                    $roleClass = strtolower($user['role']);
                    $roleIcon = '';
                    switch($user['role']) {
                        case 'superadmin':
                            $roleIcon = 'fa-crown';
                            break;
                        case 'admin':
                            $roleIcon = 'fa-user-shield';
                            break;
                        default: // case 'user'
                            $roleIcon = 'fa-user';
                    }
                ?>
                <div class="user-card" data-user='<?php echo json_encode($user); ?>'>
                    <div class="card-header">
                        <div class="card-avatar"><?php echo $initials; ?></div>
                        <div class="card-info">
                            <div class="card-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="card-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <span class="role-badge card-badge <?php echo $roleClass; ?>">
                            <i class="fas <?php echo $roleIcon; ?>"></i>
                            <?php echo strtoupper($user['role']); ?>
                        </span>
                    </div>
                    <div class="card-actions">
                        <button class="btn-action btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">
                            <i class="fas fa-edit"></i>
                            Edit
                        </button>
                        <?php if ($current_superadmin_id != $user['id']): ?>
                            <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                <i class="fas fa-trash"></i>
                                Hapus
                            </button>
                        <?php else: ?>
                                <button class="btn-action btn-delete" disabled style="cursor: not-allowed; opacity: 0.6;">
                                <i class="fas fa-lock"></i>
                                Hapus
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

        <button class="fab-add" id="fab-add-user">
        <i class="fas fa-plus"></i>
        <span>Tambah Pengguna</span>
    </button>
</div>

<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">
                    <i class="fas fa-user-plus"></i> Form Pengguna
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" style="background: none; border: none; color: white; font-size: 1.5rem;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="save-user-form">
                    <input type="hidden" id="user_id" name="user_id">
                    
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">
                            <i class="fas fa-id-card"></i> Nama Lengkap
                        </label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">
                            <i class="fas fa-user-tag"></i> Peran (Role)
                        </label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="special-code-group" style="display: none;">
                        <label for="special_code">
                            <i class="fas fa-key"></i> Kode Spesial
                        </label>
                        <input type="password" class="form-control" id="special_code" name="special_code">
                        <small class="form-text">Diperlukan untuk menetapkan peran Admin/Superadmin.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="form-text" id="password-help">Kosongkan jika tidak ingin mengubah password.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary" form="save-user-form">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Panggil footer
require_once '../../templates/footer.php';
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    let originalRole = '';
    let dataTable;
    const currentSuperadminId = <?php echo json_encode($current_superadmin_id); ?>;
    const currentSuperadminRole = <?php echo json_encode($current_superadmin_role); ?>;
    
    // Initialize DataTables with custom configuration
    dataTable = $('#usersTable').DataTable({
        "pageLength": 20,
        "lengthMenu": [[10, 20, 50, 100, -1], [10, 20, 50, 100, "Semua"]],
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data",
            "zeroRecords": "Data tidak ditemukan",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        },
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "responsive": true,
        "ordering": true,
        "autoWidth": false,
        "drawCallback": function() {
            // Add animation to newly rendered rows
            $('.modern-table tbody tr').each(function(i) {
                $(this).css('animation-delay', (i * 0.05) + 's');
            });
        }
    });

    // Custom search box integration
    $('#custom-search').on('keyup', function() {
        dataTable.search(this.value).draw();
    });

    // Mobile card search
    $('#custom-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.user-card').each(function() {
            const userData = $(this).data('user');
            const searchText = `${userData.username} ${userData.full_name} ${userData.role}`.toLowerCase();
            $(this).toggle(searchText.includes(searchTerm));
        });
    });

    // Show Toast Notification
    function showToast(message, type = 'success') {
        const toast = $(`
            <div class="toast ${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
        `);
        
        $('body').append(toast);
        
        setTimeout(() => {
            toast.css('animation', 'slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) reverse');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Add User Button Click
    $('#add-user-btn, #fab-add-user').click(function() {
        $('#userModalLabel').html('<i class="fas fa-user-plus"></i> Tambah Pengguna Baru');
        $('#save-user-form')[0].reset();
        $('#user_id').val('');
        
        // PERBAIKAN: Menambahkan logika dari kode lama untuk 'Add User'
        $('#username').prop('disabled', false);
        $('#full_name').prop('disabled', false);
        $('#role').prop('disabled', false).val('user'); // Set default 'user'
        $('#password').val('').attr('placeholder', 'Wajib diisi untuk pengguna baru').prop('required', true).prop('disabled', false);
        $('#password-help').text('Wajib diisi untuk pengguna baru.');
        
        originalRole = ''; // Reset originalRole
        $('#special-code-group').hide();
        $('#special_code').val('');
        
        $('#userModal').modal('show');
    });

    // Edit User Function
    window.editUser = function(userId) {
        // Add loading state
        const row = $(`tr[data-user*='"id":${userId}']`).addClass('is-loading');
        
        // PERBAIKAN: Mengganti 'process_users.php' ke 'api_user_handler.php'
        $.get('api_user_handler.php', { action: 'get_user', id: userId })
            .done(function(response) {
                row.removeClass('is-loading');
                
                if (response.status === 'success' && response.data) {
                    const user = response.data;
                    $('#userModalLabel').html('<i class="fas fa-user-edit"></i> Edit Pengguna');
                    $('#user_id').val(user.id);
                    $('#username').val(user.username);
                    $('#full_name').val(user.full_name);
                    $('#role').val(user.role);
                    
                    originalRole = user.role;
                    
                    // PERBAIKAN: Menggunakan logika disable dari kode lama
                    const isEditingAnotherSuperadmin = currentSuperadminRole === 'superadmin' && 
                        user.role === 'superadmin' && 
                        user.id != currentSuperadminId;
                    
                    if (isEditingAnotherSuperadmin) {
                        // Nonaktifkan field jika mengedit superadmin lain
                        $('#username').prop('disabled', true);
                        $('#full_name').prop('disabled', true);
                        $('#role').prop('disabled', true);
                        $('#password').val('').attr('placeholder', 'Tidak dapat mengubah password').prop('required', false).prop('disabled', true);
                        $('#password-help').text('Tidak dapat mengubah password superadmin lain.');
                    } else {
                        // Aktifkan field jika mengedit diri sendiri atau role lain
                        $('#username').prop('disabled', false);
                        $('#full_name').prop('disabled', false);
                        $('#role').prop('disabled', false);
                        $('#password').val('').attr('placeholder', 'Kosongkan jika tidak ingin mengubah').prop('required', false).prop('disabled', false);
                        $('#password-help').text('Kosongkan jika tidak ingin mengubah password.');
                    }

                        // Sembunyikan special code saat edit
                        $('#special-code-group').hide();
                        $('#special_code').val('');
                    
                    $('#userModal').modal('show');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            })
            .fail(function() {
                row.removeClass('is-loading');
                Swal.fire('Error', 'Gagal memuat data pengguna', 'error');
            });
    };

    // Delete User Function
    window.deleteUser = function(userId) {
        const userData = $(`tr[data-user*='"id":${userId}']`).data('user') || 
                         $(`.user-card[data-user*='"id":${userId}']`).data('user');
        
        if (!userData) return;
        
        if (userId == currentSuperadminId) {
            Swal.fire('Peringatan', 'Anda tidak dapat menghapus akun Anda sendiri!', 'warning');
            return;
        }

        const isSuperadmin = userData.role === 'superadmin';
        
        Swal.fire({
            title: 'Konfirmasi Hapus',
            html: `Apakah Anda yakin ingin menghapus pengguna <strong>${userData.full_name}</strong>?` +
                  (isSuperadmin ? '<br><br><input type="password" id="swal-input" class="swal2-input" placeholder="Masukkan kode spesial Superadmin">' : ''),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus!',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            preConfirm: () => {
                if (isSuperadmin) {
                    const specialCode = document.getElementById('swal-input').value;
                    if (!specialCode) {
                        Swal.showValidationMessage('Kode spesial diperlukan untuk menghapus Superadmin!');
                        return false;
                    }
                    return { specialCode };
                }
                return true;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const postData = { action: 'delete_user', user_id: userId };
                if (isSuperadmin && result.value.specialCode) {
                    postData.special_code = result.value.specialCode;
                }
                
                // Add loading state
                const element = $(`tr[data-user*='"id":${userId}'], .user-card[data-user*='"id":${userId}']`).addClass('is-loading');
                
                // PERBAIKAN: Mengganti 'process_users.php' ke 'api_user_handler.php'
                $.post('api_user_handler.php', postData)
                    .done(function(response) {
                        element.removeClass('is-loading');
                        
                        // PERBAIKAN: Mengganti logika dynamic remove dengan reload page (seperti kode lama)
                        if (response.status === 'success') {
                            showToast('Pengguna berhasil dihapus!', 'success');
                            // Reload halaman untuk sinkronisasi data (mengikuti logika kode lama)
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    })
                    .fail(function() {
                        element.removeClass('is-loading');
                        Swal.fire('Error', 'Gagal menghapus pengguna', 'error');
                    });
            }
        });
    };

    // Role Change Handler
    $('#role').change(function() {
        const selectedRole = $(this).val();
        const isEdit = $('#user_id').val() !== '';
        
        if ((selectedRole === 'admin' || selectedRole === 'superadmin') && 
            (!isEdit || selectedRole !== originalRole)) {
            $('#special-code-group').slideDown();
        } else {
            $('#special-code-group').slideUp();
        }
    });

    // PERBAIKAN: Mengganti submit handler dengan yang dari kode lama (yang ada konfirmasi)
    $('#save-user-form').submit(function(e) {
        e.preventDefault(); 
        
        const isEdit = $('#user_id').val() !== '';
        const title = isEdit ? 'Simpan Perubahan?' : 'Tambah Pengguna Baru?';

        Swal.fire({
            title: title,
            text: "Pastikan data yang Anda masukkan sudah benar sebelum menyimpan.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981', 
            cancelButtonColor: '#6b7280', 
            confirmButtonText: 'Ya, simpan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const $submitBtn = $('button[form="save-user-form"]');
                const originalText = $submitBtn.html();
                // Gunakan class loading dari CSS baru
                $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

                // Menggunakan serializeArray() dan push action (seperti kode lama)
                let formData = $(this).serializeArray();
                formData.push({name: "action", value: "save_user"});
                
                // Menggunakan $.ajax agar sama persis dengan kode lama
                $.ajax({
                    // PERBAIKAN: Arahkan ke 'api_user_handler.php'
                    url: 'api_user_handler.php', 
                    method: 'POST',
                    data: $.param(formData),
                    dataType: 'json',
                    success: function(response) {
                        $('#userModal').modal('hide');
                        if (response.status === 'success') {
                            // Gunakan toast dari UI baru, tapi logic reload dari UI lama
                            showToast(response.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500); // Reload setelah 1.5 detik
                        } else {
                            Swal.fire('Gagal!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Tidak dapat terhubung ke server.', 'error');
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            }
        });
    });

    // Fungsi updateStats tidak lagi diperlukan jika kita reload halaman
    // function updateStats() { ... }

    // Add smooth scroll for mobile
    if (window.innerWidth <= 768) {
        $('.user-card').on('touchstart', function() {
            $(this).addClass('touch-active');
        }).on('touchend', function() {
            $(this).removeClass('touch-active');
        });
    }

    // Handle window resize for responsive behavior
    let resizeTimer;
    $(window).resize(function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                $('.table-desktop').show();
                $('.mobile-cards').hide();
            } else {
                $('.table-desktop').hide();
                $('.mobile-cards').show();
            }
        }, 250);
    });

    // Initialize tooltips if Bootstrap tooltips are available
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Page visibility API for performance
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Pause animations when page is not visible
            $('.stat-card, .user-card').css('animation-play-state', 'paused');
        } else {
            // Resume animations when page is visible
            $('.stat-card, .user-card').css('animation-play-state', 'running');
        }
    });

    // Error handling for database errors
    <?php if($db_error): ?>
    Swal.fire({
        icon: 'error',
        title: 'Database Error',
        text: <?php echo json_encode($db_error); ?>,
        confirmButtonColor: '#ef4444'
    });
    <?php endif; ?>
});
</script>