Change Log | local_obu_timetable_usergroups
==========

v.1.4.0
-------
- Changing process for storing in queue table to only allow one instance of a courseidnumber and to update it with future calls for that number.
- Adding unique index for courseidnumber in queue table.

v.1.3.1
-------
- New API and scheduled task to automatically sync usergroups.

v.1.2.0
-------
- Table to hold module requesting re-sync
- Admin button (?) to re-sync modules
- API endpoint to retrieve modules wanting re-sync
- API endpoint to receive re-sync data

v.1.1.0
-------
- API endpoint to bulk add user groups
- API endpoint to bulk remove user groups