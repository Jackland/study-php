<?php

class ModelExtensionModuleCacheDeal extends Model
{
    const CACHE_CONTROLLER = [
        0=>'common/footer',
        1=>'extension/module/carousel',
        2=>'extension/module/slideshow',
    ];
    const TIMEOUT = 3600;
    const CACHE_NUMBER = [
        'carousel'=>1,
        'common/footer'=>0,
        'slideshow'=>2,
    ];
    protected $session_id;
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->session_id = $this->session->getId();
    }

    public function getCache($key)
    {
        $real_key = $this->session_id .'_'.self::CACHE_CONTROLLER[$key];
        $cache = $this->cache->get($real_key);
        //9abd4d2ff9f314034d5b965021_extension/module/slideshow
        if(isset($cache['expired_time']) && $cache['expired_time'] <= time()){
            $info['value'] = $cache['value'];
            $info['expired_time'] = time() + self::TIMEOUT;
            $this->setCache($key,$info);
            return $cache['value'];
        }else{
            $this->deleteCache($key);
            return false;
        }


    }

    public function setCache($key,$value,$expired =self::TIMEOUT)
    {
        $real_key = $this->session_id .'_'.self::CACHE_CONTROLLER[$key];
        $info['value'] = $value;
        $info['expired_time'] = time() + $expired;
        $this->cache->set($real_key,$info);
    }

    public function deleteCache($key)
    {
        $real_key = $this->session_id .'_'.self::CACHE_CONTROLLER[$key];
        $this->cache->delete($real_key);
        return true;
    }

    public function getCacheNumberByPart($string)
    {
        if(isset(self::CACHE_NUMBER[$string])){
            return self::CACHE_NUMBER[$string];
        }
        return false;
    }

}