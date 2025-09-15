<?php
/* @var $this AdminController */
/* @var $model CActiveDataProvider */
/* @var $pagetitle String */
/* @var $subject String */
/* @var $body String */
/* @var $actions Array */
?>

<?php if (!Permission::model()->hasGlobalPermission('superadmin')): ?>
    <div class="row">
        <div class="col-12">
            <h2>
                <?php echo gT("You do not have sufficient rights to access this page.") ?>
            </h2>

        </div>
    </div>
    <?php App()->end(); ?>
<?php endif; ?>

<div class="h1 pagetitle"><?php eT($pagetitle) ?? '' ?></div>

<!-- Grid -->
<div class="row">
    <div class="col-12">

        <?php
        $surveyGrid = $this->widget('application.extensions.admin.grid.CLSGridView', [
            'dataProvider' => $model->search(),
            // Number of row per page selection
            'id' => 'survey-grid',
            'emptyText' => gT('No surveys found.'),
            'summaryText' => '{count}' > 1 ? gT('Displaying {start}-{end} of {count} result(s).') : '',
            'ajaxUpdate' => 'survey-grid',
            'lsAfterAjaxUpdate' => [
                'window.LS.doToolTip();',
                'bindListItemclick();',
                'switchStatusOfListActions();',
            ],
            'rowLink' =>
                'App()->getConfig("editorEnabled") && Yii::app()->getConfig("debug")'
                . ' ? App()->createUrl("editorLink/index", ["route" => "survey/" . $data->sid]) '
                . ' : Yii::app()->createUrl("surveyAdministration/view/",array("iSurveyID"=>$data->sid))',
            'columns' => $model->getColumns(),
        ]);
        ?>

        <!-- Rendering massive action widget -->
        <?php
        $button = $this->widget(
            'ext.admin.grid.MassiveActionsWidget.MassiveActionsWidget',
            array(
                'pk' => 'sid',
                'gridid' => 'survey-grid',
                'dropupId' => 'surveyListActions',
                'dropUpText' => gT('Edit selected surveys'),
                'aActions' => $actions,
            )
        );
        ?>
        
    </div>
</div>