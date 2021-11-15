<?php

use App\Models\Setting\Setting;
use App\Repositories\Common\CountryRepository;
use Symfony\Component\HttpFoundation\Cookie;

class ControllerStartupStartup extends Controller
{
    public function index()
    {
        // 目前系统仅支持 store_id 为 0，不能修改
        $this->config->set('config_store_id', 0);

        $this->config->set('config_url', HTTP_SERVER);
        $this->config->set('config_ssl', HTTPS_SERVER);

        // Theme
        $this->config->set('template_cache', $this->config->get('developer_theme'));

        // Customer
        $this->initCustomer();

        // Language
        $this->initLanguage();

        // Country Currency
        $this->initCountryAndCurrency();

        // Request 请求记录
        if ($this->customer->isLogged() && !app()->isConsole()) {
            $this->request->save($this->customer, $this->registry);
        }

        // 把 request 中的非太平洋时区的时间 转为太平洋时区的时间
        $this->changeRequestDataWithCountry();

        // Tax
        $this->initTax();

        // Weight
        $this->registry->setDelay('weight', Cart\Weight::class, ['registry' => $this->registry]);

        // Length
        $this->registry->setDelay('length', Cart\Length::class, ['registry' => $this->registry]);
        //运费
        $this->registry->setDelay('freight', Yzc\Freight::class, ['registry' => $this->registry]);

        // Cart
        $this->registry->setDelay('cart', Cart\Cart::class, ['registry' => $this->registry]);

        //updated for seller buyer communication
        // 应该可以废弃了
        $this->registry->set('communication', new Communication($this->registry));

        // Encryption
        $this->registry->set('encryption', new Encryption());

        // Sequence
        $this->registry->set('sequence', new Cart\Sequence($this->registry));

        //commonFunction
        $this->registry->set('commonFunction', new yzc\CommonFunction($this->registry));

        // 注入 ajax 请求头，确保通过 request()->isAjax() 能正确判断 ajax 请求
        view()->script("if (typeof axios !== 'undefined') { axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'; }");
    }

    private function initCustomer(): void
    {
        // Customer Group
        if (session('customer') && isset(session('customer')['customer_group_id'])) {
            // For API calls
            $this->config->set('config_customer_group_id', session('customer')['customer_group_id']);
        } elseif ($this->customer->isLogged()) {
            // Logged in customers
            $this->config->set('config_customer_group_id', $this->customer->getGroupId());
        } elseif (session('guest') && isset(session('guest')['customer_group_id'])) {
            $this->config->set('config_customer_group_id', session('guest')['customer_group_id']);
        }
    }

    private function initLanguage(): void
    {
        // 当前系统语言默认为 en-gb，不可更改
        $code = 'en-gb';

        // Overwrite the default language object
        $language = new Language($code);
        $language->load($code);

        $this->registry->set('language', $language);

        // 当前系统固定为1，不可更改
        $this->config->set('config_language_id', 1);
    }

    private function initCountryAndCurrency(): void
    {
        // Country
        $countries = app(CountryRepository::class)->getShowCountriesIndexByCodeWithCustomer($this->customer)->keyBy('iso_code_3');
        // 优先 session
        $countryCode = $sessionCode = session('country');
        if (!$countryCode || !$countries->has($countryCode)) {
            // 其次 cookie
            $countryCode = $cookieCode = request()->cookieBag->get('country');
            if (!$countries->has($countryCode)) {
                // 默认取第一个
                $countryCode = $countries->keys()->first();
            }

            if ($sessionCode !== $countryCode) {
                session()->set('country', $countryCode);
            }
            if ($cookieCode !== $countryCode) {
                response()->headers->setCookie(Cookie::create('country', $countryCode, time() + 60 * 60 * 24 * 30));
            }
        }
        $this->registry->set('country', new Cart\Country($this->registry));
        $currentCountry = $countries[$countryCode];

        // Currency
        $currencies = app(CountryRepository::class)->getCurrenciesByCountries($countries)->keyBy('currency_id');
        if ($currencies->has($currentCountry->currency_id)) {
            $currencyCode = $currencies[$currentCountry->currency_id]->code;
        } else {
            $currencyCode = $this->config->get('config_currency');
        }
        if (session('currency') != $currencyCode) {
            session()->set('currency', $currencyCode);
        }
        if (request()->cookieBag->get('currency') != $currencyCode) {
            response()->headers->setCookie(Cookie::create('currency', $currencyCode, time() + 60 * 60 * 24 * 30));
        }
        $this->registry->setDelay('currency', Cart\Currency::class, ['registry' => $this->registry]);
    }

    private function initTax(): void
    {
        $this->registry->setDelay('tax', Cart\Tax::class, ['registry' => $this->registry], function (Cart\Tax $tax) {
            if ($shippingAddress = session('shipping_address')) {
                $tax->setShippingAddress($shippingAddress['country_id'], $shippingAddress['zone_id']);
            } elseif ($this->config->get('config_tax_default') == 'shipping') {
                $tax->setShippingAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
            }

            if ($paymentAddress = session('payment_address')) {
                $tax->setPaymentAddress($paymentAddress['country_id'], $paymentAddress['zone_id']);
            } elseif ($this->config->get('config_tax_default') == 'payment') {
                $tax->setPaymentAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
            }

            $tax->setStoreAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
        });
    }

    private function changeRequestDataWithCountry(): void
    {
        $ignore_query_keys = [
            'route',
        ];
        $countryCode = session('country');
        if ($countryCode && in_array($countryCode, CHANGE_TIME_COUNTRIES)) {
            // 旧的 request->get['xxx'] 形式的数据
            $need_exchange_inputs = ['get', 'post', 'request'];
            foreach ($need_exchange_inputs as $_input) {
                foreach ($this->request->{$_input} as $query_key => &$_request) {
                    if (!in_array($query_key, $ignore_query_keys)) {
                        request_data_change($_request, $countryCode);
                    }
                }
            }
            // 新的 request->query->all() 形式的数据
            $need_exchange_inputs = ['query', 'input', 'attributes'];
            foreach ($need_exchange_inputs as $_input) {
                foreach ($this->request->{$_input}->all() as $query_key => &$_request) {
                    if (!in_array($query_key, $ignore_query_keys)) {
                        request_data_change($_request, $countryCode);
                        $this->request->{$_input}->set($query_key, $_request);
                    }
                }
            }
        }
    }
}
