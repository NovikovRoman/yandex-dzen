<?php

namespace YandexDzen;

use \Exception;

class YandexDzenException extends Exception
{
    private $html;

    public function setHtml($html)
    {
        $this->html = $html;
        return $this;
    }

    public function getHtml()
    {
        return $this->html;
    }
}