<?php

namespace App\Catalog\Forms\Message;

use App\Helper\SummernoteHtmlEncodeHelper;
use App\Repositories\Message\MessageRepository;
use App\Services\Message\MessageService;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;
use Throwable;

class BuyerSaveMsgForm extends RequestForm
{
    public $ids;
    public $subject;
    public $content;

    private $customerId;

    public function __construct()
    {
        parent::__construct();
        $this->customerId = customer()->getId();
    }

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'ids' => 'required',
            'subject' => 'required|string',
            'content' => ['required', function($attribute, $value, $fail) {
                $this->content = SummernoteHtmlEncodeHelper::decode($value, true);
                if (empty(str_replace(['&nbsp;', ' ', '　'], '', strip_tags($this->content, '<img>')))) {
                    $fail('Content is required field, can not be blank.');
                }
            }],
        ];
    }

    /**
     * @return string[]
     */
    protected function getRuleMessages(): array
    {
        return [
            'ids.required' => 'Recipient is(are) required field, can not be blank.',
            'subject.required' => 'Subject is required field, can not be blank.',
            'content.required' => 'Content is required field, can not be blank.',
        ];
    }

    /**
     * 验证
     * @throws Exception
     */
    public function verify()
    {
        $this->baseValidate();

        $sellerIds = $this->sellerIds();

        [$languageType, $receiverIds] = app(MessageRepository::class)->getMsgLanguageAndReceiverIds($sellerIds, $this->subject, $this->content);
        // 提示后无法点击下一步发送 code:420
        if (empty($receiverIds)) {
            throw new Exception("The language of your message does not match the languages acceptable set by the recipient. Please change the language and try again. ", 420);
        }
        // 提示后可继续点击下一步发送 code:430
        if (count($receiverIds) != count($sellerIds)) {
            throw new Exception("Part of the recipients are unable to communicate in $languageType. Are you sure to continue to send this message to them?", 430);
        }
    }

    /**
     * 保存逻辑
     * @throws Exception
     * @throws Throwable
     */
    public function save()
    {
        $this->baseValidate();

        $sellerIds = $this->sellerIds();

        [, $receiverIds] = app(MessageRepository::class)->getMsgLanguageAndReceiverIds($sellerIds, $this->subject, $this->content);
        if (empty($receiverIds)) {
            throw new Exception('please select recipient', 400);
        }

        // 保存的逻辑
        $files = request()->filesBag->get('files', []);
        app(MessageService::class)->buildMsg($this->customerId, $this->subject, $this->content, $files, $receiverIds);
    }

    /**
     * @throws Exception
     */
    private function baseValidate()
    {
        // 发送超过当天站内信次数，410需要前端提示
        if (!app(MessageRepository::class)->checkCustomerNewMsg($this->customerId, 0)) {
            throw new Exception('The number of messages sent has reached its maximum limit for the day. ', 410);
        }

        if (!$this->isValidated()) {
            throw new Exception($this->getFirstError(), 400);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function sellerIds(): array
    {
        $sellerIds = $this->ids;

        if (empty($sellerIds)) {
            throw new Exception('please select recipient.', 400);
        }

        if (!app(MessageRepository::class)->isSameCountryFromSenderToReceivers($this->customerId, $sellerIds)) {
            throw new Exception('You are not able to establish contact or message communication with this Seller since you are not in the same Country Market as the Seller.', 400);
        }

        return $sellerIds;
    }
}
