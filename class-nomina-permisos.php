<?php
if (!defined('ABSPATH')) exit;

class WPN_Permisos {
    
    const MAX_PERMISOS_POR_ANIO = 6;
    const MAX_HORAS_POR_PERMISO = 4;
    
    public static function init(){
        add_action('admin_menu', [__CLASS__, 'add_submenu'], 35);
        add_action('admin_post_wpn_permisos_aprobar', [__CLASS__, 'aprobar_permiso']);
        add_action('admin_post_wpn_permisos_rechazar', [__CLASS__, 'rechazar_permiso']);
        add_action('admin_post_wpn_permisos_delete', [__CLASS__, 'delete_permiso']);
        add_action('admin_post_wpn_permisos_resend', [__CLASS__, 'resend_notification']);
    }
    
    public static function add_submenu(){
        if (!current_user_can('manage_options')) return;
        
        add_submenu_page(
            'wp-nomina',
            'Permisos',
            'Permisos',
            'manage_options',
            'wpn-permisos',
            [__CLASS__, 'render_page']
        );
        
        add_submenu_page(
            'wp-nomina',
            'Reporte de Permisos',
            'Reporte de Permisos',
            'manage_options',
            'wpn-permisos-reporte',
            [__CLASS__, 'render_reporte']
        );
    }
    
    /**
     * Obtener año laboral actual del empleado
     */
    public static function get_anio_laboral_actual($fecha_ingreso) {
        $start = new DateTime($fecha_ingreso);
        $now = new DateTime(current_time('Y-m-d'));
        $diff = $start->diff($now);
        return $diff->y + 1; // Año laboral (1-based)
    }
    
