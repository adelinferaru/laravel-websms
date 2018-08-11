<?php

namespace Adelinferaru\LaravelWebSms;


use nusoap_client;


class LaravelWebSms
{
    /**
     * @var nusoap_client
     */
    private $soap_client;
    private $username;
    private $password;
    private $session_path;
    private $ttl;

    function __construct($cfg)
    {
        $this->soap_client = new nusoap_client($cfg['wsdl_file'], "WSDL");
        $this->soap_client->soap_defencoding = 'UTF-8';
        $this->soap_client->decode_utf8 = FALSE;
        
        $this->session_path = $cfg['session_path'];
        $this->username = $cfg['username'];
        $this->password = $cfg['password'];
        $this->ttl = $cfg['session_ttl'];

    }

    function authenticate()
    {
        $obj = new \stdClass;
        $obj->username = $this->username;
        $obj->password = $this->password;

        $ret = $this->soap_client->call("Authenticate", array("parameters" => $obj));
        //var_dump($this->soap_client);
        if ($ret['success'] == 1) {
            $session = $ret["session_id"];
            $this->setFileSession($session);
            return $ret["session_id"];
        } else {
            die("Invalid Username and or password");
        }

    }

    function getSession()
    {
        $session=$this->getFileSession();


        if($session==null)
        {
            $session=$this->authenticate();
            $this->setFileSession($session);
        }

        return $session;
    }


    function getFileSession()
    {

        if(file_exists($this->session_path))
        {
            $tm=filemtime($this->session_path);

            if(time()-$tm<$this->ttl)
            {
                $session=file_get_contents($this->session_path);
            }else
            {
                $session=null;
            }
            return $session;
        }

        return null;
    }

    function setFileSession($session)
    {
        file_put_contents($this->session_path, $session);
    }

    function touchFile()
    {
        touch($this->session_path);
    }

    function getCredits()
    {
        $session = $this->getSession();
        $obj = new \stdClass;
        $obj->session_id = $session;
        //new soapval("session_id2",false,"asd")
        //array("parameters"=>);
        $res = $this->soap_client->call("getCredits",array("parameters"=>$session));
        //var_dump($this->soap_client);
        $this->touchFile();
        return $res;
    }

    function submitSM($from,$to,$message,$encoding="GSM")
    {
        $obj = new \stdClass;
        $obj->session_id = $this->getSession();
        $obj->from = $from;
        $obj->message = $message;
        //$obj->message="A[]{}";
        $obj->data_coding=$encoding;
        if(is_array($to))
            $obj->to = $to;
        else
            $obj->to = array($to);

        try
        {
            $ret = $this->soap_client->call("sendSM", array("parameters" => $obj));
            //var_dump($this->soap_client);
            return $ret;
        }
        catch (\SoapFault $soapFault) {

            echo '<pre>REQUEST: ' . var_export($this->soap_client->request) . '</pre>';
            echo '<pre>RESPONSE: ' . var_export($this->soap_client->response) . '</pre>';
            //echo "Request :<br>", $this->soap_client->__getLastRequest(), "<br>";
            //echo "Response :<br>", $this->soap_client->__getLastResponse(), "<br>";
        }
    }

    /**
     * @param $batchId
     * @return mixed
     */
    function getBatch($batchId)
    {
        $obj = new \stdClass;
        $obj->sessionId = $this->getSession();
        $obj->batchId = $batchId;


        try
        {
            $ret = $this->soap_client->call("getBatchStatus", array("parameters" => $obj ));
            return $ret;
        }
        catch (\SoapFault $soapFault) {

            echo '<pre>REQUEST: ' . var_export($this->soap_client->request) . '</pre>';
            echo '<pre>RESPONSE: ' . var_export($this->soap_client->response) . '</pre>';
            //echo "Request :<br>", $this->soap_client->__getLastRequest(), "<br>";
            //echo "Response :<br>", $this->soap_client->__getLastResponse(), "<br>";
        }
    }
}