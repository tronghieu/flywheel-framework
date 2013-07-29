<?php
namespace Toxotes;

use Flywheel\Controller\WebController;
use Flywheel\Factory;

abstract class Controller extends WebController {
    /**
     * @var \Languages[]
     */
    public $languages = array();

    /**
     * @var \Languages
     */
    public $currentLang;

    public function renderBlock($position) {
        $lang = ($this->currentLang)? $this->currentLang->getLangCode(): null;

        $widgets = Block::getBlocksByPosition($position, $lang);

        $html = '';

        foreach ($widgets as $widget) {
            $widget->controllerTemplate = $this->getTemplatePath() .'/widget/';
            $html .= $widget->html();
        }

        return $html;
    }

    public function block($position) {
        echo $this->renderBlock($position);
    }

    /**
     * shortcut call \Flywheel\Router\WebRouter::createUrl() method
     * @see \Flywheel\Router\WebRouter::createUrl()
     * @param $route
     * @param array $params
     * @param string $ampersand
     * @return mixed
     */
    public function createUrl($route, $params = array(), $ampersand = '&') {
        $route = trim($route, '/');
        if ('post/detail' == $route) {
            if (isset($params['id']) && ($post = \Posts::retrieveById($params['id']))) {
                $params['slug'] = $post->getSlug();
            }

        } else if ('category/default' == $route) {
            if (isset($params['id']) && ($term = \Terms::retrieveById($params['id']))) {
                $params['slug'] = $term->getSlug();
            }
        } else if ('events/default' == $route) {
            if (isset($params['id']) && ($term = \Terms::retrieveById($params['id']))) {
                $params['slug'] = $term->getSlug();
            }
        } else if ('events/detail' == $route) {
            if (isset($params['id']) && ($post = \Posts::retrieveById($params['id']))) {
                $params['slug'] = $post->getSlug();
            }
        }

        if ($this->currentLang && sizeof($this->languages) > 1) {
            $params['lang'] = $this->currentLang->getLangCode();
        }

        return parent::createUrl($route, $params, $ampersand);
    }

    protected function _initLanguages()
    {
        $this->languages = \Languages::findByPublished(true);
        if (sizeof($this->languages) < 2) {
            $this->currentLang = $this->languages[0];
            return;
        }

        $currentLangCode = $this->request()->get('lang');
        if (!$currentLangCode) {
            $currentLangCode = Factory::getCookie()->read('lang');
        }

        if (!$currentLangCode) {
            $this->currentLang = \Languages::findOneByDefault(true);
            $currentLangCode = $this->currentLang->getLangCode();
        }

        Factory::getCookie()->write('lang', $currentLangCode);

        if (Factory::getRouter()->getUrl() == '/' && !$this->request()->get('lang')) {
            $this->redirect($currentLangCode);
        }

        if (!$this->currentLang) {
            $this->currentLang = \Languages::findOneByLangCode($currentLangCode);
        }
    }
}