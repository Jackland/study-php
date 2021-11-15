<?php

/**
 * Class ModelCatalogTag
 */
class ModelCatalogTag extends Model {


    /**
     * 获取所有tag并组织特定数据
     * @return array
     * @throws Exception
     */
    public function get_all_tags()
    {
        $this->load->model('tool/image');
        $result =  [] ;
        $this->orm->table('oc_tag')
            ->where('status','=',1)
            ->orderBy('tag_id','asc')
            ->get()
            ->map(function ($item) use (&$result){
                $img_url = $this->model_tool_image->getOriginImageProductTags($item->icon);
                $item->image_icon_url = '<img data-toggle="tooltip" class="' . $item->class_style . '" title="' . $item->description . '" style="padding-left: 1px" src="' . $img_url . '">';
                $result[$item->tag_id] = obj2array($item) ;
            });
        return $result ;
    }

}
