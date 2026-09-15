# Import Templates

Templates for the **Students** and **NFC Cards** import buttons in the admin panel
(`http://103.148.246.91:8003/admin`). Column headers below match exactly what each
importer expects, so Filament will auto-map them — no manual mapping step needed.

Two formats of each are provided:

- **`.xlsx`** — use this one for actually typing/pasting data. The `id_number`,
  `uid`, and `student` columns are pre-formatted as **Text**, so Excel won't
  mangle numeric-looking values (stripping leading zeros like `0012345`, or
  switching a long UID to scientific notation). This is the safe one to edit.
- **`.csv`** — a plain reference copy showing the expected layout. The import
  feature only accepts CSV, so once your `.xlsx` is filled in, use Excel's
  **File → Save As → CSV** to produce the file you actually upload.

Delete the example rows before entering your real data; keep the header row.

## students_import_template.csv

| Column | Required | Notes |
|---|---|---|
| `id_number` | Yes | Unique. Re-importing with an existing `id_number` **updates** that student instead of creating a duplicate. |
| `first_name` | Yes | |
| `last_name` | Yes | |
| `course` | Yes | Free text, e.g. `BSIT`. |
| `year_level` | Yes | Free text, e.g. `1st Year`–`5th Year` (matches the dropdown in the admin form). |
| `department` | Yes | Free text, e.g. `CCS`. |
| `status` | No | `active` or `inactive`. Defaults to `active` if left blank. |

## nfc_cards_import_template.csv

| Column | Required | Notes |
|---|---|---|
| `uid` | Yes | The card's UID/serial number. Unique. Re-importing with an existing `uid` **updates** that card instead of duplicating it. |
| `student` | No | The student's `id_number` to assign this card to. Must already exist in the students table — import students first. Leave blank for an unassigned card. |
| `status` | No | `active`, `lost`, or `revoked`. Defaults to `active` if left blank. |

**Import order matters**: import students before cards, since a card can only be
linked to a student that already exists.
