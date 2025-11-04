<?php
namespace Application\Model\Utility;

use Application\Model\Entity\Inscripciones;
use Application\Model\Entity\Opciones;

class Utilidades{
	
    public static function giraFecha($fecha){
        $fecha2 = explode(' ', $fecha);
        if(count($fecha2) == 2){
            $fecha = implode("-", array_reverse( preg_split("/\D/", $fecha2[0]))) . ' ' . $fecha2[1];
        }else{
            $fecha = implode("-", array_reverse( preg_split("/\D/", $fecha)));
        }
        return $fecha;	
    }
	
    public static function generaPass(){
        $cadena = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
        $longitudCadena=strlen($cadena);
        $pass = "";
        $longitudPass = 8;
        for($i=1 ; $i<=$longitudPass ; $i++){
            $pos = rand(0,$longitudCadena-1);
            $pass .= substr($cadena,$pos,1);
        }
        return $pass;
    }
	
    public static function sumaTiempoFecha($tipo = 'day', $tiempo, $fecha, $operacion = '+'){	
        $fecha = implode("-", array_reverse( preg_split("/\D/", $fecha)));
        $nuevafecha = strtotime($operacion . $tiempo .' ' . $tipo , strtotime ($fecha) ) ;
        return date('Y-m-d', $nuevafecha);
    }

