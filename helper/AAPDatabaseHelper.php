<?php
/**
 * Helper functionalities for AutoArchive plugin
 */
class AAPDatabaseHelper
{

    /**
     * Note: Curly braces {{}} are necessary to ensure the table prefix is correctly applied.
     * Ensure this matches the table name specified in the installation script:
     * plugins/AutoArchive/installer/AAPluginInstaller.php
     */
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
            // todo create a PostgreSQL database to test the query above
            default:
                safeDie("Couldn't create 'select creation time table' query for connection type '" . Yii::app()->db->getDriverName() . "'");
        }
    }


    /**
     * Retrieves the most recent deactivation timestamp for a survey table
     * by parsing the date from renamed archived table names.
     * 
     * Con l'archiviazione delle risposte, la tabella [tablePrefix]_survey_[surveyid] 
     * viene rinominata in [tablePrefix]_old_survey_[surveyid]_[YYYYMMDDHHMMSS].
     * Quando si rinominano le tabelle, la data di creazione della tabella non cambia
     * e quindi non posso usare il campo create_time per sapere quando è stata rinominata la tabella.
     * Per questo motivo, devo recuperare la data dal nome della tabella [tablePrefix]_old_survey_[surveyid]_[YYYYMMDDHHMMSS]
     * per sapere quando è stata rinominata la tabella [tablePrefix]_survey_[surveyid] (e, quindi, archiviato il sondaggio [surveyid]).
     *
     * @param string $sTable The base name of the survey table.
     * @return string|null The most recent deactivation date in formatted string, or null if none found.
     */
    private static function dbSelectDeactivationDataTable($sTable)
    {
        $aTables = dbGetTablesLike($sTable);
        $sDBPrefix = Yii::app()->db->tablePrefix;
        $aDate = array();


        foreach ($aTables as $sTable) {


            // I check that the $sTable string has the desired format
            if (preg_match('/^' . preg_quote($sDBPrefix, '/') . 'old_survey_\d+_(\d{14})$/', $sTable, $matches)) {

                // I convert the [YYYYMMDDHHMMSS] string into an object using the captured group from the regex
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

        // I sort the dates in descending order
        rsort($aDate);

        // I take the most recent date
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
     * First, checks if the table old_survey_[surveyid] exists. If it does, returns its creation date.
     * If it doesn't exist, checks the autoarchive table to determine if the survey has been deactivated.
     * If so, returns the deactivation date from that table.
     *
     * This double-check is necessary because when survey responses are deleted,
     * the table old_survey_[surveyid] is also deleted, and its creation date is lost.
     *
     * @param int $surveyid The ID of the survey whose deactivation date is to be retrieved.
     * @return mixed The formatted deactivation date of the survey, or null if not found.
     */
    public static function getSurveyDeactivationDate($surveyid)
    {
        $deactivationDate = self::getSurveyDeactivationDateRaw($surveyid) ?? self::getLastDeactivationDateFromAutoArchive($surveyid);
        return $deactivationDate;
    }


    /**
     * Retrieves the creation date of the archived responses table for the survey.
     * This corresponds to the date when the table [tablePrefix]_survey_[surveyid]
     * was renamed to [tablePrefix]_old_survey_[surveyid]_[YYYYMMDDHHMMSS].
     *
     * @param int $surveyid The ID of the survey.
     * @return string|null The formatted deactivation date, or null if not found.
     */
    private static function getSurveyDeactivationDateRaw($surveyid)
    {
        AAPHelper::checkSurveyid($surveyid);
        // don't use [tablePrefix]
        $sTable = 'old_survey_' . $surveyid . '_%';
        return AAPDatabaseHelper::dbSelectDeactivationDataTable($sTable);
    }


    /**
     * Retrieves the expiration date of the last warning message sent for a survey.
     *
     * Internally calls getSurveyLastWarningExpirationDateRaw to fetch the latest warning operation.
     * If no warning has been sent, returns an empty string.
     *
     * @param int $surveyid The ID of the survey.
     * @return string The expiration date of the last warning, or an empty string if none was sent.
     */
    public static function getSurveyLastWarningExpirationDate($surveyid)
    {
        $operationDate = self::getSurveyLastWarningExpirationDateRaw($surveyid);
        if (!$operationDate) {
            return ''; // no warning sent
        }
        return $operationDate;
    }

    /**
     * Queries the database for the most recent successful "MSGOPEN" operation for the given survey.
     * This operation type corresponds to a warning message being sent.
     *
     * If no matching record is found, or the operation date is invalid, returns null.
     * Otherwise, returns the date of the last warning message sent.
     *
     * @param int $surveyid The ID of the survey.
     * @return string|null The date of the last warning sent, or null if none found.
     */
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



    /**
     * Retrieves the date of the last deactivation warning sent for a survey.
     *
     * This date is used in the expiredSurveysDeactivation schema and is calculated
     * based on the latest successful "MSGEXP" operation found in the {{autoarchive}} table.
     *
     * If no warning has been sent, or the operation date is invalid, returns null.
     *
     * @param int $surveyid The ID of the survey.
     * @return string|null The date of the last deactivation warning, or null if none found.
     */
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
     * Retrieves the most recent deactivation date for a specific survey from the autoarchive table.
     *
     * This method looks for the latest successful "DEA" operation associated with the given survey ID.
     * If no deactivation record is found, it returns null.
     *
     * @param int $surveyid The ID of the survey.
     * @return string|null The last deactivation date in 'Y-m-d H:i:s' format, or null if not found.
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
     * Checks whether the given date is valid and matches the specified format.
     *
     * @param string $date The date string to validate.
     * @return bool True if the date is valid and correctly formatted, false otherwise.
     */
    private static function isValidDateFormat($date)
    {
        $d = DateTime::createFromFormat(self::$format, $date);
        return $d && $d->format(self::$format) === $date;
    }

    /**
     * Writes an operation record to the autoarchive table.
     *
     * This method logs metadata about a survey operation, including its type, success status,
     * and optional custom date. It also captures various survey-related timestamps such as
     * activation, deactivation, and warning dates.
     *
     * If a custom operation date is provided, it must match the format defined in self::$format.
     * Otherwise, the current date is used.
     *
     * @param object $survey The survey object.
     * @param string $operationType The type of operation (e.g., AAPConstants::OP_MSGEXP, AAPConstants::OP_MSGOPEN).
     * @param bool $operationSuccess Indicates whether the operation was successful.
     * @param string|null $operationInfo Optional additional information about the operation.
     * @param string|null $customOperationDate Optional custom date for the operation (formatted according to self::$format).
     * @return bool True if the operation was successfully recorded, false otherwise.
     * @throws CHttpException If an error occurs during database write or if the custom date format is invalid.
     */
    public static function writeOperationRecord(
        $survey,
        $operationType,
        $operationSuccess,
        $operationInfo = null,
        $customOperationDate = null
    ) {
        AAPHelper::checkSurveyid($survey->sid);
        // if I get here, it means the survey ID is valid

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

        // Use the custom date if provided, otherwise use the current date
        $operationDate = $customOperationDate ?? date(self::$format); // DO NOT set the time adjustment!

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
     * Formats a raw date retrieved from the database into the specified format (self::$format).
     *
     * If the raw date is valid, it is converted into a DateTime object and formatted.
     * Logging is performed for debugging purposes, but may be removed once the code is stable.
     *
     * @param string|null $rawDate The raw date string to format.
     * @param string $logPrefix A prefix used in log messages for context.
     * @return string|null The formatted date string, or null if the input is null or formatting fails.
     */
    private static function formatAndLogDate(?string $rawDate, string $logPrefix): ?string
    {
        if ($rawDate) {
            try {
                $dateTime = new DateTime($rawDate);
                Yii::log($logPrefix . ' - Parsed DateTime object: ' . print_r($dateTime, true), CLogger::LEVEL_ERROR);
                // Use the self::$format property directly without modification
                $formattedDate = $dateTime->format(self::$format);
                Yii::log($logPrefix . ' - Successfully formatted date: ' . $formattedDate, CLogger::LEVEL_ERROR);
                return $formattedDate;
            } catch (Exception $e) {
                Yii::log("Error formatting date for {$logPrefix}: " . $e->getMessage(), CLogger::LEVEL_ERROR);
                return null;
            }
        }
        return null;
    }

    /**
     * Retrieves an array of archived table names for a given survey.
     * These include old response tables, timing tables, and token tables.
     *
     * @param int $surveyId The ID of the survey.
     * @return string[] List of matching archived table names.
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
        return $tables;
    }

    /**
     * Checks whether archived tables exist in the database for a given survey.
     *
     * @param int $surveyId The ID of the survey.
     * @return bool True if archived tables exist, false otherwise.
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
     * Deletes a list of tables from the database.
     * Logs each deletion attempt and any errors encountered.
     *
     * @param array $tableNames Array of table names to delete.
     * @return void
     */
    private static function deleteTables(array $tableNames)
    {
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
     * Deletes all archived tables associated with a given survey ID.
     * Verifies that the deletion was successful by checking for remaining tables.
     *
     * @param int $surveyId The ID of the survey.
     * @return bool True if all archived tables were deleted successfully, false otherwise.
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