<?php
namespace Application\Model\Utility;

class Notificaciones{
        
    public static function emailsContenido($codigo){
        switch($codigo){
            case 1:
                $data = [
                    'asunto'    => 'COIIAOC: Recuperar clave',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                                . '<p>%%ESTIMADO-NOMBRE%%,</p>'
                                . '<p>para recuperar su clave, acceda al siguiente enlace:</p>'
                                . '%%LINKPASS%%'
                                . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'usuario'
                ];
                break;
            case 2:
                $data = [
                    'asunto'    => 'COIIAOC: Efectuar pago de inscripción',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                                . '<p>%%ESTIMADO-NOMBRE%%,</p>'
                                . '<p>Hemos recibido su inscripción, le agradecemos su interés. Los datos de la misma son los siguientes:</p>'
                                . '%%TABLA-INSCRIPCION%%'
                                . '<p>Para completar el proceso debe realizar el pago del importe indicado. Para ello, acceda al siguiente enlace:</p>'
                                . '%%LINKPAGO%%'
                                . '<p>También puede realizarlo mediante trasferencia bancaria a la siguiente cuenta:</p>'
                                . '<ul><li>IBAN: ES25 3025 0007 7614 0000 0037</li></ul>'
                                . '<p>En caso de realizar el pago por transferencia, no olvide poner el identificador de la inscripción como concepto y adjuntar el justificante en el siguiente enlace %%LINKJUSTIFICANTE%%.</p>'
                                . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'usuario'
                ];
                break;
            case 3:
                $data = [
                    'asunto'    => 'COIIAOC: Inscripción confirmada',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                                . '<p>%%ESTIMADO-NOMBRE%%,</p>'
                                . '<p>Su inscripción ha sido confirmada, le agradecemos su interés. Los datos de la misma son los siguientes:</p>'
                                . '%%TABLA-INSCRIPCION%%'
                                //. '<p>Para concluir el proceso de inscripción, debe rellenar y enviar en la mayor brevedad posible a <a href="mailto:formacion@coiiaoc.com">formacion@coiiaoc.com</a> el siguiente documento:</p>'
                                //. '<p><a href="https://app.coiiaoc.com/templates/coiiaoc_formCursos.doc">Documento de confirmación de inscripción</a></p>'
                                . '<p>En caso de realizar el pago por transferencia, no olvide poner el identificador de la inscripción como concepto y adjuntar el justificante en el siguiente enlace %%LINKJUSTIFICANTE%%.</p>'
                                . '<p>Si tiene cualquier cuestión, no dude en escribirnos a <a href="mailto:formacion@coiiaoc.com">formacion@coiiaoc.com</a>.</p>'
                                . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'usuario'
                ];
                break;
            case 4:
                $data = [
                    'asunto'    => 'COIIAOC: Inscripción confirmada',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                                . '<p>%%ESTIMADO-NOMBRE%%,</p>'
                                . '<p>Su inscripción ha sido confirmada, le agradecemos su interés. Los datos de la misma son los siguientes:</p>'
                                . '%%TABLA-INSCRIPCION%%'
                                . '<p>Si tiene cualquier cuestión, no dude en escribirnos a <a href="mailto:formacion@coiiaoc.com">formacion@coiiaoc.com</a>.</p>'
                                . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'usuario'
                ];
                break;
            case 5:
                $data = [
                    'asunto'    => 'COIIAOC: Nueva inscripción',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                                . '<p>Aviso de nueva inscripción</p>'
                                . '<p>Se ha recibido una nueva inscripción. Los datos de la misma son los siguientes:</p>'
                                . '<p>Inscrito: <b>%%INSCRITO%%</b></p>'
                                . '%%TABLA-INSCRIPCION%%'
                                . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'administrador'
                ];
                break;
            case 6:
                $data = [
                    'asunto'    => 'COIIAOC: Pago de inscripción',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                                . '<p>Aviso de pago de inscripción</p>'
                                . '<p>Se ha recibido el pago de una inscripción. Los datos de la misma son los siguientes:</p>'
                                . '<p>Inscrito: <b>%%INSCRITO%%</b></p>'
                                . '%%TABLA-INSCRIPCION%%'
                                . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'administrador'
                ];
                break;
            case 7:
                $data = [
                    'asunto'    => 'COIIAOC: Justificante de transferencia',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                        . '<p>Aviso de pago de inscripción por transferencia</p>'
                        . '<p>Se ha recibido el justificante de pago de una inscripción. Los datos de la misma son los siguientes:</p>'
                        . '<p>Inscrito: <b>%%INSCRITO%%</b></p>'
                        . '%%TABLA-INSCRIPCION%%'
                        . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'administrador'
                ];
                break;
            case 8:
                $data = [
                    'asunto'    => 'COIIAOC: Certificado de asistencia',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                        . '<p>%%ESTIMADO-NOMBRE%%,</p>'    
                        . '<p>Se adjunta el certificado del curso: %%NOMBRE_CURSO%%.</p>'
                        . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'usuario'
                ];
                break;
            case 9:
                $data = [
                    'asunto'    => 'COIIAOC: Inscripción incompleta',
                    'mensaje'   => '<p style="color:#7a1e99;font-size:18px;border-bottom:3px solid #7a1e99;"><b>Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</b></p>'
                        . '<p>%%ESTIMADO-NOMBRE%%,</p>'    
                        . '<p>La siguiente inscripción está incompleta:</p>'
                        . '%%TABLA-INSCRIPCION%%'
                        . '<p>Le rogamos confirme su inscripción en el siguiente enlace para que sea válida:</p>'
                        . '%%LINK-CONFIRMACION%%'
                        . '<p>Si tiene cualquier cuestión, no dude en escribirnos a <a href="mailto:formacion@coiiaoc.com">formacion@coiiaoc.com</a>.</p>'
                        . '<p>Le saludamos atentamente.</p>',
                    'destinatario'  => 'usuario'
                ];
                break;
            default:
                $data = null;
        }
        return $data;
    }
    
