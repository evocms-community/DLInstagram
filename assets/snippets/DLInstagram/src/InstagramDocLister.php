<?php

use EvolutionCMS\DLInstagram\Manager;

class InstagramDocLister extends onetableDocLister
{
    public function __construct($modx, $cfg = array(), $startTime = null)
    {
        parent::__construct($modx, $cfg, $startTime);
        $modx->instagram->checkToken();
    }

    public function render($tpl = '')
    {
        if ($this->modx->instagram->isTokenInvalid()) {
            return '';
        }

        return parent::render($tpl);
    }

    public function getDocs($tvlist = '')
    {
        if ($this->modx->instagram->isTokenInvalid()) {
            return [];
        }

        if ($this->extPaginate = $this->getExtender('paginate')) {
            $this->extPaginate->init($this);
        }

        $this->_docs = $this->getDocList();
        return $this->_docs;
    }

    protected function getDocList()
    {
        $page      = 1;
        $paginate  = $this->getCFGDef('paginate', 0);
        $display   = $this->getCFGDef('display', 10);

        if ($this->extPaginate) {
            $page = $this->extPaginate->currentPage();
        }

        return $this->modx->instagram->getMedia($page, $display);
    }

    public function getChildrenCount()
    {
        if ($this->modx->instagram->isTokenInvalid()) {
            return 0;
        }

        $data = $this->modx->instagram->getProfileData();
        return $data['user']['media_count'];
    }

    public function getChildrenFolder($id)
    {
        return [];
    }
}