    /*
     * Códgo de colores de los badges:
     * - Secondary (gris): esperando acción por parte del usuario o terminado 
     * - Success (verde): OK
     * - Danger (rojo): acción urgente pendiente
     * - Warning (amarillo): acción pendiente
     * - Info (azul): correcto no terminado
     * - Dark (negro): rechazado, no admitido, etc.
     */
    public static function systemOptions($tabla,$campo,$opt = 0){
        $opciones = [
            'cursos' => [
                'estado' => [
                    0 => [
                        0 => 'Borrador',
                        1 => 'Inscripción abierta',
                        4 => 'Inscripción cerrada',
                        2 => 'En curso',
                        3 => 'Terminado'],
                    1 => [
                        0 => '<span class="badge badge-warning">Borrador</span>',
                        1 => '<span class="badge badge-success">Inscripción abierta</span>',
                        4 => '<span class="badge badge-info">Inscripción cerrada</span>',
                        2 => '<span class="badge badge-info">En curso</span>',
                        3 => '<span class="badge badge-dark">Terminado</span>'
                    ],
                    2 => [
                        0 => 'warning',
                        1 => 'success',
                        4 => 'info',
                        2 => 'info',
                        3 => 'dark'
                    ]
                ],
                'tipo' => [
                    0 => ['Curso','Jornada','Evento infantil'],
                    1 => ['curso','jornada','evento infantil']
                ],
                'colegiados' => [
                    0 => ['No','Sí']
                ],
                'beca' => [
                    0 => ['No','Sí']
                ],
                'web' => [0 => 'https://coiiaoc.com'],
                'ubicacion' => [0 => 'Sede del COIIAOC en Sevilla'],
                'enlubi' => [0 => 'https://www.google.com/maps/place/Calle+Dr.+Antonio+Cort%C3%A9s+Llad%C3%B3,+6,+41004+Sevilla/@37.3779955,-5.9829441,17z/data=!3m1!4b1!4m5!3m4!1s0xd126e9e50d9c625:0x967af2782146a8f2!8m2!3d37.3779955!4d-5.9807554'],
            ],
            'empresas' => [
                'estado' => [
                    0 => ['Pendiente','Activo','Inactivo','Rechazado'],
                    1 => [
                        '<span class="badge badge-warning">Pendiente</span>',
                        '<span class="badge badge-success">Activo</span>',
                        '<span class="badge badge-secondary">Inactivo</span>',
                        '<span class="badge badge-dark">Rechazado</span>'
                    ],
                    2 => [
                        'warning',
                        'success',
                        'secondary',
                        'dark'
                    ]
                ]
            ],
            'inscripciones' => [
                'beca' => [
                    0 => ['No solicita','Solicitada','Aprobada','Rechazada'],
                    1 => [
                        '<span class="badge badge-secondary">No solicita</span>',
                        '<span class="badge badge-warning">Solicitada</span>',
                        '<span class="badge badge-success">Aprobada</span>',
                        '<span class="badge badge-dark">Rechazada</span>'
                    ]
                ],
                'estado' => [
                    0 => [
                        0 => 'Incompleta',
                        1 => 'Pendiente',
                        2 => 'Aprobada - Pendiente de pago',
                        6 => 'Transferencia por revisar',
                        3 => 'Completada',
                        4 => 'Rechazada',
                        5 => 'Cancelada'],
                    1 => [
                        0 => '<span class="badge badge-danger">Incompleta</span>',
                        1 => '<span class="badge badge-warning">Pendiente</span>',
                        2 => '<span class="badge badge-info">Aprobada - Pendiente de pago</span>',
                        6 => '<span class="badge badge-warning">Transferencia por revisar</span>',
                        3 => '<span class="badge badge-success">Completada</span>',
                        4 => '<span class="badge badge-dark">Rechazada</span>',
                        5 => '<span class="badge badge-secondary">Cancelada</span>'
                    ],
                    2 => ['secondary','warning','info','success','dark','secondary','warning']
                ],
                'pago' => [
                    0 => ['No pagado','TPV','Transferencia'],
                    1 => [
                        '<i class="fa fa-times" title="No pagado"></i>',
                        '<i class="fa fa-credit-card" title="TPV"></i>',
                        '<i class="fa fa-university" title="Transferencia"></i>'
                    ],
                ]
            ],
            'inscritos' => [
                'sitcol' => [
                    0 => [
                        0 => 'Colegiado',
                        1 => 'Precolegiado',
                        2 => 'Titulado adherido',
                        3 => 'Estudiante adherido',
                        4 => 'Profesional adherido',
                        5 => 'No colegiado'
                    ]
                ],
                'asistencia' => [
                    0 => ['Pendiente','Sí','No']
                ]
            ],
            'ofertas' => [
                'estado' => [
                    0 => ['Pendiente de aprobación','Publicada','Cerrada','Rechazada'],
                    1 => [
                        '<span class="badge badge-warning">Pendiente de aprobación</span>',
                        '<span class="badge badge-success">Publicada</span>',
                        '<span class="badge badge-secondary">Cerrada</span>',
                        '<span class="badge badge-dark">Rechazada</span>'
                    ],
                    2 => [
                        'warning',
                        'success',
                        'secondary',
                        'dark'
                    ]
                ]
            ],
            'candidaturas' => [
                'estado' => [
                    0 => ['En espera','Enviada','Pre-seleccionado','Seleccionado','Descartado'],
                    1 => [
                        '<span class="badge badge-secondary">En espera</span>',
                        '<span class="badge badge-warning">Enviada</span>',
                        '<span class="badge badge-info">Pre-seleccionado</span>',
                        '<span class="badge badge-success">Seleccionado</span>',
                        '<span class="badge badge-dark">Descartado</span>'
                    ],
                    2 => [
                        'secondary',
                        'warning',
                        'info',
                        'success',
                        'dark'
                    ]
                ]
            ],
            'segmentos' => [
                'tipo' => [
                    0 => ['Usuarios','Cursos','Ofertas']
                ]
            ],
            'usuarios' => [
                'rol' => [
                    0 => [
                        '4dmin'     => 'Administrador',
                        'c4legiado' => 'Colegiado',
                        'us4ario'   => 'Usuario registrado'
                    ],
                    1 => ['4dmin','c4legiado','us4ario'],
                    2 => [
                        '4dmin'     => 'dark',
                        'c4legiado' => 'success',
                        'us4ario'   => 'info'
                    ]
                ],
                'autorizado' => [
                    0 => ['No','Sí','Por confirmar']
                ],
                'sitcol' => [
                    0 => [
                        0 => 'Colegiado',
                        1 => 'Precolegiado',
                        2 => 'Titulado adherido',
                        3 => 'Estudiante adherido',
                        4 => 'Profesional adherido',
                        5 => 'No colegiado',
                        6 => 'Adherido honorario',
                        7 => 'Viudo/a',
                        8 => 'Socio adherido'
                    ]
                ],
                'empleo' => [
                    0 => ['No','Sí',]
                ],
                'jornada' => [
                    0 => ['Completa','Media jornada','Freelance','Otros']
                ],
                'sexo' => [
                    0 => ['','Mujer','Hombre']
                ],
                'sitlab' => [
                    0 => ['Estudiante','Trabajador por cuenta ajena','Autónomo','Empresario','Desempleado','Jubilado']
                ],
                'delegacion' => [
                    0 => [
                        'CA' => 'Cádiz',
                        'CO' => 'Córdoba',
                        'HU' => 'Huelva',
                        'SE' => 'Sevilla'
                    ]
                ],
                'pago_pendiente' => [
                    0 => ['No','Sí',]
                ],
                'profesional_provincia' => [
                    0 => [
                        0 => '', 
                        1 => 'Álava', 
                        2 => 'Albacete', 
                        3 => 'Alicante', 
                        4 => 'Almería', 
                        33 => 'Asturias (Oviedo)', 
                        5 => 'Ávila', 
                        6 => 'Badajoz', 
                        8 => 'Barcelona', 
                        9 => 'Burgos', 
                        10 => 'Cáceres', 
                        11 => 'Cádiz', 
                        39 => 'Cantabria', 
                        12 => 'Castellón', 
                        51 => 'Ceuta', 
                        13 => 'Ciudad Real', 
                        14 => 'Córdoba', 
                        15 => 'A Coruña', 
                        16 => 'Cuenca', 
                        17 => 'Girona', 
                        18 => 'Granada', 
                        19 => 'Guadalajara', 
                        20 => 'Guipúzcoa', 
                        21 => 'Huelva', 
                        22 => 'Huesca', 
                        7 => 'Illes Balears', 
                        23 => 'Jaén', 
                        24 => 'León', 
                        25 => 'Lleida', 
                        27 => 'Lugo', 
                        28 => 'Madrid', 
                        29 => 'Málaga', 
                        52 => 'Melilla', 
                        30 => 'Murcia', 
                        31 => 'Navarra', 
                        32 => 'Ourense', 
                        34 => 'Palencia', 
                        35 => 'Las Palmas', 
                        36 => 'Pontevedra', 
                        26 => 'Rioja, La', 
                        37 => 'Salamanca', 
                        38 => 'Santa Cruz de Tenerife', 
                        40 => 'Segovia', 
                        41 => 'Sevilla', 
                        42 => 'Soria', 
                        43 => 'Tarragona', 
                        44 => 'Teruel', 
                        45 => 'Toledo', 
                        46 => 'Valencia', 
                        47 => 'Valladolid', 
                        48 => 'Vizcaya', 
                        49 => 'Zamora', 
                        50 => 'Zaragoza'
                    ]
                ]
            ]
        ];
        if(isset($opciones[$tabla][$campo][$opt])){
            $res = $opciones[$tabla][$campo][$opt];
        }else{
            $res = [];
        }
        return $res;
    }
    
