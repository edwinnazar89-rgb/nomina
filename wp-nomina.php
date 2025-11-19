<?php
/**
 * Plugin Name: WP Nómina y Vacaciones
 * Description: Cálculo de nómina (quincenas, bonos, reembolsos), gestión de vacaciones, permisos y cálculo de aguinaldo con portal público para colaboradores.
 * Version: 1.4.0
 * Author: Nueve y Media
 * Requires PHP: 7.2
 * Text Domain: wp-nomina
 */

if (!defined('ABSPATH')) exit;

if(!defined('WPN_VERSION')) define('WPN_VERSION','1.4.0');
if(!defined('WPN_DIR')) define('WPN_DIR', plugin_dir_path(__FILE__));
if(!defined('WPN_URL')) define('WPN_URL', plugin_dir_url(__FILE__));

// Includes
if (!class_exists('WPN_Utils')) require_once plugin_dir_path(__FILE__).'includes/class-nomina-utils.php';
if (!class_exists('WPN_Email')) require_once plugin_dir_path(__FILE__).'includes/class-nomina-email.php';
if (!class_exists('WPN_Admin')) require_once plugin_dir_path(__FILE__).'includes/class-nomina-admin.php';
if (!class_exists('WPN_Public')) require_once plugin_dir_path(__FILE__).'includes/class-nomina-public.php';
if (!class_exists('WPN_Mi_Informacion')) require_once plugin_dir_path(__FILE__).'includes/class-nomina-mi-informacion.php';
if (!class_exists('WPN_Aguinaldo')) require_once plugin_dir_path(__FILE__).'includes/class-nomina-aguinaldo.php';
if (!class_exists('WPN_Backup')) require_once plugin_dir_path(__FILE__).'includes/class-nomina-backup.php';
if (!class_exists('WPN_Permisos')) require_once plugin_dir_path(__FILE__).'includes/class-nomina-permisos.php';

// Admin init (menus, pages)
add_action('plugins_loaded', function(){
    if (class_exists('WPN_Admin')){
        WPN_Admin::init();
    }
    // Inicializar módulo de Aguinaldo
    if (class_exists('WPN_Aguinaldo')){
        WPN_Aguinaldo::init();
    }
    // Inicializar módulo de Backup
    if (class_exists('WPN_Backup')){
        WPN_Backup::init();
    }
    // Inicializar módulo de Permisos
    if (class_exists('WPN_Permisos')){
        WPN_Permisos::init();
    }
});

// Public hooks + shortcode
add_action('init', function(){
    if (class_exists('WPN_Public')){
        WPN_Public::init_hooks();
        add_shortcode('nomina_portal', [WPN_Public::class, 'render_portal']);
    }
});

// DB migration: add con_bono, apellido_paterno, apellido_materno, custom_password
add_action('plugins_loaded', function(){
    global $wpdb;
    $tq = $wpdb->prefix.'nmn_quincenas';
    $te = $wpdb->prefix.'nmn_employees';
    $ts = $wpdb->prefix.'nmn_vac_solicitudes';
    
    // Add con_bono to quincenas if missing
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tq));
    if ($exists){
        $col = $wpdb->get_results("SHOW COLUMNS FROM $tq LIKE 'con_bono'");
        if (!$col){
            $wpdb->query("ALTER TABLE $tq ADD COLUMN con_bono TINYINT(1) NOT NULL DEFAULT 0 AFTER imss_monto");
        }
    }
    
    // Add apellido_paterno, apellido_materno, custom_password to employees if missing
    $exists_emp = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $te));
    if ($exists_emp){
        $cols = $wpdb->get_col("DESC $te", 0);
        if (!in_array('apellido_paterno', $cols)){
            $wpdb->query("ALTER TABLE $te ADD COLUMN apellido_paterno VARCHAR(191) NULL AFTER nombre");
        }
        if (!in_array('apellido_materno', $cols)){
            $wpdb->query("ALTER TABLE $te ADD COLUMN apellido_materno VARCHAR(191) NULL AFTER apellido_paterno");
        }
        if (!in_array('custom_password', $cols)){
            $wpdb->query("ALTER TABLE $te ADD COLUMN custom_password TINYINT(1) NOT NULL DEFAULT 0 AFTER cuenta");
        }
    }
    
    // Add email_sent column to vac_solicitudes if missing
    $exists_vac = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $ts));
    if ($exists_vac){
        $cols = $wpdb->get_col("DESC $ts", 0);
        if (!in_array('email_sent', $cols)){
            $wpdb->query("ALTER TABLE $ts ADD COLUMN email_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER estado");
        }
        if (!in_array('email_sent_at', $cols)){
            $wpdb->query("ALTER TABLE $ts ADD COLUMN email_sent_at DATETIME NULL AFTER email_sent");
        }
    }
});

add_action('init', function(){
    if (class_exists('WPN_Mi_Informacion')){
        WPN_Mi_Informacion::init();
    }
});

// === WP Nomina: Export XLS por Quincena (non-destructive add) ===
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/export-quincena-xls.php';
    add_action('admin_menu', ['WP_Nomina_Export_Quincena_XLS', 'register_subpage']);
}

// Hook para autenticación personalizada
add_filter('authenticate', 'wpn_custom_authenticate', 30, 3);
function wpn_custom_authenticate($user, $username, $password) {
    if (is_wp_error($user)) {
        return $user;
    }
    
    if ($user === null) {
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        
        // Buscar empleado por RFC
        $emp = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $te WHERE UPPER(rfc) = UPPER(%s)",
            $username
        ));
        
        if ($emp && $emp->user_id) {
            $user_obj = get_user_by('id', $emp->user_id);
            
            if ($user_obj) {
                // Si tiene password personalizado, verificar contra el password de WordPress
                if ($emp->custom_password) {
                    if (wp_check_password($password, $user_obj->data->user_pass, $user_obj->ID)) {
                        return $user_obj;
                    }
                }
                // Si no tiene password personalizado, verificar contra CURP
                else if (strtoupper($password) === strtoupper($emp->curp)) {
                    return $user_obj;
                }
            }
        }
    }
    
    return $user;
}

