<?php
/**
 * Plugin Name: CBT Settings Fix
 * Plugin URI: https://cbt.mandualotim.sch.id
 * Description: Memperbaiki fitur CBT yang tidak terhubung dengan pengaturan: Pendaftaran Mandiri, Auto Token, Minimal Sisa Waktu, Tampilkan Nilai, dll.
 * Version: 1.0.0
 * Author: MAN 2 Lombok Timur
 * Text Domain: cbt-settings-fix
 */

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Settings_Fix {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook ke wp_footer untuk inject JavaScript di semua halaman frontend
        add_action('wp_footer', array($this, 'inject_frontend_scripts'), 9999);
        add_action('wp_head', array($this, 'inject_frontend_styles'), 9999);
        
        // Hook ke REST API untuk menyediakan endpoint settings
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Hook ke wp_ajax untuk non-logged-in users (siswa)
        add_action('wp_ajax_nopriv_cbt_get_settings', array($this, 'ajax_get_settings'));
        add_action('wp_ajax_cbt_get_settings', array($this, 'ajax_get_settings'));
        
        // Hook untuk registrasi siswa
        add_action('wp_ajax_nopriv_cbt_register_siswa', array($this, 'ajax_register_siswa'));
        add_action('wp_ajax_cbt_register_siswa', array($this, 'ajax_register_siswa'));
    }

    /**
     * Ambil semua settings CBT dari wp_options
     */
    public function get_all_settings() {
        return array(
            'bolehdaftar'       => get_option('siswa-melakukan-pendaftaran-sendiri', 'Tidak'),
            'autotoken'         => get_option('token-secara-otomatis', 'Tidak'),
            'waktutoken'        => get_option('masa-aktif-token', '15'),
            'examtimer'         => get_option('exam-timer', '5'),
            'minimalsisawaktu'  => get_option('minimal-sisa-waktu-sebelum-boleh-mengumpulkan', '0'),
            'shownilai'         => get_option('tampilkan-nilai-siswa-di-akhir-ujian', 'Tidak'),
            'useragent'         => get_option('useragent', ''),
            'timezone'          => get_option('timezone', 'Asia/Jakarta'),
            'examkey'           => get_option('examkey', '0'),
            'excelkey'          => get_option('excel-key', ''),
            'bolehlogout'       => get_option('siswa-boleh-logout-sebelum-selesai', 'Tidak'),
            'bolehkumpul'       => get_option('tidak-wajib-menjawab-semua-soal', 'Tidak'),
            'shownamasekolah'   => get_option('tampilkan-nama-sekolah', 'Ya'),
            'wajibreset'        => get_option('wajib-reset-ketika-keluar-dari-ujian', 'Tidak'),
            'token'             => get_option('token', ''),
            'welcome'           => get_option('pesan-homepage', ''),
            'blogdescription'   => get_option('tagline', ''),
            'allmapel'          => get_option('pilihan-mapel', ''),
            'pesanbanned'       => get_option('pesan-error-siswa-diblokir', ''),
            'logingoogle'       => get_option('login-menggunakan-account-google', 'Tidak'),
        );
    }

    /**
     * AJAX handler untuk get settings
     */
    public function ajax_get_settings() {
        header('Content-Type: application/json');
        echo json_encode($this->get_all_settings());
        wp_die();
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('cbt-fix/v1', '/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_settings'),
            'permission_callback' => '__return_true',
        ));
    }

    public function rest_get_settings() {
        return new WP_REST_Response($this->get_all_settings(), 200);
    }

    /**
     * AJAX handler untuk registrasi siswa baru
     * Tabel: portalubk_bsfsm_siswa
     * Kolom: indexkey(PK), id, kode(username), nama, pass(password), nik, nik2, mapel, server, sesi, finish
     */
    public function ajax_register_siswa() {
        global $wpdb;
        $table_prefix = $wpdb->prefix;
        
        // Ambil data dari POST
        $kode     = sanitize_text_field($_POST['username'] ?? '');
        $pass     = sanitize_text_field($_POST['password'] ?? '');
        $nama     = sanitize_text_field($_POST['nama'] ?? '');
        $nik      = sanitize_text_field($_POST['nik'] ?? '');
        
        if (empty($kode) || empty($pass) || empty($nama)) {
            wp_send_json_error('No. Peserta, Password, dan Nama harus diisi');
            return;
        }
        
        // Cek apakah siswa sudah ada
        $table_siswa = $table_prefix . 'bsfsm_siswa';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_siswa} WHERE kode = %s",
            $kode
        ));
        
        if ($exists > 0) {
            wp_send_json_error('No. Peserta sudah terdaftar');
            return;
        }
        
        // Generate indexkey unik
        $indexkey = 'REG_' . $kode . '_' . time();
        
        // Insert siswa baru sesuai struktur tabel asli
        $result = $wpdb->insert($table_siswa, array(
            'indexkey' => $indexkey,
            'id'       => $kode,
            'kode'     => $kode,
            'nama'     => $nama,
            'pass'     => $pass,
            'nik'      => $nik,
            'nik2'     => '',
            'mapel'    => '',
            'server'   => '',
            'sesi'     => '',
            'finish'   => 0,
        ));
        
        if ($result) {
            wp_send_json_success('Pendaftaran berhasil! Silakan login dengan No. Peserta dan Password yang telah dibuat.');
        } else {
            wp_send_json_error('Gagal mendaftar: ' . $wpdb->last_error);
        }
    }

    /**
     * Inject CSS di head untuk styling
     */
    public function inject_frontend_styles() {
        // Hanya inject di halaman frontend (bukan admin)
        if (is_admin()) return;
        ?>
        <style id="cbt-settings-fix-css">
            /* Modal Pendaftaran */
            .cbt-modal-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.6);
                z-index: 99999;
                align-items: center;
                justify-content: center;
            }
            .cbt-modal-overlay.active {
                display: flex;
            }
            .cbt-modal {
                background: #fff;
                border-radius: 12px;
                max-width: 420px;
                width: 90%;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
                animation: cbtModalIn 0.3s ease;
            }
            @keyframes cbtModalIn {
                from { opacity: 0; transform: scale(0.9) translateY(-20px); }
                to { opacity: 1; transform: scale(1) translateY(0); }
            }
            .cbt-modal-header {
                background: linear-gradient(135deg, #1a6fb5, #0d5a9e);
                color: #fff;
                padding: 18px 22px;
                font-size: 17px;
                font-weight: 700;
            }
            .cbt-modal-body {
                padding: 22px;
            }
            .cbt-modal-body .form-group {
                margin-bottom: 14px;
            }
            .cbt-modal-body label {
                font-weight: 600;
                font-size: 13px;
                color: #333;
                margin-bottom: 5px;
                display: block;
            }
            .cbt-modal-body input,
            .cbt-modal-body select {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #ced4da;
                border-radius: 6px;
                font-size: 14px;
                box-sizing: border-box;
            }
            .cbt-modal-body input:focus {
                border-color: #1976D2;
                outline: none;
                box-shadow: 0 0 0 3px rgba(25,118,210,0.15);
            }
            .cbt-modal-footer {
                padding: 14px 22px;
                background: #f8f9fa;
                border-top: 1px solid #e9ecef;
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }
            .cbt-btn {
                padding: 10px 22px;
                border-radius: 6px;
                border: none;
                font-weight: 600;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.2s;
            }
            .cbt-btn-primary {
                background: #1976D2;
                color: #fff;
            }
            .cbt-btn-primary:hover {
                background: #1565C0;
            }
            .cbt-btn-secondary {
                background: #e0e0e0;
                color: #333;
            }
            .cbt-btn-secondary:hover {
                background: #bdbdbd;
            }
            .cbt-btn-register {
                display: block;
                width: 100%;
                padding: 12px;
                margin-top: 12px;
                background: transparent;
                border: 2px solid #4285F4;
                color: #4285F4;
                border-radius: 25px;
                font-weight: 600;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s;
                text-align: center;
                letter-spacing: 0.5px;
            }
            .cbt-btn-register:hover {
                background: #4285F4;
                color: #fff;
            }
            .cbt-alert {
                padding: 10px 14px;
                border-radius: 6px;
                margin-bottom: 14px;
                font-size: 13px;
                display: none;
            }
            .cbt-alert-success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .cbt-alert-danger {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
        </style>
        <?php
    }

    /**
     * Inject JavaScript di footer
     */
    public function inject_frontend_scripts() {
        if (is_admin()) return;
        
        $settings = $this->get_all_settings();
        $ajax_url = admin_url('admin-ajax.php');
        $settings_json = json_encode($settings);
        ?>
        <script id="cbt-settings-fix-js">
        (function() {
            'use strict';
            
            var CBT_SETTINGS = <?php echo $settings_json; ?>;
            var CBT_AJAX_URL = '<?php echo esc_js($ajax_url); ?>';
            
            console.log('[CBT-Fix] Plugin loaded. Settings:', CBT_SETTINGS);

            // ============================================================
            // 1. FITUR PENDAFTARAN MANDIRI
            // ============================================================
            function initPendaftaran() {
                if (CBT_SETTINGS.bolehdaftar !== 'Ya') {
                    console.log('[CBT-Fix] Pendaftaran mandiri: DISABLED');
                    return;
                }
                
                console.log('[CBT-Fix] Pendaftaran mandiri: ENABLED - Adding button...');
                
                // Cari form login atau tombol login di halaman
                var loginBtn = document.querySelector('button[type="submit"], input[type="submit"], .btn-login, a.btn-login, button.btn-login');
                var loginForm = document.querySelector('form');
                
                if (!loginBtn && !loginForm) {
                    // Mungkin bukan halaman login
                    console.log('[CBT-Fix] Bukan halaman login, skip pendaftaran');
                    return;
                }
                
                var targetContainer = null;
                if (loginBtn) {
                    targetContainer = loginBtn.parentElement;
                } else if (loginForm) {
                    targetContainer = loginForm;
                }
                
                if (!targetContainer) return;
                
                // Cek apakah sudah ada tombol daftar
                if (document.getElementById('cbt-btn-daftar')) return;
                
                // Buat tombol Pendaftaran
                var btnDaftar = document.createElement('button');
                btnDaftar.type = 'button';
                btnDaftar.id = 'cbt-btn-daftar';
                btnDaftar.className = 'cbt-btn-register';
                btnDaftar.innerHTML = 'Pendaftaran Peserta Baru';
                btnDaftar.onclick = function() {
                    showRegistrationModal();
                };
                
                // Insert setelah tombol login
                if (loginBtn && loginBtn.nextSibling) {
                    loginBtn.parentElement.insertBefore(btnDaftar, loginBtn.nextSibling);
                } else {
                    targetContainer.appendChild(btnDaftar);
                }
                
                // Buat modal pendaftaran
                createRegistrationModal();
                
                console.log('[CBT-Fix] Tombol pendaftaran berhasil ditambahkan');
            }
            
            function createRegistrationModal() {
                if (document.getElementById('cbt-register-modal')) return;
                
                var overlay = document.createElement('div');
                overlay.className = 'cbt-modal-overlay';
                overlay.id = 'cbt-register-modal';
                overlay.innerHTML = 
                    '<div class="cbt-modal">' +
                        '<div class="cbt-modal-header">' +
                            'Pendaftaran Peserta Baru' +
                        '</div>' +
                        '<div class="cbt-modal-body">' +
                            '<div class="cbt-alert cbt-alert-success" id="cbt-reg-success"></div>' +
                            '<div class="cbt-alert cbt-alert-danger" id="cbt-reg-error"></div>' +
                            '<div class="form-group">' +
                                '<label>No. Peserta / Username *</label>' +
                                '<input type="text" id="cbt-reg-username" placeholder="Masukkan nomor peserta">' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label>Password *</label>' +
                                '<input type="password" id="cbt-reg-password" placeholder="Masukkan password">' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label>Nama Lengkap *</label>' +
                                '<input type="text" id="cbt-reg-nama" placeholder="Masukkan nama lengkap">' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label>NIK / NISN</label>' +
                                '<input type="text" id="cbt-reg-nik" placeholder="Masukkan NIK atau NISN (opsional)">' +
                            '</div>' +
                        '</div>' +
                        '<div class="cbt-modal-footer">' +
                            '<button class="cbt-btn cbt-btn-secondary" onclick="closeRegistrationModal()">Batal</button>' +
                            '<button class="cbt-btn cbt-btn-primary" id="cbt-reg-submit" onclick="submitRegistration()">Daftar</button>' +
                        '</div>' +
                    '</div>';
                
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) closeRegistrationModal();
                });
                
                document.body.appendChild(overlay);
            }
            
            window.showRegistrationModal = function() {
                var modal = document.getElementById('cbt-register-modal');
                if (modal) {
                    modal.classList.add('active');
                    // Reset form
                    var inputs = modal.querySelectorAll('input');
                    for (var i = 0; i < inputs.length; i++) inputs[i].value = '';
                    var alerts = modal.querySelectorAll('.cbt-alert');
                    for (var j = 0; j < alerts.length; j++) alerts[j].style.display = 'none';
                }
            };
            
            window.closeRegistrationModal = function() {
                var modal = document.getElementById('cbt-register-modal');
                if (modal) modal.classList.remove('active');
            };
            
            window.submitRegistration = function() {
                var username = document.getElementById('cbt-reg-username').value.trim();
                var password = document.getElementById('cbt-reg-password').value.trim();
                var nama = document.getElementById('cbt-reg-nama').value.trim();
                var nik = document.getElementById('cbt-reg-nik').value.trim();
                var successEl = document.getElementById('cbt-reg-success');
                var errorEl = document.getElementById('cbt-reg-error');
                var submitBtn = document.getElementById('cbt-reg-submit');
                
                successEl.style.display = 'none';
                errorEl.style.display = 'none';
                
                if (!username || !password || !nama) {
                    errorEl.textContent = 'No. Peserta, Password, dan Nama harus diisi!';
                    errorEl.style.display = 'block';
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memproses...';
                
                var formData = new FormData();
                formData.append('action', 'cbt_register_siswa');
                formData.append('username', username);
                formData.append('password', password);
                formData.append('nama', nama);
                formData.append('nik', nik);
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', CBT_AJAX_URL, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Daftar';
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.success) {
                                successEl.textContent = resp.data;
                                successEl.style.display = 'block';
                                errorEl.style.display = 'none';
                                setTimeout(function() {
                                    closeRegistrationModal();
                                }, 2000);
                            } else {
                                errorEl.textContent = resp.data || 'Gagal mendaftar';
                                errorEl.style.display = 'block';
                            }
                        } catch(e) {
                            errorEl.textContent = 'Terjadi kesalahan server';
                            errorEl.style.display = 'block';
                        }
                    }
                };
                xhr.send(formData);
            };

            // ============================================================
            // 2. FITUR AUTO TOKEN
            // ============================================================
            function initAutoToken() {
                if (CBT_SETTINGS.autotoken !== 'Ya') {
                    console.log('[CBT-Fix] Auto token: DISABLED');
                    return;
                }
                
                console.log('[CBT-Fix] Auto token: ENABLED');
                
                // Cari halaman konfirmasi (halaman dengan input token)
                var tokenInput = document.querySelector('input[name="token"], input#token, input.input-token, input[placeholder*="TOKEN" i], input[placeholder*="token" i]');
                
                if (!tokenInput) {
                    console.log('[CBT-Fix] Bukan halaman konfirmasi/token, skip auto-token');
                    return;
                }
                
                console.log('[CBT-Fix] Token input found, auto-filling...');
                
                // Ambil token aktif dari settings
                var activeToken = CBT_SETTINGS.token || '';
                
                if (activeToken && activeToken.length > 0) {
                    // Auto-fill token
                    tokenInput.value = activeToken;
                    
                    // Trigger change event
                    var event = new Event('input', { bubbles: true });
                    tokenInput.dispatchEvent(event);
                    var changeEvent = new Event('change', { bubbles: true });
                    tokenInput.dispatchEvent(changeEvent);
                    
                    console.log('[CBT-Fix] Token auto-filled: ' + activeToken);
                    
                    // Auto-submit form setelah delay singkat
                    setTimeout(function() {
                        var form = tokenInput.closest('form');
                        if (form) {
                            console.log('[CBT-Fix] Auto-submitting token form...');
                            form.submit();
                        }
                    }, 1500);
                } else {
                    console.log('[CBT-Fix] Auto-token enabled tapi token kosong');
                }
            }

            // ============================================================
            // 3. FITUR MINIMAL SISA WAKTU SEBELUM BOLEH MENGUMPULKAN
            // ============================================================
            function initMinimalSisaWaktu() {
                var minWaktu = parseInt(CBT_SETTINGS.minimalsisawaktu) || 0;
                
                if (minWaktu <= 0) {
                    console.log('[CBT-Fix] Minimal sisa waktu: DISABLED (0)');
                    return;
                }
                
                console.log('[CBT-Fix] Minimal sisa waktu: ' + minWaktu + ' menit');
                
                // Cari tombol kumpulkan/submit ujian
                var kumpulBtn = document.querySelector(
                    'button[onclick*="kumpul" i], ' +
                    'a[onclick*="kumpul" i], ' +
                    'button[onclick*="selesai" i], ' +
                    '#btn-kumpul, #btnKumpul, ' +
                    '.btn-kumpul, .btn-selesai, ' +
                    'button:not([type="submit"])'
                );
                
                // Juga cari via text content
                if (!kumpulBtn) {
                    var allButtons = document.querySelectorAll('button, a.btn');
                    for (var i = 0; i < allButtons.length; i++) {
                        var btnText = (allButtons[i].textContent || '').toLowerCase();
                        if (btnText.indexOf('kumpul') !== -1 || btnText.indexOf('selesai') !== -1) {
                            kumpulBtn = allButtons[i];
                            break;
                        }
                    }
                }
                
                if (!kumpulBtn) {
                    console.log('[CBT-Fix] Tombol kumpul tidak ditemukan, skip');
                    return;
                }
                
                // Monitor timer dan disable tombol jika waktu masih > minimal
                var checkInterval = setInterval(function() {
                    // Cari elemen timer di halaman
                    var timerEl = document.querySelector(
                        '#timer, .timer, #countdown, .countdown, ' +
                        '#waktu, .waktu, [id*="timer" i], [class*="timer" i]'
                    );
                    
                    if (!timerEl) return;
                    
                    var timerText = timerEl.textContent || '';
                    // Parse timer format HH:MM:SS atau MM:SS
                    var parts = timerText.match(/(\d+):(\d+):?(\d+)?/);
                    if (!parts) return;
                    
                    var totalMinutes;
                    if (parts[3] !== undefined) {
                        // HH:MM:SS
                        totalMinutes = parseInt(parts[1]) * 60 + parseInt(parts[2]);
                    } else {
                        // MM:SS
                        totalMinutes = parseInt(parts[1]);
                    }
                    
                    // Ambil total waktu ujian
                    var examTimerMinutes = parseInt(CBT_SETTINGS.examtimer) || 0;
                    
                    // Sisa waktu = total waktu - waktu berjalan
                    // Tapi timer biasanya menghitung mundur, jadi totalMinutes = sisa waktu
                    var sisaWaktu = totalMinutes;
                    
                    if (sisaWaktu > minWaktu) {
                        // Belum boleh kumpul
                        if (!kumpulBtn.dataset.cbtOrigDisabled) {
                            kumpulBtn.dataset.cbtOrigDisabled = 'set';
                            kumpulBtn.dataset.cbtOrigOnclick = kumpulBtn.getAttribute('onclick') || '';
                            kumpulBtn.setAttribute('onclick', 'return false;');
                            kumpulBtn.style.opacity = '0.5';
                            kumpulBtn.style.cursor = 'not-allowed';
                            kumpulBtn.title = 'Anda baru boleh mengumpulkan saat sisa waktu ' + minWaktu + ' menit';
                        }
                    } else {
                        // Boleh kumpul
                        if (kumpulBtn.dataset.cbtOrigDisabled) {
                            if (kumpulBtn.dataset.cbtOrigOnclick) {
                                kumpulBtn.setAttribute('onclick', kumpulBtn.dataset.cbtOrigOnclick);
                            } else {
                                kumpulBtn.removeAttribute('onclick');
                            }
                            kumpulBtn.style.opacity = '';
                            kumpulBtn.style.cursor = '';
                            kumpulBtn.title = '';
                            delete kumpulBtn.dataset.cbtOrigDisabled;
                            clearInterval(checkInterval);
                        }
                    }
                }, 5000);
            }

            // ============================================================
            // 4. FITUR TAMPILKAN NILAI SISWA DI AKHIR UJIAN
            // ============================================================
            function initShowNilai() {
                if (CBT_SETTINGS.shownilai !== 'Ya') {
                    console.log('[CBT-Fix] Tampilkan nilai: DISABLED');
                    return;
                }
                
                console.log('[CBT-Fix] Tampilkan nilai: ENABLED');
                // Fitur ini biasanya sudah ditangani oleh backend
                // Plugin hanya memastikan setting tersedia di frontend
            }

            // ============================================================
            // 5. UPDATE indb.php SECARA DINAMIS
            //    Sinkronisasi settings WordPress ke variabel indb.php
            // ============================================================
            // Ini ditangani di sisi PHP di bawah

            // ============================================================
            // INIT - Jalankan semua fitur saat DOM ready
            // ============================================================
            function initAll() {
                console.log('[CBT-Fix] Initializing all features...');
                initPendaftaran();
                initAutoToken();
                initMinimalSisaWaktu();
                initShowNilai();
                console.log('[CBT-Fix] All features initialized.');
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initAll);
            } else {
                // DOM sudah ready
                initAll();
            }
            
        })();
        </script>
        <?php
    }
}

