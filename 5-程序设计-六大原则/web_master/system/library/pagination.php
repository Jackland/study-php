<?php
/**
 * @package        OpenCart
 * @author        Daniel Kerr
 * @copyright    Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.com
 */

/**
 * Pagination class
 */
class Pagination
{
    public $total = 0;
    public $page = 1;
    public $limit = 20;
    public $num_links = 5;
    public $url = '';
    public $text_first = '<i class="giga icon-left2 page-direction"></i>';
    public $text_last = '<i class="giga icon-right2 page-direction"></i>';
    public $text_next = '<i class="giga icon-arrow-right-copy page-direction"></i>';
    public $text_prev = '<i class="giga icon-arrow-left page-direction"></i>';
    public $pageList = [5, 10, 15, 20, 50, 100];
    public $page_key = 'page';
    public $limit_key = 'page_limit';
    public $pagination_text = '%s-%s of %s';
    public $renderScript = true;
    public $uuid = null;
    public $render_count = 0;

    public function __construct()
    {
        $this->uuid = substr(md5(time()), 0, 6);
    }

    /**
     * @return  string
     */
    public function render()
    {
        $this->resolvePageInfo();
        $this->render_count++;
        $total = $this->total;
        $page = ($this->page < 1) ? 1 : $this->page;
        $limit = (int)$this->limit > 0 ? $this->limit : 20;
        $page = intval($page);
        $limit = intval($limit);
        $num_links = $this->num_links;
        $num_pages = ceil($total / $limit);
        $this->url = str_replace('%7Bpage%7D', '{page}', $this->url);
        // limit 信息加入
        $page_limit_key = $this->limit_key;
        // 排除掉已经存在的limit信息
        $this->url = preg_replace_callback_array([
            "/(&amp;{$page_limit_key}=[^&]*)/i" => function () {
                return '';
            },
            "/&({$page_limit_key}=[^&]*)/i" => function () {
                return '';
            },
        ], $this->url);
        // 在url的末尾加入limit信息
        $this->url = $this->url . "&{$this->limit_key}=" . $this->limit;

        $output = '<ul class="pagination">';

        if ($page > 1) {
            $output .= '<li><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '&page=1', $this->url) . '">' . $this->text_first . '</a></li>';

            if ($page - 1 === 1) {
                $output .= '<li><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '&page=1', $this->url) . '">' . $this->text_prev . '</a></li>';
            } else {
                $output .= '<li><a href="' . str_replace('{page}', $page - 1, $this->url) . '">' . $this->text_prev . '</a></li>';
            }
        }

        if ($num_pages > 1) {
            if ($num_pages <= $num_links) {
                $start = 1;
                $end = $num_pages;
            } else {
                $start = $page - floor($num_links / 2);
                $end = $page + floor($num_links / 2);

                if ($start < 1) {
                    $end += abs($start) + 1;
                    $start = 1;
                }

                if ($end > $num_pages) {
                    $start -= ($end - $num_pages);
                    $end = $num_pages;
                }
            }

            for ($i = $start; $i <= $end; $i++) {
                if ($page == $i) {
                    $output .= '<li class="active"><span>' . $i . '</span></li>';
                } else {
                    if ($i === 1) {
                        $output .= '<li><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '&page=1', $this->url) . '">' . $i . '</a></li>';
                    } else {
                        $output .= '<li><a href="' . str_replace('{page}', $i, $this->url) . '">' . $i . '</a></li>';
                    }
                }
            }
        }

        if ($page < $num_pages) {
            $output .= '<li><a href="' . str_replace('{page}', $page + 1, $this->url) . '">' . $this->text_next . '</a></li>';
            $output .= '<li><a href="' . str_replace('{page}', $num_pages, $this->url) . '">' . $this->text_last . '</a></li>';
        }
        $output .= '</ul>';
        return $num_pages > 1 ? $output . $this->renderPaginationText() : $this->renderPaginationText();
    }

    private function renderPaginationText()
    {
        $start = ($this->page - 1) * $this->limit + 1;
        $end = $this->page * $this->limit <= $this->total ? $this->page * $this->limit : $this->total;
        $str = sprintf($this->pagination_text, $start, $end, $this->total);
        return $this->results($str);
    }

    public function results($resultstring)
    {
        $this->resolvePageInfo();
        // resolve js
        $limit = $this->limit;
        $page_key = $this->page_key;
        $page_limit_key = $this->limit_key;
        $url = preg_replace_callback_array([
            "/(&amp;{$page_key}=[^&]*)/i" => function () use ($page_key) {
                return "&{$page_key}=1";
            },
            "/\?({$page_key}=[^&]*)/i" => function () use ($page_key) {
                return "?{$page_key}=1";
            },
            "/({$page_key}=[^&]*)/i" => function () use ($page_key) {
                return "{$page_key}=1";
            },
            "/(&amp;{$page_limit_key}=[^&]*)/i" => function () {
                return '';
            },
            "/&({$page_limit_key}=[^&]*)/i" => function () {
                return '';
            },
        ], $this->url);
        // resolve js end
        $id = $this->uuid . '_' . $this->render_count;
        $output = '
        <span class="page-list" id="' . $id . '">
          <b>Rows per page:&nbsp;</b>
          <span class="btn-group dropup see">
            <button class="btn btn-secondary dropdown-toggle" type="button" aria-expanded="true">
              <span class="page-size">' . $this->limit . '</span>
              <span class="caret"></span>
            </button>
            <ul class="dropdown-menu" role="menu">
            <li>
            ';
        $aList = '';
        foreach ($this->pageList as $value) {
            $tmpUrl = "{$url}&{$page_limit_key}={$value}";
            if ($value == $this->limit) {
                $aList .= '<a class="dropdown-item active" data-href="' . $tmpUrl . '" href="javascript:void(0);">' . $value . '</a>';
            } else {
                $aList .= '<a class="dropdown-item" data-href="' . $tmpUrl . '" href="javascript:void(0);">' . $value . '</a>';
            }
        }
        $output .= $aList;
        $output .= '
            </li>
            </ul>
          </span>
        </span>';
        $output .= '<span class="page-info">' . $resultstring . '</span>';

        $script = '';
        $this->renderScript && $script = <<<JS
$('#{$id} .dropdown-item').on('click',function() {
    let current_page_limit = '{$limit}';
    let page_limit = $(this).html();
    if (current_page_limit == page_limit) return;
    location.href = $(this).data('href');
})
JS;

        return $this->total > 0 ? $output . "<script>{$script}</script>" : '';
    }

    /**
     * 下面这个写法实属无奈之举
     * 如有任何疑问 联系wangjinxin
     * user：wangjinxin
     * date：2019/11/27 14:23
     */
    private function resolvePageInfo()
    {
        require_once __DIR__ . '/../../catalog/controller/event/view.php';
        ControllerEventView::$page_limit_key = $this->limit_key;
        ControllerEventView::$page_key = $this->page_key;
        ControllerEventView::$page_limit = $this->limit;

    }
}
