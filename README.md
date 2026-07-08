# TimeEdit → Moodle Usergroup Synchronisation

A Moodle local plugin that synchronises course usergroups with an external timetabling system.

The API payload is treated as the **single source of truth**. Rather than modifying Moodle groups directly, external systems submit the complete desired membership for a course. The plugin stores this payload and a scheduled task reconciles Moodle to match it.

---

## What it does

### 1) Usergroup synchronisation flow (Scheduled Task)

High-level steps performed by the scheduled task (`process_usergroups`):

1. **Fetch unprocessed synchronisation records**
    - Retrieves all records from the sync table where:
        - `is_processed = 0`
    - Each record represents the latest API payload received for a course.

2. **Load existing Moodle state**
    - Retrieves:
        - Course records
        - Existing Moodle usergroup memberships
    - Organises memberships by course for efficient comparison.

3. **Build desired state**
    - Decodes the stored JSON payload.
    - Builds the complete set of usergroup memberships that should exist according to the external system.

4. **Compare desired state vs current state**
    - Calculates:
        - memberships to create
        - memberships to remove
    - The incoming API payload is always considered the source of truth.

5. **Process deletions**
    - Removes users from groups that are no longer present in the payload.

6. **Process creations**
    - Creates any missing Moodle groups (if required).
    - Adds users to the appropriate groups.

7. **Mark payload as processed**
    - Once reconciliation completes successfully, the payload is marked as processed.
    - Prevents the same payload from being processed multiple times.

8. **Logging and error handling**
    - Progress is written to the scheduled task trace.
    - Missing courses, invalid users and processing failures are reported without affecting other courses.

---

### 2) External API flow (`sync_usergroup_users`)

The plugin exposes a web service that accepts the complete usergroup membership for one or more courses.

Purpose:

- Receive the latest membership data from the external timetabling system.
- Store the payload for later processing.
- Treat each payload as the authoritative state for that course.

Flow:

1. **Validate request**
    - Validates incoming parameters.
    - Ensures required course and group information exists.

2. **Normalise payload**
    - Converts incoming data into a consistent internal structure.

3. **Store payload**
    - Saves the complete payload as JSON.
    - Generates a payload hash.
    - Marks the record as unprocessed.

4. **Return response**
    - Confirms the payload has been accepted.
    - No Moodle groups are modified during the API request itself.

---

### 3) User enrolment recovery

The plugin also listens for Moodle enrolment events.

When a user is newly enrolled onto a course:

1. A Moodle enrolment event is fired.
2. An adhoc task is queued.
3. The task checks the latest synchronised usergroup data.
4. Any groups that the user should belong to are restored automatically.

This ensures users receive the correct group memberships even if they enrol after the original synchronisation occurred.

---

## Scheduled task behaviour

The scheduled task is responsible for keeping Moodle aligned with the external system.

Execution characteristics:

- Runs via Moodle cron.
- Processes only unprocessed API payloads.
- Performs differential synchronisation (create/delete only where necessary).
- Uses the API payload as the authoritative state.
- Safe to rerun if processing is interrupted.

---

## Tech stack

- PHP (Moodle plugin architecture)
- Moodle 4.x APIs
- Moodle Scheduled Tasks
- Moodle Adhoc Tasks
- Moodle Events (user enrolment observer)
- Moodle Groups API
- External Web Services (`externallib.php`)

---

## Modules / dependencies

This plugin depends on:

- Moodle core APIs
- `local_obu_group_manager`

Ensure:

- Cron is running correctly.
- Web services are enabled.
- External systems submit complete usergroup membership payloads.
- Group management permissions are correctly configured.