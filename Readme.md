###Goals:###
Primarily, this project started out as a learning experience based upon Stephen Nielson's adunsulag/oe-module-custom-skeleton project. There may well be more efficient ways to accomplish this function, however this has at least a certain ease of installation and certain flexibilities that make it attractive, at least to me. The primary goal was to create a easily administered functionality to streamline automatic sms messaging of reminders to patients with upcoming appointments. Obviously, keeping costs down is always a goal as well. Finally, my specfic practice has a large fraction of Spanish-speaking patients, so I needed to be able to relatively seamlessly be able to send texts in at least one additional language (but preferably be able to add different and/or more numerous languages as various needs dictated). Largely, this has all been accomplished. 

###Requirements:###
First, unless one has a cellular modem available and doesn't mind re-writing some of this code to utilize it (and is furthermore paying to keep a cellular line dedicated to this process) the code as written requires establishing a paid relationship with textbelt at www.textbelt.com. Bought in modest bulk, sms messages can be sent (in the USA) for approximately $.01 per message. 

Second, my office works off an Ubuntu server. At least some minimal comfort with the command line will be necessary to set things up properly. This module also works on my Mac and I presume can be modified somehow through the Task Scheduler to work on a Windows server.

A few minor changes to the OpenEMR database are necessary. If you are interested, these may be reviewed by examining the table.sql file included herein. None of them should interfere with a stock installation of OpenEMR and, furthermore, they are unlikely to interfere with whatever customizations you might already have in place.

I've included some sample messages for your experimentation. You'll probably want to write some of your own, however. Think carefully about how you might want to do this. There are rules in this country pertaining to what type of information can be released over an insecure medium (such as texting). Since I have set my defaults to allow ALL patients to receive reminder texts (this can be overridden on a case by case basis if needed) I generally try to write fairly short and bland messages that contain little more than the patient's initials and the date/time of the upcoming visit.

The php CURL module is required to properly interface with textbelt (there are other methods as well - please consult the textbelt.com website and consider re-writing sms_textbelt.php if needed). I believe that this was already installed on my Ubuntu server. If not, it can be easily installed with the following:

```sudo apt-get install php-curl```

###Installation:###
From the command line, navigate to the openemr directory and enter the following: 
```$ composer require mouse55/oe-module-custom-sms-reminders```

Following this, log on to OpenEMR as an admiministrative user and navigate to Modules>Manage Modules>Unregistered. Click on Register then from the Registered tab click on Install followed by Enable.

At this point navigate to Admin>Globals>SMS Reminders. Ensure that the service is enabled and enter the Gateway API Key you obtained from textbelt.com.
For testing purposes you can utilize 'textbelt' as a key (but it will limit you to sending one text per day). Alternatively, you can append '_test' to a "real" paid-for key and the system will run to completion but no texts will actually be transmitted (which limits its testing utility somewhat). Finally, fill a suitable number of hours before the appointment at which the message should be sent into the SMS Send Before Hours and then Save.

Now navigate to Admin>Custom SMS Reminders and set up a few messages. This page is modeled after the Admin>Coding>Codes page and should be at least somewhat familiar. I've included two sample messages that can be used, edited or simply deleted and replaced. Please note that "Active" messages cannot be directly edited. They need to be inactivated and saved (updated) as such first. After you are finished editing them they can be reactivated. Also, any number of messages can be saved, however there can be only one active message per language.

Finally, you'll have to edit the crontab file to instruct the system to send out messages to eligible recipients according to your desired schedule. For this, enter 

``` sudo nano /etc/crontab ``` 

at the command prompt and append the following line to the end of the file:

``` 30 08-19 * * * root /usr/bin/php /var/www/html/openemr/interface/modules/custom_modules/oe-module-custom-sms-reminders/public/cron_textbelt_reminders.php ```

By way of example, this would direct cron to automatically run the cron_textbelt_reminders.php file on the half-hours from 07:30 AM to 19:30 PM daily. Setting the SMS Send Before Hours parameter to 48, also by way of example, would direct the system to send these messages no more than 48 hours prior to the scheduled appointment time.

If your php executable is in a different location you will have to run 

``` whereis php ```

to locate it and substitute the result for /usr/bin/php in the crontab file. Also, as written above the cron_textbelt_reminders.php file will be run as the root user. It is probably possible to run it as another non-administrative user however in that case it _may_ be necessary to adjust the read permissions of the file.

###Random Thoughts:###
The choice of a language in which a message is to be sent is made by the language that is selected for the patient in Demographics>Stats. It should default to English if no language is selected.

In case you didn't notice, an additional field ("Allow SMS Reminders") is added to end bottom of Demographics>Choices. If a patient objects to receiving text messages they may be individually excluded from the service simply by setting this to "NO" for that patient.

If you want to turn off the service temporarily uncheck "Enable SMS Service Reminders" in Admin>Globals>SMS Reminders. If you want to get rid of the service entirely simply uninstall the module (although in this case the changes to the database will persist and therefore there will still be the above visible change in Demographics>Choices (it just won't have any effect).