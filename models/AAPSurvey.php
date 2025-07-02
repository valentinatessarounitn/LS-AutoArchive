<?php

/**
 * Abstracted user model for Auto Archive admin view.
 * Incorporating an alternative seach method.
 *
 * @property integer $openSinceXMonths  Filter to retrieve surveys that have been open for more than X months
 * @property integer $expiredSinceXMonths Filter to retrieve surveys that have been expired for more than X months
 * @property integer $deactivatedSinceXMonths Filter to retrieve surveys that have been deactivated for more than X months
 * 
 * @inheritDoc
 */

class AAPSurvey extends Survey
{

    /**
     * @var integer $openSinceXMonths Filter to retrieve surveys that have been open for more than X months
     */
    public $openSinceXMonths = 0;


    /**
     * @var integer $expiredSinceXMonths Filter to retrieve surveys that have been expired for more than X months
     */
    public $expiredSinceXMonths = 0;


    /**
     * @var integer $deactivatedSinceXMonths Filter to retrieve surveys that have been deactivated for more than X months
     */
    public $deactivatedSinceXMonths = 0;



    /**
     * @inheritDoc
     */
    public static function model($className = __CLASS__)
    {
        /** @var self $model */
        $model = parent::model($className);
        return $model;
    }



    /** @inheritdoc */
    public function relations()
    {
        return array(
            'permissions' => array(self::HAS_MANY, 'Permission', array('entity_id' => 'sid')), //
            'languagesettings' => array(self::HAS_MANY, 'SurveyLanguageSetting', 'surveyls_survey_id', 'index' => 'surveyls_language'),
            'defaultlanguage' => array(self::BELONGS_TO, 'SurveyLanguageSetting', array('language' => 'surveyls_language', 'sid' => 'surveyls_survey_id')),
            'correct_relation_defaultlanguage' => array(self::HAS_ONE, 'SurveyLanguageSetting', array('surveyls_language' => 'language', 'surveyls_survey_id' => 'sid')),
            'owner' => array(self::BELONGS_TO, 'User', 'owner_id', ),
            'groups' => array(self::HAS_MANY, 'QuestionGroup', 'sid', 'order' => 'groups.group_order ASC', 'together' => true),
            'questions' => array(self::HAS_MANY, 'Question', 'sid', 'order' => 'questions.qid ASC'),
            'quotas' => array(self::HAS_MANY, 'Quota', 'sid', 'order' => 'name ASC'),
            'surveymenus' => array(self::HAS_MANY, 'Surveymenu', array('survey_id' => 'sid')),
            'surveygroup' => array(self::BELONGS_TO, 'SurveysGroups', array('gsid' => 'gsid')),
            'surveysettings' => array(self::BELONGS_TO, SurveysGroupsettings::class, array('gsid' => 'gsid')),
            'templateModel' => array(self::HAS_ONE, 'Template', array('name' => 'template')),
            'templateConfiguration' => array(self::HAS_ONE, 'TemplateConfiguration', array('sid' => 'sid'))
        );
    }



