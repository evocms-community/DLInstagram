/**
 * DLInstagram
 * 
 * DLInstagram
 * 
 * @category    snippet
 * @version     0.2.0
 * @internal    @modx_category Content

*/
//<?php

require_once MODX_BASE_PATH . 'assets/snippets/DocLister/core/DocLister.abstract.php';
require_once MODX_BASE_PATH . 'assets/snippets/DocLister/core/controller/onetable.php';

if (empty($params['token'])) {
    return 'Token required';
}

return $modx->runSnippet('DocLister', array_merge([
    'tpl'        => '@CODE: <li><a href="[+url+]" target="_blank" rel="nofollow"><img src="[+image+]" alt="[+e.caption+]"></a>',
    'ownerTPL'   => '@CODE: <ul>[+wrap+]</ul>',
    'dateSource' => 'timestamp',
    'e'          => 'caption',
], $params, [
    'controller' => 'instagram',
    'dir'        => 'assets/snippets/DLInstagram/controller/',
    'tree'       => 0,
]));
