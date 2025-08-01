<?php
/*
 * LimeSurvey
 * Copyright (C) 2007-2016 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 */

/**
 * This widget render a drop-up button for grid massive actions, and attach modals to the actions.
 */
class massiveActionsWidget extends CWidget
{

    /** @var string[] these are the names of the views in MassiveActionsWidget/../modals/ */
    private const NAME_OF_MODEL_VIEWS = [
        'yes-no',
        'empty',
        'yes-no-lg',
        'empty-lg',
        'cancel-apply',
        'cancel-change',
        'cancel-resend',
        'cancel-add',
        'cancel-save',
        'cancel-delete',
        'cancel-export',
    ];

    // Selector variables
    public $pk;                         // The primary key of the grid
    public $gridid;                     // The id of the related grid
    public $dropupId = 'dropupId'; // The wanted ID for the dropup button
    public $dropUpText = '';         // The wanted text for the dropup button
    public $aActions;                   // Array of actions

    public function run()
    {
        // Render the selector
        $this->render('selector');

        // Render the modal for each action
        foreach ($this->aActions as $key => $aAction) {
            // Not all action require a modal (eg: downloads, etc)
            if (isset($aAction['actionType']) && $aAction['actionType'] === 'modal') {
                // Modal type define the view to render in views/modal
                if ($this->isView($aAction['modalType'])) {
                    //TODO: common view for all modal types.
                    $this->render(
                        'modals/' . $aAction['modalType'],
                        array(
                            'aAction' => $aAction,
                            'key' => $key,
                        )
                    );
                } else {
                    // We could rather raise an exception.
                    $this->render('unknown_modal_type');
                }
            }
        }

        // The error modal rendered if no item is selected in the grid
        $this->render('modals/first-select', array());

        // Include il file JavaScript
        $scriptUrl = Yii::app()->getAssetManager()->publish(__DIR__ . '/assets/AAPListActions.js');
        Yii::app()->clientScript->registerScriptFile($scriptUrl, CClientScript::POS_END);
    }

    /**
     * Check if the view is in the list
     *
     * @param $display string this is the name of one of the files 
     * in plugins/AutoArchive/widget/MassiveActionsWidget/views/modals
     * @return bool
     */
    private function isView($display)
    {
        return in_array($display, self::NAME_OF_MODEL_VIEWS);
    }
}
