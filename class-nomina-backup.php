<?php
if (!defined('ABSPATH')) exit;

class WPN_Backup {
    
    // Límite de tamaño de archivo: 50MB
    const MAX_UPLOAD_SIZE = 52428800; // 50 * 1024 * 1024 bytes
    
    // Versión mínima compatible
    const MIN_VERSION = '1.0.0';
    
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
        
        // Obtener estadísticas
        $te = $wpdb->prefix.'nmn_employees';
        $tq = $wpdb->prefix.'nmn_quincenas';
        $tqe = $wpdb->prefix.'nmn_quincena_employees';
        $tb = $wpdb->prefix.'nmn_bonos';
        $tr = $wpdb->prefix.'nmn_reembolsos';
        $tv = $wpdb->prefix.'nmn_vacaciones';
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';
        $tp = $wpdb->prefix.'nmn_permisos';
        
        $stats = [
            'empleados' => $wpdb->get_var("SELECT COUNT(*) FROM $te"),
            'quincenas' => $wpdb->get_var("SELECT COUNT(*) FROM $tq"),
            'registros_quincena' => $wpdb->get_var("SELECT COUNT(*) FROM $tqe"),
            'bonos' => $wpdb->get_var("SELECT COUNT(*) FROM $tb"),
            'reembolsos' => $wpdb->get_var("SELECT COUNT(*) FROM $tr"),
            'vacaciones' => $wpdb->get_var("SELECT COUNT(*) FROM $tv"),
            'solicitudes_vac' => $wpdb->get_var("SELECT COUNT(*) FROM $ts"),
            'permisos' => $wpdb->get_var("SELECT COUNT(*) FROM $tp"),
        ];
        
        // Calcular tamaño máximo de subida
        $max_upload_mb = round(self::MAX_UPLOAD_SIZE / 1024 / 1024, 0);
        
        ?>
        <div class="wrap wpn-wrap">
            <h1>Exportar / Importar Datos</h1>
            