    /**
     * @inheritDoc
     * @return array
     */
    public function getColumns(): array
    {
        $columns = [
            [
                'id' => 'sid',
                'class' => 'CCheckBoxColumn',
                'selectableRows' => '100',
                'headerHtmlOptions' => ['class' => 'ls-sticky-column'],
                'htmlOptions' => ['class' => 'ls-sticky-column']
            ],
            [
                'header' => gettext('Survey ID'),
                'name' => 'survey_id',
                'value' => '$data->sid',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],
            [
                'header' => gettext('Status'),
                'name' => 'running',
                'value' => '$data->running',
                'type' => 'raw',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],
            [
                'header' => gettext('Title'),
                'name' => 'title',
                'value' => '$data->defaultlanguage->surveyls_title ?? null',
                'htmlOptions' => ['class' => 'has-link'],
                'headerHtmlOptions' => ['class' => 'text-nowrap'],
            ],
            [
                'header' => gettext('Created'),
                'name' => 'creation_date',
                'value' => '$data->creationdate',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],
            [
                'header' => gettext('Responses'),
                'name' => 'responses',
                'value' => '$data->countFullAnswers',
                'headerHtmlOptions' => ['class' => 'd-md-none d-lg-table-cell'],
                'htmlOptions' => ['class' => 'd-md-none d-lg-table-cell has-link'],
            ],
            //expires
            [
                'header' => gettext('Expiry date'),
                'name' => 'expires',
                'value' => 'AAPHelper::convertData($data->expires)',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],
            // startdate
            [
                'header' => gettext('Start date'),
                'name' => 'startdate',
                'value' => 'AAPHelper::convertData($data->startdate)',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],
            // data effettiva di creazione della tabella delle risposte survey_ID
            [
                'header' => gettext('Activation date'),
                'name' => 'activationdate',
                'value' => 'AAPDatabaseHelper::getSurveyActivationDate($data->sid)',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],
            // data effettiva di creazione della tabella delle risposte old_survey_ID
            [
                'header' => gettext('Deactivation date'),
                'name' => 'deactivationdate',
                'value' => 'AAPDatabaseHelper::getSurveyDeactivationDate($data->sid)',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],
            // data dell'ultimo sollecito per expiration (per la schemata openSurveysMsg)
            // data calcolata in base al contenuto della tabella {{autoarchive}}
            [
                'header' => gettext('Last warning for expiration'),
                'name' => 'lastwarningexpiration',
                'value' => 'AAPDatabaseHelper::getSurveyLastWarningExpirationDate($data->sid)',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],
            // data dell'ultimo sollecito per deactivation (per la schemata expiredSurveysDeactivation)
            // data calcolata in base al contenuto della tabella {{autoarchive}}
            [
                'header' => gettext('Last warning for deactivation'),
                'name' => 'lastwarningdeactivation',
                'value' => 'AAPDatabaseHelper::getSurveyLastWarningDeactivationDate($data->sid)',
                'headerHtmlOptions' => ['class' => 'd-none d-sm-table-cell text-nowrap'],
                'htmlOptions' => ['class' => 'd-none d-sm-table-cell has-link'],
            ],

        ];
        return $columns;
    }