    public static function getPage($page,$session_page = null){
        if($page == -1){
            if(isset($session_page)){
                $page = $session_page;
            }else{
                $page = 0;
                $session_page = $page;
            }
    	}else{
            $session_page = $page;
    	}
        if($page == 0){
            $session_page = 1;
        }
        return $session_page;
    }
    
    public static function muestraPaginacion($num_elementos,$pagina_actual,$url,$elementos_por_pagina = 50){
        if($num_elementos > $elementos_por_pagina){ 
            $num_pages = ceil($num_elementos / $elementos_por_pagina);
            $hasta=((($pagina_actual - 1) * $elementos_por_pagina) + $elementos_por_pagina );
            if($pagina_actual == $num_pages){
                $hasta = $num_elementos;
            }
            if($num_pages < 5){
                $com = 1;
                $fin = $num_pages;
            }else{
                $com = $pagina_actual - 2;
                $fin = $pagina_actual + 2;
                if($com < 1){
                    $com = 1;
                    $fin = 5;
                }else if($fin > $num_pages){
                    $fin = $num_pages;
                    $com = (int)$num_pages - 4;
                }
            }
            $resultado = '<nav class="text-left"><ul class="pagination mt-4 mb-4">';
            if($pagina_actual > 1){ 
                $resultado .= '<li class="page-item"><a class="page-link" href="'.$url.'1" aria-label="Primera página"><i class="fa fa-angle-double-left"></i></a></li>';
                $resultado .= '<li class="page-item"><a class="page-link" href="'.$url.($pagina_actual - 1).'" aria-label="Anterior"><i class="fa fa-angle-left"></i></a></li>';
            }
            for($i = $com;$i <= $fin;$i++){
                if($i <> $pagina_actual){ 
                    $class = '';
                }else{ 
                    $class = 'active';
                }
                $resultado .= '<li class="page-item '.$class.'"><a class="page-link" href="'.$url.$i.'">'.$i.'</a></li>';
            }
            if($pagina_actual < $num_pages){
                $resultado .= '<li class="page-item"><a class="page-link" aria-label="Siguiente" href="'.$url.($pagina_actual + 1).'"><i class="fa fa-angle-right"></i></a></li>';
                $resultado .= '<li class="page-item"><a class="page-link" href="'.$url.$num_pages.'" aria-label="&Uacute;ltima página"><i class="fa fa-angle-double-right"></i></a></li>';
            }
            $resultado .= '</ul></nav>';
            echo $resultado;
        }
    }
    