    public static function recuperarClave($usuario,$sendMail){
        $contenido = Notificaciones::emailsContenido(1);
        $busca = [
            '%%ESTIMADO-NOMBRE%%',
            '%%LINKPASS%%'
        ];
        $remplaza = [
            $usuario->get('estimado-nombre'),
            '<a href="https://app.coiiaoc.com/auth/index/requestpass/' . base64_encode(dechex(7 * 11 * $usuario->get('id')) . 'h/h' . $usuario->get('email')) . '">Restablecer clave</a>'
        ];
        $contenido['mensaje'] = str_replace($busca, $remplaza, $contenido['mensaje']);
        $firma = Notificaciones::getFirma();
        $mail = [
            'mail' => $usuario->get('email'),
            'name' => $usuario->get('nombre-completo')
        ];
        $x = $sendMail->sendMail([$mail], $contenido['asunto'], $contenido['mensaje'].$firma);
        return $x;
    }
    
    public static function enviarCobroInscripcion($inscripcion,$sendMail){
        $contenido = Notificaciones::emailsContenido(2);
        $usuario = $inscripcion->get('creador');
        $data = [
            'tabla'         => '%%TABLA-INSCRIPCION%%',
            'inscripcion'   => $inscripcion
        ];
        $busca = [
            '%%ESTIMADO-NOMBRE%%',
            '%%TABLA-INSCRIPCION%%',
            '%%LINKPAGO%%',
            '%%LINKJUSTIFICANTE%%'
        ];
        $remplaza = [
            $usuario->get('estimado-nombre'),
            Notificaciones::generaHtml($data),
            '<a href="https://app.coiiaoc.com/pagar-curso/' . Utilidades::encriptaIdCurso($inscripcion->get('id'), 'enc') . '">Realizar pago</a>',
            '<a href="https://app.coiiaoc.com/justificante-pago/' . Utilidades::encriptaIdCurso($inscripcion->get('id'), 'enc') . '">adjuntar justificante de pago por tranferencia</a>',
        ];
        $contenido['mensaje'] = str_replace($busca, $remplaza, $contenido['mensaje']);
        $firma = Notificaciones::getFirma();
        $mail = [
            'mail' => $usuario->get('email'),
            'name' => $usuario->get('nombre-completo')
        ];
        $x = $sendMail->sendMail([$mail], $contenido['asunto'], $contenido['mensaje'].$firma);
        return $x;
    }

