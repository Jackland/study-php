<?php

//初始化框架
$app = new \System\Foundation\Application($_ENV['APP_BASE_PATH'] ?? dirname(__DIR__));

return $app;