<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

$page_title = 'Pengaturan Template Impor';
include '../../../templates/header_superadmin.php';

$template_dir = '../../../templates/excel/';
$template_file = 'template_impor_kustom.xlsx';
$template_path = $template_dir . $template_file;
$template_exists = file_exists($template_path);
?>

<style>
/* Modern Template Settings UI */
.modern-template-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.content-wrapper {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 1rem;
}

.page-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.page-header h1 {
    color: #2c3e50;
    font-weight: 700;
    margin: 0;
    font-size: 2.5rem;
    position: relative;
    z-index: 1;
}

.page-subtitle {
    color: #666;
    font-size: 1.1rem;
    margin: 0.5rem 0 0 0;
    position: relative;
    z-index: 1;
}

.alert-modern {
    border: none;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success-modern {
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.9), rgba(129, 199, 132, 0.9));
    color: white;
}

.alert-danger-modern {
    background: linear-gradient(135deg, rgba(244, 67, 54, 0.9), rgba(229, 115, 115, 0.9));
    color: white;
}

.main-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 2rem;
}

.card-header-modern {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.card-header-modern::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: shine 3s ease-in-out infinite;
}

@keyframes shine {
    0% { left: -100%; }
    100% { left: 100%; }
}

.card-header-modern h2 {
    margin: 0;
    font-weight: 600;
    font-size: 1.5rem;
    z-index: 1;
    position: relative;
}

.upload-section {
    padding: 2.5rem;
    background: rgba(255, 255, 255, 0.95);
}

.upload-description {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: 0 10px 30px rgba(240, 147, 251, 0.3);
}

.upload-form {
    background: rgba(248, 249, 250, 0.8);
    border-radius: 20px;
    padding: 2rem;
    border: 2px dashed #667eea;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.upload-form:hover {
    border-color: #764ba2;
    background: rgba(102, 126, 234, 0.05);
    transform: translateY(-2px);
}

.file-input-wrapper {
    position: relative;
    margin-bottom: 1.5rem;
}

.file-input-modern {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    background: white;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.file-input-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 20px rgba(102, 126, 234, 0.2);
    outline: none;
}

