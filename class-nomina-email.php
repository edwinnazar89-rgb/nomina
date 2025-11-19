<?php
if (!defined('ABSPATH')) exit;

class WPN_Email {
    
    /**
     * Obtener emails de administradores
     */
    public static function get_admin_emails() {
        $admins = get_users(['role' => 'administrator']);
        $emails = [];
        foreach ($admins as $admin) {
            if (!empty($admin->user_email)) {
                $emails[] = $admin->user_email;
            }
        }
        return $emails;
    }
    
    /**
     * Obtener emails de todos los colaboradores
     */
    public static function get_all_employee_emails() {
        global $wpdb;
        $te = $wpdb->prefix.'nmn_employees';
        $results = $wpdb->get_col("SELECT email FROM $te WHERE email IS NOT NULL AND email != ''");
        return $results;
    }
    
    /**
     * Notificar captura de bono/reembolso a administradores
     */
    public static function notify_bono_reembolso($emp, $tipo, $monto, $descripcion, $quincena_label) {
        $admin_emails = self::get_admin_emails();
        if (empty($admin_emails)) return;
        
        $nombre_completo = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
        $tipo_texto = ($tipo === 'bono') ? 'Bono' : 'Reembolso';
        $emoji = ($tipo === 'bono') ? 'ðŸ’°' : 'ðŸ’¸';
        
        $subject = "[$tipo_texto Capturado] $nombre_completo - $quincena_label";
        
        $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1877f2; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #1877f2; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>$emoji Nuevo $tipo_texto Capturado</h2>
        </div>
        <div class='content'>
            <div class='info-box'>
                <p><strong>Colaborador:</strong> $nombre_completo</p>
                <p><strong>RFC:</strong> {$emp->rfc}</p>
                <p><strong>Quincena:</strong> $quincena_label</p>
                <p><strong>Tipo:</strong> $tipo_texto</p>
                <p><strong>Monto:</strong> $" . number_format($monto, 2) . "</p>
                <p><strong>DescripciÃ³n:</strong> $descripcion</p>
            </div>
            <p>Este $tipo_texto ha sido capturado y estÃ¡ pendiente de revisiÃ³n en el sistema de nÃ³mina.</p>
        </div>
        <div class='footer'>
            <p>Sistema de NÃ³mina - WP NÃ³mina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Sistema de NÃ³mina <noreply@' . $_SERVER['HTTP_HOST'] . '>'
        );
        
