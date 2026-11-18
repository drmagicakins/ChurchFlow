# CHURCHFLOW — SECURE PRODUCTION FTP/FTPS DEPLOYMENT INSTRUCTIONS

## ROLE

Act as a Senior Laravel DevOps Engineer, Production Deployment Specialist, Linux/Shared Hosting Administrator, Database Administrator, and Application Security Engineer.

I have completed the development of **ChurchFlow**, a Laravel-based Church Management SaaS platform.

Your task is to inspect the completed application, prepare it for production, and upload it to my hosting server using **Explicit FTPS**.

This is a deployment task, not a redevelopment task.

## 1. ABSOLUTE RULE: PRESERVE THE COMPLETED APPLICATION

Before doing anything:

- Inspect the entire project.
- Review the README, documentation, environment configuration, routes, migrations, dependencies and deployment instructions.
- Identify the Laravel and PHP versions.
- Identify all required PHP extensions.
- Identify the frontend build system.
- Identify all existing modules and integrations.

**Do not redesign, rewrite, restructure, remove, or modify working application features.**

Do not delete existing functionality.

Do not make unnecessary code changes.

Only make changes strictly necessary for secure production deployment.

If you identify a problem requiring significant code changes, explain it and request approval before proceeding.

## 2. FTP/FTPS CONNECTION

Use the following connection details:

- Protocol: Explicit FTPS
- FTP Host: ftp.churchflow.com.ng
- Port: 21
- Username: Use the updated FTP username supplied through the secure credential input.
- Password: Use the newly rotated password supplied through the secure credential input.

IMPORTANT:

- Never use the previously exposed password.
- Never write credentials into source files.
- Never commit credentials to Git.
- Never display passwords in terminal logs.
- Never store credentials in deployment documentation.
- Never disable TLS certificate verification merely to force a connection.
- Verify that the connection is encrypted before transferring files.

If Explicit FTPS is unavailable, stop and report the issue. Do not silently downgrade to insecure FTP.

## 3. PRODUCTION TARGET

Domain:

https://churchflow.com.ng

Subdomain and additional application environments should not be modified unless explicitly authorized.

Before uploading, inspect the remote directory structure.

Identify:

- Hosting account root
- Public web directory/document root
- Existing application files
- Existing Laravel deployment, if any
- Existing .env file
- Existing uploaded user files
- Existing storage directories

Do not assume the document root is the Laravel project root.

## 4. LARAVEL DEPLOYMENT ARCHITECTURE

Deploy Laravel securely.

The preferred structure is:

- Laravel application files outside the publicly accessible web root.
- Only the contents of Laravel's public directory accessible through the domain document root.
- The document root must point to the Laravel public directory.

If the hosting environment does not permit this structure, inspect the available alternatives and explain the security implications before proceeding.

Never expose:

- .env
- app/
- bootstrap/
- config/
- database/
- resources/
- routes/
- storage/
- vendor/
- composer files
- private application backups
- logs

through public HTTP access.

Do not solve document-root problems by exposing the entire Laravel project.

## 5. PRODUCTION PREPARATION

Inspect and prepare:

- Composer dependencies
- Production PHP configuration
- Frontend assets
- Vite build
- Laravel configuration
- Storage permissions
- Cache configuration
- Session configuration
- Queue configuration
- Database configuration
- Application URL
- HTTPS configuration

Use production dependencies where appropriate.

If necessary, run:

composer install --no-dev --optimize-autoloader

Build frontend assets using the project's existing build scripts.

Do not upload node_modules.

Do not assume Composer or Node.js is available on the hosting server.

If the server cannot run Composer, build the required dependencies locally and upload the appropriate production files.

## 6. ENVIRONMENT CONFIGURATION

The production .env must be created or configured separately.

Never upload the local development .env file.

Never overwrite an existing production .env without inspecting and backing it up.

Use production values for:

APP_ENV=production
APP_DEBUG=false
APP_URL=https://churchflow.com.ng

Database credentials must be obtained through a secure configuration process.

Do not invent database credentials.

Do not expose environment values in logs or reports.

Ensure application keys and secrets are handled securely.

## 7. PAYMENT INTEGRATION — NOT CONFIGURED YET

Payment gateway configuration is pending.

Do not invent Paystack, Flutterwave, or any other payment credentials.

Do not simulate successful payments.

Do not bypass subscription verification.

Do not activate church accounts through an unverified payment process.

