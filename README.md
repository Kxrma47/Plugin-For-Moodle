# Poll Block for Moodle (Academic Voting & Scheduling Plugin-For-Moodle)

A Moodle block plugin that lets department managers create polls and time-slot schedules, and lets teachers/professors vote once per poll. Designed for academic use cases such as defense scheduling, meeting coordination, and quick department surveys.

## Highlights

* Role-aware UI: Manager console and Professor voting interface
* Poll types: Single choice and multiple choice
* Poll modes: Text options, fixed time slots, custom defense-style timeslots with breaks
* One-vote-per-user enforcement (configurable)
* Real-time statistics and Excel export
* Internationalization: English and Russian
* Secure: Moodle capabilities, CSRF protection, referential integrity

---

## Table of Contents

* [Architecture](#architecture)
* [File Structure](#file-structure)
* [Database Schema](#database-schema)
* [Capabilities and Roles](#capabilities-and-roles)
* [Installation](#installation)
* [Configuration](#configuration)
* [Usage](#usage)
* [AJAX API (Overview)](#ajax-api-overview)
* [Internationalization](#internationalization)
* [Security](#security)
* [Development](#development)
* [Roadmap](#roadmap)
* [License](#license)

---

## Architecture

* **Backend (PHP)**: Main block class renders role-specific UIs and handles secure actions. AJAX endpoints process create/delete/export/vote operations.
* **Frontend (AMD JavaScript)**: Separate modules for Manager and Professor UIs. Custom modals, form validation, toasts, and dynamic loading.
* **Styles (CSS)**: Moodle-friendly theme using CSS variables and responsive layout.
* **i18n**: Language strings in `lang/en` and `lang/ru`.

---

## File Structure

```
block_poll/
├── block_poll.php                # Main block class (role-based rendering)
├── ajax_handler.php              # AJAX endpoints (create/delete/vote/export)
├── version.php                   # Plugin metadata
├── settings.php                  # Admin configuration
├── db/
│   ├── install.xml               # DB schema (4 tables)
│   ├── access.php                # Capabilities (RBAC)
│   └── upgrade.php               # Migrations
├── amd/
│   └── src/
│       ├── Manager.js            # Manager UI logic
│       └── professor.js          # Professor UI logic
├── styles/
│   ├── Manager.css               # Manager styles
│   └── professor.css             # Professor styles
├── lang/
│   ├── en/block_poll.php         # English strings
│   └── ru/block_poll.php         # Russian strings

```

---

## Database Schema

Four core tables with proper foreign keys and cascade deletes:

* `block_poll_polls` – poll metadata (title, type, mode, start/end, active)
* `block_poll_options` – options or time slots (text/start/end/sort\_order)
* `block_poll_votes` – user votes (unique per poll/user/option to prevent duplicates)
* `block_poll_defense_settings` – defense scheduling parameters (durations, buffers, breaks)

Example uniqueness rule for votes (supports multiple-choice while preventing duplicates):

```sql
ALTER TABLE block_poll_votes
ADD CONSTRAINT unique_vote UNIQUE (poll_id, user_id, option_id);
```
<img width="1056" height="781" alt="Pasted Graphic" src="https://github.com/user-attachments/assets/eec6643b-f6a4-4cbe-b7f5-492ef1898fb6" />
<img width="824" height="701" alt="bLDDRzim3BthLn2vT1zhiTs6d8eEs6qx31XsD6I9Zeb8eYVHsOAj_tsoaZPQRQJDnI0V7p_yIDqcqL56xrHF5i5GD0vLsb8OTDGUZDCfmHy5SAVDTXDv3FOJEuxm" src="https://github.com/user-attachments/assets/051ab897-3542-4578-a74d-423aae810413" />



---

## Capabilities and Roles

Capabilities defined in `db/access.php`:

* `block/poll:addinstance` – add the block (Editing teachers, managers)
* `block/poll:manage` – manage polls (Managers)
* `block/poll:vote` – vote in polls (Teachers/Editing teachers/Managers/Users)

**Typical deployment**

* **Managers**: create and manage polls, export results
* **Teachers/Professors**: view active polls and vote
* **Students**: view results if you enable it (optional; off by default)

---

## Installation

1. Copy this folder to your Moodle blocks directory:

   ```
   moodle/blocks/block_poll
   ```

   or keep the plugin folder name consistent with your environment, e.g., `moodle/blocks/poll`.

2. Visit **Site administration → Notifications** to trigger installation.

3. Apply any pending upgrades.

4. Add the block to the **Site**, **Course**, or **Dashboard** page.

**Requirements**

* Moodle version aligned with your `version.php` (see that file)
* A supported database (PostgreSQL, MySQL/MariaDB, etc.)

---

## Configuration

Site administration → Plugins → Blocks → Poll

* **Maximum options per poll** (`block_poll_maxoptions`)
* **Allow multiple votes per user** (`block_poll_allowmultiple`)

You can further refine permissions via **Site administration → Users → Permissions → Define roles**.

---

## Usage

### For Managers

1. Open the Poll block in a page where it’s added.
2. Click **Create Poll**, set:

   * **Type**: single or multiple choice
   * **Mode**: text options, time slots, or custom timeslots with defense parameters
3. Add options:

   * **Text**: simple strings
   * **Time**: start/end times per option
   * **Custom timeslot**: defense duration, buffers, number of defenses, breaks
4. Publish. Use the dashboard to:

   * View participation, votes, and status
   * Export to Excel
   * Delete individual or multiple polls

### For Teachers/Professors

* See **Active Polls**, vote once per poll (for single choice) or as allowed (for multiple choice).
* Votes are permanent by design (departmental auditability).

---

## AJAX API (Overview)

Endpoints are routed by `ajax_handler.php` and protected by Moodle auth and `sesskey`.

* `create_poll` – Create polls with options
* `delete_poll` / `bulk_delete_polls` – Remove poll(s) with cascade
* `get_poll_statistics` – Participation metrics and per-option counts
* `get_professor_details` – Individual participation detail
* `submit_vote` – Single choice vote
* `submit_multiple_choice_vote` – Multiple selections
* `get_poll_results` – Export to Excel

All responses are JSON except file downloads. Use Moodle’s `require_login()` and capability checks for server-side security.

---

## Internationalization

* English: `lang/en/block_poll.php`
* Russian: `lang/ru/block_poll.php`

UI strings are loaded via Moodle’s `get_string()`. The frontend mirrors labels and supports dynamic updates.

---

## Security

* **Authentication**: `require_login()`
* **CSRF**: `require_sesskey()`
* **Authorization**: Moodle capabilities for manage/vote/addinstance
* **DB Safety**: Moodle DB API for queries, foreign keys with cascade
* **Vote Integrity**: uniqueness constraints and server-side checks

---

## Development

* JavaScript modules are AMD-style under `amd/src/`.
* Keep CSS aligned with Moodle themes using variables in `styles/`.
* Database changes go in `db/install.xml` and `db/upgrade.php` with version bumps in `version.php`.
* Add new strings to both `lang/en` and `lang/ru`.

### Local setup tips

* Enable developer mode in Moodle for verbose debugging.
* Test with multiple roles to verify capability boundaries.
* Use realistic datasets to verify export and statistics performance.

---

## Roadmap

* Optional anonymous voting mode (with aggregate-only exports)
* Per-course scoping presets for faculties/departments
* Granular visibility controls for students
* Additional export formats (CSV/JSON)


## Interface
<img width="549" height="912" alt="Screenshot 2025-09-05 at 2 20 53 AM" src="https://github.com/user-attachments/assets/73065cd5-a43f-4ab9-b0a8-45bfb2c753f8" />
<img width="578" height="616" alt="Transportation Services" src="https://github.com/user-attachments/assets/9058e43a-3148-4328-8ba9-eea390f4fc82" />
<img width="667" height="634" alt="Screenshot 2025-09-05 at 2 22 17 AM" src="https://github.com/user-attachments/assets/30733113-a4e0-447c-a85b-485adb4628ca" />
<img width="669" height="507" alt="student Housing reedback" src="https://github.com/user-attachments/assets/23a74dd0-71dd-4933-a6ec-6e2d7660731f" />
<img width="703" height="628" alt="Screenshot 2025-09-05 at 2 20 24 AM" src="https://github.com/user-attachments/assets/52300426-251c-44eb-a5c0-62a21953ef8d" />

## WorkFlow

<img width="407" height="1767" alt="hLF1ZjD03BtdAwoza4gLUaNT0qiNEB7iIfNA4KqJfnbrCixQdYqLwfKFu1Fv4cOoOHKXRU103ncDv_dvFTkz4JMieNF5AxZlrP8t3aJFplf38KVhUyEc0rEfVR9w" src="https://github.com/user-attachments/assets/f778ada4-790a-4575-843f-eadec9a41e8b" />


