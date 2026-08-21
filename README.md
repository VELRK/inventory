# SYNCR — Real Estate Inventory Management Portal

SaaS product for promoters and marketing companies.

- **Backend:** CodeIgniter 3 REST API + MySQL (`syncr_inventory`)
- **Frontend:** React (Vite) admin panel — teal / muted-gold theme
- **Auth:** Email + password, Bearer token
- **Mail:** SMTP settings in the admin Settings page; every key workflow queues/sends a notification

## Start (XAMPP)

Apache on this machine is **port 8080** (IIS already uses 80). MySQL user `root` with empty password.

1. Start **Apache** and **MySQL** in XAMPP.
2. Database is already created as `syncr_inventory` (re-import if needed):

```bat
C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
C:\xampp\mysql\bin\mysql.exe -u root syncr_inventory < database\seed.sql
```

3. API: [http://localhost:8080/inventory/](http://localhost:8080/inventory/)  
   Docs catalog: [http://localhost:8080/inventory/index.php/api/docs](http://localhost:8080/inventory/index.php/api/docs)

4. Frontend:

```bat
cd frontend
npm install
npm run dev
```

Open [http://localhost:5173](http://localhost:5173)

## Test logins (also under Settings)

| Role | Email | Password |
|---|---|---|
| Promoter / Admin | `velrke@gmail.com` | `Admin@123` |
| Marketing Team Admin (ABC) | `teamadmin@abc.test` | `TeamAdmin@123` |
| Marketing Team User (ABC) | `user@abc.test` | `TeamUser@123` |
| Horizon Team Admin | `hari@horizon.test` | `TeamUser@123` |

## Mail

Settings → Mail configuration:

- `mail_enabled` = `1` to actually send via SMTP
- `mail_enabled` = `0` (default) still **logs** every notification in `mail_logs` (login-safe for local XAMPP)

Events mailed: user created, password reset, block request submitted/approved/rejected, inventory status change, booking created, registration created, company created, mail test.

## Roles

| Role | Access |
|---|---|
| Promoter / Admin | Projects, inventory, pricing, companies, bookings, registrations, reports, schema studio, export |
| Marketing Team Admin | Own users, assigned projects/inventory, block requests |
| Marketing Team User | Assigned inventory + submit block requests only |

Marketing companies never see another company's data. They cannot edit inventory or pricing.

## Schema Studio (frontend)

Admins can **ADD COLUMN** and run **SELECT / SHOW / DESCRIBE**.  
**DELETE, DROP, TRUNCATE** (and ALTER DROP) are rejected by `Schema_guard`.

## Tests

```bat
php tests\run.php
cd frontend && npm test
```

## API shape

Success:

```json
{ "success": true, "message": "OK", "data": {} }
```

Error:

```json
{ "success": false, "error": { "code": "VALIDATION_ERROR", "message": "...", "details": {} } }
```

Header: `Authorization: Bearer {token}`

Use the in-app **API Tester** (Postman-style) for input/output of every endpoint, or import `docs/syncr.postman_collection.json`.
