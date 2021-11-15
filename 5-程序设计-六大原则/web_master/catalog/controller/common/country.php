<?php

use App\Catalog\Controllers\BaseController;
use App\Repositories\Common\CountryRepository;

class ControllerCommonCountry extends BaseController
{
    public function index()
    {
        $this->load->language('common/country');
        $data['logged'] = $this->customer->isLogged();

        $data['code'] = $this->session->get('country');

        $countryRepo = app(CountryRepository::class);
        $countries = $countryRepo->getShowCountriesIndexByCodeWithCustomer($this->customer);
        $currencies = $countryRepo->getCurrenciesByCountries($countries)->keyBy('currency_id');
        foreach ($countries as $country) {
            $symbol = '';
            if ($currencies->has($country->currency_id)) {
                $currency = $currencies[$country->currency_id];
                $symbol = $currency->symbol_left . $currency->symbol_right;
            }
            $data['countries'][] = [
                'name' => $country->iso_code_3 . ' ' . $symbol,
                'code' => $country->iso_code_3,
                'country' => strtoupper($country->iso_code_2)
            ];
        }

        return $this->load->view('common/country', $data);
    }

    public function country()
    {
        $country = $this->request->post('code');
        if ($country) {
            $this->session->set('country', $country);
        }

        return $this->redirect('common/home');
    }
}
