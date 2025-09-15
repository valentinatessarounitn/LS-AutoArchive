<?php

/**
 * All constants for AutoArchive plugin
 */

class AAPConstants
{
    //-----------------------------------------------------//
    // Settings
    //-----------------------------------------------------//


    // Il numero massimo di mesi che un sondaggio può rimanere aperto. Dopodichè sarà interrotto.
    // The maximum number of months a survey can remain open. After that, it will be expired.
    const DEFAULT_MAX_SURVEY_OPEN = 13;

    // Il numero massimo di mesi che un sondaggio può rimanere interrotto. Dopodichè sarà disattivato.
    // The maximum number of months a survey can remain expired. After that, it will be deactivated.
    const DEFAULT_MAX_SURVEY_EXPIRATION = 6;

    // Il numero massimo di mesi dopo i quali la struttura dei sondaggi disattivati verrà eliminata.
    // The maximum number of months after which the structure of deactivated surveys will be deleted.
    const DEFAULT_MAX_SURVEY_STRUCTURE_DELETION = 6;

    // Il numero massimo di mesi dopo i quali l'archivio delle risposte dei sondaggi disattivati verrà eliminata.
    // The maximum number of months after which the archive of responses from deactivated surveys will be deleted.
    const DEFAULT_MAX_SURVEY_RESPONSE_DELETION = 6;

    // The number of months from the opening of a survey after which the system generates a warning message to 
    // communicate the scheduled expiration after DEFAULT_MAX_SURVEY_OPEN months from the survey's opening.
    // Il numero di mesi dall'apertura di un sondaggio dopo cui il sistema genera un messaggio di avviso per 
    // comunicare l'interruzione programmata dopo DEFAULT_MAX_SURVEY_OPEN mesi dall'apertura del sondaggio. 
    // Constraint: 0 < DEFAULT_WARNING_EXPIRATION_MONTHS < DEFAULT_MAX_SURVEY_OPEN
    const DEFAULT_WARNING_EXPIRATION_MONTHS = 12;

    // The number of months from the expiration of a survey after which the system generates a warning message to 
    // communicate the scheduled deactivation after DEFAULT_MAX_SURVEY_EXPIRATION months from the survey's expiration.
    // Il numero di mesi dall'interruzione di un sondaggio dopo cui il sistema genera un messaggio di avviso per 
    // comunicare la disattivazione programmata dopo DEFAULT_MAX_SURVEY_EXPIRATION mesi dall'interruzione del sondaggio. 
    // Constraint: 0 < DEFAULT_WARNING_DEACTIVATION_MONTHS < DEFAULT_MAX_SURVEY_EXPIRATION
    const DEFAULT_WARNING_DEACTIVATION_MONTHS = 5;


    //-----------------------------------------------------//
    //  operation codes used in the autoarchive log table
    //-----------------------------------------------------//

    /**
     * OP_MSGOPEN — Notification of upcoming survey expiration sent to the survey owner.
     * This is a proactive alert, not an actual expiration.
     */
    public const OP_MSGOPEN = 'MSGOPEN';

    /**
     * OP_EXP — Forced expiration of an active survey.
     * This operation closes the survey and prevents further responses.
     */
    public const OP_EXP = 'EXP';

    // OP_MSGEXP — Notification of upcoming survey deactivation sent to the survey owner.
    public const OP_MSGEXP = 'MSGEXP';

    // OP_DEA — Survey deactivation.
    public const OP_DEA = 'DEA';

    /**
     * OP_DELANS — Deletion of archived survey responses.
     * The survey itself remains intact, but old response tables are removed.
     */
    public const OP_DELANS = 'DELANS';

    /**
     * OP_DELALL — Full deletion of the survey.
     * Includes metadata, structure, responses, and all related data.
     */
    public const OP_DELALL = 'DELALL';


    //-----------------------------------------------------//
    // Email messages
    //-----------------------------------------------------//


    const AAP_OWNERNAME = '{OWNERNAME}';
    const AAP_SURVEYID = '{SURVEYID}';
    const AAP_SURVEYTITLE = '{SURVEYTITLE}';
    const AAP_SURVEYURL = '{SURVEYURL}';
    const AAP_ADMINNAME = '{ADMINNAME}';
    const AAP_ADMINEMAIL = '{ADMINEMAIL}';

    const AAP_KEYS = [
        self::AAP_OWNERNAME,
        self::AAP_SURVEYID,
        self::AAP_SURVEYTITLE,
        self::AAP_SURVEYURL,
        self::AAP_ADMINNAME,
        self::AAP_ADMINEMAIL,
    ];

    const AAP_PLACEHOLDERS = '' . self::AAP_OWNERNAME . ', ' . self::AAP_SURVEYID . ', ' . self::AAP_SURVEYTITLE . ', '
        . self::AAP_SURVEYURL . ', ' . self::AAP_ADMINNAME . ', ' . self::AAP_ADMINEMAIL;


    // openSurveysMsg
    const OPEN_SURVEYS_MSG_HEADER = 'Limesurvey expiration : {SURVEYID} {SURVEYTITLE}';
    const OPEN_SURVEYS_MSG_BODY = "<p>Dear {OWNERNAME},</p> 
<p>the following survey will be expired soon:</p>
<p>{SURVEYID} {SURVEYTITLE}</p>

<p>Sincerely,</p>
<p>{ADMINNAME} ({ADMINEMAIL})<br />----------------------------------------------<br />
Click here for the survey:<br />{SURVEYURL}</p>";


    // expiredSurveysMsg
    const EXPIRED_SURVEYS_MSG_HEADER = 'Limesurvey deactivation : {SURVEYID} {SURVEYTITLE}';
    const EXPIRED_SURVEYS_MSG_BODY = "<p>Dear {OWNERNAME},</p>
<p>the following survey will be deactivated soon:</p>
<p>{SURVEYID} {SURVEYTITLE}</p>
<p>Sincerely,</p>
<p>{ADMINNAME} ({ADMINEMAIL})<br />----------------------------------------------<br />
Click here for the survey:<br />{SURVEYURL}</p>";

}