<?php

class ControllerApiBase extends Controller{


    protected $base_structure = array(
        'api_token' => '',
        'requestDatas' => array(),
    );
    protected $request_data_structure = array();
    protected $result_code = 0;
    protected $result_message = 'success';
    // protected $redis;
    protected $safe_data = array();
    public function __construct(Registry $registry) {
        parent::__construct($registry);
        $this->load->language('api/base');

    }
    public function index(){

    }

    public function getStructure(){
        $structure = $this->base_structure;
        $structure['requestDatas'] = $this->request_data_structure;
        return $structure;
    }

    public function setRequestDataStructure(array $structure){
        $this->request_data_structure = $structure ?: array();
    }

    public function getResultCode(){
        return $this->result_code;
    }

    public function setResultCode($result_code){
        $this->result_code = $result_code;
        return $this;
    }

    public function getResultMessage(){
        return $this->result_message;
    }

    public function setResultMessage($result_message){
        $this->result_message = $result_message;
        return $this;
    }
    public function getParsedJson($is_default = 1){

        $input = $_REQUEST;
        if(!$is_default){
           $input = json_decode(file_get_contents("php://input"),true);
        }
        $structure = $this->getStructure();

        $safe_data = array();

        try {
            foreach ($structure as $k => $v)
            {
                if (is_array($structure[$k]))
                {
                    foreach ($structure[$k] as $kk => $kv)
                    {
                        if (!isset($input[$kk]))
                        {
                            $e = self::getErrorTips('common', 'missingfield');
                            throw new \Exception($kk . $e['text'], $e['code']);
                        }
                        $safe_data[$k][$kk] = addslashes(htmlspecialchars(trim($input[$kk])));
                    }
                }
                else
                {
                    if (!isset($input[$k]))
                    {
                        $e = self::getErrorTips('common', 'missingfield');
                        throw new \Exception($k . $e['text'], $e['code']);
                    }
                    $safe_data[$k] = addslashes(htmlspecialchars(trim($input[$k])));
                }
            }

            //验证key
            $keyList = $this->getAvailAbleKey();
            if(in_array($input['api_token'], $keyList) != true){
                $e = self::getErrorTips('common', 'apipassworderror');
                throw new \Exception($e['text'], $e['code']);
            }
        }
        catch (\Exception $e)
        {
            return $this->apiError('', $e->getCode(), $e->getMessage());
        }
        if(isset($safe_data['requestDatas'])){
            $this->safe_data = $safe_data['requestDatas'];
        }else{
            $safe_data['requestDatas'] = null;
        }
        return $safe_data['requestDatas'];
    }


    public function getAvailAbleKey(){
        $keyList = $this->language->get('text_base_api_token');
        return $keyList;

    }


    /**
     * [getErrorTips description] 报错模板待会写
     * @param  string $pre [description]
     * @param  string $key [description]
     * @return [type]      [description]
     */
    public  function getErrorTips($pre = 'common', $k = 'unknown'){
        $key = 'text_'.$pre.'_'.$k;
        $res = $this->language->get($key);
        return $res;
    }

    public function apiError($key = '', $code = '', $msg = '', $reason = array()){

        if (!$key && !$code && !$msg && !$reason)
            $key = 'common.unknown';
        if ($code && $msg)
            $this->setResultCode($code)->setResultMessage($msg);

        if ($key)
        {
            $kes = explode('.', $key);
            if (!isset($kes[1]))
            {

                $kes[0] = 'common';
                $kes[1] = 'unknown';
            }
            $e = self::getErrorTips($kes[0], $kes[1]);


            $this->setResultCode($e['code'])->setResultMessage($e['text']);
        }


        return $this->apiResponse($reason);
    }
    public function apiResponse($data){
        $ret = array(
            'result_code' => $this->getResultCode(),
            'result_message' => $this->getResultMessage(),
        );
        if (is_array($data) && count($data) !== count($data, COUNT_RECURSIVE)) {
            $ret['result_data'] = $data;
        }
        else
        {
            $ret['result_data'] = empty($data) ? [] : $data;
        }
        return $ret;

    }
}
