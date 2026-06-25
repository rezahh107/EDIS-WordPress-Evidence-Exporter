# Migration from EDIS 3.1.0 to 3.2.0

1. Back up the site and database.
2. Deactivate 3.1.0 without uninstalling it.
3. Install the 3.2.0 package using the same plugin slug.
4. Create new jobs; queued/running 3.1.0 jobs are not resumable.
5. Run Diagnostics and the Safe Worker Test.

Version 3.2 introduced the bounded authenticated REST worker, Browser Bridge Context, source truth/availability separation, source coverage and the coordinated WordPress Bundle Schema 2.x family. WP-Cron remains a recovery mechanism rather than the sole executor.
