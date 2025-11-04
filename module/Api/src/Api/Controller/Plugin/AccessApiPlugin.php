<?php
namespace Api\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Http\Request;
class AccessApiPlugin extends AbstractPlugin
{

    public function authorizationHeader(Request $request, $configApi)
    {
        $dev = false;
        $auth = $request->getHeaders('Authorization');
        if($auth){
            $api_key = str_replace('Basic ', '', $auth->getFieldValue());
            $api_key = base64_decode($api_key);
            $api_key_explode = explode(':', $api_key);
            if($api_key_explode[0] == $configApi['api_word_1'] && $api_key_explode[1] == $configApi['api_word_2']){
                $dev = true;
            }

        }
        return $dev;
    }
}
