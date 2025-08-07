<?php

/**
 * Plugin to enable two factor authentication for LimeSurvey Admin Backend
 * @author LimeSurvey GmbH <info@limesurvey.org>
 * @license GPL 2.0 or later
 */


require_once __DIR__ . '/helper/AAPCostants.php';  // need
require_once __DIR__ . '/helper/AAPDatabaseHelper.php';
require_once __DIR__ . '/helper/AAPHelper.php';
require_once __DIR__ . '/helper/AAPMenuItem.php';
require_once __DIR__ . '/installer/AAPluginInstaller.php';
require_once __DIR__ . '/models/AAPSurvey.php';
require_once __DIR__ . '/widget/MassiveActionsWidget/MassiveActionsWidget.php';
require_once __DIR__ . '/widget/ListSurveysWidget/ListSurveysWidget.php';
require_once __DIR__ . '/widget/CLSGridView.php';


// translation
setlocale(LC_ALL, 'it_IT.UTF-8');
bindtextdomain('AutoArchive', __DIR__ . DIRECTORY_SEPARATOR . 'locale');
textdomain('AutoArchive');

// Debug: verifica se il dominio è attivo
// echo "Dominio attivo: " . textdomain(null) . "\n";
// Debug: verifica se il file .mo viene caricato
// echo "Percorso bindtextdomain: " . bindtextdomain('AutoArchive', null) . "\n";
// Test traduzione
// echo gettext('Activation date'); // Dovrebbe stampare "Data di attivazione"

class AutoArchive extends PluginBase
{
    protected $storage = 'DbStorage';
    protected static $description = 'Auto Archive Old Surveys';
    protected static $name = 'AutoArchive';

    // Procedura: Apertura -> Sospensione/Interruzione -> Disattivazione/Archiviazione -> Eliminazione
    // Procedure: Opening -> Expiration -> Deactivation -> Deletion


    /** @inheritdoc, this plugin allow this public method */
    public $allowedPublicMethods = array(
        'openSurveysMsg',
        'openSurveysExpiration',
        'expiredSurveysMsg',
        'expiredSurveysDeactivation',
        'inactiveSurveysAnswer',
        'inactiveSurveysStructure'
    );

    protected $settings = array(
        'max_open' => array(
            'type' => 'int',
            'label' => 'Max Open Months',
            'default' => AAPConstants::DEFAULT_MAX_SURVEY_OPEN,
            'help' => 'The maximum number of months a survey can remain open. After that, it will be expired.'
        ),
        'max_expiration' => array(
            'type' => 'int',
            'label' => 'Max Expiration Months',
            'default' => AAPConstants::DEFAULT_MAX_SURVEY_EXPIRATION,
            'help' => 'The maximum number of months a survey can stay suspended. After this period, it will be deactivated.'
        ),
        'max_response_retention' => array(
            'type' => 'int',
            'label' => 'Max Response Retention Months',
            'default' => AAPConstants::DEFAULT_MAX_SURVEY_RESPONSE_DELETION,
            'help' => 'The maximum number of months after which the archive of responses from deactivated surveys will be deleted.'
        ),
        'max_structure_retention' => array(
            'type' => 'int',
            'label' => 'Max Structure Retention Months',
            'default' => AAPConstants::DEFAULT_MAX_SURVEY_STRUCTURE_DELETION,
            'help' => 'The maximum number of months after which the structure of deactivated surveys will be deleted.'
        ),
        'warning_expiration' => array(
            'type' => 'int',
            'label' => 'Warning Expiration Months',
            'default' => AAPConstants::DEFAULT_WARNING_EXPIRATION_MONTHS,
            'help' => 'The number of months from the opening of a survey after which the system generates a warning message to communicate the scheduled expiration (after MAX OPEN MONTHS from the opening). Must be less than MAX OPEN MONTHS.'
        ),
        'warning_deactivation' => array(
            'type' => 'int',
            'label' => 'Warning Deactivation Months',
            'default' => AAPConstants::DEFAULT_WARNING_DEACTIVATION_MONTHS,
            'help' => 'The number of months from the expiration of a survey after which the system generates a warning message to communicate the scheduled deactivation (after MAX EXPIRATION MONTHS from the expiration). Must be less than MAX EXPIRATION MONTHS.'
        ),
        'email_placeholders' => array(
            'type' => 'string',
            'label' => 'Email Placeholders',
            'default' => AAPConstants::AAP_PLACEHOLDERS,
            'help' => 'The placeholders that can be used in the email messages. They will be replaced with the actual values when sending the email.',
            'htmlOptions' => array(
                'readonly' => 'readonly', // Adds the readonly attribute to the HTML input element
            ),
        ),
        'open_surveys_msg_header' => array(
            'type' => 'string',
            'label' => 'Open Surveys Message Header',
            'default' => AAPConstants::OPEN_SURVEYS_MSG_HEADER,
            'help' => 'The header for the email sent when surveys are about to expire.'
        ),
        'open_surveys_msg_body' => array(
            'type' => 'string',
            'label' => 'Open Surveys Message Body',
            'default' => AAPConstants::OPEN_SURVEYS_MSG_BODY,
            'help' => 'The body for the email sent when surveys are about to expire.'
        ),
        'expired_surveys_msg_header' => array(
            'type' => 'string',
            'label' => 'Expired Surveys Message Header',
            'default' => AAPConstants::EXPIRED_SURVEYS_MSG_HEADER,
            'help' => 'The header for the email sent when surveys are about to be deactivated.'
        ),
        'expired_surveys_msg_body' => array(
            'type' => 'string',
            'label' => 'Expired Surveys Message Body',
            'default' => AAPConstants::EXPIRED_SURVEYS_MSG_BODY,
            'help' => 'The body for the email sent when surveys are about to be deactivated.'
        ),
    );

