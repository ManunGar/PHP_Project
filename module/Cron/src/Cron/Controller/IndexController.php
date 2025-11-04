<?php
/*
 * Ejecución de cron jobs
 * php /var/www/app/public/index.php cron1
 * /var/www/vhosts/coiiaoc.com/app.coiiaoc.com/public/index.php cron1
 */
namespace Cron\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Application\Model\Entity\Opciones;

class IndexController extends AbstractActionController{
    
    public function cron1Action(){
        echo 'Comenzando la tarea programada... ';
        $db_opc = new Opciones();
        $db_opc->guardaFechaPruebaCron();
        echo 'Tarea finalizada correctamente. ';
    }
    
}