            <?php if (isset($_GET['exported']) && $_GET['exported'] === '1'): ?>
                <div class="notice notice-success">
                    <p>✅ Datos exportados correctamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['imported']) && $_GET['imported'] === '1'): ?>
                <div class="notice notice-success">
                    <p>✅ Datos importados correctamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error">
                    <p>❌ Error: <?php echo esc_html($_GET['error']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="wpn-card">
                <h2>Estadísticas del Sistema</h2>
                <div class="grid-4">
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
                </div>
                <div class="grid-4" style="margin-top: 16px;">
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #42b72a;"><?php echo number_format($stats['reembolsos']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Reembolsos</div>
                    </div>
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #f0ad4e;"><?php echo number_format($stats['vacaciones']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Balances de Vacaciones</div>
                    </div>
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #f0ad4e;"><?php echo number_format($stats['solicitudes_vac']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Solicitudes de Vacaciones</div>
                    </div>
                    <div style="background: #f0f2f5; padding: 16px; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #f0ad4e;"><?php echo number_format($stats['permisos']); ?></div>
                        <div style="color: #65676b; margin-top: 4px;">Permisos</div>
                    </div>
                </div>
                <div style="margin-top: 16px; padding: 16px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #1877f2;">
                    <p style="margin: 0; color: #0c5460;">
                        <strong>ℹ️ Información:</strong> La exportación incluye todos los datos del sistema en formato JSON.
                        La importación reemplazará completamente los datos actuales.
                    </p>
                </div>
            </div>
            
            <div class="wpn-card">
                <h2>📥 Exportar Datos</h2>
                <p>Descarga un archivo JSON con todos los datos del sistema (colaboradores, quincenas, bonos, reembolsos, vacaciones, permisos).</p>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                    <h4 style="margin-top: 0;">📋 Datos incluidos en la exportación:</h4>
                    <ul style="margin: 8px 0; padding-left: 24px;">
                        <li>✅ Colaboradores (<?php echo number_format($stats['empleados']); ?>)</li>
                        <li>✅ Quincenas (<?php echo number_format($stats['quincenas']); ?>)</li>
                        <li>✅ Registros de nómina (<?php echo number_format($stats['registros_quincena']); ?>)</li>
                        <li>✅ Bonos (<?php echo number_format($stats['bonos']); ?>)</li>
                        <li>✅ Reembolsos (<?php echo number_format($stats['reembolsos']); ?>)</li>
                        <li>✅ Vacaciones (<?php echo number_format($stats['vacaciones']); ?>)</li>
                        <li>✅ Solicitudes de vacaciones (<?php echo number_format($stats['solicitudes_vac']); ?>)</li>
                        <li>✅ Permisos (<?php echo number_format($stats['permisos']); ?>)</li>
                    </ul>
                </div>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wpn_export_all'); ?>
                    <input type="hidden" name="action" value="wpn_export_all_data">
                    <button type="submit" class="button button-primary" style="background: #42b72a; border-color: #42b72a;">
                        📦 Descargar Exportación Completa
                    </button>
                </form>
            </div>
            
            <div class="wpn-card" style="background: #fff3cd; border-left: 4px solid #f0ad4e;">
                <h2 style="color: #856404;">📤 Importar Datos</h2>
                <p style="color: #856404;">
                    <strong>⚠️ ADVERTENCIA:</strong> La importación eliminará TODOS los datos actuales y los reemplazará con los del archivo.
                    Esta acción no se puede deshacer. Se recomienda hacer una exportación antes de importar.
                </p>
                
                <div style="background: white; padding: 16px; border-radius: 8px; margin: 16px 0;">
                    <h4 style="margin-top: 0;">✅ Validaciones de seguridad:</h4>
                    <ul style="margin: 8px 0; padding-left: 24px;">
                        <li>🔍 Verificación de formato JSON válido</li>
                        <li>📏 Límite máximo de archivo: <?php echo $max_upload_mb; ?>MB</li>
                        <li>🔢 Verificación de versión del plugin</li>
                        <li>📋 Validación de estructura de datos completa</li>
                        <li>🔐 Verificación de todas las tablas requeridas</li>
                        <li>🔄 Preservación de IDs originales</li>
                    </ul>
                </div>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" 
                      onsubmit="return confirm('⚠️ ATENCIÓN: Esta acción eliminará TODOS los datos actuales y los reemplazará con los del archivo.\n\n¿Estás seguro de que deseas continuar?\n\nEsta acción NO se puede deshacer.');">
                    <?php wp_nonce_field('wpn_import_all'); ?>
                    <input type="hidden" name="action" value="wpn_import_all_data">
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Selecciona el archivo JSON de respaldo:
                        </label>
                        <input type="file" name="import_file" accept=".json" required style="padding: 8px;">
                        <p style="color: #65676b; font-size: 13px; margin: 8px 0 0;">
                            Tamaño máximo: <?php echo $max_upload_mb; ?>MB | Versión mínima compatible: <?php echo self::MIN_VERSION; ?>
                        </p>
                    </div>
                    <button type="submit" class="button button-primary" style="background: #e4223d; border-color: #e4223d;">
                        ⚠️ Importar Datos (Reemplazar Todo)
                    </button>
                </form>
            </div>
            
            <div class="wpn-card" style="background: #f8f9fa;">
                <h3>📖 Instrucciones</h3>
                <ol style="line-height: 1.8;">
                    <li><strong>Exportar:</strong> Haz clic en el botón de exportación para descargar un archivo JSON con todos tus datos.</li>
                    <li><strong>Respaldo:</strong> Guarda el archivo en un lugar seguro como respaldo.</li>
                    <li><strong>Importar:</strong> Para restaurar datos, selecciona el archivo JSON y haz clic en importar.</li>
                    <li><strong>Migración:</strong> Puedes usar esta función para migrar datos entre diferentes instalaciones de WordPress.</li>
                    <li><strong>Versión:</strong> El archivo de respaldo incluye la versión del plugin. Solo se pueden importar archivos compatibles.</li>
                </ol>
                <p style="color: #65676b; font-size: 14px; margin-top: 16px;">
                    💡 <strong>Tip:</strong> Te recomendamos hacer exportaciones periódicas como respaldo de tu información.
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
        
        // Obtener todas las tablas (INCLUYE PERMISOS)
        $tables = [
            'employees' => $wpdb->prefix.'nmn_employees',
            'quincenas' => $wpdb->prefix.'nmn_quincenas',
            'quincena_employees' => $wpdb->prefix.'nmn_quincena_employees',
            'bonos' => $wpdb->prefix.'nmn_bonos',
            'reembolsos' => $wpdb->prefix.'nmn_reembolsos',
            'vacaciones' => $wpdb->prefix.'nmn_vacaciones',
            'vac_solicitudes' => $wpdb->prefix.'nmn_vac_solicitudes',
            'permisos' => $wpdb->prefix.'nmn_permisos', // ✅ AGREGADO: Tabla de permisos
        ];
        
        $data = [
            'version' => WPN_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'plugin_name' => 'WP Nómina y Vacaciones',
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
        
        // UTF-8 BOM para asegurar codificación correcta
        echo "\xEF\xBB\xBF";
        echo $json;
        exit;
    }
    
    public static function import_all_data(){
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }
        
        check_admin_referer('wpn_import_all');
        
        // ✅ VALIDACIÓN 1: Verificar que se subió un archivo
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Error al subir archivo')));
            exit;
        }
        
        // ✅ VALIDACIÓN 2: Verificar tamaño del archivo (máximo 50MB)
        if ($_FILES['import_file']['size'] > self::MAX_UPLOAD_SIZE) {
            $max_mb = round(self::MAX_UPLOAD_SIZE / 1024 / 1024, 0);
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode("El archivo excede el tamaño máximo permitido de {$max_mb}MB")));
            exit;
        }
        
        // ✅ VALIDACIÓN 3: Verificar extensión del archivo
        $file_ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'json') {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('El archivo debe ser formato JSON')));
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
        
        // ✅ VALIDACIÓN 4: Verificar que el JSON es válido
        if ($data === null) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Archivo JSON inválido o corrupto')));
            exit;
        }
        
        // ✅ VALIDACIÓN 5: Verificar estructura básica del JSON
        if (!isset($data['tables']) || !is_array($data['tables'])) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Estructura de datos inválida: falta sección "tables"')));
            exit;
        }
        
        // ✅ VALIDACIÓN 6: Verificar versión del plugin
        if (!isset($data['version'])) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('El archivo no contiene información de versión')));
            exit;
        }
        
        // Verificar versión mínima compatible
        if (version_compare($data['version'], self::MIN_VERSION, '<')) {
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Versión incompatible. Versión mínima requerida: ' . self::MIN_VERSION)));
            exit;
        }
        
        // ✅ VALIDACIÓN 7: Verificar que existen todas las tablas requeridas
        $required_tables = ['employees', 'quincenas', 'quincena_employees', 'bonos', 'reembolsos', 'vacaciones', 'vac_solicitudes', 'permisos'];
        foreach ($required_tables as $table_key) {
            if (!isset($data['tables'][$table_key])) {
                wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode("Falta tabla requerida: {$table_key}")));
                exit;
            }
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
            'permisos' => $wpdb->prefix.'nmn_permisos', // ✅ AGREGADO: Tabla de permisos
        ];
        
        // Iniciar transacción
        $wpdb->query('START TRANSACTION');
        
        try {
            // ✅ CAMBIO: Usar DELETE en lugar de TRUNCATE para preservar estructura
            // Limpiar tablas en orden inverso (respetando dependencias)
            $cleanup_order = ['permisos', 'vac_solicitudes', 'vacaciones', 'reembolsos', 'bonos', 'quincena_employees', 'quincenas', 'employees'];
            
            foreach ($cleanup_order as $key) {
                if (isset($tables[$key])) {
                    $table = $tables[$key];
                    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                    if ($exists) {
                        // Usar DELETE en lugar de TRUNCATE
                        $wpdb->query("DELETE FROM $table");
                        
                        // Log para debugging
                        error_log("WPN_Backup: Tabla {$table} limpiada con DELETE");
                    }
                }
            }
            
            // ✅ Importar datos en orden correcto, manteniendo IDs originales
            $import_order = ['employees', 'quincenas', 'quincena_employees', 'bonos', 'reembolsos', 'vacaciones', 'vac_solicitudes', 'permisos'];
            
            foreach ($import_order as $key) {
                if (isset($data['tables'][$key]) && !empty($data['tables'][$key])) {
                    $table = $tables[$key];
                    $rows = $data['tables'][$key];
                    
                    // Contador para estadísticas
                    $imported_count = 0;
                    
                    foreach ($rows as $row) {
                        // Insertar fila por fila manteniendo IDs originales
                        $result = $wpdb->insert($table, $row);
                        
                        if ($result === false) {
                            throw new Exception("Error al importar registro en tabla {$key}: " . $wpdb->last_error);
                        }
                        
                        $imported_count++;
                    }
                    
                    // Log para debugging
                    error_log("WPN_Backup: Importados {$imported_count} registros en tabla {$key}");
                }
            }
            
            // Confirmar transacción
            $wpdb->query('COMMIT');
            
            // Log de éxito
            error_log('WPN_Backup: Importación completada exitosamente');
            
            wp_redirect(admin_url('admin.php?page=wpn-backup&imported=1'));
            exit;
            
        } catch (Exception $e) {
            // Revertir en caso de error
            $wpdb->query('ROLLBACK');
            
            // Log del error
            error_log('WPN_Backup: Error en importación - ' . $e->getMessage());
            
            wp_redirect(admin_url('admin.php?page=wpn-backup&error=' . urlencode('Error al importar: ' . $e->getMessage())));
            exit;
        }
    }
}
