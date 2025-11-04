<?php
namespace Application\Model\Utility;
use \PHPExcel;
use \PHPExcel_Shared_Date;
use \PHPExcel_Style_NumberFormat;

class Exportar{
    
    //protected $_cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
    
    public static function usuarios($objects){
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $f = 1;
        $c = 0;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Nombre');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Apellidos');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Colegiado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'NIF');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Telefono');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Email');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Rol');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Alta');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Situación laboral');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Situación colegiado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Titulación');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Máster');$c++;
        
        foreach($objects as $object):
            $f++;
            $c = 0;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('nombre'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('apellidos'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('colegiado'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('nif'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('telefono'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('email'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('usuarios', 'rol')[$object->get('rol')]);$c++;
            $alta1 = Utilidades::giraFecha($object->get('alta'));                // Le damos la vuelta porque viene en formato dd-mm-yyyy . Si viniera de un rowset, no haría falta.
            if($alta1 != null){
                $alta2 = new \DateTime($alta1);
                $objWorkSheet->SetCellValue($cols[$c].$f, PHPExcel_Shared_Date::PHPToExcel($alta2));
                $objWorkSheet->getStyle($cols[$c].$f)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('usuarios', 'sitlab')[(int)$object->get('sitlab')]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('usuarios', 'sitcol')[(int)$object->get('sitcol')]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('titulacion'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('master'));$c++;
        endforeach;
        
        foreach(range('A','Z') as $columnID) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }
        return $objPHPExcel;
    }
    
    public static function empresas($objects){
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $f = 1;
        $c = 0;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Empresa');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'CIF');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Sector');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Estado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Usuario Autorizado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Teléfono');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Email');$c++;

        foreach($objects as $object):
            $f++;
            $c = 0;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['empresasNombre']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['cif']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['sectoresNombre']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('empresas', 'estado')[$object['estado']]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['usuariosNombre'].' '.$object['usuariosApellidos']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['telefono']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['email']);$c++;
        endforeach;
        
        foreach(range('A','Z') as $columnID) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }
        return $objPHPExcel;
    }
    
    public static function cursos($objects){
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $f = 1;
        $c = 0;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Nombre');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Tipo');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Estado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Comienzo');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Fin');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Horario');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Ubicación');$c++;
        
        foreach($objects as $object):
            $f++;
            $c = 0;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('nombre'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('cursos', 'tipo')[(int)$object->get('tipo')]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('cursos', 'estado')[(int)$object->get('estado')]);$c++;
            $comienzo1 = Utilidades::giraFecha($object->get('comienzo'));                // Le damos la vuelta porque viene en formato dd-mm-yyyy . Si viniera de un rowset, no haría falta.
            if($comienzo1 != null){
                $comienzo2 = new \DateTime($comienzo1);
                $objWorkSheet->SetCellValue($cols[$c].$f, PHPExcel_Shared_Date::PHPToExcel($comienzo2));
                $objWorkSheet->getStyle($cols[$c].$f)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $c++;
            $fin1 = Utilidades::giraFecha($object->get('fin'));  
            if($fin1 != null){
                $fin2 = new \DateTime($fin1);
                $objWorkSheet->SetCellValue($cols[$c].$f, PHPExcel_Shared_Date::PHPToExcel($fin2));
                $objWorkSheet->getStyle($cols[$c].$f)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('horario'));$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object->get('ubicacion'));$c++;
        endforeach;
        
        foreach(range('A','Z') as $columnID) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }
        return $objPHPExcel;
    }
    
    public static function inscripciones($objects){
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $f = 1;
        $c = 0;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Usuario');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Teléfono');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Email');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Curso');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Tipo');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Fecha');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Importe');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Estado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Pago');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Nº inscritos');$c++;

        foreach($objects as $object):
            $f++;
            $c = 0;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['usuariosNombre'].' '.$object['usuariosApellidos']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['telefono']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['email']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['cursosNombre']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('cursos', 'tipo')[$object['tipo']]);$c++;
            $fecha1 = Utilidades::giraFecha($object['fecha']);  
            if($fecha1 != null){
                $fecha2 = new \DateTime($fecha1);
                $objWorkSheet->SetCellValue($cols[$c].$f, PHPExcel_Shared_Date::PHPToExcel($fecha2));
                $objWorkSheet->getStyle($cols[$c].$f)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['importe']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('inscripciones', 'estado')[$object['estado']]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('inscripciones', 'pago')[$object['pago']]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['inscritos']);$c++;
        endforeach;
        
        foreach(range('A','Z') as $columnID) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }
        return $objPHPExcel;
    }
    
    public static function inscritos($objects){
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $f = 1;
        $c = 0;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Usuario');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Teléfono');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Email');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Curso');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Tipo');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Fecha');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Importe');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Estado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Pago');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Solicita beca');$c++;

        foreach($objects as $object):
            $f++;
            $c = 0;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['usuariosNombre'].' '.$object['usuariosApellidos']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['telefono']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['email']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['cursosNombre']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('cursos', 'tipo')[$object['tipo']]);$c++;
            $fecha1 = Utilidades::giraFecha($object['fecha']);  
            if($fecha1 != null){
                $fecha2 = new \DateTime($fecha1);
                $objWorkSheet->SetCellValue($cols[$c].$f, PHPExcel_Shared_Date::PHPToExcel($fecha2));
                $objWorkSheet->getStyle($cols[$c].$f)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['importe']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('inscripciones', 'estado')[(int)$object['estado']]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('inscripciones', 'pago')[(int)$object['pago']]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('inscripciones', 'beca')[(int)$object['beca']]);$c++;
        endforeach;
        
        foreach(range('A','Z') as $columnID) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }
        return $objPHPExcel;
    }
    
    public static function participantes($objects){
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $f = 1;
        $c = 0;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Usuario');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Teléfono');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Email');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Menor inscrito');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Curso');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Tipo');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Fecha');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Importe');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Estado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Pago');$c++;

        foreach($objects as $object):
            $f++;
            $c = 0;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['usuariosNombre'].' '.$object['usuariosApellidos']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['telefono']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['email']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['menoresNombre'].' '.$object['menoresApellidos']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['cursosNombre']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('cursos', 'tipo')[$object['tipo']]);$c++;
            $fecha1 = Utilidades::giraFecha($object['fecha']);  
            if($fecha1 != null){
                $fecha2 = new \DateTime($fecha1);
                $objWorkSheet->SetCellValue($cols[$c].$f, PHPExcel_Shared_Date::PHPToExcel($fecha2));
                $objWorkSheet->getStyle($cols[$c].$f)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['importe']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('inscripciones', 'estado')[$object['estado']]);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('inscripciones', 'pago')[$object['pago']]);$c++;
        endforeach;
        
        foreach(range('A','Z') as $columnID) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }
        return $objPHPExcel;
    }
    
    public static function ofertas($objects){
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $f = 1;
        $c = 0;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Título');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Estado');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Fecha');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Plazas');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Empresa');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Sector');$c++;
        
        foreach($objects as $object):
            $f++;
            $c = 0;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['titulo']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('ofertas', 'estado')[$object['estado']]);$c++;
            $fecha1 = Utilidades::giraFecha($object['fecha']);
            if($fecha1 != null){
                $fecha2 = new \DateTime($fecha1);
                $objWorkSheet->SetCellValue($cols[$c].$f, PHPExcel_Shared_Date::PHPToExcel($fecha2));
                $objWorkSheet->getStyle($cols[$c].$f)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['plazas']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['empresasNombre']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['sectoresNombre']);$c++;
        endforeach;
        
        foreach(range('A','Z') as $columnID) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }
        return $objPHPExcel;
    }
    
    public static function candidaturas($objects){
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $f = 1;
        $c = 0;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Usuario');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Teléfono');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Email');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Oferta');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Empresa');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Fecha');$c++;
        $objWorkSheet->SetCellValue($cols[$c].$f, 'Estado');$c++;
        
        foreach($objects as $object):
            $f++;
            $c = 0;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['usuariosNombre'].' '.$object['usuariosApellidos']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['telefono']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['email']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['titulo']);$c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, $object['empresasNombre']);$c++;
            $fecha1 = Utilidades::giraFecha($object['candidaturasFecha']);
            if($fecha1 != null){
                $fecha2 = new \DateTime($fecha1);
                $objWorkSheet->SetCellValue($cols[$c].$f, PHPExcel_Shared_Date::PHPToExcel($fecha2));
                $objWorkSheet->getStyle($cols[$c].$f)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $c++;
            $objWorkSheet->SetCellValue($cols[$c].$f, Utilidades::systemOptions('candidaturas', 'estado')[$object['candidaturasEstado']]);$c++;
        endforeach;
        
        foreach(range('A','Z') as $columnID) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }
        return $objPHPExcel;
    }
    
}