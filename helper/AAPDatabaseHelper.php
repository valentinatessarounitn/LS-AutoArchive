<?php
/**
 * Helper functionalities for AutoArchive plugin
 */
class AAPDatabaseHelper
{

    // Le parentesi servono {{}}, altrimenti non aggiunge il prefisso al nome della tabella.
    // Verificare che coincida con il nome della tabella nel file di installazione
    // plugins/AutoArchive/installer/AAPluginInstaller.php
    private static $table = '{{autoarchive}}';
    private static $format = 'Y-m-d H:i:s';

    private static function dbSelectCreationDataTable($sTable)
    {
        switch (Yii::app()->db->getDriverName()) {
            case 'mysqli':
            case 'mysql':
                return "SELECT CREATE_TIME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$sTable'";
            case 'dblib':
            case 'mssql':
            case 'sqlsrv':
                return "SELECT create_date FROM sys.tables where name = '$sTable'";
            case 'pgsql':
                return "SELECT pg_class.relcreationtime FROM pg_class WHERE relname = '$sTable'";
            // creare un db postgres per testare la query sopra
            default:
                safeDie("Couldn't create 'select creation time table' query for connection type '" . Yii::app()->db->getDriverName() . "'");
        }
    }


    // Con l'archiviazione delle risposte, la tabella [tablePrefix]_survey_[surveyid] 
// viene rinominata in [tablePrefix]_old_survey_[surveyid]_[YYYYMMDDHHMMSS].
// Quando si rinominano le tabelle, la data di creazione della tabella non cambia
// e quindi non posso usare il campo create_time per sapere quando è stata rinominata la tabella.
// Per questo motivo, devo recuperare la data dal nome della tabella [tablePrefix]_old_survey_[surveyid]_[YYYYMMDDHHMMSS]
// per sapere quando è stata rinominata la tabella [tablePrefix]_survey_[surveyid] (e, quindi, archiviato il sondaggio [surveyid]).

    private static function dbSelectDeactivationDataTable($sTable)
    {
        $aTables = dbGetTablesLike($sTable);
        $sDBPrefix = Yii::app()->db->tablePrefix;
        $aDate = array();


        foreach ($aTables as $sTable) {


            // Verifico che la stringa $sTable abbia il formato desiderato
            if (preg_match('/^' . preg_quote($sDBPrefix, '/') . 'old_survey_\d+_(\d{14})$/', $sTable, $matches)) {
                // Converto la stringa [YYYYMMDDHHMMSS]  in un oggetto utilizzando il gruppo catturato nella regex
                $sDateTime = $matches[1];
                $iYear = (int) substr($sDateTime, 0, 4);
                $iMonth = (int) substr($sDateTime, 4, 2);
                $iDay = (int) substr($sDateTime, 6, 2);
                $iHour = (int) substr($sDateTime, 8, 2);
                $iMinute = (int) substr($sDateTime, 10, 2);
                $iSecond = (int) substr($sDateTime, 12, 2);
                $sDate = (string) date(self::$format, (int) mktime($iHour, $iMinute, $iSecond, $iMonth, $iDay, $iYear));
                $aDate[] = $sDate;
            }
        }

        if (empty($aDate)) {
            return '';
        }

        // ordino le date in ordine decrescente
        rsort($aDate);

        // prendo la data più recente
        $sLastdeactivationDate = array_shift($aDate);
        return $sLastdeactivationDate;
    }

    /**
     * Get the creation data of the responses table for the survey.
     * Data di creazione della tabella [tablePrefix]_survey_[surveyid]
     *
     * @return string
     */
    public static function getSurveyActivationDate($surveyid)
    {
        return AAPHelper::convertData(self::getSurveyActivationDateRaw($surveyid));
    }

    private static function getSurveyActivationDateRaw($surveyid)
    {
        AAPHelper::checkSurveyid($surveyid);
        $sTable = Yii::app()->db->tablePrefix . 'survey_' . $surveyid;
        return Yii::app()->db->createCommand(AAPDatabaseHelper::dbSelectCreationDataTable($sTable))->queryScalar();
    }

    /**
     * Get the creation data of the deactivated responses table for the survey.
     * Date of renaming the responses table from [tablePrefix]_survey_[surveyid] 
     * to [tablePrefix]_old_survey_[surveyid]
     *
     * @return string
     */
    public static function getSurveyDeactivationDate($surveyid)
    {
        return AAPHelper::convertData(self::getSurveyDeactivationDateRaw($surveyid));
    }

    private static function getSurveyDeactivationDateRaw($surveyid)
    {
        AAPHelper::checkSurveyid($surveyid);
        // don't use [tablePrefix]
        $sTable = 'old_survey_' . $surveyid . '_%';
        return AAPDatabaseHelper::dbSelectDeactivationDataTable($sTable);
    }