    public static function getOrder($nuevo_orden,$session_orden,$orden_por_defecto){
        if($nuevo_orden != false){
            if(isset($session_orden)){
                if($session_orden['orden'] == $nuevo_orden){
                    if($session_orden['tipo_orden'] == 'desc'){
                        $session_orden['tipo_orden'] = 'asc';
                    }else{
                        $session_orden['tipo_orden'] = 'desc';
                    }
                }else{
                    $session_orden['orden'] = $nuevo_orden;
                    $session_orden['tipo_orden'] = 'asc';
                }
            }else{
                $session_orden['orden'] = $nuevo_orden;
                $session_orden['tipo_orden'] = 'desc';
            }
        }else{
            if(!isset($session_orden)){
                $session_orden['orden'] = $orden_por_defecto;
                $session_orden['tipo_orden'] = 'asc';
            }
        }
        return $session_orden;
    }
    
    public static function getSelect($seleccionado,$tabla,$campo,$opt = 0,$multiple = 0,$opcion_todos = 0,$name = null,$css = null){
        $opciones = Utilidades::systemOptions($tabla,$campo,$opt);
        if($name != null){
            $campo = $name;
        }
        if($multiple){
            $result = '<select id="'.$campo.'" name="'.$campo.'[]" class="form-control select2 '.$css.'" multiple>';
            if($seleccionado == null){
                $seleccionado = array();
            }
        }else{
            $result = '<select id="'.$campo.'" name="'.$campo.'" class="form-control select2 '.$css.'">';
        }
        
        if($opcion_todos){
            $result .= '<option value="-1" ></option>';
        }
        
        foreach($opciones as $key => $value):
            $selected = '';
            if($multiple){
                if(in_array($key,$seleccionado)){
                    $selected = 'selected';
                }
            }else{
                if($seleccionado == $key){
                    $selected = 'selected';
                }
            }
            
            $result .= '<option value="'.$key.'" '.$selected.' >'.$value.'</option>';
        endforeach;
        $result .= '</select>';
        return $result;
    }
    
