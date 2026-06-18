# FindPoint — Lost & Found Platform

A full-stack lost-and-found platform built with PHP and MySQL. People and
institutions (schools, churches, NGOs, offices) can report lost or found
items, browse listings, file a claim on something that's theirs, and chat
directly with the other person to arrange a hand-over.

## Features

- Individual and institution accounts (schools, churches, NGOs, police posts, hospitals, offices)
- Post a lost or found item with a photo, category, location, and date
- Browse and filter listings by keyword, type, category, and status, with pagination
- Claim an item with a proof message, which opens a private chat with the poster
- Approve or reject claims received on your own items (approving one auto-rejects the rest)
- Real-time-feeling chat (polls for new messages every few seconds) with an inbox showing unread counts
- Edit your profile, change your password, and manage your own listings (edit / resolve / delete)

## Requirements

- PHP 8.x with the `mysqli` and `fileinfo` extensions
- MySQL or MariaDB
- A web server (Apache/Nginx) or PHP's built-in server for local testing

## Setup

1. **Create the database.** Import the schema, which also creates the
   database itself:
   ```
   mysql -u root -p < database/schema.sql
   ```
2. **Set your database credentials.** Open `config/db.php` and update the
   host/username/password/database name if they differ from the defaults
   (`localhost` / `root` / no password / `lost_and_found_db`).
3. **Make the uploads folders writable.** `uploads/items/` and
   `uploads/profiles/` are created automatically the first time someone
   uploads a photo, but on some servers you may need to `chmod 755 uploads`
   (or `775`, depending on your web server's user) so PHP can write to them.
4. **Point your web server at the project root**, or test locally with:
   ```
   php -S localhost:8000
   ```

## Demo accounts

The schema seeds two accounts for trying the site out — delete this section
from `database/schema.sql` (and the two `INSERT INTO users` rows) before
handing this off to a real client.

| Type | Email | Password |
|---|---|---|
| Individual | wanjiku.demo@example.com | Password123 |
| Institution | ackjericho.demo@example.com | Password123 |

## Notes on scope

- **Chat is free for now.** `chat.php` has a comment marking exactly where a
  future "payment-gating" check would go — e.g. blocking new messages after
  a free limit until a payment (such as M-Pesa) is confirmed. Nothing is
  enforced yet; this is intentionally left for a later phase.
- **No admin panel or institution verification.** Anyone can register as an
  "institution" without proof. A future version could add an admin role to
  approve institution accounts and moderate listings.
- **No CSRF tokens.** Kept simple to match the rest of the codebase's level
  of complexity — worth adding if this goes into real production use.
- Item photos and profile photos are validated by their real file content
  (not just the filename) and capped at 5MB.
