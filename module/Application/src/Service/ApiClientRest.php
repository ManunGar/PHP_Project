<?php
/**
 * Created by PhpStorm.
 * User: cperera
 * Date: 21/09/19
 * Time: 19:20
 */

namespace Application\Service;

use Zend\Http\Request;
use Zend\Http\Client;
use Zend\Stdlib\Parameters;
/*
 * Api Client Rest.
 * Use service in controller with dependence inyection
 * */

class ApiClientRest
{

    /*
     * @var Array: endpoints
     * */
    protected $_enpoints;

    /*
     * @var string: domain
     * */
    protected $_domain;

    /*
     * @var string: Basic base64encode(user:password)
     * */
    protected $_authorization;



    /**
     * ApiClientRest constructor.
     */
    public function __construct($config){
        $this->_domain = $config['domain'];
        $this->_enpoints = $config['endpoints'];
        $this->_authorization = $config['authorization'];
    }

    /*
     * @var:
     *  $id  int:identificator of entity
     *  $data Array: Data for content type from entity
     *  $type string: cursos, categorias, see global client_api_info index
     *  $crud string: create, read, update, remove
     *
     * */
    public function send($id = 0, $data = null, $entity = 'cursos', $crud = 'create'){
        $request = new Request();
        $request->getHeaders()->addHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'
        ]);
        $url = $this->_domain . '' . $this->_enpoints[$entity][$crud];//'https://iw.impulsoft.net/wp-json/wp/v2/courses/' . $id_cur;

        if($crud == 'create' and $id > 0){
            $url .= $id;
        }

        $request->setUri($url);

        switch ($crud) {
            case 'create':
                $request->setMethod('POST');
                break;
            case 'read':
                $request->setMethod('GET');
                break;
            case 'update':
                $request->setMethod('PUT');
                break;
            case 'delete':
                $request->setMethod('DELETE');
                break;
        }

        $request->getHeaders()->addHeaders([
            'Authorization' => $this->_authorization,
        ]);

        if(isset($data)){
            $request->setPost(new Parameters($data));
        }
        $client = new Client();
        //var_dump($request);
        try{
            $response = $client->dispatch($request);
            $responseData = json_decode($response->getBody(), true);
        }catch (Exception $e){
            $responseData = ['status' => 500];
            //var_dump($e);die();
        }

        return $responseData;
    }
}