    /**
     * Search
     *
     * $options = [
     *  'pageSize' => 10,
     *  'currentPage' => 1
     * ];
     *
     * @param array $options
     * @return CActiveDataProvider
     */
    public function search($options = [])
    {
        $options = $options ?? [];
        // Flush cache to get proper counts for partial/complete/total responses
        if (method_exists(Yii::app()->cache, 'flush')) {
            Yii::app()->cache->flush();
        }
        $pagination = [
            'pageSize' => Yii::app()->user->getState(
                'pageSize',
                Yii::app()->params['defaultPageSize']
            )
        ];
        if (isset($options['pageSize'])) {
            $pagination['pageSize'] = $options['pageSize'];
        }
        if (isset($options['currentPage'])) {
            $pagination['currentPage'] = $options['currentPage'];
        }

        $sort = new CSort();
        $sort->attributes = array(
            'survey_id' => array(
                'asc' => 't.sid asc',
                'desc' => 't.sid desc',
            ),
            'title' => array(
                'asc' => 'correct_relation_defaultlanguage.surveyls_title asc',
                'desc' => 'correct_relation_defaultlanguage.surveyls_title desc',
            ),

            'creation_date' => array(
                'asc' => 't.datecreated asc',
                'desc' => 't.datecreated desc',
            ),

            'owner' => array(
                'asc' => 'owner.users_name asc',
                'desc' => 'owner.users_name desc',
            ),
            'running' => array(
                'asc' => 't.active asc, t.expires asc',
                'desc' => 't.active desc, t.expires desc',
            ),

            'group' => array(
                'asc' => 'surveygroup.title asc',
                'desc' => 'surveygroup.title desc',
            ),

        );
        $sort->defaultOrder = array('creation_date' => CSort::SORT_DESC);

        $criteria = new LSDbCriteria();
        $aWithRelations = array('correct_relation_defaultlanguage');

        // Search filter
        $sid_reference = (Yii::app()->db->getDriverName() == 'pgsql' ? ' t.sid::varchar' : 't.sid');
        $aWithRelations[] = 'owner';
        $aWithRelations[] = 'surveygroup';
        $criteria->compare($sid_reference, $this->searched_value, true);
        $criteria->compare('t.admin', $this->searched_value, true, 'OR');
        $criteria->compare('owner.users_name', $this->searched_value, true, 'OR');
        $criteria->compare('correct_relation_defaultlanguage.surveyls_title', $this->searched_value, true, 'OR');
        $criteria->compare('surveygroup.title', $this->searched_value, true, 'OR');

        // Survey group filter
        if (isset($this->gsid)) {
            // The survey group filter (from the dropdown, not by title search) is applied to five levels of survey groups.
            // That is, it matches the group the survey is in, the parent group of that group, and the "grandparent" group, etc.
            $groupJoins = 'LEFT JOIN {{surveys_groups}} parentGroup1 ON t.gsid = parentGroup1.gsid ';
            $groupJoins .= 'LEFT JOIN {{surveys_groups}} parentGroup2 ON parentGroup1.parent_id = parentGroup2.gsid ';
            $groupJoins .= 'LEFT JOIN {{surveys_groups}} parentGroup3 ON parentGroup2.parent_id = parentGroup3.gsid ';
            $groupJoins .= 'LEFT JOIN {{surveys_groups}} parentGroup4 ON parentGroup3.parent_id = parentGroup4.gsid ';
            $groupJoins .= 'LEFT JOIN {{surveys_groups}} parentGroup5 ON parentGroup4.parent_id = parentGroup5.gsid ';
            $criteria->mergeWith([
                'join' => $groupJoins,
            ]);
            $groupCondition = "t.gsid=:gsid";
            $groupCondition .= " OR parentGroup2.gsid=:gsid2"; // MSSQL issue with single param for multiple value, issue #19072
            $groupCondition .= " OR parentGroup3.gsid=:gsid3";
            $groupCondition .= " OR parentGroup4.gsid=:gsid4";
            $groupCondition .= " OR parentGroup5.gsid=:gsid5";
            $criteria->addCondition($groupCondition, 'AND');
            $criteria->params = array_merge(
                $criteria->params,
                [
                    ':gsid' => $this->gsid,
                    ':gsid2' => $this->gsid,
                    ':gsid3' => $this->gsid,
                    ':gsid4' => $this->gsid,
                    ':gsid5' => $this->gsid
                ]
            );
        }

        // Active filter
        if (isset($this->active)) {
            if ($this->active == 'N' || $this->active == "Y") {
                $criteria->compare("t.active", $this->active, false);
            } else {
                // Time adjust
                $sNow = date("Y-m-d H:i:s", strtotime((string) Yii::app()->getConfig('timeadjust'), strtotime(date("Y-m-d H:i:s"))));

                if ($this->active == "E") {
                    $criteria->compare("t.active", 'Y');
                    $criteria->addCondition("t.expires <'$sNow'");
                }
                if ($this->active == "S") {
                    $criteria->compare("t.active", 'Y');
                    $criteria->addCondition("t.startdate >'$sNow'");
                }

                // Filter for surveys that are running now
                // Must be active, started and not expired
                if ($this->active == "R") {
                    $criteria->compare("t.active", 'Y');
                    $startedCriteria = new CDbCriteria();
                    $startedCriteria->addCondition("'{$sNow}' > t.startdate");
                    $startedCriteria->addCondition('t.startdate IS NULL', "OR");
                    $notExpiredCriteria = new CDbCriteria();
                    $notExpiredCriteria->addCondition("'{$sNow}' < t.expires");
                    $notExpiredCriteria->addCondition('t.expires IS NULL', "OR");
                    $criteria->mergeWith($startedCriteria);
                    $criteria->mergeWith($notExpiredCriteria);
                }
            }
        }

        // Openduration filter
        if (isset($this->openSinceXMonths) && $this->openSinceXMonths > 0) {

            //Yii::log('search message inside: ' . print_r($this->openSinceXMonths, true), CLogger::LEVEL_INFO);

            $surveyIds = $this->getSurveyIdsWithActivationDateOlderThan($this->openSinceXMonths);
            if (!empty($surveyIds)) {
                $criteria->addInCondition('t.sid', $surveyIds);
            } else {
                // Se nessun sondaggio soddisfa il criterio, forziamo una condizione impossibile
                $criteria->addCondition('1=0');
            }
        }

        // Expirationduration filter
        if (isset($this->expiredSinceXMonths) && $this->expiredSinceXMonths > 0) {

            // Applica un filtro per visualizzare solo i sondaggi la cui data di expiration è scaduta da almeno X mesi
            $dateXMonthsAgo = (new DateTime())->modify("-{$this->expiredSinceXMonths} months")->format('Y-m-d H:i:s');
            $criteria->addCondition("t.expires IS NOT NULL"); // Assicura che il campo expires non sia nullo
            $criteria->addCondition("t.expires < :dateXMonthsAgo"); // Verifica che la data di expiration sia più vecchia di X mesi
            $criteria->params[':dateXMonthsAgo'] = $dateXMonthsAgo;

            //Yii::log('search expiredSinceXMonths: ' . print_r($this->expiredSinceXMonths, true), CLogger::LEVEL_INFO);
            //Yii::log('search expiredSinceXMonths: ' . print_r($dateXMonthsAgo, true), CLogger::LEVEL_INFO);
        }

        // Expirationduration filter
        if (isset($this->deactivatedSinceXMonths) && $this->deactivatedSinceXMonths > 0) {

            //Yii::log('search deactivatedSinceXMonths: ' . print_r($this->deactivatedSinceXMonths, true), CLogger::LEVEL_INFO);

            $surveyIds = $this->getSurveyIdsWithDeactivationDateOlderThan($this->deactivatedSinceXMonths);
            if (!empty($surveyIds)) {
                $criteria->addInCondition('t.sid', $surveyIds);
            } else {
                // Se nessun sondaggio soddisfa il criterio, forziamo una condizione impossibile
                $criteria->addCondition('1=0');
            }
        }

        $criteria->with = $aWithRelations;

        // Permission
        $criteriaPerm = self::getPermissionCriteria();
        $criteria->mergeWith($criteriaPerm, 'AND');
        // $criteria->addCondition("t.blabla == 'blub'");
        $dataProvider = new CActiveDataProvider('Survey', array(
            'sort' => $sort,
            'criteria' => $criteria,
            'pagination' => $pagination,
        ));

        $dataProvider->setTotalItemCount($this->count($criteria));

        return $dataProvider;
    }