    public static function enviarCertificaciones($inscripcion,$sendMail, $inscritos){
        $contenido = Notificaciones::emailsContenido(8);
        $usuario = $inscripcion->get('creador');
        $curso = $inscripcion->get('curso');
        $data = [
            'tabla'         => '%%TABLA-INSCRIPCION%%',
            'inscripcion'   => $inscripcion
        ];
        $busca = [
            '%%ESTIMADO-NOMBRE%%',
            '%%NOMBRE_CURSO%%'
        ];
        $remplaza = [
            $usuario->get('estimado-nombre'),
            $curso->get('nombre')
        ];
        $contenido['mensaje'] = str_replace($busca, $remplaza, $contenido['mensaje']);
        $firma = Notificaciones::getFirma();
        
        $x = [];
        foreach($inscritos as $inscrito):
            $adjuntos = [];
            if(!empty($inscrito->get('diploma')) && file_exists(\Application\Model\Entity\Inscrito::FILE_DIRECTORY_DIPLOMA . $inscrito->get('diploma'))){
                $usuario_inscrito = $inscrito->get('usuario');

                $justificante_part = pathinfo(\Application\Model\Entity\Inscrito::FILE_DIRECTORY_DIPLOMA . $inscrito->get('diploma'));

                $adjuntos[] = [
                    'file_path' => \Application\Model\Entity\Inscrito::FILE_DIRECTORY_DIPLOMA . $inscrito->get('diploma'),
                    'name'      => str_replace(' ', '_', 'Certificado_'.$usuario_inscrito->get('nombre') . ' ' . $usuario_inscrito->get('apellidos')) . '.' . $justificante_part['extension'],
                ];
                if(!empty($usuario_inscrito->get('email'))){
                    $mail = [
                        'mail' => $usuario_inscrito->get('email'),
                        'name' => $usuario_inscrito->get('nombre-completo')
                    ];
                    $x[(int)$inscrito->get('id')] = $sendMail->sendMail([$mail], $contenido['asunto'], $contenido['mensaje'].$firma, $adjuntos);
                }
            }
        endforeach;
        
        return $x;
    }
    
    public static function enviarConfirmacionInscripcion($inscripcion,$sendMail){
        if($inscripcion->get('total') > 0){
            $contenido_email = 3;
        }else{
            $contenido_email = 4;
        }
        $contenido = Notificaciones::emailsContenido($contenido_email);
        $usuario = $inscripcion->get('creador');
        $data = [
            'tabla'         => '%%TABLA-INSCRIPCION%%',
            'inscripcion'   => $inscripcion
        ];
        $busca = [
            '%%ESTIMADO-NOMBRE%%',
            '%%TABLA-INSCRIPCION%%',
            '%%LINKJUSTIFICANTE%%'
        ];
        $remplaza = [
            $usuario->get('estimado-nombre'),
            Notificaciones::generaHtml($data),
            '<a href="https://app.coiiaoc.com/justificante-pago/' . Utilidades::encriptaIdCurso($inscripcion->get('id'), 'enc') . '">adjuntar justificante de pago por tranferencia</a>',
        ];
        $contenido['mensaje'] = str_replace($busca, $remplaza, $contenido['mensaje']);
        $firma = Notificaciones::getFirma();
        $mail = [
            'mail' => $usuario->get('email'),
            'name' => $usuario->get('nombre-completo')
        ];
        /*$adjunto = [
            'file_path' => '/var/www/vhosts/coiiaoc.com/app.coiiaoc.com/public/templates/coiiaoc_formCursos.doc',
            'name'      => 'coiiaoc_formCursos.doc',
            'type'      => 'application/msword'
        ];*/
        $x = $sendMail->sendMail([$mail], $contenido['asunto'], $contenido['mensaje'].$firma);
        return $x;
    }
    
