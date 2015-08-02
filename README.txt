Provides a logger and viewer for the user registration.

Overview
--------
This module records the user registration information taken from the tables
of watchdog and else.  After some of the old data in the watchdog are expired
and deleted, the information will be kept in the table belonging to this module.
It also provides the interface to browse the log.

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

