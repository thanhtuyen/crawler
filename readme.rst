###################
Web crawler
###################

This is crawler to export data from jobbkk.com

*******************
How to use?
*******************

- Run the command: php index.php crawler run
 This command to start the process get data from jobbkk.com


**************************
Config file
**************************

Filename: config.txt in root path

limitPerRequest = 1000 // the limit request per run
username = reeracoen // username to login
password = satoshiyoshida // password
csvFolder = storage // the folder to save the csv file
logMode = 1 // Log mode: 1 save log to the same file in the same folder with csv file, if log = 0 don't write to log file

*******************
Server Requirements
*******************

PHP version 5.4 or newer is recommended.

************
Installation
************

- chmod storage folder to 777 (This is folder to save the CSV file)
- chmod tmp folder to 777
