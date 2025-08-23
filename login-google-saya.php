<?php
/**
 * Plugin Name:       Login dengan Akun Google
 * Description:       Menambahkan tombol login dengan Google di halaman login WordPress dan WooCommerce.
 * Version:           1.0.0
 * Author:            Gemini
 * Author URI:        https://google.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       google-login
 */

// Mencegah akses langsung ke file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kelas utama untuk plugin login Google.
 */
class Simple_Google_Login {

    /**
     * Constructor untuk inisialisasi hook.
     */
    public function __construct() {
        // Menambahkan halaman pengaturan di menu admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);

        // Menambahkan tombol login di berbagai form
        add_action('login_form', [$this, 'display_google_login_button']);
        add_action('register_form', [$this, 'display_google_login_button']);
        add_action('woocommerce_login_form_end', [$this, 'display_google_login_button']);
        add_action('woocommerce_register_form_end', [$this, 'display_google_login_button']);

        // Menambahkan CSS untuk tombol
        add_action('login_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

        // Menangani callback dari Google OAuth
        add_action('wp_ajax_nopriv_google_oauth_callback', [$this, 'handle_google_callback']);
        add_action('wp_ajax_google_oauth_callback', [$this, 'handle_google_callback']);
    }

    /**
     * Menambahkan halaman pengaturan ke menu admin WordPress.
     */
    public function add_admin_menu() {
        add_options_page(
            'Google Login Settings',
            'Google Login Settings',
            'manage_options',
            'google_login_settings',
            [$this, 'options_page_html']
        );
    }

    /**
     * Inisialisasi pengaturan plugin menggunakan WordPress Settings API.
     */
    public function settings_init() {
        register_setting('google_login_plugin', 'google_login_settings');

        add_settings_section(
            'google_login_plugin_section',
            'Pengaturan Google OAuth Credentials',
            null,
            'google_login_plugin'
        );

        add_settings_field(
            'google_client_id',
            'Google Client ID',
            [$this, 'client_id_field_html'],
            'google_login_plugin',
            'google_login_plugin_section'
        );

        add_settings_field(
            'google_client_secret',
            'Google Client Secret',
            [$this, 'client_secret_field_html'],
            'google_login_plugin',
            'google_login_plugin_section'
        );
    }

    /**
     * HTML untuk field Client ID.
     */
    public function client_id_field_html() {
        $options = get_option('google_login_settings');
        ?>
        <input type='text' name='google_login_settings[google_client_id]' value='<?php echo esc_attr($options['google_client_id'] ?? ''); ?>' class="regular-text">
        <?php
    }

    /**
     * HTML untuk field Client Secret.
     */
    public function client_secret_field_html() {
        $options = get_option('google_login_settings');
        ?>
        <input type='password' name='google_login_settings[google_client_secret]' value='<?php echo esc_attr($options['google_client_secret'] ?? ''); ?>' class="regular-text">
        <?php
    }

    /**
     * Tampilan halaman pengaturan.
     */
    public function options_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>Masukkan kredensial Google OAuth Anda di bawah ini. Anda bisa mendapatkannya dari <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</p>
            <p>Pastikan untuk menambahkan <strong>Authorized redirect URI</strong> berikut di pengaturan OAuth Anda:</p>
            <p><code><?php echo esc_url(admin_url('admin-ajax.php?action=google_oauth_callback')); ?></code></p>
            <form action="options.php" method="post">
                <?php
                settings_fields('google_login_plugin');
                do_settings_sections('google_login_plugin');
                submit_button('Simpan Pengaturan');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Menampilkan tombol login Google.
     */
    public function display_google_login_button() {
        $options = get_option('google_login_settings');
        if (empty($options['google_client_id'])) {
            return;
        }

        // Buat state untuk keamanan (mencegah serangan CSRF)
        $state = wp_create_nonce('google_login_nonce');
        set_transient('google_login_state', $state, 60 * 5); // Simpan state selama 5 menit

        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $options['google_client_id'],
            'redirect_uri'  => admin_url('admin-ajax.php?action=google_oauth_callback'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
        ]);

        echo '<a href="' . esc_url($auth_url) . '" class="google-login-button">
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="18px" height="18px" viewBox="0 0 48 48" class="abc-icon">
                    <g>
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                        <path fill="none" d="M0 0h48v48H0z"></path>
                    </g>
                </svg>
                <span>Login dengan Google</span>
              </a>';
    }

