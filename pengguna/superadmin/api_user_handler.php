<?php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek hak akses superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

require_once '../../system/database_connection.php';

// Definisikan kode spesial
define('SUPERADMIN_CODE', 'KodeSuperAdmin123');
define('ADMIN_CODE', 'KodeAdmin456');

$response = [];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- Ambil ID dan Peran pengguna yang sedang login ---
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['role'] ?? '';

try {
    switch ($action) {
        case 'get_user':
            $user_id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT id, username, full_name, role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $response = ['status' => 'success', 'data' => $user];
            break;

        case 'save_user':
            $user_id = $_POST['user_id'] ?? null;
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $password = $_POST['password'];
            $special_code = $_POST['special_code'] ?? '';
            
            if (empty($username) || empty($full_name)) {
                throw new Exception("Username dan Nama Lengkap wajib diisi.");
            }

            $is_edit = !empty($user_id);
            if (!$is_edit && empty($password)) {
                throw new Exception("Password wajib diisi untuk pengguna baru.");
            }

            $original_role = '';
            $is_editing_another_superadmin = false;

            if ($is_edit) {
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $original_role = $stmt->fetchColumn();

                // Logika untuk menentukan apakah sedang mengedit superadmin lain
                if ($current_user_role === 'superadmin' && $original_role === 'superadmin' && $user_id != $current_user_id) {
                    $is_editing_another_superadmin = true;
                }
            }
            
            // Validasi Kode Spesial
            $is_role_changed = $is_edit && $role !== $original_role;
            $requires_code = (!$is_edit && in_array($role, ['admin', 'superadmin'])) || ($is_role_changed && in_array($role, ['admin', 'superadmin']));
            
            if ($requires_code) {
                if ($role == 'superadmin' && $special_code !== SUPERADMIN_CODE) throw new Exception("Kode spesial Superadmin salah.");
                if ($role == 'admin' && $special_code !== ADMIN_CODE) throw new Exception("Kode spesial Admin salah.");
            }

            // =================================================================
            // --- NEW SECURITY CHECK: Mencegah Self-Demotion (Bunuh Diri Akun) ---
            // =================================================================
            if ($is_edit && $user_id == $current_user_id) {
                // Jika user mengedit dirinya sendiri, cek apakah role diganti
                if ($role !== 'superadmin') {
                    throw new Exception("Demi keamanan sistem, Anda tidak diizinkan menurunkan Role akun Anda sendiri.");
                }
            }
            // =================================================================

            // --- LOGIKA UTAMA PENYIMPANAN ---
            if ($is_edit) {
                // Kondisi jika yang diedit adalah superadmin lain
                if ($is_editing_another_superadmin) {
                    // Jika mengedit sesama superadmin, HANYA ROLE yang boleh diupdate
                    $sql = "UPDATE users SET role = ? WHERE id = ?";
                    $params = [$role, $user_id];
                    
                    // Keamanan tambahan: Tolak jika ada upaya mengubah password
                    if (!empty($password)) {
                        throw new Exception("Superadmin tidak dapat mengubah password superadmin lainnya.");
                    }
                } else {
                    // Logika update normal untuk user lain atau diri sendiri (tapi role diri sendiri sudah dijaga di atas)
                    if (!empty($password)) {
                        $sql = "UPDATE users SET username = ?, full_name = ?, role = ?, password = ? WHERE id = ?";
                        $params = [$username, $full_name, $role, password_hash($password, PASSWORD_DEFAULT), $user_id];
                    } else {
                        $sql = "UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?";
                        $params = [$username, $full_name, $role, $user_id];
                    }
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $response = ['status' => 'success', 'message' => 'Pengguna berhasil diperbarui!'];

            } else { // Proses tambah pengguna baru
                $sql = "INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $full_name, password_hash($password, PASSWORD_DEFAULT), $role]);
                $response = ['status' => 'success', 'message' => 'Pengguna baru berhasil ditambahkan!'];
            }
            break;

        case 'delete_user':
            $user_id_to_delete = $_POST['user_id'] ?? 0;

            if ($user_id_to_delete == $current_user_id) {
                throw new Exception("Anda tidak dapat menghapus akun Anda sendiri.");
            }

            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id_to_delete]);
            $user_to_delete = $stmt->fetch();

            if (!$user_to_delete) {
                throw new Exception("Pengguna tidak ditemukan.");
            }
            
            if ($user_to_delete['role'] === 'superadmin') {
                $special_code = $_POST['special_code'] ?? '';
                if ($special_code !== SUPERADMIN_CODE) {
                    throw new Exception("Kode spesial untuk menghapus Superadmin salah.");
                }
            }
            
            $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->execute([$user_id_to_delete]);
            
            $response = ['status' => 'success', 'message' => 'Pengguna berhasil dihapus!'];
            break;

        default:
            throw new Exception("Aksi tidak valid.");
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);