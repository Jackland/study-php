<?php

namespace App\Console\Commands;

use App\Models\Rebate\Agreement;
use App\Models\Rebate\AgreementTemplate;
use App\Models\Rebate\OldAgreement;
use App\Models\Rebate\OldTemplate;
use App\Models\Rebate\Template;
use Illuminate\Console\Command;

/**
 * Class MigrateRebate
 * @package App\Console\Commands
 */
class TransferRebate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfer:rebate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->template();
        $this->agreement();
        $this->message();
    }

    /**
     * 迁移模板
     */
    private function template()
    {
        $Template = new Template();
        $OldTemplate = new OldTemplate();

        $old_templates = $OldTemplate->list();
        foreach ($old_templates as $old_template) {
            $is_deleted =  $old_template->status == 1 ? 0 : 1;
            !$is_deleted && (!$old_template->buyer_flag || !$old_template->p_status || $old_template->p_is_deleted) && $is_deleted = 1;
            $templateKeyVal = [
                'id' => $old_template->id,
                'rebate_template_id' => $old_template->template_id,
                'seller_id' => $old_template->customer_id,
                'day' => $old_template->day,
                'qty' => $old_template->qty,
                'rebate_type' => $old_template->discount_type,
                'rebate_value' => $old_template->discount_type ? $old_template->discount_amount : ($old_template->discount * 100),
                'search_product' => $old_template->sku,
                'items' => $old_template->sku . ':' . $old_template->mpn,
                'item_num' => 1,
                'item_price' => $old_template->price,
                'item_rebates' => $old_template->discount_type ? $old_template->discount_amount : round($old_template->discount * 100, 2),
                'is_deleted' => $is_deleted,
                'memo' => $old_template->memo,
                'create_user_name' => $old_template->create_username,
                'create_time' => $old_template->create_time,
                'update_user_name' => $old_template->update_username,
                'update_time' => $old_template->update_time,
                'program_code' => $old_template->program_code,
            ];

            $rebate_amount = $old_template->discount_type == 1 ?
                $old_template->discount_amount :
                round($old_template->price * $old_template->discount, 2);

            $itemKeyVal = [
                'template_id' => $old_template->id,
                'product_id' => $old_template->product_id,
                'price' => $old_template->price,
                'rebate_amount' => $rebate_amount,
                'min_sell_price' => $old_template->price_limit,
                'is_deleted' => $is_deleted,
                'memo' => $old_template->memo,
                'create_user_name' => $old_template->create_username,
                'create_time' => $old_template->create_time,
                'update_user_name' => $old_template->update_username,
                'update_time' => $old_template->update_time,
                'program_code' => $old_template->program_code,
            ];

            $Template->insertSingle($templateKeyVal);
            $Template->insertItem($itemKeyVal);
            echo "Transfer Template -- Product: {$old_template->product_id} " . PHP_EOL;
        }
    }

    /**
     * 1. 遍历 原有的协议表
     * 2. 根据协议中的 product_id 去匹配 模板
     * 3. 如果 2 中匹配到模板，则固化下来填充到 oc_rebate_agreement_template; 反之，跳过。
     * 4. 迁移到新的协议表
     */
    private function agreement()
    {
        $OldAgreement = new OldAgreement();
        $Agreement = new Agreement();
        $AgreementTemplate = new AgreementTemplate();
        $OldTemplate = new OldTemplate();
        $Template = new Template();

        $old_agreements = $OldAgreement->list();

        foreach ($old_agreements as $old_agreement) {
            echo "Transfer Agreement ：ID - {$old_agreement->id}, Code - {$old_agreement->contract_id} ";
            // 2.
            $old_template = $OldTemplate->getTemplateByProductID($old_agreement->product_id);
            $agreement_template_id = 0;
            $agreement_template_item_id = 0;

            // 3. 固化模板
            if (!empty($old_template)) {
                echo " - 固化模板 ";
                $template = $Template->getTemplate($old_template->id);
                $template_item = $Template->getItem($old_template->id);
                $templateKeyVal = [
                    'rebate_template_id' => $template->rebate_template_id,
                    'seller_id' => $template->seller_id,
                    'day' => $template->day,
                    'qty' => $template->qty,
                    'rebate_type' => $template->rebate_type,
                    'rebate_value' => $template->rebate_value,
                    'items' => $template->items,
                    'item_num' => $template->item_num,
                    'item_price' => $template->item_price,
                    'item_rebates' => $template->item_rebates,
                    'memo' => $template->memo,
                    'create_user_name' => $template->create_user_name,
                    'create_time' => $template->create_time,
                    'update_user_name' => $template->update_user_name,
                    'update_time' => $template->update_time,
                    'program_code' => $template->program_code,
                ];
                $agreement_template_id = $AgreementTemplate->insertSingle($templateKeyVal);
                $itemKeyVal = [
                    'agreement_rebate_template_id' => $agreement_template_id,
                    'product_id' => $template_item->product_id,
                    'price' => $template_item->price,
                    'rebate_amount' => $template_item->rebate_amount,
                    'min_sell_price' => $template_item->min_sell_price,
                    'memo' => $template_item->memo,
                    'create_user_name' => $template_item->create_user_name,
                    'create_time' => $template_item->create_time,
                    'update_user_name' => $template_item->update_user_name,
                    'update_time' => $template_item->update_time,
                    'program_code' => $template_item->program_code,
                ];
                $agreement_template_item_id = $AgreementTemplate->insertItem($itemKeyVal);
            } else {
                echo " - 未匹配到唯一模板";
            }

            $agreementKeyVal = [
                'id' => $old_agreement->id,
                'agreement_code' => $old_agreement->contract_id,
                'agreement_template_id' => $agreement_template_id,
                'buyer_id' => $old_agreement->buyer_id,
                'seller_id' => $old_agreement->seller_id,
                'day' => $old_agreement->day,
                'qty' => $old_agreement->qty,
                'effect_time' => $old_agreement->effect_time,
                'expire_time' => $old_agreement->expire_time,
                'clauses_id' => $old_agreement->clauses_id,
                'status' => $old_agreement->status,
                'remark' => '',
                'memo' => $old_agreement->memo,
                'create_user_name' => $old_agreement->create_username,
                'create_time' => $old_agreement->create_time,
                'update_user_name' => $old_agreement->update_username,
                'update_time' => $old_agreement->update_time,
                'program_code' => $old_agreement->program_code,
                'rebate_result' => 0
            ];
            $agreementItemKeyVal = [
                'agreement_id' => $old_agreement->id,
                'agreement_template_item_id' => $agreement_template_item_id,
                'product_id' => $old_agreement->product_id,
                'template_price' => $old_agreement->price,
                'rebate_amount' => $old_agreement->rebates_amount,
                'min_sell_price' => $old_agreement->limit_price,
                'is_delete' => 0,
                'memo' => $old_agreement->memo,
                'create_user_name' => $old_agreement->create_username,
                'create_time' => $old_agreement->create_time,
                'update_user_name' => $old_agreement->update_username,
                'update_time' => $old_agreement->update_time,
                'program_code' => $old_agreement->program_code,
            ];

            $Agreement->insertSingle($agreementKeyVal);
            $Agreement->insertItem($agreementItemKeyVal);
            echo " - 迁移协议内容" . PHP_EOL;
        }
    }

    /**
     * 迁移历史信息
     */
    private function message()
    {
        echo "Transfer Template : " . PHP_EOL;
        $Message = new \App\Models\Rebate\Message();
        $Message->oldToNew();
    }
}
