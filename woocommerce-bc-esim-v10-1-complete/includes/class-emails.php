<?php
class BC_Emails_V10{
    public static function init(){
        // Se inicializa automáticamente cuando se llama send_esim_email
    }
    
    public static function send_esim_email($order, $esim_list){
        if(empty($esim_list)){
            error_log('BC eSIM: Email sin datos eSIM');
            return false;
        }
        
        $to = $order->get_billing_email();
        $subject = '🎉 Tu eSIM está lista - Orden #' . $order->get_id();
        
        $message = self::build_email_html($order, $esim_list);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
        ];
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if($sent){
            $order->add_order_note('📧 Email con eSIM enviado a: ' . $to);
            error_log('BC eSIM: Email enviado a ' . $to);
        } else {
            $order->add_order_note('❌ Error al enviar email');
            error_log('BC eSIM: Error enviando email a ' . $to);
        }
        
        return $sent;
    }
    
    private static function build_email_html($order, $esim_list){
        $html = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">';
        $html .= '<div style="max-width:600px;margin:0 auto;padding:20px;">';
        
        $html .= '<h1 style="color:#0073aa;">¡Tu eSIM está lista!</h1>';
        $html .= '<p>Hola ' . $order->get_billing_first_name() . ',</p>';
        $html .= '<p>Gracias por tu compra. Tu eSIM ya está lista para usar.</p>';
        
        foreach($esim_list as $idx => $esim){
            $html .= '<div style="border:2px solid #0073aa;padding:20px;margin:20px 0;border-radius:8px;background:#f9f9f9;">';
            $html .= '<h2 style="color:#0073aa;margin-top:0;">eSIM #' . ($idx + 1) . '</h2>';
            
            if(!empty($esim['iccid'])){
                $html .= '<p><strong>ICCID:</strong> <code style="background:#fff;padding:5px 10px;border-radius:4px;">' . $esim['iccid'] . '</code></p>';
            }
            
            if(!empty($esim['qrCode'])){
                $html .= '<div style="margin:20px 0;text-align:center;">';
                $html .= '<p><strong>Escanea este código QR:</strong></p>';
                $html .= '<img src="' . esc_url($esim['qrCode']) . '" alt="QR Code" style="max-width:300px;border:1px solid #ccc;padding:10px;background:#fff;">';
                $html .= '</div>';
            }
            
            if(!empty($esim['manualCode'])){
                $html .= '<p><strong>Código manual (instalación alternativa):</strong></p>';
                $html .= '<p style="background:#fff;padding:10px;border-radius:4px;word-wrap:break-word;font-size:12px;"><code>' . $esim['manualCode'] . '</code></p>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '<div style="background:#e7f7ff;padding:15px;border-left:4px solid #0073aa;margin:20px 0;">';
        $html .= '<h3 style="margin-top:0;">📱 Cómo instalar tu eSIM:</h3>';
        $html .= '<ol style="margin:10px 0;padding-left:20px;">';
        $html .= '<li>Abre <strong>Ajustes</strong> en tu dispositivo</li>';
        $html .= '<li>Ve a <strong>Datos móviles</strong> o <strong>Celular</strong></li>';
        $html .= '<li>Selecciona <strong>Añadir plan de datos</strong></li>';
        $html .= '<li>Escanea el código QR mostrado arriba</li>';
        $html .= '<li>¡Listo! Tu eSIM se activará automáticamente</li>';
        $html .= '</ol>';
        $html .= '</div>';
        
        $html .= '<p style="margin-top:30px;padding-top:20px;border-top:1px solid #ddd;color:#666;font-size:13px;">';
        $html .= 'Detalles de tu orden: #' . $order->get_id() . '<br>';
        $html .= 'Fecha: ' . $order->get_date_created()->format('d/m/Y H:i') . '<br>';
        $html .= 'Total: $' . $order->get_total() . ' ' . $order->get_currency();
        $html .= '</p>';
        
        $html .= '</div>';
        $html .= '</body></html>';
        
        return $html;
    }
}
