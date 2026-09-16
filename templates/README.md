# Import Templates

Templates for the import buttons in the admin panel (`http://103.148.246.91:8003/admin`).
Column headers below match exactly what each importer expects, so Filament will
auto-map them — no manual mapping step needed.

Two formats are provided for the data-entry templates:

- **`.xlsx`** — use this one for actually typing/pasting data. The `id_number`,
  `uid`, and `student` columns are pre-formatted as **Text**, so Excel won't
  mangle numeric-looking values (stripping leading zeros like `0012345`, or
  switching a long UID to scientific notation). This is the safe one to edit.
- **`.csv`** — a plain reference copy showing the expected layout, and what
  you actually upload — the import feature only accepts CSV. Once your
  `.xlsx` is filled in, use Excel's **File → Save As → CSV** to produce it.

Delete the example rows before entering your real data; keep the header row.

## students_import_template.csv — Students → Import

| Column | Required | Notes |
|---|---|---|
| `id_number` | Yes | Unique. Re-importing with an existing `id_number` **updates** that student instead of creating a duplicate. |
| `first_name` | Yes | |
| `last_name` | Yes | |
| `course` | Yes | Free text, e.g. `BSIT`. |
| `year_level` | Yes | Free text, e.g. `1st Year`–`5th Year` (matches the dropdown in the admin form). |
| `department` | Yes | Free text, e.g. `CCS`. |
| `status` | No | `active` or `inactive` (case-insensitive). Defaults to `active` if left blank. |

## employees_import_template.csv — Employees → Import

| Column | Required | Notes |
|---|---|---|
| `id_number` | Yes | Unique. Re-importing with an existing `id_number` **updates** that employee instead of creating a duplicate. |
| `first_name` | Yes | |
| `last_name` | Yes | |
| `department` | Yes | Free text, e.g. `Human Resources`. |
| `status` | No | `active` or `inactive` (case-insensitive). Defaults to `active` if left blank. |

No course/year level — that's students-only.

## nfc_cards_import_template.csv — NFC Cards → Import

| Column | Required | Notes |
|---|---|---|
| `uid` | Yes | The card's UID/serial number. Unique. Re-importing with an existing `uid` **updates** that card instead of duplicating it. |
| `student` | No | The **student or employee's** `id_number` to assign this card to (column is named "student" for historical reasons, but matches either). Must already exist — import people before cards. Leave blank for an unassigned card. |
| `status` | No | `active`, `lost`, or `revoked`. Defaults to `active` if left blank. |

Each person can have at most **3 active cards** — this limit is enforced in the
admin UI, but **not** during CSV import, so a bulk import can push someone over
it. Check for that after a large import if it matters for your workflow.

**Import order matters**: import students/employees before cards, since a card
can only be linked to a person that already exists.

## deactivate_cards_import_template.csv — NFC Cards → Deactivate Cards

A separate, simpler upload for bulk-marking existing cards as no longer active
(lost/stolen batch, decommissioning old cards, etc.). This one only ever
*updates* cards — it never creates one, and a UID that doesn't match any
existing card fails that row loudly (shows up in Failed Rows) rather than
being silently ignored.

| Column | Required | Notes |
|---|---|---|
| `uid` | Yes | Must match an existing card's UID exactly, or the row fails. |
| `status` | No | `lost` or `revoked`. Defaults to `revoked` if left blank. |
