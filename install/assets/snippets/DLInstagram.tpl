/**
 * DLInstagram
 * 
 * DLInstagram
 * 
 * @category    snippet
 * @version     0.1
 * @internal    @modx_category Content

*/
//<?php

require_once MODX_BASE_PATH . 'assets/snippets/DocLister/core/controller/onetable.php';

if (empty($params['token'])) {
    return 'Token required';
}

return $modx->runSnippet('DocLister', array_merge([
    'controller' => 'instagram',
    'dir'        => 'assets/snippets/DLInstagram/controller/',
    'tree'       => 0,
    'tpl'        => '@CODE: <li><a href="[+link+]" target="_blank" rel="nofollow"><img src="[+images.standard_resolution.url+]" alt="[+e.caption.text+]"></a>',
    'ownerTPL'   => '@CODE: <ul>[+wrap+]</ul>',
    'dateSource' => 'created_time',
    'e'          => 'caption.text',
], $params));