    public static function enviarConfirmacionPagoInscripcion($inscripcion,$sendMail){
        $contenido_email = 6;
        $contenido = Notificaciones::emailsContenido($contenido_email);
        $usuario = $inscripcion->get('creador');
        $data = [
            'tabla'         => '%%TABLA-INSCRIPCION%%',
            'inscripcion'   => $inscripcion
        ];
        $busca = [
            '%%TABLA-INSCRIPCION%%',
            '%%INSCRITO%%'
        ];
        $remplaza = [
            Notificaciones::generaHtml($data),
            $usuario->get('nombre-completo')
        ];
        $contenido['mensaje'] = str_replace($busca, $remplaza, $contenido['mensaje']);
        $firma = Notificaciones::getFirma();
        $mail = [
            'mail' => 'formacion@coiiaoc.com',
            'name' => 'Responsable de formación'
        ];
        $x = $sendMail->sendMail([$mail], $contenido['asunto'], $contenido['mensaje'].$firma);
        return $x;
    }

    public static function enviarConfirmacionJustificantePagoInscripcion($inscripcion,$sendMail){
        $contenido_email = 7;
        $contenido = Notificaciones::emailsContenido($contenido_email);
        $usuario = $inscripcion->get('creador');
        $data = [
            'tabla'         => '%%TABLA-INSCRIPCION%%',
            'inscripcion'   => $inscripcion
        ];
        $busca = [
            '%%TABLA-INSCRIPCION%%',
            '%%INSCRITO%%'
        ];
        $remplaza = [
            Notificaciones::generaHtml($data),
            $usuario->get('nombre-completo')
        ];
        $contenido['mensaje'] = str_replace($busca, $remplaza, $contenido['mensaje']);
        $firma = Notificaciones::getFirma();
        $mail = [
            'mail' => 'formacion@coiiaoc.com',
            'name' => 'Responsable de formación'
        ];

        if(!empty($inscripcion->get('justificante_pago')) && file_exists(\Application\Model\Entity\Inscripcion::FILE_DIRECTORY_JUSTIFICANTE . $inscripcion->get('justificante_pago'))){
            $justificante_part = pathinfo(\Application\Model\Entity\Inscripcion::FILE_DIRECTORY_JUSTIFICANTE . $inscripcion->get('justificante_pago'));
            $adjunto = [];
            $adjunto[] = [
                'file_path' => \Application\Model\Entity\Inscripcion::FILE_DIRECTORY_JUSTIFICANTE . $inscripcion->get('justificante_pago'),
                'name'      => 'justificante_de_pago.' . $justificante_part['extension'],
            ];
        }else{
            $adjunto = false;
        }

        $x = $sendMail->sendMail([$mail], $contenido['asunto'], $contenido['mensaje'].$firma, $adjunto);
        return $x;
    }
    
    public static function enviarAvisoNuevaInscripcion($inscripcion,$sendMail){
        $contenido_email = 5;
        $contenido = Notificaciones::emailsContenido($contenido_email);
        $usuario = $inscripcion->get('creador');
        $data = [
            'tabla'         => '%%TABLA-INSCRIPCION%%',
            'inscripcion'   => $inscripcion
        ];
        $busca = [
            '%%TABLA-INSCRIPCION%%',
            '%%INSCRITO%%'
        ];
        $remplaza = [
            Notificaciones::generaHtml($data),
            $usuario->get('nombre-completo')
        ];
        $contenido['mensaje'] = str_replace($busca, $remplaza, $contenido['mensaje']);
        $firma = Notificaciones::getFirma();
        $mail = [
            'mail' => 'formacion@coiiaoc.com',
            'name' => 'Responsable de formación'
        ];
        $x = $sendMail->sendMail([$mail], $contenido['asunto'], $contenido['mensaje'].$firma);
        return $x;
    }
    
