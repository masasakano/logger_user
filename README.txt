Provides a logger and viewer for the user registration.

Overview
--------
This module records the user registration information taken from the tables
of watchdog and else on its own table: logger_user_users.
After some of the old data in the watchdog are expired and deleted,
the information will be kept in the table.
It also provides the interface to browse the table (aka, log)
accessible by only administrator(s).

Every time an admin browses the log, this module updates the table, following
the change in the other standard tables: users, watchdog, users_roles .
Also, this module runs the cron job to update the table.
The results of the updates, or mor precisely speaking, the number of
modified rows in each of the cases of update, insert etc, are recorded
in the dblog with the type of 'logger_user' and can be browsed via
/admin/reports/dblog

In updates, the column 'created', which is the time an account was created,
would unchange in our table database, even if it had changed in users table;
anyway a change in the column 'created' should not happen
in normal circumstances.  If such a case is detected, a warning is issued.

An important note is that once you uninstall this module,
the associated table in the database will be deleted, too,
hence the record kept on the table will be lost permanently,
unless they can be retrieved from watchdog table again.
Be warned.

Note if you filter the log with "All", that is, no filter,
the first column in each row is the hyperlink to the watchdog event.

Install
-------
Basically, follow the standard procedure.
Make sure the following modules this module depends on are
(installed and) enabled beforehand.

They are in short,
- dblog

Place this directory (migrate_goo) into your user module directory, usually:
    /YOUR_DRUPAL_ROOT/sites/all/modules/
preserving the directory structure.
Make sure to rename the filenames so the suffix '.php' is deleted.

Then enable the module via /admin/modules or drush
    % drush en logger_user

Usage
-----
Open /admin/reports/logger_user
which is accessible via the admin menu.

Known issues
------------
- None.

Disclaimer
----------
Please use it at your own risk.

Acknowledgements 
----------------
Drupal community.

Authors
-------
Masa Sakano - http://www.drupal.org/user/3022767