        foreach ($admin_emails as $email) {
            wp_mail($email, $subject, $message, $headers);
        }
    }
    
    /**
     * Notificar solicitud de vacaciones
     */
    public static function notify_vacation_request($emp, $anio_laboral, $fecha_inicio, $fecha_fin, $dias_habiles, $comentario) {
        $nombre_completo = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
        
        // Email al colaborador
        if (!empty($emp->email)) {
            $subject = "ðŸ–ï¸ Solicitud de Vacaciones Recibida";
            
            $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #42b72a; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #42b72a; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>ðŸ–ï¸ Tu Solicitud de Vacaciones</h2>
        </div>
        <div class='content'>
            <p>Hola <strong>$nombre_completo</strong>,</p>
            <p>Hemos recibido tu solicitud de vacaciones con los siguientes datos:</p>
            <div class='info-box'>
                <p><strong>AÃ±o Laboral:</strong> $anio_laboral</p>
                <p><strong>Fecha Inicio:</strong> $fecha_inicio</p>
                <p><strong>Fecha Fin:</strong> $fecha_fin</p>
                <p><strong>DÃ­as HÃ¡biles:</strong> $dias_habiles</p>
                " . (!empty($comentario) ? "<p><strong>Comentario:</strong> $comentario</p>" : "") . "
            </div>
            <p>Tu solicitud estÃ¡ siendo revisada por Recursos Humanos. RecibirÃ¡s una notificaciÃ³n cuando sea aprobada o rechazada.</p>
        </div>
        <div class='footer'>
            <p>Sistema de NÃ³mina - WP NÃ³mina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Sistema de NÃ³mina <noreply@' . $_SERVER['HTTP_HOST'] . '>'
            );
            
            wp_mail($emp->email, $subject, $message, $headers);
        }
        
        // Email a administradores
        $admin_emails = self::get_admin_emails();
        if (!empty($admin_emails)) {
            $subject = "[Vacaciones] Nueva Solicitud - $nombre_completo";
            
            $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f0ad4e; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #f0ad4e; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>ðŸ–ï¸ Nueva Solicitud de Vacaciones</h2>
        </div>
        <div class='content'>
            <p>Se ha recibido una nueva solicitud de vacaciones:</p>
            <div class='info-box'>
                <p><strong>Colaborador:</strong> $nombre_completo</p>
                <p><strong>RFC:</strong> {$emp->rfc}</p>
                <p><strong>Email:</strong> {$emp->email}</p>
                <p><strong>AÃ±o Laboral:</strong> $anio_laboral</p>
                <p><strong>Fecha Inicio:</strong> $fecha_inicio</p>
                <p><strong>Fecha Fin:</strong> $fecha_fin</p>
                <p><strong>DÃ­as HÃ¡biles:</strong> $dias_habiles</p>
                " . (!empty($comentario) ? "<p><strong>Comentario:</strong> $comentario</p>" : "") . "
            </div>
            <p>Por favor, revisa y procesa esta solicitud en el panel de administraciÃ³n.</p>
        </div>
        <div class='footer'>
            <p>Sistema de NÃ³mina - WP NÃ³mina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Sistema de NÃ³mina <noreply@' . $_SERVER['HTTP_HOST'] . '>'
            );
            
            foreach ($admin_emails as $email) {
                wp_mail($email, $subject, $message, $headers);
            }
        }
    }
    
    /**
     * Notificar aprobaciÃ³n de vacaciones con opciÃ³n de mÃºltiples destinatarios
     * NUEVA FUNCIONALIDAD: Permite enviar notificaciÃ³n a mÃºltiples colaboradores
     */
    public static function notify_vacation_approved_multiple($emp_solicitante, $employees_to_notify, $anio_laboral, $fecha_inicio, $fecha_fin, $dias_habiles, $solicitud_id = null) {
        $nombre_solicitante = trim($emp_solicitante->nombre . ' ' . $emp_solicitante->apellido_paterno . ' ' . $emp_solicitante->apellido_materno);
        $email_sent = false;
        
        // Enviar notificaciÃ³n a cada colaborador seleccionado
        foreach ($employees_to_notify as $emp) {
            if (empty($emp->email)) continue;
            
            $nombre_destinatario = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
            $es_solicitante = ($emp->id == $emp_solicitante->id);
            
            if ($es_solicitante) {
                // Mensaje para el solicitante
                $subject = "âœ… Vacaciones Aprobadas - AcciÃ³n Requerida";
                
                $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #42b72a; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #42b72a; }
        .alert-box { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #f0ad4e; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
        .checklist { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .checklist li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>âœ… Â¡Tus Vacaciones Han Sido Aprobadas!</h2>
        </div>
        <div class='content'>
            <p>Hola <strong>$nombre_destinatario</strong>,</p>
            <p>Nos complace informarte que tu solicitud de vacaciones ha sido <strong>APROBADA</strong>.</p>
            
            <div class='info-box'>
                <h3>ðŸ“… Detalles de tus Vacaciones</h3>
                <p><strong>PerÃ­odo:</strong> $fecha_inicio al $fecha_fin</p>
                <p><strong>DÃ­as HÃ¡biles:</strong> $dias_habiles</p>
                <p><strong>AÃ±o Laboral:</strong> $anio_laboral</p>
            </div>
            
            <div class='alert-box'>
                <h3>âš ï¸ Acciones Requeridas</h3>
                <p><strong>Es importante que tomes las siguientes acciones:</strong></p>
                <div class='checklist'>
                    <ul>
                        <li>ðŸ“¢ <strong>Comunica inmediatamente</strong> al resto del equipo sobre tu ausencia programada</li>
                        <li>ðŸ“‹ <strong>Una semana antes de tu salida:</strong> Coordina tus actividades pendientes y asegÃºrate de que todo quede cubierto</li>
                        <li>âœ‰ï¸ <strong>Notifica a tus contactos clave</strong> sobre tu ausencia y quiÃ©n serÃ¡ tu respaldo</li>
                        <li>ðŸ’¼ <strong>Prepara entregables:</strong> AsegÃºrate de que no haya pendientes urgentes durante tu ausencia</li>
                    </ul>
                </div>
            </div>
            
            <p style='text-align: center; margin-top: 20px;'>
                <strong>Â¡Disfruta tu descanso! ðŸŒ´</strong>
            </p>
        </div>
        <div class='footer'>
            <p>Sistema de NÃ³mina - WP NÃ³mina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
            } else {
                // Mensaje para otros colaboradores informÃ¡ndoles
                $subject = "ðŸ“… NotificaciÃ³n: Vacaciones Aprobadas - $nombre_solicitante";
                
                $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1877f2; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #1877f2; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>ðŸ“… NotificaciÃ³n de Vacaciones</h2>
        </div>
        <div class='content'>
            <p>Hola <strong>$nombre_destinatario</strong>,</p>
            <p>Te informamos que se han aprobado las vacaciones para <strong>$nombre_solicitante</strong>.</p>
            
            <div class='info-box'>
                <h3>ðŸ“‹ Detalles</h3>
                <p><strong>Colaborador:</strong> $nombre_solicitante</p>
                <p><strong>RFC:</strong> {$emp_solicitante->rfc}</p>
                <p><strong>PerÃ­odo:</strong> $fecha_inicio al $fecha_fin</p>
                <p><strong>DÃ­as HÃ¡biles:</strong> $dias_habiles</p>
                <p><strong>AÃ±o Laboral:</strong> $anio_laboral</p>
            </div>
            
            <p>Por favor, ten en cuenta esta informaciÃ³n para la coordinaciÃ³n de actividades y proyectos durante este perÃ­odo.</p>
        </div>
        <div class='footer'>
            <p>Sistema de NÃ³mina - WP NÃ³mina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
            }
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Sistema de NÃ³mina <noreply@' . $_SERVER['HTTP_HOST'] . '>'
            );
            
            $result = wp_mail($emp->email, $subject, $message, $headers);
            
            if ($result && $es_solicitante) {
                $email_sent = true;
                
                // Log para debugging
                error_log(sprintf(
                    'WPN_Email::notify_vacation_approved_multiple - Correo enviado exitosamente al solicitante %s (%s). PerÃ­odo: %s a %s',
                    $nombre_solicitante,
                    $emp->email,
                    $fecha_inicio,
                    $fecha_fin
                ));
            }
        }
        
        // Registrar envÃ­o en la base de datos si se enviÃ³ al solicitante
        if ($solicitud_id && $email_sent) {
            global $wpdb;
            $ts = $wpdb->prefix.'nmn_vac_solicitudes';
            $wpdb->update($ts, [
                'email_sent' => 1,
                'email_sent_at' => current_time('mysql')
            ], ['id' => $solicitud_id]);
        }
        
        return $email_sent;
    }
    
    /**
    /**
     * Mantener compatibilidad con función anterior (envía solo al solicitante)
     */
    public static function notify_vacation_approved($emp, $anio_laboral, $fecha_inicio, $fecha_fin, $dias_habiles, $solicitud_id = null) {
        return self::notify_vacation_approved_multiple($emp, [$emp], $anio_laboral, $fecha_inicio, $fecha_fin, $dias_habiles, $solicitud_id);
    }
    
    /**
     * Notificar solicitud de permiso
     */
    public static function notify_permiso_request($emp, $permiso) {
        $nombre_completo = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
        $tipo_texto = $permiso->tipo === 'entrada' ? '🌅 Entrada tarde' : '🌆 Salida temprano';
        
        // Email al colaborador
        if (!empty($emp->email)) {
            $subject = "⏰ Solicitud de Permiso Recibida";
            
            $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #42b72a; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #42b72a; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>⏰ Tu Solicitud de Permiso</h2>
        </div>
        <div class='content'>
            <p>Hola <strong>$nombre_completo</strong>,</p>
            <p>Hemos recibido tu solicitud de permiso con los siguientes datos:</p>
            <div class='info-box'>
                <p><strong>Año Laboral:</strong> {$permiso->anio_laboral}</p>
                <p><strong>Fecha:</strong> " . date('d/m/Y', strtotime($permiso->fecha)) . "</p>
                <p><strong>Tipo:</strong> $tipo_texto</p>
                <p><strong>Hora:</strong> " . date('H:i', strtotime($permiso->hora)) . "</p>
                <p><strong>Horas solicitadas:</strong> " . number_format($permiso->horas_solicitadas, 2) . " hrs</p>
                " . (!empty($permiso->motivo) ? "<p><strong>Motivo:</strong> {$permiso->motivo}</p>" : "") . "
            </div>
            <p>Tu solicitud está siendo revisada por Recursos Humanos. Recibirás una notificación cuando sea aprobada o rechazada.</p>
        </div>
        <div class='footer'>
            <p>Sistema de Nómina - WP Nómina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Sistema de Nómina <noreply@' . $_SERVER['HTTP_HOST'] . '>'
            );
            
            wp_mail($emp->email, $subject, $message, $headers);
        }
        
        // Email a administradores
        $admin_emails = self::get_admin_emails();
        if (!empty($admin_emails)) {
            $subject = "[Permiso] Nueva Solicitud - $nombre_completo";
            
            $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f0ad4e; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #f0ad4e; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>⏰ Nueva Solicitud de Permiso</h2>
        </div>
        <div class='content'>
            <p>Se ha recibido una nueva solicitud de permiso:</p>
            <div class='info-box'>
                <p><strong>Colaborador:</strong> $nombre_completo</p>
                <p><strong>RFC:</strong> {$emp->rfc}</p>
                <p><strong>Email:</strong> {$emp->email}</p>
                <p><strong>Año Laboral:</strong> {$permiso->anio_laboral}</p>
                <p><strong>Fecha:</strong> " . date('d/m/Y', strtotime($permiso->fecha)) . "</p>
                <p><strong>Tipo:</strong> $tipo_texto</p>
                <p><strong>Hora:</strong> " . date('H:i', strtotime($permiso->hora)) . "</p>
                <p><strong>Horas solicitadas:</strong> " . number_format($permiso->horas_solicitadas, 2) . " hrs</p>
                " . (!empty($permiso->motivo) ? "<p><strong>Motivo:</strong> {$permiso->motivo}</p>" : "") . "
            </div>
            <p>Por favor, revisa y procesa esta solicitud en el panel de administración.</p>
        </div>
        <div class='footer'>
            <p>Sistema de Nómina - WP Nómina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Sistema de Nómina <noreply@' . $_SERVER['HTTP_HOST'] . '>'
            );
            
            foreach ($admin_emails as $email) {
                wp_mail($email, $subject, $message, $headers);
            }
        }
    }
    
    /**
     * Notificar aprobación de permiso con opción de múltiples destinatarios
     */
    public static function notify_permiso_approved_multiple($emp_solicitante, $employees_to_notify, $permiso, $permiso_id = null) {
        $nombre_solicitante = trim($emp_solicitante->nombre . ' ' . $emp_solicitante->apellido_paterno . ' ' . $emp_solicitante->apellido_materno);
        $tipo_texto = $permiso->tipo === 'entrada' ? '🌅 Entrada tarde' : '🌆 Salida temprano';
        $email_sent = false;
        
        // Enviar notificación a cada colaborador seleccionado
        foreach ($employees_to_notify as $emp) {
            if (empty($emp->email)) continue;
            
            $nombre_destinatario = trim($emp->nombre . ' ' . $emp->apellido_paterno . ' ' . $emp->apellido_materno);
            $es_solicitante = ($emp->id == $emp_solicitante->id);
            
            if ($es_solicitante) {
                // Mensaje para el solicitante
                $subject = "✅ Permiso Aprobado";
                
                $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #42b72a; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #42b72a; }
        .alert-box { background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #f0ad4e; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
        .checklist { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .checklist li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>✅ ¡Tu Permiso Ha Sido Aprobado!</h2>
        </div>
        <div class='content'>
            <p>Hola <strong>$nombre_destinatario</strong>,</p>
            <p>Nos complace informarte que tu solicitud de permiso ha sido <strong>APROBADA</strong>.</p>
            
            <div class='info-box'>
                <h3>📅 Detalles de tu Permiso</h3>
                <p><strong>Fecha:</strong> " . date('d/m/Y', strtotime($permiso->fecha)) . "</p>
                <p><strong>Tipo:</strong> $tipo_texto</p>
                <p><strong>Hora:</strong> " . date('H:i', strtotime($permiso->hora)) . "</p>
                <p><strong>Horas:</strong> " . number_format($permiso->horas_solicitadas, 2) . " hrs</p>
                <p><strong>Año Laboral:</strong> {$permiso->anio_laboral}</p>
            </div>
            
            <div class='alert-box'>
                <h3>⚠️ Acciones Requeridas</h3>
                <p><strong>Es importante que tomes las siguientes acciones:</strong></p>
                <div class='checklist'>
                    <ul>
                        <li>📢 <strong>Comunica inmediatamente</strong> al resto del equipo sobre tu permiso</li>
                        <li>📋 <strong>Coordina tus actividades</strong> para que no haya pendientes durante tu ausencia</li>
                        <li>✉️ <strong>Notifica a tus contactos clave</strong> sobre tu horario modificado ese día</li>
                    </ul>
                </div>
            </div>
            
            <p style='text-align: center; margin-top: 20px;'>
                <strong>¡Que tengas un excelente día! 🎯</strong>
            </p>
        </div>
        <div class='footer'>
            <p>Sistema de Nómina - WP Nómina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
            } else {
                // Mensaje para otros colaboradores informándoles
                $subject = "📅 Notificación: Permiso Aprobado - $nombre_solicitante";
                
                $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1877f2; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f0f2f5; padding: 20px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #1877f2; }
        .footer { text-align: center; padding: 20px; color: #65676b; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>📅 Notificación de Permiso</h2>
        </div>
        <div class='content'>
            <p>Hola <strong>$nombre_destinatario</strong>,</p>
            <p>Te informamos que se ha aprobado un permiso para <strong>$nombre_solicitante</strong>.</p>
            
            <div class='info-box'>
                <h3>📋 Detalles</h3>
                <p><strong>Colaborador:</strong> $nombre_solicitante</p>
                <p><strong>RFC:</strong> {$emp_solicitante->rfc}</p>
                <p><strong>Fecha:</strong> " . date('d/m/Y', strtotime($permiso->fecha)) . "</p>
                <p><strong>Tipo:</strong> $tipo_texto</p>
                <p><strong>Hora:</strong> " . date('H:i', strtotime($permiso->hora)) . "</p>
                <p><strong>Horas:</strong> " . number_format($permiso->horas_solicitadas, 2) . " hrs</p>
                <p><strong>Año Laboral:</strong> {$permiso->anio_laboral}</p>
            </div>
            
            <p>Por favor, ten en cuenta esta información para la coordinación de actividades durante ese día.</p>
        </div>
        <div class='footer'>
            <p>Sistema de Nómina - WP Nómina y Vacaciones</p>
        </div>
    </div>
</body>
</html>
";
            }
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Sistema de Nómina <noreply@' . $_SERVER['HTTP_HOST'] . '>'
            );
            
            $result = wp_mail($emp->email, $subject, $message, $headers);
            
            if ($result && $es_solicitante) {
                $email_sent = true;
                
                // Log para debugging
                error_log(sprintf(
                    'WPN_Email::notify_permiso_approved_multiple - Correo enviado exitosamente al solicitante %s (%s). Fecha: %s',
                    $nombre_solicitante,
                    $emp->email,
                    date('d/m/Y', strtotime($permiso->fecha))
                ));
            }
        }
        
        // Registrar envío en la base de datos si se envió al solicitante
        if ($permiso_id && $email_sent) {
            global $wpdb;
            $tp = $wpdb->prefix.'nmn_permisos';
            $wpdb->update($tp, [
                'email_sent' => 1,
                'email_sent_at' => current_time('mysql')
            ], ['id' => $permiso_id]);
        }
        
        return $email_sent;
    }
    
    /**
     * Mantener compatibilidad con función anterior (envía solo al solicitante)
     */
    public static function notify_permiso_approved($emp, $permiso, $permiso_id = null) {
        return self::notify_permiso_approved_multiple($emp, [$emp], $permiso, $permiso_id);
    }
}
