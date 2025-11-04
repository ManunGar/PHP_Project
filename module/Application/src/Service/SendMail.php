<?php
/**
 * Created by PhpStorm.
 * User: cperera
 * Date: 01/05/18
 * Time: 19:20
 */

namespace Application\Service;
use Zend\Mail;
use Zend\Mime\Part as MimePart;
use Zend\Mime\Message as MimeMessage;
use Zend\Session\Container;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Mail\Message;
use Zend\Mime\Mime;
/*
 * Send Mail Services.
 * Use service in controller with dependence inyection for serviceLocator
 * */

class SendMail
{
    /*
     * @var Array de emails de destinos
     * [0 => ['mail' => email1, 'name' => nombre1, 'to_type' => default/cc/bcc], ..., N]
     * */
    protected $_to;

    /*
     * @var Asunto del mensaje
     * */
    protected $_subject;

    /*
     * @var Mensaje
     * */
    protected $_message;

    /*
     * @var Html true or false
     * */

    /*
     * @var Array Adjunto
     * [
     *  0 => [
     *          'name' => 'test 1',
     *          'file_path' => public/test/documento_test.docx;
     *        ]
     * ]
     * */
    protected $_attach;

    /*
     * Config global from serviceManager
     * */
    protected $_config;

    /**
     * SendMail constructor.
     */
    public function __construct($config){
        $this->_to = [];
        $this->_attach = [];
        $this->_config = $config;
    }

    public function sendMail($mails, $subject, $message, $attach = false, $html = true){
        $this->_to = [];
        $this->_attach = [];
        if(!is_array($mails)){
            $mails_tmp['mail'] = $mails;
        }else{
            $mails_tmp = $mails;
        }
        foreach($mails_tmp as $mail):
            if(isset($mail['mail'])){
                if(!isset($mail['name'])){
                    $mail['name'] = $mail['mail'];
                }
                if(!isset($mail['to_type'])){
                    $mail['to_type'] ='default';
                }
                // $mail['mail'] = 'asabeino@hotmail.com';
                $this->_to[] = $mail;
            }
        endforeach;

        $attach_tmp = [];
        if($attach && !is_array($attach)){
            $attach_tmp['mail'] = $attach;
        }else if($attach){
            $attach_tmp = $attach;
        }

        foreach($attach_tmp as $att):
            if(isset($att['file_path'])){
                if(!isset($att['name'])){
                    $att['name'] = $att['file_path'];
                }
                $this->_attach[] = $att;
            }
        endforeach;
        $this->_subject = $subject;
        $this->_message = $message;

        if($html){
            $header = '<html><body>';
            $footer = '</body></html>';
            $this->_message = $header.$this->_message.$footer;
        }

        if(count($this->_to) > 0){
            try{

                if($html){
                    $contentPartContent           = new MimePart($this->_message);
                    $contentPartContent->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
                    $contentPartContent->type     = "text/html; charset=UTF-8";
                }else{
                    $contentPartContent           = new MimePart($this->_message);
                    $contentPartContent->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
                    $contentPartContent->type     = "text/plain; charset=UTF-8";
                }


                $body = new MimeMessage();

                if (count($this->_attach) > 0) {
                    $content = new MimeMessage();
                    $content->addPart($contentPartContent);

                    $contentPart = new MimePart($content->generateMessage());
                    $contentPart->type = "multipart/alternative;\n boundary=\"" .
                        $content->getMime()->boundary() . '"';

                    $body->addPart($contentPartContent);

                    // Add each attachment
                    foreach($this->_attach as $att):
                        if($att['file_path'] != null and file_exists($att['file_path'])) {
                            $attachment = new MimePart(fopen($att['file_path'], 'r'));
                            if(isset($att['type'])){
                                $attachment->type = $att['type'];
                            }else{
                                $attachment->type = $attachment->type;
                            }
                            $attachment->encoding = Mime::ENCODING_BASE64;
                            $attachment->disposition = Mime::DISPOSITION_ATTACHMENT;
                            $attachment->filename = $att['name'];
                            $body->addPart($attachment);
                        }
                    endforeach;
                } else {
                    $body->setParts([$contentPartContent]);
                }

                $mail = new Message();
                $mail->setSubject($this->_subject);
                $mail->setEncoding('utf-8');
                //$mail->setEncoding('iso-8859-1');
                $mail->setBody($body);
                $mail->setFrom($this->_config['from']['mail'], $this->_config['from']['name']);

                foreach($this->_to as $to):
                    switch ($to['to_type']) {
                        case 'cc':
                            $mail->addBcc($to['mail'], $to['name']);
                            break;
                        case 'bcc':
                            $mail->addBcc($to['mail'], $to['name']);
                            break;
                        default:
                            $mail->addTo($to['mail'], $to['name']);
                            break;
                    }

                endforeach;
                $transport = new SmtpTransport();
                $options   = new SmtpOptions($this->_config['smtp_config']);

                $transport->setOptions($options); //Establecemos la configuración
                $transport->send($mail);

                return ['status' => true];
            }catch (Exception $e){
                throw new \Exception($e->getMessage());
                return ['status' => false, 'error' => 1];
            }
        }else{
            return ['status' => false, 'error' => 2];
        }

    }
}