<?php
/**
 * Helper class for the AutoArchive plugin.
 *
 * Provides utility methods for date formatting, string manipulation,
 * survey ID validation, and plugin status checks.
 */
class AAPHelper
{
    private static $format = 'Y-m-d H:i:s';

    /**
     * Checks whether the AutoArchive plugin is currently active.
     *
     * @return bool True if the plugin is active, false otherwise.
     */
    public static function isPluginActive()
    {
        $plugin = Plugin::model()->findByAttributes(["name" => "AutoArchive"]);

        if ($plugin) {
            return (int) $plugin->active;
        } else {
            return 0;
        }
    }

    /**
     * Validates that the survey ID contains only numeric characters.
     *
     * @param mixed $surveyid The survey ID to validate.
     * @throws Exception If the survey ID is invalid.
     */
    public static function checkSurveyid($surveyid)
    {
        // surveyid contains only numbers
        if (!preg_match('/^[0-9]+$/', $surveyid)) {
            throw new Exception("Invalid table name: $surveyid");
        }
    }

    /**
     * Replaces placeholders in a string with corresponding values from a key-value map.
     *
     * This method iterates through the provided associative array (map) and replaces
     * all occurrences of the keys in the string with their corresponding values.
     *
     * @param string $string The input string containing placeholders.
     * @param array $aReplacements Associative array of replacements [placeholder => value].
     * @return string The updated string with replacements applied.
     */
    public static function replaceKeysInString($string, $aReplacements)
    {
        foreach ($aReplacements as $key => $value) {
            $string = str_replace($key, $value, $string);
        }
        return $string;
    }

    /**
     * Converts a date string from 'YYYY-MM-DD HH:MM:SS' format to the user's session date format.
     *
     * Example: '2025-04-09 12:27:30' → '09.04.2025 12:27:30' (depending on session format).
     * Used in AAPSurvey.php.
     *
     * @param string|null $sDateTime The input date string.
     * @return string|null The formatted date string, or null if input is empty.
     */
    public static function formatDate($sDateTime)
    {
        // Imput format [AAAA-MM-GG HH:MM:SS]

        if ($sDateTime === null || empty($sDateTime)) {
            return null;
        }

        $dateformatdetails = getDateFormatData(Yii::app()->session['dateformat']);
        Yii::app()->loadLibrary('Date_Time_Converter');
        $datetimeobj = new Date_Time_Converter(dateShift($sDateTime, self::$format, App()->getConfig('timeadjust')), self::$format);

        return $datetimeobj->convert($dateformatdetails['phpdate'] . " H:i:s");
    }
}