    public static function enviarInscripcionIncompleta($inscripcion,$sendMail){
        $contenido = Notificaciones::emailsContenido(9);
        $usuario = $inscripcion->get('creador');
        $data = [
            'tabla'         => '%%TABLA-INSCRIPCION%%',
            'inscripcion'   => $inscripcion
        ];
        $busca = [
            '%%ESTIMADO-NOMBRE%%',
            '%%TABLA-INSCRIPCION%%',
            '%%LINK-CONFIRMACION%%'
        ];
        $remplaza = [
            $usuario->get('estimado-nombre'),
            Notificaciones::generaHtml($data),
            '<a href="https://app.coiiaoc.com/inscripcion-curso/' . $inscripcion->get('id_cur') . '">Confirmar inscripción</a>',
        ];
        $contenido['mensaje'] = str_replace($busca, $remplaza, $contenido['mensaje']);
        $firma = Notificaciones::getFirma();
        $mail = [
            'mail' => $usuario->get('email'),
            'name' => $usuario->get('nombre-completo')
        ];
        $x = $sendMail->sendMail([$mail], $contenido['asunto'], $contenido['mensaje'].$firma);
        return $x;
    }
    
    public static function getFirma(){
        $firma = '<p>'
                    . '<span style="color:#7a1e99">Colegio Oficial de Ingenieros Industriales de Andalucía Occidental</span><br/>'
                    . '<span>Servicio de notificaciones automáticas</span><br/>'
                    . '<span>Tel: 954 41 61 11</span><br/>'
                    . '<span><a href="mailto:coiiaoc@coiiaoc.com" target="_blank">coiiaoc@coiiaoc.com</a></span><br/>'
                    . '<span><a href="https://coiiaoc@coiiaoc.com" target="_blank">www.coiiaoc@coiiaoc.com</a></span><br/>'
                    . '</p>';
        $firma .= '<p><span style="font-size:8pt;color:green">P   Antes de imprimir este e-mail piense bien si es necesario hacerlo: El medioambiente es cosa de todos.</span></p>';
        $firma .= '<p>----------</p>';
        $firma .= '<p><span style="font-size:7pt;color:gray">Aviso de confidencialidad: De conformidad con lo dispuesto en la Ley Orgánica 15/1999 de Protección de Datos de Carácter Personal, le informamos que los datos personales que nos ha proporcionado así como aquellos que nos proporcione en un futuro, serán incorporados a un fichero automatizado de datos de carácter personal responsabilidad de la empresa, con la finalidad de gestionar las comunicaciones que se puedan realizar con usted. Para ejercitar los derechos de acceso, rectificación, oposición y cancelación reconocidos por la legislación vigente, el interesado deberá realizar una comunicación a la dirección de la empresa, indicando como referencia “Protección de datos”. Este correo electrónico contiene información confidencial, cuya divulgación está prohibida. Esta comunicación, y los documentos que, en su caso, lleve anexos, son para uso exclusivo del destinatario arriba indicado y contienen información privilegiada y/o confidencial. Si Vd. no es el destinatario original, queda informado que; la divulgación, distribución o reproducción o cualquier otro uso de su contenido, sin la autorización del remitente está terminantemente prohibida. En caso de haber recibido esta comunicación por error, hágaselo saber inmediatamente al remitente por idéntica vía, absténgase de leerlo, copiarlo, remitirlo o entregarlo a un tercero y proceda a su destrucción.</span></p>';
        return $firma;
    }
    
    public static function generaHtml($data){
        $tabla = null;
        if($data['tabla'] == '%%TABLA-INSCRIPCION%%'){
            $curso = $data['inscripcion']->get('curso');
            $tabla = '<ul>'
                    . '<li>Identificador de la inscripción:<br/><b>'.str_pad($data['inscripcion']->get('id'),5,'0',STR_PAD_LEFT).'</b></li>'
                    . '<li>Tipo de actividad:<br/><b>'.Utilidades::systemOptions('cursos','tipo')[(int)$curso->get('tipo')].'</b></li>'
                    . '<li>Título de la actividad:<br/><b>'.$curso->get('nombre').'</b></li>'
                    . '<li>Precio:<br/><b>'. number_format($data['inscripcion']->get('total'), 2,',','.').' €</b></li>'
                    . '</ul>';
        }
        return $tabla;
    }
}