Preserve the existing payment architecture and ensure that payment-dependent operations fail safely until valid credentials and webhook configuration are supplied.

If the application currently depends on payment configuration to boot, identify the issue and report it before changing anything.

## 8. SMTP / EMAIL — NOT CONFIGURED YET

SMTP configuration is also pending.

Do not invent SMTP credentials.

Do not configure fake mail servers.

Do not claim emails are being delivered.

Keep email functionality safely configurable and report the exact production settings required.

Where appropriate, use a safe mail configuration that does not send real messages until SMTP is configured.

Do not remove the email notification functionality.

Remember: ChurchFlow subscription includes email, while bulk SMS is separately charged. Do not alter this business logic.

## 9. DATABASE SAFETY

Inspect the production database configuration.

Do not:

- Drop existing databases.
- Delete existing tables.
- Run destructive migrations.
- Truncate production records.
- Reset production data.
- Replace existing production data with development data.

Before running migrations:

1. Confirm the correct production database.
2. Check the existing migration status.
3. Back up the database.
4. Identify any destructive migration.
5. Request my approval before running potentially destructive operations.

If you cannot access the database, report that clearly.

FTP alone does not execute Laravel Artisan commands.

Use an authorized server terminal, SSH, cPanel Terminal, or another approved method if available.

Never pretend an Artisan command has run when it has not.

## 10. FILE TRANSFER RULES

Upload the required production application files.

Exclude:

- .git/
- node_modules/
- local development caches
- unnecessary test artifacts
- temporary files
- local backups
- development-only files
- sensitive local configuration

Include the production dependencies and compiled assets required to run the application.

Preserve:

- Existing uploaded user files
- Existing production storage
- Existing configuration
- Existing database records
- Existing public assets where they are still required

Do not blindly overwrite files.

Compare remote and local files before replacing existing files.

If a remote file could contain important production data or configuration, back it up first.

## 11. STORAGE AND PERMISSIONS

Check Laravel storage requirements.

Ensure the application can write to:

- storage/
- bootstrap/cache/

Use appropriate secure permissions.

Do not use 777 permissions as a shortcut.

Verify that storage links are correctly configured.

Do not expose private storage files publicly.

## 12. HTTPS AND SECURITY

Verify that HTTPS works.

Check:

- SSL certificate
- HTTPS redirection
- Secure session cookies
- APP_DEBUG=false
- Production error handling
- Directory listing protection
- Sensitive file access restrictions
- Laravel public entry point
- Upload directory security

Do not expose stack traces or configuration details to visitors.

## 13. DEPLOYMENT CHECKLIST

After uploading, verify:

- Domain resolves.
- Application loads.
- Laravel routes work.
- Login page loads.
- Authentication works where database configuration permits.
- CSS loads.
- JavaScript loads.
- Images load.
- Fonts load.
- Dashboard assets load.
- Public pages work.
- Storage access works.
- No critical 403, 404 or 500 errors occur.
- No sensitive files are publicly accessible.

Check browser console errors where possible.

Check Laravel logs where authorized.

## 14. DO NOT CLAIM SUCCESS WITHOUT VERIFICATION

Distinguish between:

- Files successfully uploaded.
- Laravel successfully configured.
- Database successfully connected.
- Migrations successfully executed.
- Application successfully running.
- Payment integration configured.
- SMTP successfully configured.

These are separate deployment milestones.

Do not report a successful production deployment if only the file upload has succeeded.

## 15. FINAL REPORT

When finished, provide a detailed report containing:

1. Files inspected.
2. Hosting directory structure identified.
3. FTPS connection result.
4. Files uploaded.
5. Files excluded.
6. Files preserved.
7. Environment configuration status.
8. Database connection status.
9. Migration status.
10. Storage and permission status.
11. SSL/HTTPS status.
12. Application accessibility status.
13. Errors discovered.
14. Security concerns.
15. Payment configuration still required.
16. SMTP configuration still required.
17. Outstanding deployment tasks.

Provide a clear distinction between completed tasks and pending tasks.

## FINAL INSTRUCTION

Work carefully and systematically.

The priority is to deploy the completed ChurchFlow application without damaging existing functionality, exposing sensitive files, losing data, or introducing security vulnerabilities.

**Do not redesign the application. Do not remove existing features. Do not fabricate configuration. Do not execute destructive operations without approval.**

Begin with project inspection and remote hosting inspection. Then prepare a deployment plan and proceed with the safe, approved upload steps.
