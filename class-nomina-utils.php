<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPN_Utils {

    public static function sanitize_money($val){
        $val = str_replace([',','$',' '],'',$val);
        return is_numeric($val) ? round(floatval($val),2) : 0;
    }

    public static function days_between_business($start, $end){
        // count weekdays Mon-Fri
        $start_ts = strtotime($start);
        $end_ts   = strtotime($end);
        if ($end_ts < $start_ts) return 0;
        $days = 0;
        for($t=$start_ts; $t <= $end_ts; $t += 86400){
            $w = date('N', $t); // 1..7
            if ($w <= 5) $days++;
        }
        return $days;
    }

    public static function current_labor_year($fecha_ingreso, $date = null){
        $date = $date ? $date : date('Y-m-d');
        $start = new DateTime($fecha_ingreso);
        $now   = new DateTime($date);
        $years = $start->diff($now)->y; // completed years
        return $years + 1; // aÃ±o laboral (1-based)
    }

    public static function labor_year_options($fecha_ingreso){
        $now = new DateTime();
        $start = new DateTime($fecha_ingreso);
        $years = $start->diff($now)->y + 1;
        $out = [];
        for($i=1;$i<=$years;$i++){
            $out[] = $i;
        }
        return $out;
    }

    public static function dias_asignados_por_anio($anio_laboral){
        // AÃ±o 1: 12 dÃ­as; cada aÃ±o +2
        return 12 + max(0, ($anio_laboral-1))*2;
    }

    public static function ensure_vacation_balance($employee_id, $fecha_ingreso){
        global $wpdb;
        $table = $wpdb->prefix.'nmn_vacaciones';
        $options = self::labor_year_options($fecha_ingreso);
        foreach($options as $anio){
            $dias = self::dias_asignados_por_anio($anio);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE employee_id=%d AND anio_laboral=%d", $employee_id, $anio
            ));
            if (!$exists){
                $wpdb->insert($table, [
                    'employee_id'=>$employee_id,
                    'anio_laboral'=>$anio,
                    'dias_asignados'=>$dias,
                    'dias_usados'=>0
                ]);
            } else {
                // Update assigned days if formula changed
                $wpdb->update($table, ['dias_asignados'=>$dias], ['id'=>$exists]);
            }
        }
    }

    public static function ensure_wp_user($employee){
        // Create or sync a WP user with RFC as username and CURP as password.
        $username = sanitize_user($employee['rfc']);
        if (empty($username)) return 0;

        $user_id = username_exists($username);
        if (!$user_id){
            $user_id = wp_create_user($username, $employee['curp'], $username.'@example.com');
            if (!is_wp_error($user_id)){
                $u = new WP_User($user_id);
                $u->set_role('subscriber');
            } else {
                return 0;
            }
        } else {
            // reset password to CURP to keep rule consistent
            wp_set_password($employee['curp'], $user_id);
        }
        return $user_id;
    }

    public static function esquema_imss_visible($esquema){
        return in_array($esquema, ['IMSS','IMSS/Sindicato'], true);
    }

    public static function sueldo_diario($ingreso_mensual){
        return round(floatval($ingreso_mensual)/30, 2);
    }
    public static function quincena_base($ingreso_mensual){
        return round(floatval($ingreso_mensual)/2, 2);
    }
}
