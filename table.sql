/* This table definition is loaded and then executed when the OpenEMR interface's install button is clicked. */

/* Adds column to patient_data table to hold permission status for text notifications. As written, this defaults
 * to 'YES', which works for me as I craft my messages not to hold protected health information. You may
 * wish to consider this carefully. */
ALTER TABLE `patient_data` ADD COLUMN `allow_sms_reminders` VARCHAR(3) NOT NULL DEFAULT 'YES';

/* Next three statements add a custom yesno_default_yes list to the available lists - this forces the
 * default setting to 'YES', which was desired in my case. Be careful with this, as with it set to yes
 * you are effectively stating that the patient has agreed to accept text messages, which could create
 * problems, especially if protected health information is sent unsecurely or if texting is used
 * aggressively. It may be advisable to include an opt-out option in your message in order to allow
 * patients to decline text reminders. */
INSERT INTO list_options (list_id, option_id, title, seq, is_default, option_value, mapping, notes,
    codes, toggle_setting_1, toggle_setting_2, activity, subtype, edit_options) VALUES
    ('lists', 'yesno_default_yes', 'Yes/No (default yes)', 306, 1, 0, '', NULL, '', 0, 0, 1, '', 1);
INSERT INTO list_options (list_id, option_id, title, seq, is_default, option_value, mapping, notes,
    codes, toggle_setting_1, toggle_setting_2, activity, subtype) VALUES
    ('yesno_default_yes','YES','YES','2','1','0','','Y','','0','0','1','');
INSERT INTO list_options (list_id, option_id, title, seq, is_default, option_value, mapping, notes,
    codes, toggle_setting_1, toggle_setting_2, activity, subtype) VALUES
    ('yesno_default_yes','NO','NO','1','0','0','','N','','0','0','1','');

/* Next two statements add 'Allow SMS Reminders' dropdown box to end of Demographics>Choices section. Use
 * these on a per patient basis to override the default behavior (which is to allow texting) if desired. */
SET @newseq = (SELECT MAX(seq)+1 FROM layout_options WHERE form_id = 'DEM' AND group_id = 3);
INSERT INTO layout_options (form_id, source, field_id, title, group_id, seq, uor, fld_length, fld_rows,
    titlecols, datacols, data_type, edit_options, default_value, codes, description, max_length, list_id, list_backup_id) VALUES
    ('DEM','F','allow_sms_reminders','Allow SMS Reminders', 3, (SELECT @newseq), 1, 0, 0, 1, 1, 1,'','','','Allow SMS reminders?', 0,'yesno_default_yes','');

/* Flags any new appointment event with a 'NO' by default, thereby indicating that a text reminder
 * will be sent (if patient_data.allow_sms_reminders is set to 'YES' for the patient). */
ALTER TABLE openemr_postcalendar_events MODIFY COLUMN pc_sendalertsms VARCHAR(3) NOT NULL DEFAULT 'NO';

/* For some reason, from the entire list of entries in this table these are the only two whose
 * entries are capitalized. */
UPDATE list_options SET option_id = 'spanish' WHERE option_id = 'Spanish';
UPDATE list_options SET option_id = 'english' WHERE option_id = 'English';

/* Create a table in which to hold the messages (and two deletable sample messages). */
CREATE TABLE IF NOT EXISTS `mod_custom_sms_reminders` (`id` INT(11) NOT NULL AUTO_INCREMENT,`language` VARCHAR(24) NOT NULL,
    `message` VARCHAR(256) NOT NULL, `activity` BOOLEAN NOT NULL DEFAULT '1', PRIMARY KEY (`id`), INDEX (`language`));
INSERT INTO `mod_custom_sms_reminders` SET language = 'English', message = '***INITS*** has an appointment with ***PROVIDER*** ***DATE*** at ***STARTTIME***. Please phone XXX-XXXX for questions or cancellations.',
    activity = '1';
INSERT INTO `mod_custom_sms_reminders` SET language = 'Spanish', message = '***INITS*** tiene una cita con el ***PROVIDER*** ***DATE*** a la(s) ***STARTTIME***. Por favor llame XXX-XXXX si tiene preguntas or necesita cancelar.',
    activity = '1';
