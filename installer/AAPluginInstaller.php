<?php
/**
 * Installer class for the Auto Archive Plugin
 * A collecton of static helpers to install the Plugin
 */
class AAPluginInstaller
{
    public static $instance = null;
    private static $table = '{{autoarchive}}';
    private $errors = [];

    /**
     * Singleton get Instance
     *
     * @return AAPluginInstaller
     */
    public static function instance()
    {
        if (self::$instance == null) {
            self::$instance = new AAPluginInstaller();
        }
        return self::$instance;
    }

    /**
     * Combined installation for all necessary options
     * 
     * @throws CHttpException
     * @return void
     */
    public function install()
    {
        try {
            $this->installTables();
        } catch (CHttpException $e) {
            $this->errors[] = $e;
        }

        if (count($this->errors) > 0) {
            throw new CHttpException(500, join(",\n", array_map(function ($oError) {
                return $oError->getMessage(); }, $this->errors)));
        }
    }

    /**
     * Combined uninstallation for all necessary options
     * 
     * @throws CHttpException
     * @return void
     */
    public function uninstall()
    {
        try {
            $this->uninstallTables();
        } catch (CHttpException $e) {
            $this->errors[] = $e;
        }

        if (count($this->errors) > 0) {
            throw new CHttpException(500, join(",\n", array_map(function ($oError) {
                return $oError->getMessage(); }, $this->errors)));
        }
    }

    /**
     * Install tables for the plugin
     * 
     * @throws CHttpException
     * @return boolean
     */
    public function installTables()
    {
        $oDB = Yii::app()->db;
    
        // Check if the table already exists
        $aTables = $oDB->createCommand("SHOW TABLES LIKE '" . self::$table . "'")->queryColumn();

        if (count($aTables) > 0) {
            // Table already exists, no need to create it again
            return true;
        }

        // Table does not exist, proceed with creation
        $oTransaction = $oDB->beginTransaction();
        try {
            $oDB->createCommand()->createTable(self::$table, array(
                'id' => "pk",

                'operation_date' => 'datetime NOT NULL', //Date of the operation 
                'operation_type' => "ENUM('MSGOPEN', 'EXP', 'MSGEXP', 'DEA', 'DELANS', 'DELALL') NOT NULL", //Type of operation
                'operation_success' => 'boolean NOT NULL', //Operation was successfull yes or not
                'operation_info' => 'text NULL', //Message of the operation (error message) 

                'survey_id' => 'integer NOT NULL', //Survey id
                'survey_status' => 'string(15) NULL', //Status of the survey
                'owner_id' => 'integer NULL', //User id of the user a cui viene inviata l'email di notifica
                'owner_email' => 'string(255) NULL', //Email of the user a cui viene inviata l'email di notifica

                'created_date' => 'datetime NULL', //Date when the survey was created for first time
                'last_activation_date' => 'datetime NULL', //Date when the survey was last activated
                'last_expiration_date' => 'datetime NULL', //Date when the survey was last archived
                'last_deactivation_date' => 'datetime NULL', //Date when the survey was last deactivated

                'last_warning_expiration_date' => 'datetime NULL', // Date when the last warning for expiration was sent
                'last_warning_deactivation_date' => 'datetime NULL', // Date when the last warning for deactivation was sent
            ));
            
            $oTransaction->commit();
            return true;
        } catch (Exception $e) {
            $oTransaction->rollback();
            throw new CHttpException(500, $e->getMessage());
        }
    }

    /**
     * Uninstall tables for the plugin
     * 
     * @throws CHttpException
     * @return boolean
     */
    public function uninstallTables()
    {
        $oDB = Yii::app()->db;
        $oTransaction = $oDB->beginTransaction();
        try {
            $oDB->createCommand()->dropTable(self::$table);
            $oTransaction->commit();
            return true;
        } catch (Exception $e) {
            $oTransaction->rollback();
            throw new CHttpException(500, $e->getMessage());
        }
    }
}