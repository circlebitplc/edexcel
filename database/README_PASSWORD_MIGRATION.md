# Password migration

The legacy `update_passwords.php` script has intentionally been removed.

It contained shared hard-coded credentials (`admin123` / `teacher123`) and could
reset every teacher account if the file were reachable through the web server.

For an existing installation:

1. Use the application's normal password/account administration flow.
2. If an emergency database reset is required, generate hashes with PHP
   `password_hash()` from a trusted server shell and update only the intended
   account.
3. Never place a password-reset script with hard-coded credentials inside the
   public web root.

New teacher accounts are generated with a unique temporary password by
`teachers/create.php`.