    public static function muestraMensaje($ok,$ko = null,$info = null,$warning = null,$col = '',$cerrar = 1,$centrado = 0){
        $msg = '';
        if((int)$cerrar){
            $boton_cerrar = '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
        }else{
            $boton_cerrar = '';
        }
        if($centrado){
                $class = 'text-center text-bold destacado';
        }else{
                $class = '';
        }
        if(!empty($ok)){
            $msg .= '
                <div class="alert alert-success alert-dismissible '.$class.'" role="alert">
                    '.$boton_cerrar.'
                    '.$ok.'
                </div>';
        }
        if(!empty($ko)){
            $msg .= '
                <div class="alert alert-danger alert-dismissible '.$class.'" role="alert">
                    '.$boton_cerrar.'
                    '.$ko.'
                </div>';
        }
        if(!empty($info)){
            $msg .= '
                <div class="alert alert-info alert-dismissible '.$class.'" role="alert">
                    '.$boton_cerrar.'
                    '.$info.'
                </div>';
        }
        if(!empty($warning)){
            $msg .= '
                <div class="alert alert-warning alert-dismissible '.$class.'" role="alert">
                    '.$boton_cerrar.'
                    '.$warning.'
                </div>';
        }
        if(!empty($msg)){
            echo '<div class="row"><div class="col-12 '.$col.'">
                '.$msg.'
                </div></div>';
        }
    }
    
    public static function encriptaId($id_per){
        return dechex($id_per * 97 * 57);
    }
    
    public static function desencriptaId($id_per){
        if(!empty($id_per)){
            return hexdec($id_per) / 97 / 57;
        }else{
            return 0;
        }
    }
    
    public static function cleanString($string,$mantener_espacios = 0){
        $string = trim($string);
        $string = str_replace(
            array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'),
            array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'),
            $string
        );
        $string = str_replace(
            array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'),
            array('e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'),
            $string
        );
        $string = str_replace(
            array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'),
            array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'),
            $string
        );
        $string = str_replace(
            array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'),
            array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'),
            $string
        );
        $string = str_replace(
            array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'),
            array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'),
            $string
        );
        $string = str_replace(
            array('ñ', 'Ñ', 'ç', 'Ç'),
            array('n', 'N', 'c', 'C'),
            $string
        );
        if($mantener_espacios == 0){
            $string = str_replace(
                array(" "),
                array("_"),
                $string
            );
        }
        $string = str_replace(
            array("\\", "¨", "º", "-", "~",
                 "#", "@", "|", "!", "\"",
                 "·", "$", "%", "&", "/",
                 "(", ")", "?", "'", "¡",
                 "¿", "[", "^", "<code>", "]",
                 "+", "}", "{", "¨", "´",
                 ">", "< ", ";", ",", ":",
                 "."),
            '',
            $string
        );
        return $string;
    }
    
    public static function codificaNumero($numero){
        $caracteres = ['a','b','c','d','e','f','g','h','i','j'];
        $cadena = str_split(strval($numero));
        $resultado = '';
        foreach($cadena as $letra):
            $resultado .= $caracteres[$letra];
        endforeach;
        return $resultado;
    }
    
    public static function carpetaMes($fecha){
        $data = explode('-',$fecha);
        $meses = ['','ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
        if(isset($data[1])){
            $res = $meses[(int)$data[1]];
        }else{
            $res = '-';
        }
        return $res;
    }

    public static function getMes($mes){
        return ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][$mes];
    }
    
    public static function mb_str_pad( $input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT){
        $diff = strlen( $input ) - mb_strlen( $input, 'UTF-8' );
        return str_pad( $input, $pad_length + $diff, $pad_string, $pad_type );
    }
    
