<?php
if (!defined('ABSPATH')) exit;

class WPN_Mi_Informacion {

    public static function init(){
        add_action('admin_post_nomina_update_info', [__CLASS__, 'handle_post']);
        add_action('admin_post_nopriv_nomina_update_info', [__CLASS__, 'handle_post']);
        add_action('admin_post_nomina_update_password', [__CLASS__, 'handle_password']);
        add_action('admin_post_nopriv_nomina_update_password', [__CLASS__, 'handle_password']);
        add_shortcode('nomina_mi_informacion', [__CLASS__, 'shortcode']);
        add_action('template_redirect', [__CLASS__, 'maybe_catch_route']);
    }

    public static function shortcode(){
        return self::render_form();
    }

    public static function maybe_catch_route(){
        if (isset($_GET['nomina_action']) && $_GET['nomina_action']==='mi_informacion'){
            status_header(200);
            nocache_headers();
            echo self::wrap_container(self::render_form());
            exit;
        }
    }

    private static function wrap_container($html){
        ob_start(); ?>
        <div class="wpn-container" style="max-width:860px;margin:24px auto;padding:16px;">
            <?php echo $html; ?>
        </div>
        <?php return ob_get_clean();
    }

    private static function get_employee_for_current_user(){
        $user = wp_get_current_user();
        if (!$user || !$user->ID) return null;

        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';

        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE user_id=%d", $user->ID));
        if ($emp) return $emp;

        $emp_by_rfc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(rfc)=UPPER(%s)", $user->user_login));
        if ($emp_by_rfc){
            $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp_by_rfc->id]);
            return $emp_by_rfc;
        }

        $cols = $wpdb->get_col("DESC $te", 0);
        if (in_array('email', $cols) && !empty($user->user_email)){
            $emp_by_email = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(email)=UPPER(%s)", $user->user_email));
            if ($emp_by_email){
                $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp_by_email->id]);
                return $emp_by_email;
            }
        }
        return null;
    }

    public static function render_form(){
        if (!is_user_logged_in()){
            return '<div class="wpn-box">Debes iniciar sesiÃ³n para ver tu informaciÃ³n.</div>';
        }
        $emp = self::get_employee_for_current_user();
        if (!$emp){
            return '<div class="wpn-box">No encontramos tu registro de empleado. Contacta a Recursos Humanos.</div>';
        }

        $action = esc_url(admin_url('admin-post.php'));
        $redirect = esc_url(add_query_arg(['updated'=>'1'], remove_query_arg(['updated','password_updated'])));
        
        // Construir nombre completo
        $nombre_completo = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
        
        ob_start(); ?>
        <div class="wpn-card">
            <h2 style="margin:0 0 12px;">Mi informaciÃ³n</h2>
            <p style="margin:0 0 16px;">Actualiza tus datos bancarios y rÃ©gimen SAT. Tu RFC, CURP y fecha de ingreso son de solo lectura.</p>

            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success" style="padding:10px;border-left:4px solid #46b450;background:#f5fff8;margin-bottom:16px;">
                    Datos actualizados con Ã©xito.
                </div>
            <?php endif; ?>
            
            <?php if (!empty($_GET['password_updated'])): ?>
                <div class="notice notice-success" style="padding:10px;border-left:4px solid #46b450;background:#f5fff8;margin-bottom:16px;">
                    âœ… ContraseÃ±a actualizada exitosamente.
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['password_error'])): ?>
                <div class="notice notice-error" style="padding:10px;border-left:4px solid #dc3232;background:#fef2f2;margin-bottom:16px;">
                    Error: La contraseÃ±a actual es incorrecta.
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo $action; ?>" class="wpn-form" style="display:block;max-width:680px;">
                <?php wp_nonce_field('nomina_update_info', '_wpnonce'); ?>
                <input type="hidden" name="action" value="nomina_update_info">
                <input type="hidden" name="_redirect" value="<?php echo esc_attr($redirect); ?>">

                <div class="wpn-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label class="wpn-field" style="grid-column:span 2;">Nombre Completo
                        <input type="text" value="<?php echo esc_attr($nombre_completo); ?>" disabled>
                    </label>
                    
                    <label class="wpn-field">Puesto
                        <input type="text" value="<?php echo esc_attr($emp->puesto); ?>" disabled>
                    </label>
                    <div></div>

                    <label class="wpn-field">RFC
                        <input type="text" value="<?php echo esc_attr($emp->rfc); ?>" disabled>
                    </label>
                    <label class="wpn-field">CURP
                        <input type="text" value="<?php echo esc_attr($emp->curp); ?>" disabled>
                    </label>

                    <label class="wpn-field">Fecha de ingreso
                        <input type="date" value="<?php echo esc_attr($emp->fecha_ingreso); ?>" disabled>
                    </label>
                    <div></div>

                    <label class="wpn-field">Banco
                        <input type="text" name="banco" value="<?php echo esc_attr($emp->banco); ?>" maxlength="191" required>
                    </label>
                    <label class="wpn-field">Cuenta bancaria
                        <input type="text" name="cuenta" value="<?php echo esc_attr($emp->cuenta); ?>" maxlength="30" pattern="[0-9\- ]{4,30}">
                    </label>

                    <label class="wpn-field">CLABE
                        <input type="text" name="clabe" value="<?php echo esc_attr($emp->clabe); ?>" maxlength="18" pattern="[0-9]{18}" title="18 dÃ­gitos">
                    </label>
                    <label class="wpn-field">RÃ©gimen SAT
                        <input type="text" name="regimen_sat" value="<?php echo esc_attr($emp->regimen_sat); ?>" maxlength="191">
                    </label>
                    
                    <label class="wpn-field">CÃ³digo postal
                        <input type="text" name="cp" value="<?php echo esc_attr(isset($emp->cp) ? $emp->cp : ''); ?>" maxlength="5" pattern="[0-9]{5}" title="5 dÃ­gitos">
                    </label>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" class="button button-primary">Guardar cambios</button>
                    <a href="javascript:history.back()" class="button">Regresar</a>
                </div>
            </form>
            
            <!-- SecciÃ³n de Cambio de ContraseÃ±a -->
            <hr style="margin:30px 0;">
            <h3>ðŸ”’ Cambiar ContraseÃ±a</h3>
            <p style="color:#666;font-size:14px;">
                Por defecto, tu contraseÃ±a es tu CURP. Puedes cambiarla aquÃ­ por mayor seguridad.
                <?php if ($emp->custom_password): ?>
                    <br><span style="color:green;">âœ… Actualmente tienes una contraseÃ±a personalizada.</span>
                <?php endif; ?>
            </p>
            
            <form method="post" action="<?php echo $action; ?>" class="wpn-form" style="display:block;max-width:680px;">
                <?php wp_nonce_field('nomina_update_password', '_wpnonce'); ?>
                <input type="hidden" name="action" value="nomina_update_password">
                
                <div class="wpn-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label class="wpn-field">ContraseÃ±a Actual
                        <input type="password" name="current_password" required>
                        <span style="font-size:12px;color:#666;">
                            <?php if (!$emp->custom_password): ?>
                                (Tu CURP actual)
                            <?php endif; ?>
                        </span>
                    </label>
                    <div></div>
                    
                    <label class="wpn-field">Nueva ContraseÃ±a
                        <input type="password" name="new_password" required minlength="6">
                        <span style="font-size:12px;color:#666;">MÃ­nimo 6 caracteres</span>
                    </label>
                    
                    <label class="wpn-field">Confirmar Nueva ContraseÃ±a
                        <input type="password" name="confirm_password" required minlength="6">
                    </label>
                </div>
                
                <div style="margin-top:16px;">
                    <button type="submit" class="button button-primary">Cambiar ContraseÃ±a</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($_GET['updated']) || !empty($_GET['password_updated'])): ?>
        <script id="wpn-auto-return">
        (function(){
          try{
            setTimeout(function(){
              if (window.history && history.length > 2){
                history.go(-2);
                return;
              }
              if (window.history && history.length > 1){
                history.back();
              }
            }, 3000);
          }catch(e){}
        })();
        </script>
        <?php endif; ?>
        
        <?php return ob_get_clean();
    }

    public static function handle_post(){
        if (!is_user_logged_in()){ wp_safe_redirect(home_url('/')); exit; }
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'nomina_update_info')){
            wp_die('Nonce invÃ¡lido', 403);
        }
        $redirect = isset($_POST['_redirect']) ? esc_url_raw($_POST['_redirect']) : home_url('/');

        $user = wp_get_current_user();
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';

        $cols = $wpdb->get_col("DESC $te", 0);
        $fields = [];
        $banco = isset($_POST['banco']) ? sanitize_text_field($_POST['banco']) : '';
        $cuenta = isset($_POST['cuenta']) ? preg_replace('/[^0-9\- ]/','', $_POST['cuenta']) : '';
        $clabe  = isset($_POST['clabe']) ? preg_replace('/[^0-9]/','', $_POST['clabe']) : '';
        $regimen = isset($_POST['regimen_sat']) ? sanitize_text_field($_POST['regimen_sat']) : '';
        $cp = isset($_POST['cp']) ? preg_replace('/[^0-9]/','', $_POST['cp']) : '';
        
        if (in_array('banco', $cols)) $fields['banco'] = $banco;
        if (in_array('cuenta', $cols)) $fields['cuenta'] = $cuenta;
        if (in_array('clabe', $cols)) $fields['clabe'] = $clabe;
        if (in_array('regimen_sat', $cols)) $fields['regimen_sat'] = $regimen;
        if (in_array('cp', $cols)) $fields['cp'] = $cp;
        
        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE user_id=%d", $user->ID));
        if (!$emp){
            $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(rfc)=UPPER(%s)", $user->user_login));
            if ($emp){
                $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp->id]);
            }
        }
        if (!$emp){ wp_safe_redirect($redirect); exit; }

        if (!empty($fields)){
            $wpdb->update($te, $fields, ['id'=>$emp->id]);
        }

        wp_safe_redirect($redirect);
        exit;
    }
    
    public static function handle_password(){
        if (!is_user_logged_in()){ 
            wp_safe_redirect(home_url('/')); 
            exit; 
        }
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'nomina_update_password')){
            wp_die('Nonce invÃ¡lido', 403);
        }
        
        $user = wp_get_current_user();
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        
        // Obtener empleado
        $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE user_id=%d", $user->ID));
        if (!$emp){
            $emp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $te WHERE UPPER(rfc)=UPPER(%s)", $user->user_login));
            if ($emp){
                $wpdb->update($te, ['user_id'=>$user->ID], ['id'=>$emp->id]);
            }
        }
        
        if (!$emp){ 
            wp_safe_redirect(home_url('/')); 
            exit; 
        }
        
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Verificar que las contraseÃ±as nuevas coincidan
        if ($new_password !== $confirm_password){
            wp_safe_redirect(add_query_arg(['password_error'=>'mismatch'], wp_get_referer()));
            exit;
        }
        
        // Verificar contraseÃ±a actual
        $valid = false;
        
        // Si tiene contraseÃ±a personalizada, verificar contra la contraseÃ±a de WordPress
        if ($emp->custom_password){
            $valid = wp_check_password($current_password, $user->data->user_pass, $user->ID);
        }
        // Si no tiene contraseÃ±a personalizada, verificar contra CURP
        else {
            $valid = (strtoupper($current_password) === strtoupper($emp->curp));
        }
        
        if (!$valid){
            wp_safe_redirect(add_query_arg(['password_error'=>'1'], wp_get_referer()));
            exit;
        }
        
        // Actualizar contraseÃ±a
        wp_set_password($new_password, $user->ID);
        
        // Marcar como contraseÃ±a personalizada
        $wpdb->update($te, ['custom_password'=>1], ['id'=>$emp->id]);
        
        // Re-autenticar al usuario para que no pierda la sesiÃ³n
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        // Redireccionar con mensaje de Ã©xito
        wp_safe_redirect(add_query_arg(['password_updated'=>'1'], wp_get_referer()));
        exit;
    }
}