    public function init()
    {
        //System events
        $this->subscribe('beforeActivate');
        $this->subscribe('beforeDeactivate');
        $this->subscribe('beforeAdminMenuRender');
        // Show page
        $this->subscribe("newDirectRequest");
    }

    //##############  Plugin event handlers ##############//

    /**
     * Register new table and populate it.
     *
     * @return void
     */
    public function beforeActivate()
    {
        AAPluginInstaller::instance()->install();
    }

    /**
     * Delete the created tables again
     *
     * @return void
     */
    public function beforeDeactivate()
    {
        // AAPluginInstaller::instance()->uninstall(); // todo commentare
    }

    public function newDirectRequest()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }

        if (Yii::app()->user->getIsGuest()) {
            $this->isCurrentUrl = true;
            App()->user->setReturnUrl(App()->request->requestUri);
            App()->controller->redirect(["/admin/authentication"]);
        }

        $sAction = $this->event->get("function");

        switch ($sAction) {
            case "expireMultipleSurveysNow":
                $this->actionExpireMultipleSurveysNow();
                break;
            case "deactivateMultipleSurveysNow":
                $this->actionDeactivateMultipleSurveysNow();
                break;
            case "deleteMultipleAnswersNow":
                $this->actionDeleteMultipleAnswersNow();
                break;
            case "deleteMultipleSurveysNow":
                $this->actionDeleteMultipleSurveysNow();
                break;
            case "sendBulkSurveyOwnerEmails":
                $this->actionSendBulkSurveyOwnerEmails();
                break;
        }
    }

    /**
     * Add menu to the top bar
     * @return void
     */
    public function beforeAdminMenuRender()
    {
        $baseUrl = 'admin/pluginhelper/sa/fullpagewrapper/plugin/AutoArchive/method';

        $oEvent = $this->getEvent();
        $aMenuItems = [];

        $aMenuItems[] = new AAPMenuItem('Open surveys (send msg)', $this->api->createUrl("$baseUrl/openSurveysMsg", []));
        $aMenuItems[] = new AAPMenuItem('Open surveys (expiration)', $this->api->createUrl("$baseUrl/openSurveysExpiration", []));
        $aMenuItems[] = new AAPMenuItem('Expired surveys (send msg)', $this->api->createUrl("$baseUrl/expiredSurveysMsg", []));
        $aMenuItems[] = new AAPMenuItem('Expired surveys (deactivation)', $this->api->createUrl("$baseUrl/expiredSurveysDeactivation", []));
        $aMenuItems[] = new AAPMenuItem('Inactive surveys (delete answer)', $this->api->createUrl("$baseUrl/inactiveSurveysAnswer", []));
        $aMenuItems[] = new AAPMenuItem('Inactive surveys (delete structure)', $this->api->createUrl("$baseUrl/inactiveSurveysStructure", []));


        $aNewMenuOptions = [
            'isDropDown' => true,
            'label' => gettext('AutoArchive'),
            'href' => '#',
            'menuItems' => $aMenuItems
        ];
        $oNewMenu = (new \LimeSurvey\Menu\Menu($aNewMenuOptions));

        //enable menu only if plugin is active and user is admin
        if (AAPHelper::isPluginActive() && (Permission::model()->hasGlobalPermission('superadmin'))) {
            $oEvent->append('extraMenus', [$oNewMenu]);
        }
    }

    //################# View rendering ###################
    // openSurveysMsg
    // expiredSurveysMsg
    // openSurveysExpiration
    // expiredSurveysDeactivation
    // inactiveSurveysStructure
    // inactiveSurveysAnswer


    /**
     * Render the view for sending email to survey owners who have open surveys 
     * that are in the Running state (active) and older than a specified number of months.
     * 
     * @return string Rendered HTML content
     */
    public function openSurveysMsg()
    {
        $model = new AAPSurvey('Search');
        // Filter the model to include only Running surveys
        $model->active = 'R';
        // Filter the model to include only Running surveys older than $months
        $model->openSinceXMonths = $this->get('warning_expiration', null, null, AAPConstants::DEFAULT_WARNING_EXPIRATION_MONTHS);

        //Yii::log('openSurveysMsg $model->openSinceXMonths: ' . print_r($model->openSinceXMonths, true), CLogger::LEVEL_INFO);


        $aData = [
            'model' => $model,
            'pagetitle' => gettext('openSurveysMsg'),
            'subject' => $this->get('open_surveys_msg_header', null, null, AAPConstants::OPEN_SURVEYS_MSG_HEADER),
            'body' => $this->get('open_surveys_msg_body', null, null, AAPConstants::OPEN_SURVEYS_MSG_BODY),
            'operation_type' => 'MSGOPEN',
        ];

        //stampa il contenuto della variabile $aData    
        //Yii::log('openSurveysMsg $model: ' . print_r($model, true), CLogger::LEVEL_INFO);

        return $this->renderPartial('sendingEmailtemplate', $aData, true);
    }

    public function expiredSurveysMsg()
    {
        $model = new AAPSurvey('Search');
        $model->active = 'E';
        $model->expiredSinceXMonths = $this->get('warning_deactivation', null, null, AAPConstants::DEFAULT_WARNING_DEACTIVATION_MONTHS);
        //Yii::log('expiredSurveysMsg $model->expiredSinceXMonths: ' . print_r($model->expiredSinceXMonths, true), CLogger::LEVEL_INFO);


        $aData = [
            'model' => $model,
            'pagetitle' => gettext('expiredSurveysMsg'),
            'subject' => $this->get('expired_surveys_msg_header', null, null, AAPConstants::EXPIRED_SURVEYS_MSG_HEADER),
            'body' => $this->get('expired_surveys_msg_body', null, null, AAPConstants::EXPIRED_SURVEYS_MSG_BODY),
            'operation_type' => 'MSGEXP',
        ];

        return $this->renderPartial('sendingEmailtemplate', $aData, true);
    }

    public function openSurveysExpiration()
    {
        $model = new AAPSurvey('Search');
        // Filter the model to include only Running surveys
        $model->active = 'R';
        // Filter the model to include only Running surveys older than $months
        $model->openSinceXMonths = $this->get('max_open', null, null, AAPConstants::DEFAULT_MAX_SURVEY_OPEN);

        // Actions for the massive action widget in views/template.php
        $actions = array(
            array(
                // li element
                'type' => 'action',
                'action' => 'expire',
                'url' => App()->createUrl('plugins/direct', ['plugin' => 'AutoArchive', 'function' => 'expireMultipleSurveysNow']),
                'iconClasses' => 'ri-skip-forward-fill',
                'text' => gettext("Expiry now"),
                'grid-reload' => 'yes',
                // modal
                'actionType' => 'modal',
                'modalType' => 'cancel-apply',
                'showSelected' => 'yes',
                'selectedUrl' => App()->createUrl('surveyAdministration/renderItemsSelected'),
                'keepopen' => 'yes',
                'sModalTitle' => gettext('Expire surveys'),
                'htmlModalBody' => gettext('Are you sure you want to expire all those surveys?'),
            )
        );

        $aData = [
            'model' => $model,
            'pagetitle' => gettext('openSurveysExpiration'),
            'actions' => $actions,
        ];

        return $this->renderPartial('template', $aData, true);
    }

    public function expiredSurveysDeactivation()
    {
        $model = new AAPSurvey('Search');
        $model->active = 'E';
        $model->expiredSinceXMonths = $this->get('max_expiration', null, null, AAPConstants::DEFAULT_MAX_SURVEY_EXPIRATION);

        // Actions for the massive action widget in views/template.php
        $actions = array(
            array(
                // li element
                'type' => 'action',
                'action' => 'expire',
                'url' => App()->createUrl('plugins/direct', ['plugin' => 'AutoArchive', 'function' => 'deactivateMultipleSurveysNow']),
                'iconClasses' => 'ri-record-circle-line',
                'text' => gettext('Deactivate surveys'),
                'grid-reload' => 'yes',

                // modal
                'actionType' => 'modal',
                'modalType' => 'cancel-apply',
                'keepopen' => 'yes',
                'showSelected' => 'yes',
                'selectedUrl' => App()->createUrl('/surveyAdministration/renderItemsSelected/'),
                'sModalTitle' => gettext('Deactivate surveys'),
                'htmlModalBody' => gettext('Are you sure you want to deactivate all those surveys?'),
            )
        );

        $aData = [
            'model' => $model,
            'pagetitle' => gettext('expiredSurveysDeactivation'),
            'actions' => $actions,
        ];

        return $this->renderPartial('template', $aData, true);
    }

    public function inactiveSurveysStructure()
    {
        $model = new AAPSurvey('Search');
        $model->active = 'N';
        $model->deactivatedSinceXMonths = $this->get('max_structure_retention', null, null, AAPConstants::DEFAULT_MAX_SURVEY_STRUCTURE_DELETION);
        $model->withoutResponse = true;
        // Yii::log('inactiveSurveysStructure $model->deactivatedSinceXMonths: ' . print_r($model->deactivatedSinceXMonths, true), CLogger::LEVEL_INFO);

        // Actions for the massive action widget in views/template.php
        $actions = array(
            array(
                // li element
                'type' => 'action',
                'action' => 'delete',
                'url' => App()->createUrl('plugins/direct', ['plugin' => 'AutoArchive', 'function' => 'deleteMultipleSurveysNow']),
                'iconClasses' => 'ri-delete-bin-fill text-danger',
                'text' => gettext('Delete'),
                'grid-reload' => 'yes',

                // modal
                'actionType' => 'modal',
                'modalType' => 'cancel-delete',
                'keepopen' => 'yes',
                'showSelected' => 'yes',
                'selectedUrl' => App()->createUrl('/surveyAdministration/renderItemsSelected/'),
                'sModalTitle' => gettext('Delete surveys'),
                'htmlModalBody' => gettext('Are you sure you want to delete all those surveys?'),
            )
        );

        $aData = [
            'model' => $model,
            'pagetitle' => gettext('inactiveSurveysStructure'),
            'actions' => $actions,
        ];

        return $this->renderPartial('template', $aData, true);
    }

    public function inactiveSurveysAnswer()
    {
        $model = new AAPSurvey('Search');
        $model->active = 'N';
        $model->deactivatedSinceXMonths = $this->get('max_response_retention', null, null, AAPConstants::DEFAULT_MAX_SURVEY_RESPONSE_DELETION);
        $model->withResponse = true;
        //Yii::log('inactiveSurveysAnswer $model->deactivatedSinceXMonths: ' . print_r($model->deactivatedSinceXMonths, true), CLogger::LEVEL_INFO);

        // Actions for the massive action widget in views/template.php
        $actions = array(
            array(
                // li element
                'type' => 'action',
                'action' => 'delete',
                'url' => App()->createUrl('plugins/direct', ['plugin' => 'AutoArchive', 'function' => 'deleteMultipleAnswersNow']),
                'iconClasses' => 'ri-delete-bin-fill',
                'text' => gettext('Delete'),
                'grid-reload' => 'yes',

                // modal
                'actionType' => 'modal',
                'modalType' => 'cancel-delete',
                'keepopen' => 'yes',
                'showSelected' => 'yes',
                'selectedUrl' => App()->createUrl('/surveyAdministration/renderItemsSelected/'),
                'sModalTitle' => gettext('Delete survey responses'),
                'htmlModalBody' => gettext('Are you sure you want to delete all the selected survey responses?'),
            )
        );

        $aData = [
            'model' => $model,
            'pagetitle' => gettext('inactiveSurveysAnswer'),
            'actions' => $actions,
        ];

        return $this->renderPartial('template', $aData, true);


    }

    //################### Action methods ##################
    // actionExpireMultipleSurveysNow
    // actionSendBulkSurveyOwnerEmails
    // actionDeactivateMultipleSurveysNow
    // actionDeleteMultipleSurveysNow
    // actionDeleteMultipleAnswersNow

    /**
     * Action to expiry multiple surveys with current time.
     *  (ajax request)
     *
     * @return void
     * @throws CException
     */
    public function actionExpireMultipleSurveysNow()
    {
        $sSurveys = $_POST['sItems'] ?? '';
        $aSIDs = json_decode($sSurveys);
        $aResults = array();

        // $formatdata = getDateFormatData(Yii::app()->session['dateformat']);
        // Get the current date and time
        // and format it to match the database format
        // date_default_timezone_set('Europe/Rome'); 
        $now = new DateTime();
        $expires = $now->format('Y-m-d H:i:s');

        if (trim((string) $expires) == "") {
            $expires = null;
        }

        foreach ($aSIDs as $iSurveyID) {

            if ((int) $iSurveyID > 0) {

                $iSurveyID = sanitize_int($iSurveyID);
                $oSurvey = Survey::model()->findByPk($iSurveyID);

                $aResults[$iSurveyID]['title'] = ellipsize(
                    $oSurvey->correct_relation_defaultlanguage->surveyls_title,
                    30,
                    1,
                    '...'
                );

                if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update')) {
                    $aResults[$iSurveyID]['result'] = false;
                    $aResults[$iSurveyID]['error'] = gettext("User does not have valid permissions");
                } else {

                    //Survey::model()->expire($iSurveyID); metodo che viene richiamato sotto con $survey->expires = $expires;
                    $oSurvey->expires = $expires;
                    $success = $oSurvey->save();

                    $aResults[$iSurveyID]['result'] = $success;

                    if (!$success) {
                        $aResults[$iSurveyID]['error'] = gettext("Failed to send email");
                    }

                    // Aggiunta registrazione su tabella di log della expiration forzata del sondaggio 
                    // 'operation_type' => "ENUM('MSGOPEN', 'EXP', 'MSGEXP', 'DEA', 'DELANS', 'DELALL') NOT NULL", 
                    AAPDatabaseHelper::writeOperationRecord(
                        $oSurvey,
                        'EXP',
                        $success,
                        $success ? null : $aResults[$iSurveyID]['error']
                    );

                }
            }
        }

        Yii::app()->getController()->renderPartial(
            'ext.admin.survey.ListSurveysWidget.views.massive_actions._action_results',
            array('aResults' => $aResults, 'successLabel' => gettext('OK'))
        );
    }

    /**
     * Sends an email to the survey owner.
     *
     * @param Survey $oSurvey The survey whose owner will receive the email.
     * @param string $subject The subject of the email.
     * @param string $message The body of the email, which will be sent as HTML.
     * 
     * @return bool Returns true if the email was sent successfully, false otherwise.
     * 
     * @throws Exception If an error occurs while sending the email.
     * 
     * Logs:
     * - Logs an error if the survey owner's email is not found.
     * - Logs an error if the email fails to send, including the exception message.
     */

    protected function sendEmailToSurveyOwner($oSurvey, $subject, $message)
    {
        // Recupera le informazioni del proprietario del sondaggio
        $owner = $oSurvey->owner;


        // Indica se l'email deve essere inviata solo al bundle email, nel caso in cui l'email del owner sia vuota o non valida
        $sendOnlyToBundleEmail = false;

        if (!$owner || !isset($owner->email)) {
            Yii::log("Survey owner email not found for survey ID: $oSurvey->sid", CLogger::LEVEL_ERROR);
            $sendOnlyToBundleEmail = true;
        }

        if (!LimeMailer::validateAddress($owner->email)) {
            Yii::log("Survey owner email $owner->email for survey ID: $oSurvey->sid is not valid", CLogger::LEVEL_ERROR);
            $sendOnlyToBundleEmail = true;
        }

        $bounceEmail = $oSurvey->oOptions->bounce_email;

        // se entrambe le mail sono vuote o non valide, non invio l'email
        if ($sendOnlyToBundleEmail && (empty($bounceEmail) || !LimeMailer::validateAddress($bounceEmail))) {
            return false;
        }

        // se sono qui vuol dire che almeno una tra le due mail è valida.
        // se l'email del proprietario è vuota o non valida ($sendOnlyToBundleEmail = true), invio solo al bundle email

        $toEmail = $owner->email;
        $toName = $owner->full_name ?? $owner->user_name;


        $fromEmail = Yii::app()->getConfig('siteadminemail');
        $fromName = Yii::app()->getConfig('siteadminname');

        $mailer = new LimeMailer();
        $mailer->SetFrom($fromEmail, $fromName);
        $mailer->Subject = $subject;
        $mailer->Body = $message;
        $mailer->IsHTML(true); // Imposta il messaggio come HTML

        try {

            // owner email è vuota o non valida, invio solo al bundle email
            // specificando che l'owner email non è vuoto o non valido 
            if ($sendOnlyToBundleEmail) {
                $mailer->setTo($bounceEmail, $toName);
                // To do: aggiungere il messaggio di errore
                $mailer->Body = '<div>' . "\n" . gettext("Detailed notification could not be sent because owner mail is missing or not valid: ") . $toEmail . '</div>' . "\n" . $message;

                return $mailer->Send();
            }

            // se sono qui allora è valida sia owner mail che il bundle email
            // tento l'invio all'owner 
            $mailer->setTo($toEmail, $toName);
            $mailerSuccess = $mailer->Send();

            if ($mailerSuccess)
                return true;

            // se sono qui allora è fallito l'invio all'owner
            // invio il messaggio al bundle email aggiungendo il messaggio di errore
            $mailer->Body = '<div>' . "\n" . gettext("Detailed notification could not be sent because of error ") . $mailer->getError() . '</div>' . "\n" . $message;
            $mailer->setTo($bounceEmail, $toName);
            $mailerSuccess = $mailer->Send();
            return $mailerSuccess;

        } catch (Exception $e) {
            Yii::log("Failed to send email to survey owner $owner->email for survey ID: $oSurvey->sid. Error: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return false;
        }
    }


    /**
     * Invia un messaggio a tutti i proprietari di una lista di sondaggi.
     *
     * @return array Risultati dell'invio per ogni sondaggio.
     */
    function actionSendBulkSurveyOwnerEmails(): void
    {

        //Yii::log('ActionSendBulkSurveyOwnerEmails message: ' . print_r($_POST, true), CLogger::LEVEL_INFO);

        $aResults = [];
        $sSurveys = $_POST['sItems'] ?? '';
        $aSurveyIds = json_decode($sSurveys);

        $message = $_POST['sEmailBody'] ?? 'The following survey will be expired, deactivated or deleted: ';
        $subject = $_POST['sEmailSubject'] ?? 'LimeSurvey Survey Alert: ';

        $operationType = $_POST['sOperationType'] ?? ''; // 'MSGOPEN' or 'MSGEXP'


        foreach ($aSurveyIds as $iSurveyId) {

            if ((int) $iSurveyId > 0) {
                $iSurveyId = sanitize_int($iSurveyId);
                $oSurvey = Survey::model()->findByPk($iSurveyId);
                $sTitle = $oSurvey->correct_relation_defaultlanguage->surveyls_title;

                $aResults[$iSurveyId]['title'] = ellipsize(
                    $sTitle,
                    30,
                    1,
                    '...'
                );

                $aReplacements = array(
                    AAPConstants::AAP_SURVEYTITLE => $sTitle,
                    AAPConstants::AAP_SURVEYID => $iSurveyId,
                    AAPConstants::AAP_OWNERNAME => $oSurvey->owner->full_name ?? $oSurvey->owner->user_name,
                    AAPConstants::AAP_SURVEYURL => $oSurvey->getSurveyUrl(null, [], true),
                );

                // Sostituisco i placeholder con i valori reali
                $message = AAPHelper::replaceKeysInString($message, $aReplacements);
                $subject = AAPHelper::replaceKeysInString($subject, $aReplacements);

                $success = $this->sendEmailToSurveyOwner($oSurvey, $subject, $message);

                $aResults[$iSurveyId]['result'] = $success;

                if (!$success) {
                    $aResults[$iSurveyId]['error'] = gettext("Failed to send email");
                }

                // Aggiunta registrazione su tabella di log dell'invio della mail di avviso del sondaggio 
                // 'operation_type' => "ENUM('MSGOPEN', 'EXP', 'MSGEXP', 'DEA', 'DELANS', 'DELALL') NOT NULL", 
                AAPDatabaseHelper::writeOperationRecord(
                    $oSurvey,
                    $operationType,
                    $success,
                    $success ? null : $aResults[$iSurveyId]['error']
                );
            }
        }

        Yii::app()->getController()->renderPartial(
            'ext.admin.survey.ListSurveysWidget.views.massive_actions._action_results',
            array('aResults' => $aResults, 'successLabel' => gettext('OK'))
        );
    }



    /**
     * Function responsible to deactivate a survey.
     *
     * @return void
     * @access public
     * @throws CException
     */

    public function actionDeactivateMultipleSurveysNow()
    {
        $aSurveys = json_decode(Yii::app()->request->getPost('sItems', ''));
        $aResults = array();

        foreach ($aSurveys as $iSurveyID) {
            $iSurveyID = sanitize_int($iSurveyID);
            $oSurvey = Survey::model()->findByPk($iSurveyID);

            $aResults[$iSurveyID]['error'] = null; // need
            $aResults[$iSurveyID]['title'] = ellipsize(
                $oSurvey->correct_relation_defaultlanguage->surveyls_title,
                30,
                1,
                '...'
            );

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveyactivation', 'update')) {

                // I got a deprecated warning here sometimes, but it works
                $diContainer = \LimeSurvey\DI::getContainer();
                $surveyDeactivate = $diContainer->get(LimeSurvey\Models\Services\SurveyDeactivate::class);
                try {
                    $result = $surveyDeactivate->deactivate($iSurveyID, ['ok' => 'Y']);

                    if (!empty($result["beforeDeactivate"]["message"])) {
                        $aResults[$iSurveyID]['result'] = false;
                        $aResults[$iSurveyID]['error'] = gettext($result["beforeDeactivate"]["message"]);
                    } else {
                        $aResults[$iSurveyID]['result'] = true;
                    }

                } catch (Exception $e) {
                    $aResults[$iSurveyID]['result'] = false;
                    $aResults[$iSurveyID]['error'] = gettext($e->getMessage());
                }

                // Aggiunta registrazione su tabella di log della deactivation forzata del sondaggio 
                // 'operation_type' => "ENUM('MSGOPEN', 'EXP', 'MSGEXP', 'DEA', 'DELANS', 'DELALL') NOT NULL", 
                AAPDatabaseHelper::writeOperationRecord(
                    $oSurvey,
                    'DEA',
                    $aResults[$iSurveyID]['result'],
                    $aResults[$iSurveyID]['error']
                );


            } else {
                $aResults[$iSurveyID]['result'] = false;
                $aResults[$iSurveyID]['error'] = gettext("User does not have valid permissions");
            }
        }

        Yii::app()->getController()->renderPartial(
            'ext.admin.survey.ListSurveysWidget.views.massive_actions._action_results',
            array('aResults' => $aResults, 'successLabel' => gettext('OK'))
        );
    }

    /**
     * Delete multiple survey.
     * To delete a survey, the user must have permissions, and there must be no archive tables for the survey
     *
     * @return void
     * @throws CException
     */
    public function actionDeleteMultipleSurveysNow()
    {
        $aSurveys = json_decode(Yii::app()->request->getPost('sItems', ''));
        $aResults = array();

        foreach ($aSurveys as $iSurveyID) {
            $iSurveyID = sanitize_int($iSurveyID);
            $oSurvey = Survey::model()->findByPk($iSurveyID);

            $aResults[$iSurveyID]['error'] = null; // need
            $aResults[$iSurveyID]['title'] = ellipsize(
                $oSurvey->correct_relation_defaultlanguage->surveyls_title,
                30,
                1,
                '...'
            );

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'delete')) {

                if (AAPDatabaseHelper::archivedTableExists($iSurveyID)) {
                    $aResults[$iSurveyID]['result'] = false;
                    $aResults[$iSurveyID]['error'] = gettext("You cannot delete a survey with archived data");
                } else {
                    $aResults[$iSurveyID]['result'] = Survey::model()->deleteSurvey($iSurveyID);
                }

                // Aggiunta registrazione su tabella di log della deactivation forzata del sondaggio 
                // 'operation_type' => "ENUM('MSGOPEN', 'EXP', 'MSGEXP', 'DEA', 'DELANS', 'DELALL') NOT NULL", 
                AAPDatabaseHelper::writeOperationRecord(
                    $oSurvey,
                    'DELALL',
                    $aResults[$iSurveyID]['result'],
                    $aResults[$iSurveyID]['error']
                );


            } else {
                $aResults[$iSurveyID]['result'] = false;
                $aResults[$iSurveyID]['error'] = gettext("User does not have valid permissions");
            }
        }

        Yii::app()->getController()->renderPartial(
            'ext.admin.survey.ListSurveysWidget.views.massive_actions._action_results',
            array('aResults' => $aResults, 'successLabel' => gettext('Deleted'))
        );
    }


    /**
     * Delete the responses of multiple surveys, but not the surveys themselves.
     * Deletes tables whose name starts with:
     * [tablePrefix]old_survey_[surveyId]%
     * [tablePrefix]old_tokens_[surveyId]%
     *
     * @return void
     * @throws CException
     */
    public function actionDeleteMultipleAnswersNow()
    {
        $aSurveys = json_decode(Yii::app()->request->getPost('sItems', ''));
        $aResults = array();
        foreach ($aSurveys as $iSurveyID) {
            $iSurveyID = sanitize_int($iSurveyID);
            $oSurvey = Survey::model()->findByPk($iSurveyID);

            $aResults[$iSurveyID]['error'] = null; // need
            $aResults[$iSurveyID]['title'] = ellipsize(
                $oSurvey->correct_relation_defaultlanguage->surveyls_title,
                30,
                1,
                '...'
            );

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'delete')) {

                if (AAPDatabaseHelper::archivedTableExists($iSurveyID)) {

                    // prima di cancellare una tabella old_survey_ID_AAAAMMGGHHMMSS
                    // recupero la sua sua data di disattivazione
                    // e la salvo sulla tabella autoarchive 

                    $operationDate = AAPDatabaseHelper::getSurveyDeactivationDate($iSurveyID);
                    
                    Yii::log('Data personalizzata da inviare a writeOperationRecord: ' . $operationDate, CLogger::LEVEL_INFO);
                    // Data personalizzata da inviare a writeOperationRecord: 06.08.2024 11:21:57


                    AAPDatabaseHelper::writeOperationRecord(
                        $oSurvey,
                        'DEA',
                        true,
                        'Operation not executed through AutoArchive, merely deduced.',
                        $operationDate
                    );



                    $successDelete = AAPDatabaseHelper::deleteArchivedTables($iSurveyID);
                    $aResults[$iSurveyID]['result'] = $successDelete;
                    if (!$successDelete) {
                        $aResults[$iSurveyID]['error'] = gettext("Failed to delete survey responses");
                    }

                } else {
                    $aResults[$iSurveyID]['result'] = false;
                    $aResults[$iSurveyID]['error'] = gettext("No response to delete");
                }

                // Aggiunta registrazione su tabella di log della deactivation forzata del sondaggio 
                // 'operation_type' => "ENUM('MSGOPEN', 'EXP', 'MSGEXP', 'DEA', 'DELANS', 'DELALL') NOT NULL", 
                AAPDatabaseHelper::writeOperationRecord(
                    $oSurvey,
                    'DELANS',
                    $aResults[$iSurveyID]['result'],
                    $aResults[$iSurveyID]['error']
                );


            } else {
                $aResults[$iSurveyID]['result'] = false;
                $aResults[$iSurveyID]['error'] = gettext("User does not have valid permissions");
            }
        }

        Yii::app()->getController()->renderPartial(
            'ext.admin.survey.ListSurveysWidget.views.massive_actions._action_results',
            array('aResults' => $aResults, 'successLabel' => gettext('Deleted'))
        );
    }
}
