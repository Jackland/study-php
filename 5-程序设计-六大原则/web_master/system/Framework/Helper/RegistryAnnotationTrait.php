<?php

namespace Framework\Helper;

use Cart\Cart;
use Cart\Country;
use Cart\Currency;
use Cart\Customer;
use Cart\Sequence;
use Communication;
use DB;
use Document;
use Event;
use Framework\Cache\Cache;
use Framework\Config\Config;
use Framework\Debug\DebugBar;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Route\Url;
use Framework\Session\Session;
use Illuminate\Database\Capsule\Manager;
use Language;
use Loader;
use Log;

/**
 * @property DB $db
 * @property Cache $cache
 * @property Log $log
 * @property Config $config
 * @property Loader $load
 * @property Language $language
 * @property Event $event
 * @property Document $document
 * @property Communication $communication
 * @property Customer $customer
 * @property Manager $orm
 * @property Cart $cart
 * @property Currency $currency
 * @property Country $country
 * @property Sequence $sequence
 * @property DebugBar $debugBar
 * @property Request $request
 * @property Response $response
 * @property Session $session
 * @property Url $url
 */
trait RegistryAnnotationTrait
{
}
