<?php

spl_autoload_register(function ($class) {
    static $classes = null;

    if ($classes === null) {
        $classes = [
            'DocLister' => '/../DocLister/core/DocLister.abstract.php',
            'onetableDocLister' => '/../DocLister/core/controller/onetable.php',
            'InstagramDocLister' => '/src/InstagramDocLister.php',
            'EvolutionCMS\\DLInstagram\\Manager' => '/src/Manager.php',
        ];
    }

    if (isset($classes[$class])) {
        require __DIR__ . $classes[$class];
        return;
    }
}, true);
