# Day 1

## Entry 1

### Task

Set up the Laravel project and configure the local development environment.

### Prompt

I asked the AI how to set up the Laravel project using Laravel Herd, Vue, TypeScript, and MySQL, and how to troubleshoot the setup.

### Suggested Solution

The AI suggested creating the project with the Laravel Vue starter kit, using Laravel Herd for the PHP/Laravel environment, and connecting the project to the local MySQL server provided by XAMPP.

### My Verification

I ran the project, connected it to MySQL, successfully ran the database migrations, and verified that the authentication pages worked.

### My Changes

I created the Laravel project, configured the `.env` database settings, ran the migrations, and verified the initial application setup.

## Entry 2

### Task

Add the required Internship Progress section to the dashboard.

### Prompt

I asked the AI how to add the Internship Progress section using Laravel, Inertia, Vue, and TypeScript.

### Suggested Solution

The AI suggested creating a separate Vue component with typed TypeScript props and passing the Internship Progress data from the Laravel dashboard route through Inertia.

### My Verification

I logged in and confirmed that the Internship Progress section appeared on the dashboard and remained visible after refreshing the page.

### My Changes

I created the `InternshipProgress.vue` component and added the required Internship Progress data to the dashboard route.

## Entry 3

### Task

Replace the hardcoded student name with the authenticated user's name and verify the implementation.

### Prompt

I asked the AI how to use the authenticated user's existing data instead of hardcoding the student name, and how to test the required dashboard behavior.

### Suggested Solution

The AI suggested using the authenticated user already provided through Inertia shared props and passing the user's name to the Internship Progress component. It also suggested adding feature tests for guest access, authenticated dashboard access, and the Internship Progress data.

### My Verification

I logged in with the test account and confirmed that the correct user name appeared on the dashboard. I also ran `php artisan test` and `npm run type-check`. The applicable tests passed and the TypeScript check completed without errors.

### My Changes

I replaced the hardcoded student name with the authenticated user's name, added the dashboard feature test, and corrected an initial Inertia test assertion after it failed.

# Day 2

## Entry 4

### Task

Create the Project database model, migration, factory, and seed data.

### Prompt

I asked the AI how to implement the Project model and migration, define the User and Project relationships, and create realistic factory and seed data.

### Suggested Solution

The AI suggested creating a `Project` model with a migration, adding a foreign key from projects to users with cascade deletion, adding the required relationships and casts, and using a factory and seeder to generate development data.

### My Verification

I ran the migrations and seeded a fresh local database. I also ran the test suite and verified that the model relationships and cascade deletion worked.

### My Changes

I created the Project model and migration, updated the User model, created `ProjectFactory`, and updated `DatabaseSeeder` with one demo user and ten sample projects.

## Entry 5

### Task

Test Project listing, authentication, ownership, ordering, and empty state.

### Prompt

I asked the AI to create Pest feature tests covering the Project listing requirements from the assignment.

### Suggested Solution

The AI suggested tests for guest access, authenticated access, project ownership, newest-first ordering, and an empty project list.

### My Verification

I ran the focused Project tests and then the full Laravel test suite. The final result was 33 tests passed, 3 skipped, 0 failed, with 114 assertions.

### My Changes

I added the Project feature tests and verified that the full test suite passed.

## Entry 6

### Task

Review the Project page and make sure the empty state follows the Day 2 assignment.

### Prompt

I asked the AI to review the Project page and identify any missing requirements from the assignment.

### Suggested Solution

The AI suggested making the empty state more useful by explaining what happens when the user has no projects.

### My Decision

I changed the empty state message rather than adding a project creation form. Project creation is outside the scope of Day 2, so adding a create form would have introduced functionality that was not required by the assignment.

### My Verification

I ran the full project quality checks after the change. PHP linting, PHPStan, TypeScript checking, the Vite build, the Laravel test suite, and the frontend formatting/linting checks all passed.
