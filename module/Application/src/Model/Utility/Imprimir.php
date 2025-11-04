<?php
namespace Application\Model\Utility;

use Application\Model\Entity\Inscrito;
use Application\Model\Utility\Parrafo;
use Application\Model\Utility\Pdfavanzado;
use \PHPExcel;
use \PHPExcel_Shared_Date;
use \PHPExcel_Style_NumberFormat;

class Imprimir{
    
    public static function excelEncargos($objects,$tipo = 0){
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objWorkSheet = $objPHPExcel->getActiveSheet();
        
        $db_ruta = new Rutas();
        $rutas = $db_ruta->getAll(null,'ruta ASC');
        $db_com = new Comerciales();
        $comerciales = $db_com->getAll(null,'comercial ASC');
            
        $i = 1;
        $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
        $objWorkSheet->SetCellValue('A'.$i, 'Código');
        $objWorkSheet->SetCellValue('B'.$i, 'Nombre comercial');
        $objWorkSheet->SetCellValue('C'.$i, 'Razón social');
        $objWorkSheet->SetCellValue('D'.$i, 'Ruta');
        $objWorkSheet->SetCellValue('E'.$i, 'Facturación');
        $objWorkSheet->SetCellValue('F'.$i, 'Fecha');
        $objWorkSheet->SetCellValue('G'.$i, 'Base');
        $objWorkSheet->SetCellValue('H'.$i, 'Descuento');
        $objWorkSheet->SetCellValue('I'.$i, 'IVA');
        $objWorkSheet->SetCellValue('J'.$i, 'Total');
        if($tipo == 0){
            $objWorkSheet->SetCellValue('K'.$i, 'Pendiente de cobro');
            $objWorkSheet->SetCellValue('L'.$i, 'Estado');
            $objWorkSheet->SetCellValue('M'.$i, 'Tipo');
            $objWorkSheet->SetCellValue('N'.$i, 'Comercial');
            $objWorkSheet->SetCellValue('O'.$i, 'Fecha cobro');
            $objWorkSheet->SetCellValue('P'.$i, 'Facturado');
        }
        $where = '';
        foreach($objects as $object):
            if($i > 1){
                $where .= ' OR ';
            }
            $where .= 'encargo = '.$object['id_enc'];
            $i++;
        
            if(isset($rutas[(int)$object['ruta']])){ 
                $ruta = $rutas[(int)$object['ruta']]->get('ruta');
            }else{
                $ruta = '';
            }
        
            if(isset($comerciales[(int)$object['comercial']])){ 
                $comercial = $comerciales[(int)$object['comercial']]->get('comercial');
            }else{
                $comercial = '';
            }
            
            $objWorkSheet->SetCellValue('A'.$i, $object['codigo']);
            if(isset($object['usuarioRazon_social'])){
                $nombre_cliente = $object['usuarioRazon_social'];
            }else{
                $nombre_cliente = $object['nombre'];
            }
            $objWorkSheet->SetCellValue('B'.$i, mb_strtoupper($object['nombre'],'UTF-8'));
			$objWorkSheet->SetCellValue('C'.$i, mb_strtoupper($object['usuarioRazon_social'],'UTF-8'));
            $objWorkSheet->SetCellValue('D'.$i, $ruta);
			$objWorkSheet->SetCellValue('E'.$i, Utilidades::systemOptions('usuarios', 'facturacion')[(int)$object['facturacion']]);
            
            $fecha = $object['fecha'];
            if($fecha != null){
                $fecha_1 = new \DateTime($fecha);
                $objWorkSheet->SetCellValue('F' . $i, PHPExcel_Shared_Date::PHPToExcel($fecha_1));
                $objWorkSheet->getStyle('F' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $objWorkSheet->SetCellValue('G'.$i, $object['base']);
            $objWorkSheet->SetCellValue('H'.$i, $object['descuento']);
            $objWorkSheet->SetCellValue('I'.$i, $object['iva']);
            $objWorkSheet->SetCellValue('J'.$i, $object['total']);
            if($tipo == 0){
                $objWorkSheet->SetCellValue('K'.$i, $object['pendiente']);
                $objWorkSheet->SetCellValue('L'.$i, Utilidades::systemOptions('encargos', 'estado')[(int)$object['estado']]);
                $objWorkSheet->SetCellValue('M'.$i, Utilidades::systemOptions('encargos', 'tipo')[(int)$object['tipo']]);
                $objWorkSheet->SetCellValue('N'.$i, $comercial);
                if($object['pendiente'] == 0){
                    $fecha_cobro = $object['cobrosFecha'];
                    if(!empty($fecha_cobro)){
                        $fecha_2 = new \DateTime($fecha_cobro);
                        $objWorkSheet->SetCellValue('O' . $i, PHPExcel_Shared_Date::PHPToExcel($fecha_2));
                        $objWorkSheet->getStyle('O' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
                    }
                }
                if($object['id_fac'] != 0){
                    $objWorkSheet->SetCellValue('P'.$i, 'SÍ');
                }else{
                    $objWorkSheet->SetCellValue('P'.$i, 'NO');
                }
            }
        endforeach;
        
        foreach(range('A','N') as $columnID) {
            $objWorkSheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        if($tipo == 0){
            $objWorkSheet->setTitle('Ventas');
            /* Líneas */
            $objPHPExcel->createSheet(1);
            $objPHPExcel->setActiveSheetIndex(1);
            $objWorkSheet = $objPHPExcel->getActiveSheet();
            $objWorkSheet->setTitle('Líneas');
            
            $db_lineas = new Lineas();
            $lineas = $db_lineas->getLineas($where,'codigo');
            $db_fam = new Familias();
            $familias = $db_fam->getAll();
            
            $i = 1;
            $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
            $objWorkSheet->SetCellValue('A'.$i, 'Código');
            $objWorkSheet->SetCellValue('B'.$i, 'Tipo');
            $objWorkSheet->SetCellValue('C'.$i, 'Fecha');
            $objWorkSheet->SetCellValue('D'.$i, 'Familia');
            $objWorkSheet->SetCellValue('E'.$i, 'Artículo');
            $objWorkSheet->SetCellValue('F'.$i, 'Precio');
            $objWorkSheet->SetCellValue('G'.$i, 'Cantidad');
            $objWorkSheet->SetCellValue('H'.$i, 'Descuento (%)');
            $objWorkSheet->SetCellValue('I'.$i, 'Total');
            foreach($lineas as $object):
                $i++;
                if(isset($familias[(int)$object['familia']])){ 
                    $familia = $familias[(int)$object['familia']]->get('familia');
                }else{
                    $familia = '';
                }
                $objWorkSheet->SetCellValue('A'.$i, $object['codigo']);
                $objWorkSheet->SetCellValue('B'.$i, Utilidades::systemOptions('encargos', 'tipo')[(int)$object['tipo']]);
                
                $fecha = $object['fecha'];
                if($fecha != null){
                    $fecha_1 = new \DateTime($fecha);
                    $objWorkSheet->SetCellValue('C' . $i, PHPExcel_Shared_Date::PHPToExcel($fecha_1));
                    $objWorkSheet->getStyle('C' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
                }
                $objWorkSheet->SetCellValue('D'.$i, $familia);
                $objWorkSheet->SetCellValue('E'.$i, $object['descripcion']);
                $objWorkSheet->SetCellValue('F'.$i, $object['precio']);
                $objWorkSheet->SetCellValue('G'.$i, $object['cantidad']);
                $objWorkSheet->SetCellValue('H'.$i, $object['descuento']);
                $objWorkSheet->SetCellValue('I'.$i, round($object['precio']*$object['cantidad']*(1-$object['descuento']/100),2));
            endforeach;
            foreach(range('A','I') as $columnID) {
                $objWorkSheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            
            /* Cobros */
            $objPHPExcel->createSheet(2);
            $objPHPExcel->setActiveSheetIndex(2);
            $objWorkSheet = $objPHPExcel->getActiveSheet();
            $objWorkSheet->setTitle('Cobros');
            
            $db_cobros = new Cobros();
            $cobros = $db_cobros->getCobros($where,'codigo');
            
            $i = 1;
            $objWorkSheet->getStyle('A1:Z1')->getFont()->setBold(true);
            $objWorkSheet->SetCellValue('A'.$i, 'Código');
            $objWorkSheet->SetCellValue('B'.$i, 'Tipo');
            $objWorkSheet->SetCellValue('C'.$i, 'Cliente');
            $objWorkSheet->SetCellValue('D'.$i, 'Ruta');
            $objWorkSheet->SetCellValue('E'.$i, 'Creación');
            $objWorkSheet->SetCellValue('F'.$i, 'Vencimiento');
            $objWorkSheet->SetCellValue('G'.$i, 'Estado');
            $objWorkSheet->SetCellValue('H'.$i, 'Cobro');
            $objWorkSheet->SetCellValue('I'.$i, 'Importe');
            $objWorkSheet->SetCellValue('J'.$i, 'Forma de pago');
            foreach($cobros as $object):
                $i++;
                if(isset($rutas[(int)$object['ruta']])){ 
                    $ruta = $rutas[(int)$object['ruta']]->get('ruta');
                }else{
                    $ruta = '';
                }

                $objWorkSheet->SetCellValue('A'.$i, $object['codigo']);
                $objWorkSheet->SetCellValue('B'.$i, Utilidades::systemOptions('encargos', 'tipo')[(int)$object['tipo']]);
                $objWorkSheet->SetCellValue('C'.$i, mb_strtoupper($object['nombre'],'UTF-8'));
                $objWorkSheet->SetCellValue('D'.$i, $ruta);

                $fechaa = $object['creacion'];
                if($fechaa != null){
                    $fecha_1 = new \DateTime($fechaa);
                    $objWorkSheet->SetCellValue('E' . $i, PHPExcel_Shared_Date::PHPToExcel($fecha_1));
                    $objWorkSheet->getStyle('E' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
                }
                $fechab = $object['vencimiento'];
                if($fechab != null){
                    $fecha_1 = new \DateTime($fechab);
                    $objWorkSheet->SetCellValue('F' . $i, PHPExcel_Shared_Date::PHPToExcel($fecha_1));
                    $objWorkSheet->getStyle('F' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
                }
                $objWorkSheet->SetCellValue('G'.$i, Utilidades::systemOptions('cobros', 'estado')[(int)$object['estado']]);
                $fechac = $object['cobro'];
                if($fechac != null){
                    $fecha_1 = new \DateTime($fechac);
                    $objWorkSheet->SetCellValue('H' . $i, PHPExcel_Shared_Date::PHPToExcel($fecha_1));
                    $objWorkSheet->getStyle('H' . $i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY);
                }
                $objWorkSheet->SetCellValue('I'.$i, $object['cantidad']);
                if(isset(Utilidades::systemOptions('cobros', 'formapago')[(int)$object['formapago']])){
                    $objWorkSheet->SetCellValue('J'.$i, Utilidades::systemOptions('cobros', 'formapago')[(int)$object['formapago']]);
                }
            endforeach;
            foreach(range('A','J') as $columnID) {
                $objWorkSheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            $objPHPExcel->setActiveSheetIndex(0);
        }
        return $objPHPExcel;
    }
    
    public static function encargosPdf($id_enc,$guarda = 0){
        $encargo = new Encargo($id_enc);
        $tipo = (int)$encargo->get('tipo');
        if($tipo > 0){
            $cliente = $encargo->get('cliente-obj');
            $lineas = $encargo->get('lineas');
            if($tipo == 0){
                $datos_cliente = [
                    'razon_social'  => $cliente->get('razon_social'),
                    'cif'           => $cliente->get('cif'),
                    'direccion'     => $cliente->get('direccion-lineas')
                ];
            }else{
                $datos_cliente = [
                    'razon_social'  => $encargo->get('razon_social'),
                    'cif'           => $encargo->get('cif'),
                    'direccion'     => explode('<br />',nl2br($encargo->get('direccion')))
                ];
            }

            $pdf = \ZendPdf\PdfDocument::load(null);
            $parrafo = new Parrafo();
            $pdfavanzado = new Pdfavanzado();
            // Estilos
            $style = new \ZendPdf\Style();
            $style->setFillColor(new \ZendPdf\Color\Html('#000000'));
            //$style->setLineColor(new \ZendPdf\Color\GrayScale(0.6));
            $style->setLineColor(new \ZendPdf\Color\Html('#000000'));
            $font = \ZendPdf\Font::fontWithName(\ZendPdf\Font::FONT_HELVETICA);
            $fontb = \ZendPdf\Font::fontWithName(\ZendPdf\Font::FONT_HELVETICA_BOLD);
            $font_size07 = 7 + 3;
            $font_size08 = 8 + 5;
            $font_size09 = 9 + 5;
            $font_size10 = 10 + 5;
            $font_size12 = 12 + 5;
            $salto_linea = 9 + 5;
            // Márgenes
            $x_margen = 40;
            $y_margen = 40;

            $pdf->pages[] = ($page = $pdf->newPage('A4'));
            $x_margen_end = $page->getWidth() - $x_margen;
            $y_margen_start = $page->getHeight() - $y_margen;
            $y = $y_margen_start;

            // Cabecera
            $style->setFont($fontb, $font_size12);
            $page->setStyle($style);
            $tipo_texto = mb_strtoupper(Utilidades::systemOptions('encargos', 'tipo')[$tipo],'UTF-8');
            $page->drawText($tipo_texto,$x_margen, $y - 35,'UTF-8');
            if($tipo > 1){
                $imageFile = './public/images/logo-colorsur.jpg';
                $logo_width = 171;
                $logo_height = 50;
                $y += 10;
                $stampImage = \ZendPdf\Image::imageWithPath($imageFile);
                $page->drawImage($stampImage, $x_margen_end - $logo_width, $y - $logo_height , $x_margen_end, $y);
                $y -= $logo_height;
            }
            $y -= ($salto_linea)*3;

            $x_col2 = $page->getWidth() / 2 + $x_margen / 2 - 40;
            // Datos de Colorsur
            if($tipo > 1){
                $y_rec = $y + $salto_linea + 5;
                $y0 = $y;
                $style->setFont($fontb, $font_size08);
                $page->setStyle($style);
                $page->drawText('Colorsur Laboratorio Digital S.L.',$x_margen, $y,'UTF-8');
                $y -= ($salto_linea)*1;
                $style->setFont($font, $font_size08);
                $page->setStyle($style);
                $page->drawText('B90099664',$x_margen, $y,'UTF-8');
                $y -= ($salto_linea)*1;
                $page->drawText('c/ Aviación, 47',$x_margen, $y,'UTF-8');
                $y -= ($salto_linea)*1;
                $page->drawText('41007 Sevilla',$x_margen, $y,'UTF-8');
                $y -= ($salto_linea)*1;
                $page->drawRectangle($x_margen - 10,$y - 10,$x_col2 - 30,$y_rec,'SHAPE_DRAW_STROKE');
                $y = $y0;
            }

            // Datos del cliente
            $y_rec = $y + $salto_linea + 5;
            $style->setFont($fontb, $font_size08);
            $page->setStyle($style);
            $page->drawText('Cliente',$x_col2, $y,'UTF-8');
            $y -= ($salto_linea)*1;
            $style->setFont($font, $font_size08);
            $page->setStyle($style);
            $page->drawText($datos_cliente['razon_social'],$x_col2, $y,'UTF-8');
            $y -= ($salto_linea)*1;
            $page->drawText($datos_cliente['cif'],$x_col2, $y,'UTF-8');
            $y -= ($salto_linea)*1;
            $page->drawText($datos_cliente['direccion'][0],$x_col2, $y,'UTF-8');
            $y -= ($salto_linea)*1;
            if(isset($datos_cliente['direccion'][1])){
                $page->drawText($datos_cliente['direccion'][1],$x_col2, $y,'UTF-8');
            }
            $page->drawRectangle($x_col2 - 10,$y - 10,$x_margen_end,$y_rec,'SHAPE_DRAW_STROKE');
            $y -= ($salto_linea)*3;
            // Tabla 1
            $x_t1_col1 = $x_margen;
            $x_t1_col2 = $x_margen*3.4;
            $x_t1_col3 = $x_margen*5.5;
            $y_rec = $y + $salto_linea + 5;
            $style->setFont($fontb, $font_size08);
            $page->setStyle($style);
            $page->drawText('Nº',$x_t1_col1, $y,'UTF-8');
            $page->drawText('Fecha',$x_t1_col2, $y,'UTF-8');
            $page->drawText('Transporte',$x_t1_col3, $y,'UTF-8');
            $y -= ($salto_linea)*1;
            $style->setFont($font, $font_size08);
            $page->setStyle($style);
            $page->drawText($encargo->get('codigo'),$x_t1_col1, $y,'UTF-8');
            $page->drawText($encargo->get('fecha'),$x_t1_col2, $y,'UTF-8');
            $page->drawText($cliente->get('ruta-nombre'),$x_t1_col3, $y,'UTF-8');
            $page->drawRectangle($x_t1_col1 - 10,$y - 10,$x_margen_end,$y_rec,'SHAPE_DRAW_STROKE');
            $page->drawLine($x_t1_col2 - 10,$y - 10,$x_t1_col2 - 10,$y_rec);
            $page->drawLine($x_t1_col3 - 10,$y - 10,$x_t1_col3 - 10,$y_rec);
            $y -= ($salto_linea)*3;
            // Tabla 2
            $x_t2_col1 = $x_margen;
            $x_t2_col2 = $x_margen*6.7;
            $x_t2_col4 = $x_margen*8.7;
            $x_t2_col5 = $x_margen*10.5;
            $x_t2_col6 = $x_margen*12.2;
            $y_rec = $y + $salto_linea + 5;
            $style->setFont($fontb, $font_size08);
            $page->setStyle($style);
            $page->drawText('Artículo',$x_t2_col1, $y,'UTF-8');
            $page->drawText('Cantidad',$x_t2_col2, $y,'UTF-8');
            $page->drawText('Precio',$x_t2_col4, $y,'UTF-8');
            $page->drawText('Dto.',$x_t2_col5, $y,'UTF-8');
            $page->drawText('Subtotal',$x_t2_col6, $y,'UTF-8');
            $y -= ($salto_linea)*2;
            $page->drawLine($x_t2_col1 - 10,$y + 20,$x_margen_end,$y + 20);
            $style->setFont($font, $font_size07);
            $page->setStyle($style);
            $id_alb = 0;
            foreach($lineas as $linea):
                
                if($linea->get('id_alb') != $id_alb && $tipo == 2){
                    if($id_alb != 0){
                        $y -= ($salto_linea);
                    }
                    $id_alb = $linea->get('id_alb');
                    $encargo1 = new Encargo($id_alb);
                    $style->setFont($fontb, $font_size07);
                    $page->setStyle($style);
                    $page->drawText('ALBARÁN '.$encargo1->get('fecha'),$x_t2_col1,$y,'UTF-8');
                    $y -= ($salto_linea);
                    $style->setFont($font, $font_size07);
                    $page->setStyle($style);
                }
                
                //$page->drawText($linea->get('descripcion'),$x_t2_col1, $y,'UTF-8');
                $y_linea = $y;
                $lines = $parrafo->getLines($linea->get('descripcion'),$font_size08, 4800);
                foreach($lines as $line):
                    //$text_width = $pdfavanzado->getTextWidth($line, $font, $font_size08);
                    $page->drawText($line,$x_t2_col1,$y,'UTF-8');
                    $y -= ($salto_linea);
                endforeach;
                $y += ($salto_linea);
                $page->drawText(number_format($linea->get('cantidad'),2,',','.'),$x_t2_col2, $y_linea,'UTF-8');
                $page->drawText(number_format($linea->get('precio'),2,',','.').' €',$x_t2_col4, $y_linea,'UTF-8');
                $descuento = $linea->get('descuento');
                if(!empty($descuento)){
                    $page->drawText($descuento.'%',$x_t2_col5, $y_linea,'UTF-8');
                }
                $page->drawText(number_format($linea->get('total'),2,',','.').' €',$x_t2_col6, $y_linea,'UTF-8');
                $y -= ($salto_linea)*1;

                if($y < $y_margen*3 + 50){
                    // Cerramos la tabla
                    $page->drawRectangle($x_t2_col1 - 10,$y - 10,$x_margen_end,$y_rec,'SHAPE_DRAW_STROKE');
                    $page->drawLine($x_t2_col2 - 10,$y - 10,$x_t2_col2 - 10,$y_rec);
                    $page->drawLine($x_t2_col4 - 10,$y - 10,$x_t2_col4 - 10,$y_rec);
                    $page->drawLine($x_t2_col5 - 10,$y - 10,$x_t2_col5 - 10,$y_rec);
                    $page->drawLine($x_t2_col6 - 10,$y - 10,$x_t2_col6 - 10,$y_rec);
                    // Creamos una nueva página
                    $pdf->pages[] = ($page = $pdf->newPage('A4'));
                    $y = $y_margen_start;
                    // Cabecera
                    $style->setFont($fontb, $font_size12);
                    $page->setStyle($style);
                    $page->drawText($tipo_texto,$x_margen, $y - 35,'UTF-8');
                    if($tipo > 1){
                        $imageFile = './public/images/logo-colorsur.jpg';
                        $logo_width = 171;
                        $logo_height = 50;
                        $y += 10;
                        $stampImage = \ZendPdf\Image::imageWithPath($imageFile);
                        $page->drawImage($stampImage, $x_margen_end - $logo_width, $y - $logo_height , $x_margen_end, $y);
                        $y -= $logo_height;
                    }
                    $y -= ($salto_linea)*3;
                    // Cabecera de la tabla
                    $y_rec = $y + $salto_linea + 5;
                    $style->setFont($fontb, $font_size08);
                    $page->setStyle($style);
                    $page->drawText('Artículo',$x_t2_col1, $y,'UTF-8');
                    $page->drawText('Cantidad',$x_t2_col2, $y,'UTF-8');
                    $page->drawText('Precio',$x_t2_col4, $y,'UTF-8');
                    $page->drawText('Dto.',$x_t2_col5, $y,'UTF-8');
                    $page->drawText('Subtotal',$x_t2_col6, $y,'UTF-8');
                    $y -= ($salto_linea)*2;
                    $page->drawLine($x_t2_col1 - 10,$y + 20,$x_margen_end,$y + 20);
                    $style->setFont($font, $font_size07);
                    $page->setStyle($style);
                }
            endforeach;
            
            $y = $y_margen*3.5;
            $page->drawRectangle($x_t2_col1 - 10,$y - 10,$x_margen_end,$y_rec,'SHAPE_DRAW_STROKE');
            $page->drawLine($x_t2_col2 - 10,$y - 10,$x_t2_col2 - 10,$y_rec);
            $page->drawLine($x_t2_col4 - 10,$y - 10,$x_t2_col4 - 10,$y_rec);
            $page->drawLine($x_t2_col5 - 10,$y - 10,$x_t2_col5 - 10,$y_rec);
            $page->drawLine($x_t2_col6 - 10,$y - 10,$x_t2_col6 - 10,$y_rec);
            $y -= ($salto_linea)*3;

            // Observaciones
            $observaciones = $encargo->get('observaciones');
            if(!empty($observaciones)){

                if($y < $y_margen*3 + 50){
                    // Creamos una nueva página
                    $pdf->pages[] = ($page = $pdf->newPage('A4'));
                    $y = $y_margen_start;
                    // Cabecera
                    $style->setFont($fontb, $font_size12);
                    $page->setStyle($style);
                    $page->drawText($tipo_texto,$x_margen, $y - 35,'UTF-8');
                    if($tipo > 1){
                        $imageFile = './public/images/logo-colorsur.jpg';
                        $logo_width = 171;
                        $logo_height = 50;
                        $y += 10;
                        $stampImage = \ZendPdf\Image::imageWithPath($imageFile);
                        $page->drawImage($stampImage, $x_margen_end - $logo_width, $y - $logo_height , $x_margen_end, $y);
                        $y -= $logo_height;
                    }
                    $y -= ($salto_linea)*3;
                }
                $style->setFont($fontb, $font_size08);
                $page->setStyle($style);
                $page->drawText('Observaciones',$x_t2_col1, $y,'UTF-8');
                $y -= ($salto_linea);
                $lines = $parrafo->getLines($encargo->get('observaciones'),$font_size08, 14000);
                $style->setFont($font, $font_size07);
                $page->setStyle($style);
                foreach($lines as $line):
                    //$text_width = $pdfavanzado->getTextWidth($line, $font, $font_size08);
                    $page->drawText($line,$x_t2_col1,$y,'UTF-8');
                    $y -= ($salto_linea);
                endforeach;
                $y -= ($salto_linea);
            }
            // Resumen
            $x_t3_col0 = $x_col2 - 70;
            $x_t3_col1 = $x_col2 + 30;
            $x_t3_col2 = $x_t3_col1 + $x_margen*2.2;
            $x_t3_col3 = $x_t3_col1 + $x_margen*4.4;
            $y = $y_margen*2.5;
            $y_rec = $y + $salto_linea + 5;
            $style->setFont($fontb, $font_size08);
            $page->setStyle($style);
            $page->drawText('Base',$x_t3_col0, $y,'UTF-8');
            $page->drawText('IVA',$x_t3_col1, $y,'UTF-8');
            $page->drawText('Rec. Eq.',$x_t3_col2, $y,'UTF-8');
            $page->drawText('Total',$x_t3_col3, $y,'UTF-8');
            $y -= ($salto_linea)*1;
            $style->setFont($font, $font_size08);
            $page->setStyle($style);
            $page->drawText(number_format($encargo->get('base-con-descuento'),2,',','.').' €',$x_t3_col0, $y,'UTF-8');
            $page->drawText(number_format($encargo->get('iva'),2,',','.').' €',$x_t3_col1, $y,'UTF-8');
            $page->drawText(number_format($encargo->get('re'),2,',','.').' €',$x_t3_col2, $y,'UTF-8');
            $page->drawText(number_format($encargo->get('total'),2,',','.').' €',$x_t3_col3, $y,'UTF-8');
            $page->drawRectangle($x_t3_col0 - 10,$y - 10,$x_margen_end,$y_rec,'SHAPE_DRAW_STROKE');
            $page->drawLine($x_t3_col1 - 10,$y - 10,$x_t3_col1 - 10,$y_rec);
            $page->drawLine($x_t3_col2 - 10,$y - 10,$x_t3_col2 - 10,$y_rec);
            $page->drawLine($x_t3_col3 - 10,$y - 10,$x_t3_col3 - 10,$y_rec);

            // Pie
            $n = count($pdf->pages);
            $i = 1;
            foreach($pdf->pages as $p):
                $style->setFont($font, $font_size07);
                $p->setStyle($style);
                if($tipo > 1){
                    $p->drawText('colorsur.es // 954 31 39 79 // info@colorsur.es', $page->getWidth()/2 - $x_margen/2 - 110,$y_margen/2,'UTF-8');
                }
                $p->drawText($i.' de '.$n, $x_margen_end - 30,$y_margen/2,'UTF-8');
                $i++;
            endforeach;
        }else{
            $pdf = Utilidades::ticketPdf($encargo);
        }
        $nombres_posibles = ['ticket_','albaran_','factura_','factura-abono_'];
        $nombre_archivo = $nombres_posibles[$tipo].$encargo->get('codigo').'_'.$encargo->get('cliente-nombre-archivo');
        
        if((int)$guarda){
            $pdf->save('./public/facturas/_pdfs/'.$nombre_archivo.'.pdf');
        }
        
        return [$pdf,$nombre_archivo];
    }

    public static function certificadoPdf(Inscrito $inscrito,$guarda = 0){

        $inscripcion = $inscrito->get('inscripcion');
        $curso = $inscripcion->get('curso');
        $usuario = $inscrito->get('usuario');

        $pdf_template = \ZendPdf\PdfDocument::load('./public/templates/Plantilla_Diploma_Rellenable4.pdf');

        $pdf = \ZendPdf\PdfDocument::load();

        // Estilos
        $style = new \ZendPdf\Style();
        $style->setFillColor(new \ZendPdf\Color\Html('#000000'));
        $style->setLineColor(new \ZendPdf\Color\Html('#000000'));

        $font_bi = \ZendPdf\Font::fontWithPath('./public/assets/webfonts/Roboto/Roboto-BoldItalic.ttf');
        $font_b = \ZendPdf\Font::fontWithPath('./public/assets/webfonts/Roboto/Roboto-Bold.ttf');
        $font_m = \ZendPdf\Font::fontWithPath('./public/assets/webfonts/Roboto/Roboto-Medium.ttf');
        $font_r = \ZendPdf\Font::fontWithPath('./public/assets/webfonts/Roboto/Roboto-Regular.ttf');


        $salto_linea = 15;

        // Márgenes
        $x_margen = 67;
        $y_margen = 100;

        $pdf->pages[] = ($page = clone $pdf_template->pages[0]);
        $y_margen_start = $page->getHeight() - $y_margen;
        $y = $y_margen_start;

        $y_end_margin = $page->getWidth() - 90;

        $y -= ($salto_linea)*9.32;

        // Tabla 1
        $x_t1_col1 = $x_margen;

        $style->setFont($font_bi, 20);
        $page->setStyle($style);



        $page->drawText($usuario->get('nombre') . ' ' . $usuario->get('apellidos'), $x_margen * 1.96, $y, 'UTF-8');


        $style->setFont($font_b, 16);
        $page->setStyle($style);
        $y -= ($salto_linea) * 3.1;

        $texto_lineas = Parrafo::getLines(html_entity_decode($curso->get('nombre')),12,8200);
        foreach($texto_lineas as $tl):
            $page->drawText($tl, $x_margen, $y,'UTF-8');
            $y -= ($salto_linea) * 1.1;
        endforeach;


        $style->setFont($font_b, 12);
        $style->setLineColor(new \ZendPdf\Color\Html('#3b3d40'));
        $style->setFillColor(new \ZendPdf\Color\Html('#3b3d40'));
        $page->setStyle($style);

        $y -= ($salto_linea) * 1.6;

        /*
         * TODO Parrafo
         * */
        
        $texto_descripcion = $curso->get('informacion_certificados');

        $texto_lineas = Parrafo::getLines($texto_descripcion,12,8200);
        foreach($texto_lineas as $tl):
            $page->drawText($tl, $x_margen, $y,'UTF-8');
            $y -= ($salto_linea) * 1.1;
        endforeach;

        $y = 170;

        $fin = strtotime(Utilidades::giraFecha($curso->get('fin')));
        $page->drawText('En Sevilla a ' . (int)date('d', $fin) . ' de ' . Utilidades::getMes((int)date('m', $fin)) . ' de ' . date('Y', $fin), $x_margen, $y, 'UTF-8');

        if(!empty($curso->get('resumen'))){
            $pdf->pages[] = ($page = $pdf->newPage('A4-Landscape'));
            $style->setFont($font_b, 12);
            $anchoLinea = 8000;
            $style->setLineColor(new \ZendPdf\Color\Html('#3b3d40'));
            $style->setFillColor(new \ZendPdf\Color\Html('#3b3d40'));
            $page->setStyle($style);
            $y = $page->getHeight() - $y_margen / 2;
            $x_margen = $x_margen / 2;
            $resumenes = explode('<br />',nl2br($curso->get('resumen')));
            foreach($resumenes as $resumen):
                $texto_lineas = Parrafo::getLines($resumen,12,$anchoLinea);
                foreach($texto_lineas as $tl):
                    $page->drawText($tl, $x_margen, $y,'UTF-8');
                    
                    if($y < $y_margen / 2){
                        $y = $page->getHeight() - $y_margen / 2;
                        $x_margen = $x_margen * 15;
                        $anchoLinea = 6000;
                    }else{
                        $y -= ($salto_linea) * 1.2;
                    }
                    
                endforeach;
            endforeach;
        }

        $nombre_archivo = $usuario->get('id').'-'.$inscrito->get('id').'-'.time() . '.pdf';

        $path = null;
        if((int)$guarda){
            $path_explode = explode('/', $path = $inscrito::FILE_DIRECTORY_DIPLOMA);
            $path_create = null;
            foreach($path_explode as $pe):
                (isset($path_create))?($path_create .= '/' . $pe):($path_create = $pe);
                if(!file_exists($path_create)){
                    try {
                        mkdir($path_create);
                    } catch(ErrorException $e) {
                        echo "Error: " . $e->getMessage();
                    }
                }
            endforeach;

            $pdf->save($path . '/' . $nombre_archivo);
        }

        return [$pdf, $nombre_archivo, $path];
    }
    
}

