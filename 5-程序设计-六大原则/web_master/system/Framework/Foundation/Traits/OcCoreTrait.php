<?php

namespace Framework\Foundation\Traits;

use App\Models\Setting\Setting;
use Document;
use Framework\Config\Config;
use Framework\DB\DB;
use Framework\Event\Event;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Loader\Loader;
use Framework\Route\Url;
use Language;
use Log;
use Registry;

trait OcCoreTrait
{
    /**
     * @var Registry
     */
    public $ocRegistry;
    /**
     * @var Config
     */
    public $ocConfig;
    /**
     * @var Event
     */
    public $ocEvent;
    /**
     * @var Loader
     */
    public $ocLoad;

    public function loadOcCore($config, $db)
    {
        $this->registerOcRegistry();
        $this->registerOcDB($db);
        $this->registerOcConfig($config);
        $this->registerOcLog();

        $this->ocRegistry->set('request', new Request());
        $this->ocRegistry->set('response', new Response());
        $this->ocRegistry->set('event', $this->ocEvent = new Event($this->ocRegistry));
        $this->ocRegistry->set('load', $this->ocLoad = new Loader($this->ocRegistry));
        $this->ocRegistry->set('language', new Language($this->ocConfig->get('language_directory')));
        $this->ocRegistry->set('url', new Url($this->ocConfig->get('site_url'), $this->ocConfig->get('site_ssl')));
        $this->ocRegistry->set('document', new Document());
    }

    protected function registerOcRegistry()
    {
        $this->instance('ocRegistry', $this->ocRegistry = new Registry());
        $this->alias('ocRegistry', Registry::class);
        $this->alias('ocRegistry', 'registry');
    }

    protected function registerOcDB($orm)
    {
        $db = new DB('Orm', $orm, null, null, null, null);
        $this->ocRegistry->set('db', $db);
        $this->ocRegistry->set('orm', $orm);
    }

    protected function registerOcConfig($configName)
    {
        $config = new Config();
        // 载入 default 配置
        $config->load('default');
        // 载入 application 相关配置
        if ($configName) {
            $config->load($configName);
        }
        // 载入 db 中的配置
        $data = Setting::query()->where('store_id', 0)->get();
        foreach ($data as $item) {
            if (!$item->serialized) {
                $config->set($item->key, $item->value);
            } else {
                $config->set($item->key, json_decode($item->value, true));
            }
        }

        $this->ocRegistry->set('config', $this->ocConfig = $config);
        $this->instance('ocConfig', $this->ocConfig);
        $this->alias('ocConfig', Config::class);
    }

    protected function registerOcLog()
    {
        $error_log_dir = 'error/';
        if (!is_dir(DIR_LOGS . $error_log_dir)) mkdir(DIR_LOGS . $error_log_dir);
        $log = new Log($error_log_dir . date('Y-m-d') . '.log');
        $this->ocRegistry->set('log', $log);
    }
}