.upload-btn {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.upload-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.divider {
    height: 2px;
    background: linear-gradient(90deg, transparent, #667eea, transparent);
    margin: 2rem 0;
    border-radius: 1px;
}

.template-status-section {
    padding: 2.5rem;
    background: rgba(255, 255, 255, 0.95);
}

.section-title {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.template-active {
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(129, 199, 132, 0.1));
    border: 2px solid rgba(76, 175, 80, 0.3);
    border-radius: 20px;
    padding: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    animation: fadeIn 0.6s ease-out;
}

.template-info {
    flex: 1;
    min-width: 250px;
}

.template-info h4 {
    color: #2e7d32;
    margin: 0 0 0.5rem 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.template-info p {
    color: #666;
    margin: 0;
    font-size: 0.9rem;
}

.template-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.download-btn {
    background: linear-gradient(45deg, #4CAF50, #45a049);
    color: white;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
}

.download-btn:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
    text-decoration: none;
}

.delete-btn {
    background: linear-gradient(45ff, #f44336, #e53935);
    color: white;
    box-shadow: 0 4px 15px rgba(244, 67, 54, 0.4);
}

.delete-btn:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(244, 67, 54, 0.6);
    text-decoration: none;
}

.template-inactive {
    background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(30, 136, 229, 0.1));
    border: 2px solid rgba(33, 150, 243, 0.3);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    animation: fadeIn 0.6s ease-out;
}

.template-inactive h4 {
    color: #1976d2;
    margin: 0 0 1rem 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.template-inactive p {
    color: #666;
    margin: 0;
    line-height: 1.6;
}

.icon-large {
    font-size: 1.2em;
}

.btn-close-modern {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.btn-close-modern:hover {
    opacity: 1;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .content-wrapper {
        padding: 0 0.5rem;
    }
    
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .upload-section, .template-status-section {
        padding: 1.5rem;
    }
    
    .template-active {
        flex-direction: column;
        text-align: center;
    }
    
    .template-actions {
        justify-content: center;
    }
}

/* Loading Animation */
.loading {
    opacity: 0;
    animation: slideUp 0.6s ease-out forwards;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="modern-template-container">
    <div class="content-wrapper">
        
        <!-- Page Header -->
        <div class="page-header loading">
            <h1>📊 Pengaturan Template Impor Excel</h1>
            <p class="page-subtitle">Kelola template Excel untuk impor data di seluruh sistem</p>
        </div>

        <!-- Status Alert -->
        <?php if (isset($_GET['status'])): ?>
        <div class="alert-modern <?php echo $_GET['status'] == 'success' ? 'alert-success-modern' : 'alert-danger-modern'; ?> loading" style="animation-delay: 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="bi bi-<?php echo $_GET['status'] == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?>"></i>
                    <span><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></span>
                </div>
                <button type="button" class="btn-close-modern" data-bs-dismiss="alert" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Card -->
        <div class="main-card loading" style="animation-delay: 0.4s;">
            <!-- Card Header -->
            <div class="card-header-modern">
                <h2>🔧 Upload Template Baru</h2>
            </div>
            
            <!-- Upload Section -->
            <div class="upload-section">
                <div class="upload-description">
                    <h4 style="margin: 0 0 0.5rem 0;">📋 Informasi Template</h4>
                    <p style="margin: 0;">
                        Unggah file Excel (.xlsx) baru untuk dijadikan template impor di seluruh sistem. 
                        Jika tidak ada template yang diunggah, sistem akan menggunakan template standar yang dibuat otomatis.
                    </p>
                </div>
                
                <div class="upload-form">
                    <form action="proses_pengaturan_impor.php" method="post" enctype="multipart/form-data">
                        <div class="file-input-wrapper">
                            <input type="file" 
                                   class="file-input-modern" 
                                   name="template_excel" 
                                   accept=".xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" 
                                   required>
                        </div>
                        <button class="upload-btn" type="submit" name="upload_template">
                            <i class="bi bi-cloud-upload-fill icon-large"></i>
                            <span>Unggah & Ganti Template</span>
                        </button>
                    </form>
                </div>
                
                <div class="divider"></div>
                
                <!-- Template Status Section -->
                <div class="template-status-section">
                    <h3 class="section-title">
                        <i class="bi bi-gear-fill"></i>
                        <span>Template Aktif Saat Ini</span>
                    </h3>
                    
                    <?php if ($template_exists): ?>
                        <div class="template-active">
                            <div class="template-info">
                                <h4>
                                    <i class="bi bi-check-circle-fill icon-large"></i>
                                    Template Kustom Aktif
                                </h4>
                                <p><strong>File:</strong> <?php echo $template_file; ?></p>
                                <p><strong>Terakhir diubah:</strong> <?php echo date("d F Y H:i:s", filemtime($template_path)); ?></p>
                            </div>
                            <div class="template-actions">
                                <a href="<?php echo $template_path; ?>" class="action-btn download-btn" download>
                                    <i class="bi bi-download"></i>
                                    <span>Download</span>
                                </a>
                                <a href="proses_pengaturan_impor.php?hapus_template=1" 
                                   class="action-btn delete-btn" 
                                   onclick="return confirm('Anda yakin ingin menghapus template kustom? Sistem akan kembali menggunakan template standar.');">
                                    <i class="bi bi-trash3-fill"></i>
                                    <span>Hapus</span>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="template-inactive">
                            <h4>
                                <i class="bi bi-info-circle-fill icon-large"></i>
                                Menggunakan Template Standar
                            </h4>
                            <p>
                                Tidak ada template kustom yang diunggah. Sistem akan membuat template standar 
                                secara otomatis saat tombol "Download Template" di halaman material diklik.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Loading animation
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.loading');
    elements.forEach((el, index) => {
        el.style.animationDelay = (index * 0.2) + 's';
    });
    
    // File input enhancement
    const fileInput = document.querySelector('.file-input-modern');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Pilih file...';
            const uploadBtn = document.querySelector('.upload-btn span');
            if (this.files[0]) {
                uploadBtn.textContent = `Upload "${fileName}"`;
            }
        });
    }
    
    // Enhanced hover effects
    const uploadForm = document.querySelector('.upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#764ba2';
            this.style.background = 'rgba(102, 126, 234, 0.1)';
        });
        
        uploadForm.addEventListener('dragleave', function() {
            this.style.borderColor = '#667eea';
            this.style.background = 'rgba(248, 249, 250, 0.8)';
        });
    }
});

// Smooth scroll and animations
window.addEventListener('scroll', function() {
    const elements = document.querySelectorAll('.main-card');
    elements.forEach(el => {
        const rect = el.getBoundingClientRect();
        if (rect.top < window.innerHeight && rect.bottom > 0) {
            el.style.transform = 'translateY(0)';
            el.style.opacity = '1';
        }
    });
});
</script>

<?php include '../../../templates/footer.php'; ?>