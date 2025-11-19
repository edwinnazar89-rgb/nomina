<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPN_Admin {

    public static function init(){
        
        // Ensure optional columns exist (non-destructive)
        add_action('admin_init', function(){
            global $wpdb;
            $te = $wpdb->prefix.'nmn_employees';
            $cols = $wpdb->get_col("DESC $te", 0);
            
            // Agregar columnas nuevas si no existen
            if ($cols && !in_array('cp', $cols)) { 
                $wpdb->query("ALTER TABLE $te ADD COLUMN cp varchar(10) NULL AFTER cuenta"); 
            }
            if ($cols && !in_array('regimen_sat', $cols)) {
                $wpdb->query("ALTER TABLE $te ADD COLUMN regimen_sat varchar(191) NULL AFTER puesto");
            }
            if ($cols && !in_array('email', $cols)) {
                $wpdb->query("ALTER TABLE $te ADD COLUMN email varchar(191) NULL AFTER curp");
            }
            if ($cols && !in_array('apellido_paterno', $cols)) {
                $wpdb->query("ALTER TABLE $te ADD COLUMN apellido_paterno varchar(191) NULL AFTER nombre");
            }
            if ($cols && !in_array('apellido_materno', $cols)) {
                $wpdb->query("ALTER TABLE $te ADD COLUMN apellido_materno varchar(191) NULL AFTER apellido_paterno");
            }
            if ($cols && !in_array('custom_password', $cols)) {
                $wpdb->query("ALTER TABLE $te ADD COLUMN custom_password TINYINT(1) NOT NULL DEFAULT 0 AFTER cuenta");
            }
        });
        
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_menu', [__CLASS__, 'cleanup_submenu'], 999);
        add_action('admin_post_wpn_save_employee', [__CLASS__, 'save_employee']);
        add_action('admin_post_wpn_delete_employee', [__CLASS__, 'delete_employee']);
        add_action('admin_post_wpn_reset_password', [__CLASS__, 'reset_password']);
        add_action('admin_post_wpn_create_quincena', [__CLASS__, 'create_quincena']);
        add_action('admin_post_wpn_delete_quincena', [__CLASS__, 'delete_quincena']);
        add_action('admin_post_wpn_export_quincena', [__CLASS__, 'export_quincena']);
        add_action('admin_post_wpn_update_qe_row', [__CLASS__, 'update_qe_row']);
        add_action('admin_post_wpn_vac_aprobar', [__CLASS__, 'vac_aprobar']);
        add_action('admin_post_wpn_vac_rechazar', [__CLASS__, 'vac_rechazar']);
        add_action('admin_post_wpn_vac_delete', [__CLASS__, 'vac_delete']);
        add_action('admin_post_wpn_vac_resend', [__CLASS__, 'vac_resend_notification']);
        add_action('admin_post_wpn_sync_users', [__CLASS__, 'sync_users']);
        add_action('admin_post_wpn_export_custom', [__CLASS__, 'export_custom']);
        add_action('admin_post_wpn_admin_add_bono', [__CLASS__, 'admin_add_bono']);
        add_action('admin_post_wpn_admin_add_reembolso', [__CLASS__, 'admin_add_reembolso']);
        add_action('admin_post_wpn_admin_delete_bono', [__CLASS__, 'admin_delete_bono']);
        add_action('admin_post_wpn_admin_delete_reembolso', [__CLASS__, 'admin_delete_reembolso']);
    }

    public static function menu(){
        // MenÃº principal
        add_menu_page('NÃ³mina', 'NÃ³mina', 'manage_options', 'wp-nomina', [__CLASS__,'page_employees'], 'dashicons-groups', 26);
        // SubmenÃºs
        add_submenu_page('wp-nomina','Colaboradores (Listado)','Colaboradores','manage_options','wpn-colaboradores',[__CLASS__,'render_colaboradores_list']);
        add_submenu_page('wp-nomina','Alta de colaborador','Alta de colaborador','manage_options','wpn-colaboradores-alta',[__CLASS__,'render_colaboradores_alta']);
        add_submenu_page('wp-nomina','Quincenas','Quincenas','manage_options','wpn-quincenas', [__CLASS__,'page_quincenas']);
        add_submenu_page('wp-nomina','Vacaciones','Vacaciones','manage_options','nomina_vacaciones', [__CLASS__,'render_vacaciones_admin']);
        add_submenu_page('wp-nomina','Reporte de Vacaciones','Reporte de Vacaciones','manage_options','nomina_vacaciones_reporte',[__CLASS__,'render_vacaciones_reporte']);
        add_submenu_page('wp-nomina','ExportaciÃ³n personalizada','Exportar quincena','manage_options','wpn-export-custom',[__CLASS__,'page_export_custom']);

        // Cleanup: remove auto-generated duplicate submenu
        global $submenu;
        if (isset($submenu['wp-nomina'][0]) && isset($submenu['wp-nomina'][0][2]) && $submenu['wp-nomina'][0][2] === 'wp-nomina') {
            unset($submenu['wp-nomina'][0]);
            $submenu['wp-nomina'] = array_values($submenu['wp-nomina']);
        }
    }

    // ---------- EMPLOYEES ----------
    public static function page_employees(){
        global $wpdb;
        $t = $wpdb->prefix.'nmn_employees';

        // List
        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC");
        ?>
        <div class="wrap wpn-wrap">
            <h1>Colaboradores</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin:12px 0;">
                <?php wp_nonce_field('wpn_sync_users'); ?>
                <input type="hidden" name="action" value="wpn_sync_users">
                <button class="button">Sincronizar usuarios (RFC/CURP)</button>
            </form>
            <div class="wpn-card">
                <h2>Agregar / Editar</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wpn_employee'); ?>
                    <input type="hidden" name="action" value="wpn_save_employee">
                    <input type="hidden" name="id" value="<?php echo isset($_GET['edit']) ? intval($_GET['edit']) : 0; ?>">
                    <?php
                        $editing = null;
                        if (!empty($_GET['edit'])){
                            $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", intval($_GET['edit'])), ARRAY_A);
                        }
                    ?>
                    <div class="grid-3">
                        <div>
                            <label>Nombre</label>
                            <input type="text" name="nombre" required value="<?php echo esc_attr($editing['nombre'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Apellido Paterno</label>
                            <input type="text" name="apellido_paterno" value="<?php echo esc_attr($editing['apellido_paterno'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Apellido Materno</label>
                            <input type="text" name="apellido_materno" value="<?php echo esc_attr($editing['apellido_materno'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Puesto</label>
                            <input type="text" name="puesto" value="<?php echo esc_attr($editing['puesto'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>RFC</label>
                            <input type="text" name="rfc" required value="<?php echo esc_attr($editing['rfc'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>CURP</label>
                            <input type="text" name="curp" required value="<?php echo esc_attr($editing['curp'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Correo electrÃ³nico</label>
                            <input type="email" name="email" value="<?php echo esc_attr($editing['email'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Fecha de ingreso</label>
                            <input type="date" name="fecha_ingreso" required value="<?php echo esc_attr($editing['fecha_ingreso'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Ingreso mensual</label>
                            <input type="number" step="0.01" name="ingreso_mensual" required value="<?php echo esc_attr($editing['ingreso_mensual'] ?? '0'); ?>">
                        </div>
                        <div>
                            <label>Bono mensual</label>
                            <input type="number" step="0.01" name="bono_mensual" value="<?php echo esc_attr($editing['bono_mensual'] ?? '0'); ?>">
                        </div>
                        <div>
                            <label>Esquema</label>
                            <select name="esquema">
                                <?php
                                    $opts = ['Sindicato','IMSS/Sindicato','IMSS'];
                                    $sel = $editing['esquema'] ?? 'Sindicato';
                                    foreach($opts as $o){
                                        echo '<option value="'.esc_attr($o).'" '.selected($sel,$o,false).'>'.$o.'</option>';
                                    }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label>Banco</label>
                            <input type="text" name="banco" value="<?php echo esc_attr($editing['banco'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Clabe interbancaria</label>
                            <input type="text" name="clabe" value="<?php echo esc_attr($editing['clabe'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Cuenta bancaria</label>
                            <input type="text" name="cuenta" value="<?php echo esc_attr($editing['cuenta'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>CÃ³digo postal</label>
                            <input type="text" name="cp" value="<?php echo esc_attr($editing['cp'] ?? ''); ?>" maxlength="10" pattern="[0-9]{4,10}" title="SÃ³lo nÃºmeros, 4-10 dÃ­gitos">
                        </div>
                        <div>
                            <label>RÃ©gimen SAT</label>
                            <input type="text" name="regimen_sat" value="<?php echo esc_attr($editing['regimen_sat'] ?? ''); ?>" placeholder="RÃ©gimen fiscal (SAT)">
                        </div>
                    </div>
                    <button class="button button-primary">Guardar</button>
                </form>
            </div>

            <div class="wpn-card">
                <h2>Listado</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th><th>Nombre Completo</th><th>RFC</th><th>CURP</th><th>Fecha Ingreso</th><th>Ingreso mensual</th><th>Esquema</th><th>ContraseÃ±a</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r): ?>
                            <tr>
                                <td><?php echo intval($r->id); ?></td>
                                <td><?php echo esc_html($r->nombre . ' ' . $r->apellido_paterno . ' ' . $r->apellido_materno); ?></td>
                                <td><?php echo esc_html($r->rfc); ?></td>
                                <td><?php echo esc_html($r->curp); ?></td>
                                <td><?php echo esc_html($r->fecha_ingreso); ?></td>
                                <td>$<?php echo number_format($r->ingreso_mensual,2); ?></td>
                                <td><?php echo esc_html($r->esquema); ?></td>
                                <td>
                                    <?php if ($r->custom_password): ?>
                                        <span style="color:green;">âœ“ Personalizada</span>
                                    <?php else: ?>
                                        <span style="color:#999;">CURP</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="button" href="<?php echo admin_url('admin.php?page=wp-nomina&edit='.(int)$r->id); ?>">Editar</a>
                                    <?php if ($r->custom_password && $r->user_id): ?>
                                        <form style="display:inline" method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Â¿Restablecer contraseÃ±a al CURP original?');">
                                            <?php wp_nonce_field('wpn_reset_password'); ?>
                                            <input type="hidden" name="action" value="wpn_reset_password">
                                            <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                                            <button class="button">Reset Pass</button>
                                        </form>
                                    <?php endif; ?>
                                    <form style="display:inline" method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Â¿Eliminar colaborador?');">
                                        <?php wp_nonce_field('wpn_employee_delete'); ?>
                                        <input type="hidden" name="action" value="wpn_delete_employee">
                                        <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                                        <button class="button button-link-delete">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function save_employee(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_employee');
        global $wpdb;
        $t = $wpdb->prefix.'nmn_employees';

        $data = [
            'nombre' => sanitize_text_field($_POST['nombre'] ?? ''),
            'apellido_paterno' => sanitize_text_field($_POST['apellido_paterno'] ?? ''),
            'apellido_materno' => sanitize_text_field($_POST['apellido_materno'] ?? ''),
            'rfc' => strtoupper(sanitize_text_field($_POST['rfc'] ?? '')),
            'curp'=> strtoupper(sanitize_text_field($_POST['curp'] ?? '')),
            'fecha_ingreso'=> sanitize_text_field($_POST['fecha_ingreso'] ?? ''),
            'ingreso_mensual'=> WPN_Utils::sanitize_money($_POST['ingreso_mensual'] ?? 0),
            'bono_mensual'=> WPN_Utils::sanitize_money($_POST['bono_mensual'] ?? 0),
            'esquema'=> sanitize_text_field($_POST['esquema'] ?? 'Sindicato'),
            'puesto'=> sanitize_text_field($_POST['puesto'] ?? ''),
            'banco'=> sanitize_text_field($_POST['banco'] ?? ''),
            'clabe'=> sanitize_text_field($_POST['clabe'] ?? ''),
            'cuenta'=> sanitize_text_field($_POST['cuenta'] ?? ''),
            'email'=> sanitize_email($_POST['email'] ?? ''),
            'regimen_sat'=> sanitize_text_field($_POST['regimen_sat'] ?? ''),
            'cp'=> preg_replace('/[^0-9]/','', $_POST['cp'] ?? ''),
        ];

        $id = intval($_POST['id'] ?? 0);
        if ($id){
            $wpdb->update($t, $data, ['id'=>$id]);
        } else {
            $wpdb->insert($t, $data);
            $id = $wpdb->insert_id;
        }

        // Sync user account
        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A);
        $user_id = WPN_Utils::ensure_wp_user(['rfc'=>$emp['rfc'], 'curp'=>$emp['curp']]);
        if ($user_id){
            $wpdb->update($t, ['user_id'=>$user_id], ['id'=>$id]);
        }

        // Ensure vacation balances
        WPN_Utils::ensure_vacation_balance($id, $emp['fecha_ingreso']);

        wp_redirect(admin_url('admin.php?page=wp-nomina&updated=1'));
        exit;
    }

    public static function reset_password(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_reset_password');
        global $wpdb;
        $t = $wpdb->prefix.'nmn_employees';
        
        $id = intval($_POST['id'] ?? 0);
        if ($id){
            $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id));
            if ($emp && $emp->user_id){
                // Reset password to CURP
                wp_set_password($emp->curp, $emp->user_id);
                // Mark as not custom password
                $wpdb->update($t, ['custom_password'=>0], ['id'=>$id]);
            }
        }
        
        wp_redirect(admin_url('admin.php?page=wp-nomina&password_reset=1'));
        exit;
    }

    public static function delete_employee(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_employee_delete');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        if ($id){
            $wpdb->delete($wpdb->prefix.'nmn_employees', ['id'=>$id]);
        }
        wp_redirect(admin_url('admin.php?page=wp-nomina'));
        exit;
    }

    // ---------- QUINCENAS ----------
    public static function page_quincenas(){
        global $wpdb;
        $tq = $wpdb->prefix.'nmn_quincenas';
        $tqe= $wpdb->prefix.'nmn_quincena_employees';
        $te = $wpdb->prefix.'nmn_employees';
        $tb = $wpdb->prefix.'nmn_bonos';
        $tr = $wpdb->prefix.'nmn_reembolsos';

        if (!empty($_GET['view'])){
            $qid = intval($_GET['view']);
            $q = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tq WHERE id=%d", $qid));
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT qe.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.rfc, e.curp, 
                       e.regimen_sat AS regimen_sat, e.esquema AS esquema, e.ingreso_mensual 
                FROM $tqe qe 
                LEFT JOIN $te e ON e.id=qe.employee_id 
                WHERE qe.quincena_id=%d 
                ORDER BY qe.id DESC", $qid));
            ?>
            <div class="wrap wpn-wrap">
                <h1>Quincena: <?php echo esc_html($q->label); ?></h1>
                <a class="button" href="<?php echo admin_url('admin.php?page=wpn-quincenas'); ?>">â† Volver</a>
                <form method="post" style="display:inline" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wpn_export_quincena'); ?>
                    <input type="hidden" name="action" value="wpn_export_quincena">
                    <input type="hidden" name="id" value="<?php echo (int)$qid; ?>">
                    <button class="button">Descargar XLS (CSV)</button>
                </form>
                
                <table class="widefat fixed striped" style="margin-top:12px">
                    <thead>
                        <tr>
                            <th>Empleado</th><th>RFC</th><th>Quincena base</th><th>IMSS (config)</th><th>Pago IMSS</th><th>Pago Sindicato</th><th>Bonos</th><th>Reembolsos</th><th>Total a Pagar</th><th>Banco</th><th>CLABE</th><th>Cuenta</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r):
                            $nombre_completo = trim($r->nombre . ' ' . $r->apellido_paterno . ' ' . $r->apellido_materno);
                            $qb = floatval($r->quincena_base);
                            $bon = floatval($r->bono_total);
                            $reb = floatval($r->reembolso_total);
                            $imss_cfg = floatval($q->imss_monto);
                            $pago_imss = 0; $pago_sind = 0;
                            if ($r->esquema === 'IMSS'){
                                $pago_imss = min($imss_cfg, $qb + $bon);
                                $pago_sind = max(0, $qb + $bon - $pago_imss);
                            } elseif ($r->esquema === 'IMSS/Sindicato'){
                                $pago_imss = min($imss_cfg, $qb + $bon);
                                $pago_sind = max(0, $qb + $bon - $pago_imss);
                            } else {
                                $pago_sind = $qb + $bon;
                            }
                            $total = $pago_imss + $pago_sind + $reb;
                        ?>
                        <tr>
                            <td><?php echo esc_html($nombre_completo); ?></td>
                            <td><?php echo esc_html($r->rfc); ?></td>
                            <td>$<?php echo number_format($qb,2); ?></td>
                            <td>$<?php echo number_format($imss_cfg,2); ?></td>
                            <td>$<?php echo number_format($pago_imss,2); ?></td>
                            <td>$<?php echo number_format($pago_sind,2); ?></td>
                            <td>
                                $<?php echo number_format($bon,2); ?>
                                <a href="<?php echo admin_url('admin.php?page=wpn-quincenas&view='.$qid.'&detail='.$r->id.'#bonos'); ?>" class="button button-small">Ver detalle</a>
                            </td>
                            <td>
                                $<?php echo number_format($reb,2); ?>
                                <a href="<?php echo admin_url('admin.php?page=wpn-quincenas&view='.$qid.'&detail='.$r->id.'#reembolsos'); ?>" class="button button-small">Ver detalle</a>
                            </td>
                            <td><strong>$<?php echo number_format($total,2); ?></strong></td>
                            <td><?php echo esc_html($r->banco); ?></td>
                            <td><?php echo esc_html($r->clabe); ?></td>
                            <td><?php echo esc_html($r->cuenta); ?></td>
                            <td>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                    <?php wp_nonce_field('wpn_update_qe'); ?>
                                    <input type="hidden" name="action" value="wpn_update_qe_row">
                                    <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                                    <input type="number" step="0.01" name="bono_total" value="<?php echo esc_attr($r->bono_total); ?>" style="width:100px">
                                    <input type="number" step="0.01" name="reembolso_total" value="<?php echo esc_attr($r->reembolso_total); ?>" style="width:100px">
                                    <button class="button">Actualizar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php 
                // NUEVA SECCIÃ“N: Detalle de bonos/reembolsos
                if (!empty($_GET['detail'])){
                    $qe_id = intval($_GET['detail']);
                    $qe = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tqe WHERE id=%d", $qe_id));
                    if ($qe){
                        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE id=%d", $qe->employee_id));
                        $nombre_completo = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
                        ?>
                        <div class="wpn-card" style="margin-top:24px;">
                            <h2>ðŸ“‹ Detalle de Bonos y Reembolsos - <?php echo esc_html($nombre_completo); ?></h2>
                            
                            <!-- BONOS -->
                            <div id="bonos" style="margin-bottom:32px;">
                                <h3>ðŸ’° Bonos</h3>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="background:#f0f2f5; padding:16px; border-radius:8px; margin-bottom:16px;">
                                    <?php wp_nonce_field('wpn_admin_bono'); ?>
                                    <input type="hidden" name="action" value="wpn_admin_add_bono">
                                    <input type="hidden" name="qe_id" value="<?php echo $qe_id; ?>">
                                    <input type="hidden" name="qid" value="<?php echo $qid; ?>">
                                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px;">
                                        <div>
                                            <label><strong>Concepto:</strong></label>
                                            <input type="text" name="descripcion" required placeholder="Ej: Bono de productividad" style="width:100%;">
                                        </div>
                                        <div>
                                            <label><strong>Monto:</strong></label>
                                            <input type="number" step="0.01" name="monto" required style="width:100%;">
                                        </div>
                                        <div>
                                            <label><strong>Tipo:</strong></label>
                                            <select name="tipo" style="width:100%;">
                                                <option value="positivo">Bono (+)</option>
                                                <option value="negativo">DeducciÃ³n (âˆ’)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label>&nbsp;</label>
                                            <button type="submit" class="button button-primary">Agregar Bono</button>
                                        </div>
                                    </div>
                                </form>
                                
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th>Concepto</th>
                                            <th style="width:120px;">Monto</th>
                                            <th style="width:100px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $bonos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tb WHERE qe_id=%d ORDER BY id DESC", $qe_id));
                                        if ($bonos):
                                            foreach($bonos as $bono):
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($bono->descripcion); ?></td>
                                            <td>
                                                <?php 
                                                $monto = floatval($bono->monto);
                                                echo ($monto < 0 ? 'âˆ’' : '+') . ' $' . number_format(abs($monto), 2); 
                                                ?>
                                            </td>
                                            <td>
                                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" onsubmit="return confirm('Â¿Eliminar este bono?');">
                                                    <?php wp_nonce_field('wpn_admin_bono'); ?>
                                                    <input type="hidden" name="action" value="wpn_admin_delete_bono">
                                                    <input type="hidden" name="bono_id" value="<?php echo $bono->id; ?>">
                                                    <input type="hidden" name="qe_id" value="<?php echo $qe_id; ?>">
                                                    <input type="hidden" name="qid" value="<?php echo $qid; ?>">
                                                    <button type="submit" class="button button-small">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php 
                                            endforeach;
                                        else:
                                        ?>
                                        <tr><td colspan="3" style="text-align:center; color:#999;">No hay bonos registrados</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- REEMBOLSOS -->
                            <div id="reembolsos">
                                <h3>ðŸ’¸ Reembolsos</h3>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" style="background:#f0f2f5; padding:16px; border-radius:8px; margin-bottom:16px;">
                                    <?php wp_nonce_field('wpn_admin_reembolso'); ?>
                                    <input type="hidden" name="action" value="wpn_admin_add_reembolso">
                                    <input type="hidden" name="qe_id" value="<?php echo $qe_id; ?>">
                                    <input type="hidden" name="qid" value="<?php echo $qid; ?>">
                                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px;">
                                        <div>
                                            <label><strong>Concepto:</strong></label>
                                            <input type="text" name="descripcion" required placeholder="Ej: Gasolina" style="width:100%;">
                                        </div>
                                        <div>
                                            <label><strong>Monto:</strong></label>
                                            <input type="number" step="0.01" name="monto" required style="width:100%;">
                                        </div>
                                        <div>
                                            <label><strong>Evidencia (opcional):</strong></label>
                                            <input type="file" name="evidencia" accept="image/*,application/pdf" style="width:100%;">
                                        </div>
                                        <div>
                                            <label>&nbsp;</label>
                                            <button type="submit" class="button button-primary">Agregar Reembolso</button>
                                        </div>
                                    </div>
                                </form>
                                
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th>Concepto</th>
                                            <th style="width:120px;">Monto</th>
                                            <th style="width:100px;">Evidencia</th>
                                            <th style="width:100px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $reembolsos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tr WHERE qe_id=%d ORDER BY id DESC", $qe_id));
                                        if ($reembolsos):
                                            foreach($reembolsos as $reemb):
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($reemb->descripcion); ?></td>
                                            <td>$<?php echo number_format($reemb->monto, 2); ?></td>
                                            <td>
                                                <?php if (!empty($reemb->evidencia_url)): ?>
                                                    <a href="<?php echo esc_url($reemb->evidencia_url); ?>" target="_blank">Ver ðŸ”</a>
                                                <?php else: ?>
                                                    <span style="color:#999;">Sin evidencia</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" onsubmit="return confirm('Â¿Eliminar este reembolso?');">
                                                    <?php wp_nonce_field('wpn_admin_reembolso'); ?>
                                                    <input type="hidden" name="action" value="wpn_admin_delete_reembolso">
                                                    <input type="hidden" name="reembolso_id" value="<?php echo $reemb->id; ?>">
                                                    <input type="hidden" name="qe_id" value="<?php echo $qe_id; ?>">
                                                    <input type="hidden" name="qid" value="<?php echo $qid; ?>">
                                                    <button type="submit" class="button button-small">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php 
                                            endforeach;
                                        else:
                                        ?>
                                        <tr><td colspan="4" style="text-align:center; color:#999;">No hay reembolsos registrados</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div style="margin-top:24px;">
                                <a href="<?php echo admin_url('admin.php?page=wpn-quincenas&view='.$qid); ?>" class="button">Cerrar detalle</a>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <?php
            return;
        }

        // List all quincenas
        $qs = $wpdb->get_results("SELECT * FROM $tq ORDER BY id DESC");
        ?>
        <div class="wrap wpn-wrap">
            <h1>Quincenas</h1>
            <div class="wpn-card">
                <h2>Crear nueva quincena</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wpn_create_quincena'); ?>
                    <input type="hidden" name="action" value="wpn_create_quincena">
                    <div class="grid-3">
                        <div>
                            <label>Etiqueta (ej: 2025-Q1-1)</label>
                            <input type="text" name="label" required>
                        </div>
                        <div>
                            <label>IMSS (monto)</label>
                            <input type="number" step="0.01" name="imss_monto" value="4182" required>
                        </div>
                        <div>
                            <label>Tipo de quincena</label>
                            <select name="con_bono">
                                <option value="0">Sin bono</option>
                                <option value="1">Con bono (segunda quincena)</option>
                            </select>
                        </div>
                    </div>
                    <p>Al crear, se generarÃ¡ una fila por colaborador copiando datos base (sin bonos/reembolsos).</p>
                    <button class="button button-primary">Crear quincena</button>
                </form>
            </div>

            <div class="wpn-card">
                <h2>Listado</h2>
                <table class="widefat fixed striped">
                    <thead><tr><th>ID</th><th>Etiqueta</th><th>IMSS Config</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach($qs as $q): ?>
                        <tr>
                            <td><?php echo (int)$q->id; ?></td>
                            <td><a href="<?php echo admin_url('admin.php?page=wpn-quincenas&view='.(int)$q->id); ?>"><?php echo esc_html($q->label); ?></a></td>
                            <td>$<?php echo number_format($q->imss_monto,2); ?></td>
                            <td>
                                <a class="button" href="<?php echo admin_url('admin.php?page=wpn-quincenas&view='.(int)$q->id); ?>">Ver</a>
                                <form style="display:inline" method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Â¿Eliminar quincena completa?');">
                                    <?php wp_nonce_field('wpn_delete_quincena'); ?>
                                    <input type="hidden" name="action" value="wpn_delete_quincena">
                                    <input type="hidden" name="id" value="<?php echo (int)$q->id; ?>">
                                    <button class="button button-link-delete">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // ========== NUEVAS FUNCIONES PARA BONOS/REEMBOLSOS ADMIN ==========
    
    public static function admin_add_bono(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_admin_bono');
        
        global $wpdb;
        $tb = $wpdb->prefix.'nmn_bonos';
        $tqe = $wpdb->prefix.'nmn_quincena_employees';
        
        $qe_id = intval($_POST['qe_id'] ?? 0);
        $qid = intval($_POST['qid'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $monto = WPN_Utils::sanitize_money($_POST['monto'] ?? 0);
        $tipo = sanitize_text_field($_POST['tipo'] ?? 'positivo');
        
        if ($tipo === 'negativo') {
            $monto = -abs($monto);
        }
        
        // Insertar bono
        $wpdb->insert($tb, [
            'qe_id' => $qe_id,
            'descripcion' => $descripcion,
            'monto' => $monto
        ]);
        
        // Recalcular totales
        self::recalcular_totales($qe_id);
        
        wp_redirect(admin_url('admin.php?page=wpn-quincenas&view='.$qid.'&detail='.$qe_id.'#bonos'));
        exit;
    }
    
    public static function admin_add_reembolso(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_admin_reembolso');
        
        global $wpdb;
        $tr = $wpdb->prefix.'nmn_reembolsos';
        $tqe = $wpdb->prefix.'nmn_quincena_employees';
        
        $qe_id = intval($_POST['qe_id'] ?? 0);
        $qid = intval($_POST['qid'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $monto = WPN_Utils::sanitize_money($_POST['monto'] ?? 0);
        
        // Manejar evidencia
        $evidencia_url = '';
        if (!empty($_FILES['evidencia']['name'])){
            require_once ABSPATH.'wp-admin/includes/file.php';
            $overrides = array('test_form'=>false);
            $file = wp_handle_upload($_FILES['evidencia'], $overrides);
            if (!isset($file['error'])) {
                $evidencia_url = $file['url'];
            }
        }
        
        // Insertar reembolso
        $wpdb->insert($tr, [
            'qe_id' => $qe_id,
            'descripcion' => $descripcion,
            'monto' => $monto,
            'evidencia_url' => $evidencia_url
        ]);
        
        // Recalcular totales
        self::recalcular_totales($qe_id);
        
        wp_redirect(admin_url('admin.php?page=wpn-quincenas&view='.$qid.'&detail='.$qe_id.'#reembolsos'));
        exit;
    }
    
    public static function admin_delete_bono(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_admin_bono');
        
        global $wpdb;
        $tb = $wpdb->prefix.'nmn_bonos';
        
        $bono_id = intval($_POST['bono_id'] ?? 0);
        $qe_id = intval($_POST['qe_id'] ?? 0);
        $qid = intval($_POST['qid'] ?? 0);
        
        $wpdb->delete($tb, ['id' => $bono_id]);
        
        // Recalcular totales
        self::recalcular_totales($qe_id);
        
        wp_redirect(admin_url('admin.php?page=wpn-quincenas&view='.$qid.'&detail='.$qe_id.'#bonos'));
        exit;
    }
    
    public static function admin_delete_reembolso(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_admin_reembolso');
        
        global $wpdb;
        $tr = $wpdb->prefix.'nmn_reembolsos';
        
        $reembolso_id = intval($_POST['reembolso_id'] ?? 0);
        $qe_id = intval($_POST['qe_id'] ?? 0);
        $qid = intval($_POST['qid'] ?? 0);
        
        $wpdb->delete($tr, ['id' => $reembolso_id]);
        
        // Recalcular totales
        self::recalcular_totales($qe_id);
        
        wp_redirect(admin_url('admin.php?page=wpn-quincenas&view='.$qid.'&detail='.$qe_id.'#reembolsos'));
        exit;
    }
    
    private static function recalcular_totales($qe_id){
        global $wpdb;
        $tb = $wpdb->prefix.'nmn_bonos';
        $tr = $wpdb->prefix.'nmn_reembolsos';
        $tqe = $wpdb->prefix.'nmn_quincena_employees';
        
        $bon = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto),0) FROM $tb WHERE qe_id=%d", $qe_id)));
        $reb = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto),0) FROM $tr WHERE qe_id=%d", $qe_id)));
        
        $wpdb->update($tqe, [
            'bono_total' => $bon,
            'reembolso_total' => $reb,
            'submitted' => 1
        ], ['id' => $qe_id]);
    }

    public static function create_quincena(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_create_quincena');
        global $wpdb;
        $tq = $wpdb->prefix.'nmn_quincenas';
        $tqe= $wpdb->prefix.'nmn_quincena_employees';
        $te = $wpdb->prefix.'nmn_employees';

        $label = sanitize_text_field($_POST['label'] ?? '');
        $imss  = WPN_Utils::sanitize_money($_POST['imss_monto'] ?? 4182);
        $con_bono = isset($_POST['con_bono']) ? intval($_POST['con_bono']) : 0;

        $wpdb->insert($tq, ['label'=>$label, 'imss_monto'=>$imss, 'con_bono' => $con_bono]);
        $qid = $wpdb->insert_id;

        $emps = $wpdb->get_results("SELECT * FROM $te");
        foreach($emps as $e){
            $qb = WPN_Utils::quincena_base($e->ingreso_mensual);
            $wpdb->insert($tqe, [
                'quincena_id'=>$qid,
                'employee_id'=>$e->id,
                'quincena_base'=>$qb,
                'bono_total' => ($con_bono ? floatval($e->bono_mensual) : 0),
                'reembolso_total'=>0,
                'submitted'=>0,
                'esquema'=>$e->esquema,
                'banco'=>$e->banco,
                'clabe'=>$e->clabe,
                'cuenta'=>$e->cuenta,
            ]);
            $qe_id = $wpdb->insert_id;
            if ($con_bono && floatval($e->bono_mensual) > 0){
                $tb = $wpdb->prefix.'nmn_bonos';
                $wpdb->insert($tb, [
                    'qe_id'=>$qe_id,
                    'descripcion'=>'Bono mensual',
                    'monto'=>floatval($e->bono_mensual)
                ]);
            }
        }
        wp_redirect(admin_url('admin.php?page=wpn-quincenas&view='.$qid));
        exit;
    }

    public static function delete_quincena(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_delete_quincena');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        if ($id){
            $tq = $wpdb->prefix.'nmn_quincenas';
            $tqe= $wpdb->prefix.'nmn_quincena_employees';
            $tb = $wpdb->prefix.'nmn_bonos';
            $tr = $wpdb->prefix.'nmn_reembolsos';
            $qes = $wpdb->get_col($wpdb->prepare("SELECT id FROM $tqe WHERE quincena_id=%d", $id));
            if ($qes){
                $in = implode(',', array_map('intval',$qes));
                $wpdb->query("DELETE FROM $tb WHERE qe_id IN($in)");
                $wpdb->query("DELETE FROM $tr WHERE qe_id IN($in)");
            }
            $wpdb->delete($tqe, ['quincena_id'=>$id]);
            $wpdb->delete($tq, ['id'=>$id]);
        }
        wp_redirect(admin_url('admin.php?page=wpn-quincenas'));
        exit;
    }

    public static function export_quincena(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_export_quincena');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_die('Sin ID');
        $tq = $wpdb->prefix.'nmn_quincenas';
        $tqe= $wpdb->prefix.'nmn_quincena_employees';
        $te = $wpdb->prefix.'nmn_employees';
        $q = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tq WHERE id=%d",$id));
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT qe.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.rfc, e.curp, e.regimen_sat AS regimen_sat 
            FROM $tqe qe 
            LEFT JOIN $te e ON e.id=qe.employee_id 
            WHERE qe.quincena_id=%d",$id), ARRAY_A);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="quincena_'.$q->label.'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Nombre','Apellido Paterno','Apellido Materno','RFC','RÃ©gimen SAT','Esquema','Quincena base','IMSS Config','Pago IMSS','Pago Sindicato','Bonos','Reembolsos','Total','Banco','CLABE','Cuenta']);
        foreach($rows as $r){
            $qb = floatval($r['quincena_base']);
            $bon = floatval($r['bono_total']);
            $reb = floatval($r['reembolso_total']);
            $imss_cfg = floatval($q->imss_monto);
            $pago_imss = 0; $pago_sind = 0;
            if ($r['esquema'] === 'IMSS' || $r['esquema'] === 'IMSS/Sindicato'){
                $pago_imss = min($imss_cfg, $qb + $bon);
                $pago_sind = max(0, $qb + $bon - $pago_imss);
            } else {
                $pago_sind = $qb + $bon;
            }
            $total = $pago_imss + $pago_sind + $reb;
            fputcsv($out, [
                $r['nombre'] ?? '',
                $r['apellido_paterno'] ?? '',
                $r['apellido_materno'] ?? '',
                $r['rfc'],
                $r['regimen_sat'],
                $r['esquema'],
                $qb,
                $imss_cfg,
                $pago_imss,
                $pago_sind,
                $bon,
                $reb,
                $total,
                $r['banco'],
                $r['clabe'],
                $r['cuenta']
            ]);
        }
        fclose($out);
        exit;
    }

    public static function update_qe_row(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_update_qe');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $bon = WPN_Utils::sanitize_money($_POST['bono_total'] ?? 0);
        $reb = WPN_Utils::sanitize_money($_POST['reembolso_total'] ?? 0);
        $wpdb->update($wpdb->prefix.'nmn_quincena_employees', [
            'bono_total'=>$bon,
            'reembolso_total'=>$reb
        ], ['id'=>$id]);
        wp_redirect(wp_get_referer());
        exit;
    }

    public static function sync_users(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_sync_users');
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $rows = $wpdb->get_results("SELECT * FROM $te");
        foreach($rows as $e){
            $user_id = WPN_Utils::ensure_wp_user([
                'rfc'  => $e->rfc,
                'curp' => $e->curp
            ]);
            if ($user_id && intval($e->user_id) !== intval($user_id)){
                $wpdb->update($te, ['user_id'=>$user_id], ['id'=>$e->id]);
            }
            WPN_Utils::ensure_vacation_balance($e->id, $e->fecha_ingreso);
        }
        wp_redirect(admin_url('admin.php?page=wp-nomina&synced=1'));
        exit;
    }

    public static function render_colaboradores_alta(){
        global $wpdb;
        $t = $wpdb->prefix.'nmn_employees';
        ?>
        <div class="wrap wpn-wrap">
            <h1>Alta de colaborador</h1>
            <div class="wpn-card">
                <h2>Agregar / Editar</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wpn_employee'); ?>
                    <input type="hidden" name="action" value="wpn_save_employee">
                    <input type="hidden" name="id" value="<?php echo isset($_GET['edit']) ? intval($_GET['edit']) : 0; ?>">
                    <?php
                        $editing = null;
                        if (!empty($_GET['edit'])){
                            $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", intval($_GET['edit'])), ARRAY_A);
                        }
                    ?>
                    <div class="grid-3">
                        <div>
                            <label>Nombre</label>
                            <input type="text" name="nombre" required value="<?php echo esc_attr($editing['nombre'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Apellido Paterno</label>
                            <input type="text" name="apellido_paterno" value="<?php echo esc_attr($editing['apellido_paterno'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Apellido Materno</label>
                            <input type="text" name="apellido_materno" value="<?php echo esc_attr($editing['apellido_materno'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Puesto</label>
                            <input type="text" name="puesto" value="<?php echo esc_attr($editing['puesto'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>RFC</label>
                            <input type="text" name="rfc" required value="<?php echo esc_attr($editing['rfc'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>CURP</label>
                            <input type="text" name="curp" required value="<?php echo esc_attr($editing['curp'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Correo electrÃ³nico</label>
                            <input type="email" name="email" value="<?php echo esc_attr($editing['email'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Fecha de ingreso</label>
                            <input type="date" name="fecha_ingreso" required value="<?php echo esc_attr($editing['fecha_ingreso'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Ingreso mensual</label>
                            <input type="number" step="0.01" name="ingreso_mensual" required value="<?php echo esc_attr($editing['ingreso_mensual'] ?? '0'); ?>">
                        </div>
                        <div>
                            <label>Bono mensual</label>
                            <input type="number" step="0.01" name="bono_mensual" value="<?php echo esc_attr($editing['bono_mensual'] ?? '0'); ?>">
                        </div>
                        <div>
                            <label>Esquema</label>
                            <select name="esquema">
                                <?php
                                    $opts = ['Sindicato','IMSS/Sindicato','IMSS'];
                                    $sel = $editing['esquema'] ?? 'Sindicato';
                                    foreach($opts as $o){
                                        echo '<option value="'.esc_attr($o).'" '.selected($sel,$o,false).'>'.$o.'</option>';
                                    }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label>Banco</label>
                            <input type="text" name="banco" value="<?php echo esc_attr($editing['banco'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Clabe interbancaria</label>
                            <input type="text" name="clabe" value="<?php echo esc_attr($editing['clabe'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Cuenta bancaria</label>
                            <input type="text" name="cuenta" value="<?php echo esc_attr($editing['cuenta'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>CÃ³digo postal</label>
                            <input type="text" name="cp" value="<?php echo esc_attr($editing['cp'] ?? ''); ?>" maxlength="5" pattern="[0-9]{5}" title="5 dÃ­gitos">
                        </div>
                        <div>
                            <label>RÃ©gimen SAT</label>
                            <input type="text" name="regimen_sat" value="<?php echo esc_attr($editing['regimen_sat'] ?? ''); ?>" placeholder="RÃ©gimen fiscal (SAT)">
                        </div>
                    </div>
                    <button class="button button-primary">Guardar</button>
                </form>
            </div>
        </div>
        <?php
    }

    public static function render_colaboradores_list(){
        global $wpdb;
        $t = $wpdb->prefix.'nmn_employees';
        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC");
        ?>
        <div class="wrap wpn-wrap">
            <h1>Colaboradores</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin:12px 0;">
                <?php wp_nonce_field('wpn_sync_users'); ?>
                <input type="hidden" name="action" value="wpn_sync_users">
                <button class="button">Sincronizar usuarios (RFC/CURP)</button>
            </form>
            <div class="wpn-card">
                <h2>Listado</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th><th>Nombre Completo</th><th>RFC</th><th>CURP</th><th>Fecha Ingreso</th><th>Ingreso mensual</th><th>Esquema</th><th>ContraseÃ±a</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r): ?>
                            <tr>
                                <td><?php echo intval($r->id); ?></td>
                                <td><?php echo esc_html(trim($r->nombre . ' ' . $r->apellido_paterno . ' ' . $r->apellido_materno)); ?></td>
                                <td><?php echo esc_html($r->rfc); ?></td>
                                <td><?php echo esc_html($r->curp); ?></td>
                                <td><?php echo esc_html($r->fecha_ingreso); ?></td>
                                <td>$<?php echo number_format($r->ingreso_mensual,2); ?></td>
                                <td><?php echo esc_html($r->esquema); ?></td>
                                <td>
                                    <?php if ($r->custom_password): ?>
                                        <span style="color:green;">âœ“ Personalizada</span>
                                    <?php else: ?>
                                        <span style="color:#999;">CURP</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="button" href="<?php echo admin_url('admin.php?page=wpn-colaboradores-alta&edit='.(int)$r->id); ?>">Editar</a>
                                    <?php if ($r->custom_password && $r->user_id): ?>
                                        <form style="display:inline" method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Â¿Restablecer contraseÃ±a al CURP original?');">
                                            <?php wp_nonce_field('wpn_reset_password'); ?>
                                            <input type="hidden" name="action" value="wpn_reset_password">
                                            <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                                            <button class="button">Reset Pass</button>
                                        </form>
                                    <?php endif; ?>
                                    <form style="display:inline" method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Â¿Eliminar colaborador?');">
                                        <?php wp_nonce_field('wpn_employee_delete'); ?>
                                        <input type="hidden" name="action" value="wpn_delete_employee">
                                        <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                                        <button class="button button-link-delete">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function render_vacaciones_admin(){
        if (!current_user_can('manage_options')){ wp_die('No autorizado'); }
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';
        $tv = $wpdb->prefix.'nmn_vacaciones';

        // Ensure tables exist
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
        $wpdb->query("CREATE TABLE IF NOT EXISTS $tv (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            anio_laboral INT NOT NULL,
            dias_asignados INT NOT NULL DEFAULT 0,
            dias_usados INT NOT NULL DEFAULT 0,
            UNIQUE KEY emp_anio (employee_id, anio_laboral)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : 'pendiente';
        $anio   = isset($_GET['anio']) ? intval($_GET['anio']) : 0;

        $where = " WHERE 1=1 ";
        $params = [];
        if ($estado && in_array($estado, ['pendiente','aprobada','rechazada'], true)){
            $where .= " AND s.estado=%s ";
            $params[] = $estado;
        }
        if ($anio > 0){
            $where .= " AND s.anio_laboral=%d ";
            $params[] = $anio;
        }

        $sql = "SELECT s.*, CONCAT(e.nombre, ' ', e.apellido_paterno, ' ', e.apellido_materno) AS nombre_completo, e.rfc 
                FROM $ts s 
                JOIN $te e ON e.id=s.employee_id 
                $where 
                ORDER BY s.id DESC";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

        $base_url = menu_page_url('nomina_vacaciones', false);
        ?>
        <div class="wrap">
            <h1>Vacaciones</h1>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="nomina_vacaciones">
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="pendiente"  <?php selected($estado,'pendiente'); ?>>Pendientes</option>
                    <option value="aprobada"   <?php selected($estado,'aprobada'); ?>>Aprobadas</option>
                    <option value="rechazada"  <?php selected($estado,'rechazada'); ?>>Rechazadas</option>
                </select>
                <input type="number" name="anio" placeholder="AÃ±o laboral" value="<?php echo $anio ? (int)$anio : ''; ?>" style="width:130px;">
                <button class="button">Filtrar</button>
            </form>
            
            <?php if (isset($_GET['email_resent']) && $_GET['email_resent'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>âœ… NotificaciÃ³n reenviada exitosamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['email_error']) && $_GET['email_error'] == '1'): ?>
                <div class="notice notice-error is-dismissible">
                    <p>âŒ Error al reenviar la notificaciÃ³n. Verifica que el colaborador tenga email configurado.</p>
                </div>
            <?php endif; ?>
            
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th><th>Colaborador</th><th>AÃ±o</th><th>Periodo</th><th>DÃ­as hÃ¡biles</th><th>Comentario</th><th>Estado</th><th>Email</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows): foreach($rows as $r): 
                    // Obtener informaciÃ³n de email
                    $email_status = '';
                    if ($r->estado === 'aprobada') {
                        $email_sent = isset($r->email_sent) ? intval($r->email_sent) : 0;
                        $email_sent_at = isset($r->email_sent_at) ? $r->email_sent_at : null;
                        
                        if ($email_sent && $email_sent_at) {
                            $email_status = 'âœ… Enviado<br><small>' . date('d/m/Y H:i', strtotime($email_sent_at)) . '</small>';
                        } else if ($email_sent) {
                            $email_status = 'âœ… Enviado';
                        } else {
                            $email_status = 'âŒ No enviado';
                        }
                    } else {
                        $email_status = '-';
                    }
                ?>
                    <tr>
                        <td><?php echo (int)$r->id; ?></td>
                        <td><?php echo esc_html($r->nombre_completo).' ('.esc_html($r->rfc).')'; ?></td>
                        <td><?php echo (int)$r->anio_laboral; ?></td>
                        <td><?php echo esc_html($r->fecha_inicio.' a '.$r->fecha_fin); ?></td>
                        <td><?php echo (int)$r->dias_habiles; ?></td>
                        <td><?php echo esc_html($r->comentario); ?></td>
                        <td><?php echo esc_html(ucfirst($r->estado)); ?></td>
                        <td><?php echo $email_status; ?></td>
                        <td style="white-space:nowrap;">
                            <?php $nonce = wp_create_nonce('wpn_admin_vac'); ?>
                            <?php if ($r->estado==='pendiente'): ?>
                                <button class="button button-primary" onclick="wpnShowApprovalModal(<?php echo $r->id; ?>, '<?php echo addslashes($r->nombre_completo); ?>')">Aprobar</button>
                                <a class="button" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wpn_vac_rechazar&id='.$r->id.'&_redirect='.urlencode($base_url)),'wpn_admin_vac'); ?>">Rechazar</a>
                            <?php endif; ?>
                            <?php if ($r->estado==='aprobada'): ?>
                                <a class="button button-secondary" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wpn_vac_resend&id='.$r->id.'&_redirect='.urlencode($base_url)),'wpn_admin_vac_resend'); ?>" onclick="return confirm('Â¿Reenviar notificaciÃ³n al colaborador?');">ðŸ“§ Reenviar Email</a>
                            <?php endif; ?>
                            <a class="button button-link-delete" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wpn_vac_delete&id='.$r->id.'&_redirect='.urlencode($base_url)),'wpn_admin_vac'); ?>" onclick="return confirm('Â¿Eliminar solicitud?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="9">No hay solicitudes que coincidan con el filtro.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Modal para selecciÃ³n de colaboradores -->
        <div id="wpn-approval-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000;">
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:8px; min-width:500px; max-width:700px; max-height:80vh; overflow-y:auto;">
                <h2>Aprobar Vacaciones</h2>
                <p>Selecciona a quÃ© colaboradores se les notificarÃ¡ sobre esta aprobaciÃ³n:</p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="wpn-approval-form">
                    <?php wp_nonce_field('wpn_admin_vac'); ?>
                    <input type="hidden" name="action" value="wpn_vac_aprobar">
                    <input type="hidden" name="id" id="wpn-modal-solicitud-id" value="">
                    <input type="hidden" name="_redirect" value="<?php echo esc_url($base_url); ?>">
                    
                    <div style="margin:20px 0;">
                        <p><strong>Empleado solicitante:</strong> <span id="wpn-modal-employee-name"></span></p>
                    </div>
                    
                    <div style="margin:20px 0; padding:15px; background:#f0f2f5; border-radius:8px;">
                        <h3 style="margin-top:0;">Notificar a:</h3>
                        <label style="display:block; margin-bottom:10px;">
                            <input type="checkbox" name="notify_employee" value="1" checked>
                            <strong>Al colaborador solicitante</strong>
                        </label>
                        
                        <h4 style="margin-top:15px;">Notificar tambiÃ©n a otros colaboradores:</h4>
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
                                <input type="checkbox" id="wpn-select-all-employees">
                                <strong>Seleccionar todos</strong>
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-top:20px; text-align:right;">
                        <button type="button" class="button" onclick="wpnCloseApprovalModal()">Cancelar</button>
                        <button type="submit" class="button button-primary">Aprobar y Notificar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function wpnShowApprovalModal(solicitudId, empleadoNombre) {
            document.getElementById('wpn-modal-solicitud-id').value = solicitudId;
            document.getElementById('wpn-modal-employee-name').textContent = empleadoNombre;
            document.getElementById('wpn-approval-modal').style.display = 'block';
        }
        
        function wpnCloseApprovalModal() {
            document.getElementById('wpn-approval-modal').style.display = 'none';
        }
        
        // Funcionalidad para "Seleccionar todos"
        document.addEventListener('DOMContentLoaded', function() {
            var selectAllCheckbox = document.getElementById('wpn-select-all-employees');
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
            var modal = document.getElementById('wpn-approval-modal');
            if (event.target === modal) {
                wpnCloseApprovalModal();
            }
        });
        </script>
        <?php
    }

    // Unified handlers for vacations
    public static function vac_aprobar(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpn_admin_vac')) wp_die('Nonce invÃ¡lido');
        global $wpdb;
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';
        $tv = $wpdb->prefix.'nmn_vacaciones';
        $te = $wpdb->prefix.'nmn_employees';
        $id = intval($_REQUEST['id'] ?? 0);
        $redirect = isset($_REQUEST['_redirect']) ? esc_url_raw($_REQUEST['_redirect']) : admin_url('admin.php?page=nomina_vacaciones');
        $sol = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ts WHERE id=%d", $id));
        
        if ($sol && $sol->estado !== 'aprobada'){
            $wpdb->update($ts, ['estado'=>'aprobada'], ['id'=>$id]);
            $bal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tv WHERE employee_id=%d AND anio_laboral=%d", $sol->employee_id, $sol->anio_laboral));
            if (!$bal){
                $dias_asignados = 12 + max(0, ($sol->anio_laboral-1))*2;
                $wpdb->insert($tv, ['employee_id'=>$sol->employee_id,'anio_laboral'=>$sol->anio_laboral,'dias_asignados'=>$dias_asignados,'dias_usados'=>0]);
                $bal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tv WHERE employee_id=%d AND anio_laboral=%d", $sol->employee_id, $sol->anio_laboral));
            }
            if ($bal){
                $new_used = max(0, intval($bal->dias_usados) + intval($sol->dias_habiles));
                $wpdb->update($tv, ['dias_usados'=>$new_used], ['id'=>$bal->id]);
            }
            
            // Obtener empleado solicitante
            $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE id=%d", $sol->employee_id));
            
            // Obtener lista de colaboradores a notificar
            $notify_employee = isset($_POST['notify_employee']) ? true : false;
            $notify_others = isset($_POST['notify_others']) ? array_map('intval', $_POST['notify_others']) : [];
            
            $employees_to_notify = [];
            
            // Agregar empleado solicitante si estÃ¡ marcado
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
                WPN_Email::notify_vacation_approved_multiple($emp, $employees_to_notify, $sol->anio_laboral, $sol->fecha_inicio, $sol->fecha_fin, $sol->dias_habiles, $id);
            }
        }
        wp_safe_redirect($redirect);
        exit;
    }

    public static function vac_rechazar(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpn_admin_vac')) wp_die('Nonce invÃ¡lido');
        global $wpdb;
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';
        $id = intval($_REQUEST['id'] ?? 0);
        $redirect = isset($_REQUEST['_redirect']) ? esc_url_raw($_REQUEST['_redirect']) : admin_url('admin.php?page=nomina_vacaciones');
        $sol = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ts WHERE id=%d", $id));
        if ($sol){
            $wpdb->update($ts, ['estado'=>'rechazada'], ['id'=>$id]);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    public static function vac_delete(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpn_admin_vac')) wp_die('Nonce invÃ¡lido');
        global $wpdb;
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';
        $tv = $wpdb->prefix.'nmn_vacaciones';
        $id = intval($_REQUEST['id'] ?? 0);
        $redirect = isset($_REQUEST['_redirect']) ? esc_url_raw($_REQUEST['_redirect']) : admin_url('admin.php?page=nomina_vacaciones');
        $sol = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ts WHERE id=%d", $id));
        if ($sol){
            if ($sol->estado === 'aprobada'){
                $bal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tv WHERE employee_id=%d AND anio_laboral=%d", $sol->employee_id, $sol->anio_laboral));
                if ($bal){
                    $new_used = max(0, intval($bal->dias_usados) - intval($sol->dias_habiles));
                    $wpdb->update($tv, ['dias_usados'=>$new_used], ['id'=>$bal->id]);
                }
            }
            $wpdb->delete($ts, ['id'=>$id]);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    // Reporte de Vacaciones
    public static function render_vacaciones_reporte(){
        if (!current_user_can('manage_options')){ wp_die('No permitido'); }
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $tv = $wpdb->prefix.'nmn_vacaciones';
        
        $emps = $wpdb->get_results("SELECT id, nombre, apellido_paterno, apellido_materno, rfc, fecha_ingreso FROM $te ORDER BY nombre ASC");
        ?>
        <div class="wrap wpn-wrap">
            <h1>Reporte de Vacaciones</h1>
            <p>Resumen por colaborador del <strong>saldo disponible</strong> de vacaciones (aÃ±o laboral mÃ¡s reciente).</p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:28%">Colaborador</th>
                        <th style="width:18%">RFC</th>
                        <th style="width:14%; text-align:right">AÃ±o laboral</th>
                        <th style="width:14%; text-align:right">Asignados</th>
                        <th style="width:14%; text-align:right">Usados</th>
                        <th style="width:12%; text-align:right">Disponibles</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($emps): foreach($emps as $e):
                    $nombre_completo = trim($e->nombre . ' ' . $e->apellido_paterno . ' ' . $e->apellido_materno);
                    $bal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tv WHERE employee_id=%d ORDER BY anio_laboral DESC LIMIT 1", $e->id));
                    if ($bal){
                        $anio = intval($bal->anio_laboral);
                        $asign = intval($bal->dias_asignados);
                        $used  = intval($bal->dias_usados);
                        $disp  = max(0, $asign - $used);
                    } else {
                        $anio = 1;
                        $asign = 12;
                        $used = 0;
                        if (!empty($e->fecha_ingreso)){
                            try {
                                $fi = new DateTime($e->fecha_ingreso);
                                $hoy = new DateTime(current_time('Y-m-d'));
                                $years = $fi->diff($hoy)->y + 1;
                                if ($years < 1) $years = 1;
                                $anio = $years;
                                $asign = 12 + max(0, ($anio - 1))*2;
                            } catch (Exception $ex){ }
                        }
                        $disp = $asign;
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html($nombre_completo ?: '(Sin nombre)'); ?></td>
                        <td><?php echo esc_html($e->rfc ?: ''); ?></td>
                        <td style="text-align:right"><?php echo esc_html($anio); ?></td>
                        <td style="text-align:right"><?php echo number_format($asign, 0); ?></td>
                        <td style="text-align:right"><?php echo number_format($used, 0); ?></td>
                        <td style="text-align:right"><strong><?php echo number_format($disp, 0); ?></strong></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">No hay colaboradores.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_export_custom(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        global $wpdb;
        $tq = $wpdb->prefix.'nmn_quincenas';
        $qs = $wpdb->get_results("SELECT id, label FROM $tq ORDER BY id DESC");
        ?>
        <div class="wrap wpn-wrap">
            <h1>ExportaciÃ³n personalizada</h1>
            <div class="wpn-card" style="max-width:640px;">
                <h2>Selecciona la quincena</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('wpn_export_custom'); ?>
                    <input type="hidden" name="action" value="wpn_export_custom">
                    <label>Quincena</label>
                    <select name="qid" required>
                        <option value="">-- Elegir --</option>
                        <?php foreach($qs as $q): ?>
                            <option value="<?php echo (int)$q->id; ?>"><?php echo esc_html($q->label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button button-primary" style="margin-left:8px;">Descargar CSV</button>
                </form>
            </div>
        </div>
        <?php
    }

    public static function export_custom(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        check_admin_referer('wpn_export_custom');
        $qid = isset($_POST['qid']) ? intval($_POST['qid']) : 0;
        if (!$qid) wp_die('Sin quincena seleccionada');

        global $wpdb;
        $tq  = $wpdb->prefix.'nmn_quincenas';
        $tqe = $wpdb->prefix.'nmn_quincena_employees';
        $te  = $wpdb->prefix.'nmn_employees';

        $q = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tq WHERE id=%d", $qid));
        if (!$q) wp_die('Quincena no encontrada');

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT qe.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.rfc, e.curp, e.cp, e.regimen_sat, e.banco, e.clabe
            FROM $tqe qe
            INNER JOIN $te e ON e.id = qe.employee_id
            WHERE qe.quincena_id = %d
            ORDER BY e.nombre ASC
        ", $qid), ARRAY_A);

        header("Content-Type: text/csv; charset=utf-8");
        $fname = "export_custom_" . sanitize_title($q->label) . ".csv";
        header("Content-Disposition: attachment; filename=" . $fname);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['NOMBRE','APELLIDO PATERNO','APELLIDO MATERNO','RFC','CURP','CODIGO POSTAL','REGIMEN FISCAL','BANCO','CLABE','MONTO DE SINDICATO + BONO (EXCLUYE IMSS)']);

        $imss_cfg = isset($q->imss_monto) ? floatval($q->imss_monto) : 0.0;
        foreach($rows as $r){
            $qb  = isset($r['quincena_base']) ? floatval($r['quincena_base']) : 0.0;
            $bon = isset($r['bono_total']) ? floatval($r['bono_total']) : 0.0;
            $esq = isset($r['esquema']) ? $r['esquema'] : '';

            $pago_sind = 0.0;
            if ($esq === 'IMSS' || $esq === 'IMSS/Sindicato'){
                $pago_imss = min($imss_cfg, $qb + $bon);
                $pago_sind = max(0.0, $qb + $bon - $pago_imss);
            } else {
                $pago_sind = $qb + $bon;
            }

            fputcsv($out, [
                isset($r['nombre']) ? $r['nombre'] : '',
                isset($r['apellido_paterno']) ? $r['apellido_paterno'] : '',
                isset($r['apellido_materno']) ? $r['apellido_materno'] : '',
                isset($r['rfc']) ? $r['rfc'] : '',
                isset($r['curp']) ? $r['curp'] : '',
                isset($r['cp']) ? $r['cp'] : '',
                isset($r['regimen_sat']) ? $r['regimen_sat'] : '',
                isset($r['banco']) ? $r['banco'] : '',
                isset($r['clabe']) ? $r['clabe'] : '',
                number_format($pago_sind, 2, '.', '')
            ]);
        }
        fclose($out);
        exit;
    }

    public static function cleanup_submenu(){
        remove_submenu_page('wp-nomina', 'wp-nomina');
    }
    
    // FunciÃ³n para reenviar notificaciÃ³n de vacaciones aprobadas
    public static function vac_resend_notification(){
        if (!current_user_can('manage_options')) wp_die('No permitido');
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpn_admin_vac_resend')) wp_die('Nonce invÃ¡lido');
        
        global $wpdb;
        $ts = $wpdb->prefix.'nmn_vac_solicitudes';
        $te = $wpdb->prefix.'nmn_employees';
        $id = intval($_REQUEST['id'] ?? 0);
        $redirect = isset($_REQUEST['_redirect']) ? esc_url_raw($_REQUEST['_redirect']) : admin_url('admin.php?page=nomina_vacaciones');
        
        $sol = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ts WHERE id=%d AND estado='aprobada'", $id));
        
        if ($sol) {
            $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE id=%d", $sol->employee_id));
            
            if ($emp && class_exists('WPN_Email')) {
                $result = WPN_Email::notify_vacation_approved($emp, $sol->anio_laboral, $sol->fecha_inicio, $sol->fecha_fin, $sol->dias_habiles, $id);
                
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