// ============================================================
// SYNC SETTINGS KE indb.php
// Setiap kali pengaturan-test disimpan, update indb.php juga
// ============================================================
add_action('update_option', 'cbt_sync_indb_on_option_update', 10, 3);

function cbt_sync_indb_on_option_update($option, $old_value, $new_value) {
    // List option yang relevan untuk CBT
    $cbt_options = array(
        'siswa-melakukan-pendaftaran-sendiri',
        'token-secara-otomatis',
        'masa-aktif-token',
        'exam-timer',
        'minimal-sisa-waktu-sebelum-boleh-mengumpulkan',
        'tampilkan-nilai-siswa-di-akhir-ujian',
        'useragent',
        'timezone',
        'examkey',
        'excel-key',
        'siswa-boleh-logout-sebelum-selesai',
        'tidak-wajib-menjawab-semua-soal',
        'tampilkan-nama-sekolah',
        'wajib-reset-ketika-keluar-dari-ujian',
        'token',
        'pesan-homepage',
        'tagline',
        'pilihan-mapel',
        'pesan-error-siswa-diblokir',
        'pesan-error-siswa-gagal-login',
        'metode-penghitungan-waktu',
        'metode-penyimpanan-jawaban',
        'is_pengaturan-test_submit',
    );
    
    if (in_array($option, $cbt_options) || $option === 'is_pengaturan-test_submit') {
        cbt_regenerate_indb();
    }
}

