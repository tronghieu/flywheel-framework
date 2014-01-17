<?php
/**
 * Created by PhpStorm.
 * User: Tan
 * Date: 1/17/14
 * Time: 11:49 AM
 */

namespace Flywheel\Paginator;

use Flywheel\Base;
use Flywheel\Config\ConfigHandler;


class Paginator
{
    public $totalItem, $itemPerPage, $pageParameter, $pageUrl, $startItem = 0;
    public $currentPage;

    public function __construct($current, $total, $perPage = 20, $url, $param = 'page')
    {
        $this->totalItem = $total;
        $this->itemPerPage = $perPage;
        $this->pageParameter = $param;
        $this->pageUrl = $url;
        $this->currentPage = $current;
    }

    public function startItem()
    {
        $start = $this->itemPerPage * ($this->currentPage - 1);
        return $start;
    }

    public function lastItem()
    {
        $c = $this->startItem() + $this->totalItem;
        $result = ($c > $this->totalItem) ? $this->totalItem : $c;
        return $result;
    }

    public function pages()
    {
        return ceil($this->totalItem / $this->itemPerPage);
    }

    public function firstPage($html_wrap, $html_current = '')
    {
        $page = '';
        if ($this->currentPage != 1) {
            $page = str_replace('{page}', 1, $html_wrap);
        } else {
            $page = str_replace('{page}', 1, $html_current);
        }
        return $page;
    }

    public function lastPage($html_wrap, $html_current = '')
    {
        $page = '';
        if ($this->currentPage < $this->pages()) {
            $page = str_replace('{page}', $this->pages(), $html_wrap);
        } else {
            $page = str_replace('{page}', $this->pages(), $html_current);
        }

        return $page;
    }

    public function nextPage($html_wrap, $html_current = '')
    {
        $page = '';
        if ($this->currentPage < $this->pages()) {
            $page = str_replace('{page}', $this->currentPage + 1, $html_wrap);
        } else {
            $page = str_replace('{page}', $this->currentPage + 1, $html_current);
        }
        return $page;
    }

    public function prevPage($html_wrap, $html_current = '')
    {

        $page = '';
        if ($this->currentPage != 1) {
            $page = str_replace('{page}', $this->pages() - 1, $html_wrap);

        } else {
            $page = str_replace('{page}', $this->pages() - 1, $html_current);
        }
        return $page;


    }

    public function paging($link, $current)
    {
        $result = '';
        $range = floor(($this->itemPerPage - 1) / 2);
        if (!$this->itemPerPage) {
            $page_nums = range(1, $this->pages());
        } else {
            $lower_limit = max($this->currentPage - $range, 1);
            $upper_limit = min($this->pages(), $this->currentPage + $range);
            $page_nums = range($lower_limit, $upper_limit);
        }

        foreach ($page_nums as $i) {
            if ($this->currentPage == $i) {
                $result .= str_replace('{page}', $i, $current);
            } else {
                $result .= str_replace('{page}', $i, $link);
            }
        }
        return $result;
    }

    public function render()
    {
        $html = '';
        $html .= $this->firstPage('<li><a href="' . $this->pageUrl . '{page}">Trang đầu</a></li>') . $this->prevPage('<li><a href="' . $this->pageUrl . '{page}">Trang trước</a></li>');
        $html .= $this->paging('<li><a href="' . $this->pageUrl . '{page}">{page}</a></li>', '<li class="active"><a>{page}</a></li>');
        $html .= $this->nextPage('<li><a href="' . $this->pageUrl . '{page}">Trang tiếp</a></li>') . $this->lastPage('<li><a href="' . $this->pageUrl . '{page}">Trang cuối</a></li>');
        return $html;
    }


}