    public static function getSurveyLastWarningExpirationDate($surveyid)
    {
        $operationDate = self::getSurveyLastWarningExpirationDateRaw($surveyid);
        if (!$operationDate) {
            return ''; // no warning sent
        }
        return AAPHelper::convertData($operationDate);
    }

    private static function getSurveyLastWarningExpirationDateRaw($surveyid)
    {

        AAPHelper::checkSurveyid($surveyid);

        $sQuery = 'SELECT * FROM ' . self::$table . ' WHERE operation_type = "MSGOPEN" AND survey_id =' . $surveyid . ' AND operation_success = TRUE ORDER BY operation_date DESC LIMIT 1';
        $aFirstRow = Yii::app()->db->createCommand($sQuery)->queryRow();
        if ($aFirstRow === false || !isset($aFirstRow['operation_date']) || $aFirstRow['operation_date'] == 0) {
            return null; // no warning sent
        }
        // return the date of the last warning sent
        return $aFirstRow['operation_date'];
    }


    // data dell'ultimo sollecito per deactivation (per la schemata expiredSurveysDeactivation)
    // data calcolata in base al contenuto della tabella {{autoarchive}}
    public static function getSurveyLastWarningDeactivationDate($surveyid)
    {

        $operationDate = self::getSurveyLastWarningDeactivationDateRaw($surveyid);
        if (!$operationDate) {
            return ''; // no warning sent
        }
        return AAPHelper::convertData($operationDate);
    }

    public static function getSurveyLastWarningDeactivationDateRaw($surveyid)
    {
        AAPHelper::checkSurveyid($surveyid);

        $sQuery = 'SELECT * FROM ' . self::$table . ' WHERE operation_type = "MSGEXP" AND survey_id =' . $surveyid . ' AND operation_success = TRUE ORDER BY operation_date DESC LIMIT 1';
        $aFirstRow = Yii::app()->db->createCommand($sQuery)->queryRow();
        if ($aFirstRow === false || !isset($aFirstRow['operation_date']) || $aFirstRow['operation_date'] == 0) {
            return null; // no warning sent
        }
        // return the date of the last warning sent
        return $aFirstRow['operation_date'];

    }



    public static function writeOperationRecord(
        $survey,
        $operationType,
        $operationSuccess,
        $operationInfo = null,
    ) {
        AAPHelper::checkSurveyid($survey->sid);
        // se arrivo qui allora vuol dire che il surveyid è valido

        $oDB = Yii::app()->db;
        $oTransaction = $oDB->beginTransaction();

        $lastActivationDate = null;
        $sLastActivationDate = self::getSurveyActivationDateRaw($survey->sid);
        if ($sLastActivationDate) {
            $lastActivationDate = new DateTime($sLastActivationDate)->format(self::$format);
        }

        $lastDeactivationDate = null;
        $sLastDeactivationDate = self::getSurveyDeactivationDateRaw($survey->sid);
        if ($sLastDeactivationDate) {
            $lastDeactivationDate = new DateTime($sLastDeactivationDate)->format(self::$format);
        }

        $lastWarningExpirationDate = null;
        $sLastWarningExpirationDate = self::getSurveyLastWarningExpirationDateRaw($survey->sid);
        if ($sLastWarningExpirationDate) {
            $lastWarningExpirationDate = new DateTime($sLastWarningExpirationDate)->format(self::$format);
        }


        $lastWarningDeactivationDate = null;
        $sLastWarningDeactivationDate = self::getSurveyLastWarningDeactivationDateRaw($survey->sid);
        if ($sLastWarningDeactivationDate) {
            $lastWarningDeactivationDate = new DateTime($sLastWarningDeactivationDate)->format(self::$format);
        }


        try {
            $oCommand = $oDB->createCommand();
            $oCommand->insert(
                self::$table,
                array(
                    'operation_date' => date(self::$format), // NON impostare il timeadjust
                    'operation_type' => $operationType,
                    'operation_success' => (int) $operationSuccess,
                    'operation_info' => $operationInfo,
                    'survey_id' => (int) $survey->sid,
                    'survey_status' => $survey->getState(),
                    'owner_id' => (int) $survey->owner->uid,
                    'owner_email' => $survey->owner->email,
                    'created_date' => $survey->datecreated,
                    'last_activation_date' => $lastActivationDate,
                    'last_expiration_date' => $survey->expires,
                    'last_deactivation_date' => $lastDeactivationDate,
                    'last_warning_expiration_date' => $lastWarningExpirationDate,
                    'last_warning_deactivation_date' => $lastWarningDeactivationDate,
                )
            );
            $oTransaction->commit();
            return true;
        } catch (Exception $e) {
            $oTransaction->rollback();
            throw new CHttpException(500, $e->getMessage());
        }
    }

}

