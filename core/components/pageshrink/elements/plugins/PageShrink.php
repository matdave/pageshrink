<?php

/**
 * @var modX $modx
 * @var array $scriptProperties
 */
$pageshrink = $modx->getService(
    'pageshrink',
    'PageShrink',
    $modx->getOption(
        'pageshrink.core_path',
        null,
        $modx->getOption('core_path') . 'components/pageshrink/'
    ) . 'model/pageshrink/'
);
if (!($pageshrink instanceof \PageShrink)) return '';

$event = "\\PageShrink\\Event\\{$modx->event->name}";
if (class_exists($event)) {
    return (new $event($pageshrink, $scriptProperties))->run();
} else {
    $modx->log(modX::LOG_LEVEL_ERROR, "PageShrink: Event {$modx->event->name} not found");
}