    public static function generaCondicion($caso,$busqueda){
        $where = null;
        if($caso == 'usuarios'){
            if($busqueda['id_usu']!=null and $busqueda['id_usu'] > 0){
                $where = 'id_usu = ' . $busqueda['id_usu'];
            }else{
                $where = 'id_usu > 0';
            }
            if($busqueda['nif'] != null){
                $where .= ' and nif LIKE "%' . $busqueda['nif'].'%"';
            }
            if($busqueda['colegiado'] != null){
                $where .= ' and colegiado LIKE "%' . $busqueda['colegiado'].'%"';
            }
            if($busqueda['telefono'] != null){
                $where .= ' and telefono LIKE "%' . $busqueda['telefono'].'%"';
            }
            if($busqueda['email'] != null){
                $where .= ' and email LIKE "%' . $busqueda['email'].'%"';
            }
            if(isset($busqueda['sitcol']) && $busqueda['sitcol'] >= 0){
                $where .= ' and sitcol = ' . $busqueda['sitcol'];
            }
            if(isset($busqueda['rol']) && $busqueda['rol'] != '-1'){
                $where .= ' and rol LIKE "' . $busqueda['rol'].'"';
            }
            if(isset($busqueda['autorizado']) && $busqueda['autorizado'] >= 0){
                $where .= ' and autorizado = ' . $busqueda['autorizado'];
            }
        }else if($caso == 'empresas'){
            if($busqueda['id_emp']!=null and $busqueda['id_emp'] > 0){
                $where = 'id_emp = ' . $busqueda['id_emp'];
            }else{
                $where = 'id_emp > 0';
            }
            if($busqueda['cif'] != null){
                $where .= ' and cif LIKE "%' . $busqueda['cif'].'%"';
            }
            if($busqueda['id_sec'] > 0){
                $where .= ' and id_sec = ' . $busqueda['id_sec'];
            }
            if(isset($busqueda['estado']) && $busqueda['estado'] >= 0){
                $where .= ' and estado = ' . $busqueda['estado'];
            }
        } else if($caso == 'cursos'){
            if($busqueda['id_cur']!=null and $busqueda['id_cur'] > 0){
                $where = 'id_cur = ' . $busqueda['id_cur'];
            }else{
                $where = 'id_cur > 0';
            }
            if($busqueda['nombre'] != null){
                $where .= ' and nombre LIKE "%' . $busqueda['nombre'].'%"';
            }
            if(isset($busqueda['tipo']) && $busqueda['tipo'] >= 0){
                $where .= ' and tipo = ' . $busqueda['tipo'];
            }
            if(isset($busqueda['estado']) && $busqueda['estado'] >= 0){
                $where .= ' and estado = ' . $busqueda['estado'];
            }
            if($busqueda['comienzoDesde'] != null){
                $where .= ' and comienzo >= "' . Utilidades::giraFecha($busqueda['comienzoDesde']).'"';
            }
            if($busqueda['comienzoHasta'] != null){
                $where .= ' and comienzo <= "' . Utilidades::giraFecha($busqueda['comienzoHasta']).'"';
            }
            if($busqueda['finDesde'] != null){
                $where .= ' and fin >= "' . Utilidades::giraFecha($busqueda['finDesde']).'"';
            }
            if($busqueda['finHasta'] != null){
                $where .= ' and fin <= "' . Utilidades::giraFecha($busqueda['finHasta']).'"';
            }
            if($busqueda['id_cat'] != null){
                $where .= ' and id_cat = ' . $busqueda['id_cat'];
            }
        }else if($caso == 'ofertas'){
            if($busqueda['id_ofe']!=null and $busqueda['id_ofe'] > 0){
                $where = 'id_ofe = ' . $busqueda['id_ofe'];
            }else{
                $where = 'id_ofe > 0';
            }
            if($busqueda['titulo'] != null){
                $where .= ' and titulo LIKE "%' . $busqueda['titulo'].'%"';
            }
            if(isset($busqueda['estado']) && $busqueda['estado'] >= 0){
                $where .= ' and estado = ' . $busqueda['estado'];
            }
            if($busqueda['fechaDesde'] != null){
                $where .= ' and fecha >= "' . Utilidades::giraFecha($busqueda['fechaDesde']).'"';
            }
            if($busqueda['fechaHasta'] != null){
                $where .= ' and fecha <= "' . Utilidades::giraFecha($busqueda['fechaHasta']).'"';
            }
            if($busqueda['id_emp'] > 0){
                $where .= ' and id_emp = ' . $busqueda['id_emp'];
            }
            if($busqueda['id_sec'] > 0){
                $where .= ' and id_sec = ' . $busqueda['id_sec'];
            }
        }else if($caso == 'candidaturas'){
            if($busqueda['id_can']!=null and $busqueda['id_can'] > 0){
                $where = 'id_can = ' . $busqueda['id_can'];
            }else{
                $where = 'id_can > 0';
            }
            if($busqueda['id_usu'] != null){
                $where .= ' and id_usu = ' . $busqueda['id_usu'];
            }
            if($busqueda['id_ofe'] != null){
                $where .= ' and id_ofe = ' . $busqueda['id_ofe'];
            }
            if($busqueda['id_emp'] != null){
                $where .= ' and id_emp = ' . $busqueda['id_emp'];
            }
            if($busqueda['fechaDesde'] != null){
                $where .= ' and candidaturasFecha >= "' . Utilidades::giraFecha($busqueda['fechaDesde']).'"';
            }
            if($busqueda['fechaHasta'] != null){
                $where .= ' and candidaturasFecha <= "' . Utilidades::giraFecha($busqueda['fechaHasta']).'"';
            }
            if($busqueda['candidaturasEstado'] != null){
                $where .= ' and candidaturasEstado = ' . $busqueda['candidaturasEstado'];
            }
        }else if($caso == 'inscripciones'){
            if($busqueda['id_ins']!=null and $busqueda['id_ins'] > 0){
                $where = 'id_ins = ' . $busqueda['id_ins'];
            }else{
                $where = 'id_ins > 0';
            }
            if($busqueda['id_usu'] != null){
                $where .= ' and id_usu  = ' . $busqueda['id_usu'];
            }
            if($busqueda['id_cur'] != null){
                $where .= ' and id_cur = ' . $busqueda['id_cur'];
            }
            if($busqueda['fechaDesde'] != null){
                $where .= ' and fecha >= "' . Utilidades::giraFecha($busqueda['fechaDesde']).'"';
            }
            if($busqueda['fechaHasta'] != null){
                $where .= ' and fecha <= "' . Utilidades::giraFecha($busqueda['fechaHasta']).'"';
            }
            if(isset($busqueda['tipo']) && $busqueda['tipo'] >= 0){
                $where .= ' and tipo = ' . $busqueda['tipo'];
            }
            if(isset($busqueda['estado']) && $busqueda['estado'] >= 0){
                $where .= ' and estado = ' . $busqueda['estado'];
            }
            if(isset($busqueda['pago']) && $busqueda['pago']  >= 0){
                $where .= ' and pago = ' . $busqueda['pago'];
            }
        }else if($caso == 'inscritos'){
            if($busqueda['id_ui']!=null and $busqueda['id_ui'] > 0){
                $where = 'id_ui = ' . $busqueda['id_ui'];
            }else{
                $where = 'id_ui > 0';
            }
            if($busqueda['id_usu'] != null){
                $where .= ' and id_usu = ' . $busqueda['id_usu'];
            }
            if($busqueda['id_cur'] != null){
                $where .= ' and id_cur = ' . $busqueda['id_cur'];
            }
            if($busqueda['fechaDesde'] != null){
                $where .= ' and fecha >= "' . Utilidades::giraFecha($busqueda['fechaDesde']).'"';
            }
            if($busqueda['fechaHasta'] != null){
                $where .= ' and fecha <= "' . Utilidades::giraFecha($busqueda['fechaHasta']).'"';
            }
            if(isset($busqueda['tipo']) && $busqueda['tipo'] >= 0){
                $where .= ' and tipo = ' . $busqueda['tipo'];
            }
            if(isset($busqueda['estado']) && $busqueda['estado'] >= 0){
                $where .= ' and estado = ' . $busqueda['estado'];
            }
        }else if($caso == 'categorias'){
            if($busqueda['id_cat']!=null and $busqueda['id_cat'] > 0){
                $where = 'id_cat = ' . $busqueda['id_cat'];
            }else{
                $where = 'id_cat > 0';
            }
        }else if($caso == 'sectores'){
            if($busqueda['id_sec']!=null and $busqueda['id_sec'] > 0){
                $where = 'id_sec = ' . $busqueda['id_sec'];
            }else{
                $where = 'id_sec > 0';
            }
        }else if($caso == 'carpetas'){
            if($busqueda['id_car']!=null and $busqueda['id_car'] > 0){
                $where = 'id_car = ' . $busqueda['id_car'];
            }else{
                $where = 'id_car > 0';
            }
        }
        else if($caso == 'participantes'){
            if($busqueda['id_par']!=null and $busqueda['id_par'] > 0){
                $where = 'id_par = ' . $busqueda['id_par'];
            }else{
                $where = 'id_par > 0';
            }
            if($busqueda['id_usu'] != null){
                $where .= ' and id_usu = ' . $busqueda['id_usu'];
            }
            if($busqueda['id_cur'] != null){
                $where .= ' and id_cur = ' . $busqueda['id_cur'];
            }
            if($busqueda['menorNombre'] != null){
                $where .= ' and (menoresNombre LIKE "%' . $busqueda['menorNombre'].'%" OR  menoresApellidos LIKE "%' . $busqueda['menorNombre'].'%" OR CONCAT(menoresNombre," ",menoresApellidos) LIKE "%' . $busqueda['menorNombre'].'%")';
            }
            if($busqueda['fechaDesde'] != null){
                $where .= ' and fecha >= "' . Utilidades::giraFecha($busqueda['fechaDesde']).'"';
            }
            if($busqueda['fechaHasta'] != null){
                $where .= ' and fecha <= "' . Utilidades::giraFecha($busqueda['fechaHasta']).'"';
            }
            if(isset($busqueda['estado']) && $busqueda['estado'] >= 0){
                $where .= ' and estado = ' . $busqueda['estado'];
            }
        }
        return $where;
    }
    