    /**
     * Recupera gli ID dei sondaggi la cui data di attivazione è più vecchia di un certo numero di mesi.
     *
     * @param int $months Numero di mesi.
     * @return array Lista di ID dei sondaggi.
     */
    private function getSurveyIdsWithActivationDateOlderThan(int $months): array
    {
        $surveyIds = [];
        $allSurveys = self::model()->findAll(); // Recupera tutti i sondaggi
        foreach ($allSurveys as $survey) {
            $activationDate = AAPDatabaseHelper::getSurveyActivationDate($survey->sid);

            if ($activationDate) {
                $monthsDifference = (new DateTime())->diff(new DateTime($activationDate))->m + 
                                    (new DateTime())->diff(new DateTime($activationDate))->y * 12;

                // Yii::log('getSurveyIdsWithActivationDateOlderThan message: ' . print_r($survey->sid . ' + ' . $activationDate . '. Show if ' . $monthsDifference . ' >= ' .$months, true), CLogger::LEVEL_INFO);              
                if ($monthsDifference >= $months) {
                    $surveyIds[] = $survey->sid;
                }
            }
        }
        return $surveyIds;
    }

    /**
     * Recupera gli ID dei sondaggi la cui data di disattivazione è più vecchia di un certo numero di mesi.
     *
     * @param int $months Numero di mesi.
     * @return array Lista di ID dei sondaggi.
     */
    private function getSurveyIdsWithDeactivationDateOlderThan(int $months): array
    {
        $surveyIds = [];
        $allSurveys = self::model()->findAll(); // Recupera tutti i sondaggi
        foreach ($allSurveys as $survey) {
            $deactivationDate = AAPDatabaseHelper::getSurveyDeactivationDate($survey->sid);

            if ($deactivationDate) {
                $monthsDifference = (new DateTime())->diff(new DateTime($deactivationDate))->m + 
                                    (new DateTime())->diff(new DateTime($deactivationDate))->y * 12;

                // Yii::log('getSurveyIdsWithDeactivationDateOlderThan message: ' . print_r($survey->sid . ' + ' . $deactivationDate . '. Show if ' . $monthsDifference . ' >= ' .$months, true), CLogger::LEVEL_INFO);              
                if ($monthsDifference >= $months) {
                    $surveyIds[] = $survey->sid;
                }
            }
        }
        return $surveyIds;
    }
}
