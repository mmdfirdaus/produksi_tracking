<?php
session_start();
require_once '../../system/database_connection.php';
require_once '../../templates/header_superadmin.php';

// Pastikan hanya superadmin yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../auth/login.php");
    exit();
}

// Ambil semua admin
$queryAdmins = $pdo->query("SELECT id, full_name FROM users WHERE role = 'admin' ORDER BY full_name");
$admins = $queryAdmins->fetchAll(PDO::FETCH_ASSOC);

// Mengambil data dari tabel 'master_alur'
$queryAlur = $pdo->query("SELECT id_alur, nama_alur FROM master_alur ORDER BY urutan");
$allAlur = $queryAlur->fetchAll(PDO::FETCH_ASSOC);

// Ambil data akses yang sudah ada
$queryAccess = $pdo->query("SELECT id_user, id_tahapan FROM admin_tahapan_access");
$currentAccess = $queryAccess->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
?>

<style>
    /* Modern UI Styling - Konsisten dengan manage_users.php */
    :root {
        --primary-color: #6366f1;
        --primary-dark: #4f46e5;
        --success-color: #10b981;
        --danger-color: #ef4444;
        --warning-color: #f59e0b;
        --info-color: #3b82f6;
        --bg-light: #f8fafc;
        --text-dark: #1f2937;
        --text-muted: #6b7280;
        --border-color: #e5e7eb;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --border-radius: 0.75rem;
    }

    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .main-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: var(--border-radius);
        margin: 2rem auto;
        max-width: 1400px;
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .page-header {
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -1rem;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
        border-radius: 2px;
    }

    .page-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .page-subtitle {
        color: var(--text-muted);
        font-size: 1.1rem;
        font-weight: 400;
    }

    /* Statistics Cards */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
    }

    .stat-card.success::before {
        background: linear-gradient(180deg, var(--success-color), #059669);
    }

    .stat-card.info::before {
        background: linear-gradient(180deg, var(--info-color), #2563eb);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    .stat-card.success .stat-value {
        color: var(--success-color);
    }

    .stat-card.info .stat-value {
        color: var(--info-color);
    }

    /* Search Container */
    .search-container {
        position: relative;
        margin-bottom: 2rem;
    }

    .search-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 1.1rem;
    }

    /* Admin Cards */
    .admin-card {
        background: white;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
        overflow: hidden;
        border: 1px solid var(--border-color);
    }

    .admin-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    .admin-card-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
        border-bottom: 2px solid var(--border-color);
    }

    .admin-card-header:hover {
        background: linear-gradient(135deg, #e5e7eb 0%, #f8fafc 100%);
    }

    .admin-card-header.active {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
    }

    .admin-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }

    .admin-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.2rem;
        box-shadow: var(--shadow-md);
    }

    .admin-card-header.active .admin-avatar {
        background: white;
        color: var(--primary-color);
    }

    .admin-name {
        font-weight: 700;
        font-size: 1.1rem;
        margin: 0;
    }

    .admin-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .access-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 9999px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .admin-card-header.active .access-badge {
        background: white;
        color: var(--success-color);
        border-color: white;
    }

    .collapse-icon {
        font-size: 1.2rem;
        transition: transform 0.3s ease;
    }

    .admin-card-header.active .collapse-icon {
        transform: rotate(180deg);
    }

    .admin-card-body {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease;
    }

    .admin-card-body.show {
        max-height: 2000px;
    }

    .admin-card-content {
        padding: 1.5rem;
    }

    /* Access Controls */
    .access-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--border-color);
    }

    .control-label {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .select-all-btn {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info-color);
        border: 1px solid rgba(59, 130, 246, 0.2);
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .select-all-btn:hover {
        background: var(--info-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    /* Alur Grid */
    .alur-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .alur-item {
        background: var(--bg-light);
        padding: 1rem;
        border-radius: 0.5rem;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .alur-item:hover {
        border-color: var(--primary-color);
        box-shadow: var(--shadow-sm);
    }

    .alur-item.checked {
        background: rgba(99, 102, 241, 0.05);
        border-color: var(--primary-color);
    }

    /* Toggle Switch */
    .toggle-wrapper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .toggle-label {
        font-weight: 600;
        color: var(--text-dark);
        flex: 1;
        font-size: 0.9rem;
    }

    .toggle-switch {
        position: relative;
        width: 52px;
        height: 28px;
        flex-shrink: 0;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: 0.4s;
        border-radius: 28px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: 0.4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .toggle-switch input:checked + .toggle-slider {
        background: linear-gradient(135deg, var(--success-color), #059669);
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(24px);
    }

    .toggle-slider:hover {
        box-shadow: 0 0 8px rgba(99, 102, 241, 0.3);
    }

    /* Save Button */
    .save-button-container {
        position: sticky;
        bottom: 20px;
        margin-top: 2rem;
        text-align: center;
        z-index: 100;
    }

    .save-button {
        padding: 1rem 3rem;
        background: linear-gradient(135deg, var(--success-color), #059669);
        color: white;
        border: none;
        border-radius: var(--border-radius);
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: var(--shadow-lg);
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
    }

    .save-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.75rem 2rem rgba(16, 185, 129, 0.4);
        background: linear-gradient(135deg, #059669, #047857);
    }

    /* Alert Styling */
    .alert {
        border: none;
        border-radius: var(--border-radius);
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        font-weight: 500;
        box-shadow: var(--shadow-md);
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
        border-left: 4px solid var(--success-color);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
    }

    .empty-state i {
        font-size: 5rem;
        color: var(--border-color);
        margin-bottom: 1.5rem;
    }

    .empty-state h3 {
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-size: 1.5rem;
    }

    .empty-state p {
        color: var(--text-muted);
    }

    .no-results {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-muted);
        display: none;
    }

    .no-results i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--border-color);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .main-container {
            margin: 1rem;
            padding: 1rem;
        }

        .page-title {
            font-size: 2rem;
        }

        .stats-container {
            grid-template-columns: 1fr;
        }

        .alur-grid {
            grid-template-columns: 1fr;
        }

        .admin-meta {
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .save-button {
            width: 100%;
        }
    }
</style>

<div class="main-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">Manajemen Hak Akses Admin</h1>
        <p class="page-subtitle">Kelola dan atur hak akses administrator untuk setiap alur produksi</p>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($admins)): ?>
        <div class="empty-state">
            <i class="fas fa-user-times"></i>
            <h3>Tidak Ada Admin Ditemukan</h3>
            <p>Belum ada pengguna dengan peran 'admin' yang terdaftar dalam sistem.</p>
        </div>
    <?php else: ?>
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Admin</div>
                <div class="stat-value"><?php echo count($admins); ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Total Alur</div>
                <div class="stat-value"><?php echo count($allAlur); ?></div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">Total Akses</div>
                <div class="stat-value" id="totalAccessCount">
                    <?php 
                        $totalAccess = 0;
                        foreach ($currentAccess as $userAccess) {
                            $totalAccess += count($userAccess);
                        }
                        echo $totalAccess;
                    ?>
                </div>
            </div>
        </div>

        <!-- Search Box -->
        <div class="search-container">
            <input type="text" class="search-input" id="searchAdmin" placeholder="Cari admin berdasarkan nama..." autocomplete="off">
            <i class="fas fa-search search-icon"></i>
        </div>

        <!-- Form -->
        <form action="proses_manage_access.php" method="POST" id="accessForm">
            <div id="adminContainer">
                <?php foreach ($admins as $index => $admin): ?>
                    <div class="admin-card" data-admin-name="<?php echo strtolower($admin['full_name']); ?>">
                        <div class="admin-card-header" onclick="toggleCard(this)">
                            <div class="admin-info">
                                <div class="admin-avatar">
                                    <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                                </div>
                                <h3 class="admin-name"><?php echo htmlspecialchars($admin['full_name']); ?></h3>
                            </div>
                            <div class="admin-meta">
                                <span class="access-badge">
                                    <i class="fas fa-key"></i>
                                    <span class="access-count" data-admin-id="<?php echo $admin['id']; ?>">
                                        <?php 
                                            $accessCount = 0;
                                            if (isset($currentAccess[$admin['id']])) {
                                                $accessCount = count($currentAccess[$admin['id']]);
                                            }
                                            echo $accessCount;
                                        ?>
                                    </span> Akses
                                </span>
                                <i class="fas fa-chevron-down collapse-icon"></i>
                            </div>
                        </div>

                        <div class="admin-card-body">
                            <div class="admin-card-content">
                                <input type="hidden" name="admins[]" value="<?php echo $admin['id']; ?>">
                                
                                <div class="access-controls">
                                    <span class="control-label">
                                        <i class="fas fa-list-check"></i>Pilih Alur Produksi
                                    </span>
                                    <button type="button" class="select-all-btn" onclick="toggleSelectAll(<?php echo $admin['id']; ?>)">
                                        <i class="fas fa-check-double"></i>Pilih Semua
                                    </button>
                                </div>

                                <div class="alur-grid">
                                    <?php foreach ($allAlur as $alur): ?>
                                        <?php
                                            $adminId = $admin['id'];
                                            $hasAccess = false;
                                            if (isset($currentAccess[$adminId])) {
                                                foreach ($currentAccess[$adminId] as $access) {
                                                    if ($access['id_tahapan'] == $alur['id_alur']) {
                                                        $hasAccess = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            $checked = $hasAccess ? 'checked' : '';
                                        ?>
                                        <div class="alur-item <?php echo $checked ? 'checked' : ''; ?>" data-admin-id="<?php echo $adminId; ?>">
                                            <div class="toggle-wrapper">
                                                <label class="toggle-label" for="alur_<?php echo $adminId; ?>_<?php echo $alur['id_alur']; ?>">
                                                    <?php echo htmlspecialchars($alur['nama_alur']); ?>
                                                </label>
                                                <label class="toggle-switch">
                                                    <input 
                                                        type="checkbox" 
                                                        name="access[<?php echo $adminId; ?>][]" 
                                                        value="<?php echo $alur['id_alur']; ?>" 
                                                        id="alur_<?php echo $adminId; ?>_<?php echo $alur['id_alur']; ?>" 
                                                        <?php echo $checked; ?>
                                                        onchange="updateAccessCount(<?php echo $adminId; ?>)"
                                                    >
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="save-button-container">
                <button type="submit" class="save-button">
                    <i class="fas fa-save"></i>
                    Simpan Semua Perubahan
                </button>
            </div>
        </form>

        <div id="noResults" class="no-results">
            <i class="fas fa-search"></i>
            <h4>Tidak ada admin yang ditemukan</h4>
            <p>Coba gunakan kata kunci pencarian yang berbeda</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Toggle Card Collapse
function toggleCard(header) {
    const body = header.nextElementSibling;
    const card = header.parentElement;
    
    // Close all other cards
    document.querySelectorAll('.admin-card-header').forEach(h => {
        if (h !== header) {
            h.classList.remove('active');
            h.nextElementSibling.classList.remove('show');
        }
    });
    
    // Toggle current card
    header.classList.toggle('active');
    body.classList.toggle('show');
}

// Search Functionality
document.getElementById('searchAdmin').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const adminCards = document.querySelectorAll('.admin-card');
    let visibleCount = 0;
    
    adminCards.forEach(card => {
        const adminName = card.getAttribute('data-admin-name');
        if (adminName.includes(searchTerm)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    document.getElementById('noResults').style.display = visibleCount === 0 ? 'block' : 'none';
});

// Toggle Select All
function toggleSelectAll(adminId) {
    const checkboxes = document.querySelectorAll(`.alur-item[data-admin-id="${adminId}"] input[type="checkbox"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
        const item = checkbox.closest('.alur-item');
        if (checkbox.checked) {
            item.classList.add('checked');
        } else {
            item.classList.remove('checked');
        }
    });
    
    updateAccessCount(adminId);
}

// Update Access Count
function updateAccessCount(adminId) {
    const checkboxes = document.querySelectorAll(`input[name="access[${adminId}][]"]:checked`);
    const count = checkboxes.length;
    const badge = document.querySelector(`.access-count[data-admin-id="${adminId}"]`);
    
    if (badge) {
        badge.textContent = count;
        
        // Animate the number change
        badge.style.transform = 'scale(1.3)';
        setTimeout(() => {
            badge.style.transform = 'scale(1)';
        }, 200);
    }
    
    // Update alur-item styling
    checkboxes.forEach(cb => {
        cb.closest('.alur-item').classList.add('checked');
    });
    
    document.querySelectorAll(`input[name="access[${adminId}][]"]:not(:checked)`).forEach(cb => {
        cb.closest('.alur-item').classList.remove('checked');
    });
    
    // Update total access count
    updateTotalAccessCount();
}

// Update Total Access Count
function updateTotalAccessCount() {
    const allCheckboxes = document.querySelectorAll('input[type="checkbox"][name^="access"]:checked');
    document.getElementById('totalAccessCount').textContent = allCheckboxes.length;
}

// Open first card by default
document.addEventListener('DOMContentLoaded', function() {
    const firstCard = document.querySelector('.admin-card-header');
    if (firstCard) {
        firstCard.click();
    }
    
    // Add transition to badges
    document.querySelectorAll('.access-count').forEach(badge => {
        badge.style.transition = 'transform 0.2s ease';
    });
});

// Form submission confirmation
document.getElementById('accessForm').addEventListener('submit', function(e) {
    const totalChecked = document.querySelectorAll('input[type="checkbox"]:checked').length;
    if (totalChecked === 0) {
        if (!confirm('Anda belum memberikan akses apapun. Lanjutkan?')) {
            e.preventDefault();
        }
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>