<?php
/**
 * Interfaz para Abstract Restful
 *
 */
namespace Api\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Http\Response;

class AbstractRestfulJsonController extends AbstractRestfulController{

    protected function methodNotAllowed(){
        $this->response->setStatusCode(405);
        throw new \Exception('Method Not Allowed');
    }

    // POST to collection
    public function create($data){
        return $this->methodNotAllowed();
    }
    // DELETE to resource
    public function delete($id){
        return $this->methodNotAllowed();
    }
    // DELETE to collection
    public function deleteList($data){
        return $this->methodNotAllowed();
    }
    // GET to resource
    public function get($id){
        return $this->methodNotAllowed();
    }
    // GET to collection
    public function getList(){
        return $this->methodNotAllowed();
    }
    // HEAD to either
    public function head($id = null){
        return $this->methodNotAllowed();
    }
    // OPTIONS to either
    public function options(){
        return $this->methodNotAllowed();
    }
    // PATCH to resource
    public function patch($id, $data){
        return $this->methodNotAllowed();
    }
    // PUT to collection
    public function replaceList($data){
        return $this->methodNotAllowed();
    }

    // Modify a resource collection without completely replacing it
    public function patchList($data){
        return $this->methodNotAllowed();
    }
    // PUT to resource
    public function update($id, $data){
        return $this->methodNotAllowed();
    }
}