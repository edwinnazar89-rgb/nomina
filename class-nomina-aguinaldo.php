<?php
if (!defined('ABSPATH')) exit;

class WPN_Aguinaldo {
    
    public static function init(){
        add_action('admin_menu', [__CLASS__, 'add_submenu'], 30);
        add_action('admin_post_wpn_export_aguinaldo', [__CLASS__, 'export_aguinaldo']);
    }
    
    public static function add_submenu(){
        if (!current_user_can('manage_options')) return;
        
        add_submenu_page(
            'wp-nomina',
            'Calcular Aguinaldo',
            'Calcular Aguinaldo',
            'manage_options',
            'wpn-aguinaldo',
            [__CLASS__, 'render_page']
        );
    }
    
    public static function render_page(){
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
        
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        
        // AÃ±o seleccionado (por defecto el actual)
        $year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
        $current_year = intval(date('Y'));
        
        ?>
        <div class="wrap wpn-wrap">
            <h1>Calcular Aguinaldo</h1>
            
            <div class="wpn-card">
                <h2>Seleccionar PerÃÆ’Ã†â€™Ãâ€šÃ‚Â­odo</h2>
                <form method="get">
                    <input type="hidden" name="page" value="wpn-aguinaldo">
                    <div style="display:flex;gap:12px;align-items:center;">
                        <label>AÃ±o:</label>
                        <select name="year" onchange="this.form.submit()">
                            <?php for($y = $current_year; $y >= $current_year - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <span class="description">El aguinaldo se calcula con 15 dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as de salario por aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o trabajado</span>
                    </div>
                </form>
            </div>
            
            <div class="wpn-card">
                <h2>CÃÆ’Ã‚Â¡lculo de Aguinaldo <?php echo $year; ?></h2>
                <p class="description">PerÃÆ’Ã†â€™Ãâ€šÃ‚Â­odo de cÃÆ’Ã†â€™Ãâ€šÃ‚Â¡lculo: 1 de enero al 31 de diciembre del <?php echo $year; ?></p>
                
                <?php
                $employees = $wpdb->get_results("SELECT * FROM $te ORDER BY nombre ASC");
                
                if (empty($employees)) {
                    echo '<p>No hay colaboradores registrados.</p>';
                } else {
                    $aguinaldos = [];
                    foreach($employees as $emp) {
                        $aguinaldo_data = self::calcular_aguinaldo($emp, $year);
                        if ($aguinaldo_data) {
                            $aguinaldos[] = $aguinaldo_data;
                        }
                    }
                    ?>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-bottom:12px;">
                        <?php wp_nonce_field('wpn_export_aguinaldo'); ?>
                        <input type="hidden" name="action" value="wpn_export_aguinaldo">
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        <button type="submit" class="button button-primary">
                            ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃâ€šÃ‚Â¥ Exportar Aguinaldo CSV
                        </button>
                    </form>
                    
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width:25%">Colaborador</th>
                                <th style="width:10%">RFC</th>
                                <th style="width:12%">Fecha Ingreso</th>
                                <th style="width:10%">DÃ­as Laborados</th>
                                <th style="width:10%">AÃ±os Completos</th>
                                <th style="width:13%">Salario Mensual</th>
                                <th style="width:10%">Salario Diario</th>
                                <th style="width:10%"><strong>Aguinaldo</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_aguinaldo = 0;
                            foreach($aguinaldos as $a): 
                                $total_aguinaldo += $a['monto_aguinaldo'];
                            ?>
                            <tr>
                                <td><?php echo esc_html($a['nombre_completo']); ?></td>
                                <td><?php echo esc_html($a['rfc']); ?></td>
                                <td><?php echo esc_html($a['fecha_ingreso']); ?></td>
                                <td>
                                    <?php 
                                    echo number_format($a['dias_laborados']);
                                    if ($a['dias_laborados'] == 365) {
                                        echo ' <span class="description">(aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o completo)</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo number_format($a['anos_completos'], 2); ?></td>
                                <td>$<?php echo number_format($a['salario_mensual'], 2); ?></td>
                                <td>$<?php echo number_format($a['salario_diario'], 2); ?></td>
                                <td><strong>$<?php echo number_format($a['monto_aguinaldo'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight:bold;">
                                <td colspan="7" style="text-align:right;">Total Aguinaldo:</td>
                                <td>$<?php echo number_format($total_aguinaldo, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="wpn-card" style="margin-top:20px;background:#f8f9fa;">
                        <h3>ÃÆ’Ã‚Â°Ãâ€¦Ã‚Â¸ÃÂ¢Ã¢â€šÂ¬Ã…â€œÃâ€¦Ã‚Â  Resumen</h3>
                        <p>Total de colaboradores: <strong><?php echo count($aguinaldos); ?></strong></p>
                        <p>Monto total de aguinaldo: <strong>$<?php echo number_format($total_aguinaldo, 2); ?></strong></p>
                        <p class="description">
                            * El aguinaldo se calcula con base en 15 dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as de salario por aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o trabajado<br>
                            * El cÃÆ’Ã†â€™Ãâ€šÃ‚Â¡lculo considera desde el 1ÃÆ’Ã¢â‚¬Å¡Ãâ€šÃ‚Â° de enero hasta el 31 de diciembre del <?php echo $year; ?><br>
                            * Empleados que ingresaron antes del <?php echo $year; ?>: se consideran 365 dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as laborados (aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o completo)<br>
                            * Empleados que ingresaron durante el <?php echo $year; ?>: dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as naturales desde su ingreso hasta el 31 de diciembre<br>
                            * FÃÆ’Ã†â€™Ãâ€šÃ‚Â³rmula: (15 dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as ÃÆ’Ã†â€™Ãâ€šÃ‚Â· 365 dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as) ÃÆ’Ã†â€™ÃÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as laborados ÃÆ’Ã†â€™ÃÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â salario diario
                        </p>
                    </div>
                    
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private static function calcular_aguinaldo($emp, $year) {
        // Validar fecha de ingreso
        if (empty($emp->fecha_ingreso)) {
            return null;
        }
        
        $fecha_ingreso = new DateTime($emp->fecha_ingreso);
        $inicio_year = new DateTime("$year-01-01");
        $fin_year = new DateTime("$year-12-31");
        
        // Si el empleado ingresÃÆ’Ã†â€™Ãâ€šÃ‚Â³ despuÃÆ’Ã†â€™Ãâ€šÃ‚Â©s del aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o seleccionado, no aplica
        if ($fecha_ingreso > $fin_year) {
            return null;
        }
        
        // Determinar dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as laborados en el aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o
        if ($fecha_ingreso < $inicio_year) {
            // Si ingresÃÆ’Ã†â€™Ãâ€šÃ‚Â³ ANTES del aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o en curso -> aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o completo (365 dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as)
            $dias_laborados = 365;
        } else {
            // Si ingresÃÆ’Ã†â€™Ãâ€šÃ‚Â³ DURANTE el aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o -> calcular dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as naturales desde ingreso hasta 31 de diciembre
            // Calcular dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as naturales correctamente
            $diff = $fecha_ingreso->diff($fin_year);
            $dias_laborados = $diff->days + 1; // +1 para incluir el dÃÆ’Ã†â€™Ãâ€šÃ‚Â­a de ingreso
        }
        
        // Calcular aÃÆ’Ã†â€™Ãâ€šÃ‚Â±os completos trabajados hasta el 31 de diciembre del aÃÆ’Ã†â€™Ãâ€šÃ‚Â±o seleccionado
        $diff_total = $fecha_ingreso->diff($fin_year);
        $anos_completos = $diff_total->y + ($diff_total->m / 12) + ($diff_total->d / 365);
        
        // Salario diario
        $salario_mensual = floatval($emp->ingreso_mensual);
        $salario_diario = $salario_mensual / 30;
        
        // CÃÆ’Ã‚Â¡lculo del aguinaldo proporcional a los dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as laborados
        // FÃÆ’Ã†â€™Ãâ€šÃ‚Â³rmula: (15 dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as / 365 dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as) * dÃÆ’Ã†â€™Ãâ€šÃ‚Â­as laborados * salario diario
        $monto_aguinaldo = (15 / 365) * $dias_laborados * $salario_diario;
        
        // Obtener CP si existe la columna
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $cp = '';
        $cols = $wpdb->get_col("DESC $te", 0);
        if (in_array('cp', $cols)) {
            $cp = $emp->cp;
        }
        
        // Devolver con campos separados para exportaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n
        return [
            'nombre' => $emp->nombre,
            'apellido_paterno' => $emp->apellido_paterno ?? '',
            'apellido_materno' => $emp->apellido_materno ?? '',
            'nombre_completo' => trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno),
            'rfc' => $emp->rfc,
            'curp' => $emp->curp,
            'cp' => $cp,
            'regimen_sat' => isset($emp->regimen_sat) ? $emp->regimen_sat : '',
            'banco' => $emp->banco,
            'clabe' => $emp->clabe,
            'fecha_ingreso' => $emp->fecha_ingreso,
            'dias_laborados' => $dias_laborados,
            'anos_completos' => $anos_completos,
            'salario_mensual' => $salario_mensual,
            'salario_diario' => $salario_diario,
            'monto_aguinaldo' => $monto_aguinaldo
        ];
    }
    
    public static function export_aguinaldo(){
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }
        
        check_admin_referer('wpn_export_aguinaldo');
        
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $year = isset($_POST['year']) ? intval($_POST['year']) : intval(date('Y'));
        
        $employees = $wpdb->get_results("SELECT * FROM $te ORDER BY nombre ASC");
        
        // Preparar datos para exportaciÃÆ’Ã†â€™Ãâ€šÃ‚Â³n
        $aguinaldos = [];
        foreach($employees as $emp) {
            $aguinaldo_data = self::calcular_aguinaldo($emp, $year);
            if ($aguinaldo_data) {
                $aguinaldos[] = $aguinaldo_data;
            }
        }
        
        // Generar CSV
        $filename = 'aguinaldo_' . $year . '_' . date('Ymd') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // UTF-8 BOM para Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Encabezados
        fputcsv($output, [
            'NOMBRE',
            'APELLIDO PATERNO',
            'APELLIDO MATERNO',
            'RFC', 
            'CURP',
            'CODIGO POSTAL',
            'REGIMEN FISCAL',
            'BANCO',
            'CLABE',
            'MONTO DE AGUINALDO'
        ]);
        
        // Datos
        foreach($aguinaldos as $a) {
            fputcsv($output, [
                $a['nombre'],
                $a['apellido_paterno'],
                $a['apellido_materno'],
                $a['rfc'],
                $a['curp'],
                $a['cp'],
                $a['regimen_sat'],
                $a['banco'],
                $a['clabe'],
                number_format($a['monto_aguinaldo'], 2, '.', '')
            ]);
        }
        
        fclose($output);
        exit;
    }
}
