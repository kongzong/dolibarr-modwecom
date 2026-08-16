# Database upgrades

Scripts in this directory migrate an existing installation from one module
version to the next. Fresh installs do NOT need them: the base files in
`sql/` always create the final schema.

Apply with the mysql/mariadb client of your Dolibarr instance, e.g.:

    mysql -u <dbuser> -p <dbname> < upgrade_0.1.0_to_0.2.0.sql

Run them in order if you skip several versions. Always take a backup first.
