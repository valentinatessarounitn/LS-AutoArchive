<?php
/** @var ListSurveysWidget $this */
/**
 * Render the selector for surveys massive actions.
 *
 */
?>
<!-- Set hidden url for ajax post in listActions JS.   -->
<!-- Rendering massive action widget -->
<?php
    $this->widget('ext.admin.grid.MassiveActionsWidget.MassiveActionsWidget', array(
            'pk'          => 'sid',
            'gridid'      => 'survey-grid',
            'dropupId'    => 'surveyListActions',
            'dropUpText'  => gT('Edit selected surveys'),
            'aActions'    => array()
    ));
?>
