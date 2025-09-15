<?php
/**
 * Helper class for the AutoArchive plugin in LimeSurvey.
 * 
 * This file is located at: /var/www/html/limesurvey/plugins/AutoArchive/helper/AAPMenu
 * 
 * Purpose:
 * - Provides functionality related to the AutoArchive plugin's menu handling.
 */
class AAPMenuItem extends \LimeSurvey\Menu\MenuItem
{
    public function __construct($label, $href)
    {
        parent::__construct([
            'isDivider' => false,
            'isSmallText' => false,
            'label' => gettext($label),
            'href' => $href,
        ]);
    }
}