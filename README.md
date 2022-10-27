# community-data-scraper
This project was built using PHP 8.1 and MySQL running on a M1 chip Mac, and not tested on other software. Feel free to use this software to download community records for Douglas County Colorado.

While PHP is good for classes, the scraper functionality was built using a bunch of functions and only the DBConnection utilizes a class based approach.

To use this project:
1) Edit DBConnection.php to contain your DB credentials. 
2) Go to http://localhost:8080/setup/index.php
3) Enter the year, community name, and streets you want to get information for, then click "Start Scraping"
4) Wait for the process to finish.

Since the records are saved after the first run, you can wipe the DB and rerun much faster if needed.