// Juga hook saat form pengaturan-test disave (biasanya melalui update_option di halaman admin)
add_action('admin_init', 'cbt_check_pengaturan_submit');

function cbt_check_pengaturan_submit() {
    if (isset($_POST['is_pengaturan-test_submit']) || 
        (isset($_GET['page']) && $_GET['page'] === 'pengaturan-test' && $_SERVER['REQUEST_METHOD'] === 'POST')) {
        // Delay regeneration to after all options are saved
        add_action('shutdown', 'cbt_regenerate_indb');
    }
}

/**
 * Regenerate indb.php dengan nilai terbaru dari WordPress options
 */
function cbt_regenerate_indb() {
    static $already_done = false;
    if ($already_done) return;
    $already_done = true;
    
    $theme_dir = get_template_directory();
    $indb_path = $theme_dir . '/indb.php';
    
    if (!file_exists($indb_path)) {
        error_log('[CBT-Fix] indb.php not found at: ' . $indb_path);
        return;
    }
    
    // Ambil database config dari WordPress
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_password = DB_PASSWORD;
    $db_host = DB_HOST;
    
    global $wpdb;
    $table_prefix_val = $wpdb->prefix;
    
    // Ambil semua settings
    $opt_examtimer      = get_option('exam-timer', '5');
    $opt_waktutoken     = get_option('masa-aktif-token', '15');
    $opt_bolehlogout    = (get_option('siswa-boleh-logout-sebelum-selesai', 'Tidak') === 'Ya') ? '1' : '0';
    $opt_excelkey       = get_option('excel-key', '123456');
    $opt_autologin      = '0'; // Selalu 0
    $opt_shownilai      = (get_option('tampilkan-nilai-siswa-di-akhir-ujian', 'Tidak') === 'Ya') ? '1' : '0';
    $opt_wajibreset     = (get_option('wajib-reset-ketika-keluar-dari-ujian', 'Tidak') === 'Ya') ? '1' : '0';
    $opt_bolehdaftar    = (get_option('siswa-melakukan-pendaftaran-sendiri', 'Tidak') === 'Ya') ? '1' : '0';
    $opt_logingoogle    = (get_option('login-menggunakan-account-google', 'Tidak') === 'Ya') ? '1' : '';
    $opt_autotoken      = (get_option('token-secara-otomatis', 'Tidak') === 'Ya') ? '1' : '0';
    $opt_top10          = '';
    $opt_isonline       = '1';
    $opt_allmapel       = (get_option('pilihan-mapel', '') !== '') ? '1' : '1';
    $opt_iframe         = '';
    $opt_bolehkumpul    = (get_option('tidak-wajib-menjawab-semua-soal', 'Tidak') === 'Ya') ? '1' : '0';
    $opt_shownamasekolah = (get_option('tampilkan-nama-sekolah', 'Ya') === 'Ya') ? '1' : '0';
    $opt_backend        = '';
    $opt_gdrive_token   = '';
    $opt_gdrive_parent  = '';
    $opt_blogdescription = get_option('tagline', 'Aplikasi Ujian Berbasis Komputer');
    $opt_welcome        = get_option('pesan-homepage', 'Selamat Datang di Ujian Berbasis Komputer (CBT). Silakan login dengan username dan password Anda.');
    $opt_waktutelat     = '';
    $opt_localstorage   = (get_option('metode-penyimpanan-jawaban', 'Realtime') === 'Realtime') ? 'realtime' : 'localstorage';
    $opt_modetimer      = get_option('metode-penghitungan-waktu', 'Dynamic');
    $opt_modemapel      = '';
    $opt_pesanbanned    = get_option('pesan-error-siswa-diblokir', 'Akun Anda Diblokir, Silakan Hubungi Proktor / Pengawas Ujian');
    $opt_minimalsisawaktu = get_option('minimal-sisa-waktu-sebelum-boleh-mengumpulkan', '0');
    $opt_examkey        = get_option('examkey', '0');
    $opt_useragent      = get_option('useragent', 'Mozilla');
    $opt_timezone       = get_option('timezone', 'Asia/Jakarta');
    
    // Get site URL
    $site_url = get_option('siteurl', 'http://localhost/cbt');
    $home_url_val = get_option('home', $site_url);
    
    // Escape string untuk PHP
    $esc = function($str) {
        return addslashes($str);
    };
    
    $indb_content = '<?php
	    //require_once (dirname(__FILE__).\'/../../../wp-config.php\'); 
	    $table_prefix  = "' . $esc($table_prefix_val) . '";
	    $servername = "' . $esc($db_host) . '";
	    $username = "' . $esc($db_user) . '";
	    $password = "' . $esc($db_password) . '";
	    $dbname = "' . $esc($db_name) . '";
	    set_error_handler(function() { 
	    	?>
	    		<script>
	    			alert (\'Gagal Terkoneksi ke database. Silakan Save, Pengaturan Test dan Reset Cache\');
	    		</script>
	    	<?php
	    });
	    $conn = new mysqli($servername, $username, $password, $dbname);
	    restore_error_handler();	    
	    if ($conn->connect_error) {
	    } else {
			$conn->set_charset(\'utf8\'); 
			
			$absolute_url		= "' . $esc(trailingslashit($site_url)) . '";
			$home_url			= "' . $esc(trailingslashit($home_url_val)) . '";

    		$opt_examtimer 	= "' . $esc($opt_examtimer) . '";
    		$opt_waktutoken 	= "' . $esc($opt_waktutoken) . '";
    		$opt_bolehlogout 	= "' . $esc($opt_bolehlogout) . '";
    		$opt_excelkey 		= "' . $esc($opt_excelkey) . '";
    		$opt_autologin 		= "' . $esc($opt_autologin) . '";
    		$opt_shownilai 		= "' . $esc($opt_shownilai) . '";
    		$opt_wajibreset 		= "' . $esc($opt_wajibreset) . '";
    		$opt_bolehdaftar 		= "' . $esc($opt_bolehdaftar) . '";
    		$opt_logingoogle 		= "' . $esc($opt_logingoogle) . '";
    		$opt_autotoken 		= "' . $esc($opt_autotoken) . '";
    		$opt_top10 		= "' . $esc($opt_top10) . '";
    		$opt_isonline 		= "' . $esc($opt_isonline) . '";
    		$opt_allmapel 		= "' . $esc($opt_allmapel) . '";
    		$opt_iframe 		= "' . $esc($opt_iframe) . '";
    		$opt_bolehkumpul 		= "' . $esc($opt_bolehkumpul) . '";
    		$opt_shownamasekolah 		= "' . $esc($opt_shownamasekolah) . '";
    		$opt_backend 		= "' . $esc($opt_backend) . '";
    		$opt_gdrive_token 		= "' . $esc($opt_gdrive_token) . '";
    		$opt_gdrive_parent 		= "' . $esc($opt_gdrive_parent) . '";
    		$opt_blogdescription 		= "' . $esc($opt_blogdescription) . '";

    		$opt_welcome 		= "' . $esc($opt_welcome) . '";

    		$opt_waktutelat 		= "' . $esc($opt_waktutelat) . '";

    		$opt_localstorage 		= "' . $esc($opt_localstorage) . '";
    		$opt_modetimer 		= "' . $esc($opt_modetimer) . '";
    		$opt_modemapel 		= "' . $esc($opt_modemapel) . '";

    		$opt_pesanbanned 		= "' . $esc($opt_pesanbanned) . '";
    		$opt_minimalsisawaktu 		= "' . $esc($opt_minimalsisawaktu) . '";
    		$opt_examkey 		    = "' . $esc($opt_examkey) . '";
    		$opt_useragent 		    = "' . $esc($opt_useragent) . '";
			$opt_timezone 		    = "' . $esc($opt_timezone) . '";

    		date_default_timezone_set("' . $esc($opt_timezone) . '");
	    }

	';
    
    // Tulis file indb.php
    $result = @file_put_contents($indb_path, $indb_content);
    
    if ($result !== false) {
        error_log('[CBT-Fix] indb.php berhasil diupdate dengan settings terbaru');
    } else {
        error_log('[CBT-Fix] GAGAL menulis indb.php. Check permissions.');
    }
}

// ============================================================
// AKTIVASI PLUGIN: Regenerate indb.php langsung
// ============================================================
register_activation_hook(__FILE__, 'cbt_settings_fix_activate');

function cbt_settings_fix_activate() {
    // Regenerate indb.php saat plugin diaktifkan
    cbt_regenerate_indb();
}

// ============================================================
// ADMIN NOTICE untuk konfirmasi plugin aktif
// ============================================================
add_action('admin_notices', 'cbt_settings_fix_admin_notice');

function cbt_settings_fix_admin_notice() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'pengaturan-test') return;
    ?>
    <div class="notice notice-success">
        <p><strong>CBT Settings Fix</strong> aktif. Pengaturan akan otomatis disinkronkan ke frontend saat Anda menyimpan.</p>
    </div>
    <?php
}

// ============================================================
// INIT PLUGIN
// ============================================================
CBT_Settings_Fix::get_instance();

