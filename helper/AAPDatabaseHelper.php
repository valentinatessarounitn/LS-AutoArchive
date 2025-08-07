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


    /* Con l'archiviazione delle risposte, la tabella [tablePrefix]_survey_[surveyid] 
     viene rinominata in [tablePrefix]_old_survey_[surveyid]_[YYYYMMDDHHMMSS].
     Quando si rinominano le tabelle, la data di creazione della tabella non cambia
     e quindi non posso usare il campo create_time per sapere quando è stata rinominata la tabella.
     Per questo motivo, devo recuperare la data dal nome della tabella [tablePrefix]_old_survey_[surveyid]_[YYYYMMDDHHMMSS]
     per sapere quando è stata rinominata la tabella [tablePrefix]_survey_[surveyid] (e, quindi, archiviato il sondaggio [surveyid]).
    */

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
            return null;
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
        AAPHelper::checkSurveyid($surveyid);
        $sTable = Yii::app()->db->tablePrefix . 'survey_' . $surveyid;
        $result = Yii::app()->db->createCommand(AAPDatabaseHelper::dbSelectCreationDataTable($sTable))->queryScalar();

        Yii::log("Activation date raw value for survey ID $surveyid: " . print_r($result, true), CLogger::LEVEL_INFO);

        return $result;
    }


    /**
     * Retrieves the deactivation date of a survey.
     * 
     * First, I check if the table old_survey_[surveyid] exists. If it does, I return the creation date of the table.
     * If it doesn't exist, I check the autoarchive table to see if the survey has been deactivated. If so, I return the deactivation date.
     *
     * The double check is necessary because when I delete the responses of a survey, 
     * the table old_survey_[surveyid] is deleted, and thus I lose the creation date of the table.
     * 
     * @param int $surveyid The ID of the survey whose deactivation date is to be retrieved.
     * @return mixed The converted deactivation date of the survey, or null if no date is found.
     */
    public static function getSurveyDeactivationDate($surveyid)
    {
        $deactivationDate = self::getSurveyDeactivationDateRaw($surveyid) ?? self::getLastDeactivationDateFromAutoArchive($surveyid);
        return $deactivationDate;
    }


    /**
     * Get the creation data of the deactivated responses table for the survey.
     * Date of renaming the responses table from [tablePrefix]_survey_[surveyid] 
     * to [tablePrefix]_old_survey_[surveyid]
     *
     * @return string
     */
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
        return $operationDate;
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


    
    /*
    public static function getSurveyLastWarningDeactivationDate($surveyid)
    {

        $operationDate = self::getSurveyLastWarningDeactivationDateRaw($surveyid);
        if (!$operationDate) {
            return ''; // no warning sent
        }
        re
        turn $operationDate;
    }
    */

    // data dell'ultimo sollecito per deactivation (per la schemata expiredSurveysDeactivation)
    // data calcolata in base al contenuto della tabella {{autoarchive}}
    public static function getSurveyLastWarningDeactivationDate($surveyid)
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

    /**
     * Get the last deactivation date from the autoarchive table for a specific survey.
     * This method retrieves the most recent deactivation date for a survey from the autoarchive table.
     *
     * @param int $surveyid The ID of the survey.
     * @return string|null The last deactivation date in 'Y-m-d H:i:s' format, or null if no deactivation found.
     */
    public static function getLastDeactivationDateFromAutoArchive($surveyid)
    {
        AAPHelper::checkSurveyid($surveyid);
        $sQuery = 'SELECT operation_date FROM ' . self::$table . ' WHERE operation_type = "DEA" AND operation_success = TRUE AND survey_id = ' . $surveyid . ' ORDER BY operation_date DESC LIMIT 1';
        $result = Yii::app()->db->createCommand($sQuery)->queryScalar();
        Yii::log("Deactivation found inside AutoArchive table for survey ID $surveyid : $result", CLogger::LEVEL_INFO);

        if ($result === false || $result === null) {
            return null; // no deactivation found    
        }

        return $result;
    }

    /**
     * Verifica se la data è valida e conforme al formato specificato.
     *
     * @param string $date La data da verificare.
     * @return bool True se la data è valida, false altrimenti.
     */
    private static function isValidDateFormat($date)
    {
        $d = DateTime::createFromFormat(self::$format, $date);
        return $d && $d->format(self::$format) === $date;
    }

    /**
     * Scrive un record dell'operazione.
     *
     * @param object $survey L'oggetto survey.
     * @param string $operationType Il tipo di operazione.
     * @param bool $operationSuccess Indica se l'operazione ha avuto successo.
     * @param string|null $operationInfo Informazioni aggiuntive sull'operazione.
     * @param string|null $customOperationDate Data personalizzata per l'operazione (formattata secondo self::$format).
     * @return bool True se l'operazione è stata scritta con successo, false altrimenti.
     * @throws CHttpException Se si verifica un errore durante la scrittura nel database.
     */
    public static function writeOperationRecord(
        $survey,
        $operationType,
        $operationSuccess,
        $operationInfo = null,
        $customOperationDate = null
    ) {
        AAPHelper::checkSurveyid($survey->sid);
        // se arrivo qui allora vuol dire che il surveyid è valido

        $oDB = Yii::app()->db;
        $oTransaction = $oDB->beginTransaction();

        $lastActivationDate = self::formatAndLogDate(
            self::getSurveyActivationDate($survey->sid),
            'Survey object lastActivationDate'
        );

        $lastDeactivationDate = self::formatAndLogDate(
            self::getSurveyDeactivationDateRaw($survey->sid),
            'Survey object lastDeactivationDate'
        );

        $lastWarningExpirationDate = self::formatAndLogDate(
            self::getSurveyLastWarningExpirationDateRaw($survey->sid),
            'Survey object lastWarningExpirationDate'
        );

        $lastWarningDeactivationDate = self::formatAndLogDate(
            self::getSurveyLastWarningDeactivationDate($survey->sid),
            'Survey object lastWarningDeactivationDate'
        );

        if ($customOperationDate !== null) {
            Yii::log('Data personalizzata ricevuta a writeOperationRecord: ' . $customOperationDate, CLogger::LEVEL_INFO);
        }

        if ($customOperationDate !== null && !self::isValidDateFormat($customOperationDate)) {
            throw new CHttpException(400, 'Formato data non valido. Atteso: ' . self::$format . ' Ricevuto: ' . $customOperationDate);
        }

        // Usa la data personalizzata se fornita, altrimenti la data corrente
        $operationDate = $customOperationDate ?? date(self::$format); // NON impostare il timeadjust

        try {
            $oCommand = $oDB->createCommand();
            $oCommand->insert(
                self::$table,
                array(
                    'operation_date' => $operationDate,
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

    /**
     * Formatta una data grezza ottenuta dal database in un formato specifico (self::$format).
     *
     * @param string|null $rawDate La data grezza da formattare.
     * @param string $logPrefix Un prefisso per i messaggi di log.
     * @return string|null La data formattata o null se la data grezza è null.
     */
    private static function formatAndLogDate(?string $rawDate, string $logPrefix): ?string
    {
        if ($rawDate) {
            try {
                $dateTime = new DateTime($rawDate);
                // Valutare se questi log sono ancora necessari una volta che il codice è stabile
                Yii::log($logPrefix . ' DateTime object: ' . print_r($dateTime, true), CLogger::LEVEL_ERROR);
                $formattedDate = $dateTime->format(self::$format); // Usa direttamente self::$format
                Yii::log($logPrefix . ' Formatted date: ' . print_r($formattedDate, true), CLogger::LEVEL_ERROR);
                return $formattedDate;
            } catch (Exception $e) {
                Yii::log('Errore nella formattazione della data per ' . $logPrefix . ': ' . $e->getMessage(), CLogger::LEVEL_ERROR);
                return null;
            }
        }
        return null;
    }

    /**
     * Gets an array of old response, old response timing and old tokens table names for a survey.
     * @param int $surveyId
     * @return string[]
     */
    private static function getOldTables($surveyId)
    {
        $tables = array();

        $base = App()->getDb()->tablePrefix . 'old_survey_' . $surveyId;
        $tokenbase = App()->getDb()->tablePrefix . 'old_tokens_' . $surveyId;
        foreach (App()->getDb()->getSchema()->getTableNames() as $table) {
            if (
                strpos((string) $table, $base) === 0
                || strpos((string) $table, $tokenbase) === 0
            ) {
                $tables[] = $table;
            }
        }

        if (!empty($tables)) {
            Yii::log("Found old tables for survey ID {$surveyId}: " . print_r($tables, true), CLogger::LEVEL_INFO);
        } else {
            Yii::log("No old tables found for survey ID {$surveyId}.", CLogger::LEVEL_INFO);
        }

        return $tables;
    }

    /**
     * Check if exist archived data in db (old response, old response timing and old tokens tables) for a survey
     * @param int $surveyId
     * @return bool
     */
    public static function archivedTableExists($surveyId)
    {
        $oldTables = AAPDatabaseHelper::getOldTables($surveyId);
        if (empty($oldTables)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Deletes tables from the database based on a list of table names.
     *
     * @param array $tableNames List of table names to delete.
     * @return void
     */
    private static function deleteTables(array $tableNames)
    {
        Yii::log("Will be delete tables: " . print_r($tableNames, true), CLogger::LEVEL_ERROR);

        $db = Yii::app()->db;
        foreach ($tableNames as $tableName) {
            try {
                $db->createCommand()->dropTable($tableName);
                Yii::log(gettext("Deleting survey table  {$tableName}"), CLogger::LEVEL_ERROR);
            } catch (Exception $e) {
                Yii::log("Failed to delete table {$tableName}: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            }
        }
    }

    /**
     * Deletes archived response tables for a given survey ID.
     * Ensures that the tables are deleted and verifies their removal.
     *
     * @param int $surveyId The ID of the survey whose archived tables should be deleted.
     * @return bool True if the tables were successfully deleted, false otherwise.
     */
    public static function deleteArchivedTables($surveyId)
    {
        AAPHelper::checkSurveyid($surveyId);
        Yii::log("Attempting to delete archived tables for survey ID: {$surveyId}", CLogger::LEVEL_INFO);

        // Retrieve the list of archived tables for the survey
        $archivedTables = AAPDatabaseHelper::getOldTables($surveyId);

        // Delete the retrieved tables
        AAPDatabaseHelper::deleteTables($archivedTables);

        // Refresh the database schema to ensure all operations are completed
        Yii::app()->db->getSchema()->refresh();

        // Verify that no archived tables remain
        $isDeletionSuccessful = !AAPDatabaseHelper::archivedTableExists($surveyId);

        if ($isDeletionSuccessful) {
            Yii::log("Archived tables for survey ID {$surveyId} were successfully deleted.", CLogger::LEVEL_INFO);
        } else {
            Yii::log("Failed to delete all archived tables for survey ID {$surveyId}.", CLogger::LEVEL_ERROR);
        }

        return $isDeletionSuccessful;
    }

    /**
     * Retrieves inactive surveys filtered by presence or absence of archived responses.
     *
     * @param bool $withResponses If true, returns surveys with archived responses; otherwise, without.
     * @return array List of survey IDs.
     */
    public static function getInactiveSurveysByResponseStatus($withResponses = false)
    {
        $db = Yii::app()->db;
        $query = "SELECT sid FROM {{surveys}} WHERE active = 'N'";
        $surveyIds = $db->createCommand($query)->queryColumn();
        $result = array();

        foreach ($surveyIds as $surveyId) {
            $hasArchived = AAPDatabaseHelper::archivedTableExists($surveyId);
            if ($withResponses === $hasArchived) {
                $result[] = $surveyId;
            }
        }

        $logLabel = $withResponses ? "with" : "without";
        Yii::log("Inactive surveys {$logLabel} responses: " . print_r($result, true), CLogger::LEVEL_INFO);

        return $result;
    }
}