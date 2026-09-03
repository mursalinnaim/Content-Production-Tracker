# Day 1 Work Log

## Start Time

## Environment

- OS: Windows
- PHP: 8.4.25
- Composer: 2.10.3
- Node.js: 24.19.0
- npm: 11.17.0
- Git: 2.55.0
- MySQL: XAMPP MySQL
- MySQL CLI: Not available in PATH (`mysql --version` not recognized)
- Laravel environment: Laravel Herd

## Tasks Completed

### Testing

- `php artisan test`
  PASS Tests\Feature\DashboardTest
  ✓ guests are redirected to the login page 0.03s
  ✓ authenticated users can visit the dashboard 0.05s
  ✓ authenticated users can see Internship Progress on the dashboard
    - 24 tests passed
    - 3 tests skipped because two-factor authentication is not enabled
    - 0 tests failed
    - 65 assertions
- `npm run type-check`
    - Passed with no TypeScript errors

## Problems

- `mysql --version` was not recognized because the MySQL CLI executable from XAMPP is not available in the system PATH.
- Laravel was still able to connect to the XAMPP MySQL server and migrations completed successfully.

## What I Learned

## End Time



## Day 2 work log

### Start Time

8:00 pm, Thursday, September 3, 2026

### Starting Branch and Commit

* Branch: `feature/project-foundation`
* Starting commit: `7b572f4`

### Test Command

```text
php artisan test
```

### Initial Test Result

* 24 tests passed
* 3 tests skipped
* 0 tests failed
* 65 assertions

### Setup Errors

No setup errors occurred at the start of Day 2.

### Tasks Completed

* Created the project data specification in `docs/PROJECTS-SPEC.md`.
* Added the database relationship between users and projects.
* Created the `Project` model and migration.
* Added the `projects()` relationship to the `User` model.
* Added project factory data using the required content types and statuses.
* Added a development demo user and sample projects through the database seeder.
* Added the Projects controller and authenticated `/projects` route.
* Added the Projects Vue page using Vue 3 and TypeScript.
* Added the Projects navigation item to the application sidebar.
* Displayed project title, content type, status, due date, and project count.
* Added an empty state for users without projects.
* Added feature tests for authentication, ownership, ordering, and the empty state.
* Verified the full test suite.

### What I Learned

I learned how Laravel model relationships connect the `User` and `Project` models, how factories and seeders can create realistic development data, and how Inertia passes typed project data from Laravel to Vue. I also learned how to use the authenticated user when querying projects so that users only receive their own data.

### Problems and Solutions

The project due date was initially displayed in the browser as a full ISO timestamp such as `2026-09-18T00:00:00.000000Z`. The database and factory were working correctly, but Laravel's date cast serialized the value as a date-time string for Inertia. I formatted the value in Vue so that only the `YYYY-MM-DD` portion is displayed.

### Final Test Result

* 33 tests passed
* 3 tests skipped
* 0 tests failed
* 114 assertions

## End Time
1 am, Friday, September 4, 2026
