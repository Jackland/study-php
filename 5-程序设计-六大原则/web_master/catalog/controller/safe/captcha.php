<?php

use App\Catalog\Controllers\BaseController;
use App\Components\PageViewSafe\CaptchaTransfer\TransferRequest;
use App\Components\PageViewSafe\CaptchaTransfer\TransferResponse;
use App\Components\PageViewSafe\Support;
use App\Logging\LogChannel;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;

class ControllerSafeCaptcha extends BaseController
{
    const VERIFY_DATA_KEY = ControllerStartupSafeChecker::VERIFY_DATA_KEY;

    const SESSION_KEY_DATA = '__safe_captcha_data';
    const SESSION_KEY_CAPTCHA = '__safe_captcha_phrase';

    private $logger;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->logger = logger(LogChannel::SAFE_CAPTCHA);
    }

    public function index()
    {
        $request = request();

        $this->logger->info('enter: method: {method}, url: {url}', [
            'method' => $request->getMethod(),
            'url' => url()->current(),
        ]);

        if ($request->isMethod('POST')) {
            /** @var TransferRequest $transferRequest */
            $transferRequest = TransferRequest::loadFromData(session(self::SESSION_KEY_DATA));
            if (!$transferRequest) {
                $this->logger->warning('POST 提交无 session 数据');
                return 'post err x1';
            }
            // 校验验证码
            $answerPhrase = $request->post('answer');
            list($isOk, $correctPhrase, $start) = $this->checkCaptcha($answerPhrase);
            if ($isOk) {
                $this->logger->info('校验正确，耗时：{time}', ['time' => time() - $start]);
                // 正确时跳回
                return $this->redirect(Support::buildUrl($transferRequest->backUrl, [
                    self::VERIFY_DATA_KEY => Support::getEncryption()->encrypt(new TransferResponse($transferRequest->getData())),
                ]));
            }

            $this->logger->warning('校验错误，耗时：{time}，正确：{correct}，答案：{answer}', [
                'time' => time() - $start,
                'correct' => $correctPhrase,
                'answer' => $answerPhrase,
            ]);

            session()->flash->set('error', 'Unable to verify, please try later!');
            return $this->redirect(url()->current());
        }

        if (!$data = $request->get(self::VERIFY_DATA_KEY)) {
            $this->logger->warning('必须参数不存在');
            return 'no data';
        }

        /** @var TransferRequest $transferRequest */
        $transferRequest = Support::getEncryption()->decrypt($data, TransferRequest::class);
        if (!$transferRequest) {
            $this->logger->warning('data 解析错误');
            return 'data err x1';
        }
        $valid = $transferRequest->checkDataVerified($this->logger);
        if ($valid === 'redirect') {
            return $this->redirect($transferRequest->backUrl);
        }
        if (!$valid) {
            $this->logger->warning('data 验证错误');
            return 'data err x2';
        }

        session()->set(self::SESSION_KEY_DATA, $transferRequest->getData());
        $this->logger->info('data: ' . json_encode($transferRequest->getData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $error = session()->flash->get('error');
        return $this->render('safe/captcha_index', [
            'error' => $error,
        ]);
    }

    public function test()
    {
        $request = request();
        if ($request->get('ttt') !== 'yzc') {
            return '';
        }

        if (request()->isMethod('POST')) {
            // 校验验证码
            list($isOk, ,) = $this->checkCaptcha($request->post('answer'));
            if ($isOk) {
                return 'success';
            }
            session()->flash->set('error', 'Unable to verify, please try later!');
            return $this->redirect(url()->current());
        }

        $error = session()->flash->get('error');
        return $this->render('safe/captcha_index', [
            'error' => $error,
        ]);
    }

    public function captcha()
    {
        $phraseBuilder = new PhraseBuilder(4);
        $captcha = new CaptchaBuilder(null, $phraseBuilder);
        $captcha->build(request('w', 150), request('h', 50));
        session()->set(self::SESSION_KEY_CAPTCHA, [$captcha->getPhrase(), time()]);

        $captcha->output();
        $this->response->headers->set('Content-type', 'image/jpeg');
        return $this->response;
    }

    protected function checkCaptcha($answer)
    {
        // 校验验证码
        list($correctPhrase, $start) = session(self::SESSION_KEY_CAPTCHA);
        $builder = new CaptchaBuilder($correctPhrase);
        return [$builder->testPhrase($answer), $correctPhrase, $start];
    }
}