    /**
     * Obtener permisos usados en el año laboral actual
     */
    public static function get_permisos_usados($employee_id, $anio_laboral = null) {
        global $wpdb;
        $tp = $wpdb->prefix.'nmn_permisos';
        
        if ($anio_laboral === null) {
            $te = $wpdb->prefix.'nmn_employees';
            $emp = $wpdb->get_row($wpdb->prepare("SELECT fecha_ingreso FROM $te WHERE id=%d", $employee_id));
            if (!$emp) return 0;
            $anio_laboral = self::get_anio_laboral_actual($emp->fecha_ingreso);
        }
        
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tp WHERE employee_id=%d AND anio_laboral=%d AND estado='aprobado'",
            $employee_id,
            $anio_laboral
        )));
    }
    
    /**
     * Verificar si puede solicitar más permisos
     */
    public static function puede_solicitar_permiso($employee_id, $anio_laboral = null) {
        $usados = self::get_permisos_usados($employee_id, $anio_laboral);
        return $usados < self::MAX_PERMISOS_POR_ANIO;
    }
    
    /**
     * Página principal de administración de permisos
     */
    public static function render_page(){
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $tp = $wpdb->prefix.'nmn_permisos';
        
        $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : 'pendiente';
        $anio = isset($_GET['anio']) ? intval($_GET['anio']) : 0;
        
        $where = " WHERE 1=1 ";
        $params = [];
        if ($estado && in_array($estado, ['pendiente','aprobado','rechazado'], true)){
            $where .= " AND p.estado=%s ";
            $params[] = $estado;
        }
        if ($anio > 0){
            $where .= " AND p.anio_laboral=%d ";
            $params[] = $anio;
        }
        
        $sql = "SELECT p.*, CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', e.apellido_materno) AS nombre_completo, e.rfc, e.email
                FROM $tp p 
                JOIN $te e ON e.id=p.employee_id 
                $where 
                ORDER BY p.id DESC";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
        
        $base_url = menu_page_url('wpn-permisos', false);
        ?>
        <div class="wrap">
            <h1>⏰ Permisos de Colaboradores</h1>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="wpn-permisos">
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="pendiente" <?php selected($estado,'pendiente'); ?>>Pendientes</option>
                    <option value="aprobado" <?php selected($estado,'aprobado'); ?>>Aprobados</option>
                    <option value="rechazado" <?php selected($estado,'rechazado'); ?>>Rechazados</option>
                </select>
                <input type="number" name="anio" placeholder="Año laboral" value="<?php echo $anio ? (int)$anio : ''; ?>" style="width:130px;">
                <button class="button">Filtrar</button>
            </form>
            
            <?php if (isset($_GET['email_resent']) && $_GET['email_resent'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>✅ Notificación reenviada exitosamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['email_error']) && $_GET['email_error'] == '1'): ?>
                <div class="notice notice-error is-dismissible">
                    <p>❌ Error al reenviar la notificación. Verifica que el colaborador tenga email configurado.</p>
                </div>
            <?php endif; ?>
            
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th><th>Colaborador</th><th>Año Laboral</th><th>Fecha</th><th>Tipo</th><th>Hora</th><th>Horas</th><th>Motivo</th><th>Estado</th><th>Email</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows): foreach($rows as $r): 
                    // Obtener información de email
                    $email_status = '';
                    if ($r->estado === 'aprobado') {
                        $email_sent = isset($r->email_sent) ? intval($r->email_sent) : 0;
                        $email_sent_at = isset($r->email_sent_at) ? $r->email_sent_at : null;
                        
                        if ($email_sent && $email_sent_at) {
                            $email_status = '✅ Enviado<br><small>' . date('d/m/Y H:i', strtotime($email_sent_at)) . '</small>';
                        } else if ($email_sent) {
                            $email_status = '✅ Enviado';
                        } else {
                            $email_status = '❌ No enviado';
                        }
                    } else {
                        $email_status = '-';
                    }
                    
                    $tipo_texto = $r->tipo === 'entrada' ? '🌅 Entrada tarde' : '🌆 Salida temprano';
                ?>
                    <tr>
                        <td><?php echo (int)$r->id; ?></td>
                        <td><?php echo esc_html($r->nombre_completo).' ('.esc_html($r->rfc).')'; ?></td>
                        <td><?php echo (int)$r->anio_laboral; ?></td>
                        <td><?php echo esc_html(date('d/m/Y', strtotime($r->fecha))); ?></td>
                        <td><?php echo $tipo_texto; ?></td>
                        <td><?php echo esc_html(date('H:i', strtotime($r->hora))); ?></td>
                        <td><?php echo number_format($r->horas_solicitadas, 2); ?> hrs</td>
                        <td><?php echo esc_html($r->motivo); ?></td>
                        <td><?php echo esc_html(ucfirst($r->estado)); ?></td>
                        <td><?php echo $email_status; ?></td>
                        <td style="white-space:nowrap;">
                            <?php $nonce = wp_create_nonce('wpn_admin_permisos'); ?>
                            <?php if ($r->estado==='pendiente'): ?>
                                <button class="button button-primary" onclick="wpnShowPermisosApprovalModal(<?php echo $r->id; ?>, '<?php echo addslashes($r->nombre_completo); ?>')">Aprobar</button>
                                <a class="button" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wpn_permisos_rechazar&id='.$r->id.'&_redirect='.urlencode($base_url)),'wpn_admin_permisos'); ?>">Rechazar</a>
                            <?php endif; ?>
                            <?php if ($r->estado==='aprobado'): ?>
                                <a class="button button-secondary" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wpn_permisos_resend&id='.$r->id.'&_redirect='.urlencode($base_url)),'wpn_admin_permisos_resend'); ?>" onclick="return confirm('¿Reenviar notificación al colaborador?');">📧 Reenviar Email</a>
                            <?php endif; ?>
                            <a class="button button-link-delete" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wpn_permisos_delete&id='.$r->id.'&_redirect='.urlencode($base_url)),'wpn_admin_permisos'); ?>" onclick="return confirm('¿Eliminar solicitud?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="11">No hay solicitudes que coincidan con el filtro.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Modal para selección de colaboradores -->
        <div id="wpn-permisos-approval-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000;">
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:8px; min-width:500px; max-width:700px; max-height:80vh; overflow-y:auto;">
                <h2>Aprobar Permiso</h2>
                <p>Selecciona a qué colaboradores se les notificará sobre esta aprobación:</p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="wpn-permisos-approval-form">
                    <?php wp_nonce_field('wpn_admin_permisos'); ?>
                    <input type="hidden" name="action" value="wpn_permisos_aprobar">
                    <input type="hidden" name="id" id="wpn-permisos-modal-solicitud-id" value="">
                    <input type="hidden" name="_redirect" value="<?php echo esc_url($base_url); ?>">
                    
                    <div style="margin:20px 0;">
                        <p><strong>Empleado solicitante:</strong> <span id="wpn-permisos-modal-employee-name"></span></p>
                    </div>
                    
                    <div style="margin:20px 0; padding:15px; background:#f0f2f5; border-radius:8px;">
                        <h3 style="margin-top:0;">Notificar a:</h3>
                        <label style="display:block; margin-bottom:10px;">
                            <input type="checkbox" name="notify_employee" value="1" checked>
                            <strong>Al colaborador solicitante</strong>
                        </label>
                        
                        <h4 style="margin-top:15px;">Notificar también a otros colaboradores:</h4>
                        <div style="max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:10px; background:white;">
                            <?php
                            $all_employees = $wpdb->get_results("SELECT id, nombre, apellido_paterno, apellido_materno, rfc, email FROM $te WHERE email IS NOT NULL AND email != '' ORDER BY nombre ASC");
                            if ($all_employees):
                                foreach($all_employees as $emp):
                                    $emp_nombre_completo = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
                            ?>
                                <label style="display:block; margin-bottom:8px; padding:5px;">
                                    <input type="checkbox" name="notify_others[]" value="<?php echo (int)$emp->id; ?>">
                                    <?php echo esc_html($emp_nombre_completo); ?> (<?php echo esc_html($emp->rfc); ?>)
                                    <br><small style="color:#666; margin-left:20px;"><?php echo esc_html($emp->email); ?></small>
                                </label>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <p style="color:#999;">No hay otros colaboradores con email registrado.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top:15px;">
                            <label style="display:block;">
                                <input type="checkbox" id="wpn-permisos-select-all-employees">
                                <strong>Seleccionar todos</strong>
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-top:20px; text-align:right;">
                        <button type="button" class="button" onclick="wpnClosePermisosApprovalModal()">Cancelar</button>
                        <button type="submit" class="button button-primary">Aprobar y Notificar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function wpnShowPermisosApprovalModal(solicitudId, empleadoNombre) {
            document.getElementById('wpn-permisos-modal-solicitud-id').value = solicitudId;
            document.getElementById('wpn-permisos-modal-employee-name').textContent = empleadoNombre;
            document.getElementById('wpn-permisos-approval-modal').style.display = 'block';
        }
        
        function wpnClosePermisosApprovalModal() {
            document.getElementById('wpn-permisos-approval-modal').style.display = 'none';
        }
        
        // Funcionalidad para "Seleccionar todos"
        document.addEventListener('DOMContentLoaded', function() {
            var selectAllCheckbox = document.getElementById('wpn-permisos-select-all-employees');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    var checkboxes = document.querySelectorAll('input[name="notify_others[]"]');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            }
        });
        
        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', function(event) {
            var modal = document.getElementById('wpn-permisos-approval-modal');
            if (event.target === modal) {
                wpnClosePermisosApprovalModal();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Página de reporte de permisos
     */
    public static function render_reporte(){
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $tp = $wpdb->prefix.'nmn_permisos';
        
        $emps = $wpdb->get_results("SELECT id, nombre, apellido_paterno, apellido_materno, rfc, fecha_ingreso FROM $te ORDER BY nombre ASC");
        ?>
        <div class="wrap wpn-wrap">
            <h1>📊 Reporte de Permisos</h1>
            <p>Resumen por colaborador del <strong>saldo disponible</strong> de permisos (año laboral actual).</p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:28%">Colaborador</th>
                        <th style="width:18%">RFC</th>
                        <th style="width:14%; text-align:right">Año laboral</th>
                        <th style="width:14%; text-align:right">Permitidos</th>
                        <th style="width:14%; text-align:right">Usados</th>
                        <th style="width:12%; text-align:right">Disponibles</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($emps): foreach($emps as $e):
                    $nombre_completo = trim($e->nombre . ' ' . $e->apellido_paterno . ' ' . $e->apellido_materno);
                    $anio_laboral = self::get_anio_laboral_actual($e->fecha_ingreso);
                    $permitidos = self::MAX_PERMISOS_POR_ANIO;
                    $usados = self::get_permisos_usados($e->id, $anio_laboral);
                    $disponibles = max(0, $permitidos - $usados);
                ?>
                    <tr>
                        <td><?php echo esc_html($nombre_completo ?: '(Sin nombre)'); ?></td>
                        <td><?php echo esc_html($e->rfc ?: ''); ?></td>
                        <td style="text-align:right"><?php echo esc_html($anio_laboral); ?></td>
                        <td style="text-align:right"><?php echo number_format($permitidos, 0); ?></td>
                        <td style="text-align:right"><?php echo number_format($usados, 0); ?></td>
                        <td style="text-align:right"><strong><?php echo number_format($disponibles, 0); ?></strong></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">No hay colaboradores.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Aprobar permiso
     */
    public static function aprobar_permiso(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpn_admin_permisos')) wp_die('Nonce inválido');
        
        global $wpdb;
        $tp = $wpdb->prefix.'nmn_permisos';
        $te = $wpdb->prefix.'nmn_employees';
        
        $id = intval($_REQUEST['id'] ?? 0);
        $redirect = isset($_REQUEST['_redirect']) ? esc_url_raw($_REQUEST['_redirect']) : admin_url('admin.php?page=wpn-permisos');
        
        $permiso = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tp WHERE id=%d", $id));
        
        if ($permiso && $permiso->estado !== 'aprobado'){
            $wpdb->update($tp, ['estado'=>'aprobado'], ['id'=>$id]);
            
            // Obtener empleado solicitante
            $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE id=%d", $permiso->employee_id));
            
            // Obtener lista de colaboradores a notificar
            $notify_employee = isset($_POST['notify_employee']) ? true : false;
            $notify_others = isset($_POST['notify_others']) ? array_map('intval', $_POST['notify_others']) : [];
            
            $employees_to_notify = [];
            
            // Agregar empleado solicitante si está marcado
            if ($notify_employee && $emp) {
                $employees_to_notify[] = $emp;
            }
            
            // Agregar otros colaboradores seleccionados
            if (!empty($notify_others)) {
                $others = $wpdb->get_results("SELECT * FROM $te WHERE id IN (" . implode(',', $notify_others) . ")");
                if ($others) {
                    foreach($others as $other_emp) {
                        $employees_to_notify[] = $other_emp;
                    }
                }
            }
            
            // Enviar notificaciones
            if (class_exists('WPN_Email') && $emp && !empty($employees_to_notify)) {
                WPN_Email::notify_permiso_approved_multiple($emp, $employees_to_notify, $permiso, $id);
            }
        }
        wp_safe_redirect($redirect);
        exit;
    }
    
    /**
     * Rechazar permiso
     */
    public static function rechazar_permiso(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpn_admin_permisos')) wp_die('Nonce inválido');
        
        global $wpdb;
        $tp = $wpdb->prefix.'nmn_permisos';
        $id = intval($_REQUEST['id'] ?? 0);
        $redirect = isset($_REQUEST['_redirect']) ? esc_url_raw($_REQUEST['_redirect']) : admin_url('admin.php?page=wpn-permisos');
        
        $permiso = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tp WHERE id=%d", $id));
        if ($permiso){
            $wpdb->update($tp, ['estado'=>'rechazado'], ['id'=>$id]);
        }
        wp_safe_redirect($redirect);
        exit;
    }
    
    /**
     * Eliminar permiso
     */
    public static function delete_permiso(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpn_admin_permisos')) wp_die('Nonce inválido');
        
        global $wpdb;
        $tp = $wpdb->prefix.'nmn_permisos';
        $id = intval($_REQUEST['id'] ?? 0);
        $redirect = isset($_REQUEST['_redirect']) ? esc_url_raw($_REQUEST['_redirect']) : admin_url('admin.php?page=wpn-permisos');
        
        $permiso = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tp WHERE id=%d", $id));
        if ($permiso){
            $wpdb->delete($tp, ['id'=>$id]);
        }
        wp_safe_redirect($redirect);
        exit;
    }
    
    /**
     * Reenviar notificación
     */
    public static function resend_notification(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpn_admin_permisos_resend')) wp_die('Nonce inválido');
        
        global $wpdb;
        $tp = $wpdb->prefix.'nmn_permisos';
        $te = $wpdb->prefix.'nmn_employees';
        $id = intval($_REQUEST['id'] ?? 0);
        $redirect = isset($_REQUEST['_redirect']) ? esc_url_raw($_REQUEST['_redirect']) : admin_url('admin.php?page=wpn-permisos');
        
        $permiso = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tp WHERE id=%d AND estado='aprobado'", $id));
        
        if ($permiso) {
            $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE id=%d", $permiso->employee_id));
            
            if ($emp && class_exists('WPN_Email')) {
                $result = WPN_Email::notify_permiso_approved($emp, $permiso, $id);
                
                if ($result) {
                    $redirect = add_query_arg('email_resent', '1', $redirect);
                } else {
                    $redirect = add_query_arg('email_error', '1', $redirect);
                }
            }
        }
        
        wp_safe_redirect($redirect);
        exit;
    }
}
