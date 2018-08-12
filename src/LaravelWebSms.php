<?php

namespace Adelinferaru\LaravelWebSms;


use nusoap_client;


class LaravelWebSms
{
    /**
     * @var nusoap_client
     */
    private $soap_client;
    /**
     * @var string
     */
    private $username;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $session_path;
    /**
     * @var integer
     */
    private $ttl;

    /**
     * LaravelWebSms constructor.
     * @param $config
     */
    function __construct($config)
    {
        $this->soap_client = new nusoap_client( $config['wsdl_file'], "WSDL");
        $this->soap_client->soap_defencoding = 'UTF-8';
        $this->soap_client->decode_utf8 = FALSE;
        
        $this->session_path = $config['session_path'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->ttl = $config['session_ttl'];

    }

    /**
     * Authenticate to the SMS service provider
     * @return mixed
     */
    function authenticate()
    {
        $obj = new \stdClass;
        $obj->username = $this->username;
        $obj->password = $this->password;
        $ret = $this->soap_client->call("Authenticate", array("parameters" => $obj));
        if ($ret['success'] == 1) {
            $session = $ret["session_id"];
            $this->setFileSession($session);
            return $ret["session_id"];
        } else {
            die("Invalid Username and or password");
        }

    }

    /**
     * @return bool|mixed|null|string
     */
    function getSession()
    {
        $session = $this->getFileSession();
        if($session == null)
        {
            $session = $this->authenticate();
            $this->setFileSession($session);
        }

        return $session;
    }


    /**
     * Get info from file session
     * @return bool|null|string
     */
    function getFileSession()
    {
        if(file_exists($this->session_path))
        {
            $tm = filemtime($this->session_path);

            if(time()-$tm < $this->ttl)
            {
                $session = file_get_contents($this->session_path);
            }else
            {
                $session = null;
            }
            return $session;
        }

        return null;
    }

    /**
     * @param $session
     */
    function setFileSession($session)
    {
        file_put_contents($this->session_path, $session);
    }

    /**
     * Modify the session file timestamp
     */
    function touchFile()
    {
        touch($this->session_path);
    }

    /**
     * @return mixed
     */
    function getCreditsLeft()
    {
        $session = $this->getSession();
        $obj = new \stdClass;
        $obj->session_id = $session;
        $res = $this->soap_client->call("getCredits", array("parameters"=>$session));
        $this->touchFile();
        return $res;
    }

    /**
     * @param $from
     * @param $to
     * @param $message
     * @param string $encoding
     * @return mixed
     */
    function sendSMS($from, $to, $message, $encoding = "GSM")
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
            return $ret;
        }
        catch (\SoapFault $soapFault) {

            echo '<pre>REQUEST: ' . var_export($this->soap_client->request) . '</pre>';
            echo '<pre>RESPONSE: ' . var_export($this->soap_client->response) . '</pre>';
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
        }
    }
}