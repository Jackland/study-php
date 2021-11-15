<?php
/**
 * Created by PHPSTORM
 * User: yaopengfei
 * Date: 2020/7/21
 * Time: 下午3:31
 */

use App\Catalog\Controllers\AuthController;
use App\Models\ServiceAgreement\AgreementVersion;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\ServiceAgreement\ServiceAgreementRepository;
use App\Services\ServiceAgreement\ServiceAgreementService;


/**
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelAccountCustomer $model_account_customer
 *
 * Class ControllerAccountServiceAgreement
 */
class ControllerAccountServiceAgreement extends AuthController
{
    private $defaultRedirectRoute = 'common/home';

    /**
     * @var AgreementVersion
     */
    private $version;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $version = app(ServiceAgreementRepository::class)->checkCustomerSignAgreement(AgreementVersion::AGREEMENT_ID_BY_CUSTOMER_LOGIN, customer()->getId());
        if (!$version) {
            return $this->redirectAfterAgree();
        }

        if (!$this->session->has('is_redirect_agreement')) {
            return $this->redirectAfterAgree();
        }

        $this->version = $version;
    }

    public function index()
    {
        $this->setLanguages('information/information');
        $this->load->model('catalog/information');

        $informationInfo = $this->model_catalog_information->getInformation($this->version->information_id);
        if (empty($informationInfo)) {
            return $this->redirectAfterAgree();
        }

        $this->setDocumentInfo($informationInfo['meta_title'], $informationInfo['meta_description'], $informationInfo['meta_keyword']);

        $data['heading_title'] = $informationInfo['title'];
        $data['description'] = html_entity_decode($informationInfo['description'], ENT_QUOTES, 'UTF-8');
        $data['agree_link'] = $this->url->link('account/service_agreement/agree');
        $data['footer'] = $this->renderController('common/footer', ['is_show_notice' => false, 'is_show_message' => false]);
        $data['header'] = $this->renderController('common/header', ['display_top' => false, 'display_search' => false, 'display_account_info' => false, 'display_menu' => false, 'display_common_ticket' => false]);

        return $this->response->setOutput($this->render('account/service_agreement', $data));
    }

    public function agree()
    {
        app(ServiceAgreementService::class)->agreeServiceAgreementVersion($this->version, customer()->getId());

        return $this->redirectAfterAgree();
    }


    private function redirectAfterAgree()
    {
        // 新用户同意协议后
        if (app(CustomerRepository::class)->isPhoneNeedVerify(customer())) {
            // 需要验证手机号
            return $this->redirect('account/phone/verify')->send();
        }

        if ($this->session->has('redirect')) {
            $redirect = $this->session->get('redirect');
            $this->session->remove('redirect');
            return $this->response->redirectTo($redirect)->send();
        }

        return $this->redirect($this->defaultRedirectRoute)->send();
    }
}