    /**
     * Menambahkan file CSS untuk styling tombol.
     */
    public function enqueue_styles() {
        $css = "
        .google-login-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
            color: #444;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            margin-top: 15px;
            width: 100%;
            box-sizing: border-box;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: background-color .2s, box-shadow .2s;
        }
        .google-login-button:hover {
            background-color: #f9f9f9;
            border-color: #ccc;
        }
        .google-login-button svg {
            margin-right: 10px;
        }
        #loginform .google-login-button, #registerform .google-login-button {
             margin-bottom: 10px;
        }
        ";
        wp_add_inline_style('login', $css);
        wp_add_inline_style('woocommerce-layout', $css); // Untuk halaman WooCommerce
    }

    /**
     * Menangani callback setelah otentikasi Google berhasil.
     */
    public function handle_google_callback() {
        // Verifikasi state nonce
        $state = sanitize_text_field($_GET['state']);
        $transient_state = get_transient('google_login_state');
        delete_transient('google_login_state'); // Hapus transient setelah digunakan

        if (!$state || !$transient_state || $state !== $transient_state) {
            wp_die('Sesi tidak valid atau telah kedaluwarsa. Silakan coba lagi.');
        }

        if (!isset($_GET['code'])) {
            wp_die('Kode otorisasi tidak ditemukan.');
        }

        $code = sanitize_text_field($_GET['code']);
        $options = get_option('google_login_settings');

        // Tukar kode dengan access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $options['google_client_id'],
                'client_secret' => $options['google_client_secret'],
                'redirect_uri'  => admin_url('admin-ajax.php?action=google_oauth_callback'),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_die('Gagal terhubung ke Google: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            wp_die('Terjadi kesalahan saat otentikasi: ' . esc_html($body['error_description']));
        }

        $id_token = $body['id_token'];
        
        // Decode ID token untuk mendapatkan info pengguna
        list($header, $payload, $signature) = explode('.', $id_token);
        $user_info = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

        if (empty($user_info['email'])) {
            wp_die('Tidak dapat mengambil alamat email dari Google.');
        }

        $email = sanitize_email($user_info['email']);

        // Cari pengguna berdasarkan email
        $user = get_user_by('email', $email);

        if ($user) {
            // Jika pengguna sudah ada, loginkan
            $user_id = $user->ID;
        } else {
            // Jika pengguna belum ada, buat akun baru
            $username = sanitize_user(explode('@', $email)[0]);
            $i = 1;
            $base_username = $username;
            while (username_exists($username)) {
                $username = $base_username . $i;
                $i++;
            }

            $random_password = wp_generate_password(12, false);
            $user_data = [
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => $random_password,
                'first_name' => sanitize_text_field($user_info['given_name'] ?? ''),
                'last_name'  => sanitize_text_field($user_info['family_name'] ?? ''),
                'display_name' => sanitize_text_field($user_info['name'] ?? $username),
            ];
            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                wp_die('Gagal membuat pengguna baru: ' . $user_id->get_error_message());
            }
        }

        // Loginkan pengguna
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // Arahkan pengguna ke halaman yang sesuai
        $redirect_url = home_url();
        if (class_exists('WooCommerce')) {
            $redirect_url = wc_get_page_permalink('myaccount');
        }
        
        wp_redirect($redirect_url);
        exit;
    }
}

// Inisialisasi plugin
new Simple_Google_Login();

new SGL_Plugin_Updater(
    __FILE__,
    'https://gist.githubusercontent.com/miftahussyarif/gemini/raw/info.json'
);
