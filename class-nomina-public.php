<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPN_Public {
    public static function mi_info_url(){
        $base = home_url(add_query_arg([], remove_query_arg(['tab','wpn_info'])));
        $url = add_query_arg(['tab'=>'info','wpn_info'=>1], $base);
        return esc_url($url);
    }

    public static function init_hooks(){
        add_action('admin_post_wpn_public_bono', [__CLASS__, 'handle_bono']);
        add_action('admin_post_nopriv_wpn_public_bono', [__CLASS__, 'handle_bono']);
        add_action('admin_post_wpn_public_reembolso', [__CLASS__, 'handle_reembolso']);
        add_action('admin_post_nopriv_wpn_public_reembolso', [__CLASS__, 'handle_reembolso']);
        add_action('admin_post_wpn_public_vac', [__CLASS__, 'handle_vac']);
        add_action('admin_post_nopriv_wpn_public_vac', [__CLASS__, 'handle_vac']);
        add_action('admin_post_wpn_public_permiso', [__CLASS__, 'handle_permiso']);
        add_action('admin_post_nopriv_wpn_public_permiso', [__CLASS__, 'handle_permiso']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    public static function enqueue_styles(){
        if (is_page() && has_shortcode(get_post()->post_content, 'nomina_portal')) {
            wp_enqueue_style('wpn-public-enhanced', WPN_URL . 'assets/css/public-enhanced.css', [], WPN_VERSION);
        }
    }

    // -------- Handlers admin-post --------

    public static function handle_bono(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') wp_die('Método no permitido');
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'wpn_public')) wp_die('Nonce inválido');
        $redirect = isset($_POST['_redirect']) ? esc_url_raw($_POST['_redirect']) : home_url('/');
        $qid = intval($_POST['qid'] ?? 0);

        $user = wp_get_current_user();
        if (!$user || !$user->ID){
            wp_safe_redirect($redirect); exit;
        }

        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $tqe= $wpdb->prefix.'nmn_quincena_employees';
        $tb = $wpdb->prefix.'nmn_bonos';
        $tr = $wpdb->prefix.'nmn_reembolsos';
        $tq = $wpdb->prefix.'nmn_quincenas';

        // Map employee
        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE user_id=%d", $user->ID));
        
        if (!$emp){
            $emp_by_rfc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(rfc)=UPPER(%s)", $user->user_login));
            if ($emp_by_rfc){
                $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp_by_rfc->id]);
                $emp = $emp_by_rfc;
            }
        }
        if (!$emp){ wp_safe_redirect($redirect); exit; }

        // Load QE row
        $qe = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tqe WHERE quincena_id=%d AND employee_id=%d", $qid, $emp->id));
        if (!$qe){ wp_safe_redirect($redirect); exit; }

        $action = sanitize_text_field($_POST['wpn_public_action'] ?? '');

        if ($action === 'add_bono'){
            $monto = WPN_Utils::sanitize_money($_POST['monto'] ?? 0);
            $tipo  = sanitize_text_field($_POST['tipo'] ?? 'positivo');
            if ($tipo === 'negativo') { $monto = -abs($monto); }
            $desc  = sanitize_text_field($_POST['descripcion'] ?? '');
            $wpdb->insert($tb, ['qe_id'=>$qe->id,'descripcion'=>$desc,'monto'=>$monto]);
            
            // Enviar notificación
            $quincena = $wpdb->get_row($wpdb->prepare("SELECT label FROM $tq WHERE id=%d", $qid));
            $quincena_label = $quincena ? $quincena->label : "Quincena #$qid";
            if (class_exists('WPN_Email')) {
                WPN_Email::notify_bono_reembolso($emp, 'bono', $monto, $desc, $quincena_label);
            }
        } elseif ($action === 'update_bono'){
            $bid = intval($_POST['bono_id'] ?? 0);
            $owner = $wpdb->get_var($wpdb->prepare("SELECT qe_id FROM $tb WHERE id=%d", $bid));
            if (intval($owner) === intval($qe->id)){
                $monto = WPN_Utils::sanitize_money($_POST['monto'] ?? 0);
                $tipo  = sanitize_text_field($_POST['tipo'] ?? 'positivo');
                if ($tipo === 'negativo') { $monto = -abs($monto); }
                $desc  = sanitize_text_field($_POST['descripcion'] ?? '');
                $wpdb->update($tb, ['monto'=>$monto,'descripcion'=>$desc], ['id'=>$bid]);
            }
        } elseif ($action === 'delete_bono'){
            $bid = intval($_POST['bono_id'] ?? 0);
            $owner = $wpdb->get_var($wpdb->prepare("SELECT qe_id FROM $tb WHERE id=%d", $bid));
            if (intval($owner) === intval($qe->id)){
                $wpdb->delete($tb, ['id'=>$bid]);
            }
        }

        // Recalc totals
        $bon = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto),0) FROM $tb WHERE qe_id=%d",$qe->id)));
        $reb = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto),0) FROM $tr WHERE qe_id=%d",$qe->id)));
        $wpdb->update($tqe, ['bono_total'=>$bon,'reembolso_total'=>$reb,'submitted'=>1], ['id'=>$qe->id]);

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_reembolso(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') wp_die('Método no permitido');
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'wpn_public')) wp_die('Nonce inválido');
        $redirect = isset($_POST['_redirect']) ? esc_url_raw($_POST['_redirect']) : home_url('/');
        $qid = intval($_POST['qid'] ?? 0);

        $user = wp_get_current_user();
        if (!$user || !$user->ID){ wp_safe_redirect($redirect); exit; }

        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $tqe= $wpdb->prefix.'nmn_quincena_employees';
        $tb = $wpdb->prefix.'nmn_bonos';
        $tr = $wpdb->prefix.'nmn_reembolsos';
        $tq = $wpdb->prefix.'nmn_quincenas';

        // Map employee
        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE user_id=%d", $user->ID));
        if (!$emp){
            $emp_by_rfc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(rfc)=UPPER(%s)", $user->user_login));
            if ($emp_by_rfc){
                $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp_by_rfc->id]);
                $emp = $emp_by_rfc;
            }
        }
        if (!$emp){ wp_safe_redirect($redirect); exit; }

        // Load QE row
        $qe = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tqe WHERE quincena_id=%d AND employee_id=%d", $qid, $emp->id));
        if (!$qe){ wp_safe_redirect($redirect); exit; }

        $action = sanitize_text_field($_POST['wpn_public_action'] ?? '');

        if ($action === 'add_reembolso'){
            $monto = WPN_Utils::sanitize_money($_POST['monto'] ?? 0);
            $desc  = sanitize_text_field($_POST['descripcion'] ?? '');
            $url = '';
            if (!empty($_FILES['evidencia']['name'])){
                require_once ABSPATH.'wp-admin/includes/file.php';
                $overrides = array('test_form'=>false);
                $file = wp_handle_upload($_FILES['evidencia'], $overrides);
                if (!isset($file['error'])) $url = $file['url'];
            }
            $wpdb->insert($tr, ['qe_id'=>$qe->id,'descripcion'=>$desc,'monto'=>$monto,'evidencia_url'=>$url]);
            
            // Enviar notificación
            $quincena = $wpdb->get_row($wpdb->prepare("SELECT label FROM $tq WHERE id=%d", $qid));
            $quincena_label = $quincena ? $quincena->label : "Quincena #$qid";
            if (class_exists('WPN_Email')) {
                WPN_Email::notify_bono_reembolso($emp, 'reembolso', $monto, $desc, $quincena_label);
            }
        } elseif ($action === 'update_reembolso'){
            $rid = intval($_POST['reembolso_id'] ?? 0);
            $owner = $wpdb->get_var($wpdb->prepare("SELECT qe_id FROM $tr WHERE id=%d", $rid));
            if (intval($owner) === intval($qe->id)){
                $monto = WPN_Utils::sanitize_money($_POST['monto'] ?? 0);
                $desc  = sanitize_text_field($_POST['descripcion'] ?? '');
                $data = ['monto'=>$monto,'descripcion'=>$desc];
                if (!empty($_FILES['evidencia']['name'])){
                    require_once ABSPATH.'wp-admin/includes/file.php';
                    $overrides = array('test_form'=>false);
                    $file = wp_handle_upload($_FILES['evidencia'], $overrides);
                    if (!isset($file['error'])) $data['evidencia_url'] = $file['url'];
                }
                $wpdb->update($tr, $data, ['id'=>$rid]);
            }
        } elseif ($action === 'delete_reembolso'){
            $rid = intval($_POST['reembolso_id'] ?? 0);
            $owner = $wpdb->get_var($wpdb->prepare("SELECT qe_id FROM $tr WHERE id=%d", $rid));
            if (intval($owner) === intval($qe->id)){
                $wpdb->delete($tr, ['id'=>$rid]);
            }
        }

        // Recalc totals
        $bon = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto),0) FROM $tb WHERE qe_id=%d",$qe->id)));
        $reb = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto),0) FROM $tr WHERE qe_id=%d",$qe->id)));
        $wpdb->update($tqe, ['bono_total'=>$bon,'reembolso_total'=>$reb,'submitted'=>1], ['id'=>$qe->id]);

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_vac(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') wp_die('Método no permitido');
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'wpn_public')) wp_die('Nonce inválido');
        $redirect = isset($_POST['_redirect']) ? esc_url_raw($_POST['_redirect']) : home_url('/');
        $anio = intval($_POST['anio_laboral'] ?? 0);
        $fi = sanitize_text_field($_POST['fecha_inicio'] ?? '');
        $ff = sanitize_text_field($_POST['fecha_fin'] ?? '');
        $coment = sanitize_text_field($_POST['comentario'] ?? '');

        $user = wp_get_current_user();
        if (!$user || !$user->ID){ wp_safe_redirect($redirect); exit; }

        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';

        // Ensure table exists
        $wpdb->query("CREATE TABLE IF NOT EXISTS $ts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            anio_laboral INT NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            dias_habiles INT NOT NULL DEFAULT 0,
            comentario VARCHAR(255) NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Map employee
        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE user_id=%d", $user->ID));
        if (!$emp){
            $emp_by_rfc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(rfc)=UPPER(%s)", $user->user_login));
            if ($emp_by_rfc){
                $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp_by_rfc->id]);
                $emp = $emp_by_rfc;
            }
        }
        if (!$emp){ wp_safe_redirect($redirect); exit; }

        // Calc business days
        $dias = WPN_Utils::days_between_business($fi, $ff);

        // Insert request
        $wpdb->insert($ts, [
            'employee_id'=>$emp->id,
            'anio_laboral'=>$anio,
            'fecha_inicio'=>$fi,
            'fecha_fin'=>$ff,
            'dias_habiles'=>$dias,
            'comentario'=>$coment,
            'estado'=>'pendiente'
        ]);
        
        // Enviar notificaciones
        if (class_exists('WPN_Email')) {
            WPN_Email::notify_vacation_request($emp, $anio, $fi, $ff, $dias, $coment);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_permiso(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') wp_die('Método no permitido');
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'wpn_public')) wp_die('Nonce inválido');
        $redirect = isset($_POST['_redirect']) ? esc_url_raw($_POST['_redirect']) : home_url('/');
        
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $tipo = sanitize_text_field($_POST['tipo'] ?? '');
        $hora = sanitize_text_field($_POST['hora'] ?? '');
        $horas_solicitadas = floatval($_POST['horas_solicitadas'] ?? 0);
        $motivo = sanitize_text_field($_POST['motivo'] ?? '');
        
        $user = wp_get_current_user();
        if (!$user || !$user->ID){ wp_safe_redirect($redirect); exit; }
        
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $tp = $wpdb->prefix.'nmn_permisos';
        
        // Ensure table exists
        $wpdb->query("CREATE TABLE IF NOT EXISTS $tp (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Map employee
        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE user_id=%d", $user->ID));
        if (!$emp){
            $emp_by_rfc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(rfc)=UPPER(%s)", $user->user_login));
            if ($emp_by_rfc){
                $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp_by_rfc->id]);
                $emp = $emp_by_rfc;
            }
        }
        if (!$emp){ wp_safe_redirect($redirect); exit; }
        
        // Calcular año laboral actual
        if (class_exists('WPN_Permisos')) {
            $anio_laboral = WPN_Permisos::get_anio_laboral_actual($emp->fecha_ingreso);
        } else {
            $start = new DateTime($emp->fecha_ingreso);
            $now = new DateTime(current_time('Y-m-d'));
            $diff = $start->diff($now);
            $anio_laboral = $diff->y + 1;
        }
        
        // Verificar límites
        if ($horas_solicitadas > 4) {
            wp_safe_redirect(add_query_arg('error', 'max_horas', $redirect));
            exit;
        }
        
        $usados = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tp WHERE employee_id=%d AND anio_laboral=%d AND estado='aprobado'",
            $emp->id,
            $anio_laboral
        )));
        
        if ($usados >= 6) {
            wp_safe_redirect(add_query_arg('error', 'max_permisos', $redirect));
            exit;
        }
        
        // Insert request
        $wpdb->insert($tp, [
            'employee_id'=>$emp->id,
            'anio_laboral'=>$anio_laboral,
            'fecha'=>$fecha,
            'tipo'=>$tipo,
            'hora'=>$hora,
            'horas_solicitadas'=>$horas_solicitadas,
            'motivo'=>$motivo,
            'estado'=>'pendiente'
        ]);
        
        $permiso_id = $wpdb->insert_id;
        $permiso = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tp WHERE id=%d", $permiso_id));
        
        // Enviar notificaciones
        if (class_exists('WPN_Email') && $permiso) {
            WPN_Email::notify_permiso_request($emp, $permiso);
        }
        
        wp_safe_redirect($redirect);
        exit;
    }

    // -------- Shortcode --------

    public static function render_portal(){
        // Early route: Mi información
        if ((isset($_GET['wpn_info']) && $_GET['wpn_info']) || (isset($_GET['tab']) && $_GET['tab']==='info')){
            if (class_exists('WPN_Mi_Informacion')){
                return WPN_Mi_Informacion::render_form();
            }
        }

        ob_start();
        if (!is_user_logged_in()){
            ?>
            <div class="wpn-portal">
                <div class="wpn-login-container">
                    <div class="wpn-login-card">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <span style="font-size: 48px;">👤</span>
                        </div>
                        <h2>Portal del Colaborador</h2>
                        <p class="subtitle">Ingresa con tus credenciales corporativas</p>
                        
                        <?php
                        if (isset($_GET['login']) && $_GET['login']==='failed'){
                            echo '<div class="notice notice-error">
                                <span class="icon-emoji">⚠️</span> Usuario o contraseña incorrectos.<br>
                                <small>Recuerda: Usuario = RFC en MAYÚSCULAS, Contraseña = CURP en MAYÚSCULAS.</small>
                            </div>';
                        }
                        ?>
                        
                        <form name="loginform" id="loginform" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
                            <div class="wpn-form-group">
                                <label for="user_login">
                                    <span class="icon-emoji">👤</span> RFC (Usuario)
                                </label>
                                <input type="text" name="log" id="user_login" class="input" value="" size="20" autocapitalize="off" placeholder="Ej: ABCD123456XYZ" required>
                            </div>
                            
                            <div class="wpn-form-group">
                                <label for="user_pass">
                                    <span class="icon-emoji">🔒</span> CURP (Contraseña)
                                </label>
                                <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" placeholder="Tu CURP en mayúsculas" required>
                            </div>
                            
                            <div class="wpn-form-group">
                                <button type="submit" name="wp-submit" id="wp-submit" class="wpn-btn-primary" style="width: 100%;">
                                    <span class="icon-emoji">🚀</span> Iniciar Sesión
                                </button>
                                <input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink()); ?>">
                            </div>
                        </form>
                        
                        <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #dadde1; text-align: center;">
                            <p style="color: #65676b; font-size: 14px; margin: 0;">
                                <span class="icon-emoji">💡</span> ¿Problemas para acceder? Contacta a Recursos Humanos
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        $user = wp_get_current_user();
        global $wpdb;
        $te  = $wpdb->prefix.'nmn_employees';

        // Find employee by user_id, fallback RFC
        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE user_id=%d", $user->ID));
        if (!$emp){
            $emp_by_rfc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(rfc)=UPPER(%s)", $user->user_login));
            if ($emp_by_rfc){
                $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp_by_rfc->id]);
                $emp = $emp_by_rfc;
            }
        }
        if (!$emp){
            echo '<div class="wpn-portal"><div class="wpn-card"><p><span class="icon-emoji">⚠️</span> No se encontró tu perfil de colaborador. Verifica que tu RFC esté dado de alta en Colaboradores o usa "Sincronizar usuarios".</p></div></div>';
            return ob_get_clean();
        }

        // Construir nombre completo
        $nombre_completo = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
        $col_name = $nombre_completo ?: $user->display_name;
        
        // Verificar si estamos en una sección específica
        $sec = isset($_GET['sec']) ? sanitize_text_field($_GET['sec']) : 'home';
        
        ?>
        <div class="wpn-portal">
        <?php
        // Header bar
        $logout_url = wp_logout_url( 'https://www.nueveymedia.com' );
        ?>
        <div class="wpn-portal-header">
            <div class="left">
                <span class="icon-emoji">👋</span> Bienvenido <strong><?php echo esc_html($col_name); ?></strong>
            </div>
            <div class="right">
                <a class="wpn-logout" href="<?php echo esc_url($logout_url); ?>">
                    <span class="icon-emoji">🚪</span> Cerrar sesión
                </a>
            </div>
        </div>

        <?php if ($sec === 'home'): ?>
            <!-- Menú Principal -->
            <div class="wpn-main-menu">
                <h2 style="text-align:center; color: #1877f2; margin-bottom: 32px;">¿Qué deseas hacer hoy?</h2>
                <div class="wpn-menu-grid">
                    <a href="<?php echo esc_url(add_query_arg(['sec'=>'pagos'])); ?>" class="wpn-menu-card">
                        <div class="wpn-menu-icon">💰</div>
                        <h3>Pagos</h3>
                        <p>Gestiona bonos, reembolsos y consulta tu nómina</p>
                    </a>
                    
                    <a href="<?php echo esc_url(add_query_arg(['sec'=>'vacaciones'])); ?>" class="wpn-menu-card">
                        <div class="wpn-menu-icon">🏖️</div>
                        <h3>Vacaciones</h3>
                        <p>Solicita vacaciones y consulta tu saldo disponible</p>
                    </a>
                    
                    <a href="<?php echo esc_url(add_query_arg(['wpn_info'=>1])); ?>" class="wpn-menu-card">
                        <div class="wpn-menu-icon">👤</div>
                        <h3>Mi Información</h3>
                        <p>Actualiza tus datos personales y bancarios</p>
                    </a>
                    
                    <a href="<?php echo esc_url(add_query_arg(['sec'=>'permisos'])); ?>" class="wpn-menu-card">
                        <div class="wpn-menu-icon">⏰</div>
                        <h3>Permisos</h3>
                        <p>Solicita permisos de entrada o salida y consulta tu saldo</p>
                    </a>
                </div>
            </div>
        
        <?php elseif ($sec === 'pagos'): ?>
            <!-- Sección de Pagos -->
            <?php echo self::render_pagos_section($emp); ?>
            
        <?php elseif ($sec === 'vacaciones'): ?>
            <!-- Sección de Vacaciones -->
            <?php echo self::render_vacaciones_section($emp); ?>
            
            
        <?php elseif ($sec === 'permisos'): ?>
            <!-- Sección de Permisos -->
            <?php echo self::render_permisos_section($emp); ?>
        <?php endif; ?>
        
        </div>
        <?php
        return ob_get_clean();
    }
    
    private static function render_pagos_section($emp) {
        global $wpdb;
        $tq  = $wpdb->prefix.'nmn_quincenas';
        $tqe = $wpdb->prefix.'nmn_quincena_employees';
        $tb  = $wpdb->prefix.'nmn_bonos';
        $tr  = $wpdb->prefix.'nmn_reembolsos';
        
        // Quincena selector
        $rows_raw = $wpdb->get_results("SELECT * FROM $tq ORDER BY id DESC");
        $quincenas = [];
        if ($rows_raw){
            foreach($rows_raw as $r){
                $label = '';
                if (isset($r->etiqueta)) $label = $r->etiqueta;
                elseif (isset($r->label)) $label = $r->label;
                elseif (isset($r->nombre)) $label = $r->nombre;
                elseif (isset($r->titulo)) $label = $r->titulo;
                else $label = 'Quincena #'.$r->id;
                $quincenas[] = (object)['id'=>intval($r->id), 'etiqueta'=>$label];
            }
        }
        $selected  = isset($_GET['qid']) ? intval($_GET['qid']) : (count($quincenas) ? intval($quincenas[0]->id) : 0);
        
        if ($selected){
            // Ensure QE row exists
            $qe = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tqe WHERE quincena_id=%d AND employee_id=%d", $selected, $emp->id));
            if (!$qe){
                $wpdb->insert($tqe, ['quincena_id'=>$selected,'employee_id'=>$emp->id,'bono_total'=>0,'reembolso_total'=>0,'submitted'=>0]);
                $qe = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tqe WHERE quincena_id=%d AND employee_id=%d", $selected, $emp->id));
            }
        } else {
            $qe = null;
        }

        // Basic amounts
        $ingreso_mensual = floatval($emp->ingreso_mensual);
        $quincena_base   = round($ingreso_mensual / 2, 2);

        // Current sums
        $bono_total = $qe ? floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto),0) FROM $tb WHERE qe_id=%d", $qe->id))) : 0;
        $reembolso_total = $qe ? floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto),0) FROM $tr WHERE qe_id=%d", $qe->id))) : 0;

        // FIX bono mensual visible en total cuando la quincena aplica bono
        $bono_mensual_extra = 0;
        if ($qe){
            $has_bono_mensual = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $tb WHERE qe_id=%d AND descripcion=%s",
                $qe->id, 'Bono mensual'
            )));
            if ($has_bono_mensual === 0 && isset($emp->bono_mensual) && floatval($emp->bono_mensual) > 0){
                $bono_mensual_extra = floatval($emp->bono_mensual);
            }
        }

        // Get IMSS configured for quincena
        $imss_monto = 0;
        $con_bono = 0;
        if ($selected){
            $row_q = $wpdb->get_row($wpdb->prepare("SELECT imss_monto, con_bono FROM $tq WHERE id=%d", $selected));
            if ($row_q){ $imss_monto = floatval($row_q->imss_monto); $con_bono = intval($row_q->con_bono ?? 0); }
        }
        
        ob_start();
        ?>
        <div class="wpn-back-button">
            <a href="<?php echo esc_url(remove_query_arg(['sec','qid','tab'])); ?>" class="wpn-btn-secondary">
                <span class="icon-emoji">◀️</span> Regresar al menú principal
            </a>
        </div>
        
        <div class="wpn-card">
            <h3><span class="icon-emoji">📅</span> Selecciona quincena</h3>
            <?php if (empty($quincenas)): ?>
                <p><span class="icon-emoji">⚠️</span> No hay quincenas creadas aún. Pide a RH que cree una en <strong>Administración → Nómina → Quincenas</strong>.</p>
            <?php else: ?>
                <form method="get">
                    <input type="hidden" name="sec" value="pagos">
                    <div class="wpn-form-group">
                        <select name="qid" onchange="this.form.submit()" style="max-width: 300px;">
                            <?php foreach($quincenas as $q): ?>
                                <option value="<?php echo (int)$q->id; ?>" <?php selected($selected, $q->id); ?>><?php echo esc_html($q->etiqueta); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($selected && $qe): ?>
        <?php $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'bonos'; ?>
        <div class="wpn-card">
            <h2><span class="icon-emoji">💵</span> Pagos</h2>
            <div class="wpn-tabs">
                <button class="wpn-tab" data-tab="bonos">Bonos</button>
                <button class="wpn-tab" data-tab="reembolsos">Reembolsos</button>
                <button class="wpn-tab" data-tab="total">Total</button>
            </div>

            <div class="wpn-tabpanel" id="tab-bonos">
                <h3><span class="icon-emoji">💰</span> Agregar bono</h3>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wpn_public'); ?>
                    <input type="hidden" name="action" value="wpn_public_bono">
                    <input type="hidden" name="wpn_public_action" value="add_bono">
                    <input type="hidden" name="qid" value="<?php echo (int)$selected; ?>">
                    <input type="hidden" name="_redirect" value="<?php echo esc_url(add_query_arg(['sec'=>'pagos','qid'=>$selected,'tab'=>'bonos'])); ?>">
                    <div class="grid-4 wpn-form-row">
                        <div class="wpn-form-group">
                            <label>Monto</label>
                            <input type="number" step="0.01" name="monto" required>
                        </div>
                        <div class="wpn-form-group">
                            <label>Tipo</label>
                            <select name="tipo">
                                <option value="negativo">Deducción de bono (−)</option>
                                <option value="positivo">Bono (+)</option>
                            </select>
                        </div>
                        <div class="wpn-form-group">
                            <label>Comentarios</label>
                            <input type="text" name="descripcion" placeholder="Detalle o comentario">
                        </div>
                        <div class="wpn-form-group">
                            <label>&nbsp;</label>
                            <button class="wpn-btn-primary" type="submit">Agregar</button>
                        </div>
                    </div>
                </form>

                <h3><span class="icon-emoji">📋</span> Bonos capturados</h3>
                <table class="wpn-table">
                    <thead><tr><th>Comentarios</th><th>Monto</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php
                        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tb WHERE qe_id=%d ORDER BY id DESC",$qe->id));
                        foreach($rows as $r){
                            $admin_post_url = admin_url('admin-post.php');
                            $redirect_bonos = esc_url(add_query_arg(['sec'=>'pagos','qid'=>$selected,'tab'=>'bonos'])); $redirect_bonos .= '#bonos';
                            echo '<tr>';
                            echo '<td>'.esc_html($r->descripcion).'</td>';
                            echo '<td>' . ($r->monto<0?'−':'+') . ' $'.number_format(abs($r->monto),2).'</td>';
                            echo '<td style="white-space:nowrap">';
                            // Delete form
                            echo '<form method="post" action="'.$admin_post_url.'" style="display:inline-block; margin-right:6px;">'
                                .wp_nonce_field('wpn_public','_wpnonce',false,false)
                                .'<input type="hidden" name="action" value="wpn_public_bono">'
                                .'<input type="hidden" name="wpn_public_action" value="delete_bono">'
                                .'<input type="hidden" name="qid" value="'.intval($selected).'">'
                                .'<input type="hidden" name="_redirect" value="'.$redirect_bonos.'">'
                                .'<input type="hidden" name="bono_id" value="'.intval($r->id).'">'
                                .'<button type="submit" class="wpn-btn-danger" onclick="return confirm(\'¿Eliminar bono?\')">Eliminar</button>'
                            .'</form>';
                            // Edit form
                            echo '<details style="display:inline-block; margin-left:6px"><summary>Editar</summary>';
                            echo '<form method="post" action="'.$admin_post_url.'" style="margin-top:6px;">'
                                    .wp_nonce_field('wpn_public','_wpnonce',false,false)
                                    .'<input type="hidden" name="action" value="wpn_public_bono">'
                                    .'<input type="hidden" name="wpn_public_action" value="update_bono">'
                                    .'<input type="hidden" name="qid" value="'.intval($selected).'">'
                                    .'<input type="hidden" name="_redirect" value="'.$redirect_bonos.'">'
                                    .'<input type="hidden" name="bono_id" value="'.intval($r->id).'">'
                                    .'<div class="grid-4 wpn-form-row">'
                                        .'<div class="wpn-form-group"><label>Monto</label><input type="number" step="0.01" name="monto" value="'.esc_attr(number_format(abs($r->monto),2,'.','')).'" required></div>'
                                        .'<div class="wpn-form-group"><label>Tipo</label><select name="tipo">'
                                            .($r->monto<0?'<option value="negativo" selected>Deducción de bono (−)</option><option value="positivo">Bono (+)</option>':'<option value="negativo">Deducción de bono (−)</option><option value="positivo" selected>Bono (+)</option>')
                                            .'</select></div>'
                                        .'<div class="wpn-form-group"><label>Comentarios</label><input type="text" name="descripcion" value="'.esc_attr($r->descripcion).'"></div>'
                                        .'<div class="wpn-form-group"><label>&nbsp;</label><button class="wpn-btn-success" type="submit">Guardar</button></div>'
                                    .'</div>'
                                .'</form>';
                            echo '</details>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        if (empty($rows)) {
                            echo '<tr><td colspan="3" style="text-align:center; color: #65676b;">No hay bonos capturados</td></tr>';
                        }
                    ?>
                    </tbody>
                </table>
            </div>

            <div class="wpn-tabpanel" id="tab-reembolsos" style="display:none;">
                <h3><span class="icon-emoji">💸</span> Agregar reembolso</h3>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('wpn_public'); ?>
                    <input type="hidden" name="action" value="wpn_public_reembolso">
                    <input type="hidden" name="wpn_public_action" value="add_reembolso">
                    <input type="hidden" name="qid" value="<?php echo (int)$selected; ?>">
                    <input type="hidden" name="_redirect" value="<?php echo esc_url(add_query_arg(['sec'=>'pagos','qid'=>$selected,'tab'=>'reembolsos'])); ?>">
                    <div class="grid-4 wpn-form-row">
                        <div class="wpn-form-group">
                            <label>Monto</label>
                            <input type="number" step="0.01" name="monto" required>
                        </div>
                        <div class="wpn-form-group">
                            <label>Descripción</label>
                            <input type="text" name="descripcion">
                        </div>
                        <div class="wpn-form-group">
                            <label>Evidencia</label>
                            <input type="file" name="evidencia" accept="image/*,application/pdf">
                        </div>
                        <div class="wpn-form-group">
                            <label>&nbsp;</label>
                            <button class="wpn-btn-primary" type="submit">Agregar</button>
                        </div>
                    </div>
                </form>

                <h3><span class="icon-emoji">📋</span> Reembolsos capturados</h3>
                <table class="wpn-table">
                    <thead><tr><th>Descripción</th><th>Monto</th><th>Evidencia</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php
                        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tr WHERE qe_id=%d ORDER BY id DESC",$qe->id));
                        foreach($rows as $r){
                            $admin_post_url = admin_url('admin-post.php');
                            $redirect_reembolsos = esc_url(add_query_arg(['sec'=>'pagos','qid'=>$selected,'tab'=>'reembolsos'])); $redirect_reembolsos .= '#reembolsos';
                            echo '<tr>';
                            echo '<td>'.esc_html($r->descripcion).'</td>';
                            echo '<td>$'.number_format($r->monto,2).'</td>';
                            echo '<td>';
                            if (!empty($r->evidencia_url)) echo '<a target="_blank" href="'.esc_url($r->evidencia_url).'">Ver 📎</a>';
                            echo '</td>';
                            echo '<td style="white-space:nowrap">';
                            // Delete
                            echo '<form method="post" action="'.$admin_post_url.'" style="display:inline-block; margin-right:6px;">'
                                .wp_nonce_field('wpn_public','_wpnonce',false,false)
                                .'<input type="hidden" name="action" value="wpn_public_reembolso">'
                                .'<input type="hidden" name="wpn_public_action" value="delete_reembolso">'
                                .'<input type="hidden" name="qid" value="'.intval($selected).'">'
                                .'<input type="hidden" name="_redirect" value="'.$redirect_reembolsos.'">'
                                .'<input type="hidden" name="reembolso_id" value="'.intval($r->id).'">'
                                .'<button type="submit" class="wpn-btn-danger" onclick="return confirm(\'¿Eliminar reembolso?\')">Eliminar</button>'
                            .'</form>';
                            // Edit
                            echo '<details style="display:inline-block; margin-left:6px"><summary>Editar</summary>';
                            echo '<form method="post" action="'.$admin_post_url.'" enctype="multipart/form-data" style="margin-top:6px;">'
                                    .wp_nonce_field('wpn_public','_wpnonce',false,false)
                                    .'<input type="hidden" name="action" value="wpn_public_reembolso">'
                                    .'<input type="hidden" name="wpn_public_action" value="update_reembolso">'
                                    .'<input type="hidden" name="qid" value="'.intval($selected).'">'
                                    .'<input type="hidden" name="_redirect" value="'.$redirect_reembolsos.'">'
                                    .'<input type="hidden" name="reembolso_id" value="'.intval($r->id).'">'
                                    .'<div class="grid-4 wpn-form-row">'
                                        .'<div class="wpn-form-group"><label>Monto</label><input type="number" step="0.01" name="monto" value="'.esc_attr(number_format($r->monto,2,'.','')).'" required></div>'
                                        .'<div class="wpn-form-group"><label>Descripción</label><input type="text" name="descripcion" value="'.esc_attr($r->descripcion).'"></div>'
                                        .'<div class="wpn-form-group"><label>Evidencia (opcional)</label><input type="file" name="evidencia" accept="image/*,application/pdf"></div>'
                                        .'<div class="wpn-form-group"><label>&nbsp;</label><button class="wpn-btn-success" type="submit">Guardar</button></div>'
                                    .'</div>'
                                .'</form>';
                            echo '</details>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        if (empty($rows)) {
                            echo '<tr><td colspan="4" style="text-align:center; color: #65676b;">No hay reembolsos capturados</td></tr>';
                        }
                    ?>
                    </tbody>
                </table>
            </div>

            <div class="wpn-tabpanel" id="tab-total" style="display:none;">
                <?php
                    $total_base = $quincena_base + $reembolso_total; 
                    $bono_aplicable = ($con_bono ? ($bono_total + $bono_mensual_extra) : 0); 
                    $total_ingresos = $total_base + $bono_aplicable;
                    $esquema = strtoupper(trim($emp->esquema ?? ''));
                    $pago_imss = 0;
                    $pago_sind = 0;
                    if ($esquema === 'SINDICATO'){
                        $pago_sind = $total_ingresos;
                    } else {
                        $pago_imss = min($imss_monto, $total_base);
                        $pago_sind = ($total_base - $pago_imss) + $bono_aplicable;
                    }
                ?>
                <div class="grid-3">
                    <div class="wpn-stat">
                        <span>Quincena base</span>
                        <strong>$<?php echo number_format($quincena_base,2); ?></strong>
                    </div>
                    <div class="wpn-stat">
                        <span>Bonos (si aplica)</span>
                        <strong><?php echo ($bono_total<0?'−':'+'); ?> $<?php echo number_format(abs($bono_total),2); ?></strong>
                    </div>
                    <div class="wpn-stat">
                        <span>Reembolsos</span>
                        <strong>+ $<?php echo number_format($reembolso_total,2); ?></strong>
                    </div>
                </div>
                <div class="grid-2" style="margin-top:16px;">
                    <?php if ($esquema !== 'SINDICATO'): ?>
                        <div class="wpn-stat">
                            <span>Pago IMSS</span>
                            <strong>$<?php echo number_format($pago_imss,2); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="wpn-stat">
                        <span>Pago Sindicato</span>
                        <strong>$<?php echo number_format($pago_sind,2); ?></strong>
                    </div>
                </div>
                <div class="wpn-total-box" style="margin-top:20px;">
                    <h3><span class="icon-emoji">💰</span> Total a recibir</h3>
                    <p><strong>$<?php echo number_format($total_ingresos,2); ?></strong></p>
                </div>
            </div>
        </div>
        <?php endif; // selected & qe ?>
        
        <script>
        document.addEventListener('DOMContentLoaded', function(){
          const tabs = document.querySelectorAll('.wpn-tab');
          const panels = {
            bonos: document.getElementById('tab-bonos'),
            reembolsos: document.getElementById('tab-reembolsos'),
            total: document.getElementById('tab-total')
          };
          
          function openTab(key){
            Object.values(panels).forEach(p => p && (p.style.display='none'));
            if (panels[key]) panels[key].style.display = 'block';
            tabs.forEach(b => b.classList.remove('active'));
            const btn = document.querySelector(`.wpn-tab[data-tab="${key}"]`);
            if (btn) btn.classList.add('active');
            const u = new URL(location.href);
            u.searchParams.set('tab', key);
            u.hash = key;
            history.replaceState(null, '', u.toString());
          }
          
          tabs.forEach(btn => {
            if (btn.dataset.tab) {
              btn.addEventListener('click', () => openTab(btn.dataset.tab));
            }
          });
          
          const params = new URLSearchParams(location.search);
          let initial = params.get('tab');
          if (!initial && location.hash) initial = location.hash.replace('#','');
          openTab((initial && panels[initial]) ? initial : 'bonos');
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private static function render_vacaciones_section($emp) {
        global $wpdb;
        $tv = $wpdb->prefix.'nmn_vacaciones';
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';
        
        // Opciones de años laborales
        $fecha_ingreso = $emp->fecha_ingreso;
        $anio_base = intval(date('Y', strtotime($fecha_ingreso)));
        $hoy = current_time('Y-m-d');
        $anios = [];
        $years = max(1, intval(date('Y', strtotime($hoy))) - $anio_base + 1);
        for($i=1; $i<=$years; $i++){ $anios[] = $i; }

        $anio_sel = isset($_GET['anio']) ? intval($_GET['anio']) : end($anios);
        if (!$anio_sel) $anio_sel = 1;

        $dias_asignados = 12 + max(0, ($anio_sel-1))*2;
        // Asegurar fila de balance
        $wpdb->query("CREATE TABLE IF NOT EXISTS $tv (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            anio_laboral INT NOT NULL,
            dias_asignados INT NOT NULL DEFAULT 0,
            dias_usados INT NOT NULL DEFAULT 0,
            UNIQUE KEY emp_anio (employee_id, anio_laboral)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $bal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tv WHERE employee_id=%d AND anio_laboral=%d", $emp->id, $anio_sel));
        if (!$bal){
            $wpdb->insert($tv, ['employee_id'=>$emp->id,'anio_laboral'=>$anio_sel,'dias_asignados'=>$dias_asignados,'dias_usados'=>0]);
            $bal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tv WHERE employee_id=%d AND anio_laboral=%d", $emp->id, $anio_sel));
        }
        $dias_usados = intval($bal->dias_usados);

        // Solicitudes
        $wpdb->query("CREATE TABLE IF NOT EXISTS $ts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            anio_laboral INT NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            dias_habiles INT NOT NULL DEFAULT 0,
            comentario VARCHAR(255) NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $sols = $wpdb->get_results($wpdb->prepare("SELECT * FROM $ts WHERE employee_id=%d AND anio_laboral=%d ORDER BY id DESC", $emp->id, $anio_sel));
        
        ob_start();
        ?>
        <div class="wpn-back-button">
            <a href="<?php echo esc_url(remove_query_arg(['sec','anio'])); ?>" class="wpn-btn-secondary">
                <span class="icon-emoji">◀️</span> Regresar al menú principal
            </a>
        </div>
        
        <div class="wpn-card">
            <h2><span class="icon-emoji">🏖️</span> Vacaciones</h2>
            <div class="grid-3">
                <div class="wpn-stat">
                    <span>Año laboral</span>
                    <form method="get" style="margin-top: 8px;">
                        <input type="hidden" name="sec" value="vacaciones">
                        <select name="anio" onchange="this.form.submit()" style="width: auto;">
                            <?php foreach($anios as $a): ?>
                                <option value="<?php echo (int)$a; ?>" <?php selected($anio_sel,$a); ?>>Año <?php echo (int)$a; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="wpn-stat">
                    <span>Días asignados</span>
                    <strong><?php echo (int)$bal->dias_asignados; ?></strong>
                </div>
                <div class="wpn-stat">
                    <span>Días usados</span>
                    <strong><?php echo (int)$dias_usados; ?></strong>
                </div>
            </div>

            <div class="wpn-card" style="margin-top:16px; background: #f0f2f5;">
                <h3><span class="icon-emoji">📝</span> Solicitar vacaciones</h3>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wpn_public'); ?>
                    <input type="hidden" name="action" value="wpn_public_vac">
                    <input type="hidden" name="_redirect" value="<?php echo esc_url(add_query_arg(['sec'=>'vacaciones','anio'=>$anio_sel])); ?>#vacaciones">
                    <div class="grid-4 wpn-form-row">
                        <div class="wpn-form-group">
                            <label>De</label>
                            <input type="date" name="fecha_inicio" required>
                        </div>
                        <div class="wpn-form-group">
                            <label>A</label>
                            <input type="date" name="fecha_fin" required>
                        </div>
                        <div class="wpn-form-group">
                            <label>Comentarios</label>
                            <input type="text" name="comentario" placeholder="Motivo u observaciones">
                        </div>
                        <div class="wpn-form-group">
                            <label>&nbsp;</label>
                            <button class="wpn-btn-primary" type="submit">Enviar solicitud</button>
                        </div>
                    </div>
                    <input type="hidden" name="anio_laboral" value="<?php echo (int)$anio_sel; ?>">
                </form>
            </div>

            <h3 style="margin-top:16px;"><span class="icon-emoji">📋</span> Tus solicitudes</h3>
            <table class="wpn-table">
                <thead><tr><th>Periodo</th><th>Días hábiles</th><th>Comentario</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php if ($sols): foreach($sols as $s): ?>
                        <tr>
                            <td><?php echo esc_html($s->fecha_inicio.' a '.$s->fecha_fin); ?></td>
                            <td><?php echo (int)$s->dias_habiles; ?></td>
                            <td><?php echo esc_html($s->comentario); ?></td>
                            <td>
                                <?php 
                                $estado = ucfirst($s->estado);
                                $badge_class = '';
                                if ($s->estado === 'aprobada') $badge_class = 'success';
                                elseif ($s->estado === 'rechazada') $badge_class = 'danger';
                                elseif ($s->estado === 'pendiente') $badge_class = 'warning';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo esc_html($estado); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" style="text-align:center; color: #65676b;">Aún no has enviado solicitudes en este año laboral.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    
    private static function render_permisos_section($emp) {
        global $wpdb;
        $tp = $wpdb->prefix.'nmn_permisos';
        
        // Calcular año laboral actual
        if (class_exists('WPN_Permisos')) {
            $anio_laboral = WPN_Permisos::get_anio_laboral_actual($emp->fecha_ingreso);
            $max_permisos = WPN_Permisos::MAX_PERMISOS_POR_ANIO;
            $max_horas = WPN_Permisos::MAX_HORAS_POR_PERMISO;
            $puede_solicitar = WPN_Permisos::puede_solicitar_permiso($emp->id, $anio_laboral);
        } else {
            $start = new DateTime($emp->fecha_ingreso);
            $now = new DateTime(current_time('Y-m-d'));
            $diff = $start->diff($now);
            $anio_laboral = $diff->y + 1;
            $max_permisos = 6;
            $max_horas = 4;
            $puede_solicitar = true;
        }
        
        // Obtener permisos usados
        $usados = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tp WHERE employee_id=%d AND anio_laboral=%d AND estado='aprobado'",
            $emp->id,
            $anio_laboral
        )));
        
        $disponibles = max(0, $max_permisos - $usados);
        
        // Solicitudes
        $wpdb->query("CREATE TABLE IF NOT EXISTS $tp (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $sols = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tp WHERE employee_id=%d AND anio_laboral=%d ORDER BY id DESC",
            $emp->id,
            $anio_laboral
        ));
        
        ob_start();
        ?>
        <div class="wpn-back-button">
            <a href="<?php echo esc_url(remove_query_arg(['sec'])); ?>" class="wpn-btn-secondary">
                <span class="icon-emoji">◀️</span> Regresar al menú principal
            </a>
        </div>
        
        <div class="wpn-card">
            <h2><span class="icon-emoji">⏰</span> Permisos</h2>
            
            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] === 'max_permisos'): ?>
                    <div class="notice notice-error" style="padding:10px;border-left:4px solid #dc3232;background:#fef2f2;margin-bottom:16px;">
                        ❌ Ya has utilizado todos tus permisos disponibles para este año laboral.
                    </div>
                <?php elseif ($_GET['error'] === 'max_horas'): ?>
                    <div class="notice notice-error" style="padding:10px;border-left:4px solid #dc3232;background:#fef2f2;margin-bottom:16px;">
                        ❌ El máximo de horas por permiso es <?php echo $max_horas; ?>.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="grid-3">
                <div class="wpn-stat">
                    <span>Año laboral</span>
                    <strong><?php echo (int)$anio_laboral; ?></strong>
                </div>
                <div class="wpn-stat">
                    <span>Permisos usados</span>
                    <strong><?php echo (int)$usados; ?></strong>
                </div>
                <div class="wpn-stat">
                    <span>Permisos disponibles</span>
                    <strong><?php echo (int)$disponibles; ?></strong>
                </div>
            </div>

            <?php if ($puede_solicitar && $disponibles > 0): ?>
                <div class="wpn-card" style="margin-top:16px; background: #f0f2f5;">
                    <h3><span class="icon-emoji">📝</span> Solicitar permiso</h3>
                    <p style="color:#666; font-size:14px;">
                        Puedes solicitar máximo <?php echo $max_permisos; ?> permisos de hasta <?php echo $max_horas; ?> horas cada uno por año laboral.
                    </p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('wpn_public'); ?>
                        <input type="hidden" name="action" value="wpn_public_permiso">
                        <input type="hidden" name="_redirect" value="<?php echo esc_url(add_query_arg(['sec'=>'permisos'])); ?>#permisos">
                        <div class="grid-4 wpn-form-row">
                            <div class="wpn-form-group">
                                <label>Fecha</label>
                                <input type="date" name="fecha" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="wpn-form-group">
                                <label>Tipo</label>
                                <select name="tipo" required>
                                    <option value="">-- Seleccionar --</option>
                                    <option value="entrada">🌅 Entrada tarde</option>
                                    <option value="salida">🌆 Salida temprano</option>
                                </select>
                            </div>
                            <div class="wpn-form-group">
                                <label>Hora</label>
                                <input type="time" name="hora" required>
                            </div>
                            <div class="wpn-form-group">
                                <label>Horas</label>
                                <input type="number" name="horas_solicitadas" required min="0.5" max="<?php echo $max_horas; ?>" step="0.5" placeholder="Ej: 2">
                            </div>
                        </div>
                        <div class="wpn-form-group" style="margin-top:12px;">
                            <label>Motivo</label>
                            <input type="text" name="motivo" placeholder="Describe el motivo de tu solicitud" required>
                        </div>
                        <button class="wpn-btn-primary" type="submit">Enviar solicitud</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="notice notice-error" style="padding:10px;border-left:4px solid #dc3232;background:#fef2f2;margin:16px 0;">
                    ❌ Has alcanzado el límite de permisos para este año laboral.
                </div>
            <?php endif; ?>

            <h3 style="margin-top:16px;"><span class="icon-emoji">📋</span> Tus solicitudes</h3>
            <table class="wpn-table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Hora</th><th>Horas</th><th>Motivo</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php if ($sols): foreach($sols as $s): 
                        $tipo_texto = $s->tipo === 'entrada' ? '🌅 Entrada tarde' : '🌆 Salida temprano';
                    ?>
                        <tr>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($s->fecha))); ?></td>
                            <td><?php echo $tipo_texto; ?></td>
                            <td><?php echo esc_html(date('H:i', strtotime($s->hora))); ?></td>
                            <td><?php echo number_format($s->horas_solicitadas, 2); ?> hrs</td>
                            <td><?php echo esc_html($s->motivo); ?></td>
                            <td>
                                <?php 
                                $estado = ucfirst($s->estado);
                                $badge_class = '';
                                if ($s->estado === 'aprobado') $badge_class = 'success';
                                elseif ($s->estado === 'rechazado') $badge_class = 'danger';
                                elseif ($s->estado === 'pendiente') $badge_class = 'warning';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo esc_html($estado); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" style="text-align:center; color: #65676b;">Aún no has enviado solicitudes en este año laboral.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
