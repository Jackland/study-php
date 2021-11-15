<?php
/**
 * @package		OpenCart
 * @author		zhousuyang
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

/**
* Pagination class
*/
class Pagination {
	public $total = 0;
	public $page = 1;
	public $limit = 20;
	public $num_links = 5;
	public $url = '';
	public $text_first = '|&lt;';
	public $text_last = '&gt;|';
	public $text_next = '&gt;';
	public $text_prev = '&lt;';

	/**
     * 
     *
     * @return	text
     */
	public function render() {
		$total = $this->total;

		if ($this->page < 1) {
			$page = 1;
		} else {
			$page = $this->page;
		}

		if (!(int)$this->limit) {
			$limit = 10;
		} else {
			$limit = $this->limit;
		}

		$num_links = $this->num_links;
		$num_pages = ceil($total / $limit);

		$this->url = str_replace('%7Bpage%7D', '{page}', $this->url);

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
//        $output.= '<li><input type="number" min="1" max="' . $num_pages . '" id="PageNum"/><button onclick="a();">GO</button></li>';
//        $output.= '<li>
//            <div class="col-lg-4">
//				<div class="input-group">
//					<input type="number" min="1" max="' . $num_pages . '" id="PageNum" class="form-control" value="'. $page .'">
//					<span class="input-group-btn">
//						<button class="btn btn-default" type="button" onclick="a();">
//							Go
//						</button>
//					</span>
//				</div>
//			</div></li>';
		$output .= '</ul>';
//        $output .= '<script>function a() {
//                var page = document.getElementById("PageNum").value;
//                var reg = /({page})/gi;
//                var url = "' . str_replace('&amp;', '&', $this->url) .'";
//                url = url.replace(reg, page);
//                location = url;
//        }</script>';



        $text_pages = '<span>%d-%d of %d (%d Pages)</span>';
        $text_pages = sprintf($text_pages,
            ($this->total) ? (($page - 1) * $this->limit) + 1 : 0,
            ((($page - 1) * $this->limit) > ($this->total - $this->limit)) ? $this->total : ((($page - 1) * $this->limit) + $this->limit),
            $this->total,
            ceil($this->total / $this->limit)
        );
        $text_pages .='
</span>';



		if ($num_pages > 1) {


            $text_rows = '
<span class="text_rows_page" style="">
    <span class="page-list">
        <span style="font-weight: 700;">Rows per page :</span>
        <span class="btn-group dropdown dropup">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
                <span class="page-size">' . $this->limit . '</span>
                <span class="caret"></span>
            </button>
            <div class="dropdown-menu" x-placement="top-start" style="left: 0px;will-change: transform;">';
                $rows = [$this->limit];
                $a = '';
                foreach ($rows as $v) {
                    if ($this->limit == $v) {
                        $a .= '<a class="dropdown-item active" href="' . str_replace('{page}', 1, $this->url) . '&page_size=' . $v . '">' . $v . '</a>';
                    } else {
                        $a .= '<a class="dropdown-item       " href="' . str_replace('{page}', 1, $this->url) . '&page_size=' . $v . '">' . $v . '</a>';
                    }
                }
                $text_rows .= $a;
                $text_rows .= '
            </div>
        </span>
    </span>';


            return $text_rows . $text_pages . $output;
            //return $output;
        } else {
			return $text_pages;
		}
	}
}
