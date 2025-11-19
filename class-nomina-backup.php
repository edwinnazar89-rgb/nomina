<?php
if (!defined('ABSPATH')) exit;

class WPN_Backup {
    
    public static function init(){
        add_action('admin_menu', [__CLASS__, 'add_submenu'], 100);
        add_action('admin_post_wpn_export_all_data', [__CLASS__, 'export_all_data']);
        add_action('admin_post_wpn_import_all_data', [__CLASS__, 'import_all_data']);
    }
    
    public static function add_submenu(){
        if (!current_user_can('manage_options')) return;
        
        add_submenu_page(
            'wp-nomina',
            'Exportar/Importar Datos',
            'Exportar/Importar',
            'manage_options',
            'wpn-backup',
            [__CLASS__, 'render_page']
        );
    }
    
    public static function render_page(){
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        
        global $wpdb;
        
        // Obtener estadÃÆ’Ã†â€™Ãâ€šÃ‚Â­sticas
        $te = $wpdb->prefix.'nmn_employees';
        $tq = $wpdb->prefix.'nmn_quincenas';
        $tqe = $wpdb->prefix.'nmn_quincena_employees';
        $tb = $wpdb->prefix.'nmn_bonos';
        $tr = $wpdb->prefix.'nmn_reembolsos';
        $tv = $wpdb->prefix.'nmn_vacaciones';
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';
        
        $stats = [
            'empleados' => $wpdb->get_var("SELECT COUNT(*) FROM $te"),
            'quincenas' => $wpdb->get_var("SELECT COUNT(*) FROM $tq"),
            'registros_quincena' => $wpdb->get_var("SELECT COUNT(*) FROM $tqe"),
            'bonos' => $wpdb->get_var("SELECT COUNT(*) FROM $tb"),
            'reembolsos' => $wpdb->get_var("SELECT COUNT(*) FROM $tr"),
            'vacaciones' => $wpdb->get_var("SELECT COUNT(*) FROM $tv"),
            'solicitudes_vac' => $wpdb->get_var("SELECT COUNT(*) FROM $ts"),
        ];
        
        ?>
        <div class="wrap wpn-wrap">
            <h1>ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢Ãâ€šÃ‚Â¾ Exportar / Importar Datos</h1>
            
            <?php if (isset($_GET['exported']) && $_GET['exported'] === '1'): ?>
                <div class="notice notice-success">
                    <p>ÃÆ’Ã‚Â¢Ãâ€¦Ã¢â‚¬Å“ÃÂ¢Ã¢â€šÂ¬Ã‚Â¦ Datos exportados correctamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['imported']) && $_GET['imported'] === '1'): ?>
                <div class="notice notice-success">
                    <p>ÃÆ’Ã‚Â¢Ãâ€¦Ã¢â‚¬Å“ÃÂ¢Ã¢â€šÂ¬Ã‚Â¦ Datos importados correctamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error">
                    <p>ÃÆ’Ã‚Â¢Ãâ€šÃ‚ÂÃâ€¦Ã¢â‚¬â„¢ Error: <?php echo esc_html($_GET['error']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="wpn-card">
                <h2>ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃâ€¦Ã‚Â  EstadÃÆ’Ã†â€™Ãâ€šÃ‚Â­sticas del Sistema</h2>
                <div class="grid-3">
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #1877f2;"><?php echo number_format($stats['empleados']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Colaboradores</div>
                    </div>
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #1877f2;"><?php echo number_format($stats['quincenas']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Quincenas</div>
                    </div>
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #1877f2;"><?php echo number_format($stats['registros_quincena']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Registros de Quincena</div>
                    </div>
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #42b72a;"><?php echo number_format($stats['bonos']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Bonos</div>
                    </div>
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #42b72a;"><?php echo number_format($stats['reembolsos']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Reembolsos</div>
                    </div>
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #f0ad4e;"><?php echo number_format($stats['vacaciones']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Balances de Vacaciones</div>
                    </div>
                </div>
                <div style="margin-top: 16px; padding: 16px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #1877f2;">
                    <p style="margin: 0; color: #0c5460;">
                        <strong>ÃÆ’Ã‚Â¢ÃÂ¢Ã¢â€šÂ¬Ã…Â¾Ãâ€šÃ‚Â¹ÃÆ’Ã‚Â¯Ãâ€šÃ‚Â¸Ãâ€šÃ‚Â InformaciÃ³n:</strong> La exportaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n incluye todos los datos del sistema en formato JSON.
                        La importaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n reemplazarÃÆ’Ã†â€™Ãâ€šÃ‚Â¡ completamente los datos actuales.
                    </p>
                </div>
            </div>
            
            <div class="wpn-card">
                <h2>ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃÂ¢Ã¢â€šÂ¬Ã…Â¡ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃâ€šÃ‚Â¥ Exportar Datos</h2>
                <p>Descarga un archivo JSON con todos los datos del sistema (colaboradores, quincenas, bonos, reembolsos, vacaciones).</p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wpn_export_all'); ?>
                    <input type="hidden" name="action" value="wpn_export_all_data">
                    <button type="submit" class="button button-primary" style="background: #42b72a; border-color: #42b72a;">
                        ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃÂ¢Ã¢â€šÂ¬Ã…Â¡ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃâ€šÃ‚Â¥ Descargar ExportaciÃ³n Completa
                    </button>
                </form>
            </div>
            
            <div class="wpn-card" style="background: #fff3cd; border-left: 4px solid #f0ad4e;">
                <h2 style="color: #856404;">ÃÆ’Ã‚Â¢Ãâ€¦Ã‚Â¡Ãâ€šÃ‚Â ÃÆ’Ã‚Â¯Ãâ€šÃ‚Â¸Ãâ€šÃ‚Â Importar Datos</h2>
                <p style="color: #856404;">
                    <strong>ADVERTENCIA:</strong> La importaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n eliminarÃÆ’Ã†â€™Ãâ€šÃ‚Â¡ TODOS los datos actuales y los reemplazarÃÆ’Ã†â€™Ãâ€šÃ‚Â¡ con los del archivo.
                    Esta acciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n no se puede deshacer. Se recomienda hacer una exportaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n antes de importar.
                </p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" 
                      onsubmit="return confirm('ÃÆ’Ã‚Â¢Ãâ€¦Ã‚Â¡Ãâ€šÃ‚Â ÃÆ’Ã‚Â¯Ãâ€šÃ‚Â¸Ãâ€šÃ‚Â ATENCIÃÆ’Ã†â€™ÃÂ¢Ã¢â€šÂ¬Ã…â€œN: Esta acciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n eliminarÃÆ’Ã†â€™Ãâ€šÃ‚Â¡ TODOS los datos actuales y los reemplazarÃÆ’Ã†â€™Ãâ€šÃ‚Â¡ con los del archivo.\n\nÃÆ’Ã¢â‚¬Å¡Ãâ€šÃ‚Â¿EstÃÆ’Ã†â€™Ãâ€šÃ‚Â¡s seguro de que deseas continuar?\n\nEsta acciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n NO se puede deshacer.');">
                    <?php wp_nonce_field('wpn_import_all'); ?>
                    <input type="hidden" name="action" value="wpn_import_all_data">
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Selecciona el archivo JSON de respaldo:
                        </label>
                        <input type="file" name="import_file" accept=".json" required style="padding: 8px;">
                    </div>
                    <button type="submit" class="button button-primary" style="background: #e4223d; border-color: #e4223d;">
                        ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃÂ¢Ã¢â€šÂ¬Ã…Â¡ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃâ€šÃ‚Â¤ Importar Datos (Reemplazar Todo)
                    </button>
                </form>
            </div>
            
            <div class="wpn-card" style="background: #f8f9fa;">
                <h3>ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃÂ¢Ã¢â€šÂ¬Ã…Â¡ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃÂ¢Ã¢â€šÂ¬Ã‚Â¹ Instrucciones</h3>
                <ol style="line-height: 1.8;">
                    <li><strong>Exportar:</strong> Haz clic en el botÃÆ’Ã†â€™Ãâ€šÃ‚Â³n de exportaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n para descargar un archivo JSON con todos tus datos.</li>
                    <li><strong>Respaldo:</strong> Guarda el archivo en un lugar seguro como respaldo.</li>
                    <li><strong>Importar:</strong> Para restaurar datos, selecciona el archivo JSON y haz clic en importar.</li>
                    <li><strong>MigraciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n:</strong> Puedes usar esta funciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n para migrar datos entre diferentes instalaciones de WordPress.</li>
                </ol>
                <p style="color: #65676b; font-size: 14px; margin-top: 16px;">
                    ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢Ãâ€šÃ‚Â¡ <strong>Tip:</strong> Te recomendamos hacer exportaciones periÃÆ’Ã†â€™Ãâ€šÃ‚Â³dicas como respaldo de tu informaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n.
                </p>
            </div>
        </div>
        <?php
    }
    
    public static function export_all_data(){
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }
        
        check_admin_referer('wpn_export_all');
        
        global $wpdb;
        
        // Obtener todas las tablas
        $tables = [
            'employees' => $wpdb->prefix.'nmn_employees',
            'quincenas' => $wpdb->prefix.'nmn_quincenas',
            'quincena_employees' => $wpdb->prefix.'nmn_quincena_employees',
            'bonos' => $wpdb->prefix.'nmn_bonos',
            'reembolsos' => $wpdb->prefix.'nmn_reembolsos',
            'vacaciones' => $wpdb->prefix.'nmn_vacaciones',
            'vac_solicitudes' => $wpdb->prefix.'nmn_vac_solicitudes',
        ];
        
        $data = [
            'version' => WPN_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'tables' => []
        ];
        
        foreach ($tables as $key => $table) {
            // Verificar si la tabla existe
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists) {
                $data['tables'][$key] = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            } else {
                $data['tables'][$key] = [];
            }
        }
        
        // Generar JSON con UTF-8
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Error al generar JSON')));
            exit;
        }
        
        // Enviar archivo
        $filename = 'wp-nomina-backup-' . date('Y-m-d-His') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // UTF-8 BOM para asegurar codificaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n correcta
        echo "\xEF\xBB\xBF";
        echo $json;
        exit;
    }
    
    public static function import_all_data(){
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }
        
        check_admin_referer('wpn_import_all');
        
        // Verificar archivo
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Error al subir archivo')));
            exit;
        }
        
        // Leer archivo
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        
        if ($file_content === false) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Error al leer archivo')));
            exit;
        }
        
        // Decodificar JSON
        $data = json_decode($file_content, true);
        
        if ($data === null || !isset($data['tables'])) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Archivo JSON invÃÆ’Ã†â€™Ãâ€šÃ‚Â¡lido')));
            exit;
        }
        
        global $wpdb;
        
        // Mapeo de tablas
        $tables = [
            'employees' => $wpdb->prefix.'nmn_employees',
            'quincenas' => $wpdb->prefix.'nmn_quincenas',
            'quincena_employees' => $wpdb->prefix.'nmn_quincena_employees',
            'bonos' => $wpdb->prefix.'nmn_bonos',
            'reembolsos' => $wpdb->prefix.'nmn_reembolsos',
            'vacaciones' => $wpdb->prefix.'nmn_vacaciones',
            'vac_solicitudes' => $wpdb->prefix.'nmn_vac_solicitudes',
        ];
        
        // Iniciar transacciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n
        $wpdb->query('START TRANSACTION');
        
        try {
            // Limpiar tablas en orden inverso (respetando foreign keys)
            $cleanup_order = ['vac_solicitudes', 'vacaciones', 'reembolsos', 'bonos', 'quincena_employees', 'quincenas', 'employees'];
            
            foreach ($cleanup_order as $key) {
                if (isset($tables[$key])) {
                    $table = $tables[$key];
                    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                    if ($exists) {
                        $wpdb->query("TRUNCATE TABLE $table");
                    }
                }
            }
            
            // Importar datos en orden correcto
            $import_order = ['employees', 'quincenas', 'quincena_employees', 'bonos', 'reembolsos', 'vacaciones', 'vac_solicitudes'];
            
            foreach ($import_order as $key) {
                if (isset($data['tables'][$key]) && !empty($data['tables'][$key])) {
                    $table = $tables[$key];
                    $rows = $data['tables'][$key];
                    
                    foreach ($rows as $row) {
                        // Insertar fila por fila para mantener IDs
                        $wpdb->insert($table, $row);
                    }
                }
            }
            
            // Confirmar transacciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n
            $wpdb->query('COMMIT');
            
            wp_redirect(admin_url('admin.php?page=wpn-backup&imported=1'));
            exit;
            
        } catch (Exception $e) {
            // Revertir en caso de error
            $wpdb->query('ROLLBACK');
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Error al importar: ' . $e->getMessage())));
            exit;
        }
    }
}