// === Activación del plugin ===
register_activation_hook(__FILE__, function(){
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Tabla de empleados con nuevos campos
    $te = $wpdb->prefix.'nmn_employees';
    $sql = "CREATE TABLE IF NOT EXISTS $te (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        nombre VARCHAR(191) NOT NULL,
        apellido_paterno VARCHAR(191) NULL,
        apellido_materno VARCHAR(191) NULL,
        puesto VARCHAR(191) NULL,
        rfc VARCHAR(13) NOT NULL,
        curp VARCHAR(18) NOT NULL,
        email VARCHAR(191) NULL,
        fecha_ingreso DATE NOT NULL,
        ingreso_mensual DECIMAL(10,2) DEFAULT 0,
        bono_mensual DECIMAL(10,2) DEFAULT 0,
        esquema VARCHAR(50) DEFAULT 'Sindicato',
        banco VARCHAR(191) NULL,
        clabe VARCHAR(18) NULL,
        cuenta VARCHAR(30) NULL,
        custom_password TINYINT(1) DEFAULT 0,
        cp VARCHAR(10) NULL,
        regimen_sat VARCHAR(191) NULL,
        KEY idx_user_id (user_id),
        KEY idx_rfc (rfc)
    ) $charset_collate";
    dbDelta($sql);
    
    // Tabla de quincenas
    $tq = $wpdb->prefix.'nmn_quincenas';
    $sql = "CREATE TABLE IF NOT EXISTS $tq (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(191) NOT NULL,
        imss_monto DECIMAL(10,2) DEFAULT 0,
        con_bono TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate";
    dbDelta($sql);
    
    // Tabla de quincena_employees
    $tqe = $wpdb->prefix.'nmn_quincena_employees';
    $sql = "CREATE TABLE IF NOT EXISTS $tqe (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quincena_id INT NOT NULL,
        employee_id INT NOT NULL,
        quincena_base DECIMAL(10,2) DEFAULT 0,
        bono_total DECIMAL(10,2) DEFAULT 0,
        reembolso_total DECIMAL(10,2) DEFAULT 0,
        submitted TINYINT(1) DEFAULT 0,
        esquema VARCHAR(50) NULL,
        banco VARCHAR(191) NULL,
        clabe VARCHAR(18) NULL,
        cuenta VARCHAR(30) NULL,
        KEY idx_quincena (quincena_id),
        KEY idx_employee (employee_id)
    ) $charset_collate";
    dbDelta($sql);
    
    // Tabla de bonos
    $tb = $wpdb->prefix.'nmn_bonos';
    $sql = "CREATE TABLE IF NOT EXISTS $tb (
        id INT AUTO_INCREMENT PRIMARY KEY,
        qe_id INT NOT NULL,
        descripcion VARCHAR(255) NULL,
        monto DECIMAL(10,2) DEFAULT 0,
        KEY idx_qe (qe_id)
    ) $charset_collate";
    dbDelta($sql);
    
    // Tabla de reembolsos
    $tr = $wpdb->prefix.'nmn_reembolsos';
    $sql = "CREATE TABLE IF NOT EXISTS $tr (
        id INT AUTO_INCREMENT PRIMARY KEY,
        qe_id INT NOT NULL,
        descripcion VARCHAR(255) NULL,
        monto DECIMAL(10,2) DEFAULT 0,
        evidencia_url VARCHAR(500) NULL,
        KEY idx_qe (qe_id)
    ) $charset_collate";
    dbDelta($sql);
    
    // Tabla de vacaciones
    $tv = $wpdb->prefix.'nmn_vacaciones';
    $sql = "CREATE TABLE IF NOT EXISTS $tv (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        anio_laboral INT NOT NULL,
        dias_asignados INT DEFAULT 0,
        dias_usados INT DEFAULT 0,
        UNIQUE KEY emp_anio (employee_id, anio_laboral)
    ) $charset_collate";
    dbDelta($sql);
    
    // Tabla de solicitudes de vacaciones
    $ts = $wpdb->prefix.'nmn_vac_solicitudes';
    $sql = "CREATE TABLE IF NOT EXISTS $ts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        anio_laboral INT NOT NULL,
        fecha_inicio DATE NOT NULL,
        fecha_fin DATE NOT NULL,
        dias_habiles INT DEFAULT 0,
        comentario VARCHAR(255) NULL,
        estado VARCHAR(20) DEFAULT 'pendiente',
        email_sent TINYINT(1) DEFAULT 0,
        email_sent_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_employee (employee_id),
        KEY idx_estado (estado)
    ) $charset_collate";
    dbDelta($sql);
    
    // Tabla de permisos (NUEVA)
    $tp = $wpdb->prefix.'nmn_permisos';
    $sql = "CREATE TABLE IF NOT EXISTS $tp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        anio_laboral INT NOT NULL,
        fecha DATE NOT NULL,
        tipo VARCHAR(20) NOT NULL,
        hora TIME NOT NULL,
        horas_solicitadas DECIMAL(4,2) NOT NULL,
        motivo VARCHAR(255) NULL,
        estado VARCHAR(20) DEFAULT 'pendiente',
        email_sent TINYINT(1) DEFAULT 0,
        email_sent_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_employee (employee_id),
        KEY idx_estado (estado),
        KEY idx_anio (anio_laboral)
    ) $charset_collate";
    dbDelta($sql);
});
