/**
 * DLInstagram
 *
 * DLInstagram
 *
 * @category    snippet
 * @version     0.3.0
 * @internal    @modx_category Content

*/
//<?php

return $modx->runSnippet('DocLister', array_merge([
    'tpl'        => '@CODE: <li><a href="[+url+]" target="_blank" rel="nofollow"><img src="[+image+]" alt="[+e.caption+]"></a>',
    'ownerTPL'   => '@CODE: <ul>[+dl.wrap+]</ul>',
    'dateSource' => 'timestamp',
    'e'          => 'caption',
], $params, [
    'controller' => 'InstagramDocLister',
    'dir'        => 'assets/snippets/DLInstagram/src/',
    'tree'       => 0,
]));