    public static function ejecutaCronJobs(){
        $db_opc = new Opciones();
        $campo = 'cron-ejecutada';
        $opcion = $db_opc->getByKey($campo);
        $cronEjecutadaHoy = Utilidades::esHoy($opcion->get('valor'));
        if(!$cronEjecutadaHoy){
            $db_insc = new Inscripciones();
            $db_insc->borraInscripcionesIncompletasCursosTerminados();
            
            $opcion->cronEjecutada($campo);
        }
    }
    
    public static function esHoy($fecha){
        $date = date("Y-m-d",strtotime($fecha));
        if($date == date("Y-m-d")){
            return true;
        }else{
            return false; 
        }
    }

    public static function sectionsRedirect($id_sec = 0){
        $sections = [
            0 => [
                'module'        => 'application/default',
                'controller'    => 'index',
                'action'        => 'index'
            ],
            1 => [
                'module'        => 'backend/default',
                'controller'    => 'empleo',
                'action'        => 'ofertasempleo'
            ],
        ];

        return $sections[$id_sec];
    }

    public static function encriptaIdCurso($id, $type = 'enc'){
        if($type == 'enc'){
            $hash = base64_encode(dechex(17 * 7 * 11 * $id));
        }else{
            $hash = hexdec(base64_decode($id)) / 17 / 7 / 11;
        }
        return $hash;
    }
}