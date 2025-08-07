<?php
/* @var $this AdminController */
/* @var $model CActiveDataProvider */
/* @var $pagetitle String */
/* @var $subject String */
/* @var $body String */
/* @var $operation_type String */
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

        $surveyGrid = $this->widget('plugins.AutoArchive.widget.CLSGridView', [
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


        <div class="tab-content" style="margin-bottom: 20px;">
            <?php

            // Prepare the fields for the email
            $admin_name = Yii::app()->getConfig("siteadminname");
            $admin_email = Yii::app()->getConfig("siteadminemail");

            $fieldsarray[AAPConstants::AAP_ADMINNAME] = $admin_name;
            $fieldsarray[AAPConstants::AAP_ADMINEMAIL] = $admin_email;

            $textarea = Replacefields($body, $fieldsarray, false);

            ?>

            <div class="tab-pane fade show active">

                <!-- todo in produzione, dopo aver verificato il mittente, aggiungere style="display: none;"-->
                <div class='mb-3'>
                    <label class='form-label '><?php eT("From:"); ?></label>
                    <div class=''>
                        <?php echo CHtml::textField("from", $admin_name . " <" . $admin_email . ">", array('class' => 'form-control', 'readonly' => 'readonly')); ?>
                    </div>
                </div>


                <!-- Non rimuovere sOperationType, è nascosto ma serve a distinguere il tipo di messaggio"-->
                <div class='mb-3' style="display: none;">
                    <?php echo CHtml::textField("sOperationType", $operation_type, array('class' => 'form-control', 'readonly' => 'readonly')); ?>
                </div>

                <div class='mb-3'>
                    <label class='form-label '><?php eT("Subject:"); ?></label>
                    <div class=''>
                        <?php echo CHtml::textField("sEmailSubject", $subject, array('class' => 'form-control')); ?>
                    </div>
                </div>

                <div class='mb-3'>
                    <label><?php eT("Message:"); ?></label>

                    <div class="input-group htmleditor">
                        <?php
                        if (!function_exists('initKcfinder')) {
                            Yii::app()->loadHelper('admin.htmleditor');
                            Yii::app()->loadHelper('surveytranslator');
                        }

                        //PrepareEditorScript() è indispensabile per il metodo getEditor() di CKEditor
                        PrepareEditorScript();

                        //<!-- NEED 'id' => 'sEmailBody' altrimenti non viene inviato il body editato dall'utente -->
                        //<!-- code 177-190 AutoArchive/widget/MassiveActionsWidget/assets/listActions.js -->
                        echo CHtml::textArea(
                            'sEmailBody',
                            $textarea,
                            array('cols' => '80', 'rows' => '10', 'id' => 'sEmailBody')
                        ); ?>
                        <?php echo getEditor(
                            "survey-welc",
                            'sEmailBody',
                            $textarea,
                        ); ?>

                    </div>

                </div>
            </div>
        </div>





        <!-- Rendering massive action widget 
        Per l'invio delle email devo usare la versione customizzata di MassiveActionsWidget
        'plugins.AutoArchive.widget.MassiveActionsWidget.MassiveActionsWidget'
        che mi permette di passare il form con i dati dell'email
        -->
        <?php
        $deleteButton = $this->widget(
            'plugins.AutoArchive.widget.MassiveActionsWidget.MassiveActionsWidget',
            //'ext.admin.grid.MassiveActionsWidget.MassiveActionsWidget',
            array(
                'pk' => 'sid',
                'gridid' => 'survey-grid',
                'dropupId' => 'surveyListActions',
                'dropUpText' => gT('Edit selected surveys'),

                'aActions' => array(
                    // Send email
                    array(
                        // li element
                        'type' => 'action',
                        'action' => 'invite',
                        'disabled' => false,
                        'url' =>
                            App()->createUrl('plugins/direct', ['plugin' => 'AutoArchive', 'function' => 'sendBulkSurveyOwnerEmails']),
                        'iconClasses' => 'ri-mail-send-fill',
                        'text' => gT('Send email'),
                        'grid-reload' => 'yes', // Need to reload the grid with new Last warning date
        
                        // modal
                        'actionType' => 'modal',
                        'modalType' => 'cancel-apply',
                        'showSelected' => 'yes',
                        'selectedUrl' => App()->createUrl('surveyAdministration/renderItemsSelected'),
                        'keepopen' => 'yes',
                        'sModalTitle' => gT('Send email'),
                        'htmlModalBody' => gT('Are you sure you want to send an email to the owners of the selected surveys?'),
                    ),
                ),
            )
        );
        ?>
    </div>
</div>