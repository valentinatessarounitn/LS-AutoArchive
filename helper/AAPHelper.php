<?php
/**
 * Helper functionalities for AutoArchive plugin
 */
class AAPHelper
{

    private static $format = 'Y-m-d H:i:s';


    /**
     * Get the status of the plugin from db
     *
     * @return boolean
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



    // Metodo che prende in input una data in formato stringa e la converte in un oggetto DateTime. 
    // Per esempio la stringa 2025-03-31 09:22:38 o 2025-03-25 14:25:19
    // Se la stringa non è nel formato corretto, restituisce false
    public static function stringToDateTime($string)
    {
        $date = DateTime::createFromFormat(self::$format, $string);
        if ($date === false) {
            return 'conversion from string to DateTime failed';
        }
        return $date;
    }

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
     * @param string $string The input string containing placeholders to be replaced.
     * @param array $aReplacements An associative array where keys are placeholders to be replaced
     *                   and values are the replacement strings.
     * @return string The resulting string after all replacements have been made.
     */
    public static function replaceKeysInString($string, $aReplacements)
    {
        foreach ($aReplacements as $key => $value) {
            $string = str_replace($key, $value, $string);
        }
        return $string;
    }

    // From string AAAA-MM-GG HH:MM:SS to string session['dateformat']." H:i:s"
    // es. from 2025-04-09 12:27:30 to 09.04.2025 12:27:30
    // Usata su AAPSurvey.php
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