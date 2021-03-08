/**
 * DLInstagram
 *
 * DLInstagram
 *
 * @category    plugin
 * @version     0.3.0
 * @author      mnoskov
 * @internal    @events OnWebPageInit,OnCacheUpdate,OnManagerPageInit,OnManagerWelcomeHome,OnPageNotFound
 * @internal    @properties &token=Token;text; &fetchMediaFields=Media fields to fetch;text;caption,media_type,media_url,permalink,thumbnail_url,timestamp &fetchUserFields=User fields to fetch;text;id,media_count,username &cachetime=Cache time, in seconds;text;86400 &debug=Debug;list;No==0||Yes==1;0
 * @internal    @modx_category Content
 */
//<?php

use EvolutionCMS\DLInstagram\Manager;
use Illuminate\Container\Container;

if (!function_exists('initializeDLInstagram')) {
    function initializeDLInstagram($params)
    {
        if (!class_exists('Manager')) {
            require_once MODX_BASE_PATH . 'assets/snippets/DLInstagram/autoload.php';
        }

        return new Manager($params);
    }
}

if ($modx instanceof Container) {
    if (!$modx->offsetExists('instagram')) {
        $modx->instance('instagram', initializeDLInstagram($params));
    }
} else if (!isset($modx->instagram) || !($modx->instagram instanceof Manager)) {
    $modx->instagram = initializeDLInstagram($params);
}

$instagram = $modx->instagram;

switch ($modx->event->name) {
    case 'OnManagerWelcomeHome': {
        $out = $instagram->renderDashboardWidget($params);

        if (!empty($out)) {
            $widgets['instagram'] = [
                'menuindex' => '-999',
                'id'        => 'instagram',
                'cols'      => 'col-sm-12',
                'icon'      => 'fa fa-instagram',
                'title'     => 'Instagram',
                'body'      => '<div class="card-body">' . $out . '</div>',
                'hide'      => '0',
            ];

            $modx->event->output(serialize($widgets));
        }

        break;
    }

    case 'OnCacheUpdate': {
        $instagram->setTokenStatus(Manager::TOKEN_VALID);
        break;
    }
}
