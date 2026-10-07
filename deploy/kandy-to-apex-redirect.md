# Legacy kandy.edexcel.college → edexcel.college (CyberPanel / OpenLiteSpeed)

Put this as the **document-root `.htaccess`** for the retired `kandy.edexcel.college`
website (or paste the Rewrite rules into that vHost Conf). Do **not** apply this
on `live.kandy.edexcel.college` or `s3.live.kandy.edexcel.college`.

```apache
RewriteEngine On
RewriteCond %{HTTP_HOST} ^(www\.)?kandy\.edexcel\.college$ [NC]
RewriteRule ^ https://edexcel.college%{REQUEST_URI} [L,R=301]
```

CyberPanel alternative (no file edit):
Websites → kandy.edexcel.college → Manage → Redirects
→ Source `/` → Destination `https://edexcel.college/$1` (or full URI preserve)
→ Type permanent (301).

Expected result:
- `https://kandy.edexcel.college/teachers/` → `https://edexcel.college/teachers/`
- No redirect loop (apex does not redirect back to kandy)
- LiveKit subdomains unchanged
